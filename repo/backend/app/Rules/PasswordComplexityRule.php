<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordComplexityRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        $valid = strlen($password) >= 12
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/\d/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);

        if (! $valid) {
            $fail('Password must be at least 12 characters and include an uppercase letter, a lowercase letter, a number, and a special character.');
        }
    }
}
