<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Services\SocialSharingService;
use App\Services\SocialMediaApiService;
use App\Services\SocialMetadataService;
use App\Services\AchievementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SocialSharingController extends Controller
{
    private SocialSharingService $socialSharingService;
    private SocialMediaApiService $socialMediaApiService;
    private SocialMetadataService $socialMetadataService;
    private AchievementService $achievementService;

    public function __construct(
        SocialSharingService $socialSharingService,
        SocialMediaApiService $socialMediaApiService,
        SocialMetadataService $socialMetadataService,
        AchievementService $achievementService
    ) {
        $this->socialSharingService = $socialSharingService;
        $this->socialMediaApiService = $socialMediaApiService;
        $this->socialMetadataService = $socialMetadataService;
        $this->achievementService = $achievementService;
    }

    /**
     * Share a comic to social media
     */
    public function shareComic(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'platform' => ['required', Rule::in(['facebook', 'twitter', 'instagram', 'whatsapp', 'copy_link', 'native'])],
            'share_type' => ['required', Rule::in(['discovery', 'achievement', 'recommendation', 'review', 'comic_discovery', 'reading_achievement'])],
            'message' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
            'metadata.title' => 'nullable|string',
            'metadata.url' => 'nullable|url',
            'metadata.message' => 'nullable|string',
            'metadata.cover_image' => 'nullable|string',
            'include_image' => 'boolean',
            'auto_post' => 'boolean',
        ]);

        $user = Auth::user(); // Can be null for guest users
        $platform = $request->input('platform');
        $shareType = $request->input('share_type');
        $customMessage = $request->input('message');
        $metadata = $request->input('metadata', []);
        $includeImage = $request->boolean('include_image', true);
        $autoPost = $request->boolean('auto_post', false);

        try {
            // Create social share record only for authenticated users
            $socialShare = null;
            if ($user) {
                $socialShare = $this->socialSharingService->shareComic(
                    $user,
                    $comic,
                    $platform,
                    $shareType,
                    [
                        'custom_message' => $customMessage,
                        'include_image' => $includeImage,
                        'metadata' => $metadata,
                    ]
                );
            }

            // Generate sharing metadata
            $sharingMetadata = $this->socialMetadataService->generateSharingPreview($comic, $shareType, [
                'custom_message' => $customMessage,
            ]);

            $response = [
                'success' => true,
                'share_id' => $socialShare?->id,
                'share_url' => $socialShare?->getShareUrl() ?? route('comics.show', $comic),
                'metadata' => $sharingMetadata,
                'platform' => $platform,
                'share_type' => $shareType,
            ];

            // Auto-post to social media if requested and user has connected account
            if ($user && $autoPost && $this->socialMediaApiService->verifySocialConnection($user, $platform)) {
                $message = $customMessage ?: $sharingMetadata['description'];
                
                $postResult = match ($platform) {
                    'facebook' => $this->socialMediaApiService->postToFacebook($user, $comic, $message, ['include_image' => $includeImage]),
                    'twitter' => $this->socialMediaApiService->postToTwitter($user, $comic, $message, ['include_image' => $includeImage]),
                    'instagram' => $this->socialMediaApiService->shareToInstagram($user, $comic, $message, ['include_image' => $includeImage]),
                    default => ['success' => false, 'error' => 'Unsupported platform'],
                };

                $response['auto_post_result'] = $postResult;

                // Update social share with post result
                if ($postResult['success']) {
                    $socialShare->setShareUrl($postResult['post_url'] ?? $postResult['tweet_url'] ?? '');
                }
            }

            // Check for achievements (only for authenticated users)
            if ($user) {
                $achievements = $this->achievementService->checkAchievements($user, 'social_share', [
                    'comic' => $comic,
                    'platform' => $platform,
                ]);

                if (!empty($achievements)) {
                    $response['achievements'] = $achievements;
                }
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to share comic: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sharing metadata for a comic
     */
    public function getSharingMetadata(Comic $comic, Request $request): JsonResponse
    {
        $request->validate([
            'platform' => ['nullable', Rule::in(['facebook', 'twitter', 'instagram', 'general'])],
            'context' => ['nullable', Rule::in(['general', 'achievement', 'recommendation', 'review'])],
        ]);

        $platform = $request->input('platform', 'general');
        $context = $request->input('context', 'general');

        try {
            $metadata = [
                'general' => $this->socialMetadataService->generateSharingPreview($comic, $context),
                'open_graph' => $this->socialMetadataService->generateOpenGraphMetadata($comic),
                'twitter_card' => $this->socialMetadataService->generateTwitterCardMetadata($comic),
                'structured_data' => $this->socialMetadataService->generateStructuredData($comic),
                'hashtags' => $this->socialMetadataService->generateHashtags($comic, $platform),
            ];

            if ($platform !== 'general') {
                $metadata['platform_specific'] = match ($platform) {
                    'facebook' => $metadata['open_graph'],
                    'twitter' => $metadata['twitter_card'],
                    'instagram' => [
                        'caption' => $metadata['general']['description'],
                        'hashtags' => $metadata['hashtags'],
                    ],
                    default => $metadata['general'],
                };
            }

            return response()->json([
                'success' => true,
                'metadata' => $metadata,
                'comic' => [
                    'id' => $comic->id,
                    'title' => $comic->title,
                    'slug' => $comic->slug,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate metadata: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's sharing history
     */
    public function getSharingHistory(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => ['nullable', Rule::in(['facebook', 'twitter', 'instagram'])],
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $platform = $request->input('platform');
        $limit = $request->input('limit', 20);

        try {
            $sharingHistory = $this->socialSharingService->getUserSharingHistory($user, $platform);
            
            $paginatedHistory = $sharingHistory->take($limit)->map(function ($share) {
                return [
                    'id' => $share->id,
                    'platform' => $share->platform,
                    'share_type' => $share->share_type,
                    'comic' => [
                        'id' => $share->comic->id,
                        'title' => $share->comic->title,
                        'slug' => $share->comic->slug,
                        'cover_image' => $share->comic->cover_image_path,
                    ],
                    'share_url' => $share->getShareUrl(),
                    'created_at' => $share->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'sharing_history' => $paginatedHistory,
                'total_shares' => $sharingHistory->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get sharing history: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comic sharing statistics
     */
    public function getComicSharingStats(Comic $comic): JsonResponse
    {
        try {
            $stats = $this->socialSharingService->getComicSharingStats($comic);

            return response()->json([
                'success' => true,
                'comic_id' => $comic->id,
                'sharing_stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get sharing stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available social platforms for user
     */
    public function getAvailablePlatforms(): JsonResponse
    {
        $user = Auth::user();

        try {
            $platforms = $this->socialMediaApiService->getAvailablePlatforms($user);

            return response()->json([
                'success' => true,
                'platforms' => $platforms,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get available platforms: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Connect social media account
     */
    public function connectSocialAccount(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => ['required', Rule::in(['facebook', 'twitter', 'instagram'])],
            'access_token' => 'required|string',
            'profile_data' => 'nullable|array',
        ]);

        $user = Auth::user();
        $platform = $request->input('platform');
        $accessToken = $request->input('access_token');
        $profileData = $request->input('profile_data', []);

        try {
            // Store encrypted access token and profile data
            $socialProfiles = $user->social_profiles ?? [];
            $socialProfiles[$platform] = [
                'access_token' => encrypt($accessToken),
                'profile_data' => $profileData,
                'connected_at' => now()->toISOString(),
            ];

            $user->social_profiles = $socialProfiles;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => ucfirst($platform) . ' account connected successfully',
                'platform' => $platform,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to connect social account: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disconnect social media account
     */
    public function disconnectSocialAccount(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => ['required', Rule::in(['facebook', 'twitter', 'instagram'])],
        ]);

        $user = Auth::user();
        $platform = $request->input('platform');

        try {
            $success = $this->socialMediaApiService->disconnectSocialAccount($user, $platform);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => ucfirst($platform) . ' account disconnected successfully',
                    'platform' => $platform,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Account was not connected or already disconnected',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to disconnect social account: ' . $e->getMessage(),
            ], 500);
        }
    }
}