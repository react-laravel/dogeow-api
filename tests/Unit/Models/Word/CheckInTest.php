<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\CheckIn;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    public function test_check_in_has_fillable(): void
    {
        $checkIn = new CheckIn;

        $this->assertContains('user_id', $checkIn->getFillable());
    }

    public function test_check_in_has_casts(): void
    {
        $checkIn = new CheckIn;

        $this->assertArrayHasKey('id', $checkIn->getCasts());
    }
}
