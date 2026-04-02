<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentVersion;
use App\Models\Site;
use App\Models\SyncIndex;
use App\Models\User;
use App\Services\SyncService;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private SyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
        $this->syncService = app(SyncService::class);
    }

    public function test_run_sync_creates_sync_index_for_new_appointment(): void
    {
        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $client   = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $appt = Appointment::withoutGlobalScopes()->create([
            'client_id'      => $client->id,
            'provider_id'    => $provider->id,
            'resource_id'    => 1,
            'service_type'   => 'sync-test',
            'start_time'     => now()->addDays(3),
            'end_time'       => now()->addDays(3)->addHour(),
            'status'         => Appointment::STATUS_REQUESTED,
            'site_id'        => 1,
            'department_id'  => 1,
            'assessed_no_show' => false,
        ]);

        $this->syncService->runSync(1);

        $this->assertDatabaseHas('sync_index', [
            'site_id'     => 1,
            'entity_type' => 'appointment',
            'entity_id'   => $appt->id,
        ]);
    }

    public function test_run_sync_updates_fingerprint_when_appointment_changes(): void
    {
        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $client   = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $appt = Appointment::withoutGlobalScopes()->create([
            'client_id'      => $client->id,
            'provider_id'    => $provider->id,
            'resource_id'    => 1,
            'service_type'   => 'sync-fingerprint',
            'start_time'     => now()->addDays(4),
            'end_time'       => now()->addDays(4)->addHour(),
            'status'         => Appointment::STATUS_REQUESTED,
            'site_id'        => 1,
            'department_id'  => 1,
            'assessed_no_show' => false,
        ]);

        // First sync — establishes fingerprint
        $this->syncService->runSync(1);

        $firstFingerprint = SyncIndex::query()
            ->where('entity_id', $appt->id)
            ->where('site_id', 1)
            ->value('fingerprint');

        // Travel 1 minute into the future so the mutated appointment's
        // updated_at is strictly after the first sync's synced_at.
        $this->travel(1)->minutes();

        $appt->status = Appointment::STATUS_CONFIRMED;
        $appt->save();

        // Second sync — fingerprint should update
        $this->syncService->runSync(1);

        $secondFingerprint = SyncIndex::query()
            ->where('entity_id', $appt->id)
            ->where('site_id', 1)
            ->value('fingerprint');

        $this->assertNotNull($firstFingerprint);
        $this->assertNotNull($secondFingerprint);
        $this->assertNotEquals($firstFingerprint, $secondFingerprint);
    }

    public function test_run_sync_does_not_create_duplicate_sync_index_entries(): void
    {
        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $client   = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $appt = Appointment::withoutGlobalScopes()->create([
            'client_id'      => $client->id,
            'provider_id'    => $provider->id,
            'resource_id'    => 1,
            'service_type'   => 'sync-dedup',
            'start_time'     => now()->addDays(5),
            'end_time'       => now()->addDays(5)->addHour(),
            'status'         => Appointment::STATUS_REQUESTED,
            'site_id'        => 1,
            'department_id'  => 1,
            'assessed_no_show' => false,
        ]);

        $this->syncService->runSync(1);
        $this->syncService->runSync(1);

        $count = SyncIndex::query()
            ->where('site_id', 1)
            ->where('entity_type', 'appointment')
            ->where('entity_id', $appt->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_run_all_sites_processes_all_sites(): void
    {
        // Create a second site
        $site2 = Site::withoutGlobalScopes()->create(['organization_id' => 1, 'name' => 'Site Two']);

        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $client   = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $appt1 = Appointment::withoutGlobalScopes()->create([
            'client_id'      => $client->id,
            'provider_id'    => $provider->id,
            'resource_id'    => 1,
            'service_type'   => 'sync-all-1',
            'start_time'     => now()->addDays(6),
            'end_time'       => now()->addDays(6)->addHour(),
            'status'         => Appointment::STATUS_REQUESTED,
            'site_id'        => 1,
            'department_id'  => 1,
            'assessed_no_show' => false,
        ]);

        $appt2 = Appointment::withoutGlobalScopes()->create([
            'client_id'      => $client->id,
            'provider_id'    => $provider->id,
            'resource_id'    => 1,
            'service_type'   => 'sync-all-2',
            'start_time'     => now()->addDays(7),
            'end_time'       => now()->addDays(7)->addHour(),
            'status'         => Appointment::STATUS_REQUESTED,
            'site_id'        => $site2->id,
            'department_id'  => 1,
            'assessed_no_show' => false,
        ]);

        $this->syncService->runAllSites();

        $this->assertDatabaseHas('sync_index', ['site_id' => 1,          'entity_id' => $appt1->id]);
        $this->assertDatabaseHas('sync_index', ['site_id' => $site2->id, 'entity_id' => $appt2->id]);
    }

    public function test_resolve_conflict_prefers_confirmed_status(): void
    {
        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $client   = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $confirmed = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id, 'provider_id' => $provider->id,
            'resource_id' => 1, 'service_type' => 'conflict-a',
            'start_time' => now()->addDays(8), 'end_time' => now()->addDays(8)->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1, 'department_id' => 1, 'assessed_no_show' => false,
        ]);

        $pending = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id, 'provider_id' => $provider->id,
            'resource_id' => 1, 'service_type' => 'conflict-b',
            'start_time' => now()->addDays(9), 'end_time' => now()->addDays(9)->addHour(),
            'status' => Appointment::STATUS_REQUESTED,
            'site_id' => 1, 'department_id' => 1, 'assessed_no_show' => false,
        ]);

        [$winner, $loser, $rule] = $this->syncService->resolveConflictForTest($pending, $confirmed);

        $this->assertEquals(Appointment::STATUS_CONFIRMED, $winner->status);
        $this->assertEquals('confirmed_wins', $rule);
    }

    public function test_resolve_conflict_latest_updated_at_wins_when_both_confirmed(): void
    {
        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $client   = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $older = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id, 'provider_id' => $provider->id,
            'resource_id' => 1, 'service_type' => 'older-confirmed',
            'start_time' => now()->addDays(10), 'end_time' => now()->addDays(10)->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1, 'department_id' => 1, 'assessed_no_show' => false,
        ]);
        $older->updated_at = now()->subHour();
        $older->saveQuietly();

        $newer = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id, 'provider_id' => $provider->id,
            'resource_id' => 1, 'service_type' => 'newer-confirmed',
            'start_time' => now()->addDays(11), 'end_time' => now()->addDays(11)->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1, 'department_id' => 1, 'assessed_no_show' => false,
        ]);

        [$winner, , $rule] = $this->syncService->resolveConflictForTest($newer, $older);

        $this->assertEquals($newer->id, $winner->id);
        $this->assertEquals('latest_updated_at', $rule);
    }
}
