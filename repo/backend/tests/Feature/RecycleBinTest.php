<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RecycleBinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_admin_can_restore_soft_deleted_user(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'staff002')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/users/'.$user->id)
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/recycle-bin/user/'.$user->id.'/restore')
            ->assertOk();

        $listResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/recycle-bin?entity_type=user')
            ->assertOk();

        $items = $listResponse->json('data') ?? [];
        foreach ($items as $item) {
            $this->assertNotSame($user->id, (int) ($item['entity_id'] ?? 0));
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_admin_can_restore_soft_deleted_appointment(): void
    {
        $token = $this->token('admin001');
        $staff = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $client = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $appointment = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'recycle-test',
            'start_time' => now()->addDays(4),
            'end_time' => now()->addDays(4)->addMinutes(30),
            'status' => Appointment::STATUS_CANCELLED,
            'site_id' => 1,
            'department_id' => 1,
        ]);
        $appointment->delete();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/recycle-bin/appointment/'.$appointment->id.'/restore')
            ->assertOk();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'deleted_at' => null]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments')
            ->assertOk();

        $ids = array_map(static fn ($row) => (int) ($row['id'] ?? 0), $response->json('data.data') ?? []);
        $this->assertContains($appointment->id, $ids);
    }

    public function test_admin_can_force_delete_from_recycle_bin(): void
    {
        $token = $this->token('admin001');
        $user = User::withoutGlobalScopes()->where('identifier', 'reviewer001')->firstOrFail();

        $user->delete();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/recycle-bin/user/'.$user->id, ['force' => true])
            ->assertOk();

        $this->assertNull(User::withoutGlobalScopes()->withTrashed()->find($user->id));
    }

    public function test_staff_cannot_access_recycle_bin(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/recycle-bin')
            ->assertStatus(403);
    }

    public function test_bulk_restore_multiple_entities(): void
    {
        $token = $this->token('admin001');
        $userOne = User::withoutGlobalScopes()->where('identifier', 'staff002')->firstOrFail();
        $userTwo = User::withoutGlobalScopes()->where('identifier', 'reviewer001')->firstOrFail();

        $userOne->delete();
        $userTwo->delete();

        foreach ([$userOne->id, $userTwo->id] as $id) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/admin/recycle-bin/user/'.$id.'/restore')
                ->assertOk();
        }

        $usersResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users')
            ->assertOk();

        $ids = array_map(static fn ($row) => (int) ($row['id'] ?? 0), $usersResponse->json('data.data') ?? []);
        $this->assertContains($userOne->id, $ids);
        $this->assertContains($userTwo->id, $ids);
    }

    public function test_admin_can_bulk_restore_multiple_users(): void
    {
        $token = $this->token('admin001');
        $userOne = User::withoutGlobalScopes()->where('identifier', 'staff002')->first();
        $userTwo = User::withoutGlobalScopes()->where('identifier', 'client001')->first();

        $userOne?->delete();
        $userTwo?->delete();

        $items = [];
        if ($userOne) {
            $items[] = ['entity_type' => 'user', 'entity_id' => $userOne->id];
        }
        if ($userTwo) {
            $items[] = ['entity_type' => 'user', 'entity_id' => $userTwo->id];
        }

        if ($items === []) {
            $this->markTestSkipped('No users to bulk restore.');
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/recycle-bin/bulk-restore', ['items' => $items])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_non_admin_cannot_bulk_restore(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/recycle-bin/bulk-restore', [
                'items' => [
                    ['entity_type' => 'user', 'entity_id' => 1],
                ],
            ])
            ->assertStatus(403);
    }

    public function test_bulk_restore_with_empty_items_returns_422(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/recycle-bin/bulk-restore', ['items' => []])
            ->assertStatus(422);
    }

    public function test_admin_can_bulk_delete_from_recycle_bin(): void
    {
        $token = $this->token('admin001');
        $userOne = User::withoutGlobalScopes()->where('identifier', 'staff002')->firstOrFail();
        $userTwo = User::withoutGlobalScopes()->where('identifier', 'reviewer001')->firstOrFail();

        $userOne->delete();
        $userTwo->delete();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/recycle-bin/bulk', [
                'items' => [
                    ['entity_type' => 'user', 'entity_id' => $userOne->id],
                    ['entity_type' => 'user', 'entity_id' => $userTwo->id],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(User::withoutGlobalScopes()->withTrashed()->find($userOne->id));
        $this->assertNull(User::withoutGlobalScopes()->withTrashed()->find($userTwo->id));
    }

    public function test_staff_cannot_bulk_delete(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/recycle-bin/bulk', [
                'items' => [
                    ['entity_type' => 'user', 'entity_id' => 1],
                ],
            ])
            ->assertStatus(403);
    }

    public function test_bulk_delete_empty_items_returns_422(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/recycle-bin/bulk', ['items' => []])
            ->assertStatus(422);
    }

    private function token(string $identifier): string
    {
        User::withoutGlobalScopes()->where('identifier', $identifier)->update([
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
            'is_banned' => false,
            'muted_until' => null,
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
