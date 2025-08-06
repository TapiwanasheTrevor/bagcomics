<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLibraryResource extends JsonResource
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
            'access_type' => $this->access_type,
            'purchase_price' => $this->purchase_price,
            'purchased_at' => $this->purchased_at,
            'is_favorite' => $this->is_favorite,
            'rating' => $this->rating,
            'review' => $this->review,
            'total_reading_time' => $this->total_reading_time,
            'reading_sessions' => $this->reading_sessions,
            'last_read_at' => $this->last_read_at,
            'completion_date' => $this->completion_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'comic' => $this->whenLoaded('comic', function () {
                return new ComicResource($this->comic);
            }),
            'progress' => $this->whenLoaded('progress', function () {
                return new ReadingProgressResource($this->progress);
            }),
            
            // Computed fields
            'reading_statistics' => [
                'average_session_time' => $this->getAverageSessionTime(),
                'completion_percentage' => $this->getCompletionPercentage(),
                'days_since_purchase' => $this->purchased_at ? $this->purchased_at->diffInDays(now()) : null,
                'days_since_last_read' => $this->last_read_at ? $this->last_read_at->diffInDays(now()) : null,
            ],
        ];
    }
}