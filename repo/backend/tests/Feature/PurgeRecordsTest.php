<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\User;
use App\Models\WaitlistEntry;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_purge_skips_payments_and_audit_logs(): void
    {
        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();
        $client = User::query()->where('identifier', 'client001')->firstOrFail();

        $appt = Appointment::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'provider_id' => $staff->id,
            'resource_id' => 1,
            'service_type' => 'purge-skip',
            'start_time' => now()->subYears(3),
            'end_time' => now()->subYears(3)->addMinutes(30),
            'status' => Appointment::STATUS_CANCELLED,
            'site_id' => 1,
            'department_id' => 1,
        ]);
        $appt->delete();

        $payment = Payment::withoutGlobalScopes()->create([
            'reference_id' => 'PURGE-SKIP-PAY',
            'amount' => 10,
            'method' => 'cash',
            'fee_assessment_id' => null,
            'posted_by' => $staff->id,
            'site_id' => 1,
            'notes' => 'skip',
            'created_at' => now(),
        ]);

        AuditLog::query()->create([
            'user_id' => $staff->id,
            'action' => 'KEEP_AUDIT',
            'target_type' => 'test',
            'target_id' => 1,
            'payload' => [],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $this->artisan('records:purge')->assertExitCode(0);

        $this->assertNotNull(Appointment::withoutGlobalScopes()->withTrashed()->find($appt->id));
        $this->assertNotNull(Payment::withoutGlobalScopes()->find($payment->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'KEEP_AUDIT']);
    }

    public function test_purge_only_removes_records_older_than_24_months(): void
    {
        $oldUser = User::withoutGlobalScopes()->where('identifier', 'staff002')->firstOrFail();
        $oldUser->delete();
        $oldUser->deleted_at = now()->subMonths(25);
        $oldUser->save();

        $newResource = Resource::withoutGlobalScopes()->firstOrCreate(['id' => 99], [
            'name' => 'To Keep',
            'type' => 'room',
            'site_id' => 1,
            'is_active' => true,
        ]);
        $newResource->delete();
        $newResource->deleted_at = now()->subMonths(2);
        $newResource->save();

        $wait = WaitlistEntry::withoutGlobalScopes()->create([
            'client_id' => User::query()->where('identifier', 'client001')->firstOrFail()->id,
            'service_type' => 'purge-old',
            'priority' => 10,
            'preferred_start' => now()->subYears(3),
            'preferred_end' => now()->subYears(3)->addHour(),
            'status' => 'waiting',
            'site_id' => 1,
        ]);
        $wait->delete();
        $wait->deleted_at = now()->subMonths(30);
        $wait->save();

        $oldAppointment = Appointment::withoutGlobalScopes()->create([
            'client_id' => User::query()->where('identifier', 'client001')->firstOrFail()->id,
            'provider_id' => User::query()->where('identifier', 'staff001')->firstOrFail()->id,
            'resource_id' => 1,
            'service_type' => 'purge-old-appointment',
            'start_time' => now()->subYears(3),
            'end_time' => now()->subYears(3)->addMinutes(30),
            'status' => Appointment::STATUS_CANCELLED,
            'site_id' => 1,
            'department_id' => 1,
        ]);
        $oldAppointment->delete();
        $oldAppointment->deleted_at = now()->subMonths(30);
        $oldAppointment->save();

        $this->artisan('records:purge')->assertExitCode(0);

        $this->assertNull(User::withoutGlobalScopes()->withTrashed()->find($oldUser->id));
        $this->assertNull(Appointment::withoutGlobalScopes()->withTrashed()->find($oldAppointment->id));
        $this->assertNotNull(Resource::withoutGlobalScopes()->withTrashed()->find($newResource->id));
        $this->assertNull(WaitlistEntry::withoutGlobalScopes()->withTrashed()->find($wait->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'PURGE_RECORDS']);
    }
}
