<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'comic_id',
        'stripe_payment_intent_id',
        'stripe_payment_method_id',
        'stripe_refund_id',
        'amount',
        'refund_amount',
        'currency',
        'status',
        'payment_type',
        'subscription_type',
        'bundle_discount_percent',
        'stripe_metadata',
        'paid_at',
        'refunded_at',
        'failure_reason',
        'retry_count',
        'last_retry_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'bundle_discount_percent' => 'decimal:2',
        'stripe_metadata' => 'array',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'canceled']);
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->refund_amount > 0 && $this->refund_amount < $this->amount;
    }

    public function canBeRetried(): bool
    {
        return $this->isFailed() && $this->retry_count < 3;
    }

    public function markAsSucceeded(): void
    {
        $this->update([
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    public function markAsRefunded(float $refundAmount = null): void
    {
        $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refund_amount' => $refundAmount ?? $this->amount,
        ]);
    }

    public function incrementRetryCount(): void
    {
        $this->update([
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now(),
        ]);
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getFormattedRefundAmountAttribute(): ?string
    {
        return $this->refund_amount ? '$' . number_format($this->refund_amount, 2) : null;
    }

    public function getPaymentTypeDisplayAttribute(): string
    {
        return match($this->payment_type) {
            'single' => 'Single Purchase',
            'bundle' => 'Bundle Purchase',
            'subscription' => 'Subscription',
            default => 'Unknown',
        };
    }

    // Scopes
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'succeeded');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', ['failed', 'canceled']);
    }

    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', 'refunded');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('payment_type', $type);
    }

    public function scopeInDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
