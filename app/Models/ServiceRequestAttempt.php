<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestAttempt extends Model
{
    protected $fillable = [
        'service_request_id', 'sequence', 'status', 'endpoint_url', 'http_method',
        'request_payload', 'response_payload', 'mapped_response', 'response_raw', 'http_status', 'duration_ms',
        'price_amount', 'error_code', 'error_message', 'started_at', 'completed_at',
    ];

    protected $hidden = ['request_payload', 'response_payload', 'mapped_response', 'response_raw'];

    protected $casts = [
        'status' => AttemptStatus::class,
        'request_payload' => 'encrypted:array',
        'response_payload' => 'encrypted:array',
        'mapped_response' => 'encrypted:array',
        'response_raw' => 'encrypted',
        'price_amount' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }
}
