<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\RemoteService;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(): View
    {
        $services = request()->user()->services()
            ->wherePivot('is_active', true)
            ->where('services.is_active', true)
            ->with('inputFields')
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('user.services.index', compact('services'));
    }

    public function show(RemoteService $service): View
    {
        $this->authorizeService($service);
        $service->load(['inputFields', 'outputFields']);

        return view('user.services.show', compact('service'));
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
