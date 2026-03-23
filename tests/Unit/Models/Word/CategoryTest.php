<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\Book;
use App\Models\Word\Category;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    public function test_category_has_fillable(): void
    {
        $category = new Category;

        $this->assertContains('name', $category->getFillable());
    }

    public function test_category_has_casts(): void
    {
        $category = new Category;

        $this->assertSame('integer', $category->getCasts()['sort_order']);
    }

    public function test_books_relation_is_configured(): void
    {
        $relation = (new Category)->books();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(Book::class, $relation->getRelated());
    }

    public function test_category_uses_timestamp(): void
    {
        $category = new Category;

        $this->assertTrue($category->timestamps);
    }

    public function test_category_has_correct_table_name(): void
    {
        $category = new Category;

        $this->assertSame('word_categories', $category->getTable());
    }

    public function test_category_fillable_includes_all_fields(): void
    {
        $category = new Category;
        $fillable = $category->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('sort_order', $fillable);
    }

    public function test_category_casts_includes_required_types(): void
    {
        $category = new Category;
        $casts = $category->getCasts();

        $this->assertArrayHasKey('sort_order', $casts);
    }
}
