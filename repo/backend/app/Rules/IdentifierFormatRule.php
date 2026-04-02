<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IdentifierFormatRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $identifier = (string) $value;

        // Must be 3–100 characters; alphanumeric, dots, underscores, hyphens, or @ allowed
        // Must start with alphanumeric character — no leading special chars
        $valid = strlen($identifier) >= 3
            && strlen($identifier) <= 100
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._@\-]*$/', $identifier);

        if (! $valid) {
            $fail('The identifier must be 3–100 characters and may only contain letters, numbers, dots, underscores, hyphens, or @ symbol.');
        }
    }
}
