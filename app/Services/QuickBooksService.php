<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Product;
use App\Models\QuickBooksAudit;
use App\Models\QuickBooksToken;
use App\Models\Sale;
use Illuminate\Support\Facades\Log;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Facades\Account;
use QuickBooksOnline\API\Facades\Customer;
use QuickBooksOnline\API\Facades\Invoice;
use QuickBooksOnline\API\Facades\Item;

class QuickBooksService
{
    private function audit(string $operation, string $level, array $ctx = []): void
    {
        // $ctx keys you can pass: sale_id, realm_id, environment, message, http, request, response, sdk_error, user_id
        try {
            QuickBooksAudit::create([
                'user_id' => $ctx['user_id'] ?? (auth()->id() ?? null),
                'sale_id' => $ctx['sale_id'] ?? null,
                'realm_id' => $ctx['realm_id'] ?? null,
                'environment' => $this->normalizeEnv($ctx['environment'] ?? (config('services.quickbooks.env', 'Development'))),
                'operation' => $operation,
                'level' => $level, // 'info' | 'warning' | 'error'
                'message' => $ctx['message'] ?? null,
                'http_code' => $ctx['http'] ?? null,
                'request_payload' => isset($ctx['request']) ? (is_string($ctx['request']) ? $ctx['request'] : json_encode($ctx['request'])) : null,
                'response_body' => isset($ctx['response']) ? (is_string($ctx['response']) ? $ctx['response'] : json_encode($ctx['response'])) : null,
                'sdk_error' => $ctx['sdk_error'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Never block main flow because of audit logging
            \Log::debug('QuickBooksAudit log failed: '.$e->getMessage());
        }
    }

    /* ---------------- core + tokens ---------------- */

    private function normalizeEnv(?string $e): string
    {
        $e = strtolower(trim($e ?? 'development'));

        return in_array($e, ['development', 'dev', 'sandbox'], true) ? 'Development' : 'Production';
    }

    private function resolveTokenRow(?string $realmId, ?string $environment): ?QuickBooksToken
    {
        $env = $this->normalizeEnv($environment ?? config('services.quickbooks.env', 'Development'));

        if ($realmId) {
            $row = QuickBooksToken::where('realm_id', $realmId)->where('environment', $env)->first();
            if ($row) {
                return $row;
            }
            $row = QuickBooksToken::where('realm_id', $realmId)->latest()->first();
            if ($row) {
                return $row;
            }
        }
        $row = QuickBooksToken::where('environment', $env)->latest()->first();
        if ($row) {
            return $row;
        }

        return QuickBooksToken::latest()->first();
    }

    private function setupDataService(QuickBooksToken $row): DataService
    {
        $env = $this->normalizeEnv($row->environment);

        $ds = DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => config('services.quickbooks.client_id'),
            'ClientSecret' => config('services.quickbooks.client_secret'),
            'accessTokenKey' => $row->access_token,
            'refreshTokenKey' => $row->refresh_token,
            'QBORealmID' => $row->realm_id,
            'baseUrl' => $env,
        ]);

        $logDir = storage_path('logs/qbo');
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $ds->setLogLocation($logDir);
        $ds->throwExceptionOnError(false);

        // refresh if near expiry (tokens last ~1 hour)
        if ($row->access_token_expires_at && now()->addMinute()->gte($row->access_token_expires_at)) {
            $helper = $ds->getOAuth2LoginHelper();
            $newTok = $helper->refreshToken();

            if (method_exists($helper, 'getLastError') && ($err = $helper->getLastError())) {
                Log::warning('QBO token refresh failed', [
                    'realm' => $row->realm_id,
                    'http' => method_exists($err, 'getHttpStatusCode') ? $err->getHttpStatusCode() : null,
                    'body' => method_exists($err, 'getResponseBody') ? $err->getResponseBody() : (string) $err,
                ]);
            } elseif ($newTok) {
                $ds->updateOAuth2Token($newTok);
                $row->update([
                    'access_token' => $newTok->getAccessToken(),
                    'refresh_token' => $newTok->getRefreshToken() ?: $row->refresh_token,
                    'access_token_expires_at' => now()->addSeconds(3600),
                ]);
            }
        }

        return $ds;
    }

    private function qboEscape(string $s): string
    {
        return str_replace("'", "''", $s);
    }

    private function qboEscapeLike(string $s): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $s);
    }

    private function dsError(DataService $ds): ?array
    {
        $err = $ds->getLastError();
        if (! $err) {
            return null;
        }

        return ['http' => $err->getHttpStatusCode(), 'body' => $err->getResponseBody()];
    }

    /* ---------------- Income account (by NAME or fallback) ---------------- */

    private function ensureIncomeAccountId(DataService $ds): array
    {
        if ($nameCfg = trim((string) config('services.quickbooks.income_account_name'))) {
            $nameEsc = $this->qboEscape($nameCfg);
            $hit = $ds->Query("select Id,Name from Account where Name = '$nameEsc' and Active = true");
            if (is_array($hit) && isset($hit[0]->Id)) {
                return ['ok' => true, 'id' => (string) $hit[0]->Id];
            }
            $like = $this->qboEscapeLike($nameCfg);
            $hit2 = $ds->Query("select Id,Name from Account where Name like '%{$like}%' and Active = true order by Name");
            if (is_array($hit2) && isset($hit2[0]->Id)) {
                Log::info('QBO Income account resolved via LIKE', ['config_name' => $nameCfg, 'matched' => $hit2[0]->Name ?? null]);

                return ['ok' => true, 'id' => (string) $hit2[0]->Id];
            }
            Log::warning('Configured Income account name not found', ['name' => $nameCfg]);
        }

        $any = $ds->Query("select Id,Name from Account where AccountType in ('Income','OtherIncome') and Active = true order by Id");
        if (is_array($any) && isset($any[0]->Id)) {
            return ['ok' => true, 'id' => (string) $any[0]->Id];
        }

        // create a standard one if none exist
        $payload = [
            'Name' => 'Sales of Product Income',
            'AccountType' => 'Income',
            'AccountSubType' => 'SalesOfProductIncome',
            'Active' => true,
        ];
        $acc = Account::create($payload);
        $res = $ds->Add($acc);
        if ($e = $this->dsError($ds)) {
            return ['ok' => false, 'error' => 'QBO Create Income Account failed'] + $e;
        }
        Log::info('QBO Income account auto-created', ['id' => $res->Id ?? null, 'name' => $res->Name ?? null]);

        return ['ok' => true, 'id' => (string) ($res->Id ?? '')];
    }

    /* ---------------- mapping: Customer & Item ---------------- */

    private function ensureCustomer(Client $client, DataService $ds): array
    {
        if (! empty($client->quickbooks_id)) {
            $id = $this->qboEscape($client->quickbooks_id);
            $hit = $ds->Query("select Id from Customer where Id = '$id'");
            if (is_array($hit) && isset($hit[0]->Id)) {
                return ['ok' => true, 'id' => (string) $hit[0]->Id];
            }
            $client->quickbooks_id = null;
            $client->save();
        }

        if (! empty($client->email)) {
            $email = $this->qboEscape($client->email);
            $hit = $ds->Query("select Id from Customer where PrimaryEmailAddr.Address = '$email' order by Id");
            if (is_array($hit) && isset($hit[0]->Id)) {
                $client->quickbooks_id = (string) $hit[0]->Id;
                $client->save();

                return ['ok' => true, 'id' => $client->quickbooks_id];
            }
        }

        $display = $this->qboEscape($client->name ?? ('Client#'.$client->id));
        $hit = $ds->Query("select Id from Customer where DisplayName = '$display' order by Id");
        if (is_array($hit) && isset($hit[0]->Id)) {
            $client->quickbooks_id = (string) $hit[0]->Id;
            $client->save();

            return ['ok' => true, 'id' => $client->quickbooks_id];
        }

        $payload = ['DisplayName' => $client->name ?? ('Client#'.$client->id)];
        if (! empty($client->email)) {
            $payload['PrimaryEmailAddr'] = ['Address' => $client->email];
        }
        if (! empty($client->phone ?? null)) {
            $payload['PrimaryPhone'] = ['FreeFormNumber' => (string) $client->phone];
        }

        $res = $ds->Add(Customer::create($payload));
        if ($e = $this->dsError($ds)) {
            return ['ok' => false, 'error' => 'QBO Create Customer failed'] + $e;
        }

        $client->quickbooks_id = (string) ($res->Id ?? null);
        $client->save();

        return ['ok' => true, 'id' => $client->quickbooks_id];
    }

    public function ensureCustomerStandalone(Client $client, ?string $realmId = null, ?string $environment = null): array
    {
        // Resolve the token row (use your existing helper)
        $row = $this->resolveTokenRow($realmId, $environment);
        if (! $row) {
            return ['ok' => false, 'error' => 'No QuickBooks connection found. Connect via /quickbooks/connect first.'];
        }

        // Build the DataService (your existing helper)
        $ds = $this->setupDataService($row);

        // Delegate to your existing private method
        return $this->ensureCustomer($client, $ds);
    }

    private function ensureItem(Product $product, DataService $ds): array
    {
        if (! empty($product->quickbooks_id)) {
            $id = $this->qboEscape($product->quickbooks_id);
            $hit = $ds->Query("select Id from Item where Id = '$id'");
            if (is_array($hit) && isset($hit[0]->Id)) {
                return ['ok' => true, 'id' => (string) $hit[0]->Id];
            }
            $product->quickbooks_id = null;
            $product->save();
        }

        $name = $this->qboEscape($product->name ?? ('Product#'.$product->id));
        $hit = $ds->Query("select Id from Item where Name = '$name' order by Id");
        if (is_array($hit) && isset($hit[0]->Id)) {
            $product->quickbooks_id = (string) $hit[0]->Id;
            $product->save();

            return ['ok' => true, 'id' => $product->quickbooks_id];
        }

        $acct = $this->ensureIncomeAccountId($ds);
        if (! ($acct['ok'] ?? false)) {
            return $acct;
        }

        $payload = [
            'Name' => $product->name ?? ('Product#'.$product->id),
            'Type' => 'Service',
            'Active' => true,
            'IncomeAccountRef' => ['value' => (string) $acct['id']],
        ];
        if (! empty($product->code ?? null)) {
            $payload['Sku'] = (string) $product->code;
        }

        $res = $ds->Add(Item::create($payload));
        if ($e = $this->dsError($ds)) {
            return ['ok' => false, 'error' => 'QBO Create Item failed'] + $e;
        }

        $product->quickbooks_id = (string) ($res->Id ?? null);
        $product->save();

        return ['ok' => true, 'id' => $product->quickbooks_id];
    }

    /* ---------------- invoice helpers ---------------- */

    private function buildInvoiceLines(Sale $sale, DataService $ds): array
    {
        $sale->loadMissing(['saleDetails.product']);
        $lines = [];
        foreach ($sale->saleDetails as $d) {
            $p = $d->product;
            if (! $p) {
                return ['ok' => false, 'error' => "Missing product id {$d->product_id} on detail {$d->id}"];
            }
            $map = $this->ensureItem($p, $ds);
            if (! ($map['ok'] ?? false)) {
                return ['ok' => false] + $map;
            }

            $lines[] = [
                'Amount' => (float) $d->total,
                'DetailType' => 'SalesItemLineDetail',
                'Description' => $p->name ?? '',
                'SalesItemLineDetail' => [
                    'ItemRef' => ['value' => (string) $p->quickbooks_id],
                    'Qty' => (float) $d->quantity,
                    'UnitPrice' => (float) $d->price,
                ],
            ];
        }

        return ['ok' => true, 'lines' => $lines];
    }

    private function getInvoiceMetaById(DataService $ds, string $id)
    {
        $id = $this->qboEscape($id);
        $rows = $ds->Query("select Id, SyncToken, DocNumber from Invoice where Id = '$id'");

        return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
    }

    private function findInvoiceByDocNumber(DataService $ds, string $docNumber)
    {
        $doc = $this->qboEscape($docNumber);
        $rows = $ds->Query("select Id, SyncToken, DocNumber from Invoice where DocNumber = '$doc' order by MetaData.LastUpdatedTime desc");

        return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
    }

    /* ---------------- public: create & update ---------------- */

    public function createInvoice(Sale $sale, ?string $realmId = null, ?string $environment = null): array
    {
        $realmId = $realmId ?? $sale->quickbooks_realm_id ?? null;
        $row = $this->resolveTokenRow($realmId, $environment);

        if (! $row) {
            $this->audit('create', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $realmId,
                'environment' => $environment,
                'message' => 'No QuickBooks connection found. Connect via /quickbooks/connect first.',
            ]);

            return ['ok' => false, 'error' => 'No QuickBooks connection found. Connect via /quickbooks/connect first.'];
        }

        $ds = $this->setupDataService($row);

        // customer
        $sale->loadMissing('client');
        $cust = $this->ensureCustomer($sale->client, $ds);
        if (! ($cust['ok'] ?? false)) {
            $this->audit('create', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $row->realm_id,
                'environment' => $row->environment ?? null,
                'message' => $cust['error'] ?? 'Customer mapping failed',
                'response' => $cust,
            ]);

            return $cust;
        }

        // lines
        $built = $this->buildInvoiceLines($sale, $ds);
        if (! ($built['ok'] ?? false)) {
            $this->audit('create', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $row->realm_id,
                'environment' => $row->environment ?? null,
                'message' => $built['error'] ?? 'Line build failed',
                'response' => $built,
            ]);

            return ['ok' => false, 'error' => $built['error'] ?? 'Line build failed'] + $built;
        }

        // payload (use Ref as DocNumber)
        $payload = [
            'CustomerRef' => ['value' => (string) $sale->client->quickbooks_id],
            'TxnDate' => $sale->date ?: now()->toDateString(),
            'Line' => $built['lines'],
            'PrivateNote' => $sale->notes ?? null,
        ];
        if (! empty($sale->Ref)) {
            $payload['DocNumber'] = (string) $sale->Ref;
        }

        $result = $ds->Add(\QuickBooksOnline\API\Facades\Invoice::create($payload));
        if ($e = $this->dsError($ds)) {
            $this->audit('create', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $row->realm_id,
                'environment' => $row->environment ?? null,
                'message' => 'QBO Add Invoice failed',
                'http' => $e['http'] ?? null,
                'request' => $payload,
                'response' => $e['body'] ?? null,
            ]);

            return ['ok' => false, 'error' => 'QBO Add Invoice failed'] + $e;
        }

        $this->audit('create', 'info', [
            'sale_id' => $sale->id ?? null,
            'realm_id' => $row->realm_id,
            'environment' => $row->environment ?? null,
            'message' => 'Invoice created',
            'request' => $payload,
            'response' => $result,
        ]);

        return ['ok' => true, 'id' => $result->Id ?? null, 'raw' => $result, 'realm' => $row->realm_id];
    }

    public function updateInvoice(\App\Models\Sale $sale, ?string $realmId = null, ?string $environment = null): array
    {
        if (empty($sale->quickbooks_invoice_id) && empty($sale->Ref)) {
            $this->audit('update', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $realmId,
                'environment' => $environment,
                'message' => 'Missing quickbooks_invoice_id and Ref; cannot resolve invoice to update',
            ]);

            return ['ok' => false, 'error' => 'Missing quickbooks_invoice_id and Ref; cannot resolve invoice to update'];
        }

        $realmId = $realmId ?? $sale->quickbooks_realm_id ?? null;
        $row = $this->resolveTokenRow($realmId, $environment);
        if (! $row) {
            $this->audit('update', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $realmId,
                'environment' => $environment,
                'message' => 'No QuickBooks connection found. Connect via /quickbooks/connect first.',
            ]);

            return ['ok' => false, 'error' => 'No QuickBooks connection found. Connect via /quickbooks/connect first.'];
        }

        $ds = $this->setupDataService($row);

        // 1) Try by stored Id via Query
        $existing = null;
        if (! empty($sale->quickbooks_invoice_id)) {
            $existing = $this->getInvoiceMetaById($ds, (string) $sale->quickbooks_invoice_id);
        }
        // 2) Fallback by DocNumber
        if (! $existing && ! empty($sale->Ref)) {
            $existing = $this->findInvoiceByDocNumber($ds, (string) $sale->Ref);
        }

        if (! $existing) {
            if ($e = $this->dsError($ds)) {
                $this->audit('update', 'error', [
                    'sale_id' => $sale->id ?? null,
                    'realm_id' => $row->realm_id,
                    'environment' => $row->environment ?? null,
                    'message' => 'QBO Find Invoice failed',
                    'http' => $e['http'] ?? null,
                    'response' => $e['body'] ?? null,
                ]);

                return ['ok' => false, 'error' => 'QBO Find Invoice failed'] + $e;
            }
            $this->audit('update', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $row->realm_id,
                'environment' => $row->environment ?? null,
                'message' => 'Invoice not found in QBO (update aborted; no create fallback)',
            ]);

            return ['ok' => false, 'error' => 'Invoice not found in QBO (update aborted; no create fallback)'];
        }

        // Ensure customer & items
        $sale->loadMissing('client');
        $cust = $this->ensureCustomer($sale->client, $ds);
        if (! ($cust['ok'] ?? false)) {
            $this->audit('update', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $row->realm_id,
                'environment' => $row->environment ?? null,
                'message' => $cust['error'] ?? 'Customer mapping failed',
                'response' => $cust,
            ]);

            return $cust;
        }

        $built = $this->buildInvoiceLines($sale, $ds);
        if (! ($built['ok'] ?? false)) {
            $this->audit('update', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $row->realm_id,
                'environment' => $row->environment ?? null,
                'message' => $built['error'] ?? 'Line build failed',
                'response' => $built,
            ]);

            return ['ok' => false, 'error' => $built['error'] ?? 'Line build failed'] + $built;
        }

        // Full update with Id + SyncToken + preserve DocNumber
        $full = [
            'Id' => (string) $existing->Id,
            'SyncToken' => (string) $existing->SyncToken,
            'CustomerRef' => ['value' => (string) $sale->client->quickbooks_id],
            'TxnDate' => $sale->date ?: now()->toDateString(),
            'DocNumber' => (string) ($existing->DocNumber ?? ($sale->Ref ?? '')),
            'Line' => $built['lines'],
            'PrivateNote' => $sale->notes ?? null,
        ];

        $merged = \QuickBooksOnline\API\Facades\Invoice::update($existing, $full);
        $result = $ds->Update($merged);
        if ($e = $this->dsError($ds)) {
            $this->audit('update', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $row->realm_id,
                'environment' => $row->environment ?? null,
                'message' => 'QBO Update Invoice failed',
                'http' => $e['http'] ?? null,
                'request' => $full,
                'response' => $e['body'] ?? null,
            ]);

            return ['ok' => false, 'error' => 'QBO Update Invoice failed'] + $e;
        }

        $this->audit('update', 'info', [
            'sale_id' => $sale->id ?? null,
            'realm_id' => $row->realm_id,
            'environment' => $row->environment ?? null,
            'message' => 'Invoice updated',
            'request' => $full,
            'response' => $result,
        ]);

        return [
            'ok' => true,
            'id' => (string) ($result->Id ?? $existing->Id),
            'raw' => $result,
            'realm' => $row->realm_id,
        ];
    }

    /**
     * Delete the invoice in QBO that corresponds to this Sale (by quickbooks_invoice_id or Ref/DocNumber).
     * Never creates, never voids — only deletes. If not found, returns ok=true (already gone).
     */
    public function deleteInvoice(\App\Models\Sale $sale, ?string $realmId = null, ?string $environment = null): array
    {
        if (empty($sale->quickbooks_invoice_id) && empty($sale->Ref)) {
            $this->audit('delete', 'info', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $realmId,
                'environment' => $environment,
                'message' => 'No reference (id/ref) to delete; treated as success',
            ]);

            return ['ok' => true, 'status' => 'no_reference'];
        }

        $realmId = $realmId ?? $sale->quickbooks_realm_id ?? null;
        $row = $this->resolveTokenRow($realmId, $environment);
        if (! $row) {
            $this->audit('delete', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $realmId,
                'environment' => $environment,
                'message' => 'No QuickBooks connection found. Connect via /quickbooks/connect first.',
            ]);

            return ['ok' => false, 'error' => 'No QuickBooks connection found. Connect via /quickbooks/connect first.'];
        }

        $ds = $this->setupDataService($row);

        // Resolve the target invoice
        $existing = null;
        if (! empty($sale->quickbooks_invoice_id)) {
            $existing = $this->getInvoiceMetaById($ds, (string) $sale->quickbooks_invoice_id);
        }
        if (! $existing && ! empty($sale->Ref)) {
            $existing = $this->findInvoiceByDocNumber($ds, (string) $sale->Ref);
        }

        if (! $existing) {
            if ($e = $this->dsError($ds)) {
                $this->audit('delete', 'error', [
                    'sale_id' => $sale->id ?? null,
                    'realm_id' => $row->realm_id,
                    'environment' => $row->environment ?? null,
                    'message' => 'QBO Find Invoice failed',
                    'http' => $e['http'] ?? null,
                    'response' => $e['body'] ?? null,
                ]);

                return ['ok' => false, 'error' => 'QBO Find Invoice failed'] + $e;
            }
            $this->audit('delete', 'info', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $row->realm_id,
                'environment' => $row->environment ?? null,
                'message' => 'Invoice already deleted/not found (treated as success)',
            ]);

            return ['ok' => true, 'status' => 'already_deleted'];
        }

        // Need full entity for Delete
        $full = $ds->FindById('Invoice', (string) $existing->Id);
        if (! $full) {
            if ($e = $this->dsError($ds)) {
                $this->audit('delete', 'error', [
                    'sale_id' => $sale->id ?? null,
                    'realm_id' => $row->realm_id,
                    'environment' => $row->environment ?? null,
                    'message' => 'QBO FindById before Delete failed',
                    'http' => $e['http'] ?? null,
                    'response' => $e['body'] ?? null,
                ]);

                return ['ok' => false, 'error' => 'QBO FindById before Delete failed'] + $e;
            }
            $this->audit('delete', 'info', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $row->realm_id,
                'environment' => $row->environment ?? null,
                'message' => 'Invoice already deleted (FindById returned null)',
            ]);

            return ['ok' => true, 'status' => 'already_deleted'];
        }

        // Try delete (QBO may block if there are linked Payments)
        $delRes = $ds->Delete($full);
        if ($e = $this->dsError($ds)) {
            $this->audit('delete', 'error', [
                'sale_id' => $sale->id ?? null,
                'realm_id' => $row->realm_id,
                'environment' => $row->environment ?? null,
                'message' => 'QBO Delete Invoice failed',
                'http' => $e['http'] ?? null,
                'response' => $e['body'] ?? null,
            ]);

            return ['ok' => false, 'error' => 'QBO Delete Invoice failed'] + $e;
        }

        $this->audit('delete', 'info', [
            'sale_id' => $sale->id ?? null,
            'realm_id' => $row->realm_id,
            'environment' => $row->environment ?? null,
            'message' => 'Invoice deleted',
            'response' => $delRes,
        ]);

        return ['ok' => true, 'id' => (string) $existing->Id, 'realm' => $row->realm_id, 'status' => 'deleted'];
    }
}
