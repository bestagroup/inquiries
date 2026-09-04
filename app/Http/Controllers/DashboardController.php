<?php

namespace App\Http\Controllers;

use App\Enums\ServiceRequestStatus;
use App\Models\RemoteService;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        $today = today();
        $tomorrow = $today->copy()->addDay();

        if ($user->isAdmin()) {
            $metrics = [
                'users' => User::query()->where('role', 'user')->where('is_active', true)->count(),
                'services' => RemoteService::query()->where('is_active', true)->count(),
                'requestsToday' => ServiceRequest::query()->where('created_at', '>=', $today)->where('created_at', '<', $tomorrow)->count(),
                'failedToday' => ServiceRequest::query()->where('created_at', '>=', $today)->where('created_at', '<', $tomorrow)->where('status', ServiceRequestStatus::Failed->value)->count(),
            ];
            $recent = ServiceRequest::query()->with(['user:id,name', 'service:id,name'])->latest()->limit(10)->get();

            return view('dashboard', compact('metrics', 'recent'));
        }

        $serviceIds = $user->services()->wherePivot('is_active', true)->where('services.is_active', true)->pluck('services.id');
        $metrics = [
            'services' => $serviceIds->count(),
            'requestsToday' => $user->serviceRequests()->where('created_at', '>=', $today)->where('created_at', '<', $tomorrow)->count(),
            'successful' => $user->serviceRequests()->where('status', ServiceRequestStatus::Succeeded->value)->count(),
            'failed' => $user->serviceRequests()->where('status', ServiceRequestStatus::Failed->value)->count(),
        ];
        $recent = $user->serviceRequests()->with('service:id,name')->latest()->limit(10)->get();

        return view('dashboard', compact('metrics', 'recent'));
    }
}
