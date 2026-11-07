<?php

namespace Database\Seeders;

use App\Models\AiSetting;
use App\Models\Plan;
use App\Models\TopUpPack;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Roles + permissions must exist before users are given workspaces.
        $this->call(RolesAndPermissionsSeeder::class);

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Demo User',
                'password' => 'password',
                'is_admin' => false,
                'email_verified_at' => now(),
            ],
        );

        AiSetting::current();

        Plan::updateOrCreate(
            ['name' => 'Free'],
            ['credit_limit' => 100, 'price' => 0.00, 'duration_days' => 30, 'is_active' => true,
                'max_jobs' => 3, 'max_seats' => 1, 'max_files_per_batch' => 25, 'max_file_mb' => 10,
                'description' => '100 credits/mo · 3 jobs · 25 CVs/job · 1 seat'],
        );
        Plan::updateOrCreate(
            ['name' => 'Starter'],
            ['credit_limit' => 500, 'price' => 9.00, 'duration_days' => 30, 'is_active' => true,
                'max_jobs' => 15, 'max_seats' => 3, 'max_files_per_batch' => 50, 'max_file_mb' => 15,
                'description' => '500 credits/mo · 15 jobs · 3 seats'],
        );
        Plan::updateOrCreate(
            ['name' => 'Pro'],
            ['credit_limit' => 2000, 'price' => 49.00, 'duration_days' => 30, 'is_active' => true,
                'max_jobs' => 50, 'max_seats' => 10, 'max_files_per_batch' => 100, 'max_file_mb' => 25,
                'description' => '2,000 credits/mo · 50 jobs · 10 seats'],
        );
        Plan::updateOrCreate(
            ['name' => 'Business'],
            ['credit_limit' => 10000, 'price' => 199.00, 'duration_days' => 30, 'is_active' => true,
                'max_jobs' => 500, 'max_seats' => 25, 'max_files_per_batch' => 200, 'max_file_mb' => 50,
                'description' => '10,000 credits/mo · 500 jobs · 25 seats'],
        );

        TopUpPack::updateOrCreate(['name' => 'Small pack'], ['credits' => 250, 'price' => 10.00, 'is_active' => true]);
        TopUpPack::updateOrCreate(['name' => 'Medium pack'], ['credits' => 1000, 'price' => 30.00, 'is_active' => true]);
        TopUpPack::updateOrCreate(['name' => 'Large pack'], ['credits' => 5000, 'price' => 120.00, 'is_active' => true]);
    }
}
