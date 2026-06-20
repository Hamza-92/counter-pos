<?php

// app/Models/QuickBooksToken.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickBooksToken extends Model
{
    protected $fillable = [
        'user_id', 'realm_id', 'environment',
        'access_token', 'refresh_token',
        'access_token_expires_at', 'refresh_token_expires_at',
    ];

    protected $casts = [
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
    ];

    public static function normalizeEnv(?string $e): string
    {
        $e = strtolower(trim($e ?? 'development'));

        return in_array($e, ['development', 'dev', 'sandbox'], true) ? 'Development' : 'Production';
    }

    /**
     * Try to find a token row:
     * 1) exact realm + env
     * 2) any env for realm
     * 3) latest row for env
     * 4) latest row overall
     */
    public static function resolve(?string $realmId = null, ?string $environment = null): ?self
    {
        $env = self::normalizeEnv($environment);

        if ($realmId) {
            $row = self::where('realm_id', $realmId)->where('environment', $env)->first();
            if ($row) {
                return $row;
            }

            $row = self::where('realm_id', $realmId)->first();
            if ($row) {
                return $row;
            }
        }

        $row = self::where('environment', $env)->latest()->first();
        if ($row) {
            return $row;
        }

        return self::latest()->first();
    }
}
