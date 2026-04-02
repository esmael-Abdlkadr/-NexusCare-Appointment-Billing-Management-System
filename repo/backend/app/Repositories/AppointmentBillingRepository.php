<?php

namespace App\Repositories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Collection;

class AppointmentBillingRepository
{
    public function dueForNoShowAssessment(int $graceMinutes): Collection
    {
        return Appointment::withoutGlobalScopes()
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('assessed_no_show', false)
            ->where('start_time', '<', now()->subMinutes($graceMinutes))
            ->get();
    }

    public function save(Appointment $appointment): Appointment
    {
        $appointment->save();
        return $appointment;
    }
}
