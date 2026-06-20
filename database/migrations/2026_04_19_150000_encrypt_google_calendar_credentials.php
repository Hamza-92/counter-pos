<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration
{
    private array $columns = [
        'google_calendar_client_secret',
        'google_calendar_refresh_token',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->columns as $col) {
            if (! Schema::hasColumn('settings', $col)) {
                continue;
            }
        }

        $rows = DB::table('settings')->get(['id', ...$this->columns]);
        foreach ($rows as $row) {
            $update = [];
            foreach ($this->columns as $col) {
                $value = $row->{$col} ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                try {
                    Crypt::decryptString($value);
                    continue;
                } catch (DecryptException $e) {
                    $update[$col] = Crypt::encryptString($value);
                }
            }
            if (! empty($update)) {
                DB::table('settings')->where('id', $row->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $rows = DB::table('settings')->get(['id', ...$this->columns]);
        foreach ($rows as $row) {
            $update = [];
            foreach ($this->columns as $col) {
                $value = $row->{$col} ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                try {
                    $update[$col] = Crypt::decryptString($value);
                } catch (DecryptException $e) {
                    continue;
                }
            }
            if (! empty($update)) {
                DB::table('settings')->where('id', $row->id)->update($update);
            }
        }
    }
};
