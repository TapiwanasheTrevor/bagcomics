<?php

namespace App\Services;

use App\Models\Comic;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SocialMediaApiService
{
    /**
     * Post to Facebook using Graph API
     */
    public function postToFacebook(User $user, Comic $comic, string $message, array $options = []): array
    {
        $accessToken = $this->getUserSocialToken($user, 'facebook');
        
        if (!$accessToken) {
            throw new \Exception('Facebook access token not found for user');
        }

        $postData = [
            'message' => $message,
            'link' => route('comics.show', $comic->slug),
            'access_token' => $accessToken,
        ];

        // Add image if available
        if ($comic->cover_image_path && isset($options['include_image']) && $options['include_image']) {
            $postData['picture'] = asset('storage/' . $comic->cover_image_path);
        }

        try {
            $response = Http::post('https://graph.facebook.com/v18.0/me/feed', $postData);
            
            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Successfully posted to Facebook', [
                    'user_id' => $user->id,
                    'comic_id' => $comic->id,
                    'post_id' => $data['id'] ?? null,
                ]);
                
                return [
                    'success' => true,
                    'post_id' => $data['id'] ?? null,
                    'post_url' => "https://facebook.com/{$data['id']}",
                ];
            } else {
                throw new \Exception('Facebook API error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Facebook posting failed', [
                'user_id' => $user->id,
                'comic_id' => $comic->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Post to Twitter using API v2
     */
    public function postToTwitter(User $user, Comic $comic, string $text, array $options = []): array
    {
        $accessToken = $this->getUserSocialToken($user, 'twitter');
        
        if (!$accessToken) {
            throw new \Exception('Twitter access token not found for user');
        }

        $tweetData = [
            'text' => $text,
        ];

        // Add media if available
        if ($comic->cover_image_path && isset($options['include_image']) && $options['include_image']) {
            $mediaId = $this->uploadTwitterMedia($comic->cover_image_path, $accessToken);
            if ($mediaId) {
                $tweetData['media'] = ['media_ids' => [$mediaId]];
            }
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post('https://api.twitter.com/2/tweets', $tweetData);
            
            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Successfully posted to Twitter', [
                    'user_id' => $user->id,
                    'comic_id' => $comic->id,
                    'tweet_id' => $data['data']['id'] ?? null,
                ]);
                
                return [
                    'success' => true,
                    'tweet_id' => $data['data']['id'] ?? null,
                    'tweet_url' => "https://twitter.com/i/web/status/{$data['data']['id']}",
                ];
            } else {
                throw new \Exception('Twitter API error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Twitter posting failed', [
                'user_id' => $user->id,
                'comic_id' => $comic->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload media to Twitter for use in tweets
     */
    private function uploadTwitterMedia(string $imagePath, string $accessToken): ?string
    {
        try {
            $imageData = base64_encode(file_get_contents(storage_path('app/public/' . $imagePath)));
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->post('https://upload.twitter.com/1.1/media/upload.json', [
                'media_data' => $imageData,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['media_id_string'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Twitter media upload failed', ['error' => $e->getMessage()]);
        }
        
        return null;
    }

    /**
     * Share to Instagram (using Instagram Basic Display API)
     * Note: Instagram sharing requires manual approval and has limitations
     */
    public function shareToInstagram(User $user, Comic $comic, string $caption, array $options = []): array
    {
        // Instagram API has strict requirements and limitations
        // This is a placeholder for the Instagram sharing functionality
        // In a real implementation, you would need:
        // 1. Instagram Business Account
        // 2. Facebook App with Instagram permissions
        // 3. User authentication with Instagram
        
        Log::info('Instagram sharing attempted', [
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'note' => 'Instagram sharing requires additional setup and permissions',
        ]);
        
        return [
            'success' => false,
            'error' => 'Instagram sharing requires additional setup and permissions',
            'deep_link' => 'instagram://camera', // Opens Instagram camera for manual sharing
        ];
    }

    /**
     * Get user's social media access token
     */
    private function getUserSocialToken(User $user, string $platform): ?string
    {
        // In a real implementation, you would store encrypted tokens in the database
        // For now, we'll check if the user has connected their social accounts
        $socialProfiles = $user->social_profiles ?? [];
        
        return $socialProfiles[$platform]['access_token'] ?? null;
    }

    /**
     * Verify social media connection for a user
     */
    public function verifySocialConnection(User $user, string $platform): bool
    {
        $accessToken = $this->getUserSocialToken($user, $platform);
        
        if (!$accessToken) {
            return false;
        }

        // Cache the verification result for 5 minutes
        $cacheKey = "social_verification_{$user->id}_{$platform}";
        
        return Cache::remember($cacheKey, 300, function () use ($platform, $accessToken) {
            switch ($platform) {
                case 'facebook':
                    return $this->verifyFacebookToken($accessToken);
                case 'twitter':
                    return $this->verifyTwitterToken($accessToken);
                case 'instagram':
                    return $this->verifyInstagramToken($accessToken);
                default:
                    return false;
            }
        });
    }

    /**
     * Verify Facebook access token
     */
    private function verifyFacebookToken(string $accessToken): bool
    {
        try {
            $response = Http::get('https://graph.facebook.com/v18.0/me', [
                'access_token' => $accessToken,
                'fields' => 'id,name',
            ]);
            
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verify Twitter access token
     */
    private function verifyTwitterToken(string $accessToken): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get('https://api.twitter.com/2/users/me');
            
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verify Instagram access token
     */
    private function verifyInstagramToken(string $accessToken): bool
    {
        try {
            $response = Http::get('https://graph.instagram.com/me', [
                'access_token' => $accessToken,
                'fields' => 'id,username',
            ]);
            
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get available social platforms for a user
     */
    public function getAvailablePlatforms(User $user): array
    {
        $platforms = [];
        
        foreach (['facebook', 'twitter', 'instagram'] as $platform) {
            $platforms[$platform] = [
                'connected' => $this->verifySocialConnection($user, $platform),
                'name' => ucfirst($platform),
            ];
        }
        
        return $platforms;
    }

    /**
     * Disconnect social media account
     */
    public function disconnectSocialAccount(User $user, string $platform): bool
    {
        $socialProfiles = $user->social_profiles ?? [];
        
        if (isset($socialProfiles[$platform])) {
            unset($socialProfiles[$platform]);
            $user->social_profiles = $socialProfiles;
            $user->save();
            
            // Clear verification cache
            Cache::forget("social_verification_{$user->id}_{$platform}");
            
            return true;
        }
        
        return false;
    }
}