<?php

namespace App\Http\Requests\Api;

class ComicFilterRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'genre' => 'sometimes|string|max:100',
            'author' => 'sometimes|string|max:255',
            'publisher' => 'sometimes|string|max:255',
            'language' => 'sometimes|string|size:2',
            'tags' => 'sometimes|string|max:500',
            'is_free' => 'sometimes|boolean',
            'has_mature_content' => 'sometimes|boolean',
            'search' => 'sometimes|string|max:255',
            'sort_by' => 'sometimes|string|in:title,published_at,average_rating,total_readers,page_count,price',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'min_rating' => 'sometimes|numeric|min:0|max:5',
            'max_rating' => 'sometimes|numeric|min:0|max:5|gte:min_rating',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0|gte:min_price',
            'publication_year_from' => 'sometimes|integer|min:1900|max:' . date('Y'),
            'publication_year_to' => 'sometimes|integer|min:1900|max:' . date('Y') . '|gte:publication_year_from',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'language.size' => 'Language must be a 2-character ISO code.',
            'sort_by.in' => 'Sort field must be one of: title, published_at, average_rating, total_readers, page_count, price.',
            'sort_order.in' => 'Sort order must be either asc or desc.',
            'per_page.max' => 'Maximum 100 items per page allowed.',
            'max_rating.gte' => 'Maximum rating must be greater than or equal to minimum rating.',
            'max_price.gte' => 'Maximum price must be greater than or equal to minimum price.',
            'publication_year_to.gte' => 'End year must be greater than or equal to start year.',
        ];
    }
}