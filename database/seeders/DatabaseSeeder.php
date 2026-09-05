<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\RemoteService;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD');

        if (! $password && app()->environment('production')) {
            throw new RuntimeException('ADMIN_PASSWORD must be configured before production seeding.');
        }

        $admin = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('ADMIN_NAME', 'System Administrator'),
                'password' => $password ?: 'ChangeMe!12345',
                'role' => UserRole::Admin,
                'is_active' => true,
            ]
        );

        $this->call(ServiceCatalogSeeder::class);

        $admin->services()->syncWithoutDetaching(
            RemoteService::query()->pluck('id')->mapWithKeys(fn (int $id) => [
                $id => ['is_active' => true, 'assigned_at' => now()],
            ])->all()
        );
    }
}
