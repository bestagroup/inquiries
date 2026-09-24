@extends('layouts.base')
@section('title', 'همه درخواست‌ها')
@section('page-title', 'همه درخواست‌ها')

@section('content')
<section class="card request-status-card mb-4" aria-labelledby="request-status-title">
    <div class="card-header">
        <h5 class="mb-1" id="request-status-title">وضعیت HTTP درخواست‌ها</h5>
        <div class="text-muted small">۷ روز اخیر، بر اساس تاریخ ثبت درخواست و آخرین پاسخ HTTP آن</div>
    </div>
    <div class="card-body">
        @if($statuses === [])
            <div class="text-center text-muted py-5">در ۷ روز اخیر درخواستی ثبت نشده است.</div>
        @else
            <div class="request-status-legend" aria-hidden="true">
                @foreach($statuses as $status)
                    <span><i style="background-color: {{ $status['color'] }}"></i>{{ $status['label'] }}</span>
                @endforeach
            </div>
            <div class="request-status-chart" aria-hidden="true">
                <div class="request-status-scale"><span>{{ $chartMax }}</span><span>{{ (int) ceil($chartMax / 2) }}</span><span>۰</span></div>
                <div class="request-status-plot">
                    @foreach($chartDays as $day)
                        <div class="request-status-day">
                            <div class="request-status-column">
                                @if($day['total'] > 0)
                                    <div class="request-status-stack" style="height: {{ 100 * $day['total'] / $chartMax }}%">
                                        @foreach($day['segments'] as $segment)
                                            <div class="request-status-segment" style="height: {{ $segment['percent'] }}%; background-color: {{ $segment['color'] }}" title="{{ $day['date'] }} — {{ $segment['label'] }}: {{ $segment['count'] }}"></div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <span class="request-status-date">{{ $day['label'] }}</span>
                            <span class="request-status-total">{{ $day['total'] }}</span>
                        </div>
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
