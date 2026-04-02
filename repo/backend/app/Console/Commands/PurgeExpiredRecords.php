<?php

namespace App\Console\Commands;

use App\Models\Resource;
use App\Models\Appointment;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\AuditLogger;
use Illuminate\Console\Command;

class PurgeExpiredRecords extends Command
{
    protected $signature = 'records:purge';
    protected $description = 'Purge soft-deleted records older than 24 months';

    public function handle(): int
    {
        $threshold = now()->subMonths(24);

        $users = User::withoutGlobalScopes()->onlyTrashed()->where('deleted_at', '<', $threshold)->get();
        $appointments = Appointment::withoutGlobalScopes()->onlyTrashed()->where('deleted_at', '<', $threshold)->get();
        $resources = Resource::withoutGlobalScopes()->onlyTrashed()->where('deleted_at', '<', $threshold)->get();
        $waitlist = WaitlistEntry::withoutGlobalScopes()->onlyTrashed()->where('deleted_at', '<', $threshold)->get();

        $userCount = 0;
        $appointmentCount = 0;
        $resourceCount = 0;
        $waitlistCount = 0;

        foreach ($users as $item) {
            $item->forceDelete();
            $userCount++;
        }

        foreach ($appointments as $item) {
            $item->forceDelete();
            $appointmentCount++;
        }

        foreach ($resources as $item) {
            $item->forceDelete();
            $resourceCount++;
        }

        foreach ($waitlist as $item) {
            $item->forceDelete();
            $waitlistCount++;
        }

        $total = $userCount + $appointmentCount + $resourceCount + $waitlistCount;

        AuditLogger::write(
            null,
            'PURGE_RECORDS',
            null,
            null,
            [
                'users' => $userCount,
                'appointments' => $appointmentCount,
                'resources' => $resourceCount,
                'waitlist' => $waitlistCount,
                'total' => $total,
            ],
            null,
        );

        $this->info('Purge completed. Total: '.$total);

        return self::SUCCESS;
    }
}
