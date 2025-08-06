<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SocialShareResource extends JsonResource
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
            'platform' => $this->platform,
            'share_type' => $this->share_type,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            
            // Relationships
            'comic' => $this->whenLoaded('comic', function () {
                return [
                    'id' => $this->comic->id,
                    'title' => $this->comic->title,
                    'slug' => $this->comic->slug,
                    'cover_image_url' => $this->comic->getCoverImageUrl(),
                ];
            }),
            
            // Computed fields
            'share_url' => $this->getShareUrl(),
            'platform_display_name' => $this->getPlatformDisplayName(),
        ];
    }
}