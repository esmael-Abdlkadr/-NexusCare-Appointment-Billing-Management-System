<?php

namespace App\Repositories;

use App\Models\Appointment;
use App\Models\WaitlistEntry;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class WaitlistRepository
{
    public function listBySite(?int $siteId, ?int $departmentId = null, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = WaitlistEntry::query()->orderBy('priority')->orderBy('created_at');

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function create(array $data): WaitlistEntry
    {
        return WaitlistEntry::query()->create($data);
    }

    public function findById(int $id): ?WaitlistEntry
    {
        return WaitlistEntry::query()->find($id);
    }

    public function topMatchingForBackfill(Appointment $appointment, CarbonInterface $slotStart, CarbonInterface $slotEnd): ?WaitlistEntry
    {
        return WaitlistEntry::withoutGlobalScopes()
            ->where('status', 'waiting')
            ->where('site_id', $appointment->site_id)
            ->where('service_type', $appointment->service_type)
            ->where('preferred_start', '<=', $slotStart)
            ->where('preferred_end', '>=', $slotEnd)
            ->orderBy('priority')
            ->orderBy('created_at')
            ->first();
    }

    public function save(WaitlistEntry $entry): WaitlistEntry
    {
        $entry->save();
        return $entry;
    }

    public function delete(WaitlistEntry $entry): void
    {
        $entry->delete();
    }
}
