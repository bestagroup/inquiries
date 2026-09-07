<?php

namespace App\Services\Billing;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttempt;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletReservation;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WalletService
{
    public function walletFor(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['balance' => 0, 'reserved_balance' => 0],
        );
    }

    public function setBalance(User $user, int $targetBalance, ?User $actor = null, ?string $note = null): Wallet
    {
        if ($targetBalance < 0) {
            throw ValidationException::withMessages(['wallet_balance' => 'موجودی کیف پول نمی‌تواند منفی باشد.']);
        }

        return DB::transaction(function () use ($user, $targetBalance, $actor, $note): Wallet {
            $wallet = $this->lockWallet($user->getKey());

            if ($targetBalance < (int) $wallet->reserved_balance) {
                throw ValidationException::withMessages([
                    'wallet_balance' => 'موجودی جدید نمی‌تواند کمتر از مبلغ رزروشده برای استعلام‌های در حال پردازش باشد.',
                ]);
            }

            $before = (int) $wallet->balance;
            if ($before === $targetBalance) {
                return $wallet;
            }

            $wallet->update(['balance' => $targetBalance]);

            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'performed_by' => $actor?->getKey(),
                'type' => $targetBalance > $before ? WalletTransaction::TYPE_ADMIN_CREDIT : WalletTransaction::TYPE_ADMIN_DEBIT,
                'amount' => abs($targetBalance - $before),
                'balance_before' => $before,
                'balance_after' => $targetBalance,
                'note' => $note ?: 'تنظیم دستی موجودی توسط مدیر سامانه',
            ]);

            return $wallet->fresh();
        }, 3);
    }

    public function reserveForExecution(ServiceRequest $request, string $executionToken): ?WalletReservation
    {
        $request->loadMissing(['service', 'user']);
        $amount = (int) ($request->service?->price_amount ?? 0);

        if ($amount <= 0) {
            return null;
        }

        if (! $request->user) {
            throw new RuntimeException('کاربر درخواست برای رزرو کیف پول یافت نشد.');
        }

        return DB::transaction(function () use ($request, $executionToken, $amount): WalletReservation {
            $wallet = $this->lockWallet($request->user_id);

            if ($wallet->availableBalance() < $amount) {
                throw new RuntimeException('موجودی قابل استفاده کیف پول برای انجام این استعلام کافی نیست.');
            }

            $wallet->increment('reserved_balance', $amount);

            return WalletReservation::query()->create([
                'wallet_id' => $wallet->id,
                'service_request_id' => $request->getKey(),
                'execution_token' => $executionToken,
                'amount' => $amount,
                'status' => WalletReservation::STATUS_RESERVED,
            ]);
        }, 3);
    }

    public function reservedAmount(string $executionToken): int
    {
        return (int) WalletReservation::query()->where('execution_token', $executionToken)->value('amount');
    }

    public function capture(string $executionToken, ServiceRequestAttempt $attempt): ?WalletTransaction
    {
        if ((int) $attempt->price_amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($executionToken, $attempt): WalletTransaction {
            $reservation = WalletReservation::query()->where('execution_token', $executionToken)->lockForUpdate()->first();

            if (! $reservation) {
                throw new RuntimeException('رزرو مالی این استعلام یافت نشد.');
            }

            if ($reservation->status === WalletReservation::STATUS_CAPTURED) {
                return WalletTransaction::query()->where('service_request_attempt_id', $attempt->getKey())->firstOrFail();
            }

            if ($reservation->status !== WalletReservation::STATUS_RESERVED) {
                throw new RuntimeException('رزرو مالی این استعلام دیگر قابل برداشت نیست.');
            }

            $wallet = Wallet::query()->whereKey($reservation->wallet_id)->lockForUpdate()->firstOrFail();
            $amount = (int) $reservation->amount;

            if ((int) $wallet->reserved_balance < $amount || (int) $wallet->balance < $amount) {
                throw new RuntimeException('یکپارچگی موجودی کیف پول برای برداشت این استعلام برقرار نیست.');
            }

            $before = (int) $wallet->balance;
            $after = $before - $amount;
            $wallet->update([
                'balance' => $after,
                'reserved_balance' => (int) $wallet->reserved_balance - $amount,
            ]);

            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'service_request_id' => $reservation->service_request_id,
                'service_request_attempt_id' => $attempt->getKey(),
                'type' => WalletTransaction::TYPE_INQUIRY_DEBIT,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'note' => 'کسر هزینه استعلام موفق',
            ]);

            $reservation->update(['status' => WalletReservation::STATUS_CAPTURED, 'captured_at' => now()]);

            return $transaction;
        }, 3);
    }

    public function release(string $executionToken): void
    {
        DB::transaction(function () use ($executionToken): void {
            $reservation = WalletReservation::query()->where('execution_token', $executionToken)->lockForUpdate()->first();

            if (! $reservation || $reservation->status !== WalletReservation::STATUS_RESERVED) {
                return;
            }

            $wallet = Wallet::query()->whereKey($reservation->wallet_id)->lockForUpdate()->firstOrFail();
            $amount = (int) $reservation->amount;

            if ((int) $wallet->reserved_balance < $amount) {
                throw new RuntimeException('یکپارچگی مبلغ رزروشده کیف پول برقرار نیست.');
            }

            $wallet->update(['reserved_balance' => (int) $wallet->reserved_balance - $amount]);
            $reservation->update(['status' => WalletReservation::STATUS_RELEASED, 'released_at' => now()]);
        }, 3);
    }

    private function lockWallet(int $userId): Wallet
    {
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'reserved_balance' => 0],
        );

        return Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();
    }
}
