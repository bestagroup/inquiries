<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    public const TYPE_ADMIN_CREDIT = 'admin_credit';
    public const TYPE_ADMIN_DEBIT = 'admin_debit';
    public const TYPE_INQUIRY_DEBIT = 'inquiry_debit';

    protected $fillable = [
        'uuid', 'wallet_id', 'service_request_id', 'service_request_attempt_id', 'performed_by',
        'type', 'amount', 'balance_before', 'balance_after', 'note',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            $transaction->uuid ??= (string) Str::uuid();
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ServiceRequestAttempt::class, 'service_request_attempt_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
