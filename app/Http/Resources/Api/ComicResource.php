<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComicResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'genre' => $this->genre,
            'tags' => $this->getTagsArray(),
            'description' => $this->description,
            'page_count' => $this->page_count,
            'language' => $this->language,
            'isbn' => $this->isbn,
            'publication_year' => $this->publication_year,
            'average_rating' => $this->average_rating,
            'total_ratings' => $this->total_ratings,
            'total_readers' => $this->total_readers,
            'cover_image_url' => $this->getCoverImageUrl(),
            'is_free' => $this->is_free,
            'price' => $this->price,
            'has_mature_content' => $this->has_mature_content,
            'content_warnings' => $this->content_warnings,
            'reading_time_estimate' => $this->getReadingTimeEstimate(),
            'is_new_release' => $this->isNewRelease(),
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Conditional fields
            'user_progress' => $this->whenLoaded('userProgress'),
            'series' => $this->whenLoaded('series'),
            'reviews' => $this->whenLoaded('reviews'),
            'bookmarks' => $this->whenLoaded('bookmarks'),
            
            // User-specific fields (only when authenticated)
            'user_has_access' => $this->when(
                $request->user(),
                fn() => $request->user()->hasAccessToComic($this->resource)
            ),
            'is_in_library' => $this->when(
                $request->user(),
                fn() => $request->user()->library()->where('comic_id', $this->id)->exists()
            ),
            'is_favorite' => $this->when(
                $request->user(),
                fn() => $request->user()->library()
                    ->where('comic_id', $this->id)
                    ->where('is_favorite', true)
                    ->exists()
            ),
        ];
    }
}