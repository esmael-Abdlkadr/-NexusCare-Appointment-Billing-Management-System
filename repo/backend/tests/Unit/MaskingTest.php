<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\MaskingService;
use Tests\TestCase;

class MaskingTest extends TestCase
{
    public function test_gov_id_masked_reviewer(): void
    {
        $service = new MaskingService();
        $viewer = (new User())->forceFill(['id' => 2, 'role' => 'reviewer']);
        $subject = (new User())->forceFill(['id' => 3, 'role' => 'staff']);

        $this->assertSame('****1234', $service->mask($viewer, $subject, 'government_id', 'ABC1234'));
    }

    public function test_gov_id_visible_administrator(): void
    {
        $service = new MaskingService();
        $viewer = (new User())->forceFill(['id' => 1, 'role' => 'administrator']);
        $subject = (new User())->forceFill(['id' => 3, 'role' => 'staff']);

        $this->assertSame('ABC1234', $service->mask($viewer, $subject, 'government_id', 'ABC1234'));
    }

    public function test_phone_masked_staff(): void
    {
        $service = new MaskingService();
        $viewer = (new User())->forceFill(['id' => 5, 'role' => 'staff']);
        $subject = (new User())->forceFill(['id' => 6, 'role' => 'staff']);

        $this->assertSame('(***) ***-5678', $service->mask($viewer, $subject, 'phone', '(555) 123-5678'));
    }

    public function test_own_record_unmasked(): void
    {
        $service = new MaskingService();
        $viewer = (new User())->forceFill(['id' => 9, 'role' => 'reviewer']);
        $subject = (new User())->forceFill(['id' => 9, 'role' => 'reviewer']);

        $this->assertSame('ABC1234', $service->mask($viewer, $subject, 'government_id', 'ABC1234'));
    }
}
