@extends('layouts.base')
@section('title','کیف پول من')
@section('page-title','کیف پول من')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small mb-1">موجودی کل</div><div class="fs-4 fw-bold">{{ number_format($wallet->balance) }} <small class="fs-6 text-muted">{{ config('billing.currency_label') }}</small></div></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small mb-1">مبلغ رزروشده</div><div class="fs-4 fw-bold">{{ number_format($wallet->reserved_balance) }} <small class="fs-6 text-muted">{{ config('billing.currency_label') }}</small></div><div class="text-muted small mt-1">برای استعلام‌های در حال پردازش؛ در صورت شکست آزاد می‌شود.</div></div></div></div>
    <div class="col-md-4"><div class="card h-100 border-primary"><div class="card-body"><div class="text-muted small mb-1">موجودی قابل استفاده</div><div class="fs-4 fw-bold text-primary">{{ number_format($wallet->availableBalance()) }} <small class="fs-6 text-muted">{{ config('billing.currency_label') }}</small></div></div></div></div>
</div>

<div class="card">
    <div class="card-header"><strong>گردش کیف پول</strong></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>تاریخ</th><th>شرح</th><th>سرویس</th><th>مبلغ</th><th>موجودی پس از تراکنش</th></tr></thead>
            <tbody>
            @forelse($transactions as $transaction)
                @php($isCredit = $transaction->type === \App\Models\WalletTransaction::TYPE_ADMIN_CREDIT)
                <tr>
                    <td>{{ $transaction->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>
                        @if($transaction->type === \App\Models\WalletTransaction::TYPE_INQUIRY_DEBIT)
                            هزینه استعلام موفق
                        @elseif($transaction->type === \App\Models\WalletTransaction::TYPE_ADMIN_CREDIT)
                            افزایش موجودی توسط مدیر
                        @else
                            کاهش موجودی توسط مدیر
                        @endif
                        @if($transaction->note)<div class="text-muted small">{{ $transaction->note }}</div>@endif
                    </td>
                    <td>{{ $transaction->serviceRequest?->service?->name ?? '-' }}</td>
                    <td class="fw-bold {{ $isCredit ? 'text-success' : 'text-danger' }}" dir="ltr">{{ $isCredit ? '+' : '-' }}{{ number_format($transaction->amount) }}</td>
                    <td>{{ number_format($transaction->balance_after) }} {{ config('billing.currency_label') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">هنوز تراکنشی در کیف پول ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())<div class="card-footer">{{ $transactions->links() }}</div>@endif
</div>
@endsection
