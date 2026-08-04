<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\sms_gateway;
use App\Tenancy\TenantOptionStore;
use Illuminate\Http\Request;

class Sms_SettingsController extends Controller
{
    // -------------- Get_sms_config ---------------\\

    public function get_sms_config(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'sms_settings', Setting::class);
        $twilio['TWILIO_SID'] = tenant_option('TWILIO_SID');
        $twilio['TWILIO_FROM'] = tenant_option('TWILIO_FROM');
        $twilio['TWILIO_TOKEN'] = '';

        $termi['TERMI_KEY'] = tenant_option('TERMI_KEY');
        $termi['TERMI_SECRET'] = '';
        $termi['TERMI_SENDER'] = tenant_option('TERMI_SENDER');

        $infobip['base_url'] = tenant_option('base_url');
        $infobip['api_key'] = '';
        $infobip['sender_from'] = tenant_option('sender_from');

        $custom = [
            'api_url' => tenant_option('CUSTOM_SMS_API_URL'),
            'method' => tenant_option('CUSTOM_SMS_METHOD', 'POST'),
            'content_type' => tenant_option('CUSTOM_SMS_CONTENT_TYPE', 'json'),
            'sender' => tenant_option('CUSTOM_SMS_SENDER'),
            'success_keyword' => tenant_option('CUSTOM_SMS_SUCCESS_KEYWORD'),
            'headers' => $this->decodeConfigJson(tenant_option('CUSTOM_SMS_HEADERS')),
            'payload' => $this->decodeConfigJson(tenant_option('CUSTOM_SMS_PAYLOAD')),
        ];

        $sms_gateway = sms_gateway::where('deleted_at', '=', null)->get(['id', 'title']);
        $settings = Setting::where('deleted_at', '=', null)->first();

        if ($settings->sms_gateway) {
            if (sms_gateway::where('id', $settings->sms_gateway)->where('deleted_at', '=', null)->first()) {
                $default_sms_gateway = $settings->sms_gateway;
            } else {
                $default_sms_gateway = '';
            }
        } else {
            $default_sms_gateway = '';
        }

        return response()->json([
            'twilio' => $twilio,
            'termi' => $termi,
            'infobip' => $infobip,
            'custom' => $custom,
            'sms_gateway' => $sms_gateway,
            'default_sms_gateway' => $default_sms_gateway,
        ], 200);
    }

    // -------------- update_twilio_config ---------------\\

    public function update_twilio_config(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'sms_settings', Setting::class);

        $this->setEnvironmentValue([
            'TWILIO_SID' => $request['TWILIO_SID'] !== null ? '"'.$request['TWILIO_SID'].'"' : '"'.tenant_option('TWILIO_SID').'"',
            'TWILIO_TOKEN' => $request['TWILIO_TOKEN'] !== null ? '"'.$request['TWILIO_TOKEN'].'"' : '"'.tenant_option('TWILIO_TOKEN').'"',
            'TWILIO_FROM' => $request['TWILIO_FROM'] !== null ? '"'.$request['TWILIO_FROM'].'"' : '"'.tenant_option('TWILIO_FROM').'"',
        ]);

        return response()->json(['success' => true]);

    }

    // -------------- Update update_termi_config ---------------\\

    public function update_termi_config(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'sms_settings', Setting::class);

        $this->setEnvironmentValue([
            'TERMI_KEY' => $request['TERMI_KEY'] !== null ? '"'.$request['TERMI_KEY'].'"' : '"'.tenant_option('TERMI_KEY').'"',
            'TERMI_SECRET' => $request['TERMI_SECRET'] !== null ? '"'.$request['TERMI_SECRET'].'"' : '"'.tenant_option('TERMI_SECRET').'"',
            'TERMI_SENDER' => $request['TERMI_SENDER'] !== null ? '"'.$request['TERMI_SENDER'].'"' : '"'.tenant_option('TERMI_SENDER').'"',

        ]);


        return response()->json(['success' => true]);

    }

    // -------------- update_infobip_config ---------------\\

    public function update_infobip_config(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'sms_settings', Setting::class);

        $this->setEnvironmentValue([
            'base_url' => $request['base_url'] !== null ? '"'.$request['base_url'].'"' : '"'.tenant_option('base_url').'"',
            'api_key' => $request['api_key'] !== null ? '"'.$request['api_key'].'"' : '"'.tenant_option('api_key').'"',
            'sender_from' => $request['sender_from'] !== null ? '"'.$request['sender_from'].'"' : '"'.tenant_option('sender_from').'"',
        ]);


        return response()->json(['success' => true]);

    }

    // -------------- update_custom_config ---------------\\

    public function update_custom_config(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'sms_settings', Setting::class);

        $request->validate([
            'api_url' => 'required|url',
            'method' => 'required|in:GET,POST,PUT',
            'content_type' => 'required|in:json,form',
            'headers' => 'nullable|array',
            'payload' => 'nullable|array',
            'sender' => 'nullable|string|max:64',
            'success_keyword' => 'nullable|string|max:128',
        ]);

        $headersEncoded = $this->encodeConfigJson($request->input('headers', []));
        $payloadEncoded = $this->encodeConfigJson($request->input('payload', []));

        $this->setEnvironmentValue([
            'CUSTOM_SMS_API_URL' => '"'.$request->input('api_url').'"',
            'CUSTOM_SMS_METHOD' => '"'.$request->input('method').'"',
            'CUSTOM_SMS_CONTENT_TYPE' => '"'.$request->input('content_type').'"',
            'CUSTOM_SMS_SENDER' => '"'.($request->input('sender') ?? '').'"',
            'CUSTOM_SMS_SUCCESS_KEYWORD' => '"'.($request->input('success_keyword') ?? '').'"',
            'CUSTOM_SMS_HEADERS' => '"'.$headersEncoded.'"',
            'CUSTOM_SMS_PAYLOAD' => '"'.$payloadEncoded.'"',
        ]);


        return response()->json(['success' => true]);
    }

    // -------------- Encode / decode JSON config safely in .env ---------------\\

    private function encodeConfigJson($value): string
    {
        if (empty($value)) {
            return '';
        }

        return base64_encode(json_encode($value));
    }

    private function decodeConfigJson($value)
    {
        if (empty($value)) {
            return new \stdClass;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return new \stdClass;
        }

        $parsed = json_decode($decoded, true);
        if (! is_array($parsed) || empty($parsed)) {
            return new \stdClass;
        }

        return $parsed;
    }

    // -------------- update_Default_SMS ---------------\\

    public function update_Default_SMS(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'sms_settings', Setting::class);

        if ($request['default_sms_gateway'] != 'null') {
            $sms_gateway = $request['default_sms_gateway'];
        } else {
            $sms_gateway = null;
        }

        Setting::whereId(1)->update([
            'sms_gateway' => $sms_gateway,
        ]);

    }

    // -------------- Set Environment Value ---------------\\

    public function setEnvironmentValue(array $values)
    {
        if (config('tenancy.enabled', false)) {
            $normalized = [];
            foreach ($values as $key => $value) {
                $value = (string) $value;
                if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
                    $value = substr($value, 1, -1);
                }
                $normalized[$key] = $value;
            }
            app(TenantOptionStore::class)->putMany($normalized);

            return true;
        }

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
