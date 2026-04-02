<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentVersion;
use App\Models\SyncIndex;
use App\Models\Site;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Log;

class SyncService
{
    public function runSync(int $siteId): void
    {
        $lastSyncAt = SyncIndex::query()
            ->where('site_id', $siteId)
            ->where('entity_type', 'appointment')
            ->max('synced_at');

        $lastSyncAt = $lastSyncAt ? now()->parse($lastSyncAt) : now()->subMinutes(15);

        $appointments = Appointment::withoutGlobalScopes()
            ->where('site_id', $siteId)
            ->where('updated_at', '>', $lastSyncAt)
            ->get();

        $incomingRecords = $appointments->all();
        $mergedCount = 0;
        $skippedCount = 0;

        Log::channel('sync')->info('Sync started', [
            'event' => 'sync_started',
            'record_count' => count($incomingRecords ?? []),
        ]);

        foreach ($appointments as $appt) {
            $fingerprint = $this->fingerprintAppointment($appt);

            $index = SyncIndex::query()
                ->where('site_id', $siteId)
                ->where('entity_type', 'appointment')
                ->where('entity_id', $appt->id)
                ->first();

            if (! $index || $index->fingerprint === $fingerprint) {
                SyncIndex::query()->updateOrCreate(
                    ['site_id' => $siteId, 'entity_type' => 'appointment', 'entity_id' => $appt->id],
                    ['fingerprint' => $fingerprint, 'synced_at' => now()],
                );
                $skippedCount++;
                continue;
            }

            $remote = Appointment::withoutGlobalScopes()
                ->where('id', $appt->id)
                ->where('site_id', '!=', $siteId)
                ->first();

            if (! $remote) {
                SyncIndex::query()->updateOrCreate(
                    ['site_id' => $siteId, 'entity_type' => 'appointment', 'entity_id' => $appt->id],
                    ['fingerprint' => $fingerprint, 'synced_at' => now()],
                );
                $skippedCount++;
                continue;
            }

            [$winner, $loser, $rule] = $this->resolveConflict($appt, $remote);

            Log::channel('sync')->info('Sync conflict resolved', [
                'event' => 'sync_conflict',
                'appointment_id' => $appt->id ?? null,
                'strategy' => 'keep_confirmed_or_latest',
            ]);

            AppointmentVersion::query()->create([
                'appointment_id' => $loser->id,
                'snapshot' => $loser->toArray(),
                'changed_by' => $loser->provider_id,
                'created_at' => now(),
            ]);

            $loser->status = $winner->status;
            $loser->start_time = $winner->start_time;
            $loser->end_time = $winner->end_time;
            $loser->provider_id = $winner->provider_id;
            $loser->resource_id = $winner->resource_id;
            $loser->service_type = $winner->service_type;
            $loser->department_id = $winner->department_id;
            $loser->updated_at = $winner->updated_at;
            $loser->save();

            SyncIndex::query()->updateOrCreate(
                ['site_id' => $winner->site_id, 'entity_type' => 'appointment', 'entity_id' => $winner->id],
                ['fingerprint' => $this->fingerprintAppointment($winner), 'synced_at' => now()],
            );

            SyncIndex::query()->updateOrCreate(
                ['site_id' => $loser->site_id, 'entity_type' => 'appointment', 'entity_id' => $loser->id],
                ['fingerprint' => $this->fingerprintAppointment($loser), 'synced_at' => now()],
            );

            AuditLogger::write(
                null,
                'SYNC_MERGE',
                Appointment::class,
                $appt->id,
                [
                    'winner_site' => $winner->site_id,
                    'loser_site' => $loser->site_id,
                    'rule' => $rule,
                ],
                request()?->ip(),
            );

            $mergedCount++;
        }

        Log::channel('sync')->info('Sync completed', [
            'event' => 'sync_completed',
            'merged' => $mergedCount ?? 0,
            'skipped' => $skippedCount ?? 0,
        ]);
    }

    public function runAllSites(): void
    {
        foreach (Site::query()->pluck('id') as $siteId) {
            $this->runSync((int) $siteId);
        }
    }

    private function resolveConflict(Appointment $local, Appointment $remote): array
    {
        if ($local->status === Appointment::STATUS_CONFIRMED && $remote->status !== Appointment::STATUS_CONFIRMED) {
            return [$local, $remote, 'confirmed_wins'];
        }

        if ($remote->status === Appointment::STATUS_CONFIRMED && $local->status !== Appointment::STATUS_CONFIRMED) {
            return [$remote, $local, 'confirmed_wins'];
        }

        if ($local->status === Appointment::STATUS_CONFIRMED && $remote->status === Appointment::STATUS_CONFIRMED) {
            if ($local->updated_at->gte($remote->updated_at)) {
                return [$local, $remote, 'latest_updated_at'];
            }
            return [$remote, $local, 'latest_updated_at'];
        }

        if ($local->updated_at->gte($remote->updated_at)) {
            return [$local, $remote, 'latest_updated_at'];
        }

        return [$remote, $local, 'latest_updated_at'];
    }

    public function resolveConflictForTest(Appointment $local, Appointment $remote): array
    {
        return $this->resolveConflict($local, $remote);
    }

    private function fingerprintAppointment(Appointment $appt): string
    {
        return hash('sha256', json_encode([
            'id' => $appt->id,
            'status' => $appt->status,
            'start_time' => (string) $appt->start_time,
            'end_time' => (string) $appt->end_time,
            'provider_id' => $appt->provider_id,
            'resource_id' => $appt->resource_id,
            'updated_at' => (string) $appt->updated_at,
        ]));
    }
}
