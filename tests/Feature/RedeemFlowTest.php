<?php

namespace Tests\Feature;

use App\Jobs\DispatchGiftWebhook;
use App\Repositories\GiftCodeRepository;
use App\Services\RedeemService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RedeemFlowTest extends TestCase
{
    private GiftCodeRepository $codes;
    private RedeemService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codes = $this->app->make(GiftCodeRepository::class);
        $this->service = $this->app->make(RedeemService::class);
    }

    public function test_successful_redeem_dispatches_webhook(): void
    {
        Queue::fake();

        $this->codes->reset([
            [
                'code' => 'GFLOW-TEST-0001',
                'status' => 'available',
                'product_id' => 'product_abc',
                'creator_id' => 'creator_123',
            ],
        ]);

        $response = $this->postJson('/api/redeem', [
            'code' => 'GFLOW-TEST-0001',
            'user' => ['email' => 'newuser@example.com'],
        ]);

        $response->assertStatus(200)->assertJson([
            'status' => 'redeemed',
            'code' => 'GFLOW-TEST-0001',
            'creator_id' => 'creator_123',
            'product_id' => 'product_abc',
            'webhook' => ['status' => 'queued'],
        ]);

        Queue::assertPushed(DispatchGiftWebhook::class, 1);
    }

    public function test_redeeming_already_redeemed_code_returns_conflict(): void
    {
        $this->codes->reset([
            [
                'code' => 'GFLOW-TEST-0001',
                'status' => 'redeemed',
                'product_id' => 'product_abc',
                'creator_id' => 'creator_123',
                'redeemed_by' => 'existing@example.com',
                'event_id' => $this->service->generateEventId('GFLOW-TEST-0001', 'existing@example.com'),
            ],
        ]);

        $response = $this->postJson('/api/redeem', [
            'code' => 'GFLOW-TEST-0001',
            'user' => ['email' => 'other@example.com'],
        ]);

        $response->assertStatus(409);
    }

    public function test_redeem_is_idempotent_for_same_user(): void
    {
        Queue::fake();

        $this->codes->reset([
            [
                'code' => 'GFLOW-TEST-0002',
                'status' => 'available',
                'product_id' => 'product_abc',
                'creator_id' => 'creator_123',
            ],
        ]);

        $payload = [
            'code' => 'GFLOW-TEST-0002',
            'user' => ['email' => 'repeat@example.com'],
        ];

        $first = $this->postJson('/api/redeem', $payload);
        $first->assertStatus(200)->assertJsonPath('webhook.status', 'queued');

        $second = $this->postJson('/api/redeem', $payload);
        $second->assertStatus(200)->assertJsonPath('webhook.status', 'skipped');

        Queue::assertPushed(DispatchGiftWebhook::class, 1);
    }

    public function test_webhook_signature_and_duplicates(): void
    {
        config(['giftflow.webhook_secret' => 'secret-123']);

        $payload = [
            'event_id' => 'evt_abc123',
            'type' => 'giftcard.redeemed',
            'data' => [
                'code' => 'GFLOW-TEST-0001',
                'email' => 'receiver@example.com',
                'creator_id' => 'creator_123',
                'product_id' => 'product_abc',
            ],
            'sent_at' => now()->toIso8601ZuluString(),
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, 'secret-123');

        $first = $this
            ->withHeader('X-GiftFlow-Signature', $signature)
            ->postJson('/api/webhook/issuer-platform', $payload);

        $first->assertStatus(200)->assertJson(['status' => 'received']);

        // Duplicate should be accepted but not processed again.
        $duplicate = $this
            ->withHeader('X-GiftFlow-Signature', $signature)
            ->postJson('/api/webhook/issuer-platform', $payload);

        $duplicate->assertStatus(200)->assertJson(['status' => 'duplicate']);

        // Invalid signature rejected.
        $invalid = $this
            ->withHeader('X-GiftFlow-Signature', 'bad-signature')
            ->postJson('/api/webhook/issuer-platform', $payload);

        $invalid->assertStatus(401);
    }
}
