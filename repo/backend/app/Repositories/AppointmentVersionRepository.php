<?php

namespace App\Repositories;

use App\Models\Appointment;
use App\Models\AppointmentVersion;
use Illuminate\Database\Eloquent\Collection;

class AppointmentVersionRepository
{
    public function createSnapshot(Appointment $appointment, int $changedBy): AppointmentVersion
    {
        return AppointmentVersion::query()->create([
            'appointment_id' => $appointment->id,
            'snapshot' => $appointment->fresh()->toArray(),
            'changed_by' => $changedBy,
            'created_at' => now(),
        ]);
    }

    public function listByAppointmentId(int $appointmentId): Collection
    {
        return AppointmentVersion::query()
            ->where('appointment_id', $appointmentId)
            ->orderBy('id')
            ->get();
    }
}
