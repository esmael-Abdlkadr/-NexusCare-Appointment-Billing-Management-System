<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\Resource;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies that appointment creation/update enforces site-scoped referential integrity.
 * A staff user from Site 1 must not be able to use client/provider/resource IDs
 * that belong to Site 2 — these should return 422.
 */
class AppointmentCrossSiteTest extends TestCase
{
    use RefreshDatabase;

    private int $site2Id;
    private User $site2Client;
    private User $site2Provider;
    private Resource $site2Resource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        // Create a second site with its own user and resource
        $site2 = Site::withoutGlobalScopes()->firstOrCreate(
            ['id' => 2],
            ['organization_id' => 1, 'name' => 'Site 2']
        );
        $this->site2Id = (int) $site2->id;

        $dept2 = Department::withoutGlobalScopes()->firstOrCreate(
            ['id' => 2],
            ['site_id' => $this->site2Id, 'name' => 'Dept 2']
        );

        $this->site2Client = User::withoutGlobalScopes()->create([
            'identifier'     => 'site2_client_' . Str::lower(Str::random(6)),
            'password_hash'  => Hash::make('Admin@12345678', ['rounds' => 12]),
            'role'           => 'staff',
            'site_id'        => $this->site2Id,
            'department_id'  => $dept2->id,
            'is_banned'      => false,
            'failed_attempts' => 0,
        ]);

        $this->site2Provider = User::withoutGlobalScopes()->create([
            'identifier'     => 'site2_prov_' . Str::lower(Str::random(6)),
            'password_hash'  => Hash::make('Admin@12345678', ['rounds' => 12]),
            'role'           => 'staff',
            'site_id'        => $this->site2Id,
            'department_id'  => $dept2->id,
            'is_banned'      => false,
            'failed_attempts' => 0,
        ]);

        $this->site2Resource = Resource::withoutGlobalScopes()->create([
            'name'      => 'Site2 Room',
            'type'      => 'room',
            'site_id'   => $this->site2Id,
            'is_active' => true,
        ]);
    }

    public function test_create_appointment_with_cross_site_client_is_rejected(): void
    {
        $token = $this->loginAs('staff001');
        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Client-Type' => 'api'])
            ->postJson('/api/appointments', [
                'client_id'     => $this->site2Client->id,  // Site 2 client
                'provider_id'   => $provider->id,
                'resource_id'   => 1,
                'service_type'  => 'cross-site-test',
                'start_time'    => now()->addDay()->toDateTimeString(),
                'end_time'      => now()->addDay()->addHour()->toDateTimeString(),
                'department_id' => 1,
            ])->assertStatus(422);
    }

    public function test_create_appointment_with_cross_site_provider_is_rejected(): void
    {
        $token = $this->loginAs('staff001');
        $client = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Client-Type' => 'api'])
            ->postJson('/api/appointments', [
                'client_id'     => $client->id,
                'provider_id'   => $this->site2Provider->id,  // Site 2 provider
                'resource_id'   => 1,
                'service_type'  => 'cross-site-test',
                'start_time'    => now()->addDay()->toDateTimeString(),
                'end_time'      => now()->addDay()->addHour()->toDateTimeString(),
                'department_id' => 1,
            ])->assertStatus(422);
    }

    public function test_create_appointment_with_cross_site_resource_is_rejected(): void
    {
        $token = $this->loginAs('staff001');
        $client   = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();
        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Client-Type' => 'api'])
            ->postJson('/api/appointments', [
                'client_id'     => $client->id,
                'provider_id'   => $provider->id,
                'resource_id'   => $this->site2Resource->id,  // Site 2 resource
                'service_type'  => 'cross-site-test',
                'start_time'    => now()->addDay()->toDateTimeString(),
                'end_time'      => now()->addDay()->addHour()->toDateTimeString(),
                'department_id' => 1,
            ])->assertStatus(422);
    }

    public function test_create_appointment_with_same_site_refs_succeeds(): void
    {
        $token    = $this->loginAs('staff001');
        $client   = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();
        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Client-Type' => 'api'])
            ->postJson('/api/appointments', [
                'client_id'     => $client->id,
                'provider_id'   => $provider->id,
                'resource_id'   => 1,
                'service_type'  => 'cross-site-test',
                'start_time'    => now()->addDays(3)->toDateTimeString(),
                'end_time'      => now()->addDays(3)->addHour()->toDateTimeString(),
                'department_id' => 1,
            ])->assertCreated();
    }

    public function test_reschedule_appointment_with_cross_site_provider_is_rejected(): void
    {
        $client   = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();
        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        $appointment = Appointment::withoutGlobalScopes()->create([
            'client_id'        => $client->id,
            'provider_id'      => $provider->id,
            'resource_id'      => 1,
            'service_type'     => 'reschedule-cross-site',
            'start_time'       => now()->addDays(5),
            'end_time'         => now()->addDays(5)->addHour(),
            'status'           => Appointment::STATUS_CONFIRMED,
            'site_id'          => 1,
            'department_id'    => 1,
            'assessed_no_show' => false,
        ]);

        $token = $this->loginAs('staff001');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Client-Type' => 'api'])
            ->patchJson("/api/appointments/{$appointment->id}", [
                'start_time'  => now()->addDays(6)->toDateTimeString(),
                'end_time'    => now()->addDays(6)->addHour()->toDateTimeString(),
                'provider_id' => $this->site2Provider->id,  // Site 2 provider
            ])->assertStatus(422);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function loginAs(string $identifier): string
    {
        User::withoutGlobalScopes()->where('identifier', $identifier)->update([
            'password_hash'   => Hash::make('Admin@12345678', ['rounds' => 12]),
            'is_banned'       => false,
            'muted_until'     => null,
            'locked_until'    => null,
            'failed_attempts' => 0,
        ]);

        $response = $this->withHeader('X-Client-Type', 'api')
            ->postJson('/api/auth/login', [
                'identifier' => $identifier,
                'password'   => 'Admin@12345678',
            ])->assertOk();

        return (string) $response->json('data.access_token');
    }
}
