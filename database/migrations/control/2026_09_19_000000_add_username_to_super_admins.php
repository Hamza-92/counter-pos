<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('control');
        if (! $schema->hasColumn('super_admins', 'username')) {
            $schema->table('super_admins', function (Blueprint $table) {
                $table->string('username', 100)->nullable()->after('name');
            });
        }

        $used = [];
        DB::connection('control')->table('super_admins')->orderBy('id')->get()->each(function ($admin) use (&$used) {
            if (is_string($admin->username) && $admin->username !== '') {
                $used[strtolower($admin->username)] = true;
                return;
            }

            $base = Str::of((string) $admin->email)->before('@')->lower()
                ->replaceMatches('/[^a-z0-9_.-]+/', '.')->trim('.-_')->limit(80, '')->toString();
            $base = $base !== '' ? $base : 'admin';
            $username = $base;
            $suffix = 1;
            while (isset($used[$username])) {
                $username = $base.'.'.$suffix++;
            }
            $used[$username] = true;
            DB::connection('control')->table('super_admins')->where('id', $admin->id)->update(['username' => $username]);
        });

        if (! $this->hasUsernameIndex()) {
            $schema->table('super_admins', function (Blueprint $table) {
                $table->unique('username');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('control');
        if ($schema->hasColumn('super_admins', 'username')) {
            $schema->table('super_admins', function (Blueprint $table) {
                $table->dropUnique(['username']);
                $table->dropColumn('username');
            });
        }
    }

    private function hasUsernameIndex(): bool
    {
        return collect(Schema::connection('control')->getIndexes('super_admins'))
            ->contains(fn (array $index) => ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['username']);
    }
};
