<?php

namespace Database\Factories;

use App\Models\Comic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentType = 'single';
        
        return [
            'user_id' => function () {
                $existingUserIds = User::query()->pluck('id');

                // Keep analytics fixtures stable (large user pools) without
                // inflating user counts, but isolate smaller test setups.
                if ($existingUserIds->count() >= 10) {
                    return $existingUserIds->first();
                }

                return User::factory()->create()->id;
            },
            'comic_id' => $paymentType === 'subscription'
                ? null
                : fn () => Comic::query()->value('id') ?? Comic::factory()->create()->id,
            'stripe_payment_intent_id' => 'pi_' . $this->faker->unique()->regexify('[a-zA-Z0-9]{24}'),
            'stripe_payment_method_id' => null,
            'stripe_refund_id' => null,
            'amount' => $this->faker->randomFloat(2, 0.99, 99.99),
            'refund_amount' => null,
            'currency' => 'usd',
            'status' => 'pending',
            'payment_type' => $paymentType,
            'subscription_type' => null,
            'bundle_discount_percent' => null,
            'stripe_metadata' => [
                'user_email' => $this->faker->email,
                'purchase_type' => $paymentType,
            ],
            'paid_at' => null,
            'refunded_at' => null,
            'failure_reason' => null,
            'retry_count' => 0,
            'last_retry_at' => null,
        ];
    }

    /**
     * Indicate that the payment is successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
            'paid_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'failure_reason' => null,
        ]);
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
            'refunded_at' => null,
            'failure_reason' => null,
        ]);
    }

    /**
     * Indicate that the payment has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'paid_at' => null,
            'refunded_at' => null,
            'failure_reason' => $this->faker->randomElement([
                'Your card was declined.',
                'Insufficient funds.',
                'Your card has expired.',
                'Your card number is incorrect.',
                'Your card\'s security code is incorrect.',
            ]),
        ]);
    }

    /**
     * Indicate that the payment has been refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'paid_at' => $this->faker->dateTimeBetween('-1 month', '-1 week'),
            'refunded_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'refund_amount' => $attributes['amount'] ?? $this->faker->randomFloat(2, 0.99, 99.99),
            'stripe_refund_id' => 're_' . $this->faker->regexify('[a-zA-Z0-9]{24}'),
        ]);
    }

    /**
     * Indicate that the payment is for a single comic.
     */
    public function single(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => 'single',
            'comic_id' => Comic::factory(),
            'subscription_type' => null,
            'bundle_discount_percent' => null,
        ]);
    }

    /**
     * Indicate that the payment is for a bundle.
     */
    public function bundle(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => 'bundle',
            'comic_id' => Comic::factory(),
            'subscription_type' => null,
            'bundle_discount_percent' => $this->faker->randomFloat(2, 10.00, 25.00),
        ]);
    }

    /**
     * Indicate that the payment is for a subscription.
     */
    public function subscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => 'subscription',
            'comic_id' => null,
            'subscription_type' => $this->faker->randomElement(['monthly', 'yearly']),
            'bundle_discount_percent' => null,
            'amount' => $this->faker->randomElement([9.99, 99.99]), // Monthly or yearly price
        ]);
    }

    /**
     * Indicate that the payment can be retried.
     */
    public function retriable(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'retry_count' => $this->faker->numberBetween(0, 2),
            'last_retry_at' => $this->faker->optional()->dateTimeBetween('-1 day', 'now'),
        ]);
    }
}
