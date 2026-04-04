<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Repositories\WaitlistRepository;
use App\Support\AuditLogger;
use Illuminate\Pagination\LengthAwarePaginator;

class WaitlistService
{
    public function __construct(
        private readonly WaitlistRepository $waitlistRepository,
        private readonly AppointmentService $appointmentService,
    ) {
    }

    public function listForSite(?int $siteId, ?int $departmentId = null, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        return $this->waitlistRepository->listBySite($siteId, $departmentId, $perPage, $page);
    }

    public function getEntry(int $id): ?WaitlistEntry
    {
        return $this->waitlistRepository->findById($id);
    }

    public function addToWaitlist(array $data, User $actor): WaitlistEntry
    {
        $entry = $this->waitlistRepository->create([
            'client_id' => (int) $data['client_id'],
            'service_type' => $data['service_type'],
            'priority' => (int) $data['priority'],
            'preferred_start' => $data['preferred_start'],
            'preferred_end' => $data['preferred_end'],
            'status' => 'waiting',
            'site_id' => (int) $data['site_id'],
            'department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null,
        ]);

        AuditLogger::write(
            $actor->id,
            'WAITLIST_ADD',
            WaitlistEntry::class,
            $entry->id,
            ['priority' => $entry->priority, 'service_type' => $entry->service_type],
            request()->ip(),
        );

        return $entry;
    }

    public function proposeBackfill(Appointment $cancelledAppt, User $actor): ?WaitlistEntry
    {
        $entry = $this->waitlistRepository->topMatchingForBackfill(
            $cancelledAppt,
            $cancelledAppt->start_time,
            $cancelledAppt->end_time,
        );

        if (! $entry) {
            return null;
        }

        $entry->status = 'proposed';
        $this->waitlistRepository->save($entry);

        AuditLogger::write(
            $actor->id,
            'BACKFILL_PROPOSED',
            WaitlistEntry::class,
            $entry->id,
            [
                'appointment_id' => $cancelledAppt->id,
                'service_type' => $entry->service_type,
            ],
            request()->ip(),
        );

        return $entry;
    }

    public function confirmBackfill(int $waitlistId, array $slotData, User $actor): array
    {
        $entry = $this->waitlistRepository->findById($waitlistId);

        if (! $entry || $entry->status !== 'proposed') {
            return [
                'success' => false,
                'error' => 'WAITLIST_NOT_PROPOSED',
                'data' => [],
                'status' => 422,
            ];
        }

        if ($actor->role === 'staff' && $entry->department_id !== null && (int) $entry->department_id !== (int) $actor->department_id) {
            return [
                'success' => false,
                'error' => 'FORBIDDEN',
                'data' => [],
                'status' => 403,
            ];
        }

        $result = $this->appointmentService->createAppointment([
            'client_id' => $entry->client_id,
            'provider_id' => (int) $slotData['provider_id'],
            'resource_id' => (int) $slotData['resource_id'],
            'service_type' => $entry->service_type,
            'start_time' => $slotData['start_time'],
            'end_time' => $slotData['end_time'],
            'department_id' => (int) $slotData['department_id'],
            'site_id' => $entry->site_id,
        ], $actor);

        if (! ($result['success'] ?? false)) {
            return $result;
        }

        $entry->status = 'booked';
        $this->waitlistRepository->save($entry);

        AuditLogger::write(
            $actor->id,
            'BACKFILL_CONFIRMED',
            WaitlistEntry::class,
            $entry->id,
            ['appointment_id' => $result['data']['appointment']['id'] ?? null],
            request()->ip(),
        );

        return [
            'success' => true,
            'data' => [
                'waitlist' => $entry,
                'appointment' => $result['data']['appointment'],
            ],
        ];
    }

    public function removeWaitingEntry(int $waitlistId, User $actor): array
    {
        $entry = $this->waitlistRepository->findById($waitlistId);

        if (! $entry) {
            return [
                'success' => false,
                'error' => 'NOT_FOUND',
                'data' => [],
                'status' => 404,
            ];
        }

        if ($actor->role === 'staff' && $entry->department_id !== null && (int) $entry->department_id !== (int) $actor->department_id) {
            return [
                'success' => false,
                'error' => 'FORBIDDEN',
                'data' => [],
                'status' => 403,
            ];
        }

        if ($entry->status !== 'waiting') {
            return [
                'success' => false,
                'error' => 'WAITLIST_NOT_REMOVABLE',
                'data' => [],
                'status' => 422,
            ];
        }

        $this->waitlistRepository->delete($entry);

        AuditLogger::write(
            $actor->id,
            'WAITLIST_REMOVE',
            WaitlistEntry::class,
            $entry->id,
            ['service_type' => $entry->service_type],
            request()->ip(),
        );

        return [
            'success' => true,
            'data' => ['message' => 'Waitlist entry removed'],
            'status' => 200,
        ];
    }
}
