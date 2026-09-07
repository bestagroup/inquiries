<aside class="app-sidebar" id="app-sidebar">
    <button class="btn btn-sm btn-outline-secondary sidebar-close" type="button" data-sidebar-close aria-label="بستن منو"><i class="bi bi-x-lg"></i></button>
    <a href="{{ route('dashboard') }}" class="brand">
        <span class="brand-mark"><i class="bi bi-diagram-3"></i></span>
        <span>{{ config('app.name', 'Service Gateway') }}</span>
    </a>
    <div class="sidebar-title">سامانه</div>
    <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-grid"></i> داشبورد</a>
    @if(auth()->user()->isAdmin())
        <div class="sidebar-title">مدیریت</div>
        <a class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="bi bi-people"></i> کاربران</a>
        <a class="sidebar-link {{ request()->routeIs('admin.services.*') ? 'active' : '' }}" href="{{ route('admin.services.index') }}"><i class="bi bi-hdd-network"></i> سرویس‌ها</a>
        <a class="sidebar-link {{ request()->routeIs('admin.billing.*') ? 'active' : '' }}" href="{{ route('admin.billing.edit') }}"><i class="bi bi-cash-coin"></i> تعرفه و صورتحساب</a>
        <a class="sidebar-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.integration.edit') }}"><i class="bi bi-shield-lock"></i> توکن سرویس‌ها</a>
        <a class="sidebar-link {{ request()->routeIs('admin.requests.*') ? 'active' : '' }}" href="{{ route('admin.requests.index') }}"><i class="bi bi-clock-history"></i> همه درخواست‌ها</a>
    @else
        <div class="sidebar-title">خدمات من</div>
        <a class="sidebar-link {{ request()->routeIs('services.*') ? 'active' : '' }}" href="{{ route('services.index') }}"><i class="bi bi-ui-checks-grid"></i> سرویس‌های تخصیص‌یافته</a>
        <a class="sidebar-link {{ request()->routeIs('requests.*') ? 'active' : '' }}" href="{{ route('requests.index') }}"><i class="bi bi-receipt"></i> درخواست‌های من</a>
        <a class="sidebar-link {{ request()->routeIs('wallet.*') ? 'active' : '' }}" href="{{ route('wallet.show') }}"><i class="bi bi-wallet2"></i> کیف پول من</a>
    @endif
</aside>
