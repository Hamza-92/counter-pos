<?php

namespace App\Services;

use GuzzleHttp\Client as HttpClient;

class CustomSmsGateway
{
    public function send(string $toPhone, string $message): void
    {
        $apiUrl = env('CUSTOM_SMS_API_URL');
        if (! $apiUrl) {
            throw new \RuntimeException('Custom SMS gateway is not configured');
        }

        $method = strtoupper(env('CUSTOM_SMS_METHOD', 'POST'));
        $contentType = strtolower(env('CUSTOM_SMS_CONTENT_TYPE', 'json'));
        $sender = env('CUSTOM_SMS_SENDER', '');
        $successKeyword = env('CUSTOM_SMS_SUCCESS_KEYWORD');

        $replacements = [
            '{phone}' => $toPhone,
            '{message}' => $message,
            '{sender}' => $sender,
        ];

        $headers = $this->interpolate(
            $this->decodeStoredJson(env('CUSTOM_SMS_HEADERS')),
            $replacements
        );
        $payload = $this->interpolate(
            $this->decodeStoredJson(env('CUSTOM_SMS_PAYLOAD')),
            $replacements
        );

        $finalUrl = strtr($apiUrl, $replacements);

        $options = [
            'headers' => $headers,
            'http_errors' => false,
        ];

        if ($method === 'GET') {
            $options['query'] = $payload;
        } elseif ($contentType === 'form') {
            $options['form_params'] = $payload;
        } else {
            $options['json'] = $payload;
        }

        $client = new HttpClient;
        $response = $client->request($method, $finalUrl, $options);

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Custom SMS gateway returned HTTP '.$status.' '.$body);
        }

        if ($successKeyword && stripos($body, $successKeyword) === false) {
            throw new \RuntimeException('Custom SMS gateway reported a failure: '.$body);
        }
    }

    private function decodeStoredJson($value): array
    {
        if (empty($value)) {
            return [];
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return [];
        }

        $parsed = json_decode($decoded, true);

        return is_array($parsed) ? $parsed : [];
    }

    private function interpolate(array $config, array $replacements): array
    {
        $result = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->interpolate($value, $replacements);
            } elseif (is_string($value)) {
                $result[$key] = strtr($value, $replacements);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
