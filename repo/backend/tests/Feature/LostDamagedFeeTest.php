<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LostDamagedFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_staff_can_assess_lost_damaged_fee(): void
    {
        $token = $this->loginAs('staff001');
        $clientId = (int) User::withoutGlobalScopes()->where('identifier', 'client001')->value('id');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Client-Type' => 'api',
        ])->postJson('/api/fee-assessments', [
            'client_id' => $clientId,
            'amount' => 35.00,
        ])->assertCreated();

        $this->assertDatabaseHas('fee_assessments', [
            'client_id' => $clientId,
            'fee_type' => 'lost_damaged',
            'status' => 'pending',
            'amount' => 35.00,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'entry_type' => 'fee',
            'client_id' => $clientId,
            'site_id' => 1,
            'amount' => 35.00,
            'description' => 'Lost/damaged fee assessed',
        ]);

        $staffId = (int) User::withoutGlobalScopes()->where('identifier', 'staff001')->value('id');
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $staffId,
            'action' => 'ASSESS_LOST_DAMAGED_FEE',
        ]);
    }

    public function test_reviewer_cannot_assess_lost_damaged_fee(): void
    {
        $token = $this->loginAs('reviewer001');
        $clientId = (int) User::withoutGlobalScopes()->where('identifier', 'client001')->value('id');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Client-Type' => 'api',
        ])->postJson('/api/fee-assessments', [
            'client_id' => $clientId,
            'amount' => 35.00,
        ])->assertStatus(403);
    }

    public function test_assess_fee_requires_valid_client_id(): void
    {
        $token = $this->loginAs('staff001');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Client-Type' => 'api',
        ])->postJson('/api/fee-assessments', [
            'client_id' => 999999,
            'amount' => 35.00,
        ])->assertStatus(422);
    }

    public function test_assess_fee_requires_positive_amount(): void
    {
        $token = $this->loginAs('staff001');
        $clientId = (int) User::withoutGlobalScopes()->where('identifier', 'client001')->value('id');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Client-Type' => 'api',
        ])->postJson('/api/fee-assessments', [
            'client_id' => $clientId,
            'amount' => 0,
        ])->assertStatus(422);
    }

    public function test_assess_fee_requires_site_scoped_client(): void
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
            'identifier' => 'site2_client_'.Str::lower(Str::random(6)),
            'password_hash' => Hash::make('Admin@12345678', ['rounds' => 12]),
            'role' => 'staff',
            'site_id' => 2,
            'department_id' => 2,
            'is_banned' => false,
            'failed_attempts' => 0,
        ]);

        $token = $this->loginAs('staff001');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Client-Type' => 'api',
        ])->postJson('/api/fee-assessments', [
            'client_id' => $site2Client->id,
            'amount' => 35.00,
        ])->assertStatus(422);
    }

    private function loginAs(string $identifier): string
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
