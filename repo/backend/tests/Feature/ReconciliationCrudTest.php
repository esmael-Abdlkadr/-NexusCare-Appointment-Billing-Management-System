<?php

namespace Tests\Feature;

use App\Models\AnomalyAlert;
use App\Models\SettlementImport;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ReconciliationCrudTest extends TestCase
{
    use RefreshDatabase;

    private int $anomalyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        $reviewer = User::withoutGlobalScopes()->where('identifier', 'reviewer001')->firstOrFail();

        $import = SettlementImport::withoutGlobalScopes()->create([
            'filename' => 'crud-import.csv',
            'file_hash' => hash('sha256', 'crud-import-1'),
            'imported_by' => $reviewer->id,
            'site_id' => 1,
            'row_count' => 1,
            'matched_count' => 0,
            'discrepancy_count' => 1,
            'daily_variance' => 72.50,
            'created_at' => now(),
        ]);

        $alert = AnomalyAlert::withoutGlobalScopes()->create([
            'import_id' => $import->id,
            'site_id' => 1,
            'variance_amount' => 72.50,
            'status' => 'unresolved',
            'acknowledged_by' => null,
            'created_at' => now(),
        ]);

        $this->anomalyId = (int) $alert->id;
    }

    public function test_reviewer_can_list_imports(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reconciliation/imports')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_admin_can_list_imports(): void
    {
        $token = $this->token('admin001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reconciliation/imports')
            ->assertOk();
    }

    public function test_staff_cannot_list_imports(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reconciliation/imports')
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_list_imports(): void
    {
        $this->getJson('/api/reconciliation/imports')->assertStatus(401);
    }

    public function test_reviewer_can_list_anomalies(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reconciliation/anomalies')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_staff_cannot_list_anomalies(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reconciliation/anomalies')
            ->assertStatus(403);
    }

    public function test_reviewer_can_acknowledge_anomaly(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/reconciliation/anomalies/'.$this->anomalyId.'/acknowledge')
            ->assertOk();

        $this->assertDatabaseHas('anomaly_alerts', [
            'id' => $this->anomalyId,
            'status' => 'acknowledged',
        ]);
    }

    public function test_staff_cannot_acknowledge_anomaly(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/reconciliation/anomalies/'.$this->anomalyId.'/acknowledge')
            ->assertStatus(403);
    }

    public function test_acknowledge_nonexistent_anomaly_returns_404(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/reconciliation/anomalies/999999/acknowledge')
            ->assertStatus(404);
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
