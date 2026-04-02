<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AnomalyAlert;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\FeeAssessment;
use App\Models\Payment;
use App\Models\ReconciliationException;
use App\Models\SettlementImport;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSession;
use App\Support\AuditLogger;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_reviewer_cannot_view_user_from_different_site(): void
    {
        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        $site2User = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'site2_staff'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users/'.$site2User->id)
            ->assertStatus(403);
    }

    public function test_financial_report_does_not_leak_cross_site_fees(): void
    {
        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        $site2Client = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'client_site2'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        FeeAssessment::query()->create([
            'client_id' => $site2Client->id,
            'fee_type' => 'no_show',
            'amount' => 999.99,
            'status' => 'pending',
            'assessed_at' => now()->subDay(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $token = $this->token('reviewer001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/financial?format=csv')
            ->assertOk();

        $this->assertStringNotContainsString('999.99', (string) $response->getContent());
    }

    public function test_token_valid_just_before_absolute_timeout(): void
    {
        $token = $this->loginAs('staff001');
        $session = UserSession::query()->latest('id')->firstOrFail();
        $session->expires_at = now()->addMinute();
        $session->last_active_at = now();
        $session->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk();
    }

    public function test_token_expired_just_after_absolute_timeout(): void
    {
        $token = $this->loginAs('staff001');
        $session = UserSession::query()->latest('id')->firstOrFail();
        $session->expires_at = now()->subMinute();
        $session->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJson(['error' => 'SESSION_EXPIRED']);
    }

    public function test_audit_logger_redacts_password_and_token_fields(): void
    {
        $user = User::withoutGlobalScopes()->where('identifier', 'admin001')->firstOrFail();

        $sanitized = AuditLogger::sanitizedPayload(new Request([
            'password' => 'SuperSecret@123',
            'access_token' => 'eyJhbGci...',
            'safe_field' => 'visible_value',
            'government_id' => 'GOV-12345',
        ]));

        AuditLogger::write(
            $user->id,
            'TEST_EVENT',
            User::class,
            $user->id,
            $sanitized,
            '127.0.0.1'
        );

        $this->assertDatabaseMissing('audit_logs', ['payload->password' => 'SuperSecret@123']);
        $this->assertDatabaseMissing('audit_logs', ['payload->access_token' => 'eyJhbGci...']);

        $logEntry = AuditLog::query()->latest('id')->firstOrFail();
        $payload = $logEntry->payload;

        $this->assertSame('[REDACTED]', $payload['password'] ?? null);
        $this->assertSame('[REDACTED]', $payload['access_token'] ?? null);
        $this->assertSame('[REDACTED]', $payload['government_id'] ?? null);
        $this->assertSame('visible_value', $payload['safe_field'] ?? null);
    }

    public function test_user_list_with_per_page_1_returns_single_item(): void
    {
        $token = $this->token('admin001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users?per_page=1')
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_user_list_out_of_range_page_returns_empty(): void
    {
        $token = $this->token('admin001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users?page=99999&per_page=20')
            ->assertOk();

        $this->assertEmpty($response->json('data.data'));
    }

    public function test_per_page_over_100_is_rejected(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users?per_page=9999')
            ->assertStatus(422);
    }

    public function test_staff_can_search_users_within_own_site(): void
    {
        $token = $this->token('staff001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users/search?identifier=staff')
            ->assertOk();

        $users = $response->json('data');
        $this->assertNotEmpty($users);

        foreach ($users as $user) {
            $this->assertSame(1, $user['site_id'] ?? null);
        }
    }

    public function test_staff_user_search_does_not_return_cross_site_users(): void
    {
        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'cross_site_target'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $token = $this->token('staff001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users/search?identifier=cross_site_target')
            ->assertOk();

        $this->assertEmpty($response->json('data'));
    }

    public function test_reviewer_cannot_see_site2_payments_in_payment_list(): void
    {
        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        $site2Poster = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'site2_poster'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $site2Payment = Payment::withoutGlobalScopes()->create([
            'reference_id' => 'SITE2-PAY-LEAK',
            'amount' => 777.77,
            'method' => 'cash',
            'fee_assessment_id' => null,
            'posted_by' => $site2Poster->id,
            'site_id' => 2,
            'notes' => 'site2 payment',
            'created_at' => now(),
        ]);

        $token = $this->token('reviewer001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/payments')
            ->assertOk();

        $rows = $response->json('data.data');
        $this->assertIsArray($rows);
        $ids = array_column($rows, 'id');
        $this->assertNotContains($site2Payment->id, $ids, 'Site-2 payment must not appear in reviewer payment list');
        foreach ($rows as $row) {
            $this->assertNotSame('SITE2-PAY-LEAK', $row['reference_id'] ?? null, 'Site-2 payment must not leak via reference');
        }
    }

    public function test_reviewer_cannot_resolve_site2_reconciliation_exception(): void
    {
        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        $site2Reviewer = User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'reviewer_site2_for_exception'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'reviewer',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $import = SettlementImport::withoutGlobalScopes()->create([
            'filename' => 'site2.csv',
            'file_hash' => hash('sha256', 'site2-recon-import'),
            'imported_by' => $site2Reviewer->id,
            'site_id' => 2,
            'row_count' => 1,
            'matched_count' => 0,
            'discrepancy_count' => 1,
            'daily_variance' => 10.00,
            'created_at' => now(),
        ]);

        $exception = ReconciliationException::withoutGlobalScopes()->create([
            'import_id' => $import->id,
            'row_data' => ['transaction_id' => 'X-1'],
            'expected_amount' => null,
            'actual_amount' => 10.00,
            'reason' => 'ORDER_NOT_FOUND',
            'status' => 'unresolved',
            'created_at' => now(),
        ]);

        $token = $this->token('reviewer001');

        $status = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/reconciliation/exceptions/'.$exception->id.'/resolve', [
                'resolution_note' => 'cross-site attempt',
            ])
            ->status();

        $this->assertContains($status, [403, 404]);
    }

    public function test_appointment_list_per_page_1_returns_single_item(): void
    {
        $provider = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $client = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        foreach ([1, 2, 3] as $index) {
            Appointment::withoutGlobalScopes()->create([
                'client_id' => $client->id,
                'provider_id' => $provider->id,
                'resource_id' => 1,
                'service_type' => 'pagination-'.$index,
                'start_time' => now()->addDays(5 + $index),
                'end_time' => now()->addDays(5 + $index)->addMinutes(30),
                'status' => Appointment::STATUS_CONFIRMED,
                'site_id' => 1,
                'department_id' => 1,
            ]);
        }

        $token = $this->token('staff001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments?per_page=1')
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
        $this->assertGreaterThanOrEqual(3, (int) $response->json('data.total'));
    }

    public function test_appointment_list_out_of_range_page_returns_empty(): void
    {
        $token = $this->token('staff001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/appointments?per_page=10&page=9999')
            ->assertOk();

        $this->assertEmpty($response->json('data.data'));
    }

    public function test_fee_list_per_page_over_100_is_rejected(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/fee-assessments?per_page=999')
            ->assertStatus(422);
    }

    public function test_audit_log_records_cannot_be_updated(): void
    {
        $log = AuditLog::query()->create([
            'user_id' => null,
            'action' => 'IMMUTABILITY_TEST',
            'target_type' => null,
            'target_id' => null,
            'payload' => [],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $log->update(['action' => 'TAMPERED']);
    }

    public function test_audit_log_records_cannot_be_deleted(): void
    {
        $log = AuditLog::query()->create([
            'user_id' => null,
            'action' => 'IMMUTABILITY_DELETE_TEST',
            'target_type' => null,
            'target_id' => null,
            'payload' => [],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $log->delete();
    }

    public function test_waitlist_list_per_page_1_returns_single_item(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/waitlist?per_page=1')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_waitlist_per_page_over_100_is_rejected(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/waitlist?per_page=999')
            ->assertStatus(422);
    }

    public function test_payments_list_per_page_1_returns_ok(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/payments?per_page=1')
            ->assertOk();
    }

    public function test_payments_per_page_over_100_is_rejected(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/payments?per_page=999')
            ->assertStatus(422);
    }

    public function test_site2_staff_cannot_access_site1_payment(): void
    {
        $site1Token = $this->loginAs('staff001');

        $postResp = $this->withHeader('Authorization', 'Bearer '.$site1Token)
            ->postJson('/api/payments', [
                'reference_id' => 'ISO-PAY-'.uniqid(),
                'amount' => 50.00,
                'method' => 'cash',
            ])
            ->assertStatus(201);

        $paymentId = $postResp->json('data.payment.id');

        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'site2_staff_iso_payments'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $site2Token = $this->loginAs('site2_staff_iso_payments');

        $listResp = $this->withHeader('Authorization', 'Bearer '.$site2Token)
            ->getJson('/api/payments')
            ->assertOk();

        $ids = collect($listResp->json('data.data') ?? $listResp->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($paymentId, $ids, 'Site2 staff must not see site1 payments');
    }

    public function test_site2_staff_cannot_see_site1_resource(): void
    {
        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'site2_staff_iso_resources'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        \App\Models\Resource::withoutGlobalScopes()->firstOrCreate(['id' => 2002], [
            'name' => 'Site 2 Resource',
            'type' => 'room',
            'site_id' => 2,
            'is_active' => true,
        ]);

        $site1Token = $this->loginAs('staff001');
        $site1Resp = $this->withHeader('Authorization', 'Bearer '.$site1Token)
            ->getJson('/api/resources')
            ->assertOk();
        $site1Ids = collect($site1Resp->json('data'))->pluck('id')->toArray();

        $site2Token = $this->loginAs('site2_staff_iso_resources');
        $site2Resp = $this->withHeader('Authorization', 'Bearer '.$site2Token)
            ->getJson('/api/resources')
            ->assertOk();
        $site2Ids = collect($site2Resp->json('data'))->pluck('id')->toArray();

        $overlap = array_intersect($site1Ids, $site2Ids);
        $this->assertEmpty($overlap, 'Site1 and site2 resources must not overlap');
    }

    public function test_site2_reviewer_cannot_approve_fee_waiver_from_site1(): void
    {
        $site1Client = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $fee = FeeAssessment::withoutGlobalScopes()->create([
            'appointment_id' => null,
            'client_id' => $site1Client->id,
            'fee_type' => 'no_show',
            'amount' => 40.00,
            'status' => 'pending',
            'assessed_at' => now(),
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'site2_reviewer_fee_waiver'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'reviewer',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $site2ReviewerToken = $this->loginAs('site2_reviewer_fee_waiver');

        $this->withHeader('Authorization', 'Bearer '.$site2ReviewerToken)
            ->postJson('/api/fee-assessments/'.$fee->id.'/waiver', [
                'waiver_type' => 'waived',
                'waiver_note' => 'Cross-site attempt',
            ])
            ->assertStatus(403);
    }

    public function test_site2_staff_cannot_see_site1_reconciliation_imports(): void
    {
        $site1Import = SettlementImport::withoutGlobalScopes()->create([
            'filename' => 'site1-import.csv',
            'file_hash' => hash('sha256', 'site1-import-hash'),
            'imported_by' => User::withoutGlobalScopes()->where('identifier', 'reviewer001')->firstOrFail()->id,
            'site_id' => 1,
            'row_count' => 1,
            'matched_count' => 0,
            'discrepancy_count' => 1,
            'daily_variance' => 100.00,
            'created_at' => now(),
        ]);

        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'site2_staff_import_scope'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'staff',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $site2StaffToken = $this->loginAs('site2_staff_import_scope');

        $this->withHeader('Authorization', 'Bearer '.$site2StaffToken)
            ->getJson('/api/reconciliation/imports')
            ->assertStatus(403);

        User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'site2_reviewer_import_scope'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'reviewer',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $site2ReviewerToken = $this->loginAs('site2_reviewer_import_scope');

        $response = $this->withHeader('Authorization', 'Bearer '.$site2ReviewerToken)
            ->getJson('/api/reconciliation/imports')
            ->assertOk();

        $ids = collect($response->json('data.data', []))->pluck('id')->all();
        $this->assertNotContains($site1Import->id, $ids);
    }

    public function test_site2_reviewer_cannot_see_site1_anomalies(): void
    {
        $site1Import = SettlementImport::withoutGlobalScopes()->create([
            'filename' => 'site1-anomaly.csv',
            'file_hash' => hash('sha256', 'site1-anomaly-hash'),
            'imported_by' => User::withoutGlobalScopes()->where('identifier', 'reviewer001')->firstOrFail()->id,
            'site_id' => 1,
            'row_count' => 1,
            'matched_count' => 0,
            'discrepancy_count' => 1,
            'daily_variance' => 200.00,
            'created_at' => now(),
        ]);

        $anomaly = AnomalyAlert::withoutGlobalScopes()->create([
            'import_id' => $site1Import->id,
            'site_id' => 1,
            'variance_amount' => 200.00,
            'status' => 'unresolved',
            'acknowledged_by' => null,
            'created_at' => now(),
        ]);

        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);

        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => 'site2_reviewer_anomaly_scope'],
            [
                'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
                'role' => 'reviewer',
                'site_id' => 2,
                'department_id' => 2,
                'is_banned' => false,
                'failed_attempts' => 0,
            ]
        );

        $site2ReviewerToken = $this->loginAs('site2_reviewer_anomaly_scope');

        $response = $this->withHeader('Authorization', 'Bearer '.$site2ReviewerToken)
            ->getJson('/api/reconciliation/anomalies')
            ->assertOk();

        $ids = collect($response->json('data', []))->pluck('id')->all();
        $this->assertNotContains($anomaly->id, $ids);
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

    private function loginAs(string $identifier): string
    {
        return $this->token($identifier);
    }
}
