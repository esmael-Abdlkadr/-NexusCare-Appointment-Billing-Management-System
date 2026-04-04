<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use App\Models\Site;
use App\Models\Department;
use Carbon\Carbon;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_admin_can_list_users(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data']]);
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $token = $this->token('admin001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users?role=staff')
            ->assertOk();

        foreach ($response->json('data.data', []) as $row) {
            $this->assertSame('staff', $row['role']);
        }
    }

    public function test_admin_can_filter_users_by_identifier(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users?identifier=staff001')
            ->assertOk()
            ->assertJsonFragment(['identifier' => 'staff001']);
    }

    public function test_admin_can_filter_users_by_banned_and_muted_status(): void
    {
        $token = $this->token('admin001');

        $banned = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'filter-banned-user'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
                'is_banned' => true,
                'muted_until' => null,
                'failed_attempts' => 0,
            ]
        );

        $muted = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'filter-muted-user'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
                'is_banned' => false,
                'muted_until' => now()->addHour(),
                'failed_attempts' => 0,
            ]
        );

        $clean = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'filter-clean-user'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
                'is_banned' => false,
                'muted_until' => null,
                'failed_attempts' => 0,
            ]
        );

        $bannedIds = collect(
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/admin/users?is_banned=1')
                ->assertOk()
                ->json('data.data', [])
        )->pluck('id')->all();

        $this->assertContains($banned->id, $bannedIds);
        $this->assertNotContains($muted->id, $bannedIds);
        $this->assertNotContains($clean->id, $bannedIds);

        $mutedIds = collect(
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/admin/users?is_muted=1')
                ->assertOk()
                ->json('data.data', [])
        )->pluck('id')->all();

        $this->assertContains($muted->id, $mutedIds);
        $this->assertNotContains($banned->id, $mutedIds);
        $this->assertNotContains($clean->id, $mutedIds);

        $allIds = collect(
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/admin/users')
                ->assertOk()
                ->json('data.data', [])
        )->pluck('id')->all();

        $this->assertContains($banned->id, $allIds);
        $this->assertContains($muted->id, $allIds);
        $this->assertContains($clean->id, $allIds);
    }

    public function test_reviewer_cannot_list_users(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }

    public function test_staff_cannot_list_users(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_list_users(): void
    {
        $this->getJson('/api/admin/users')->assertStatus(401);
    }

    public function test_admin_can_create_user(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users', [
                'identifier' => 'newuser01',
                'email' => 'new@test.com',
                'password' => 'Temp@NexusCare12',
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
            ])->assertStatus(201);

        $this->assertDatabaseHas('users', ['identifier' => 'newuser01']);
    }

    public function test_create_user_duplicate_identifier_rejected(): void
    {
        $token = $this->token('admin001');

        $payload = [
            'identifier' => 'dup-user-01',
            'email' => 'dup@test.com',
            'password' => 'Temp@NexusCare12',
            'role' => 'staff',
            'site_id' => 1,
            'department_id' => 1,
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users', $payload)
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users', $payload)
            ->assertStatus(422);
    }

    public function test_create_user_weak_password_rejected(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users', [
                'identifier' => 'weak-user',
                'email' => 'weak@test.com',
                'password' => 'weak',
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.password.0', fn ($value) => is_string($value));
    }

    public function test_create_user_missing_required_fields(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users', [])
            ->assertStatus(422);
    }

    public function test_reviewer_cannot_create_user(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users', [
                'identifier' => 'nope-create',
                'email' => 'nope@test.com',
                'password' => 'Temp@NexusCare12',
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
            ])->assertStatus(403);
    }

    public function test_admin_cannot_create_user_in_another_site(): void
    {
        Site::withoutGlobalScopes()->updateOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Secondary Site',
        ]);

        Department::withoutGlobalScopes()->updateOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Secondary Department',
        ]);

        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users', [
                'identifier' => 'cross-site-create-user',
                'email' => 'cross-site-create@test.com',
                'password' => 'Temp@NexusCare12',
                'role' => 'staff',
                'site_id' => 2,
                'department_id' => 2,
            ])->assertStatus(403)
            ->assertJsonPath('error', 'FORBIDDEN');

        $this->assertDatabaseMissing('users', ['identifier' => 'cross-site-create-user']);
    }

    public function test_admin_can_view_user(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.user.identifier', 'staff001');
    }

    public function test_reviewer_can_view_user(): void
    {
        $token = $this->token('reviewer001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users/'.$user->id)
            ->assertOk();
    }

    public function test_staff_cannot_view_user(): void
    {
        $token = $this->token('staff001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff002')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users/'.$user->id)
            ->assertStatus(403);
    }

    public function test_view_nonexistent_user_returns_404(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users/999999')
            ->assertStatus(404);
    }

    public function test_reviewer_cannot_view_user_from_another_site(): void
    {
        Site::withoutGlobalScopes()->updateOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Secondary Site',
        ]);

        Department::withoutGlobalScopes()->updateOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Secondary Department',
        ]);

        $target = User::withoutGlobalScopes()->create([
            'identifier' => 'site2-target-user',
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
            'role' => 'staff',
            'site_id' => 2,
            'department_id' => 2,
            'is_banned' => false,
            'failed_attempts' => 0,
        ]);

        $reviewerToken = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$reviewerToken)
            ->getJson('/api/admin/users/'.$target->id)
            ->assertStatus(403)
            ->assertJsonPath('error', 'FORBIDDEN');
    }

    public function test_admin_can_update_user_role(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/admin/users/'.$user->id, ['role' => 'reviewer'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'reviewer']);
    }

    public function test_admin_can_update_user_ban(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/admin/users/'.$user->id, ['is_banned' => true])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_banned' => 1]);
    }

    public function test_reviewer_cannot_update_user(): void
    {
        $token = $this->token('reviewer001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/admin/users/'.$user->id, ['is_banned' => true])
            ->assertStatus(403);
    }

    public function test_update_nonexistent_user_returns_404(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/admin/users/999999', ['is_banned' => true])
            ->assertStatus(404);
    }

    public function test_admin_can_soft_delete_user(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff002')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/users/'.$user->id)
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_reviewer_cannot_delete_user(): void
    {
        $token = $this->token('reviewer001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff002')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/users/'.$user->id)
            ->assertStatus(403);
    }

    public function test_delete_nonexistent_user_returns_404(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/users/999999')
            ->assertStatus(404);
    }

    public function test_bulk_ban(): void
    {
        $token = $this->token('admin001');
        $staffId = (int) User::withoutGlobalScopes()->where('identifier', 'staff001')->value('id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/bulk', ['user_ids' => [$staffId], 'action' => 'ban'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $staffId, 'is_banned' => 1]);
    }

    public function test_bulk_unban(): void
    {
        $token = $this->token('admin001');
        $staffId = (int) User::withoutGlobalScopes()->where('identifier', 'staff001')->value('id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/bulk', ['user_ids' => [$staffId], 'action' => 'ban'])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/bulk', ['user_ids' => [$staffId], 'action' => 'unban'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $staffId, 'is_banned' => 0]);
    }

    public function test_bulk_delete(): void
    {
        $token = $this->token('admin001');
        $staffId = (int) User::withoutGlobalScopes()->where('identifier', 'staff002')->value('id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/bulk', ['user_ids' => [$staffId], 'action' => 'delete'])
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $staffId]);
    }

    public function test_bulk_change_role(): void
    {
        $token = $this->token('admin001');
        $staffId = (int) User::withoutGlobalScopes()->where('identifier', 'staff001')->value('id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/bulk', [
                'user_ids' => [$staffId],
                'action' => 'change_role',
                'role' => 'reviewer',
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $staffId, 'role' => 'reviewer']);
    }

    public function test_bulk_mute(): void
    {
        $token = $this->token('admin001');
        $staffId = (int) User::withoutGlobalScopes()->where('identifier', 'staff001')->value('id');
        $until = now()->addHour()->toIso8601String();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/bulk', [
                'user_ids' => [$staffId],
                'action' => 'mute',
                'muted_until' => $until,
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $staffId]);
        $this->assertNotNull(User::withoutGlobalScopes()->find($staffId)?->muted_until);
    }

    public function test_bulk_mute_without_muted_until_defaults_to_24h(): void
    {
        $token = $this->token('admin001');
        $target = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Client-Type' => 'api'])
            ->postJson('/api/admin/users/bulk', [
                'user_ids' => [$target->id],
                'action' => 'mute',
            ])->assertOk()
            ->assertJsonPath('success', true);

        $target->refresh();
        $this->assertNotNull($target->muted_until);
        $this->assertGreaterThan(
            now()->addHours(23)->addMinutes(59)->timestamp,
            Carbon::parse($target->muted_until)->timestamp
        );
    }

    public function test_single_user_mute_shorter_than_24h_is_bumped_to_24h(): void
    {
        $token = $this->token('admin001');
        $target = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $shortMute = now()->addMinutes(30)->toDateTimeString();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Client-Type' => 'api'])
            ->patchJson('/api/admin/users/'.$target->id, [
                'muted_until' => $shortMute,
            ])->assertOk();

        $target->refresh();
        $this->assertGreaterThan(
            now()->addHours(23)->addMinutes(59)->timestamp,
            Carbon::parse($target->muted_until)->timestamp
        );
    }

    public function test_mute_audit_log_records_24h_duration(): void
    {
        $token = $this->token('admin001');
        $target = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Client-Type' => 'api'])
            ->postJson('/api/admin/users/bulk', [
                'user_ids' => [$target->id],
                'action' => 'mute',
            ])->assertOk();

        $log = \App\Models\AuditLog::query()
            ->where('action', 'MUTE_USER')
            ->where('target_id', $target->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($log);

        $payload = is_array($log->payload)
            ? $log->payload
            : json_decode((string) ($log->payload ?? '{}'), true);

        $this->assertEquals(24, $payload['duration_hours'] ?? null);
    }

    public function test_bulk_empty_user_ids_rejected(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/bulk', ['user_ids' => [], 'action' => 'ban'])
            ->assertStatus(422);
    }

    public function test_reviewer_cannot_bulk_action(): void
    {
        $token = $this->token('reviewer001');
        $staffId = (int) User::withoutGlobalScopes()->where('identifier', 'staff001')->value('id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/bulk', ['user_ids' => [$staffId], 'action' => 'ban'])
            ->assertStatus(403);
    }

    public function test_admin_can_unlock_user(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $user->locked_until = now()->addHour();
        $user->failed_attempts = 3;
        $user->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/'.$user->id.'/unlock')
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locked_until' => null,
            'failed_attempts' => 0,
        ]);
    }

    public function test_reviewer_cannot_unlock_user(): void
    {
        $token = $this->token('reviewer001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/'.$user->id.'/unlock')
            ->assertStatus(403);
    }

    public function test_admin_can_reset_password(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/'.$user->id.'/reset-password', [
                'new_password'        => 'Temp@NexusCare12',
                'verification_method' => 'in_person',
                'verified_attributes' => ['government_id', 'employee_id'],
                'verifier_role'       => 'administrator',
                'verification_result' => 'passed',
            ])->assertOk();

        $this->postJson('/api/auth/login', [
            'identifier' => 'staff001',
            'password' => 'Temp@NexusCare12',
        ])->assertOk();
    }

    public function test_reset_password_missing_verification_fields_rejected(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        // Missing all verification fields → 422
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/'.$user->id.'/reset-password', [
                'new_password' => 'Temp@NexusCare12',
            ])->assertStatus(422);
    }

    public function test_reset_password_invalid_verification_method_rejected(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        // Invalid verification_method → 422
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/'.$user->id.'/reset-password', [
                'new_password'        => 'Temp@NexusCare12',
                'verification_method' => 'telepathy',
                'verified_attributes' => ['government_id'],
                'verifier_role'       => 'administrator',
                'verification_result' => 'passed',
            ])->assertStatus(422);
    }

    public function test_reset_password_invalid_verification_result_rejected(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        // verification_result not 'passed' → 422
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/'.$user->id.'/reset-password', [
                'new_password'        => 'Temp@NexusCare12',
                'verification_method' => 'in_person',
                'verified_attributes' => ['government_id'],
                'verifier_role'       => 'administrator',
                'verification_result' => 'failed',
            ])->assertStatus(422);
    }

    public function test_reset_password_audit_contains_structured_metadata(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/'.$user->id.'/reset-password', [
                'new_password'        => 'Temp@NexusCare12',
                'verification_method' => 'document',
                'verified_attributes' => ['government_id', 'phone'],
                'verifier_role'       => 'administrator',
                'verification_result' => 'passed',
            ])->assertOk();

        $log = \App\Models\AuditLog::query()
            ->where('action', 'PASSWORD_RESET')
            ->where('target_id', $user->id)
            ->latest('created_at')
            ->firstOrFail();

        $payload = $log->payload;
        $this->assertSame('document', $payload['verification_method'] ?? null);
        $this->assertContains('government_id', $payload['verified_attributes'] ?? []);
        $this->assertSame('passed', $payload['verification_result'] ?? null);
    }

    public function test_reset_password_weak_password_rejected(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/'.$user->id.'/reset-password', [
                'new_password'        => 'weak',
                'verification_method' => 'in_person',
                'verified_attributes' => ['government_id'],
                'verifier_role'       => 'administrator',
                'verification_result' => 'passed',
            ])->assertStatus(422);
    }

    public function test_reviewer_cannot_reset_password(): void
    {
        $token = $this->token('reviewer001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/'.$user->id.'/reset-password', [
                'new_password'        => 'Temp@NexusCare12',
                'verification_method' => 'in_person',
                'verified_attributes' => ['government_id'],
                'verifier_role'       => 'administrator',
                'verification_result' => 'passed',
            ])->assertStatus(403);
    }

    public function test_admin_cannot_reset_password_for_user_in_another_site(): void
    {
        Site::withoutGlobalScopes()->updateOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Secondary Site',
        ]);

        Department::withoutGlobalScopes()->updateOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Secondary Department',
        ]);

        $target = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'site2-reset-target'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/users/'.$target->id.'/reset-password', [
                'new_password'        => 'Temp@NexusCare12',
                'verification_method' => 'in_person',
                'verified_attributes' => ['government_id'],
                'verifier_role'       => 'administrator',
                'verification_result' => 'passed',
            ])->assertStatus(404)
            ->assertJsonPath('error', 'NOT_FOUND');
    }

    public function test_admin_can_list_recycle_bin(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff002')->firstOrFail();
        $user->delete();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/recycle-bin?entity_type=user')
            ->assertOk();

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_recycle_bin_empty_when_nothing_deleted(): void
    {
        $token = $this->token('admin001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/recycle-bin?entity_type=user')
            ->assertOk();

        $this->assertCount(0, $response->json('data', []));
    }

    public function test_reviewer_cannot_access_recycle_bin(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/recycle-bin')
            ->assertStatus(403);
    }

    public function test_admin_can_force_delete_from_recycle_bin(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff002')->firstOrFail();
        $user->delete();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/recycle-bin/user/'.$user->id, ['force' => true])
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_reviewer_cannot_force_delete(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/recycle-bin/user/999', ['force' => true])
            ->assertStatus(403);
    }

    public function test_force_delete_nonexistent_returns_404(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/recycle-bin/user/999999', ['force' => true])
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
