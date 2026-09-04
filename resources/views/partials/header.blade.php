<header class="app-header">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary sidebar-toggle" type="button" data-sidebar-open aria-label="باز کردن منو" aria-controls="app-sidebar" aria-expanded="false"><i class="bi bi-list"></i></button>
        <strong>@yield('page-title', 'پنل کاربری')</strong>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="text-end"><div class="fw-bold small">{{ auth()->user()->name }}</div><div class="text-muted" style="font-size:.75rem">{{ auth()->user()->isAdmin() ? 'مدیر سامانه' : 'کاربر' }}</div></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-box-arrow-left"></i> خروج</button></form>
    </div>
</header>
