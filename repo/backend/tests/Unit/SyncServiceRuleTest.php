<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\User;
use App\Services\SyncService;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncServiceRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_confirmed_status_wins_over_non_confirmed(): void
    {
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        $a = new Appointment([
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'start_time' => now(),
            'end_time' => now()->addMinutes(30),
        ]);
        $a->id = 100;
        $a->updated_at = now()->subHours(2);

        $b = new Appointment([
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'status' => Appointment::STATUS_CANCELLED,
            'site_id' => 2,
            'start_time' => now(),
            'end_time' => now()->addMinutes(30),
        ]);
        $b->id = 100;
        $b->updated_at = now();

        $service = app(SyncService::class);
        [$winner, $loser, $rule] = $service->resolveConflictForTest($a, $b);

        $this->assertSame(Appointment::STATUS_CONFIRMED, $winner->status);
        $this->assertSame('confirmed_wins', $rule);
        $this->assertSame(2, $loser->site_id);
    }

    public function test_when_both_confirmed_latest_updated_at_wins(): void
    {
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        $a = new Appointment([
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'start_time' => now(),
            'end_time' => now()->addMinutes(30),
        ]);
        $a->id = 101;
        $a->updated_at = now()->subMinute();

        $b = new Appointment([
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 2,
            'start_time' => now(),
            'end_time' => now()->addMinutes(30),
        ]);
        $b->id = 101;
        $b->updated_at = now();

        $service = app(SyncService::class);
        [$winner, , $rule] = $service->resolveConflictForTest($a, $b);

        $this->assertSame(2, $winner->site_id);
        $this->assertSame('latest_updated_at', $rule);
    }
}
