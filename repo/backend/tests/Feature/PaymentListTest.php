<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PaymentListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        $staff = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        Payment::withoutGlobalScopes()->create([
            'reference_id' => 'LIST-TEST-1',
            'amount' => 10.00,
            'method' => 'cash',
            'fee_assessment_id' => null,
            'posted_by' => $staff->id,
            'site_id' => 1,
            'notes' => 'seed list payment',
            'created_at' => now(),
        ]);
    }

    public function test_staff_can_list_payments(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/payments')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_reviewer_can_list_payments(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/payments')
            ->assertOk();
    }

    public function test_admin_can_list_payments(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/payments')
            ->assertOk();
    }

    public function test_unauthenticated_cannot_list_payments(): void
    {
        $this->getJson('/api/payments')->assertStatus(401);
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
