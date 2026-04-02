<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\FeeAssessment;
use App\Models\FeeRule;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class IterationFourTestSeeder extends Seeder
{
    public function run(): void
    {
        FeeRule::withoutGlobalScopes()->updateOrCreate(
            ['site_id' => 1, 'fee_type' => 'no_show'],
            ['amount' => 25.00, 'rate' => null, 'period_days' => null, 'grace_minutes' => 10, 'is_active' => true],
        );

        FeeRule::withoutGlobalScopes()->updateOrCreate(
            ['site_id' => 1, 'fee_type' => 'overdue'],
            ['amount' => 0.00, 'rate' => 0.0150, 'period_days' => 30, 'grace_minutes' => null, 'is_active' => true],
        );

        FeeRule::withoutGlobalScopes()->updateOrCreate(
            ['site_id' => 2, 'fee_type' => 'no_show'],
            ['amount' => 25.00, 'rate' => null, 'period_days' => null, 'grace_minutes' => 10, 'is_active' => true],
        );

        FeeRule::withoutGlobalScopes()->updateOrCreate(
            ['site_id' => 2, 'fee_type' => 'overdue'],
            ['amount' => 0.00, 'rate' => 0.0150, 'period_days' => 30, 'grace_minutes' => null, 'is_active' => true],
        );

        $staff = User::withoutGlobalScopes()->where('identifier', 'staff1')->first();
        $client = User::withoutGlobalScopes()->where('identifier', 'client1')->first();

        $reviewerTwo = User::withoutGlobalScopes()->firstOrCreate(
            ['identifier' => 'reviewer2'],
            [
                'password_hash' => Hash::make('Reviewer2@NexusCare1'),
                'role' => 'reviewer',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ],
        );

        $reviewerRole = Role::query()->where('name', 'reviewer')->first();
        if ($reviewerRole) {
            UserRole::withoutGlobalScopes()->firstOrCreate([
                'user_id' => $reviewerTwo->id,
                'role_id' => $reviewerRole->id,
                'site_id' => 2,
            ], [
                'created_at' => now(),
            ]);
        }

        if ($staff && $client) {
            Appointment::withoutGlobalScopes()->updateOrCreate(
                [
                    'provider_id' => $staff->id,
                    'client_id' => $client->id,
                    'service_type' => 'billing-no-show-test',
                ],
                [
                    'resource_id' => 1,
                    'start_time' => now()->subMinutes(11),
                    'end_time' => now()->subMinutes(1),
                    'status' => Appointment::STATUS_CONFIRMED,
                    'cancel_reason' => null,
                    'assessed_no_show' => false,
                    'site_id' => 1,
                    'department_id' => 1,
                ],
            );

            FeeAssessment::query()->firstOrCreate([
                'client_id' => $client->id,
                'fee_type' => 'no_show',
                'amount' => 100.00,
                'assessed_at' => now()->subDays(35),
            ], [
                'appointment_id' => null,
                'status' => 'pending',
                'due_date' => now()->subDays(35)->toDateString(),
            ]);
        }
    }
}
