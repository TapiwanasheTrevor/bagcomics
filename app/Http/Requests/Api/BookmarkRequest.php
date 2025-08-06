<?php

namespace App\Http\Requests\Api;

class BookmarkRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'page_number' => 'required|integer|min:1',
            'note' => 'nullable|string|max:500',
            'bookmark_type' => 'nullable|string|in:manual,auto,favorite_scene',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'page_number.min' => 'The page number must be at least 1.',
            'note.max' => 'The bookmark note cannot exceed 500 characters.',
        ];
    }
}