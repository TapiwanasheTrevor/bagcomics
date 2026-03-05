<?php

namespace Tests\Feature\Api;

use App\Models\Comic;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create([
            'price' => 9.99,
            'is_free' => false,
            'is_visible' => true
        ]);
    }

    public function test_create_payment_intent_requires_authentication(): void
    {
        $response = $this->postJson("/api/payments/comics/{$this->comic->id}/intent");

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_create_payment_intent_successfully(): void
    {
        Sanctum::actingAs($this->user);

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentIntent = (object) [
            'id' => 'pi_test_123',
            'client_secret' => 'pi_test_123_secret_456'
        ];

        $mockPaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->with(
                Mockery::type(User::class),
                Mockery::on(fn ($comic) => $comic instanceof Comic && $comic->id === $this->comic->id),
                Mockery::type('array')
            )
            ->andReturn($mockPaymentIntent);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson("/api/payments/comics/{$this->comic->id}/intent");

        $response->assertStatus(200)
            ->assertJson([
                'payment_intent' => [
                    'id' => 'pi_test_123'
                ],
                'client_secret' => 'pi_test_123_secret_456'
            ]);
    }

    public function test_create_payment_intent_with_invalid_comic(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/payments/comics/999/intent');

        $response->assertStatus(404);
    }

    public function test_create_payment_intent_validates_request_data(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/payments/comics/{$this->comic->id}/intent", [
            'currency' => 'INVALID',
            'payment_method' => 'invalid_method'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_process_payment_successfully(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded'
        ]);

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('processPayment')
            ->once()
            ->with(Mockery::type(User::class), 'pi_test_123')
            ->andReturn($payment);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson('/api/payments/process', [
            'payment_intent_id' => 'pi_test_123',
            'comic_id' => $this->comic->id
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Payment processed successfully'
            ]);
    }

    public function test_process_payment_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/payments/process', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_intent_id']);
    }

    public function test_get_payment_history(): void
    {
        Sanctum::actingAs($this->user);

        $payments = Payment::factory()->count(3)->create([
            'user_id' => $this->user->id
        ]);

        // Create payment for another user (should not be included)
        Payment::factory()->create([
            'user_id' => User::factory()->create()->id
        ]);

        $response = $this->getJson('/api/payments/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'comic_id',
                        'amount',
                        'status',
                        'created_at'
                    ]
                ],
                'pagination'
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_payment_history_with_filters(): void
    {
        Sanctum::actingAs($this->user);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'succeeded',
            'created_at' => now()->subDays(5)
        ]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
            'created_at' => now()->subDays(10)
        ]);

        $response = $this->getJson('/api/payments/history?status=succeeded&from_date=' . now()->subDays(7)->toDateString());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_payment_details(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id
        ]);

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $payment->id,
                'user_id' => $this->user->id,
                'comic_id' => $this->comic->id
            ]);
    }

    public function test_show_payment_denies_access_to_other_users_payments(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PAYMENT_ACCESS_DENIED');
    }

    public function test_request_refund_successfully(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'succeeded'
        ]);

        $mockRefund = (object) ['id' => 'ref_123', 'amount' => 999];

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('refundPayment')
            ->once()
            ->with(
                Mockery::on(fn ($paymentModel) => $paymentModel instanceof Payment && $paymentModel->id === $payment->id),
            )
            ->andReturn($mockRefund);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson("/api/payments/{$payment->id}/refund", [
            'reason' => 'Changed my mind'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Refund request submitted successfully'
            ]);
    }

    public function test_request_refund_denies_access_to_other_users_payments(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->postJson("/api/payments/{$payment->id}/refund");

        $response->assertStatus(403);
    }

    public function test_get_payment_statistics(): void
    {
        Sanctum::actingAs($this->user);

        // Create some test payments
        Payment::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'succeeded',
            'amount' => 9.99
        ]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'refunded',
            'amount' => 4.99,
            'refund_amount' => 4.99
        ]);

        $response = $this->getJson('/api/payments/statistics/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_spent',
                'total_purchases',
                'total_refunds',
                'average_purchase_amount',
                'recent_purchases',
                'monthly_spending'
            ]);

        $data = $response->json();
        $this->assertEquals(29.97, $data['total_spent']);
        $this->assertEquals(3, $data['total_purchases']);
        $this->assertEquals(4.99, $data['total_refunds']);
    }

    public function test_api_rate_limiting_is_applied(): void
    {
        Sanctum::actingAs($this->user);

        // This test would need to be adjusted based on your actual rate limiting configuration
        // For now, we'll just verify the middleware is applied by checking headers
        $response = $this->getJson('/api/payments/history');

        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
