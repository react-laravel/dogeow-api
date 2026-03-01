<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\Category;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    public function test_category_has_fillable(): void
    {
        $category = new Category;

        $this->assertContains('name', $category->getFillable());
    }
}
