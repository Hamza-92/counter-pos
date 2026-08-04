<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Tenancy\TenantOptionStore;
use Illuminate\Http\Request;

class Payment_gateway_SettingsController extends Controller
{
    // -------------- Get Payment Gateway ---------------\\

    public function Get_payment_gateway(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'payment_gateway', Setting::class);
        $item['stripe_key'] = tenant_option('STRIPE_KEY');
        $item['stripe_secret'] = '';
        $item['deleted'] = false;

        return response()->json(['gateway' => $item], 200);
    }

    public function get_payment_gateway_ws(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Setting::class);
        $item['stripe_key'] = tenant_option('STRIPE_KEY');
        $item['stripe_secret'] = '';
        $item['deleted'] = false;

        return response()->json(['gateway' => $item], 200);
    }

    // -------------- Update  Payment Gateway ---------------\\

    public function Update_payment_gateway(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'payment_gateway', Setting::class);

        if ($request['deleted'] == 'true') {
            $this->storeGatewayValues([
                'STRIPE_KEY' => '',
                'STRIPE_SECRET' => '',
            ]);

        } else {
            $values = ['STRIPE_KEY' => (string) $request->input('stripe_key', '')];
            if ($request->filled('stripe_secret')) {
                $values['STRIPE_SECRET'] = (string) $request->input('stripe_secret');
            }
            $this->storeGatewayValues($values);
        }

        return response()->json(['success' => true]);

    }

    private function storeGatewayValues(array $values): void
    {
        if (config('tenancy.enabled', false)) {
            app(TenantOptionStore::class)->putMany($values);

            return;
        }

        $this->setEnvironmentValue(array_map(
            static fn ($value) => $value === '' ? '' : '"'.$value.'"',
            $values,
        ));
    }

    // -------------- Set Environment Value ---------------\\

    public function setEnvironmentValue(array $values)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
        $str .= "\r\n";
        if (count($values) > 0) {
            foreach ($values as $envKey => $envValue) {

                $keyPosition = strpos($str, "$envKey=");
                $endOfLinePosition = strpos($str, "\n", $keyPosition);
                $oldLine = substr($str, $keyPosition, $endOfLinePosition - $keyPosition);

                if (is_bool($keyPosition) && $keyPosition === false) {
                    // variable doesnot exist
                    $str .= "$envKey=$envValue";
                    $str .= "\r\n";
                } else {
                    // variable exist
                    $str = str_replace($oldLine, "$envKey=$envValue", $str);
                }
            }
        }

        $str = substr($str, 0, -1);
        if (! file_put_contents($envFile, $str)) {
            return false;
        }

        app()->loadEnvironmentFrom($envFile);

        return true;
    }
}
