<?php

namespace Database\Seeders;

use App\Models\FeeRule;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Site;
use App\Models\Department;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::withoutGlobalScopes()->firstOrCreate(['id' => 1], [
            'organization_id' => 1,
            'name' => 'Main Site',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 1], [
            'site_id' => $site->id,
            'name' => 'General',
        ]);

        $password = Hash::make('Admin@12345678', ['rounds' => 12]);

        $users = [
            ['identifier' => 'admin001', 'role' => 'administrator', 'department_id' => 1],
            ['identifier' => 'reviewer001', 'role' => 'reviewer', 'department_id' => 1],
            ['identifier' => 'staff001', 'role' => 'staff', 'department_id' => 1],
            ['identifier' => 'staff002', 'role' => 'staff', 'department_id' => 1],
            ['identifier' => 'client001', 'role' => 'staff', 'department_id' => 1],
        ];

        foreach ($users as $data) {
            User::withoutGlobalScopes()->updateOrCreate(
                ['identifier' => $data['identifier']],
                [
                    'password_hash' => $password,
                    'role' => $data['role'],
                    'site_id' => 1,
                    'department_id' => $data['department_id'],
                    'is_banned' => false,
                    'failed_attempts' => 0,
                ],
            );
        }

        Resource::withoutGlobalScopes()->firstOrCreate(['id' => 1], [
            'name' => 'Resource 1',
            'type' => 'room',
            'site_id' => 1,
            'is_active' => true,
        ]);

        Resource::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'name' => 'Resource 2',
            'type' => 'room',
            'site_id' => 1,
            'is_active' => true,
        ]);

        FeeRule::withoutGlobalScopes()->updateOrCreate(
            ['site_id' => 1, 'fee_type' => 'no_show'],
            ['amount' => 25.00, 'rate' => null, 'period_days' => null, 'grace_minutes' => 10, 'is_active' => true],
        );

        FeeRule::withoutGlobalScopes()->updateOrCreate(
            ['site_id' => 1, 'fee_type' => 'overdue'],
            ['amount' => 0.00, 'rate' => 0.0150, 'period_days' => 30, 'grace_minutes' => null, 'is_active' => true],
        );

        FeeRule::withoutGlobalScopes()->updateOrCreate(
            ['site_id' => 1, 'fee_type' => 'lost_damaged'],
            ['amount' => 50.00, 'rate' => null, 'period_days' => null, 'grace_minutes' => null, 'is_active' => true],
        );

        $roles = Role::query()->pluck('id', 'name');
        $allUsers = User::withoutGlobalScopes()->whereIn('identifier', array_column($users, 'identifier'))->get();

        foreach ($allUsers as $user) {
            $roleId = $roles[$user->role] ?? null;
            if (! $roleId) {
                continue;
            }

            UserRole::withoutGlobalScopes()->updateOrCreate(
                ['user_id' => $user->id, 'role_id' => $roleId, 'site_id' => 1],
                ['created_at' => now()],
            );
        }
    }
}
