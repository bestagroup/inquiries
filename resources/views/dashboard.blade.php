@extends('layouts.base')
@section('title','داشبورد')
@section('page-title','داشبورد')
@section('content')
<div class="row g-3 mb-4">
@foreach($metrics as $key=>$value)
    @php($labels = auth()->user()->isAdmin() ? ['users'=>'کاربران فعال','services'=>'سرویس‌های فعال','requestsToday'=>'درخواست‌های امروز','failedToday'=>'ناموفق امروز'] : ['services'=>'سرویس‌های من','requestsToday'=>'درخواست‌های امروز','successful'=>'کل موفق','failed'=>'کل ناموفق'])
    <div class="col-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body d-flex align-items-center gap-3"><div class="metric-icon"><i class="bi bi-activity"></i></div><div><div class="text-muted small">{{ $labels[$key] ?? $key }}</div><div class="fs-4 fw-bold">{{ number_format($value) }}</div></div></div></div></div>
@endforeach
</div>
<div class="card"><div class="card-header d-flex justify-content-between align-items-center"><strong>آخرین درخواست‌ها</strong>@if(auth()->user()->isAdmin())<a href="{{ route('admin.requests.index') }}" class="btn btn-sm btn-outline-primary">مشاهده همه</a>@else<a href="{{ route('requests.index') }}" class="btn btn-sm btn-outline-primary">مشاهده همه</a>@endif</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>سرویس</th>@if(auth()->user()->isAdmin())<th>کاربر</th>@endif<th>وضعیت</th><th>زمان</th><th></th></tr></thead><tbody>
@forelse($recent as $item)<tr><td>{{ $item->service?->name }}</td>@if(auth()->user()->isAdmin())<td>{{ $item->user?->name }}</td>@endif<td><span class="badge {{ $item->status->value==='succeeded'?'badge-soft-success':($item->status->value==='failed'?'badge-soft-danger':'badge-soft-warning') }}">{{ __('statuses.'.$item->status->value) }}</span></td><td>{{ $item->created_at?->format('Y-m-d H:i') }}</td><td><a class="btn btn-sm btn-outline-secondary" href="{{ auth()->user()->isAdmin() ? route('admin.requests.show',$item) : route('requests.show',$item) }}">جزئیات</a></td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">هنوز درخواستی ثبت نشده است.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
