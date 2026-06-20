<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SMSMessage extends Model
{
    protected $table = 'sms_messages';

    protected $fillable = [
        'name', 'locale', 'text',
    ];

    /**
     * Get template by name and locale, with fallback to 'en'.
     */
    public static function getForLocale(string $name, ?string $locale = null): ?self
    {
        $locale = $locale ?: (\App\Models\Setting::where('deleted_at', null)->value('default_language') ?: 'en');
        $message = static::where('name', $name)
            ->where('locale', $locale)
            ->whereNull('deleted_at')
            ->first();
        if ($message) {
            return $message;
        }
        if ($locale !== 'en') {
            return static::where('name', $name)
                ->where('locale', 'en')
                ->whereNull('deleted_at')
                ->first();
        }
        return null;
    }
}
