<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\FeeAssessment;
use App\Models\Payment;
use App\Models\Site;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_staff_can_post_payment_with_same_site_fee_assessment(): void
    {
        $token = $this->token('staff001');
        $client = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $fee = FeeAssessment::withoutGlobalScopes()->create([
            'appointment_id' => null,
            'client_id' => $client->id,
            'fee_type' => 'no_show',
            'amount' => 25.00,
            'status' => 'pending',
            'assessed_at' => now()->subHour(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Client-Type' => 'api',
        ])->postJson('/api/payments', [
            'reference_id' => 'PAY-SAME-'.Str::upper(Str::random(8)),
            'amount' => 25.00,
            'method' => 'cash',
            'fee_assessment_id' => $fee->id,
        ])->assertStatus(201);
    }

    public function test_staff_cannot_post_payment_with_cross_site_fee_assessment(): void
    {
        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);
        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        $site2Client = User::withoutGlobalScopes()->create([
            'identifier' => 'client_site2_'.Str::lower(Str::random(6)),
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
            'role' => 'staff',
            'site_id' => 2,
            'department_id' => 2,
            'is_banned' => false,
            'failed_attempts' => 0,
        ]);

        $fee = FeeAssessment::withoutGlobalScopes()->create([
            'appointment_id' => null,
            'client_id' => $site2Client->id,
            'fee_type' => 'no_show',
            'amount' => 30.00,
            'status' => 'pending',
            'assessed_at' => now()->subHour(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $token = $this->token('staff001');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Client-Type' => 'api',
        ])->postJson('/api/payments', [
            'reference_id' => 'PAY-XSITE-'.Str::upper(Str::random(8)),
            'amount' => 30.00,
            'method' => 'cash',
            'fee_assessment_id' => $fee->id,
        ])->assertStatus(403);
    }

    public function test_payment_service_returns_forbidden_for_cross_site_fee_assessment(): void
    {
        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);
        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        $site2Client = User::withoutGlobalScopes()->create([
            'identifier' => 'client_site2_guard_'.Str::lower(Str::random(6)),
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
            'role' => 'staff',
            'site_id' => 2,
            'department_id' => 2,
            'is_banned' => false,
            'failed_attempts' => 0,
        ]);

        $fee = FeeAssessment::withoutGlobalScopes()->create([
            'appointment_id' => null,
            'client_id' => $site2Client->id,
            'fee_type' => 'no_show',
            'amount' => 40.00,
            'status' => 'pending',
            'assessed_at' => now()->subHour(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $actor = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();
        $service = app(PaymentService::class);

        $result = $service->postPayment([
            'reference_id' => 'PAY-GUARD-'.Str::upper(Str::random(8)),
            'amount' => 40.00,
            'method' => 'cash',
            'fee_assessment_id' => $fee->id,
        ], $actor);

        $this->assertFalse($result['success']);
        $this->assertSame(403, $result['status']);
        $this->assertSame('FORBIDDEN', $result['error']);
    }

    public function test_staff_can_post_payment_with_same_site_client_id(): void
    {
        $sameClient = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();
        $token = $this->token('staff001');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Client-Type' => 'api',
        ])->postJson('/api/payments', [
            'reference_id' => 'PAY-CID-SAME-'.Str::upper(Str::random(8)),
            'amount' => 15.00,
            'method' => 'cash',
            'client_id' => $sameClient->id,
        ])->assertStatus(201);
    }

    public function test_payments_index_respects_per_page(): void
    {
        $token = $this->token('staff001');
        $staff = User::withoutGlobalScopes()->where('identifier', 'staff001')->firstOrFail();

        for ($i = 0; $i < 3; $i++) {
            Payment::withoutGlobalScopes()->create([
                'reference_id' => 'PAY-PAGE-'.strtoupper(uniqid()),
                'amount' => 10.00,
                'method' => 'cash',
                'posted_by' => $staff->id,
                'site_id' => $staff->site_id,
                'created_at' => now(),
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Client-Type' => 'api',
        ])->getJson('/api/payments?per_page=1&page=1');

        $response->assertOk();
        $response->assertJsonPath('data.per_page', 1);
        $response->assertJsonCount(1, 'data.data');
        $this->assertGreaterThanOrEqual(3, $response->json('data.total'));
    }

    public function test_staff_cannot_post_payment_with_cross_site_client_id(): void
    {
        Site::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Site 2',
        ]);
        Department::withoutGlobalScopes()->firstOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Dept 2',
        ]);

        $site2User = User::withoutGlobalScopes()->create([
            'identifier' => 'client_cid_xsite_'.Str::lower(Str::random(6)),
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 4]),
            'role' => 'staff',
            'site_id' => 2,
            'department_id' => 2,
            'is_banned' => false,
            'failed_attempts' => 0,
        ]);

        $token = $this->token('staff001');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Client-Type' => 'api',
        ])->postJson('/api/payments', [
            'reference_id' => 'PAY-CID-XSITE-'.Str::upper(Str::random(8)),
            'amount' => 20.00,
            'method' => 'cash',
            'client_id' => $site2User->id,
        ])->assertStatus(403);
    }

    private function token(string $identifier): string
    {
        User::withoutGlobalScopes()->where('identifier', $identifier)->update([
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 4]),
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
