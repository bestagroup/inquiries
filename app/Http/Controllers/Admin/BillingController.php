<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RemoteService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function edit(): View
    {
        $services = RemoteService::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'category', 'price_amount', 'is_active']);

        return view('admin.billing.edit', compact('services'));
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'prices' => ['required', 'array'],
            'prices.*' => ['required', 'integer', 'min:0', 'max:9000000000000000'],
        ]);

        DB::transaction(function () use ($validated, $audit): void {
            foreach ($validated['prices'] as $serviceId => $price) {
                $service = RemoteService::query()->lockForUpdate()->findOrFail((int) $serviceId);
                $before = (int) $service->price_amount;
                $after = (int) $price;

                if ($before === $after) {
                    continue;
                }

                $service->update(['price_amount' => $after]);
                $audit->write('service.price_updated', $service, [
                    'before' => $before,
                    'after' => $after,
                    'currency' => config('billing.currency'),
                ]);
            }
        }, 3);

        return redirect()->route('admin.billing.edit')->with('success', 'تعرفه سرویس‌ها بروزرسانی شد.');
    }
}
