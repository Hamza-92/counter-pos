<?php

namespace App\Tenancy;

use App\Models\ControlPlane\Tenant;
use App\Models\ControlPlane\TenantDatabase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class TenantAdministratorService
{
    public function __construct(private readonly TenantOperationRunner $runner) {}

    /** @param array{name: string, email: string, password: string} $administrator */
    public function configure(
        string $tenantId,
        array $administrator,
        string $source = 'control_panel',
        ?string $externalReference = null,
    ): array {
        return $this->runner->run(
            $tenantId,
            'configure-administrator',
            function (Tenant $tenant, TenantDatabase $database) use ($administrator): array {
                if (! Schema::connection('tenant')->hasTable('users')
                    || ! Schema::connection('tenant')->hasTable('roles')
                    || ! Schema::connection('tenant')->hasTable('role_user')) {
                    throw new RuntimeException('Run tenant migrations before configuring the administrator.');
                }
                if (! DB::connection('tenant')->table('roles')->where('id', 1)->exists()) {
                    throw new RuntimeException('Install tenant reference data before configuring the administrator.');
                }

                return DB::connection('tenant')->transaction(function () use ($administrator): array {
                    $email = strtolower(trim($administrator['email']));
                    $name = trim($administrator['name']) ?: 'Owner';
                    $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
                    $created = $user === null;
                    $values = [
                        'firstname' => $name,
                        'lastname' => '',
                        'username' => $name,
                        'email' => $email,
                        'password' => Hash::make($administrator['password']),
                        'phone' => $user?->phone ?? '',
                        'avatar' => $user?->avatar ?: 'no_avatar.png',
                        'role_id' => 1,
                        'statut' => 1,
                        'is_all_warehouses' => 1,
                        'record_view' => 1,
                        'deleted_at' => null,
                    ];

                    if ($user) {
                        $user->forceFill($values)->save();
                    } else {
                        $user = User::query()->create($values);
                    }

                    DB::connection('tenant')->table('role_user')->updateOrInsert(
                        ['user_id' => $user->id, 'role_id' => 1],
                        ['updated_at' => now(), 'created_at' => now()],
                    );

                    return [
                        'status' => 'configured',
                        'admin_email' => $email,
                        'created' => $created,
                    ];
                });
            },
            true,
            $source,
            $externalReference,
        );
    }
}
