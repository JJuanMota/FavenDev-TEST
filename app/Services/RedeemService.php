<?php

namespace App\Services;

use App\Exceptions\GiftCodeAlreadyRedeemedException;
use App\Exceptions\GiftCodeNotFoundException;
use App\Jobs\DispatchGiftWebhook;
use App\Repositories\GiftCodeRepository;
use Illuminate\Contracts\Bus\Dispatcher;

class RedeemService
{
    public function __construct(
        private GiftCodeRepository $codes,
        private Dispatcher $dispatcher,
    ) {
    }

    /**
     * @return array{record: array, queued: bool, idempotent: bool}
     */
    public function redeem(string $code, string $email): array
    {
        $record = $this->codes->find($code);

        if (!$record) {
            throw new GiftCodeNotFoundException('Gift code not found');
        }

        if (($record['status'] ?? 'available') === 'redeemed') {
            if (($record['redeemed_by'] ?? null) === $email) {
                if (!isset($record['event_id'])) {
                    $record['event_id'] = $this->generateEventId($code, $email);
                    $this->codes->upsert($record);
                }

                return [
                    'record' => $record,
                    'queued' => false,
                    'idempotent' => true,
                ];
            }

            throw new GiftCodeAlreadyRedeemedException($record);
        }

        $eventId = $record['event_id'] ?? $this->generateEventId($code, $email);

        $record = array_merge($record, [
            'status' => 'redeemed',
            'redeemed_by' => $email,
            'event_id' => $eventId,
            'redeemed_at' => now()->toISOString(),
        ]);

        $this->codes->upsert($record);

        $this->dispatcher->dispatch(new DispatchGiftWebhook(
            $eventId,
            $code,
            $email,
            $record['creator_id'] ?? '',
            $record['product_id'] ?? ''
        ));

        return [
            'record' => $record,
            'queued' => true,
            'idempotent' => false,
        ];
    }

    public function seedDefaults(): void
    {
        $seeds = config('giftflow.seed_codes', []);

        foreach ($seeds as $seed) {
            if (($seed['status'] ?? 'available') === 'redeemed' && isset($seed['redeemed_by'])) {
                $seed['event_id'] = $seed['event_id'] ?? $this->generateEventId($seed['code'], $seed['redeemed_by']);
                $seed['redeemed_at'] = $seed['redeemed_at'] ?? now()->toISOString();
            }

            $seed['status'] = $seed['status'] ?? 'available';
            $this->codes->upsert($seed);
        }
    }

    public function generateEventId(string $code, string $email): string
    {
        $hash = substr(hash('sha256', strtolower($code.'|'.$email)), 0, 24);

        return config('giftflow.event_prefix', 'evt_').$hash;
    }
}
