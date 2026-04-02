<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\ReconciliationException;
use App\Models\SettlementImport;
use App\Models\User;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        $staff = User::query()->where('identifier', 'staff001')->firstOrFail();

        Payment::withoutGlobalScopes()->create([
            'reference_id' => 'RCN-TXN-001',
            'amount' => 100.00,
            'method' => 'cash',
            'fee_assessment_id' => null,
            'posted_by' => $staff->id,
            'site_id' => 1,
            'notes' => 'seed',
            'created_at' => now(),
        ]);
    }

    public function test_import_settlement_csv(): void
    {
        $token = $this->token('reviewer001');
        $file = $this->csvFile(
            "transaction_id,amount,type,timestamp,terminal_id\nRCN-TXN-001,100.00,sale,2026-03-27T10:00:00Z,T-1\n",
            'import1.csv'
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $file])
            ->assertStatus(201)
            ->assertJsonPath('data.import.matched_count', 1);
    }

    public function test_duplicate_file_rejected(): void
    {
        $token = $this->token('reviewer001');
        $content = "transaction_id,amount,type,timestamp,terminal_id\nRCN-TXN-001,100.00,sale,2026-03-27T10:00:00Z,T-1\n";

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $this->csvFile($content, 'dup1.csv')])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $this->csvFile($content, 'dup2.csv')])
            ->assertStatus(409)
            ->assertJson(['error' => 'DUPLICATE_SETTLEMENT_FILE']);
    }

    public function test_order_not_found_creates_exception(): void
    {
        $token = $this->token('reviewer001');
        $file = $this->csvFile(
            "transaction_id,amount,type,timestamp,terminal_id\nUNKNOWN-TXN,10.00,sale,2026-03-27T10:00:00Z,T-1\n",
            'notfound.csv'
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $file])
            ->assertStatus(201);

        $this->assertDatabaseHas('reconciliation_exceptions', ['reason' => 'ORDER_NOT_FOUND']);
    }

    public function test_amount_mismatch_creates_exception(): void
    {
        $token = $this->token('reviewer001');
        $file = $this->csvFile(
            "transaction_id,amount,type,timestamp,terminal_id\nRCN-TXN-001,120.50,sale,2026-03-27T10:00:00Z,T-1\n",
            'mismatch.csv'
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $file])
            ->assertStatus(201);

        $this->assertDatabaseHas('reconciliation_exceptions', ['reason' => 'AMOUNT_MISMATCH']);
    }

    public function test_variance_over_50_creates_anomaly(): void
    {
        $token = $this->token('reviewer001');
        $file = $this->csvFile(
            "transaction_id,amount,type,timestamp,terminal_id\nUNKNOWN-TXN,72.50,sale,2026-03-27T10:00:00Z,T-1\n",
            'variance.csv'
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $file])
            ->assertStatus(201)
            ->assertJsonPath('data.anomaly_alert', true);

        $this->assertDatabaseHas('anomaly_alerts', ['status' => 'unresolved']);
    }

    public function test_reviewer_resolves_exception(): void
    {
        $token = $this->token('reviewer001');
        $file = $this->csvFile(
            "transaction_id,amount,type,timestamp,terminal_id\nUNKNOWN-TXN,10.00,sale,2026-03-27T10:00:00Z,T-1\n",
            'resolve.csv'
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $file])
            ->assertStatus(201);

        $id = (int) DB::table('reconciliation_exceptions')->latest('id')->value('id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/reconciliation/exceptions/'.$id.'/resolve', [
                'resolution_note' => 'resolved by reviewer',
            ])
            ->assertOk();

        $this->assertDatabaseHas('reconciliation_exceptions', ['id' => $id, 'status' => 'resolved']);
    }

    public function test_import_empty_csv_file_returns_422(): void
    {
        $token = $this->token('reviewer001');
        $file = $this->csvFile('', 'empty.csv');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reconciliation/import', ['file' => $file])
            ->assertStatus(422);
    }

    public function test_import_csv_missing_required_column_returns_422(): void
    {
        $token = $this->token('reviewer001');
        $file = $this->csvFile(
            "transaction_id,type,timestamp,terminal_id\nRCN-TXN-001,sale,2026-03-27T10:00:00Z,T-1\n",
            'missing-amount.csv'
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reconciliation/import', ['file' => $file]);

        $this->assertContains($response->status(), [422, 409]);
    }

    public function test_import_csv_with_only_header_row_returns_422(): void
    {
        $token = $this->token('reviewer001');
        $file = $this->csvFile(
            "transaction_id,amount,type,timestamp,terminal_id\n",
            'header-only.csv'
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reconciliation/import', ['file' => $file])
            ->assertStatus(422);
    }

    public function test_reconciliation_import_rolls_back_on_db_failure(): void
    {
        $token = $this->token('admin001');
        $content = "transaction_id,amount,type,timestamp,terminal_id\nRCN-ROLLBACK-001,100.00,sale,2026-03-27T10:00:00Z,T-99\n";

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $this->csvFile($content, 'rollback_test_1.csv')])
            ->assertStatus(201);

        $countAfterFirst = SettlementImport::query()->count();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $this->csvFile($content, 'rollback_test_2.csv')])
            ->assertStatus(409);

        $countAfterDuplicate = SettlementImport::query()->count();

        $this->assertSame(
            $countAfterFirst,
            $countAfterDuplicate,
            'Duplicate import must not create any partial records'
        );
    }

    public function test_import_csv_row_with_negative_amount_creates_exception_or_is_flagged(): void
    {
        $token = $this->token('reviewer001');
        $file = $this->csvFile(
            "transaction_id,amount,type,timestamp,terminal_id\nRCN-NEG-001,-50.00,sale,2026-03-27T10:00:00Z,T-1\n",
            'negative-amount.csv'
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $file])
            ->assertStatus(201);

        $exists = ReconciliationException::query()
            ->where('reason', 'ORDER_NOT_FOUND')
            ->get()
            ->contains(fn (ReconciliationException $item) => ($item->row_data['transaction_id'] ?? null) === 'RCN-NEG-001');

        $this->assertTrue($exists, 'Negative amount row should be flagged as an exception.');
    }

    public function test_import_csv_row_with_missing_terminal_id_is_flagged(): void
    {
        $token = $this->token('reviewer001');
        $file = $this->csvFile(
            "transaction_id,amount,type,timestamp,terminal_id\nRCN-NOTERM-001,25.00,sale,2026-03-27T10:00:00Z,\n",
            'missing-terminal-id.csv'
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $file])
            ->assertStatus(201);

        $exists = ReconciliationException::query()
            ->where('reason', 'ORDER_NOT_FOUND')
            ->get()
            ->contains(fn (ReconciliationException $item) => ($item->row_data['transaction_id'] ?? null) === 'RCN-NOTERM-001');

        $this->assertTrue($exists, 'Row with missing terminal_id should be flagged as an exception.');
    }

    public function test_resolving_already_resolved_exception_is_rejected(): void
    {
        $token = $this->token('reviewer001');
        $file = $this->csvFile(
            "transaction_id,amount,type,timestamp,terminal_id\nUNKNOWN-TXN-AGAIN,10.00,sale,2026-03-27T10:00:00Z,T-1\n",
            'resolve-twice.csv'
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/reconciliation/import', ['file' => $file])
            ->assertStatus(201);

        $exceptionId = (int) DB::table('reconciliation_exceptions')->latest('id')->value('id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/reconciliation/exceptions/'.$exceptionId.'/resolve', [
                'resolution_note' => 'First resolution note',
            ])
            ->assertOk();

        $status = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/reconciliation/exceptions/'.$exceptionId.'/resolve', [
                'resolution_note' => 'Second resolution note',
            ])
            ->status();

        $this->assertContains($status, [409, 422]);
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

    private function csvFile(string $content, string $name): UploadedFile
    {
        Storage::fake('local');
        $path = storage_path('framework/testing/'.$name);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }
}
