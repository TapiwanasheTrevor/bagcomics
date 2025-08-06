<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookmarkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'comic_id' => $this->comic_id,
            'page_number' => $this->page_number,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'comic' => $this->whenLoaded('comic', function () {
                return [
                    'id' => $this->comic->id,
                    'title' => $this->comic->title,
                    'slug' => $this->comic->slug,
                    'page_count' => $this->comic->page_count,
                ];
            }),
            
            // Computed fields
            'progress_percentage' => $this->comic ? 
                round(($this->page_number / $this->comic->page_count) * 100, 2) : 0,
        ];
    }
}