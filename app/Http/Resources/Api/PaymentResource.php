<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'comic_id' => $this->comic_id,
            'stripe_payment_intent_id' => $this->stripe_payment_intent_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_method_details' => $this->payment_method_details,
            'failure_reason' => $this->failure_reason,
            'refund_amount' => $this->refund_amount,
            'refund_reason' => $this->refund_reason,
            'refunded_at' => $this->refunded_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'comic' => $this->whenLoaded('comic', function () {
                return [
                    'id' => $this->comic->id,
                    'title' => $this->comic->title,
                    'slug' => $this->comic->slug,
                    'cover_image_url' => $this->comic->getCoverImageUrl(),
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            
            // Computed fields
            'is_refundable' => $this->isRefundable(),
            'net_amount' => $this->amount - ($this->refund_amount ?? 0),
            'formatted_amount' => $this->getFormattedAmount(),
            'transaction_fee' => $this->getTransactionFee(),
        ];
    }
}