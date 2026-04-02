<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentVersion;
use App\Models\User;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_staff_can_create_appointment(): void
    {
        $token = $this->token('staff001');
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/appointments', [
                'client_id' => $staff->id,
                'provider_id' => $staff->id,
                'resource_id' => 1,
                'service_type' => 'consultation',
                'start_time' => now()->addDay()->setHour(10)->setMinute(0)->toIso8601String(),
                'end_time' => now()->addDay()->setHour(10)->setMinute(30)->toIso8601String(),
                'department_id' => 1,
            ])
            ->assertStatus(201);
    }

    public function test_reviewer_cannot_create_appointment(): void
    {
        $token = $this->token('reviewer001');
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/appointments', [
                'client_id' => $staff->id,
                'provider_id' => $staff->id,
                'resource_id' => 1,
                'service_type' => 'consultation',
                'start_time' => now()->addDay()->setHour(10)->setMinute(0)->toIso8601String(),
                'end_time' => now()->addDay()->setHour(10)->setMinute(30)->toIso8601String(),
                'department_id' => 1,
            ])
            ->assertStatus(403);
    }

    public function test_conflict_detection_provider(): void
    {
        $token = $this->token('staff001');
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        Appointment::withoutGlobalScopes()->create([
            'client_id' => $staff->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'consultation',
            'start_time' => now()->addDay()->setHour(11)->setMinute(0),
            'end_time' => now()->addDay()->setHour(11)->setMinute(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/appointments', [
                'client_id' => $staff->id,
                'provider_id' => $staff->id,
                'resource_id' => 2,
                'service_type' => 'consultation',
                'start_time' => now()->addDay()->setHour(11)->setMinute(10)->toIso8601String(),
                'end_time' => now()->addDay()->setHour(11)->setMinute(40)->toIso8601String(),
                'department_id' => 1,
            ]);

        $response->assertStatus(409)->assertJson(['error' => 'APPOINTMENT_CONFLICT']);
        $this->assertSame('provider', $response->json('data.conflict_type'));
        $this->assertNotEmpty($response->json('data.next_available_slots'));
    }

    public function test_conflict_detection_resource(): void
    {
        $token = $this->token('staff001');
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        Appointment::withoutGlobalScopes()->create([
            'client_id' => $staff->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'consultation',
            'start_time' => now()->addDay()->setHour(12)->setMinute(0),
            'end_time' => now()->addDay()->setHour(12)->setMinute(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $other = User::query()->where('identifier', 'staff002')->firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/appointments', [
                'client_id' => $staff->id,
                'provider_id' => $other->id,
                'resource_id' => 1,
                'service_type' => 'consultation',
                'start_time' => now()->addDay()->setHour(12)->setMinute(10)->toIso8601String(),
                'end_time' => now()->addDay()->setHour(12)->setMinute(40)->toIso8601String(),
                'department_id' => 1,
            ]);

        $response->assertStatus(409)->assertJson(['error' => 'APPOINTMENT_CONFLICT']);
        $this->assertSame('resource', $response->json('data.conflict_type'));
    }

    public function test_invalid_status_transition(): void
    {
        $token = $this->token('staff001');
        $appt = $this->createRequestedAppointment();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/appointments/'.$appt->id.'/status', ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJson(['error' => 'INVALID_TRANSITION']);
    }

    public function test_cancel_requires_reason(): void
    {
        $token = $this->token('staff001');
        $appt = $this->createRequestedAppointment();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/appointments/'.$appt->id.'/status', ['status' => 'confirmed'])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/appointments/'.$appt->id.'/status', ['status' => 'cancelled'])
            ->assertStatus(422);
    }

    public function test_version_created_on_transition(): void
    {
        $token = $this->token('staff001');
        $appt = $this->createRequestedAppointment();
        AppointmentVersion::query()->where('appointment_id', $appt->id)->delete();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/appointments/'.$appt->id.'/status', ['status' => 'confirmed'])
            ->assertOk();

        $this->assertDatabaseCount('appointment_versions', 1);
    }

    public function test_cross_site_appointment_blocked(): void
    {
        $token = $this->token('staff001');
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        $appt = Appointment::withoutGlobalScopes()->create([
            'client_id' => $staff->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'consultation',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => Appointment::STATUS_REQUESTED,
            'site_id' => 2,
            'department_id' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments/'.$appt->id);

        $this->assertContains($response->status(), [403, 404]);
    }

    private function token(string $identifier): string
    {
        User::query()->where('identifier', $identifier)->update([
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
            'is_banned' => false,
            'muted_until' => null,
            'locked_until' => null,
            'failed_attempts' => 0,
        ]);

        if ($identifier === 'reviewer001') {
            User::query()->where('identifier', $identifier)->update(['role' => 'reviewer']);
        }

        $response = $this->withHeader('X-Client-Type', 'api')
            ->postJson('/api/auth/login', [
            'identifier' => $identifier,
            'password' => 'Admin@12345678',
        ])->assertOk();

        return (string) $response->json('data.access_token');
    }

    private function createRequestedAppointment(): Appointment
    {
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        return Appointment::withoutGlobalScopes()->create([
            'client_id' => $staff->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'consultation',
            'start_time' => now()->addDay()->setHour(14)->setMinute(0),
            'end_time' => now()->addDay()->setHour(14)->setMinute(30),
            'status' => Appointment::STATUS_REQUESTED,
            'site_id' => 1,
            'department_id' => 1,
        ]);
    }
}
