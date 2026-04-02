<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        $userId = (int) User::withoutGlobalScopes()->where('identifier', 'admin001')->value('id');

        AuditLog::withoutGlobalScopes()->create([
            'user_id' => $userId,
            'action' => 'LOGIN',
            'target_type' => User::class,
            'target_id' => $userId,
            'payload' => ['identifier' => 'admin001'],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);
    }

    public function test_reviewer_can_list_audit_logs(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data']]);
    }

    public function test_admin_can_list_audit_logs(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/audit-logs')
            ->assertOk();
    }

    public function test_staff_cannot_list_audit_logs(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/audit-logs')
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_list_audit_logs(): void
    {
        $this->getJson('/api/audit-logs')->assertStatus(401);
    }

    public function test_audit_logs_support_action_filter(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/audit-logs?action=LOGIN')
            ->assertOk();
    }

    public function test_reviewer_only_sees_audit_logs_from_own_site(): void
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
            'identifier' => 'site2-audit-user',
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
            'role' => 'staff',
            'site_id' => 2,
            'department_id' => 2,
            'is_banned' => false,
            'failed_attempts' => 0,
        ]);

        $foreignLog = AuditLog::withoutGlobalScopes()->create([
            'user_id' => $siteTwoUser->id,
            'action' => 'LOGIN',
            'target_type' => User::class,
            'target_id' => $siteTwoUser->id,
            'payload' => ['identifier' => 'site2-audit-user'],
            'ip_address' => '127.0.0.2',
            'created_at' => now(),
        ]);

        $reviewerToken = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$reviewerToken)
            ->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonMissing(['id' => $foreignLog->id]);
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
