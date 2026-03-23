<?php

namespace App\Http\Resources;

use App\Models\Thing\ItemImage;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /** @var ItemImage $resource */
        $resource = $this->resource;

        return [
            'thumbnail_path' => $resource->thumbnail_path,
        ];
    }
}
