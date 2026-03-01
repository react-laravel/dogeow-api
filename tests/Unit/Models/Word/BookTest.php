<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\Book;
use Tests\TestCase;

class BookTest extends TestCase
{
    public function test_book_has_fillable(): void
    {
        $book = new Book;

        $this->assertContains('name', $book->getFillable());
        $this->assertContains('description', $book->getFillable());
    }

    public function test_book_has_casts(): void
    {
        $book = new Book;

        $this->assertArrayHasKey('id', $book->getCasts());
    }
}
