<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\FeeAssessment;
use App\Models\User;
use App\Repositories\FeeAssessmentRepository;
use App\Repositories\FeeRuleRepository;
use App\Repositories\LedgerEntryRepository;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class FeeService
{
    public function __construct(
        private readonly FeeRuleRepository $feeRuleRepository,
        private readonly FeeAssessmentRepository $feeAssessmentRepository,
        private readonly LedgerEntryRepository $ledgerEntryRepository,
    ) {
    }

    /**
     * Returns true if the appointment's grace period has elapsed and a no-show fee
     * may be assessed. If no active fee rule exists, defaults to true (no grace configured).
     */
    public function isGracePeriodElapsed(Appointment $appointment): bool
    {
        $rule = $this->feeRuleRepository->findActiveByTypeAndSite('no_show', (int) $appointment->site_id);

        if (! $rule) {
            return true;
        }

        $graceMinutes = (int) ($rule->grace_minutes ?? 0);

        return Carbon::now()->gte(
            Carbon::parse($appointment->start_time)->addMinutes($graceMinutes)
        );
    }

    public function assessNoShowFee(Appointment $appointment): ?FeeAssessment
    {
        $existing = $this->feeAssessmentRepository->findByAppointmentAndType($appointment->id, 'no_show');
        if ($existing) {
            return $existing;
        }

        $rule = $this->feeRuleRepository->findActiveByTypeAndSite('no_show', (int) $appointment->site_id);

        if (! $rule) {
            return null;
        }

        $assessment = $this->feeAssessmentRepository->create([
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'fee_type' => 'no_show',
            'amount' => $rule->amount,
            'status' => 'pending',
            'assessed_at' => now(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
        ]);

        $this->ledgerEntryRepository->create([
            'entry_type' => 'fee',
            'amount' => $assessment->amount,
            'reference_id' => 'FEE-'.$assessment->id,
            'client_id' => $assessment->client_id,
            'site_id' => $appointment->site_id,
            'description' => 'No-show fee assessed',
            'created_at' => now(),
        ]);

        AuditLogger::write(
            null,
            'ASSESS_NO_SHOW_FEE',
            FeeAssessment::class,
            $assessment->id,
            ['appointment_id' => $appointment->id, 'amount' => $assessment->amount],
            request()?->ip(),
        );

        return $assessment;
    }

    public function assessOverdueFines(): int
    {
        $pending = $this->feeAssessmentRepository->pendingOverdue();
        $created = 0;

        foreach ($pending as $assessment) {
            $siteId = (int) ($assessment->appointment?->site_id ?? $assessment->client?->site_id ?? 0);
            if (! $siteId) {
                continue;
            }

            $rule = $this->feeRuleRepository->findActiveByTypeAndSite('overdue', $siteId);

            if (! $rule || ! $rule->rate || ! $rule->period_days || $rule->period_days <= 0) {
                continue;
            }

            $days = Carbon::parse($assessment->due_date)->diffInDays(now());
            $periods = (int) floor($days / (int) $rule->period_days);

            if ($periods <= 0) {
                continue;
            }

            $fineAmount = round((float) $assessment->amount * (float) $rule->rate * $periods, 2);

            if ($fineAmount <= 0) {
                continue;
            }

            $fine = $this->feeAssessmentRepository->create([
                'appointment_id' => $assessment->appointment_id,
                'client_id' => $assessment->client_id,
                'fee_type' => 'overdue',
                'amount' => $fineAmount,
                'status' => 'pending',
                'assessed_at' => now(),
                'due_date' => now()->addDays((int) ($rule->period_days ?? 30))->toDateString(),
            ]);

            $this->ledgerEntryRepository->create([
                'entry_type' => 'fine',
                'amount' => $fineAmount,
                'reference_id' => 'FINE-'.$fine->id,
                'client_id' => $fine->client_id,
                'site_id' => $siteId,
                'description' => 'Overdue fine assessed',
                'created_at' => now(),
            ]);

            AuditLogger::write(
                null,
                'ASSESS_OVERDUE_FINE',
                FeeAssessment::class,
                $fine->id,
                ['base_assessment_id' => $assessment->id, 'amount' => $fineAmount, 'periods' => $periods],
                request()?->ip(),
            );

            $created++;
        }

        return $created;
    }

    public function assessLostDamagedFee(
        int $clientId,
        int $siteId,
        float $amount,
        ?string $notes,
        User $actor,
    ): FeeAssessment {
        $assessmentData = [
            'client_id' => $clientId,
            'fee_type' => 'lost_damaged',
            'amount' => $amount,
            'status' => 'pending',
            'assessed_at' => now(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
        ];

        if (Schema::hasColumn('fee_assessments', 'notes')) {
            $assessmentData['notes'] = $notes;
        }

        $assessment = $this->feeAssessmentRepository->create($assessmentData);

        $this->ledgerEntryRepository->create([
            'entry_type' => 'fee',
            'amount' => $amount,
            'reference_id' => 'FEE-'.$assessment->id,
            'client_id' => $clientId,
            'site_id' => $siteId,
            'description' => 'Lost/damaged fee assessed',
            'created_at' => now(),
        ]);

        AuditLogger::write(
            $actor->id,
            'ASSESS_LOST_DAMAGED_FEE',
            FeeAssessment::class,
            $assessment->id,
            ['client_id' => $clientId, 'amount' => $amount, 'notes' => $notes],
            request()?->ip(),
        );

        return $assessment;
    }

    public function calculateOverdueFine(float $amount, int $days): float
    {
        if ($days <= 0 || $amount <= 0) {
            return 0.0;
        }

        $periods = (int) floor($days / 30);
        if ($periods <= 0) {
            return 0.0;
        }

        return round($amount * 0.015 * $periods, 2);
    }

    public function approveWaiver(FeeAssessment $fee, User $reviewer, string $type, string $note): array
    {
        if (! in_array($type, ['waived', 'written_off'], true)) {
            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'status' => 422,
                'data' => ['waiver_type' => ['The waiver_type must be waived or written_off.']],
            ];
        }

        $siteId = (int) ($fee->appointment?->site_id ?? $fee->client?->site_id ?? 0);

        if ($reviewer->role !== 'administrator' && (int) $reviewer->site_id !== $siteId) {
            return [
                'success' => false,
                'error' => 'WAIVER_NOT_PERMITTED',
                'status' => 403,
                'data' => [],
            ];
        }

        $fee->status = $type;
        $fee->waiver_by = $reviewer->id;
        $fee->waiver_note = $note;
        $this->feeAssessmentRepository->save($fee);

        $this->ledgerEntryRepository->create([
            'entry_type' => $type === 'waived' ? 'waiver' : 'writeoff',
            'amount' => $fee->amount,
            'reference_id' => 'FEE-'.$fee->id,
            'client_id' => $fee->client_id,
            'site_id' => $siteId,
            'description' => $type === 'waived' ? 'Fee waived' : 'Fee written off',
            'created_at' => now(),
        ]);

        AuditLogger::write(
            $reviewer->id,
            'APPROVE_WAIVER',
            FeeAssessment::class,
            $fee->id,
            ['type' => $type, 'note' => $note],
            request()?->ip(),
        );

        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'fee_assessment' => $fee->fresh(['client', 'appointment', 'waiverBy']),
            ],
        ];
    }
}
