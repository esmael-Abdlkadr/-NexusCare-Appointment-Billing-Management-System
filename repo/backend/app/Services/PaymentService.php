<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\RefundOrder;
use App\Models\User;
use App\Repositories\FeeAssessmentRepository;
use App\Repositories\LedgerEntryRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\RefundOrderRepository;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly FeeAssessmentRepository $feeAssessmentRepository,
        private readonly RefundOrderRepository $refundOrderRepository,
        private readonly LedgerEntryRepository $ledgerEntryRepository,
    ) {
    }

    public function postPayment(array $data, User $actor): array
    {
        if ($this->paymentRepository->existsByReferenceId($data['reference_id'])) {
            Log::channel('billing')->info('Payment post rejected', [
                'event' => 'payment_failed',
                'reason' => 'duplicate_reference_id',
                'reference_id' => $data['reference_id'],
                'actor_id' => $actor->id,
            ]);

            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'status' => 422,
                'data' => ['reference_id' => ['The reference_id has already been taken.']],
            ];
        }

        $assessment = null;
        if (! empty($data['fee_assessment_id'])) {
            $assessment = $this->feeAssessmentRepository->findByIdWithoutActorScope((int) $data['fee_assessment_id']);

            $assessmentSiteId = (int) ($assessment?->site_id ?? $assessment?->appointment?->site_id ?? $assessment?->client?->site_id ?? 0);
            if ($assessment && $assessmentSiteId !== (int) $actor->site_id) {
                return [
                    'success' => false,
                    'error' => 'FORBIDDEN',
                    'status' => 403,
                    'data' => ['fee_assessment_id' => ['The fee assessment does not belong to your site.']],
                ];
            }
        }

        if (! empty($data['client_id']) && ! $this->validateClientId((int) $data['client_id'], $actor)) {
            return [
                'success' => false,
                'error' => 'FORBIDDEN',
                'status' => 403,
                'data' => ['client_id' => ['The client does not belong to your site.']],
            ];
        }

        $payment = $this->paymentRepository->create([
            'reference_id' => $data['reference_id'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'fee_assessment_id' => $data['fee_assessment_id'] ?? null,
            'posted_by' => $actor->id,
            'site_id' => $actor->site_id,
            'notes' => $data['notes'] ?? null,
            'batch_file_path' => $data['batch_file_path'] ?? null,
            'batch_row_count' => $data['batch_row_count'] ?? null,
            'created_at' => now(),
        ]);

        if ($assessment) {
            $assessment->status = 'paid';
            $this->feeAssessmentRepository->save($assessment);
        }

        $clientId = $this->resolveClientId($payment, isset($data['client_id']) ? (int) $data['client_id'] : null);

        Log::channel('billing')->info('Payment posted', [
            'event' => 'payment_posted',
            'amount' => (float) $payment->amount,
            'client_id' => $clientId,
            'payment_id' => $payment->id,
            'reference_id' => $payment->reference_id,
        ]);

        $this->ledgerEntryRepository->create([
            'entry_type' => 'payment',
            'amount' => $payment->amount,
            'reference_id' => $payment->reference_id,
            'client_id' => $clientId,
            'site_id' => (int) $payment->site_id,
            'description' => 'Offline payment posted',
            'created_at' => now(),
        ]);

        $auditPayload = ['reference_id' => $payment->reference_id, 'amount' => $payment->amount, 'method' => $payment->method];
        if ($payment->batch_file_path) {
            $auditPayload['batch_file'] = basename($payment->batch_file_path);
            $auditPayload['batch_row_count'] = $payment->batch_row_count;
        }

        AuditLogger::write(
            $actor->id,
            'POST_PAYMENT',
            Payment::class,
            $payment->id,
            $auditPayload,
            request()?->ip(),
        );

        return [
            'success' => true,
            'status' => 201,
            'data' => ['payment' => $payment->fresh(['feeAssessment', 'postedBy'])],
        ];
    }

    public function createRefundOrder(array $data, User $actor): array
    {
        $payment = $this->paymentRepository->findById((int) $data['payment_id']);

        if (! $payment) {
            return [
                'success' => false,
                'error' => 'NOT_FOUND',
                'status' => 404,
                'data' => [],
            ];
        }

        if ((float) $data['amount'] > (float) $payment->amount) {
            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'status' => 422,
                'data' => ['amount' => ['Refund amount cannot exceed original payment amount.']],
            ];
        }

        $refund = $this->refundOrderRepository->create([
            'payment_id' => $payment->id,
            'client_id' => $this->resolveClientId($payment),
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'status' => 'pending',
            'requested_by' => $actor->id,
            'site_id' => $payment->site_id,
        ]);

        $this->ledgerEntryRepository->create([
            'entry_type' => 'refund',
            'amount' => $refund->amount,
            'reference_id' => 'REF-'.$refund->id,
            'client_id' => $refund->client_id,
            'site_id' => (int) $refund->site_id,
            'description' => 'Refund request created',
            'created_at' => now(),
        ]);

        AuditLogger::write(
            $actor->id,
            'REQUEST_REFUND',
            RefundOrder::class,
            $refund->id,
            ['payment_id' => $payment->id, 'amount' => $refund->amount],
            request()?->ip(),
        );

        return [
            'success' => true,
            'status' => 201,
            'data' => ['refund_order' => $refund->fresh(['payment', 'client', 'requestedBy'])],
        ];
    }

    public function approveRefund(RefundOrder $refundOrder, array $data, User $actor): array
    {
        if ($refundOrder->status !== 'pending') {
            return [
                'success' => false,
                'error' => 'INVALID_REFUND_STATE',
                'status' => 422,
                'data' => [],
            ];
        }

        $decision = $data['decision'];

        if ($decision === 'approved') {
            $refundOrder->status = 'processed';

            $this->ledgerEntryRepository->create([
                'entry_type' => 'refund',
                'amount' => $refundOrder->amount,
                'reference_id' => 'REF-PROC-'.$refundOrder->id,
                'client_id' => $refundOrder->client_id,
                'site_id' => (int) $refundOrder->site_id,
                'description' => 'Refund processed',
                'created_at' => now(),
            ]);
        } else {
            $refundOrder->status = 'rejected';
        }

        $refundOrder->approved_by = $actor->id;
        $refundOrder->reviewer_note = $data['note'] ?? null;

        $this->refundOrderRepository->save($refundOrder);

        AuditLogger::write(
            $actor->id,
            'APPROVE_REFUND',
            RefundOrder::class,
            $refundOrder->id,
            ['decision' => $decision, 'note' => $data['note'] ?? null],
            request()?->ip(),
        );

        return [
            'success' => true,
            'status' => 200,
            'data' => ['refund_order' => $refundOrder->fresh(['payment', 'client', 'requestedBy', 'approvedBy'])],
        ];
    }

    private function resolveClientId(Payment $payment, ?int $explicitClientId = null): int
    {
        if ($explicitClientId) {
            return $explicitClientId;
        }

        $assessment = $payment->feeAssessment;

        if ($assessment) {
            return (int) $assessment->client_id;
        }

        Log::warning('Payment ledger entry has no client_id', [
            'payment_id' => $payment->id,
            'posted_by' => $payment->posted_by,
        ]);

        return (int) $payment->posted_by;
    }

    public function validateClientId(?int $clientId, User $actor): bool
    {
        if (! $clientId) {
            return true;
        }

        $client = User::withoutGlobalScopes()->find($clientId);

        return $client && (int) $client->site_id === (int) $actor->site_id;
    }
}
