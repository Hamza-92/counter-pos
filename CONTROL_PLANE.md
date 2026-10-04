# Superadmin control plane

The superadmin control plane manages tenant registration, domain records, tenant database credentials, connection checks, and tenant status. Subscription plans and payment records are managed by the external CRM and are not used to grant or deny tenant access.

## Login

Superadmins sign in with a username and password. Existing admins receive a username based on the part of their email address before `@` when the control migration runs. During transition, the login field also accepts the existing email address.

Create or update an administrator interactively:

```bash
php artisan control:admin-create --name="Administrator" --username=admin --email=admin@example.com
```

The command asks for and confirms a password. Authenticator and recovery codes are no longer generated or accepted.

## Deploying this change

Upload the changed application files and the new control migration, then run:

```bash
php artisan control:migrate --confirm-control
```

This adds and backfills the superadmin `username` field. It does not alter tenant databases, and it does not delete existing plan, subscription, or payment records. Those tables remain available as historical data but have no control-plane routes or screens and no effect on tenant access.

Tenant activation requires a configured tenant database and a verified primary domain. Active tenants are allowed without a local subscription record.

## CRM linkage foundation

Each control-plane tenant can be linked to one CRM application instance through the nullable, unique `tenants.crm_application_instance_id` column. Existing tenants remain unlinked until the CRM integration explicitly associates them.

The tenant also records the last applied allowlisted data template in `data_template_code` and `data_template_version`. Tenant schema state continues to use `schema_version`.

CRM-triggered work reuses `provisioning_runs`. Set `source` to `crm` and set `external_reference` to the CRM operation idempotency key. Both fields are indexed for status reconciliation. Never store database credentials, API secrets, or raw SQL in the run result.

Apply the linkage migration to the control database with:

```bash
php artisan control:migrate --confirm-control
```

## CRM control API

The server-to-server API is available only on the exact control-plane host under `/api/control/v1`. It does not use a browser session and is not registered on tenant domains.

Configure a shared key and a high-entropy secret:

```dotenv
CRM_API_KEY=crm-production
CRM_API_SECRET=replace-with-a-random-secret
CRM_API_CLOCK_SKEW_SECONDS=300
```

Every request must include `X-CRM-Key`, `X-CRM-Timestamp`, `X-CRM-Nonce`, and `X-CRM-Signature`. The signature is a lowercase SHA-256 HMAC of this canonical string:

```text
{unix timestamp}\n{nonce}\n{uppercase method}\n{path with query}\n{sha256 request body}
```

The API rejects expired timestamps, reused nonces, invalid signatures, and requests made through tenant hosts. Every mutating request also requires a UUID `Idempotency-Key`. Repeating the same request with the same key returns the stored response; reusing the key for different request data is rejected.

The available endpoints are:

- `GET /api/control/v1/health`
- `GET /api/control/v1/tenants` (paginated legacy transfer inventory; `search`, `page`, `per_page`)
- `POST /api/control/v1/tenants`
- `GET /api/control/v1/tenants/by-crm-instance/{id}`
- `GET /api/control/v1/tenants/{tenant}` (legacy transfer details)
- `PUT /api/control/v1/tenants/{tenant}/crm-link` (link an existing tenant to a CRM application instance)
- `PUT /api/control/v1/tenants/{tenant}/domain`
- `PUT /api/control/v1/tenants/{tenant}/database`
- `POST /api/control/v1/tenants/{tenant}/database/test`
- `POST /api/control/v1/tenants/{tenant}/migrations`
- `PUT /api/control/v1/tenants/{tenant}/status`
- `GET /api/control/v1/operations/{run}`

General tenant and operation responses intentionally exclude database host, name, username, passwords, and migration credentials. The signed legacy transfer inventory and detail responses include the database name for operator review; they still exclude its host, username, passwords, and migration credentials. Database credentials are encrypted in the control database before storage.

## Hosting automation boundary

Hostinger connects only to the CRM. Its API token must remain in the CRM server environment and must never be configured in CounterPOS. The CRM creates the domain and database through Hostinger, then sends only the resulting domain and database connection details to the signed CounterPOS control API. CounterPOS verifies the database, runs tenant migrations, and changes tenant status.
