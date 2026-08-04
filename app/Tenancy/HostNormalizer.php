<?php

namespace App\Tenancy;

use App\Tenancy\Exceptions\InvalidHostException;

final class HostNormalizer
{
    public function normalize(string $host): string
    {
        if ($host === '' || $host !== trim($host) || str_contains($host, '/') || str_contains($host, ':')) {
            throw new InvalidHostException('The request host is invalid.');
        }

        $host = rtrim(strtolower($host), '.');

        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7f]/', $host)) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii === false) {
                throw new InvalidHostException('The request host is invalid.');
            }
            $host = strtolower($ascii);
        }

        if (
            $host === ''
            || strlen($host) > 253
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))+$/', $host)
        ) {
            throw new InvalidHostException('The request host is invalid.');
        }

        return $host;
    }
}
