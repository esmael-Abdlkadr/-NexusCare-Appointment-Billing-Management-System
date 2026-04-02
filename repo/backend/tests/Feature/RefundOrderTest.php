<?php

namespace Tests\Feature;

use App\Models\FeeAssessment;
use App\Models\Payment;
use App\Models\RefundOrder;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class RefundOrderTest extends TestCase
{
    use RefreshDatabase;

    private int $paymentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestFixtureSeeder::class);

        $clientId = (int) User::withoutGlobalScopes()->where('identifier', 'client001')->value('id');
        $staffId = (int) User::withoutGlobalScopes()->where('identifier', 'staff001')->value('id');

        $fee = FeeAssessment::withoutGlobalScopes()->create([
            'appointment_id' => null,
            'client_id' => $clientId,
            'fee_type' => 'no_show',
            'amount' => 30.00,
            'status' => 'paid',
            'assessed_at' => now()->subDay(),
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $payment = Payment::withoutGlobalScopes()->create([
            'reference_id' => 'RFND-BASE-001',
            'amount' => 30.00,
            'method' => 'cash',
            'fee_assessment_id' => $fee->id,
            'posted_by' => $staffId,
            'site_id' => 1,
            'notes' => 'refund base payment',
            'created_at' => now(),
        ]);

        $this->paymentId = (int) $payment->id;
    }

    public function test_staff_can_list_refund_orders(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/refund-orders')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_reviewer_can_list_refund_orders(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/refund-orders')
            ->assertOk();
    }

    public function test_unauthenticated_cannot_list(): void
    {
        $this->getJson('/api/refund-orders')->assertStatus(401);
    }

    public function test_staff_can_create_refund_order(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/refund-orders', [
                'payment_id' => $this->paymentId,
                'amount' => 10.00,
                'reason' => 'Client overpaid',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('refund_orders', ['payment_id' => $this->paymentId]);
    }

    public function test_create_refund_order_missing_reason(): void
    {
        $token = $this->token('staff001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/refund-orders', [
                'payment_id' => $this->paymentId,
                'amount' => 10.00,
            ])
            ->assertStatus(422);
    }

    public function test_reviewer_cannot_create_refund_order(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/refund-orders', [
                'payment_id' => $this->paymentId,
                'amount' => 10.00,
                'reason' => 'Client overpaid',
            ])
            ->assertStatus(403);
    }

    public function test_reviewer_can_approve_refund_order(): void
    {
        $staffToken = $this->token('staff001');
        $reviewerToken = $this->token('reviewer001');

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->postJson('/api/refund-orders', [
                'payment_id' => $this->paymentId,
                'amount' => 10.00,
                'reason' => 'Client overpaid',
            ])
            ->assertStatus(201);

        $orderId = (int) $createResponse->json('data.refund_order.id');

        $this->withHeader('Authorization', 'Bearer '.$reviewerToken)
            ->patchJson('/api/refund-orders/'.$orderId.'/approve', [
                'decision' => 'approved',
                'note' => 'Approved by reviewer',
            ])
            ->assertOk();

        $this->assertDatabaseHas('refund_orders', ['id' => $orderId, 'status' => 'processed']);
    }

    public function test_staff_cannot_approve_refund_order(): void
    {
        $staffToken = $this->token('staff001');
        $reviewerToken = $this->token('reviewer001');

        $order = $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->postJson('/api/refund-orders', [
                'payment_id' => $this->paymentId,
                'amount' => 10.00,
                'reason' => 'Client overpaid',
            ])->assertStatus(201);

        $orderId = (int) $order->json('data.refund_order.id');

        $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->patchJson('/api/refund-orders/'.$orderId.'/approve', [
                'decision' => 'approved',
                'note' => 'No permission',
            ])
            ->assertStatus(403);

        // make sure order still pending and approve path itself is valid for reviewer.
        $this->withHeader('Authorization', 'Bearer '.$reviewerToken)
            ->patchJson('/api/refund-orders/'.$orderId.'/approve', [
                'decision' => 'rejected',
                'note' => 'Reviewed',
            ])
            ->assertOk();
    }

    public function test_approve_nonexistent_refund_order_returns_404(): void
    {
        $token = $this->token('reviewer001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/refund-orders/999999/approve', [
                'decision' => 'approved',
                'note' => 'Reviewed',
            ])
            ->assertStatus(404);
    }

    public function test_approving_already_processed_refund_order_is_rejected(): void
    {
        $staffToken = $this->token('staff001');
        $reviewerToken = $this->token('reviewer001');

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->postJson('/api/refund-orders', [
                'payment_id' => $this->paymentId,
                'amount' => 10.00,
                'reason' => 'Client overpaid',
            ])
            ->assertStatus(201);

        $orderId = (int) $createResponse->json('data.refund_order.id');

        $this->withHeader('Authorization', 'Bearer '.$reviewerToken)
            ->patchJson('/api/refund-orders/'.$orderId.'/approve', [
                'decision' => 'approved',
                'note' => 'Initial approval',
            ])
            ->assertOk();

        $this->assertDatabaseHas('refund_orders', ['id' => $orderId, 'status' => 'processed']);

        $status = $this->withHeader('Authorization', 'Bearer '.$reviewerToken)
            ->patchJson('/api/refund-orders/'.$orderId.'/approve', [
                'decision' => 'approved',
                'note' => 'Duplicate approval attempt',
            ])
            ->status();

        $this->assertContains($status, [409, 422]);
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
