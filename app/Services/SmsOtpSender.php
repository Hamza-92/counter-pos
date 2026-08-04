<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\sms_gateway;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;
use Infobip\Api\SendSmsApi;
use Infobip\Configuration;
use Infobip\Model\SmsAdvancedTextualRequest;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsTextualMessage;
use Twilio\Rest\Client as TwilioClient;

class SmsOtpSender
{
    public function send(string $toPhone, string $message): void
    {
        $settings = Setting::where('deleted_at', '=', null)->first();
        if (! $settings || ! $settings->sms_gateway) {
            throw new \RuntimeException('SMS gateway is not configured');
        }

        $gateway = sms_gateway::where('id', $settings->sms_gateway)->where('deleted_at', '=', null)->first();
        if (! $gateway) {
            throw new \RuntimeException('SMS gateway is not configured');
        }

        if ($gateway->title === 'twilio') {
            $sid = tenant_option('TWILIO_SID');
            $token = tenant_option('TWILIO_TOKEN');
            $from = tenant_option('TWILIO_FROM');
            if (! $sid || ! $token || ! $from) {
                throw new \RuntimeException('Twilio credentials are missing');
            }
            $client = new TwilioClient($sid, $token);
            $client->messages->create($toPhone, ['from' => $from, 'body' => $message]);
            return;
        }

        if ($gateway->title === 'infobip') {
            $BASE_URL = tenant_option('base_url');
            $API_KEY = tenant_option('api_key');
            $SENDER = tenant_option('sender_from');
            if (! $BASE_URL || ! $API_KEY || ! $SENDER) {
                throw new \RuntimeException('Infobip credentials are missing');
            }

            $configuration = (new Configuration)
                ->setHost($BASE_URL)
                ->setApiKeyPrefix('Authorization', 'App')
                ->setApiKey('Authorization', $API_KEY);

            $client = new HttpClient;
            $sendSmsApi = new SendSmsApi($client, $configuration);

            $destination = (new SmsDestination)->setTo($toPhone);
            $sms = (new SmsTextualMessage)
                ->setFrom($SENDER)
                ->setText($message)
                ->setDestinations([$destination]);

            $request = (new SmsAdvancedTextualRequest)->setMessages([$sms]);
            $sendSmsApi->sendSmsMessage($request);
            return;
        }

        if ($gateway->title === 'termii') {
            $url = 'https://api.ng.termii.com/api/sms/send';
            $payload = [
                'to' => $toPhone,
                'from' => tenant_option('TERMI_SENDER'),
                'sms' => $message,
                'type' => 'plain',
                'channel' => 'generic',
                'api_key' => tenant_option('TERMI_KEY'),
            ];
            if (! $payload['from'] || ! $payload['api_key']) {
                throw new \RuntimeException('Termii credentials are missing');
            }

            $client = new HttpClient;
            try {
                $client->post($url, ['json' => $payload]);
            } catch (\Throwable $e) {
                Log::error('Termii SMS Error: '.$e->getMessage());
                throw new \RuntimeException('Failed to send SMS');
            }
            return;
        }

        if ($gateway->title === 'custom') {
            try {
                (new CustomSmsGateway)->send($toPhone, $message);
            } catch (\Throwable $e) {
                Log::error('Custom SMS gateway error: '.$e->getMessage());
                throw new \RuntimeException('Failed to send SMS via custom gateway');
            }
            return;
        }

        throw new \RuntimeException('Unsupported SMS gateway: '.$gateway->title);
    }
}


