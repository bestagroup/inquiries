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
        $admin = User::query()->where('email', $email)->first();

        if ($admin && ! $admin->isAdmin()) {
            throw new RuntimeException('ADMIN_EMAIL belongs to an existing non-admin user; seeding aborted.');
        }

        if (! $admin) {
            if (! $password && app()->environment('production')) {
                throw new RuntimeException('ADMIN_PASSWORD must be configured when bootstrapping the production admin account.');
            }

            $admin = User::query()->create([
                'email' => $email,
                'name' => (string) env('ADMIN_NAME', 'System Administrator'),
                'password' => $password ?: 'ChangeMe!12345',
                'role' => UserRole::Admin,
                'is_active' => true,
            ]);
        }

        $this->call(ServiceCatalogSeeder::class);

        $admin->services()->syncWithoutDetaching(
            RemoteService::query()->pluck('id')->mapWithKeys(fn (int $id) => [
                $id => ['is_active' => true, 'assigned_at' => now()],
            ])->all()
        );
    }
}
