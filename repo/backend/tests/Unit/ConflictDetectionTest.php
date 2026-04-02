<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\User;
use App\Repositories\AppointmentRepository;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConflictDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_overlapping_times_detected(): void
    {
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        Appointment::withoutGlobalScopes()->create([
            'client_id' => $staff->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'overlap-a',
            'start_time' => now()->addDay()->setHour(10),
            'end_time' => now()->addDay()->setHour(10)->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $repo = app(AppointmentRepository::class);
        $conflicts = $repo->overlappingConflicts(
            $staff->id,
            1,
            now()->addDay()->setHour(10)->addMinutes(10),
            now()->addDay()->setHour(10)->addMinutes(40),
            1,
        );

        $this->assertCount(1, $conflicts);
    }

    public function test_adjacent_times_not_conflict(): void
    {
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        Appointment::withoutGlobalScopes()->create([
            'client_id' => $staff->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'adjacent-a',
            'start_time' => now()->addDay()->setHour(11),
            'end_time' => now()->addDay()->setHour(11)->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $repo = app(AppointmentRepository::class);
        $conflicts = $repo->overlappingConflicts(
            $staff->id,
            1,
            now()->addDay()->setHour(11)->addMinutes(30),
            now()->addDay()->setHour(12),
            1,
        );

        $this->assertCount(0, $conflicts);
    }

    public function test_cancelled_appointment_not_in_conflict(): void
    {
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        Appointment::withoutGlobalScopes()->create([
            'client_id' => $staff->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'cancelled-a',
            'start_time' => now()->addDay()->setHour(13),
            'end_time' => now()->addDay()->setHour(13)->addMinutes(30),
            'status' => Appointment::STATUS_CANCELLED,
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $repo = app(AppointmentRepository::class);
        $conflicts = $repo->overlappingConflicts(
            $staff->id,
            1,
            now()->addDay()->setHour(13)->addMinutes(10),
            now()->addDay()->setHour(13)->addMinutes(40),
            1,
        );

        $this->assertCount(0, $conflicts);
    }
}
