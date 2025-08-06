<?php

namespace App\Http\Requests\Api;

class ComicSearchRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'query' => 'nullable|string|max:255',
            'genre' => 'nullable|string|max:100',
            'author' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:10',
            'tags' => 'nullable|string',
            'is_free' => 'nullable|boolean',
            'has_mature_content' => 'nullable|boolean',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'max_rating' => 'nullable|numeric|min:0|max:5|gte:min_rating',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0|gte:min_price',
            'publication_year_from' => 'nullable|integer|min:1900|max:' . date('Y'),
            'publication_year_to' => 'nullable|integer|min:1900|max:' . date('Y') . '|gte:publication_year_from',
            'sort_by' => 'nullable|string|in:title,published_at,average_rating,total_readers,page_count,price',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'max_rating.gte' => 'The maximum rating must be greater than or equal to the minimum rating.',
            'max_price.gte' => 'The maximum price must be greater than or equal to the minimum price.',
            'publication_year_to.gte' => 'The end publication year must be greater than or equal to the start year.',
            'per_page.max' => 'You can request a maximum of 100 items per page.',
        ];
    }
}