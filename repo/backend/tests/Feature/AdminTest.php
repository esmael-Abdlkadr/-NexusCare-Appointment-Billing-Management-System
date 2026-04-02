<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_admin_can_ban_user(): void
    {
        $adminToken = $this->token('admin001');
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->patchJson('/api/admin/users/'.$staff->id, ['is_banned' => true])
            ->assertOk();

        $this->postJson('/api/auth/login', [
            'identifier' => 'staff001',
            'password' => 'Admin@12345678',
        ])->assertStatus(403)->assertJson(['error' => 'ACCOUNT_BANNED']);
    }

    public function test_admin_can_mute_user(): void
    {
        $adminToken = $this->token('admin001');
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->patchJson('/api/admin/users/'.$staff->id, ['muted_until' => now()->addHour()->toIso8601String()])
            ->assertOk();

        $staffToken = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->postJson('/api/payments', [
                'reference_id' => 'MUTE-BLOCK',
                'amount' => 2,
                'method' => 'cash',
            ])
            ->assertStatus(403)
            ->assertJson(['error' => 'ACCOUNT_MUTED']);
    }

    public function test_admin_restore_from_recycle_bin(): void
    {
        $token = $this->token('admin001');
        $staff = User::query()->where('identifier', 'staff002')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/users/'.$staff->id)
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $staff->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/recycle-bin/user/'.$staff->id.'/restore')
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'deleted_at' => null]);
    }

    public function test_government_id_masked_for_reviewer(): void
    {
        $adminToken = $this->token('admin001');
        $reviewerToken = $this->token('reviewer001');

        $created = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/admin/users', [
                'identifier' => 'masked_user',
                'password' => 'Admin@12345678',
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
                'government_id' => 'ABC1234',
                'phone' => '(555) 123-5678',
                'email' => 'masked@example.test',
            ])
            ->assertStatus(201);

        $id = (int) $created->json('data.user.id');

        $response = $this->withHeader('Authorization', 'Bearer '.$reviewerToken)
            ->getJson('/api/admin/users/'.$id)
            ->assertOk();

        $this->assertStringStartsWith('****', (string) $response->json('data.user.government_id'));
    }

    public function test_government_id_unmasked_for_admin(): void
    {
        $adminToken = $this->token('admin001');

        $response = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/admin/users', [
                'identifier' => 'unmasked_admin_view',
                'password' => 'Admin@12345678',
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
                'government_id' => 'ABC1234',
                'phone' => '(555) 123-5678',
                'email' => 'admin.view@example.test',
            ])
            ->assertStatus(201);

        $id = (int) $response->json('data.user.id');

        $list = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson('/api/admin/users?identifier=unmasked_admin_view')
            ->assertOk();

        $value = data_get($list->json(), 'data.data.0.government_id');
        $this->assertSame('ABC1234', $value);
        $this->assertNotSame('****1234', $value);
    }

    public function test_audit_log_immutable(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/audit-logs/1', ['action' => 'x'])
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/audit-logs/1')
            ->assertStatus(404);
    }

    private function token(string $identifier): string
    {
        User::withoutGlobalScopes()->where('identifier', $identifier)->update([
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
