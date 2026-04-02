<?php

namespace App\Services;

use App\Models\User;

class MaskingService
{
    public function mask(User $viewer, User $subject, string $field, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if ((int) $viewer->id === (int) $subject->id) {
            return $value;
        }

        if ($viewer->role === 'administrator') {
            return $value;
        }

        return match ($field) {
            'government_id' => $viewer->role === 'reviewer' ? $this->maskLast4($value) : null,
            'phone' => $this->maskPhone($value),
            'email' => $this->maskEmail($value),
            default => $value,
        };
    }

    private function maskLast4(string $value): string
    {
        $last4 = substr($value, -4);
        return '****'.$last4;
    }

    private function maskPhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        $last4 = substr($digits, -4);
        return '(***) ***-'.str_pad($last4, 4, '*', STR_PAD_LEFT);
    }

    private function maskEmail(string $value): string
    {
        $parts = explode('@', $value);
        if (count($parts) !== 2) {
            return '***';
        }

        $name = $parts[0];
        $domain = $parts[1];
        $first = substr($name, 0, 1);
        return $first.'***@'.$domain;
    }
}
