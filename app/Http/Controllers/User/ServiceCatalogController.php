<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\RemoteService;
use App\Services\Billing\WalletService;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(WalletService $wallets): View
    {
        $services = request()->user()->services()
            ->wherePivot('is_active', true)
            ->where('services.is_active', true)
            ->with('inputFields')
            ->orderBy('sort_order')->orderBy('name')->get();
        $wallet = $wallets->walletFor(request()->user());

        return view('user.services.index', compact('services', 'wallet'));
    }

    public function show(RemoteService $service, WalletService $wallets): View
    {
        $this->authorizeService($service);
        $service->load(['inputFields', 'outputFields']);
        $wallet = $wallets->walletFor(request()->user());

        return view('user.services.show', compact('service', 'wallet'));
    }

    private function authorizeService(RemoteService $service): void
    {
        abort_unless(
            $service->is_active && request()->user()->services()->whereKey($service->id)->wherePivot('is_active', true)->exists(),
            403,
            'این سرویس به شما تخصیص داده نشده است.'
        );
    }
}
