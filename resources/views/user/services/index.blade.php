@extends('layouts.base')
@section('title', 'سرویس‌های استعلام')
@section('page-title', 'سرویس‌های استعلام')

@section('content')
@php
    $categories = $services->pluck('category')->filter()->unique()->sort()->values();
@endphp
<div class="service-catalog">
    <header class="page-heading">
        <div>
            <span class="page-kicker">مرکز خدمات سازمانی</span>
            <h1>سرویس‌های استعلام</h1>
            <p>سرویس مورد نیاز را پیدا کنید و استعلام جدید را در چند مرحله کوتاه ثبت کنید.</p>
        </div>
        <div class="page-heading-actions">
            <a class="btn btn-outline-primary" href="{{ route('wallet.show') }}"><i class="bi bi-wallet2"></i> موجودی: {{ number_format($wallet->availableBalance()) }} {{ config('billing.currency_label') }}</a>
        </div>
    </header>

    <section class="catalog-toolbar" aria-label="جست‌وجو و فیلتر سرویس‌ها">
        <label class="catalog-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <span class="visually-hidden">جست‌وجوی سرویس</span>
            <input type="search" id="service-search" placeholder="جست‌وجو در نام یا توضیحات سرویس…" autocomplete="off">
        </label>
        <div class="catalog-filters" role="group" aria-label="دسته‌بندی سرویس‌ها">
            <button type="button" class="catalog-filter active" data-category="all" aria-pressed="true">همه <span>{{ $services->count() }}</span></button>
            @foreach($categories as $category)
                <button type="button" class="catalog-filter" data-category="{{ $category }}" aria-pressed="false">{{ $category }}</button>
            @endforeach
        </div>
    </section>

    <div class="catalog-result-bar">
        <div><strong id="visible-service-count">{{ $services->count() }}</strong> سرویس در دسترس شماست</div>
        <div class="catalog-api-state"><span></span> آماده دریافت درخواست</div>
    </div>

    <div class="row g-3 service-card-grid" id="service-grid">
        @forelse($services as $service)
            <div class="col-sm-6 col-xl-4 service-card-column" data-category="{{ $service->category }}" data-search="{{ $service->name }} {{ $service->description }} {{ $service->slug }}">
                <article class="catalog-service-card h-100">
                    <div class="catalog-service-main">
                        <span class="catalog-service-icon" aria-hidden="true"><i class="bi {{ $service->icon ?: 'bi-hdd-network' }}"></i></span>
                        <div class="catalog-service-title">
                            <div class="catalog-service-meta">
                                @if($service->category)<span>{{ $service->category }}</span>@endif
                                <span class="catalog-status"><i class="bi bi-circle-fill"></i> فعال</span>
                            </div>
                            <h2>{{ $service->name }}</h2>
                        </div>
                    </div>
                    <p>{{ \Illuminate\Support\Str::limit((string) $service->description, 145) }}</p>
                    <div class="small text-muted mb-2"><i class="bi bi-cash-coin"></i> تعرفه: {{ $service->price_amount > 0 ? number_format($service->price_amount).' '.config('billing.currency_label') : 'رایگان' }}</div>
                    <footer>
                        <span><i class="bi bi-input-cursor-text"></i> {{ $service->inputFields->count() }} ورودی</span>
                        <a href="{{ route('services.show', $service) }}" aria-label="شروع {{ $service->name }}">شروع استعلام <i class="bi bi-arrow-left"></i></a>
                    </footer>
                </article>
            </div>
        @empty
            <div class="col-12"><div class="catalog-empty"><i class="bi bi-inboxes"></i><h2>سرویسی برای حساب شما فعال نشده است</h2><p>برای دریافت دسترسی با مدیر سامانه در ارتباط باشید.</p></div></div>
        @endforelse
    </div>

    @if($services->isNotEmpty())
        <div class="catalog-empty d-none" id="service-no-results">
            <i class="bi bi-search"></i><h2>سرویسی با این مشخصات پیدا نشد</h2><p>عبارت جست‌وجو یا دسته‌بندی انتخاب‌شده را تغییر دهید.</p>
            <button type="button" class="btn btn-sm btn-outline-primary" id="service-filter-reset">پاک‌کردن فیلترها</button>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('service-search');
    if (!search) return;
    const cards = [...document.querySelectorAll('.service-card-column')];
    const filters = [...document.querySelectorAll('.catalog-filter')];
    const empty = document.getElementById('service-no-results');
    const count = document.getElementById('visible-service-count');
    let selectedCategory = 'all';
    const normalize = value => String(value ?? '').trim().toLocaleLowerCase('fa');
    const applyFilters = () => {
        const term = normalize(search.value);
        let visible = 0;
        cards.forEach(card => {
            const show = (selectedCategory === 'all' || card.dataset.category === selectedCategory) && (!term || normalize(card.dataset.search).includes(term));
            card.classList.toggle('d-none', !show);
            if (show) visible++;
        });
        count.textContent = visible.toLocaleString('fa-IR');
        empty?.classList.toggle('d-none', visible !== 0);
    };
    filters.forEach(filter => filter.addEventListener('click', () => {
        selectedCategory = filter.dataset.category;
        filters.forEach(item => { const active = item === filter; item.classList.toggle('active', active); item.setAttribute('aria-pressed', active ? 'true' : 'false'); });
        applyFilters();
    }));
    search.addEventListener('input', applyFilters);
    document.getElementById('service-filter-reset')?.addEventListener('click', () => { search.value = ''; filters[0]?.click(); search.focus(); });
});
</script>
@endpush
