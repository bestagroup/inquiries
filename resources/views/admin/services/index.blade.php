@extends('layouts.base')
@section('title', 'مدیریت سرویس‌ها')
@section('page-title', 'مدیریت سرویس‌ها')

@section('content')
<div class="admin-services-page">
    <header class="page-heading">
        <div>
            <span class="page-kicker">تنظیمات کاتالوگ و API</span>
            <h1>مدیریت سرویس‌ها</h1>
            <p>سرویس‌ها، ورودی‌ها، خروجی‌ها و سیاست اجرای هر استعلام را از این بخش مدیریت کنید.</p>
        </div>
        <div class="page-heading-actions">
            <a class="btn btn-outline-secondary" href="{{ route('services.index') }}"><i class="bi bi-grid"></i> مشاهده کاتالوگ</a>
            <a class="btn btn-primary" href="{{ route('admin.services.create') }}"><i class="bi bi-plus-lg"></i> تعریف سرویس جدید</a>
        </div>
    </header>

    <div class="admin-summary-grid" aria-label="خلاصه وضعیت سرویس‌ها">
        <div class="admin-summary-card"><i class="bi bi-layers"></i><div><strong>{{ $summary['total'] }}</strong><span>کل سرویس‌ها</span></div></div>
        <div class="admin-summary-card is-success"><i class="bi bi-check2-circle"></i><div><strong>{{ $summary['active'] }}</strong><span>سرویس فعال</span></div></div>
        <div class="admin-summary-card is-muted"><i class="bi bi-pause-circle"></i><div><strong>{{ $summary['inactive'] }}</strong><span>غیرفعال</span></div></div>
        <div class="admin-summary-card is-violet"><i class="bi bi-collection"></i><div><strong>{{ $summary['categories'] }}</strong><span>دسته‌بندی</span></div></div>
    </div>

    <section class="admin-list-panel">
        <div class="admin-list-heading">
            <div>
                <h2>فهرست سرویس‌ها</h2>
                <p>برای تغییر مشخصات یا فیلدهای هر سرویس، گزینه ویرایش را انتخاب کنید.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table id="services-table" class="table admin-services-table align-middle w-100">
                <thead><tr><th>سرویس</th><th>دسته‌بندی</th><th>کد سرویس</th><th>متد</th><th>کاربران فعال</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const escapeHtml = value => $('<div>').text(value ?? '').html();

    new DataTable('#services-table', {
        processing: true,
        serverSide: true,
        ajax: @json(route('admin.services.index')),
        order: [[0, 'asc']],
        pageLength: 15,
        columns: [
            {
                data: 'name', name: 'name',
                render: (value, type, row) => type !== 'display' ? value : `<div class="admin-service-name"><span><i class="bi ${escapeHtml(row.icon || 'bi-hdd-network')}"></i></span><strong>${escapeHtml(value)}</strong></div>`,
            },
            {data: 'category', name: 'category', render: value => value ? `<span class="table-category">${escapeHtml(value)}</span>` : '<span class="text-muted">—</span>'},
            {data: 'slug', name: 'slug', render: value => `<code class="table-code">${escapeHtml(value)}</code>`},
            {data: 'http_method', name: 'http_method', render: value => `<span class="table-method">${escapeHtml(value)}</span>`},
            {data: 'active_users_count', searchable: false},
            {data: 'is_active', name: 'is_active', render: value => value === 'فعال' ? '<span class="table-status is-active"><i></i> فعال</span>' : '<span class="table-status"><i></i> غیرفعال</span>'},
            {data: 'action', orderable: false, searchable: false},
        ],
        language: {
            search: '', searchPlaceholder: 'جست‌وجوی سرویس…', lengthMenu: 'نمایش _MENU_ ردیف',
            info: 'نمایش _START_ تا _END_ از _TOTAL_ سرویس', infoEmpty: 'سرویسی ثبت نشده است',
            zeroRecords: 'سرویسی با این مشخصات پیدا نشد', processing: 'در حال دریافت اطلاعات…',
            paginate: {next: 'بعدی', previous: 'قبلی'},
        },
    });
});
</script>
@endpush
