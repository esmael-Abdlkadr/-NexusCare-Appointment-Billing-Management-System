<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PasswordComplexityRule;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AdminUserController extends Controller
{
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($actor->role !== 'administrator') {
            return response()->json([
                'success' => false,
                'error' => 'FORBIDDEN',
                'data' => [],
            ], Response::HTTP_FORBIDDEN);
        }

        // Keep admin operations tenant-scoped: site admins cannot reset
        // credentials for users in another site.
        if ((int) $actor->site_id !== (int) $user->site_id) {
            return response()->json([
                'success' => false,
                'error' => 'NOT_FOUND',
                'data' => [],
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'new_password'        => ['required', 'string', new PasswordComplexityRule()],
            'verification_method' => ['required', 'string', 'in:in_person,phone,document'],
            'verified_attributes' => ['required', 'array', 'min:1'],
            'verified_attributes.*' => ['required', 'string', 'max:100'],
            'verifier_role'       => ['required', 'string', 'max:100'],
            'verification_result' => ['required', 'string', 'in:passed'],
        ]);

        $user->forceFill([
            'password_hash' => Hash::make($validated['new_password']),
        ])->save();

        AuditLogger::write(
            $actor->id,
            'PASSWORD_RESET',
            User::class,
            $user->id,
            [
                'verification_method'  => $validated['verification_method'],
                'verified_attributes'  => $validated['verified_attributes'],
                'verifier_role'        => $validated['verifier_role'],
                'verification_result'  => $validated['verification_result'],
            ],
            $request->ip(),
        );

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Password reset successful'],
        ]);
    }
}
