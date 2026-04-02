<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_any_authenticated_user_can_list_resources(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/resources')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Resource 1']);
    }

    public function test_unauthenticated_cannot_list_resources(): void
    {
        $this->getJson('/api/resources')->assertStatus(401);
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
