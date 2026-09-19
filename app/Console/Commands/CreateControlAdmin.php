<?php

namespace App\Console\Commands;

use App\Models\ControlPlane\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class CreateControlAdmin extends Command
{
    protected $signature = 'control:admin-create {--name=} {--username=} {--email=}';

    protected $description = 'Create or securely rotate a control-plane superadmin';

    public function handle(): int
    {
        if (config('database.connections.control.driver') !== 'mysql') {
            $this->error('The control admin command requires a configured MySQL control database.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
        $username = strtolower(trim((string) ($this->option('username') ?: $this->ask('Username'))));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Confirm password');

        $validation = Validator::make(compact('name', 'username', 'email'), [
            'name' => ['required', 'string', 'max:191'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('control.super_admins', 'username')->ignore($email, 'email')],
            'email' => ['required', 'email', 'max:191'],
        ]);
        if ($validation->fails()) {
            $this->error($validation->errors()->first());

            return self::FAILURE;
        }

        if (strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->error('Passwords must match and contain at least 12 characters.');

            return self::FAILURE;
        }

        $admin = SuperAdmin::query()->firstOrNew(['email' => $email]);
        $admin->fill([
            'name' => $name,
            'username' => $username,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
        $admin->save();

        $this->newLine();
        $this->info('Superadmin created or updated: '.$username);

        return self::SUCCESS;
    }
}
