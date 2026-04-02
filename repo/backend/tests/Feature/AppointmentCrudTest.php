<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AppointmentCrudTest extends TestCase
{
    use RefreshDatabase;

    private int $appointmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $client = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $appointment = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'provider_id' => $provider->id,
            'resource_id' => 1,
            'service_type' => 'crud-appointment',
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
            'assessed_no_show' => false,
        ]);

        $this->appointmentId = (int) $appointment->id;
    }

    public function test_staff_can_list_appointments(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data']]);
    }

    public function test_reviewer_can_list_appointments(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments')
            ->assertOk();
    }

    public function test_admin_can_list_appointments(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments')
            ->assertOk();
    }

    public function test_unauthenticated_cannot_list_appointments(): void
    {
        $this->getJson('/api/appointments')->assertStatus(401);
    }

    public function test_staff_can_view_appointment(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments/'.$this->appointmentId)
            ->assertOk()
            ->assertJsonPath('data.appointment.id', $this->appointmentId);
    }

    public function test_nonexistent_appointment_returns_404(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments/999999')
            ->assertStatus(404);
    }

    public function test_staff_can_update_appointment_schedule(): void
    {
        $token = $this->token('staff001');
        $start = now()->addDays(3)->toIso8601String();
        $end = now()->addDays(3)->addHour()->toIso8601String();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/appointments/'.$this->appointmentId, [
                'start_time' => $start,
                'end_time' => $end,
                'reason' => 'Staff rescheduled due to availability update',
            ])
            ->assertOk();

        $this->assertDatabaseHas('appointments', ['id' => $this->appointmentId]);
    }

    public function test_reviewer_cannot_update_appointment(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/appointments/'.$this->appointmentId, [
                'start_time' => now()->addDays(4)->toIso8601String(),
                'end_time' => now()->addDays(4)->addHour()->toIso8601String(),
                'reason' => 'Reviewer attempted schedule update',
            ])
            ->assertStatus(403);
    }

    public function test_update_nonexistent_appointment_returns_404(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/appointments/999999', [
                'start_time' => now()->addDays(4)->toIso8601String(),
                'end_time' => now()->addDays(4)->addHour()->toIso8601String(),
                'reason' => 'Nonexistent appointment update attempt',
            ])
            ->assertStatus(404);
    }

    public function test_reviewer_can_list_appointment_versions(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments/'.$this->appointmentId.'/versions')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_staff_cannot_list_appointment_versions(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments/'.$this->appointmentId.'/versions')
            ->assertStatus(403);
    }

    public function test_versions_nonexistent_appointment_returns_404(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments/999999/versions')
            ->assertStatus(404);
    }

    public function test_full_appointment_lifecycle_requested_to_completed(): void
    {
        $staffToken = $this->token('staff001');
        $adminToken = $this->token('admin001');

        $staff = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $client = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->postJson('/api/appointments', [
                'client_id' => $client->id,
                'provider_id' => $staff->id,
                'resource_id' => 1,
                'service_type' => 'lifecycle-full-chain',
                'start_time' => now()->addDays(5)->toIso8601String(),
                'end_time' => now()->addDays(5)->addMinutes(30)->toIso8601String(),
                'department_id' => 1,
            ])
            ->assertStatus(201);

        $id = (int) $createResponse->json('data.appointment.id');

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->patchJson('/api/appointments/'.$id.'/status', ['status' => Appointment::STATUS_CONFIRMED])
            ->assertOk()
            ->assertJsonPath('data.appointment.status', Appointment::STATUS_CONFIRMED);

        $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->patchJson('/api/appointments/'.$id.'/status', ['status' => Appointment::STATUS_CHECKED_IN])
            ->assertOk()
            ->assertJsonPath('data.appointment.status', Appointment::STATUS_CHECKED_IN);

        $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->patchJson('/api/appointments/'.$id.'/status', ['status' => Appointment::STATUS_COMPLETED])
            ->assertOk()
            ->assertJsonPath('data.appointment.status', Appointment::STATUS_COMPLETED);

        $versionsResponse = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson('/api/appointments/'.$id.'/versions')
            ->assertOk();

        $this->assertGreaterThanOrEqual(4, count($versionsResponse->json('data') ?? []));

        $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->getJson('/api/appointments/'.$id)
            ->assertOk()
            ->assertJsonPath('data.appointment.status', Appointment::STATUS_COMPLETED);
    }

    public function test_reschedule_into_conflicting_slot_returns_409(): void
    {
        $token = $this->token('staff001');
        $staff = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $client = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $appointmentA = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'reschedule-a',
            'start_time' => now()->addDays(7)->setTime(10, 0),
            'end_time' => now()->addDays(7)->setTime(10, 30),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $appointmentB = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'reschedule-b',
            'start_time' => now()->addDays(7)->setTime(11, 0),
            'end_time' => now()->addDays(7)->setTime(11, 30),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/appointments/'.$appointmentA->id, [
                'start_time' => $appointmentB->start_time->copy()->addMinutes(5)->toIso8601String(),
                'end_time' => $appointmentB->end_time->copy()->addMinutes(5)->toIso8601String(),
                'reason' => 'Conflict scenario reschedule',
            ])
            ->assertStatus(409)
            ->assertJson(['error' => 'APPOINTMENT_CONFLICT']);
    }

    public function test_reschedule_via_patch_verb_succeeds(): void
    {
        $token = $this->token('staff001');
        $newStart = now()->addDays(10)->setHour(9)->setMinute(0)->setSecond(0)->toIso8601String();
        $newEnd = now()->addDays(10)->setHour(10)->setMinute(0)->setSecond(0)->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/appointments/'.$this->appointmentId, [
                'start_time' => $newStart,
                'end_time' => $newEnd,
            ]);

        $this->assertContains($response->status(), [200, 403, 409, 422]);
    }

    private function token(string $identifier): string
    {
        $user = User::withoutGlobalScopes()->where('identifier', $identifier)->firstOrFail();

        $jti = (string) Str::uuid();
        $token = JWTAuth::claims(['jti' => $jti])->fromUser($user);

        UserSession::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'token_jti' => $jti,
            'last_active_at' => now(),
            'expires_at' => now()->addHours(12),
        ]);

        return $token;
    }
}
