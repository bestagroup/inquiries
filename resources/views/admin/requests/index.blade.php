@extends('layouts.base')
@section('title', 'همه درخواست‌ها')
@section('page-title', 'همه درخواست‌ها')

@section('content')
<section class="card request-status-card mb-4" aria-labelledby="request-status-title">
    <div class="card-body">
        <header class="request-status-header">
            <div class="request-status-heading">
                <span class="request-status-heading-icon"><i class="bi bi-bar-chart-line-fill" aria-hidden="true"></i></span>
                <div>
                    <span class="request-status-eyebrow">نمای کلی عملکرد</span>
                    <h2 id="request-status-title">وضعیت HTTP درخواست‌ها</h2>
                    <p>روند روزانه بر اساس تاریخ ثبت درخواست و آخرین پاسخ HTTP آن</p>
                </div>
            </div>
            <span class="request-status-period"><i class="bi bi-calendar3" aria-hidden="true"></i> ۷ روز اخیر</span>
        </header>

        @if($statuses === [])
            <div class="request-status-empty"><span><i class="bi bi-graph-up" aria-hidden="true"></i></span><strong>هنوز داده‌ای برای نمایش نیست</strong><p>در ۷ روز اخیر درخواستی ثبت نشده است.</p></div>
        @else
            <div class="request-status-summary" aria-label="خلاصه ۷ روز اخیر">
                <div class="request-status-metric"><span class="request-status-metric-icon is-total"><i class="bi bi-layers" aria-hidden="true"></i></span><div><span>کل درخواست‌ها</span><strong>{{ number_format($chartSummary['total']) }}</strong></div></div>
                <div class="request-status-metric"><span class="request-status-metric-icon is-success"><i class="bi bi-check2-circle" aria-hidden="true"></i></span><div><span>پاسخ‌های 2xx</span><strong>{{ number_format($chartSummary['success']) }}</strong></div></div>
                <div class="request-status-metric"><span class="request-status-metric-icon is-unanswered"><i class="bi bi-dash-circle" aria-hidden="true"></i></span><div><span>بدون پاسخ HTTP</span><strong>{{ number_format($chartSummary['without_http']) }}</strong></div></div>
            </div>

            <div class="request-status-visual">
                <div class="request-status-visual-head"><div><strong>توزیع روزانه پاسخ‌ها</strong><span>هر رنگ نشان‌دهنده یک کد HTTP است</span></div><span class="request-status-unit">تعداد درخواست</span></div>
                <div class="request-status-chart" aria-hidden="true">
                    <div class="request-status-scale"><span>{{ $chartMax }}</span><span>{{ (int) ceil($chartMax / 2) }}</span><span>۰</span></div>
                    <div class="request-status-plot">
                        @foreach($chartDays as $day)
                            <div class="request-status-day {{ $day['is_today'] ? 'is-today' : '' }}">
                                <div class="request-status-column">
                                    @if($day['total'] > 0)
                                        <div class="request-status-bar" style="height: {{ 100 * $day['total'] / $chartMax }}%">
                                            <span class="request-status-bar-total">{{ $day['total'] }}</span>
                                            <div class="request-status-stack">
                                                @foreach($day['segments'] as $segment)
                                                    <div class="request-status-segment" style="height: {{ $segment['percent'] }}%; background-color: {{ $segment['color'] }}" title="{{ $day['date'] }} — {{ $segment['label'] }}: {{ $segment['count'] }}"></div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <span class="request-status-date">{{ $day['label'] }}</span>
                                @if($day['is_today'])<span class="request-status-today">امروز</span>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="request-status-legend" aria-hidden="true">
                    @foreach($statuses as $status)
                        <span class="request-status-legend-item"><i style="background-color: {{ $status['color'] }}"></i><span>{{ $status['label'] }}</span><b>{{ number_format($status['total']) }}</b></span>
                    @endforeach
                </div>
            </div>
            <table class="visually-hidden">
                <caption>تعداد درخواست‌ها در هر روز بر اساس کد HTTP</caption>
                <thead><tr><th scope="col">روز</th>@foreach($statuses as $status)<th scope="col">{{ $status['label'] }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach($chartDays as $day)
                        <tr><th scope="row">{{ $day['date'] }}</th>@foreach($statuses as $status)<td>{{ collect($day['segments'])->firstWhere('key', $status['key'])['count'] ?? 0 }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>

<div class="card">
    <div class="card-header"><h5 class="mb-1">گزارش درخواست‌ها</h5><div class="text-muted small">مانیتورینگ درخواست‌های تمام کاربران</div></div>
    <div class="card-body"><div class="table-responsive"><table id="requests-table" class="table table-striped align-middle w-100"><thead><tr><th>کاربر</th><th>سرویس</th><th>وضعیت</th><th>تعداد اجرا</th><th>HTTP</th><th>زمان</th><th></th></tr></thead></table></div></div>
</div>
@endsection

@push('scripts')
<script>document.addEventListener('DOMContentLoaded',()=>{new DataTable('#requests-table',{processing:true,serverSide:true,ajax:@json(route('admin.requests.index')),order:[[5,'desc']],columns:[{data:'user_name',orderable:false,searchable:false},{data:'service_name',orderable:false,searchable:false},{data:'status',name:'status'},{data:'attempt_count',name:'attempt_count'},{data:'last_http_status',name:'last_http_status',defaultContent:'-'},{data:'created_at',name:'created_at'},{data:'action',orderable:false,searchable:false}],language:{search:'جستجو:',lengthMenu:'نمایش _MENU_ رکورد',info:'نمایش _START_ تا _END_ از _TOTAL_',zeroRecords:'نتیجه‌ای یافت نشد',paginate:{next:'بعدی',previous:'قبلی'}}});});</script>
@endpush
