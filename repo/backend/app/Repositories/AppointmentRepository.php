<?php

namespace App\Repositories;

use App\Models\Appointment;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AppointmentRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Appointment::query()->with(['client', 'provider', 'resource']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['provider_id'])) {
            $query->where('provider_id', (int) $filters['provider_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('start_time', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('end_time', '<=', $filters['date_to']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        return $query->orderBy('start_time')->paginate($perPage);
    }

    public function findById(int $id): ?Appointment
    {
        return Appointment::query()->with(['client', 'provider', 'resource'])->find($id);
    }

    public function findByIdWithoutScope(int $id): ?Appointment
    {
        return Appointment::withoutGlobalScopes()->with(['client', 'provider', 'resource'])->find($id);
    }

    public function create(array $data): Appointment
    {
        return Appointment::query()->create($data);
    }

    public function save(Appointment $appointment): Appointment
    {
        $appointment->save();
        return $appointment;
    }

    public function overlappingConflicts(
        int $providerId,
        int $resourceId,
        CarbonInterface $startTime,
        CarbonInterface $endTime,
        ?int $siteId = null,
        ?int $excludeAppointmentId = null,
    ): Collection {
        $query = Appointment::withoutGlobalScopes()
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->where(function ($q) use ($providerId, $resourceId): void {
                $q->where('provider_id', $providerId)
                    ->orWhere('resource_id', $resourceId);
            })
            ->where(function ($q) use ($startTime, $endTime): void {
                $q->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            });

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        if ($excludeAppointmentId !== null) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->orderBy('start_time')->get();
    }

    public function confirmedOrCheckedInForWindow(
        int $providerId,
        int $resourceId,
        CarbonInterface $startTime,
        CarbonInterface $endTime,
        ?int $siteId = null,
    ): bool
    {
        $query = Appointment::withoutGlobalScopes()
            ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_CHECKED_IN])
            ->where(function ($query) use ($providerId, $resourceId): void {
                $query->where('provider_id', $providerId)
                    ->orWhere('resource_id', $resourceId);
            })
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->exists();
    }
}
