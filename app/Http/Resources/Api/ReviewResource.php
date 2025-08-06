<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'rating' => $this->rating,
            'title' => $this->title,
            'content' => $this->content,
            'is_spoiler' => $this->is_spoiler,
            'helpful_votes' => $this->helpful_votes,
            'total_votes' => $this->total_votes,
            'helpfulness_ratio' => $this->getHelpfulnessRatio(),
            'is_approved' => $this->is_approved,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'avatar_url' => $this->user->avatar_path ? asset('storage/' . $this->user->avatar_path) : null,
                ];
            }),
            'comic' => $this->whenLoaded('comic', function () {
                return [
                    'id' => $this->comic->id,
                    'title' => $this->comic->title,
                    'slug' => $this->comic->slug,
                ];
            }),
            
            // User-specific fields
            'user_vote' => $this->when(
                $request->user(),
                fn() => $this->votes()->where('user_id', $request->user()->id)->first()?->is_helpful
            ),
            'can_edit' => $this->when(
                $request->user(),
                fn() => $request->user()->id === $this->user_id || $request->user()->can('moderate-reviews')
            ),
            'can_delete' => $this->when(
                $request->user(),
                fn() => $request->user()->id === $this->user_id || $request->user()->can('moderate-reviews')
            ),
        ];
    }
}