<?php

namespace Tests\Unit;

use App\Rules\IdentifierFormatRule;
use Closure;
use PHPUnit\Framework\TestCase;

class IdentifierFormatRuleTest extends TestCase
{
    private function validate(string $value): ?string
    {
        $message = null;
        $fail = function (string $msg) use (&$message): void {
            $message = $msg;
        };
        (new IdentifierFormatRule())->validate('identifier', $value, Closure::fromCallable($fail));

        return $message;
    }

    public function test_valid_alphanumeric_identifier_passes(): void
    {
        $this->assertNull($this->validate('staff001'));
    }

    public function test_valid_email_style_identifier_passes(): void
    {
        $this->assertNull($this->validate('john.doe@clinic'));
    }

    public function test_valid_identifier_with_dots_dashes_underscores_passes(): void
    {
        $this->assertNull($this->validate('emp_001-A.dept'));
    }

    public function test_identifier_too_short_fails(): void
    {
        $this->assertNotNull($this->validate('ab'));
    }

    public function test_identifier_too_long_fails(): void
    {
        $this->assertNotNull($this->validate(str_repeat('a', 101)));
    }

    public function test_identifier_with_leading_special_char_fails(): void
    {
        $this->assertNotNull($this->validate('.leading'));
        $this->assertNotNull($this->validate('-leading'));
        $this->assertNotNull($this->validate('@leading'));
    }

    public function test_identifier_with_space_fails(): void
    {
        $this->assertNotNull($this->validate('user name'));
    }

    public function test_identifier_with_sql_injection_chars_fails(): void
    {
        $this->assertNotNull($this->validate("'; DROP TABLE users;--"));
        $this->assertNotNull($this->validate('<script>'));
    }

    public function test_identifier_with_only_numbers_passes(): void
    {
        $this->assertNull($this->validate('12345'));
    }
}
