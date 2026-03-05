<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class EnhancedPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create([
            'price' => 9.99,
            'is_free' => false,
            'is_visible' => true,
        ]);
    }

    /** @test */
    public function it_creates_payment_intent_with_current_api_contract(): void
    {
        Sanctum::actingAs($this->user);

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentIntent = (object) [
            'id' => 'pi_test_123',
            'client_secret' => 'pi_test_123_secret_456',
        ];

        $mockPaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn($mockPaymentIntent);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson("/api/payments/comics/{$this->comic->id}/intent", [
            'currency' => 'USD',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'payment_intent' => [
                    'id' => 'pi_test_123',
                ],
                'client_secret' => 'pi_test_123_secret_456',
            ]);
    }

    /** @test */
    public function it_handles_payment_intent_service_errors_gracefully(): void
    {
        Sanctum::actingAs($this->user);

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andThrow(new \RuntimeException('gateway unavailable'));

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson("/api/payments/comics/{$this->comic->id}/intent");

        $response->assertStatus(400)
            ->assertJsonPath('error.message', 'Failed to create payment intent. Please try again.')
            ->assertJsonPath('error.code', 'PAYMENT_INTENT_FAILED');
    }

    /** @test */
    public function it_confirms_payment_with_current_api_contract(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
            'amount' => 9.99,
        ]);

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with(Mockery::type(User::class), 'pi_test_123')
            ->andReturn($payment);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson('/api/payments/confirm', [
            'payment_intent_id' => 'pi_test_123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'payment' => [
                    'id',
                    'type',
                    'amount',
                ],
                'message',
            ]);
    }

    /** @test */
    public function it_handles_payment_confirmation_errors(): void
    {
        Sanctum::actingAs($this->user);

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('confirmPaymentIntent')
            ->once()
            ->andThrow(new \RuntimeException('intent not found'));

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson('/api/payments/confirm', [
            'payment_intent_id' => 'pi_nonexistent',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'PAYMENT_CONFIRMATION_FAILED');
    }

    /** @test */
    public function it_returns_filtered_payment_history(): void
    {
        Sanctum::actingAs($this->user);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
        ]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'failed',
        ]);

        Payment::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'succeeded',
        ]);

        $response = $this->getJson('/api/payments/history?status=succeeded');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'pagination',
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('succeeded', $response->json('data.0.status'));
    }

    /** @test */
    public function it_processes_refund_requests(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
        ]);

        $mockRefund = (object) [
            'id' => 'ref_123',
            'amount' => 999,
        ];

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('refundPayment')
            ->once()
            ->andReturn($mockRefund);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson("/api/payments/{$payment->id}/refund", [
            'reason' => 'Requested by customer',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Refund request submitted successfully',
            ]);
    }

    /** @test */
    public function it_denies_refund_requests_for_other_users_payments(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $otherUser->id,
            'comic_id' => $this->comic->id,
        ]);

        $response = $this->postJson("/api/payments/{$payment->id}/refund");

        $response->assertStatus(403);
    }

    /** @test */
    public function it_returns_user_payment_statistics(): void
    {
        Sanctum::actingAs($this->user);

        Payment::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'succeeded',
            'amount' => 9.99,
        ]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'refunded',
            'amount' => 4.99,
            'refund_amount' => 4.99,
        ]);

        $response = $this->getJson('/api/payments/statistics/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_spent',
                'total_purchases',
                'total_refunds',
                'average_purchase_amount',
                'recent_purchases',
                'monthly_spending',
            ]);
    }

    /** @test */
    public function it_reports_unimplemented_enhanced_routes_as_not_found(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $receiptResponse = $this->get("/api/payments/{$payment->id}/receipt");
        $invoiceResponse = $this->get("/api/payments/{$payment->id}/invoice");
        $retryResponse = $this->postJson("/api/payments/{$payment->id}/retry");
        $bundleResponse = $this->postJson('/api/payments/bundle/intent', [
            'comic_ids' => [$this->comic->id],
        ]);
        $subscriptionResponse = $this->postJson('/api/payments/subscription/intent', [
            'subscription_type' => 'monthly',
        ]);

        $this->assertSame(404, $receiptResponse->status());
        $this->assertSame(404, $invoiceResponse->status());
        $this->assertSame(404, $retryResponse->status());
        $this->assertSame(404, $bundleResponse->status());
        $this->assertSame(404, $subscriptionResponse->status());
    }
}
