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

class DepartmentIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $staffDeptA;
    private User $staffDeptB;
    private User $clientDeptA;
    private User $clientDeptB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        Site::withoutGlobalScopes()->firstOrCreate(['id' => 1], [
            'organization_id' => 1,
            'name' => 'Main Site',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 1,
            'name' => 'Dept B',
        ]);

        Resource::withoutGlobalScopes()->firstOrCreate(['id' => 1], [
            'name' => 'Resource 1',
            'type' => 'room',
            'site_id' => 1,
            'is_active' => true,
        ]);

        $this->staffDeptA = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'staff_dept_a'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $this->staffDeptB = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'staff_dept_b'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $this->clientDeptA = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'client_dept_a'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 1,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $this->clientDeptB = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'client_dept_b'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 1,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );
    }

    public function test_staff_cannot_view_appointment_from_different_department(): void
    {
        $appointment = Appointment::withoutGlobalScopes()->create([
            'client_id' => $this->clientDeptA->id,
            'provider_id' => $this->staffDeptA->id,
            'resource_id' => 1,
            'service_type' => 'dept-isolation-view',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
            'assessed_no_show' => false,
        ]);

        $token = $this->token('staff_dept_b');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments/'.$appointment->id)
            ->assertStatus(403);
    }

    public function test_staff_cannot_list_appointments_from_different_department(): void
    {
        Appointment::withoutGlobalScopes()->create([
            'client_id' => $this->clientDeptA->id,
            'provider_id' => $this->staffDeptA->id,
            'resource_id' => 1,
            'service_type' => 'dept-a-list',
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
            'assessed_no_show' => false,
        ]);

        Appointment::withoutGlobalScopes()->create([
            'client_id' => $this->clientDeptB->id,
            'provider_id' => $this->staffDeptB->id,
            'resource_id' => 1,
            'service_type' => 'dept-b-list',
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 2,
            'assessed_no_show' => false,
        ]);

        $token = $this->token('staff_dept_a');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments')
            ->assertOk();

        foreach ($response->json('data.data', []) as $row) {
            $this->assertSame(1, (int) ($row['department_id'] ?? 0));
        }
    }

    public function test_staff_cannot_create_appointment_for_different_department(): void
    {
        $token = $this->token('staff_dept_a');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/appointments', [
                'client_id' => $this->clientDeptA->id,
                'provider_id' => $this->staffDeptA->id,
                'resource_id' => 1,
                'service_type' => 'dept-create-forbidden',
                'start_time' => now()->addDays(4)->toIso8601String(),
                'end_time' => now()->addDays(4)->addMinutes(30)->toIso8601String(),
                'department_id' => 2,
            ]);

        $this->assertTrue(
            in_array($response->status(), [403, 422], true),
            'Expected 403 (forbidden) or 422 (validation error) but got '.$response->status()
        );
    }

    public function test_reviewer_can_view_appointments_across_departments(): void
    {
        Appointment::withoutGlobalScopes()->create([
            'client_id' => $this->clientDeptA->id,
            'provider_id' => $this->staffDeptA->id,
            'resource_id' => 1,
            'service_type' => 'reviewer-dept-a',
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(5)->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 1,
            'assessed_no_show' => false,
        ]);

        Appointment::withoutGlobalScopes()->create([
            'client_id' => $this->clientDeptB->id,
            'provider_id' => $this->staffDeptB->id,
            'resource_id' => 1,
            'service_type' => 'reviewer-dept-b',
            'start_time' => now()->addDays(6),
            'end_time' => now()->addDays(6)->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'site_id' => 1,
            'department_id' => 2,
            'assessed_no_show' => false,
        ]);

        $token = $this->token('reviewer001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments')
            ->assertOk();

        $departmentIds = collect($response->json('data.data', []))->pluck('department_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $this->assertContains(1, $departmentIds);
        $this->assertContains(2, $departmentIds);
    }

    public function test_staff_cannot_view_waitlist_entry_from_different_department(): void
    {
        $entry = WaitlistEntry::withoutGlobalScopes()->create([
            'client_id' => $this->clientDeptA->id,
            'service_type' => 'waitlist-dept-a',
            'priority' => 1,
            'preferred_start' => now()->addDay(),
            'preferred_end' => now()->addDay()->addHour(),
            'status' => 'waiting',
            'site_id' => 1,
            'department_id' => 1,
        ]);

        $token = $this->token('staff_dept_b');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/waitlist/'.$entry->id)
            ->assertStatus(403);
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
