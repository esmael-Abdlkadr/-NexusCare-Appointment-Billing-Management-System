<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\AdminUserRepository;
use App\Rules\PasswordComplexityRule;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminUserService
{
    public function __construct(
        private readonly AdminUserRepository $adminUserRepository,
        private readonly MaskingService $maskingService,
    ) {
    }

    public function listUsers(User $actor, array $filters, int $perPage = 20): array
    {
        $paginator = $this->adminUserRepository->paginate($filters, $perPage);
        $paginator = $this->mapPaginator($paginator, fn (User $user) => $this->transformUserForViewer($actor, $user));

        return [
            'success' => true,
            'status' => 200,
            'data' => $paginator,
        ];
    }

    public function viewUser(User $viewer, User $subject): array
    {
        if ($viewer->role === 'reviewer' && $viewer->site_id !== $subject->site_id) {
            return [
                'success' => false,
                'status' => 403,
                'error' => 'FORBIDDEN',
            ];
        }

        return [
            'success' => true,
            'status' => 200,
            'data' => ['user' => $this->transformUserForViewer($viewer, $subject)],
        ];
    }

    public function createUser(User $actor, array $data): array
    {
        $validator = Validator::make($data, [
            'identifier' => ['required', 'string', 'max:100', 'unique:users,identifier'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'string', new PasswordComplexityRule()],
            'role' => ['required', 'string', 'in:staff,reviewer,administrator'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'government_id' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'status' => 422,
                'error' => 'VALIDATION_ERROR',
                'data' => $validator->errors()->toArray(),
            ];
        }

        $validated = $validator->validated();

        $user = $this->adminUserRepository->create([
            'identifier' => $validated['identifier'],
            'email' => $validated['email'] ?? null,
            'password_hash' => Hash::make($validated['password'], ['rounds' => 12]),
            'role' => $validated['role'],
            'site_id' => (int) $validated['site_id'],
            'department_id' => (int) $validated['department_id'],
            'is_banned' => false,
            'failed_attempts' => 0,
            'government_id' => $validated['government_id'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ]);

        AuditLogger::write(
            $actor->id,
            'CREATE_USER',
            User::class,
            $user->id,
            ['identifier' => $user->identifier, 'role' => $user->role],
            request()?->ip(),
        );

        return [
            'success' => true,
            'status' => 201,
            'data' => ['user' => $this->transformUserForViewer($actor, $user)],
        ];
    }

    public function updateUser(User $actor, User $target, array $data): array
    {
        if ($target->role === 'administrator') {
            return [
                'success' => false,
                'status' => 403,
                'error' => 'FORBIDDEN',
                'data' => ['message' => 'Cannot modify another administrator.'],
            ];
        }

        $validator = Validator::make($data, [
            'role' => ['sometimes', 'string', 'in:staff,reviewer,administrator'],
            'is_banned' => ['sometimes', 'boolean'],
            'muted_until' => ['sometimes', 'nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'status' => 422,
                'error' => 'VALIDATION_ERROR',
                'data' => $validator->errors()->toArray(),
            ];
        }

        $validated = $validator->validated();

        $actionLogs = [];
        if (array_key_exists('role', $validated) && $validated['role'] !== $target->role) {
            $actionLogs[] = ['action' => 'CHANGE_ROLE', 'payload' => ['from' => $target->role, 'to' => $validated['role']]];
            $target->role = $validated['role'];
        }

        if (array_key_exists('is_banned', $validated) && (bool) $validated['is_banned'] !== (bool) $target->is_banned) {
            $actionLogs[] = [
                'action' => (bool) $validated['is_banned'] ? 'BAN_USER' : 'UNBAN_USER',
                'payload' => ['is_banned' => (bool) $validated['is_banned']],
            ];
            $target->is_banned = (bool) $validated['is_banned'];
        }

        if (array_key_exists('muted_until', $validated) && $validated['muted_until']) {
            // Enforce minimum 24h from now for all mute actions
            $muteUntil = Carbon::parse($validated['muted_until']);
            if ($muteUntil->lt(now()->addHours(24))) {
                $muteUntil = now()->addHours(24);
            }

            $target->muted_until = $muteUntil;
            $actionLogs[] = [
                'action' => 'MUTE_USER',
                'payload' => ['muted_until' => $muteUntil->toDateTimeString(), 'duration_hours' => 24],
            ];
        }

        if (array_key_exists('muted_until', $validated) && ! $validated['muted_until']) {
            $target->muted_until = null;
        }

        $this->adminUserRepository->save($target);

        foreach ($actionLogs as $entry) {
            AuditLogger::write(
                $actor->id,
                $entry['action'],
                User::class,
                $target->id,
                $entry['payload'],
                request()?->ip(),
            );
        }

        return [
            'success' => true,
            'status' => 200,
            'data' => ['user' => $this->transformUserForViewer($actor, $target)],
        ];
    }

    public function deleteUser(User $actor, User $target): array
    {
        if ($target->role === 'administrator') {
            return [
                'success' => false,
                'status' => 403,
                'error' => 'FORBIDDEN',
                'data' => ['message' => 'Cannot delete another administrator.'],
            ];
        }

        $target->delete();

        AuditLogger::write(
            $actor->id,
            'DELETE_USER',
            User::class,
            $target->id,
            ['identifier' => $target->identifier],
            request()?->ip(),
        );

        return [
            'success' => true,
            'status' => 200,
            'data' => ['message' => 'User deleted successfully'],
        ];
    }

    public function bulkAction(User $actor, array $data): array
    {
        $validator = Validator::make($data, [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'action' => ['required', 'string', 'in:ban,unban,mute,delete,change_role'],
            'muted_until' => ['nullable', 'date'],
            'role' => ['nullable', 'string', 'in:staff,reviewer,administrator'],
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'status' => 422,
                'error' => 'VALIDATION_ERROR',
                'data' => $validator->errors()->toArray(),
            ];
        }

        $validated = $validator->validated();

        if ($validated['action'] === 'mute' && empty($validated['muted_until'])) {
            $validated['muted_until'] = now()->addHours(24)->toDateTimeString();
        }

        if ($validated['action'] === 'change_role' && empty($validated['role'])) {
            return [
                'success' => false,
                'status' => 422,
                'error' => 'VALIDATION_ERROR',
                'data' => ['role' => ['The role field is required for change_role action.']],
            ];
        }

        $succeeded = [];
        $failed = [];

        foreach ($validated['user_ids'] as $userId) {
            $target = $this->adminUserRepository->findByIdWithTrashed((int) $userId);

            if (! $target) {
                $failed[] = ['id' => (int) $userId, 'reason' => 'user_not_found'];
                continue;
            }

            if ($target->role === 'administrator') {
                $failed[] = ['id' => (int) $userId, 'reason' => 'cannot ban admin'];
                continue;
            }

            if ($validated['action'] === 'ban') {
                $target->is_banned = true;
                $this->adminUserRepository->save($target);
                AuditLogger::write($actor->id, 'BAN_USER', User::class, $target->id, [], request()?->ip());
                $succeeded[] = (int) $userId;
                continue;
            }

            if ($validated['action'] === 'unban') {
                $target->is_banned = false;
                $this->adminUserRepository->save($target);
                AuditLogger::write($actor->id, 'UNBAN_USER', User::class, $target->id, [], request()?->ip());
                $succeeded[] = (int) $userId;
                continue;
            }

            if ($validated['action'] === 'mute') {
                $target->muted_until = $validated['muted_until'];
                $this->adminUserRepository->save($target);
                AuditLogger::write(
                    $actor->id,
                    'MUTE_USER',
                    User::class,
                    $target->id,
                    [
                        'muted_until' => $validated['muted_until'],
                        'duration_hours' => 24,
                    ],
                    request()?->ip()
                );
                $succeeded[] = (int) $userId;
                continue;
            }

            if ($validated['action'] === 'change_role') {
                $target->role = $validated['role'];
                $this->adminUserRepository->save($target);
                AuditLogger::write($actor->id, 'CHANGE_ROLE', User::class, $target->id, ['to' => $validated['role']], request()?->ip());
                $succeeded[] = (int) $userId;
                continue;
            }

            if ($validated['action'] === 'delete') {
                $target->delete();
                AuditLogger::write($actor->id, 'DELETE_USER', User::class, $target->id, [], request()?->ip());
                $succeeded[] = (int) $userId;
                continue;
            }
        }

        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'succeeded' => $succeeded,
                'failed' => $failed,
            ],
        ];
    }

    public function unlockUser(User $actor, User $target): array
    {
        $target->locked_until = null;
        $target->failed_attempts = 0;
        $this->adminUserRepository->save($target);

        AuditLogger::write(
            $actor->id,
            'UNLOCK_USER',
            User::class,
            $target->id,
            [],
            request()?->ip(),
        );

        return [
            'success' => true,
            'status' => 200,
            'data' => ['user' => $this->transformUserForViewer($actor, $target)],
        ];
    }

    private function transformUserForViewer(User $viewer, User $subject): array
    {
        return [
            'id' => $subject->id,
            'identifier' => $subject->identifier,
            'email' => $this->maskingService->mask($viewer, $subject, 'email', $subject->email),
            'role' => $subject->role,
            'site_id' => $subject->site_id,
            'department_id' => $subject->department_id,
            'is_banned' => (bool) $subject->is_banned,
            'muted_until' => optional($subject->muted_until)->toIso8601String(),
            'locked_until' => optional($subject->locked_until)->toIso8601String(),
            'failed_attempts' => (int) $subject->failed_attempts,
            'government_id' => $this->maskingService->mask($viewer, $subject, 'government_id', $subject->government_id),
            'phone' => $this->maskingService->mask($viewer, $subject, 'phone', $subject->phone),
            'deleted_at' => optional($subject->deleted_at)->toIso8601String(),
        ];
    }

    private function mapPaginator(LengthAwarePaginator $paginator, callable $callback): LengthAwarePaginator
    {
        $items = [];

        foreach ($paginator->items() as $item) {
            $items[] = $callback($item);
        }

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            [
                'path' => request()?->url(),
                'query' => request()?->query(),
            ],
        );
    }
}
