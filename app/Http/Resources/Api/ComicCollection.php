<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ComicCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return ComicResource::collection($this->collection)->toArray($request);
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        return response()->json([
            'data' => ComicResource::collection($this->collection),
            'pagination' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
            ],
            'meta' => [
                'total_count' => $this->collection->count(),
                'filters_applied' => $this->getAppliedFilters($request),
                'sort_applied' => [
                    'sort_by' => $request->get('sort_by', 'published_at'),
                    'sort_order' => $request->get('sort_order', 'desc'),
                ],
            ],
        ]);
    }

    /**
     * Get the applied filters from the request.
     */
    protected function getAppliedFilters(Request $request): array
    {
        $filters = [];
        
        $filterKeys = [
            'genre', 'author', 'publisher', 'language', 'tags',
            'is_free', 'has_mature_content', 'min_rating', 'max_rating',
            'min_price', 'max_price', 'publication_year_from', 'publication_year_to'
        ];

        foreach ($filterKeys as $key) {
            if ($request->filled($key)) {
                $filters[$key] = $request->get($key);
            }
        }

        return $filters;
    }
}