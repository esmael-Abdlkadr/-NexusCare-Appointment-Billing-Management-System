<?php

namespace App\Repositories;

use App\Models\FeeAssessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class FeeAssessmentRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = FeeAssessment::query()->with(['client', 'appointment', 'waiverBy']);

        $this->applyActorSiteScope($query);

        if (! empty($filters['client_id'])) {
            $query->where('client_id', (int) $filters['client_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['fee_type'])) {
            $query->where('fee_type', $filters['fee_type']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function findById(int $id): ?FeeAssessment
    {
        $query = FeeAssessment::query()->with(['client', 'appointment', 'waiverBy']);
        $this->applyActorSiteScope($query);
        return $query->find($id);
    }

    public function findByIdWithoutActorScope(int $id): ?FeeAssessment
    {
        return FeeAssessment::query()->with(['client', 'appointment', 'waiverBy'])->find($id);
    }

    public function create(array $data): FeeAssessment
    {
        return FeeAssessment::query()->create($data);
    }

    public function save(FeeAssessment $feeAssessment): FeeAssessment
    {
        $feeAssessment->save();
        return $feeAssessment;
    }

    public function findByAppointmentAndType(int $appointmentId, string $feeType): ?FeeAssessment
    {
        return FeeAssessment::query()
            ->where('appointment_id', $appointmentId)
            ->where('fee_type', $feeType)
            ->first();
    }

    public function pendingOverdue(): Collection
    {
        return FeeAssessment::query()
            ->with(['appointment', 'client'])
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->get();
    }

    private function applyActorSiteScope(Builder $query): void
    {
        $actor = request()?->user();

        if (! $actor || $actor->role === 'administrator') {
            return;
        }

        $query->whereHas('client', function (Builder $clientQuery) use ($actor): void {
            $clientQuery->where('site_id', $actor->site_id);
        });
    }
}
