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
        $user = request()->user();
        $services = $user->isAdmin()
            ? RemoteService::query()->where('is_active', true)->with('inputFields')->orderBy('sort_order')->orderBy('name')->get()
            : $user->services()->wherePivot('is_active', true)->where('services.is_active', true)
                ->with('inputFields')->orderBy('sort_order')->orderBy('name')->get();
        $wallet = $user->isAdmin() ? null : $wallets->walletFor($user);

        return view('user.services.index', compact('services', 'wallet'));
    }

    public function show(RemoteService $service, WalletService $wallets): View
    {
        abort_unless(request()->user()->canUseService($service), 403, 'این سرویس برای شما قابل استفاده نیست.');
        $service->load(['inputFields', 'outputFields']);
        $wallet = request()->user()->isAdmin() ? null : $wallets->walletFor(request()->user());

        return view('user.services.show', compact('service', 'wallet'));
    }
}
