<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);
    }

    public function test_payment_post_writes_to_billing_log_channel(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('billing')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return ($context['event'] ?? null) === 'payment_posted'
                    && isset($context['amount'])
                    && isset($context['client_id']);
            })
            ->andReturnNull();

        $token = $this->token('staff001');
        $client = User::withoutGlobalScopes()->where('identifier', 'client001')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/payments', [
                'reference_id' => 'LOG-PAY-001',
                'amount' => 15.50,
                'method' => 'cash',
                'client_id' => $client->id,
            ])
            ->assertStatus(201);
    }

    public function test_successful_login_writes_to_auth_log_channel(): void
    {
        Log::shouldReceive('channel')
            ->with('auth')
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->withArgs(function (string $message, array $context): bool {
                return ($context['event'] ?? null) === 'login_success'
                    && isset($context['identifier'])
                    && isset($context['ip']);
            })
            ->andReturnNull();
        Log::shouldReceive('channel')->withAnyArgs()->andReturnSelf();
        Log::shouldReceive('warning')->withAnyArgs()->andReturnNull();
        Log::shouldReceive('error')->withAnyArgs()->andReturnNull();

        $this->withHeader('X-Client-Type', 'api')
            ->postJson('/api/auth/login', [
                'identifier' => 'staff001',
                'password' => 'Admin@12345678',
            ])
            ->assertOk();
    }

    public function test_failed_login_writes_warning_to_auth_log_channel(): void
    {
        Log::shouldReceive('channel')
            ->with('auth')
            ->andReturnSelf();
        Log::shouldReceive('warning')
            ->withArgs(function (string $message, array $context): bool {
                return ($context['event'] ?? null) === 'login_failed'
                    && isset($context['identifier']);
            })
            ->andReturnNull();
        Log::shouldReceive('channel')->withAnyArgs()->andReturnSelf();
        Log::shouldReceive('info')->withAnyArgs()->andReturnNull();
        Log::shouldReceive('error')->withAnyArgs()->andReturnNull();

        $this->withHeader('X-Client-Type', 'api')
            ->postJson('/api/auth/login', [
                'identifier' => 'staff001',
                'password' => 'wrong-password',
            ])
            ->assertStatus(401);
    }

    public function test_duplicate_reconciliation_import_writes_to_reconciliation_channel(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\AuditLoggerMiddleware::class);

        $infoLogs = [];

        Log::shouldReceive('channel')->withAnyArgs()->andReturnSelf();
        Log::shouldReceive('info')->withAnyArgs()->andReturnUsing(function (...$args) use (&$infoLogs) {
            $message = (string) ($args[0] ?? '');
            $context = is_array($args[1] ?? null) ? $args[1] : [];
            $infoLogs[] = ['message' => $message, 'context' => $context];
            return null;
        });
        Log::shouldReceive('warning')->withAnyArgs()->andReturnNull();
        Log::shouldReceive('error')->withAnyArgs()->andReturnNull();

        $token = $this->token('reviewer001');
        $csv = implode("\n", [
            'transaction_id,amount,type,timestamp,terminal_id',
            'RCN-TXN-001,100.00,sale,2026-01-15T10:00:00Z,TERM-01',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('log_test.csv', $csv);
        $headers = ['Authorization' => 'Bearer '.$token, 'X-Client-Type' => 'api'];

        $this->withHeaders($headers)
            ->post('/api/reconciliation/import', ['file' => $file])
            ->assertStatus(201);

        $file2 = \Illuminate\Http\UploadedFile::fake()->createWithContent('log_test.csv', $csv);
        $this->withHeaders($headers)
            ->post('/api/reconciliation/import', ['file' => $file2])
            ->assertStatus(409);

        $this->assertTrue(collect($infoLogs)->contains(function (array $log): bool {
            $message = strtolower((string) ($log['message'] ?? ''));
            $event = (string) (($log['context']['event'] ?? ''));

            return str_contains($message, 'duplicate')
                || $event === 'duplicate_import'
                || $event === 'import_duplicate';
        }));
    }

    public function test_auth_channel_never_logs_password(): void
    {
        $loggedContext = [];

        Log::shouldReceive('channel')
            ->with('auth')
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->withAnyArgs()
            ->andReturnUsing(function (string $msg, array $ctx) use (&$loggedContext) {
                $loggedContext[] = $ctx;
                return null;
            });
        Log::shouldReceive('warning')
            ->withAnyArgs()
            ->andReturnUsing(function (string $msg, array $ctx) use (&$loggedContext) {
                $loggedContext[] = $ctx;
                return null;
            });
        Log::shouldReceive('channel')->withAnyArgs()->andReturnSelf();
        Log::shouldReceive('error')->withAnyArgs()->andReturnNull();

        $this->withHeader('X-Client-Type', 'api')
            ->postJson('/api/auth/login', [
                'identifier' => 'staff001',
                'password' => 'Admin@12345678',
            ]);

        foreach ($loggedContext as $ctx) {
            $this->assertArrayNotHasKey('password', $ctx);
            $this->assertArrayNotHasKey('password_hash', $ctx);
        }
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
