<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Models\ComicPage;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ComicUploadController extends Controller
{
    protected CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * Upload pages for an existing comic
     */
    public function uploadPages(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'pages' => 'required|array|min:1',
            'pages.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240', // 10MB max
            'start_page' => 'integer|min:1',
        ]);

        $startPage = $request->get('start_page', 1);
        $results = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($request->file('pages') as $index => $file) {
                $pageNumber = $startPage + $index;

                // Upload to Cloudinary
                $uploadResult = $this->cloudinary->uploadPage(
                    $file,
                    $comic->slug,
                    $pageNumber
                );

                if ($uploadResult['success']) {
                    // Create or update page record
                    $page = ComicPage::updateOrCreate(
                        [
                            'comic_id' => $comic->id,
                            'page_number' => $pageNumber,
                        ],
                        [
                            'image_url' => $uploadResult['url'],
                            'image_path' => $uploadResult['public_id'],
                            'width' => $uploadResult['width'],
                            'height' => $uploadResult['height'],
                            'file_size' => $uploadResult['size'],
                        ]
                    );

                    $results[] = [
                        'page_number' => $pageNumber,
                        'url' => $uploadResult['url'],
                        'success' => true,
                    ];
                } else {
                    $errors[] = [
                        'page_number' => $pageNumber,
                        'error' => $uploadResult['error'],
                    ];
                }
            }

            // Update comic page count
            $comic->page_count = $comic->pages()->count();
            $comic->save();

            DB::commit();

            return response()->json([
                'message' => 'Pages uploaded successfully',
                'uploaded' => count($results),
                'failed' => count($errors),
                'pages' => $results,
                'errors' => $errors,
                'total_pages' => $comic->page_count,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Comic page upload failed', [
                'comic_id' => $comic->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Upload failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload cover image for a comic
     */
    public function uploadCover(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'cover' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120', // 5MB max
        ]);

        try {
            $result = $this->cloudinary->uploadCover(
                $request->file('cover'),
                $comic->slug
            );

            if ($result['success']) {
                // Store the full URL for Cloudinary images
                $comic->cover_image_path = $result['url'];
                $comic->save();

                return response()->json([
                    'message' => 'Cover uploaded successfully',
                    'url' => $result['url'],
                    'public_id' => $result['public_id'],
                ]);
            }

            return response()->json([
                'message' => 'Upload failed',
                'error' => $result['error'],
            ], 500);
        } catch (\Exception $e) {
            Log::error('Cover upload failed', [
                'comic_id' => $comic->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Upload failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new comic with pages from local directory
     */
    public function createFromDirectory(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'directory' => 'required|string',
            'description' => 'nullable|string',
            'genre' => 'nullable|string',
            'is_free' => 'boolean',
        ]);

        $directory = public_path($request->directory);

        if (!is_dir($directory)) {
            return response()->json([
                'message' => 'Directory not found',
                'path' => $directory,
            ], 404);
        }

        // Get all image files sorted by name
        $files = glob($directory . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        sort($files, SORT_NATURAL);

        if (empty($files)) {
            return response()->json([
                'message' => 'No image files found in directory',
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Create the comic
            $comic = Comic::create([
                'title' => $request->title,
                'slug' => Str::slug($request->title),
                'author' => $request->author,
                'description' => $request->description ?? '',
                'genre' => $request->genre ?? 'General',
                'is_free' => $request->boolean('is_free', true),
                'is_visible' => true,
                'published_at' => now(),
                'page_count' => count($files),
            ]);

            $results = [];
            $errors = [];

            // Upload first page as cover
            $coverResult = $this->cloudinary->uploadCover($files[0], $comic->slug);
            if ($coverResult['success']) {
                // Store the full URL for Cloudinary images
                $comic->cover_image_path = $coverResult['url'];
                $comic->save();
            }

            // Upload all pages
            foreach ($files as $index => $filePath) {
                $pageNumber = $index + 1;

                $uploadResult = $this->cloudinary->uploadPage(
                    $filePath,
                    $comic->slug,
                    $pageNumber
                );

                if ($uploadResult['success']) {
                    ComicPage::create([
                        'comic_id' => $comic->id,
                        'page_number' => $pageNumber,
                        'image_url' => $uploadResult['url'],
                        'image_path' => $uploadResult['public_id'],
                        'width' => $uploadResult['width'],
                        'height' => $uploadResult['height'],
                        'file_size' => $uploadResult['size'],
                    ]);

                    $results[] = [
                        'page_number' => $pageNumber,
                        'url' => $uploadResult['url'],
                    ];
                } else {
                    $errors[] = [
                        'page_number' => $pageNumber,
                        'file' => basename($filePath),
                        'error' => $uploadResult['error'],
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Comic created successfully',
                'comic' => [
                    'id' => $comic->id,
                    'slug' => $comic->slug,
                    'title' => $comic->title,
                    'cover_url' => $comic->cover_image_url,
                ],
                'pages_uploaded' => count($results),
                'pages_failed' => count($errors),
                'errors' => $errors,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Comic creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Comic creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a comic and all its pages from Cloudinary
     */
    public function deleteComic(Comic $comic): JsonResponse
    {
        try {
            // Delete from Cloudinary
            $this->cloudinary->deleteFolder("pages/{$comic->slug}");
            $this->cloudinary->deleteFolder("covers/{$comic->slug}");

            // Delete from database
            $comic->pages()->delete();
            $comic->delete();

            return response()->json([
                'message' => 'Comic deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Comic deletion failed', [
                'comic_id' => $comic->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Deletion failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder pages for a comic
     */
    public function reorderPages(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'page_order' => 'required|array',
            'page_order.*' => 'integer|exists:comic_pages,id',
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->page_order as $newPosition => $pageId) {
                ComicPage::where('id', $pageId)
                    ->where('comic_id', $comic->id)
                    ->update(['page_number' => $newPosition + 1]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Pages reordered successfully',
                'pages' => $comic->fresh()->pages->map(fn($p) => [
                    'id' => $p->id,
                    'page_number' => $p->page_number,
                    'url' => $p->image_url,
                ]),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Reorder failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete specific pages from a comic
     */
    public function deletePages(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'page_ids' => 'required|array',
            'page_ids.*' => 'integer|exists:comic_pages,id',
        ]);

        try {
            $pages = ComicPage::whereIn('id', $request->page_ids)
                ->where('comic_id', $comic->id)
                ->get();

            foreach ($pages as $page) {
                // Delete from Cloudinary
                if ($page->image_path) {
                    $this->cloudinary->deleteImage($page->image_path);
                }
                $page->delete();
            }

            // Renumber remaining pages
            $remainingPages = $comic->pages()->orderBy('page_number')->get();
            foreach ($remainingPages as $index => $page) {
                $page->update(['page_number' => $index + 1]);
            }

            // Update comic page count
            $comic->page_count = $comic->pages()->count();
            $comic->save();

            return response()->json([
                'message' => 'Pages deleted successfully',
                'deleted_count' => count($request->page_ids),
                'remaining_pages' => $comic->page_count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Deletion failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
