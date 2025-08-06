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
        $paymentType = $this->faker->randomElement(['single', 'bundle', 'subscription']);
        
        return [
            'user_id' => User::factory(),
            'comic_id' => $paymentType === 'subscription' ? null : Comic::factory(),
            'stripe_payment_intent_id' => 'pi_' . $this->faker->unique()->regexify('[a-zA-Z0-9]{24}'),
            'stripe_payment_method_id' => $this->faker->optional()->regexify('pm_[a-zA-Z0-9]{24}'),
            'stripe_refund_id' => $this->faker->optional()->regexify('re_[a-zA-Z0-9]{24}'),
            'amount' => $this->faker->randomFloat(2, 0.99, 99.99),
            'refund_amount' => $this->faker->optional()->randomFloat(2, 0.99, 50.00),
            'currency' => 'usd',
            'status' => $this->faker->randomElement(['pending', 'succeeded', 'failed', 'canceled', 'refunded']),
            'payment_type' => $paymentType,
            'subscription_type' => $paymentType === 'subscription' 
                ? $this->faker->randomElement(['monthly', 'yearly']) 
                : null,
            'bundle_discount_percent' => $paymentType === 'bundle' 
                ? $this->faker->randomFloat(2, 5.00, 25.00) 
                : null,
            'stripe_metadata' => [
                'user_email' => $this->faker->email,
                'purchase_type' => $paymentType,
            ],
            'paid_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'refunded_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
            'failure_reason' => $this->faker->optional()->sentence,
            'retry_count' => $this->faker->numberBetween(0, 3),
            'last_retry_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
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
