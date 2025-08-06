<?php

namespace App\Http\Requests\Api;

class ReviewRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string|min:10|max:2000',
            'is_spoiler' => 'nullable|boolean',
            'recommend_to_friends' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'A rating is required for the review.',
            'rating.min' => 'The rating must be at least 1 star.',
            'rating.max' => 'The rating cannot exceed 5 stars.',
            'content.required' => 'Review content is required.',
            'content.min' => 'The review must be at least 10 characters long.',
            'content.max' => 'The review cannot exceed 2000 characters.',
        ];
    }
}