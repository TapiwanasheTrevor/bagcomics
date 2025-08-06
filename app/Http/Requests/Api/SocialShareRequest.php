<?php

namespace App\Http\Requests\Api;

class SocialShareRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'platform' => 'required|string|in:facebook,twitter,instagram,linkedin,reddit,pinterest',
            'share_type' => 'required|string|in:comic_discovery,reading_completion,achievement,recommendation',
            'message' => 'nullable|string|max:500',
            'include_progress' => 'nullable|boolean',
            'include_rating' => 'nullable|boolean',
            'custom_hashtags' => 'nullable|array|max:10',
            'custom_hashtags.*' => 'string|max:50|regex:/^[a-zA-Z0-9_]+$/',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'platform.in' => 'The selected platform is not supported.',
            'share_type.in' => 'The selected share type is not valid.',
            'message.max' => 'The share message cannot exceed 500 characters.',
            'custom_hashtags.max' => 'You can include a maximum of 10 hashtags.',
            'custom_hashtags.*.regex' => 'Hashtags can only contain letters, numbers, and underscores.',
        ];
    }
}