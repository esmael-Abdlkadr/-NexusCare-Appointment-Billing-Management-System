<?php

namespace Tests\Unit;

use App\Rules\PasswordComplexityRule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordComplexityTest extends TestCase
{
    public function test_short_password_rejected(): void
    {
        $validator = Validator::make(['password' => 'Abcdef1!xyz'], [
            'password' => [new PasswordComplexityRule()],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_no_uppercase_rejected(): void
    {
        $validator = Validator::make(['password' => 'admin@12345678'], [
            'password' => [new PasswordComplexityRule()],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_no_special_char_rejected(): void
    {
        $validator = Validator::make(['password' => 'Admin12345678'], [
            'password' => [new PasswordComplexityRule()],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_valid_password_passes(): void
    {
        $validator = Validator::make(['password' => 'Admin@12345678'], [
            'password' => [new PasswordComplexityRule()],
        ]);

        $this->assertFalse($validator->fails());
    }
}
