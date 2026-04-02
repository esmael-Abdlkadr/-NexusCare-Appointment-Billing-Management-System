<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\FeeRule;
use App\Models\User;
use App\Repositories\AppointmentBillingRepository;
use App\Services\FeeService;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_overdue_simple_interest(): void
    {
        $service = app(FeeService::class);
        $this->assertSame(1.50, $service->calculateOverdueFine(100, 30));
        $this->assertSame(3.00, $service->calculateOverdueFine(100, 60));
        $this->assertSame(4.50, $service->calculateOverdueFine(100, 90));
    }

    public function test_no_show_grace_period_boundary(): void
    {
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        $atBoundary = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'boundary',
            'start_time' => now()->subMinutes(10),
            'end_time' => now()->addMinutes(20),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
            'assessed_no_show' => false,
        ]);

        $afterBoundary = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'after-boundary',
            'start_time' => now()->subMinutes(10)->subSecond(),
            'end_time' => now()->addMinutes(20),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
            'assessed_no_show' => false,
        ]);

        $rule = FeeRule::withoutGlobalScopes()->where('site_id', 1)->where('fee_type', 'no_show')->firstOrFail();
        $grace = (int) $rule->grace_minutes;

        $repo = app(AppointmentBillingRepository::class);
        $due = $repo->dueForNoShowAssessment($grace);
        $ids = $due->pluck('id')->all();

        $this->assertNotContains($atBoundary->id, $ids);
        $this->assertContains($afterBoundary->id, $ids);
    }
}
