<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ClientSeeder::class,
            CurrencySeeder::class,
            SettingSeeder::class,
            ServerSeeder::class,
            PermissionsSeeder::class,
            RoleSeeder::class,
            PermissionRoleSeeder::class,
            Warehouse::class,
            StoreSettingSeeder::class,
            PaymentMethodsSeeder::class,
        ]);
    }
}
