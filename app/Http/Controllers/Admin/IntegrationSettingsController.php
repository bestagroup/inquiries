<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateIntegrationSettingsRequest;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IntegrationSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.integration', [
            'hasServiceToken' => SystemSetting::remoteServiceToken() !== null,
        ]);
    }

    public function update(UpdateIntegrationSettingsRequest $request, AuditLogger $audit): RedirectResponse
    {
        $token = trim((string) $request->validated('service_token'));

        if ($token !== '') {
            $setting = SystemSetting::query()->updateOrCreate(
                ['key' => SystemSetting::REMOTE_SERVICE_TOKEN],
                ['value' => $token],
            );

            $audit->write('settings.remote_service_token.updated', $setting);
        }

        return redirect()
            ->route('admin.settings.integration.edit')
            ->with('success', $token !== '' ? 'توکن مشترک سرویس‌ها با موفقیت ذخیره شد.' : 'توکن مشترک بدون تغییر باقی ماند.');
    }
}
