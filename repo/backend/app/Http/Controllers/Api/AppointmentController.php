<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Policies\AppointmentPolicy;
use App\Repositories\AppointmentRepository;
use App\Repositories\AppointmentVersionRepository;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AppointmentVersionRepository $appointmentVersionRepository,
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentPolicy $appointmentPolicy,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($request->boolean('check_conflict')) {
            $validated = $request->validate([
                'provider_id' => ['required', 'integer', 'exists:users,id'],
                'resource_id' => ['required', 'integer', 'exists:resources,id'],
                'start_time' => ['required', 'date'],
                'end_time' => ['required', 'date', 'after:start_time'],
            ]);

            $conflicts = $this->appointmentRepository->overlappingConflicts(
                (int) $validated['provider_id'],
                (int) $validated['resource_id'],
                Carbon::parse($validated['start_time']),
                Carbon::parse($validated['end_time']),
                (int) $actor->site_id,
            );

            if ($conflicts->isNotEmpty()) {
                $conflict = $conflicts->first();
                $conflictType = $conflict->provider_id === (int) $validated['provider_id'] ? 'provider' : 'resource';

                return $this->error('APPOINTMENT_CONFLICT', Response::HTTP_CONFLICT, [
                    'conflict_type' => $conflictType,
                    'next_available_slots' => $this->appointmentService->getNextAvailableSlots(
                        (int) $validated['provider_id'],
                        (int) $validated['resource_id'],
                        $validated['end_time'],
                        3,
                        (int) $actor->site_id,
                    ),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'has_conflict' => false,
                    'next_available_slots' => [],
                ],
            ]);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:requested,confirmed,checked_in,no_show,completed,cancelled'],
            'provider_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($actor->role === 'staff') {
            $validated['department_id'] = (int) $actor->department_id;
        }

        $appointments = $this->appointmentRepository->paginate($validated, (int) ($validated['per_page'] ?? 15));

        return response()->json([
            'success' => true,
            'data' => $appointments,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $this->appointmentPolicy->create($actor)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $siteId = (int) $actor->site_id;

        $validated = $request->validate([
            'client_id'     => ['required', 'integer', Rule::exists('users', 'id')->where('site_id', $siteId)],
            'provider_id'   => ['required', 'integer', Rule::exists('users', 'id')->where('site_id', $siteId)],
            'resource_id'   => ['required', 'integer', Rule::exists('resources', 'id')->where('site_id', $siteId)],
            'service_type'  => ['required', 'string', 'max:100'],
            'start_time'    => ['required', 'date'],
            'end_time'      => ['required', 'date', 'after:start_time'],
            'department_id' => [
                'required',
                'integer',
                $actor->role === 'staff'
                    ? Rule::in([$actor->department_id])
                    : Rule::exists('departments', 'id')->where('site_id', $siteId),
            ],
        ]);

        $validated['site_id'] = $siteId;

        $result = $this->appointmentService->createAppointment($validated, $actor);

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : $result['error'],
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $appointment = $this->appointmentRepository->findByIdWithoutScope($id);

        if (! $appointment) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        if (! $this->appointmentPolicy->view($actor, $appointment)) {
            $error = $appointment->site_id !== $actor->site_id && $actor->role !== 'administrator'
                ? 'SCOPE_VIOLATION'
                : 'FORBIDDEN';

            return $this->error($error, Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'appointment' => $appointment,
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $appointment = $this->appointmentRepository->findByIdWithoutScope($id);

        if (! $appointment) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        if (! $this->appointmentPolicy->update($actor, $appointment)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $siteId = (int) $actor->site_id;

        $validated = $request->validate([
            'start_time'  => ['required', 'date'],
            'end_time'    => ['required', 'date', 'after:start_time'],
            'provider_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('site_id', $siteId)],
            'resource_id' => ['nullable', 'integer', Rule::exists('resources', 'id')->where('site_id', $siteId)],
        ]);

        $result = $this->appointmentService->rescheduleAppointment($appointment, $validated, $actor);

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : $result['error'],
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }

    public function transitionStatus(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $appointment = $this->appointmentRepository->findByIdWithoutScope($id);

        if (! $appointment) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        if (! $this->appointmentPolicy->update($actor, $appointment)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:requested,confirmed,checked_in,no_show,completed,cancelled'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'cancel_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->appointmentService->transitionStatus(
            $appointment,
            $validated['status'],
            $actor,
            $validated['reason'] ?? $validated['cancel_reason'] ?? null,
        );

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : $result['error'],
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }

    public function versions(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $appointment = $this->appointmentRepository->findByIdWithoutScope($id);

        if (! $appointment) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        if ($actor->role !== 'administrator' && $appointment->site_id !== $actor->site_id) {
            return $this->error('SCOPE_VIOLATION', Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => $this->appointmentVersionRepository->listByAppointmentId($appointment->id),
        ]);
    }

    private function error(string $code, int $status, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $code,
            'data' => $data,
        ], $status);
    }
}
