<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->role === 'administrator') {
            return true;
        }

        if (! in_array($user->role, ['staff', 'reviewer'], true)) {
            return false;
        }

        if ($user->role === 'staff') {
            return $appointment->site_id === $user->site_id
                && (int) $appointment->department_id === (int) $user->department_id;
        }

        return $appointment->site_id === $user->site_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['staff', 'administrator'], true);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        if (! in_array($user->role, ['staff', 'administrator'], true)) {
            return false;
        }

        if ($user->role === 'administrator') {
            return true;
        }

        return $appointment->site_id === $user->site_id
            && (int) $appointment->department_id === (int) $user->department_id;
    }

    public function delete(User $user): bool
    {
        return $user->role === 'administrator';
    }
}
