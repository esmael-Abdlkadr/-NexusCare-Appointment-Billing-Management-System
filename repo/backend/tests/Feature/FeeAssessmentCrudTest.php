<?php

namespace Tests\Feature;

use App\Models\FeeAssessment;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class FeeAssessmentCrudTest extends TestCase
{
    use RefreshDatabase;

    private int $feeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        $clientId = (int) User::withoutGlobalScopes()->where('identifier', 'client001')->value('id');

        $fee = FeeAssessment::withoutGlobalScopes()->create([
            'appointment_id' => null,
            'client_id' => $clientId,
            'fee_type' => 'no_show',
            'amount' => 25.00,
            'status' => 'pending',
            'assessed_at' => now()->subDay(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->feeId = (int) $fee->id;
    }

    public function test_staff_can_list_fee_assessments(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/fee-assessments')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data']]);
    }

    public function test_reviewer_can_list_fee_assessments(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/fee-assessments')
            ->assertOk();
    }

    public function test_admin_can_list_fee_assessments(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/fee-assessments')
            ->assertOk();
    }

    public function test_unauthenticated_cannot_list(): void
    {
        $this->getJson('/api/fee-assessments')->assertStatus(401);
    }

    public function test_staff_can_view_fee_assessment(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/fee-assessments/'.$this->feeId)
            ->assertOk()
            ->assertJsonPath('data.fee_assessment.id', $this->feeId);
    }

    public function test_nonexistent_fee_assessment_returns_404(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/fee-assessments/999999')
            ->assertStatus(404);
    }

    public function test_staff_cannot_approve_waiver(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/fee-assessments/'.$this->feeId.'/waiver', [
                'waiver_type' => 'waived',
                'waiver_note' => 'Not allowed for staff',
            ])
            ->assertStatus(403);
    }

    public function test_reviewer_can_write_off_fee(): void
    {
        $token = $this->token('reviewer001');
        $fee = FeeAssessment::query()
            ->whereIn('status', ['pending', 'disputed'])
            ->first();

        if (! $fee) {
            $this->markTestSkipped('No pending/disputed fee available for write-off test.');
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/fees/'.$fee->id.'/write-off', [
                'note' => 'Written off per E2E test authorization check',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('fee_assessments', [
            'id' => $fee->id,
            'status' => 'written_off',
        ]);
    }

    public function test_staff_cannot_write_off_fee(): void
    {
        $token = $this->token('staff001');
        $fee = FeeAssessment::query()->where('status', 'pending')->first();

        if (! $fee) {
            $this->markTestSkipped('No pending fee available.');
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/fees/'.$fee->id.'/write-off', ['note' => 'staff attempt'])
            ->assertStatus(403);
    }

    public function test_staff_can_list_fees(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/fees')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data']]);
    }

    public function test_reviewer_can_list_fees(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/fees')
            ->assertOk();
    }

    public function test_unauthenticated_cannot_list_fees(): void
    {
        $this->getJson('/api/fees')->assertStatus(401);
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
