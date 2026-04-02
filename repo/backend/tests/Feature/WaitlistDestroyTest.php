<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use App\Models\WaitlistEntry;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class WaitlistDestroyTest extends TestCase
{
    use RefreshDatabase;

    private int $waitlistId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        $clientId = (int) User::withoutGlobalScopes()->where('identifier', 'client001')->value('id');

        $entry = WaitlistEntry::withoutGlobalScopes()->create([
            'client_id' => $clientId,
            'service_type' => 'General Consultation',
            'priority' => 1,
            'preferred_start' => now()->addDay(),
            'preferred_end' => now()->addDay()->addHour(),
            'status' => 'waiting',
            'site_id' => 1,
        ]);

        $this->waitlistId = (int) $entry->id;
    }

    public function test_staff_can_remove_waitlist_entry(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/waitlist/'.$this->waitlistId)
            ->assertOk();

        $this->assertSoftDeleted('waitlist', ['id' => $this->waitlistId]);
    }

    public function test_reviewer_cannot_remove_waitlist_entry(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/waitlist/'.$this->waitlistId)
            ->assertStatus(403);
    }

    public function test_remove_nonexistent_waitlist_entry_returns_404(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/waitlist/999999')
            ->assertStatus(404);
    }

    public function test_unauthenticated_cannot_remove(): void
    {
        $this->deleteJson('/api/waitlist/'.$this->waitlistId)
            ->assertStatus(401);
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
