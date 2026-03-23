<?php

namespace Tests\Unit\Resources\Word;

use App\Http\Resources\Word\BookResource;
use App\Models\Word\Book;
use App\Models\Word\Category;
use App\Models\Word\EducationLevel;
use Illuminate\Http\Request;
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
        $book->description = 'Test Description';
        $book->difficulty = 2;
        $book->total_words = 20;
        $book->sort_order = 5;
        $book->user_id = 1;

        $category = new Category;
        $category->id = 9;
        $category->name = 'CET';
        $book->setRelation('category', $category);

        $level = new EducationLevel;
        $level->id = 7;
        $level->code = 'college';
        $level->name = 'College';
        $book->setRelation('educationLevels', collect([$level]));

        $resource = new BookResource($book);
        $array = $resource->resolve(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertSame('CET', $array['category']['name']);
        $this->assertSame('college', $array['education_levels'][0]['code']);
    }
}
