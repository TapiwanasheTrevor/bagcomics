<?php

namespace App\Http\Requests\Api;

class PaymentRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'payment_method' => 'nullable|string|in:card,paypal,apple_pay,google_pay',
            'currency' => 'nullable|string|size:3|in:USD,EUR,GBP,CAD,AUD',
            'save_payment_method' => 'nullable|boolean',
            'billing_address' => 'nullable|array',
            'billing_address.line1' => 'required_with:billing_address|string|max:255',
            'billing_address.line2' => 'nullable|string|max:255',
            'billing_address.city' => 'required_with:billing_address|string|max:100',
            'billing_address.state' => 'nullable|string|max:100',
            'billing_address.postal_code' => 'required_with:billing_address|string|max:20',
            'billing_address.country' => 'required_with:billing_address|string|size:2',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'currency.size' => 'Currency must be a 3-letter ISO code.',
            'currency.in' => 'Currency must be one of: USD, EUR, GBP, CAD, AUD.',
            'billing_address.country.size' => 'Country must be a 2-letter ISO code.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'billing_address.line1' => 'billing address line 1',
            'billing_address.line2' => 'billing address line 2',
            'billing_address.postal_code' => 'postal code',
        ];
    }
}