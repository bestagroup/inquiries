<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FieldDirection;
use App\Enums\FieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveServiceRequest;
use App\Models\RemoteService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ServiceController extends Controller
{
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            $query = RemoteService::query()
                ->select(['id', 'name', 'slug', 'category', 'icon', 'http_method', 'is_active', 'created_at'])
                ->withCount([
                    'users as active_users_count' => fn ($query) => $query->where('service_user.is_active', true),
                ]);

            return DataTables::eloquent($query)
                ->editColumn('http_method', fn (RemoteService $service) => $service->http_method->value)
                ->editColumn('is_active', fn (RemoteService $service) => $service->is_active ? 'فعال' : 'غیرفعال')
                ->addColumn('action', fn (RemoteService $service) => view('admin.services._actions', compact('service'))->render())
                ->rawColumns(['action'])
                ->toJson();
        }

        return view('admin.services.index', [
            'summary' => [
                'total' => RemoteService::query()->count(),
                'active' => RemoteService::query()->where('is_active', true)->count(),
                'inactive' => RemoteService::query()->where('is_active', false)->count(),
                'categories' => RemoteService::query()->whereNotNull('category')->distinct()->count('category'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.services.form', ['service' => new RemoteService, 'inputs' => collect(), 'outputs' => collect()]);
    }

    public function store(SaveServiceRequest $request, AuditLogger $audit): RedirectResponse
    {
        $service = $this->persist(new RemoteService, $request->validated());
        $audit->write('service.created', $service);

        return redirect()->route('admin.services.index')->with('success', 'سرویس با موفقیت ایجاد شد.');
    }

    public function edit(RemoteService $service): View
    {
        $service->load('fields');

        return view('admin.services.form', [
            'service' => $service,
            'inputs' => $service->fields->where('direction', FieldDirection::Input),
            'outputs' => $service->fields->where('direction', FieldDirection::Output),
        ]);
    }

    public function update(SaveServiceRequest $request, RemoteService $service, AuditLogger $audit): RedirectResponse
    {
        $service = $this->persist($service, $request->validated());
        $audit->write('service.updated', $service);

        return redirect()->route('admin.services.index')->with('success', 'سرویس بروزرسانی شد.');
    }

    public function destroy(RemoteService $service, AuditLogger $audit): RedirectResponse
    {
        $service->update(['is_active' => false]);
        $service->delete();
        $audit->write('service.deleted', $service);

        return redirect()->route('admin.services.index')->with('success', 'سرویس غیرفعال و حذف شد؛ سوابق درخواست‌ها حفظ شده‌اند.');
    }

    private function persist(RemoteService $service, array $data): RemoteService
    {
        return DB::transaction(function () use ($service, $data): RemoteService {
            $serviceData = Arr::except($data, ['inputs', 'outputs', 'headers_json']);
            $decodedHeaders = filled($data['headers_json'] ?? null)
                ? json_decode($data['headers_json'], true, 512, JSON_THROW_ON_ERROR)
                : [];
            $serviceData['headers'] = collect($decodedHeaders)
                ->mapWithKeys(fn ($value, $key) => [trim((string) $key) => (string) ($value ?? '')])
                ->all();
            $service->fill($serviceData)->save();

            $service->fields()->delete();
            $this->createFields($service, $data['inputs'] ?? [], FieldDirection::Input);
            $this->createFields($service, $data['outputs'] ?? [], FieldDirection::Output);

            return $service->fresh('fields');
        });
    }

    private function createFields(RemoteService $service, array $rows, FieldDirection $direction): void
    {
        foreach (array_values($rows) as $index => $row) {
            $rules = collect(explode('|', (string) ($row['validation_rules'] ?? '')))->map(fn ($rule) => trim($rule))->filter()->values()->all();
            $options = collect(preg_split('/\r\n|\r|\n|,/', (string) ($row['options'] ?? '')))->map(fn ($v) => trim($v))->filter()->values()->all();
            $service->fields()->create([
                'direction' => $direction,
                'key' => $row['key'],
                'label' => $row['label'],
                'type' => $row['type'] ?? FieldType::Text->value,
                'is_required' => (bool) ($row['is_required'] ?? false),
                'validation_rules' => $rules ?: null,
                'default_value' => $row['default_value'] ?? null,
                'json_path' => $row['json_path'] ?? null,
                'options' => $options ?: null,
                'is_sensitive' => (bool) ($row['is_sensitive'] ?? false),
                'sort_order' => $index,
            ]);
        }
    }
}
