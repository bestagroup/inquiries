<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ServiceRequest extends Model
{
    protected $fillable = [
        'uuid', 'execution_token', 'user_id', 'service_id', 'input_payload', 'status', 'attempt_count',
        'last_requested_at', 'last_responded_at', 'last_http_status', 'last_duration_ms', 'last_error',
    ];

    protected $hidden = ['input_payload', 'execution_token'];

    protected $casts = [
        'input_payload' => 'encrypted:array',
        'status' => ServiceRequestStatus::class,
        'last_requested_at' => 'datetime',
        'last_responded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(RemoteService::class, 'service_id')->withTrashed();
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ServiceRequestAttempt::class)->latest('sequence');
    }
}
