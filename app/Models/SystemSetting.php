<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    public const REMOTE_SERVICE_TOKEN = 'remote_services.token';

    protected $fillable = ['key', 'value'];

    protected $hidden = ['value'];

    protected $casts = ['value' => 'encrypted'];

    public static function remoteServiceToken(): ?string
    {
        $setting = static::query()
            ->where('key', self::REMOTE_SERVICE_TOKEN)
            ->first(['id', 'key', 'value']);

        $token = trim((string) $setting?->value);

        return $token !== '' ? $token : null;
    }
}
