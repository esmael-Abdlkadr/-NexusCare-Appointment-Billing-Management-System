<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\FeeAssessment;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_reviewer_can_get_appointment_report(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/appointments')
            ->assertOk();
    }

    public function test_staff_cannot_get_appointment_report(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/appointments')
            ->assertStatus(403);
    }

    public function test_report_supports_date_range(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/appointments?date_from=2024-01-01&date_to=2025-12-31')
            ->assertOk();
    }

    public function test_reviewer_can_get_financial_report(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/financial')
            ->assertOk();
    }

    public function test_staff_cannot_get_financial_report(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/financial')
            ->assertStatus(403);
    }

    public function test_reviewer_financial_report_is_scoped_to_reviewer_site(): void
    {
        Site::withoutGlobalScopes()->updateOrCreate(['id' => 2], [
            'organization_id' => 1,
            'name' => 'Secondary Site',
        ]);

        Department::withoutGlobalScopes()->updateOrCreate(['id' => 2], [
            'site_id' => 2,
            'name' => 'Secondary Department',
        ]);

        $clientSiteOneId = (int) User::withoutGlobalScopes()->where('identifier', 'client001')->value('id');

        $clientSiteTwo = User::withoutGlobalScopes()->create([
            'identifier' => 'site2-client-report',
            'password_hash' => bcrypt('Admin@12345678'),
            'role' => 'staff',
            'site_id' => 2,
            'department_id' => 2,
            'is_banned' => false,
            'failed_attempts' => 0,
        ]);

        $siteOneFee = FeeAssessment::withoutGlobalScopes()->create([
            'appointment_id' => null,
            'client_id' => $clientSiteOneId,
            'fee_type' => 'no_show',
            'amount' => 25.00,
            'status' => 'pending',
            'assessed_at' => now()->subDay(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $siteTwoFee = FeeAssessment::withoutGlobalScopes()->create([
            'appointment_id' => null,
            'client_id' => $clientSiteTwo->id,
            'fee_type' => 'lost_damaged',
            'amount' => 99.00,
            'status' => 'pending',
            'assessed_at' => now()->subDay(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $token = $this->token('reviewer001');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/financial?format=csv')
            ->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('FEE-'.$siteOneFee->id, $content);
        $this->assertStringNotContainsString('FEE-'.$siteTwoFee->id, $content);
    }

    public function test_admin_can_get_audit_report(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/audit')
            ->assertOk();
    }

    public function test_reviewer_cannot_get_audit_report(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/audit')
            ->assertStatus(403);
    }

    public function test_staff_cannot_get_audit_report(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/audit')
            ->assertStatus(403);
    }

    public function test_appointment_report_json_format_returns_json_structure(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/appointments?format=json')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_financial_report_json_format_returns_json_structure(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/reports/financial?format=json')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_report_invalid_format_is_rejected(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports/appointments?format=pdf')
            ->assertStatus(422);
    }

    private function token(string $identifier): string
    {
        $user = User::withoutGlobalScopes()->where('identifier', $identifier)->firstOrFail();
        $jti = (string) Str::uuid();
        $token = JWTAuth::claims(['jti' => $jti])->fromUser($user);

        UserSession::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'token_jti' => $jti,
            'last_active_at' => now(),
            'expires_at' => now()->addHours(12),
        ]);

        return $token;
    }
}
