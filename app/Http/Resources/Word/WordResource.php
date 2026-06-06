<?php

namespace App\Http\Resources\Word;

use App\Models\Word\EducationLevel;
use App\Models\Word\Word;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var Word $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'content' => $resource->content,
            'phonetic_us' => $resource->phonetic_us,
            'explanation' => $resource->explanation,
            'example_sentences' => $resource->example_sentences,
            'difficulty' => $resource->difficulty,
            'frequency' => $resource->frequency,
            'is_review_word' => (bool) ($resource->is_review_word ?? false),
            'books' => $this->whenLoaded('books', fn () => BookResource::collection($resource->books)),
            'education_levels' => $this->whenLoaded('educationLevels', function () use ($resource) {
                /** @var Collection<int, EducationLevel> $levels */
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
