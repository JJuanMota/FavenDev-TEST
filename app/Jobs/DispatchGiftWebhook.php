<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class DispatchGiftWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $eventId,
        public string $code,
        public string $email,
        public string $creatorId,
        public string $productId,
    ) {
    }

    public function handle(): void
    {
        $payload = [
            'event_id' => $this->eventId,
            'type' => 'giftcard.redeemed',
            'data' => [
                'code' => $this->code,
                'email' => $this->email,
                'creator_id' => $this->creatorId,
                'product_id' => $this->productId,
            ],
            'sent_at' => Carbon::now()->toIso8601ZuluString(),
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $secret = config('giftflow.webhook_secret');

        $signature = hash_hmac('sha256', $body, $secret);

        $response = Http::withHeaders([
            'X-GiftFlow-Signature' => $signature,
            'Content-Type' => 'application/json',
        ])->post(config('giftflow.issuer_url'), $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Webhook delivery failed with status '.$response->status());
        }
    }
}
