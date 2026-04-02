<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\FeeAssessment;
use App\Models\User;
use App\Models\Site;
use App\Models\Department;
use App\Services\FeeService;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_no_show_fee_assessed_after_grace_period(): void
    {
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'consultation',
            'start_time' => now()->subMinutes(11),
            'end_time' => now()->addMinutes(19),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
            'assessed_no_show' => false,
        ]);

        $this->artisan('fees:assess-noshows')->assertExitCode(0);

        $this->assertDatabaseHas('fee_assessments', [
            'client_id' => $client->id,
            'fee_type' => 'no_show',
        ]);
    }

    public function test_no_show_fee_not_assessed_within_grace(): void
    {
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'consultation',
            'start_time' => now()->subMinutes(9),
            'end_time' => now()->addMinutes(21),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
            'assessed_no_show' => false,
        ]);

        $this->artisan('fees:assess-noshows')->assertExitCode(0);

        $this->assertDatabaseMissing('fee_assessments', [
            'client_id' => $client->id,
            'fee_type' => 'no_show',
        ]);
    }

    public function test_overdue_fine_calculation(): void
    {
        $service = app(FeeService::class);
        $this->assertSame(3.00, $service->calculateOverdueFine(100, 60));
    }

    public function test_post_payment_marks_fee_paid(): void
    {
        $token = $this->token('staff001');
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        $fee = FeeAssessment::query()->create([
            'appointment_id' => null,
            'client_id' => $client->id,
            'fee_type' => 'no_show',
            'amount' => 25.00,
            'status' => 'pending',
            'assessed_at' => now()->subDay(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/payments', [
                'reference_id' => 'BILL-PAY-1',
                'amount' => 25.00,
                'method' => 'cash',
                'fee_assessment_id' => $fee->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('fee_assessments', ['id' => $fee->id, 'status' => 'paid']);
    }

    public function test_duplicate_payment_reference_rejected(): void
    {
        $token = $this->token('staff001');

        $payload = [
            'reference_id' => 'DUP-PAY-1',
            'amount' => 10.00,
            'method' => 'cash',
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/payments', $payload)
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/payments', $payload)
            ->assertStatus(422);
    }

    public function test_reviewer_approves_waiver(): void
    {
        $token = $this->token('reviewer001');
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        $fee = FeeAssessment::query()->create([
            'appointment_id' => null,
            'client_id' => $client->id,
            'fee_type' => 'no_show',
            'amount' => 20.00,
            'status' => 'pending',
            'assessed_at' => now()->subDay(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $reviewer = User::query()->where('identifier', 'reviewer001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/fee-assessments/'.$fee->id.'/waiver', [
                'waiver_type' => 'waived',
                'waiver_note' => 'Reviewed and approved',
            ])
            ->assertOk();

        $this->assertDatabaseHas('fee_assessments', [
            'id' => $fee->id,
            'status' => 'waived',
            'waiver_by' => $reviewer->id,
        ]);
    }

    public function test_reviewer_cross_site_waiver_blocked(): void
    {
        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], ['organization_id' => 1, 'name' => 'Site 2']);
        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], ['site_id' => 2, 'name' => 'Dept 2']);

        $otherReviewer = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'reviewer_site2'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'reviewer',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ],
        );

        $token = $this->token('reviewer_site2');
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        $fee = FeeAssessment::query()->create([
            'appointment_id' => null,
            'client_id' => $client->id,
            'fee_type' => 'no_show',
            'amount' => 20.00,
            'status' => 'pending',
            'assessed_at' => now()->subDay(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/fee-assessments/'.$fee->id.'/waiver', [
                'waiver_type' => 'waived',
                'waiver_note' => 'not permitted',
            ])
            ->assertStatus(403);
    }

    private function token(string $identifier): string
    {
        User::withoutGlobalScopes()->where('identifier', $identifier)->update([
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
            'is_banned' => false,
            'muted_until' => null,
            'locked_until' => null,
            'failed_attempts' => 0,
        ]);

        $response = $this->withHeader('X-Client-Type', 'api')
            ->postJson('/api/auth/login', [
            'identifier' => $identifier,
            'password' => 'Admin@12345678',
        ])->assertOk();

        return (string) $response->json('data.access_token');
    }
}
