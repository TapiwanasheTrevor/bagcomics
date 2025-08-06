<?php

namespace App\Http\Requests\Api;

class UserLibraryRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $method = $this->getMethod();
        
        if ($method === 'POST' && $this->routeIs('*.add')) {
            return $this->getAddToLibraryRules();
        }
        
        if ($method === 'POST' && $this->routeIs('*.rating')) {
            return $this->getRatingRules();
        }
        
        if ($method === 'POST' && $this->routeIs('*.reading-time')) {
            return $this->getReadingTimeRules();
        }
        
        if ($method === 'POST' && $this->routeIs('*.progress')) {
            return $this->getProgressRules();
        }
        
        if ($method === 'GET') {
            return $this->getFilterRules();
        }
        
        return [];
    }

    /**
     * Get validation rules for adding to library.
     */
    protected function getAddToLibraryRules(): array
    {
        return [
            'access_type' => 'required|string|in:purchased,free,subscription',
            'purchase_price' => 'sometimes|numeric|min:0',
        ];
    }

    /**
     * Get validation rules for rating.
     */
    protected function getRatingRules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'sometimes|string|max:2000',
        ];
    }

    /**
     * Get validation rules for reading time update.
     */
    protected function getReadingTimeRules(): array
    {
        return [
            'reading_time_seconds' => 'required|integer|min:0',
            'session_start' => 'sometimes|date',
            'session_end' => 'sometimes|date|after:session_start',
        ];
    }

    /**
     * Get validation rules for progress update.
     */
    protected function getProgressRules(): array
    {
        return [
            'current_page' => 'required|integer|min:1',
            'total_pages' => 'sometimes|integer|min:1',
            'reading_time_seconds' => 'sometimes|integer|min:0',
        ];
    }

    /**
     * Get validation rules for filtering.
     */
    protected function getFilterRules(): array
    {
        return [
            'status' => 'sometimes|string|in:all,reading,completed,not_started',
            'access_type' => 'sometimes|string|in:purchased,free,subscription',
            'is_favorite' => 'sometimes|boolean',
            'genre' => 'sometimes|string|max:100',
            'rating' => 'sometimes|integer|min:1|max:5',
            'sort_by' => 'sometimes|string|in:title,purchased_at,last_read_at,rating,progress',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ];
    }
}