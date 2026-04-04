<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\Resource;
use App\Models\Site;
use App\Models\User;
use App\Models\WaitlistEntry;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_add_to_waitlist(): void
    {
        $token = $this->token('staff001');
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/waitlist', [
                'client_id' => $client->id,
                'service_type' => 'consultation',
                'priority' => 10,
                'preferred_start' => now()->addDay()->setHour(9)->toIso8601String(),
                'preferred_end' => now()->addDay()->setHour(18)->toIso8601String(),
            ])
            ->assertStatus(201);
    }

    public function test_backfill_proposed_on_cancellation(): void
    {
        $token = $this->token('staff001');
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        $entry = WaitlistEntry::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'service_type' => 'consultation',
            'priority' => 1,
            'preferred_start' => now()->addDay()->setHour(8),
            'preferred_end' => now()->addDay()->setHour(20),
            'status' => 'waiting',
            'site_id' => 1,
        ]);

        $appointment = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'consultation',
            'start_time' => now()->addDay()->setHour(10),
            'end_time' => now()->addDay()->setHour(10)->addMinutes(30),
            'status' => Appointment::STATUS_REQUESTED,
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/appointments/'.$appointment->id.'/status', ['status' => 'confirmed'])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/appointments/'.$appointment->id.'/status', [
                'status' => 'cancelled',
                'reason' => 'Client cancelled',
            ])
            ->assertOk();

        $this->assertDatabaseHas('waitlist', ['id' => $entry->id, 'status' => 'proposed']);
    }

    public function test_confirm_backfill_creates_appointment(): void
    {
        $token = $this->token('staff001');
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        $entry = WaitlistEntry::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'service_type' => 'consultation',
            'priority' => 1,
            'preferred_start' => now()->addDay()->setHour(8),
            'preferred_end' => now()->addDay()->setHour(20),
            'status' => 'proposed',
            'site_id' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/waitlist/'.$entry->id.'/confirm-backfill', [
                'provider_id' => $staff->id,
                'resource_id' => 1,
                'department_id' => 1,
                'start_time' => now()->addDay()->setHour(11)->toIso8601String(),
                'end_time' => now()->addDay()->setHour(11)->addMinutes(30)->toIso8601String(),
            ])
            ->assertOk();

        $newApptId = $response->json('data.appointment.id');
        $this->assertNotNull($newApptId);
        $this->assertDatabaseHas('appointments', ['id' => $newApptId]);
        $this->assertDatabaseHas('waitlist', ['id' => $entry->id, 'status' => 'booked']);
    }

    public function test_waitlist_index_returns_paginated_envelope(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/waitlist')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    }

    public function test_waitlist_index_respects_per_page_param(): void
    {
        WaitlistEntry::withoutGlobalScopes()->delete();

        $client = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        foreach (range(1, 5) as $i) {
            WaitlistEntry::withoutGlobalScopes()->create([
                'client_id' => $client->id,
                'service_type' => 'per-page-'.$i,
                'priority' => $i,
                'preferred_start' => now()->addDays($i),
                'preferred_end' => now()->addDays($i)->addHour(),
                'status' => 'waiting',
                'site_id' => 1,
                'department_id' => 1,
            ]);
        }

        $token = $this->token('staff001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/waitlist?per_page=2&page=1')
            ->assertOk();

        $this->assertCount(2, $response->json('data.data'));
        $this->assertSame(5, (int) $response->json('data.total'));
    }

    public function test_confirm_backfill_second_attempt_is_rejected(): void
    {
        $token = $this->token('staff001');
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        $entry = WaitlistEntry::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'service_type' => 'idempotency-check',
            'priority' => 1,
            'preferred_start' => now()->addDay()->setHour(8),
            'preferred_end' => now()->addDay()->setHour(20),
            'status' => 'proposed',
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $payload = [
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'department_id' => 1,
            'start_time' => now()->addDay()->setHour(11)->toIso8601String(),
            'end_time' => now()->addDay()->setHour(11)->addMinutes(30)->toIso8601String(),
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/waitlist/'.$entry->id.'/confirm-backfill', $payload)
            ->assertOk();

        $this->assertDatabaseHas('waitlist', ['id' => $entry->id, 'status' => 'booked']);

        $status = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/waitlist/'.$entry->id.'/confirm-backfill', $payload)
            ->status();

        $this->assertContains($status, [409, 422]);
    }

    public function test_confirm_backfill_with_cross_site_provider_is_rejected(): void
    {
        $site1Staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        // Create site 2 with a provider and resource
        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);
        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        $site2Provider = User::withoutGlobalScopes()->create([
            'identifier' => 'site2_provider_backfill_test',
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
            'role' => 'staff',
            'site_id' => 2,
            'department_id' => 2,
            'is_banned' => false,
            'failed_attempts' => 0,
        ]);

        // Waitlist entry belongs to site 1
        $entry = WaitlistEntry::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'service_type' => 'cross-site-backfill',
            'priority' => 1,
            'preferred_start' => now()->addDay()->setHour(8),
            'preferred_end' => now()->addDay()->setHour(20),
            'status' => 'proposed',
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $token = $this->token('staff001');

        // Attempt to confirm backfill using site2 provider → must be rejected
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/waitlist/'.$entry->id.'/confirm-backfill', [
                'provider_id' => $site2Provider->id,
                'resource_id' => 1,
                'department_id' => 1,
                'start_time' => now()->addDay()->setHour(11)->toIso8601String(),
                'end_time' => now()->addDay()->setHour(11)->addMinutes(30)->toIso8601String(),
            ])
            ->assertStatus(422);
    }

    public function test_confirm_backfill_with_cross_site_resource_is_rejected(): void
    {
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);
        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        $site2Resource = Resource::withoutGlobalScopes()->create([
            'name' => 'Site2 Room',
            'type' => 'room',
            'site_id' => 2,
            'is_active' => true,
        ]);

        $entry = WaitlistEntry::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'service_type' => 'cross-site-resource-backfill',
            'priority' => 1,
            'preferred_start' => now()->addDay()->setHour(8),
            'preferred_end' => now()->addDay()->setHour(20),
            'status' => 'proposed',
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        $token = $this->token('staff001');

        // Attempt to confirm backfill using site2 resource → must be rejected
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/waitlist/'.$entry->id.'/confirm-backfill', [
                'provider_id' => $staff->id,
                'resource_id' => $site2Resource->id,
                'department_id' => 1,
                'start_time' => now()->addDay()->setHour(11)->toIso8601String(),
                'end_time' => now()->addDay()->setHour(11)->addMinutes(30)->toIso8601String(),
            ])
            ->assertStatus(422);
    }

    public function test_cross_site_waitlist_entry_is_rejected(): void
    {
        $token = $this->token('staff001');
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        // Create a client in site 2 (staff001 is in site 1)
        $site2Client = User::query()->create([
            'identifier' => 'site2_client_waitlist_test',
            'email' => 'site2_waitlist@test.com',
            'password_hash' => Hash::make('Test@12345678', ['rounds' => 12]),
            'role' => 'staff',
            'site_id' => 2,
            'department_id' => 2,
        ]);

        // Site 1 staff should NOT be able to create waitlist entry for site 2 client
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/waitlist', [
                'client_id' => $site2Client->id,
                'service_type' => 'cross-site-attempt',
                'priority' => 10,
                'preferred_start' => now()->addDay()->setHour(9)->toIso8601String(),
                'preferred_end' => now()->addDay()->setHour(18)->toIso8601String(),
            ]);

        // Should be rejected with 422 validation error (client_id doesn't exist in site 1)
        $response->assertStatus(422);
        $this->assertStringContainsString('client_id', json_encode($response->json()));
    }

    private function token(string $identifier): string
    {
        User::query()->where('identifier', $identifier)->update([
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
