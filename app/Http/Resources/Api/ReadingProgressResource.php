<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReadingProgressResource extends JsonResource
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
            'current_page' => $this->current_page,
            'total_pages' => $this->total_pages,
            'progress_percentage' => $this->progress_percentage,
            'reading_time_seconds' => $this->reading_time_seconds,
            'session_count' => $this->session_count,
            'average_session_time' => $this->average_session_time,
            'total_pause_time' => $this->total_pause_time,
            'device_type' => $this->device_type,
            'reading_speed_wpm' => $this->reading_speed_wpm,
            'last_read_at' => $this->last_read_at,
            'is_completed' => $this->is_completed,
            'completion_date' => $this->completion_date,
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
            'bookmarks' => $this->whenLoaded('bookmarks', function () {
                return BookmarkResource::collection($this->bookmarks);
            }),
            
            // Computed fields
            'reading_statistics' => [
                'pages_remaining' => max(0, $this->total_pages - $this->current_page),
                'estimated_time_remaining' => $this->getEstimatedTimeRemaining(),
                'reading_streak_days' => $this->getReadingStreakDays(),
                'last_session_duration' => $this->getLastSessionDuration(),
            ],
        ];
    }
}