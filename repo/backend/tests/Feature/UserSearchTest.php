<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_staff_can_search_users_within_own_site(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users/search')
            ->assertOk()
            ->assertJsonFragment(['identifier' => 'staff001']);
    }

    public function test_user_search_is_scoped_to_actor_site(): void
    {
        Site::withoutGlobalScopes()->updateOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Secondary Site',
        ]);

        Department::withoutGlobalScopes()->updateOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Secondary Department',
        ]);

        $siteTwoUser = User::withoutGlobalScopes()->create([
            'identifier' => 'site2-search-user',
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
            'role' => 'staff',
            'site_id' => 2,
            'department_id' => 2,
            'is_banned' => false,
            'failed_attempts' => 0,
        ]);

        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users/search')
            ->assertOk()
            ->assertJsonMissing(['identifier' => $siteTwoUser->identifier]);
    }

    public function test_unauthenticated_user_search_is_blocked(): void
    {
        $this->getJson('/api/users/search')->assertStatus(401);
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
