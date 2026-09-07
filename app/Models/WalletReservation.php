<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletReservation extends Model
{
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'wallet_id', 'service_request_id', 'execution_token', 'amount', 'status', 'captured_at', 'released_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'captured_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
