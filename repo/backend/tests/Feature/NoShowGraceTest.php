<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\FeeRule;
use App\Models\User;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifies that the no-show fee grace period is enforced on manual status transitions.
 *
 * The seeder creates a no_show FeeRule with grace_minutes = 10 for site_id = 1.
 * Transitioning to no_show BEFORE the grace window must NOT create a fee_assessment.
 * Transitioning AFTER the grace window MUST create a fee_assessment.
 */
class NoShowGraceTest extends TestCase
{
    use RefreshDatabase;

    private int $providerId;
    private int $clientId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        $this->providerId = (int) User::withoutGlobalScopes()->where('identifier', 'staff001')->value('id');
        $this->clientId   = (int) User::withoutGlobalScopes()->where('identifier', 'client001')->value('id');
    }

    public function test_no_show_transition_within_grace_period_does_not_assess_fee(): void
    {
        // Grace is 10 min — start_time is 5 min ago (still within grace)
        $appointment = $this->confirmedAppointment(now()->subMinutes(5));
        $token = $this->loginAs('staff001');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Client-Type' => 'api',
        ])->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'no_show',
        ])->assertOk();

        $this->assertDatabaseMissing('fee_assessments', [
            'appointment_id' => $appointment->id,
            'fee_type'       => 'no_show',
        ]);
    }

    public function test_no_show_transition_after_grace_period_assesses_fee(): void
    {
        // Grace is 10 min — start_time is 15 min ago (past grace)
        $appointment = $this->confirmedAppointment(now()->subMinutes(15));
        $token = $this->loginAs('staff001');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Client-Type' => 'api',
        ])->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'no_show',
        ])->assertOk();

        $this->assertDatabaseHas('fee_assessments', [
            'appointment_id' => $appointment->id,
            'fee_type'       => 'no_show',
            'status'         => 'pending',
        ]);
    }

    public function test_no_show_fee_is_not_duplicated_on_double_assessment(): void
    {
        // Appointment is past grace period
        $appointment = $this->confirmedAppointment(now()->subMinutes(20));
        $token = $this->loginAs('staff001');

        // First transition — creates the fee
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Client-Type' => 'api',
        ])->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'no_show',
        ])->assertOk();

        // Directly call assessNoShowFee again (simulates scheduler running after manual transition)
        $appointment->refresh();
        app(\App\Services\FeeService::class)->assessNoShowFee($appointment);

        $this->assertDatabaseCount('fee_assessments', 1);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function confirmedAppointment(\Carbon\Carbon $startTime): Appointment
    {
        return Appointment::withoutGlobalScopes()->create([
            'client_id'       => $this->clientId,
            'provider_id'     => $this->providerId,
            'resource_id'     => 1,
            'service_type'    => 'no-show-grace-test',
            'start_time'      => $startTime,
            'end_time'        => $startTime->copy()->addHour(),
            'status'          => Appointment::STATUS_CONFIRMED,
            'site_id'         => 1,
            'department_id'   => 1,
            'assessed_no_show' => false,
        ]);
    }

    private function loginAs(string $identifier): string
    {
        User::withoutGlobalScopes()->where('identifier', $identifier)->update([
            'password_hash'   => Hash::make('Admin@12345678', ['rounds' => 12]),
            'is_banned'       => false,
            'muted_until'     => null,
            'locked_until'    => null,
            'failed_attempts' => 0,
        ]);

        $response = $this->withHeader('X-Client-Type', 'api')
            ->postJson('/api/auth/login', [
                'identifier' => $identifier,
                'password'   => 'Admin@12345678',
            ])->assertOk();

        return (string) $response->json('data.access_token');
    }
}
