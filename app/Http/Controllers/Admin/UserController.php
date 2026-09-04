<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\RemoteService;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            $query = User::query()
                ->select(['id', 'name', 'email', 'phone', 'is_active', 'created_at'])
                ->where('role', UserRole::User->value)
                ->withCount([
                    'services as active_services_count' => fn ($query) => $query->where('service_user.is_active', true),
                ]);

            return DataTables::eloquent($query)
                ->editColumn('is_active', fn (User $user) => $user->is_active ? 'فعال' : 'غیرفعال')
                ->addColumn('action', fn (User $user) => view('admin.users._actions', compact('user'))->render())
                ->rawColumns(['action'])
                ->toJson();
        }

        return view('admin.users.index');
    }

    public function create(): View
    {
        $services = RemoteService::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']);

        return view('admin.users.form', ['user' => new User, 'services' => $services, 'selectedServices' => []]);
    }

    public function store(StoreUserRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
                'role' => UserRole::User,
                'is_active' => $validated['is_active'] ?? true,
            ]);
            $this->syncServices($user, $validated['service_ids'] ?? []);

            return $user;
        });
        $audit->write('user.created', $user, ['service_ids' => $validated['service_ids'] ?? []]);

        return redirect()->route('admin.users.index')->with('success', 'کاربر با موفقیت ایجاد شد.');
    }

    public function edit(User $user): View
    {
        abort_if($user->isAdmin(), 404);
        $services = RemoteService::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']);
        $selectedServices = $user->services()->pluck('services.id')->all();

        return view('admin.users.form', compact('user', 'services', 'selectedServices'));
    }

    public function update(UpdateUserRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        abort_if($user->isAdmin(), 404);
        $validated = $request->validated();
        DB::transaction(function () use ($user, $validated): void {
            $payload = [
                'name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'],
            ];
            if (! empty($validated['password'])) {
                $payload['password'] = $validated['password'];
            }
            $user->update($payload);
            $this->syncServices($user, $validated['service_ids'] ?? []);
        });
        $audit->write('user.updated', $user, ['service_ids' => $validated['service_ids'] ?? []]);

        return redirect()->route('admin.users.index')->with('success', 'اطلاعات کاربر بروزرسانی شد.');
    }

    public function destroy(User $user, AuditLogger $audit): RedirectResponse
    {
        abort_if($user->isAdmin(), 404);
        $user->update(['is_active' => false]);
        $audit->write('user.deactivated', $user);

        return redirect()->route('admin.users.index')->with('success', 'کاربر غیرفعال شد و سوابق تاریخی او حفظ گردید.');
    }

    private function syncServices(User $user, array $serviceIds): void
    {
        $sync = collect($serviceIds)->unique()->mapWithKeys(fn ($id) => [(int) $id => ['is_active' => true, 'assigned_at' => now()]])->all();
        $user->services()->sync($sync);
    }
}
