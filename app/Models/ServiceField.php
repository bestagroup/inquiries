<?php

namespace App\Models;

use App\Enums\FieldDirection;
use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceField extends Model
{
    protected $fillable = [
        'service_id', 'direction', 'key', 'label', 'type', 'is_required', 'validation_rules',
        'default_value', 'json_path', 'options', 'is_sensitive', 'sort_order',
    ];

    protected $casts = [
        'direction' => FieldDirection::class,
        'type' => FieldType::class,
        'is_required' => 'boolean',
        'validation_rules' => 'array',
        'options' => 'array',
        'is_sensitive' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(RemoteService::class, 'service_id');
    }
}
