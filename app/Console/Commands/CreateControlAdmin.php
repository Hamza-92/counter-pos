<?php

namespace App\Console\Commands;

use App\Models\ControlPlane\SuperAdmin;
use App\Services\ControlPlane\TotpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateControlAdmin extends Command
{
    protected $signature = 'control:admin-create {--name=} {--email=}';

    protected $description = 'Create or securely rotate a control-plane superadmin';

    public function handle(TotpService $totp): int
    {
        if (config('database.connections.control.driver') !== 'mysql') {
            $this->error('The control admin command requires a configured MySQL control database.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Confirm password');

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid name and email are required.');

            return self::FAILURE;
        }

        if (strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->error('Passwords must match and contain at least 12 characters.');

            return self::FAILURE;
        }

        $secret = $totp->generateSecret();
        $recoveryCodes = $totp->generateRecoveryCodes();

        $admin = SuperAdmin::query()->firstOrNew(['email' => $email]);
        $admin->fill([
            'name' => $name,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
        $admin->totp_secret = $secret;
        $admin->recovery_code_hashes = array_map(static fn (string $code) => Hash::make($code), $recoveryCodes);
        $admin->save();

        $this->newLine();
        $this->warn('Store these values securely now. They will not be shown again.');
        $this->line('TOTP secret: '.$secret);
        $this->line('Authenticator URI: '.$totp->provisioningUri($secret, $email));
        $this->line('Recovery codes:');
        foreach ($recoveryCodes as $code) {
            $this->line('  '.$code);
        }

        return self::SUCCESS;
    }
}
