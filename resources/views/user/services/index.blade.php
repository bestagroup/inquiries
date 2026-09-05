@extends('layouts.base')
@section('title', 'سرویس‌های استعلام')
@section('page-title', 'سرویس‌های استعلام')

@section('content')
@php
    $categoryCount = $services->pluck('category')->filter()->unique()->count();
@endphp
<div class="services-page">
    <div class="services-orb services-orb-primary" aria-hidden="true"></div>
    <div class="services-orb services-orb-secondary" aria-hidden="true"></div>

    <section class="services-hero" aria-labelledby="services-title">
        <div class="services-hero-copy">
            <span class="services-eyebrow"><i class="bi bi-stars"></i> مرکز خدمات سازمانی</span>
            <h1 id="services-title">سرویس‌های استعلام</h1>
            <p>دسترسی سریع، امن و یکپارچه به سرویس‌های استعلامی مورد نیاز کسب‌وکار شما</p>
        </div>
        <div class="services-stats" aria-label="آمار سرویس‌ها">
            <div class="service-stat">
                <span class="service-stat-icon service-stat-icon-primary"><i class="bi bi-grid"></i></span>
                <div><strong>{{ number_format($services->count()) }}</strong><span>سرویس فعال</span></div>
            </div>
            <div class="service-stat">
                <span class="service-stat-icon service-stat-icon-success"><i class="bi bi-broadcast-pin"></i></span>
                <div><strong>فعال</strong><span>وضعیت سرویس‌ها</span></div>
            </div>
            <div class="service-stat">
                <span class="service-stat-icon service-stat-icon-violet"><i class="bi bi-collection"></i></span>
                <div><strong>{{ number_format($categoryCount) }}</strong><span>دسته‌بندی</span></div>
            </div>
        </div>
    </section>

    <div class="services-section-heading">
        <div>
            <h2>سرویس‌های در دسترس شما</h2>
            <p>برای ثبت و پیگیری یک استعلام، سرویس مورد نظر را انتخاب کنید.</p>
        </div>
        <span class="services-count">{{ number_format($services->count()) }} سرویس</span>
    </div>

    <div class="row g-4 services-grid">
        @forelse($services as $service)
            <div class="col-md-6 col-xl-4">
                <article class="service-glass-card h-100">
                    <div class="service-card-top">
                        <span class="service-card-icon"><i class="bi {{ $service->icon ?: 'bi-hdd-network' }}"></i></span>
                        <span class="service-status"><i class="bi bi-check-circle-fill"></i> فعال</span>
                    </div>
                    <div class="service-card-content">
                        @if($service->category)
                            <span class="service-category">{{ $service->category }}</span>
                        @endif
                        <h3>{{ $service->name }}</h3>
                        <p>{{ \Illuminate\Support\Str::limit((string) $service->description, 135) }}</p>
                    </div>
                    <div class="service-card-footer">
                        <span class="service-input-count"><i class="bi bi-input-cursor-text"></i> {{ number_format($service->inputFields->count()) }} ورودی</span>
                        <a href="{{ route('services.show', $service) }}" aria-label="مشاهده سرویس {{ $service->name }}">
                            مشاهده سرویس <i class="bi bi-arrow-left"></i>
                        </a>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12">
                <div class="services-empty">
                    <span><i class="bi bi-grid"></i></span>
                    <h2>هنوز سرویسی در دسترس شما نیست</h2>
                    <p>برای فعال‌سازی سرویس‌های مورد نیاز با مدیر سامانه در ارتباط باشید.</p>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
