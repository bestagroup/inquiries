<?php

namespace App\Models;

use App\Enums\PayloadMode;
use App\Enums\ResponseFormat;
use App\Enums\ServiceHttpMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemoteService extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'services';

    protected $fillable = [
        'name', 'slug', 'description', 'category', 'icon', 'endpoint_url', 'http_method', 'payload_mode',
        'response_format', 'headers', 'timeout_seconds', 'connect_timeout_seconds',
        'retry_times', 'retry_delay_ms', 'rate_limit_per_minute', 'price_amount', 'allow_resubmit',
        'is_active', 'sort_order',
    ];

    protected $hidden = ['headers'];

    protected $casts = [
        'http_method' => ServiceHttpMethod::class,
        'payload_mode' => PayloadMode::class,
        'response_format' => ResponseFormat::class,
        'headers' => 'encrypted:array',
        'price_amount' => 'integer',
        'allow_resubmit' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(ServiceField::class, 'service_id')->orderBy('sort_order')->orderBy('id');
    }

    public function inputFields(): HasMany
    {
        return $this->fields()->where('direction', 'input');
    }

    public function outputFields(): HasMany
    {
        return $this->fields()->where('direction', 'output');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'service_user', 'service_id', 'user_id')
            ->withPivot(['is_active', 'rate_limit_per_minute', 'assigned_at'])
            ->withTimestamps();
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'service_id');
    }
}
