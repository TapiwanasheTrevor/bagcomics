<?php

namespace App\Http\Requests\Api;

class ReadingProgressRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'current_page' => 'required|integer|min:1',
            'total_pages' => 'nullable|integer|min:1|gte:current_page',
            'reading_time_seconds' => 'nullable|integer|min:0',
            'session_metadata' => 'nullable|array',
            'session_metadata.device_type' => 'nullable|string|max:50',
            'session_metadata.screen_size' => 'nullable|string|max:20',
            'session_metadata.zoom_level' => 'nullable|numeric|min:0.1|max:10',
            'session_metadata.reading_mode' => 'nullable|string|in:single_page,double_page,continuous',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'current_page.min' => 'The current page must be at least 1.',
            'total_pages.gte' => 'The total pages must be greater than or equal to the current page.',
            'reading_time_seconds.min' => 'Reading time cannot be negative.',
            'session_metadata.zoom_level.min' => 'Zoom level must be at least 0.1.',
            'session_metadata.zoom_level.max' => 'Zoom level cannot exceed 10.',
        ];
    }
}