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

        $validated = $request->validate([
            'new_password' => ['required', 'string', new PasswordComplexityRule()],
            'verification_note' => ['required', 'string', 'min:10'],
        ]);

        $user->forceFill([
            'password_hash' => Hash::make($validated['new_password']),
        ])->save();

        AuditLogger::write(
            $actor->id,
            'PASSWORD_RESET',
            User::class,
            $user->id,
            ['verification_note' => $validated['verification_note']],
            $request->ip(),
        );

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Password reset successful'],
        ]);
    }
}
