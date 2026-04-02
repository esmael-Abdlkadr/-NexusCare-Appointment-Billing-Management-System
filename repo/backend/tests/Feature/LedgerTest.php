<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        $clientId = (int) User::withoutGlobalScopes()->where('identifier', 'client001')->value('id');

        LedgerEntry::withoutGlobalScopes()->create([
            'entry_type' => 'payment',
            'amount' => 20.00,
            'reference_id' => 'LEDGER-TEST-001',
            'client_id' => $clientId,
            'site_id' => 1,
            'description' => 'ledger test',
            'created_at' => now(),
        ]);
    }

    public function test_admin_can_view_ledger(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/ledger')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_reviewer_cannot_view_ledger(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/ledger')
            ->assertStatus(403);
    }

    public function test_staff_cannot_view_ledger(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/ledger')
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_view_ledger(): void
    {
        $this->getJson('/api/ledger')->assertStatus(401);
    }

    public function test_ledger_supports_date_filter(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/ledger?date_from=2024-01-01&date_to=2025-12-31')
            ->assertOk();
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
