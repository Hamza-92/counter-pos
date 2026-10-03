<?php

namespace App\Tenancy;

final class TenantAccessUnavailablePage
{
    public function render(string $state, ?string $reason): string
    {
        $template = file_get_contents(resource_path('views/errors/tenant-access-unavailable.html'));
        if ($template === false) {
            return '<!doctype html><title>Tenant access unavailable</title><h1>Tenant access unavailable</h1>';
        }

        $supportEmail = trim((string) config('tenancy.support.email'));
        $supportUrl = trim((string) config('tenancy.support.website'));
        $actions = [];

        if ($supportEmail !== '' && filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            $email = htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8');
            $subject = rawurlencode('Counter POS tenant access support');
            $actions[] = '<a class="button primary" href="mailto:'.$email.'?subject='.$subject.'">Contact support</a>';
        }

        if ($supportUrl !== ''
            && filter_var($supportUrl, FILTER_VALIDATE_URL)
            && in_array(parse_url($supportUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $url = htmlspecialchars($supportUrl, ENT_QUOTES, 'UTF-8');
            $actions[] = '<a class="button" href="'.$url.'" target="_blank" rel="noopener noreferrer">Visit Counter POS</a>';
        }

        return strtr($template, [
            '%%STATE_LABEL%%' => $state === 'archived' ? 'Account archived' : 'Account temporarily restricted',
            '%%REASON%%' => htmlspecialchars($reason ?: 'This Counter POS account is not currently active.', ENT_QUOTES, 'UTF-8'),
            '%%SUPPORT_ACTIONS%%' => implode('', $actions),
        ]);
    }
}
