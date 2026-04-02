<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class IterationThreeTestSeeder extends Seeder
{
    public function run(): void
    {
        Site::withoutGlobalScopes()->firstOrCreate(
            ['id' => 2],
            ['organization_id' => 1, 'name' => 'Site Two'],
        );

        Department::withoutGlobalScopes()->firstOrCreate(
            ['id' => 2],
            ['site_id' => 2, 'name' => 'Dept Two'],
        );

        $staff = User::withoutGlobalScopes()->firstOrCreate(
            ['identifier' => 'staff1'],
            [
                'password_hash' => Hash::make('Staff@NexusCare1'),
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
                'is_banned' => false,
                'failed_attempts' => 0,
            ],
        );

        $reviewer = User::withoutGlobalScopes()->firstOrCreate(
            ['identifier' => 'reviewer1'],
            [
                'password_hash' => Hash::make('Reviewer@NexusCare1'),
                'role' => 'reviewer',
                'site_id' => 1,
                'department_id' => 1,
                'is_banned' => false,
                'failed_attempts' => 0,
            ],
        );

        $staffOther = User::withoutGlobalScopes()->firstOrCreate(
            ['identifier' => 'staff2'],
            [
                'password_hash' => Hash::make('Staff2@NexusCare1'),
                'role' => 'staff',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ],
        );

        $client = User::withoutGlobalScopes()->firstOrCreate(
            ['identifier' => 'client1'],
            [
                'password_hash' => Hash::make('Client@NexusCare1'),
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
                'is_banned' => false,
                'failed_attempts' => 0,
            ],
        );

        Resource::withoutGlobalScopes()->firstOrCreate(
            ['id' => 1],
            ['name' => 'Room 101', 'type' => 'room', 'site_id' => 1, 'is_active' => true],
        );

        Resource::withoutGlobalScopes()->firstOrCreate(
            ['id' => 2],
            ['name' => 'Room 201', 'type' => 'room', 'site_id' => 2, 'is_active' => true],
        );

        $staffRole = Role::query()->where('name', 'staff')->first();
        $reviewerRole = Role::query()->where('name', 'reviewer')->first();

        if ($staffRole) {
            UserRole::withoutGlobalScopes()->firstOrCreate([
                'user_id' => $staff->id,
                'role_id' => $staffRole->id,
                'site_id' => 1,
            ], [
                'created_at' => now(),
            ]);

            UserRole::withoutGlobalScopes()->firstOrCreate([
                'user_id' => $staffOther->id,
                'role_id' => $staffRole->id,
                'site_id' => 2,
            ], [
                'created_at' => now(),
            ]);

            UserRole::withoutGlobalScopes()->firstOrCreate([
                'user_id' => $client->id,
                'role_id' => $staffRole->id,
                'site_id' => 1,
            ], [
                'created_at' => now(),
            ]);
        }

        if ($reviewerRole) {
            UserRole::withoutGlobalScopes()->firstOrCreate([
                'user_id' => $reviewer->id,
                'role_id' => $reviewerRole->id,
                'site_id' => 1,
            ], [
                'created_at' => now(),
            ]);
        }
    }
}
