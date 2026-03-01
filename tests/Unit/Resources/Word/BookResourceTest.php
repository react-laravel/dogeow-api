<?php

namespace Tests\Unit\Resources\Word;

use App\Http\Resources\Word\BookResource;
use App\Models\Word\Book;
use Tests\TestCase;

class BookResourceTest extends TestCase
{
    public function test_book_resource_to_array(): void
    {
        $book = new Book;
        $book->id = 1;
        $book->name = 'Test Book';
        $book->description = 'Test Description';
        $book->user_id = 1;

        $resource = new BookResource($book);
        $array = $resource->toArray(request());

        $this->assertEquals(1, $array['id']);
        $this->assertEquals('Test Book', $array['name']);
    }

    public function test_book_resource_with_relations(): void
    {
        $book = new Book;
        $book->id = 1;
        $book->name = 'Test Book';
        $book->user_id = 1;

        $resource = new BookResource($book);
        $resource->load('words');

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
    }
}
