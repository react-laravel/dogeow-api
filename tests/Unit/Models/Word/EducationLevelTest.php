<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\EducationLevel;
use Tests\TestCase;

class EducationLevelTest extends TestCase
{
    public function test_education_level_has_fillable(): void
    {
        $level = new EducationLevel;

        $this->assertContains('code', $level->getFillable());
        $this->assertContains('name', $level->getFillable());
    }

    public function test_education_level_has_casts(): void
    {
        $level = new EducationLevel;

        $this->assertArrayHasKey('id', $level->getCasts());
    }
}
