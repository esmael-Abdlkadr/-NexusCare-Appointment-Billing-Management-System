<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Department;
use App\Models\Resource;
use App\Models\User;
use App\Repositories\AppointmentRepository;
use App\Repositories\AppointmentVersionRepository;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AppointmentService
{
    private const TRANSITIONS = [
        Appointment::STATUS_REQUESTED => [Appointment::STATUS_CONFIRMED],
        Appointment::STATUS_CONFIRMED => [Appointment::STATUS_CHECKED_IN, Appointment::STATUS_NO_SHOW, Appointment::STATUS_CANCELLED],
        Appointment::STATUS_CHECKED_IN => [Appointment::STATUS_COMPLETED, Appointment::STATUS_CANCELLED],
        Appointment::STATUS_NO_SHOW => [],
        Appointment::STATUS_COMPLETED => [],
        Appointment::STATUS_CANCELLED => [],
    ];

    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AppointmentVersionRepository $appointmentVersionRepository,
        private readonly FeeService $feeService,
    ) {
    }

    public function createAppointment(array $data, User $actor): array
    {
        $start = Carbon::parse($data['start_time']);
        $end = Carbon::parse($data['end_time']);

        if (! $start->lt($end)) {
            return [
                'success' => false,
                'error' => 'INVALID_TIME_RANGE',
                'data' => [],
                'status' => 422,
            ];
        }

        $siteId = (int) ($data['site_id'] ?? $actor->site_id ?? 0);
        $providerId = (int) $data['provider_id'];
        $resourceId = (int) $data['resource_id'];

        // Defensive site-scope invariants: block cross-site mismatch even if
        // controller validation is bypassed (e.g., via direct service call).
        if (! User::withoutGlobalScopes()->where('id', $providerId)->where('site_id', $siteId)->exists()) {
            return [
                'success' => false,
                'error' => 'CROSS_SITE_MISMATCH',
                'data' => ['field' => 'provider_id'],
                'status' => 422,
            ];
        }

        if (! Resource::withoutGlobalScopes()->where('id', $resourceId)->where('site_id', $siteId)->exists()) {
            return [
                'success' => false,
                'error' => 'CROSS_SITE_MISMATCH',
                'data' => ['field' => 'resource_id'],
                'status' => 422,
            ];
        }

        $departmentId = (int) $data['department_id'];
        if (! Department::withoutGlobalScopes()->where('id', $departmentId)->where('site_id', $siteId)->exists()) {
            return [
                'success' => false,
                'error' => 'CROSS_SITE_MISMATCH',
                'data' => ['field' => 'department_id'],
                'status' => 422,
            ];
        }

        $conflicts = $this->appointmentRepository->overlappingConflicts(
            $providerId,
            $resourceId,
            $start,
            $end,
            $siteId,
        );

        if ($conflicts->isNotEmpty()) {
            $conflict = $conflicts->first();
            $conflictType = $conflict->provider_id === $providerId ? 'provider' : 'resource';

            return [
                'success' => false,
                'error' => 'APPOINTMENT_CONFLICT',
                'data' => [
                    'conflict_type' => $conflictType,
                    'next_available_slots' => $this->getNextAvailableSlots($providerId, $resourceId, $end, 3, $siteId),
                ],
                'status' => 409,
            ];
        }

        $appointment = $this->appointmentRepository->create([
            'client_id' => (int) $data['client_id'],
            'provider_id' => $providerId,
            'resource_id' => $resourceId,
            'service_type' => $data['service_type'],
            'start_time' => $start,
            'end_time' => $end,
            'status' => Appointment::STATUS_REQUESTED,
            'site_id' => $siteId,
            'department_id' => $departmentId,
        ]);

        $this->appointmentVersionRepository->createSnapshot($appointment, $actor->id);

        AuditLogger::write(
            $actor->id,
            'CREATE_APPOINTMENT',
            Appointment::class,
            $appointment->id,
            [
                'status' => $appointment->status,
                'start_time' => $appointment->start_time->toIso8601String(),
                'end_time' => $appointment->end_time->toIso8601String(),
            ],
            request()->ip(),
        );

        return [
            'success' => true,
            'data' => [
                'appointment' => $appointment->fresh(['client', 'provider', 'resource']),
            ],
            'status' => 201,
        ];
    }

    public function rescheduleAppointment(Appointment $appointment, array $data, User $actor, ?string $reason = null): array
    {
        $start = Carbon::parse($data['start_time']);
        $end = Carbon::parse($data['end_time']);

        if (! $start->lt($end)) {
            return [
                'success' => false,
                'error' => 'INVALID_TIME_RANGE',
                'data' => [],
                'status' => 422,
            ];
        }

        $providerId = (int) ($data['provider_id'] ?? $appointment->provider_id);
        $resourceId = (int) ($data['resource_id'] ?? $appointment->resource_id);
        $siteId = (int) $appointment->site_id;

        $conflicts = $this->appointmentRepository->overlappingConflicts(
            $providerId,
            $resourceId,
            $start,
            $end,
            $siteId,
            $appointment->id,
        );

        if ($conflicts->isNotEmpty()) {
            $conflict = $conflicts->first();
            $conflictType = $conflict->provider_id === $providerId ? 'provider' : 'resource';

            return [
                'success' => false,
                'error' => 'APPOINTMENT_CONFLICT',
                'data' => [
                    'conflict_type' => $conflictType,
                    'next_available_slots' => $this->getNextAvailableSlots($providerId, $resourceId, $end, 3, $siteId),
                ],
                'status' => 409,
            ];
        }

        $appointment->fill([
            'start_time' => $start,
            'end_time' => $end,
            'provider_id' => $providerId,
            'resource_id' => $resourceId,
            'reschedule_reason' => $reason,
        ]);

        $this->appointmentRepository->save($appointment);
        $this->appointmentVersionRepository->createSnapshot($appointment, $actor->id);

        AuditLogger::write(
            $actor->id,
            'RESCHEDULE_APPOINTMENT',
            Appointment::class,
            $appointment->id,
            [
                'start_time' => $appointment->start_time->toIso8601String(),
                'end_time' => $appointment->end_time->toIso8601String(),
                'reschedule_reason' => $reason,
            ],
            request()->ip(),
        );

        return [
            'success' => true,
            'data' => [
                'appointment' => $appointment->fresh(['client', 'provider', 'resource']),
            ],
            'status' => 200,
        ];
    }

    public function transitionStatus(Appointment $appointment, string $newStatus, User $actor, ?string $reason = null): array
    {
        $current = $appointment->status;
        $allowed = self::TRANSITIONS[$current] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            return [
                'success' => false,
                'error' => 'INVALID_TRANSITION',
                'data' => [
                    'from' => $current,
                    'to' => $newStatus,
                ],
                'status' => 422,
            ];
        }

        if (! $this->canTransition($actor, $newStatus)) {
            return [
                'success' => false,
                'error' => 'FORBIDDEN',
                'data' => [],
                'status' => 403,
            ];
        }

        if ($newStatus === Appointment::STATUS_CANCELLED && ! $reason) {
            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'data' => ['cancel_reason' => ['The cancel_reason field is required when cancelling.']],
                'status' => 422,
            ];
        }

        $appointment->status = $newStatus;
        $appointment->cancel_reason = $newStatus === Appointment::STATUS_CANCELLED ? $reason : null;

        if ($newStatus === Appointment::STATUS_NO_SHOW) {
            $appointment->assessed_no_show = true;
        }

        $this->appointmentRepository->save($appointment);

        $this->appointmentVersionRepository->createSnapshot($appointment, $actor->id);

        if ($newStatus === Appointment::STATUS_NO_SHOW && $this->feeService->isGracePeriodElapsed($appointment)) {
            $this->feeService->assessNoShowFee($appointment);
        }

        if ($newStatus === Appointment::STATUS_CANCELLED) {
            app(WaitlistService::class)->proposeBackfill($appointment, $actor);
        }

        AuditLogger::write(
            $actor->id,
            'TRANSITION_APPOINTMENT',
            Appointment::class,
            $appointment->id,
            [
                'from' => $current,
                'to' => $newStatus,
                'reason' => $reason,
            ],
            request()->ip(),
        );

        return [
            'success' => true,
            'data' => [
                'appointment' => $appointment->fresh(['client', 'provider', 'resource']),
            ],
            'status' => 200,
        ];
    }

    public function getNextAvailableSlots(
        int $providerId,
        int $resourceId,
        CarbonInterface|string $after,
        int $count = 3,
        ?int $siteId = null,
    ): array {
        $cursor = $after instanceof CarbonInterface ? $after->copy() : Carbon::parse($after);
        $cursor = $cursor->second(0);

        $slots = [];
        $attempts = 0;

        while (count($slots) < $count && $attempts < 96) {
            $slotStart = $cursor->copy();
            $slotEnd = $slotStart->copy()->addMinutes(30);

            $isBlocked = $this->appointmentRepository->confirmedOrCheckedInForWindow(
                $providerId,
                $resourceId,
                $slotStart,
                $slotEnd,
                $siteId,
            );

            if (! $isBlocked) {
                $slots[] = [
                    'start_time' => $slotStart->toIso8601String(),
                    'end_time' => $slotEnd->toIso8601String(),
                ];
            }

            $cursor = $cursor->addMinutes(30);
            $attempts++;
        }

        return $slots;
    }

    private function canTransition(User $actor, string $newStatus): bool
    {
        if ($actor->role === 'administrator') {
            return true;
        }

        if ($actor->role !== 'staff') {
            return false;
        }

        return in_array($newStatus, [
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_NO_SHOW,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_CANCELLED,
        ], true);
    }
}
