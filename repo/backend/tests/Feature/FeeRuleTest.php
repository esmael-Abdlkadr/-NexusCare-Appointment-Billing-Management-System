<?php

namespace Tests\Feature;

use App\Models\FeeRule;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class FeeRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_admin_can_list_fee_rules(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/fee-rules')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_reviewer_cannot_list_fee_rules(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/fee-rules')
            ->assertStatus(403);
    }

    public function test_staff_cannot_list_fee_rules(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/fee-rules')
            ->assertStatus(403);
    }

    public function test_admin_can_create_fee_rule(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/fee-rules', [
                'fee_type' => 'lost_damaged',
                'amount' => 75.00,
                'is_active' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('fee_rules', [
            'fee_type' => 'lost_damaged',
            'site_id' => 1,
            'amount' => 75.00,
        ]);
    }

    public function test_create_fee_rule_missing_required_fields(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/fee-rules', [])
            ->assertStatus(422);
    }

    public function test_reviewer_cannot_create_fee_rule(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/fee-rules', [
                'fee_type' => 'lost_damaged',
                'amount' => 75.00,
            ])
            ->assertStatus(403);
    }

    public function test_admin_can_delete_fee_rule(): void
    {
        $token = $this->token('admin001');

        $rule = FeeRule::withoutGlobalScopes()->create([
            'fee_type' => 'lost_damaged',
            'amount' => 99.00,
            'rate' => null,
            'period_days' => null,
            'grace_minutes' => null,
            'site_id' => 1,
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/fee-rules/'.$rule->id)
            ->assertOk();

        $this->assertDatabaseHas('fee_rules', ['id' => $rule->id, 'is_active' => 0]);
    }

    public function test_reviewer_cannot_delete_fee_rule(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/fee-rules/1')
            ->assertStatus(403);
    }

    public function test_delete_nonexistent_fee_rule_returns_404(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/fee-rules/999999')
            ->assertStatus(404);
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
