<?php

namespace App\Http\Resources\Word;

use App\Models\Word\Book;
use App\Models\Word\EducationLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var Book $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'description' => $resource->description,
            'difficulty' => $resource->difficulty,
            'total_words' => $resource->total_words,
            'sort_order' => $resource->sort_order,
            'category' => $this->whenLoaded('category'),
            'education_levels' => $this->whenLoaded('educationLevels', function () use ($resource) {
                /** @var \Illuminate\Database\Eloquent\Collection<int, EducationLevel> $levels */
                $levels = $resource->educationLevels;

                return $levels->map(fn (EducationLevel $level): array => [
                    'id' => $level->id,
                    'code' => $level->code,
                    'name' => $level->name,
                ]);
            }),
        ];
    }
}
