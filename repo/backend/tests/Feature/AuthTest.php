<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_login_success(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'admin001',
            'password' => 'Admin@12345678',
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['token_type', 'user']]);
        $this->assertArrayNotHasKey('access_token', $response->json('data'));
    }

    public function test_login_wrong_password(): void
    {
        $this->postJson('/api/auth/login', [
            'identifier' => 'admin001',
            'password' => 'WrongPassword1!',
        ])->assertStatus(401);
    }

    public function test_login_account_locked(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'identifier' => 'staff001',
                'password' => 'WrongPass@123',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'staff001',
            'password' => 'Admin@12345678',
        ]);

        $response->assertStatus(423)->assertJsonStructure(['data' => ['locked_until']]);
    }

    public function test_login_banned_account(): void
    {
        $user = User::query()->where('identifier', 'staff001')->firstOrFail();
        $user->is_banned = true;
        $user->save();

        $this->postJson('/api/auth/login', [
            'identifier' => 'staff001',
            'password' => 'Admin@12345678',
        ])->assertStatus(403)->assertJson(['error' => 'ACCOUNT_BANNED']);
    }

    public function test_idle_timeout(): void
    {
        $token = $this->loginAs('staff001');
        $session = UserSession::query()->latest('id')->firstOrFail();
        $session->last_active_at = now()->subMinutes(31);
        $session->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJson(['error' => 'SESSION_IDLE_TIMEOUT']);
    }

    public function test_absolute_timeout(): void
    {
        $token = $this->loginAs('staff001');
        $session = UserSession::query()->latest('id')->firstOrFail();
        $session->expires_at = now()->subMinute();
        $session->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJson(['error' => 'SESSION_EXPIRED']);
    }

    public function test_logout_invalidates_session(): void
    {
        $token = $this->loginAs('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_muted_user_blocked_on_write(): void
    {
        $user = User::query()->where('identifier', 'staff001')->firstOrFail();
        $user->muted_until = now()->addHour();
        $user->save();

        $token = $this->loginAs('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/appointments', [
                'client_id' => $user->id,
                'provider_id' => $user->id,
                'resource_id' => 1,
                'service_type' => 'consultation',
                'start_time' => now()->addHour()->toIso8601String(),
                'end_time' => now()->addHours(2)->toIso8601String(),
                'department_id' => 1,
            ])
            ->assertStatus(403)
            ->assertJson(['error' => 'ACCOUNT_MUTED']);
    }

    public function test_muted_user_allowed_on_read(): void
    {
        $user = User::query()->where('identifier', 'staff001')->firstOrFail();
        $user->muted_until = now()->addHour();
        $user->save();

        $token = $this->loginAs('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments')
            ->assertOk();
    }

    public function test_browser_login_does_not_return_access_token_in_body(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'admin001',
            'password' => 'Admin@12345678',
        ])->assertOk();

        $this->assertArrayNotHasKey(
            'access_token',
            $response->json('data'),
            'Browser login must not expose access_token in response body'
        );
        $this->assertSame('cookie', $response->json('data.token_type'));
    }

    public function test_api_client_login_returns_access_token_in_body(): void
    {
        $response = $this->withHeader('X-Client-Type', 'api')
            ->postJson('/api/auth/login', [
                'identifier' => 'admin001',
                'password' => 'Admin@12345678',
            ])->assertOk();

        $this->assertArrayHasKey(
            'access_token',
            $response->json('data'),
            'API client login must include access_token in response body'
        );
        $this->assertSame('bearer', $response->json('data.token_type'));
        $this->assertNotEmpty($response->json('data.access_token'));
    }

    public function test_login_response_sets_httponly_cookie(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'admin001',
            'password' => 'Admin@12345678',
        ])->assertOk();

        $response->assertCookie('nexuscare_token');

        $allSetCookieHeaders = implode(' ', $response->headers->all('set-cookie'));
        $this->assertMatchesRegularExpression('/httponly/i', $allSetCookieHeaders, 'Token cookie must be HttpOnly');
    }

    public function test_login_access_token_is_not_present_in_audit_log(): void
    {
        $this->postJson('/api/auth/login', [
            'identifier' => 'admin001',
            'password' => 'Admin@12345678',
        ])->assertOk();

        $log = \DB::table('audit_logs')
            ->where('action', 'LOGIN')
            ->latest()
            ->first();

        $this->assertNotNull($log, 'LOGIN audit log entry must exist');
        $decoded = json_decode($log->payload ?? '{}', true);
        $this->assertArrayNotHasKey('access_token', $decoded);
        $this->assertArrayNotHasKey('password', $decoded);
    }

    private function loginAs(string $identifier): string
    {
        User::query()->where('identifier', $identifier)->update([
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
            'is_banned' => false,
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
