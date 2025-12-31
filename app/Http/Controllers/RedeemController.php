<?php

namespace App\Http\Controllers;

use App\Exceptions\GiftCodeAlreadyRedeemedException;
use App\Exceptions\GiftCodeNotFoundException;
use App\Services\RedeemService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class RedeemController extends Controller
{
    public function __invoke(Request $request, RedeemService $service)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'user.email' => ['required', 'email'],
        ]);

        $code = $validated['code'];
        $email = Arr::get($validated, 'user.email');

        try {
            $result = $service->redeem($code, $email);
        } catch (GiftCodeNotFoundException $e) {
            return response()->json(['message' => 'Gift code not found'], 404);
        } catch (GiftCodeAlreadyRedeemedException $e) {
            $record = $e->record();

            return response()->json([
                'message' => 'Gift code already redeemed',
                'code' => $record['code'] ?? $code,
            ], 409);
        }

        $record = $result['record'];
        $webhookStatus = $result['queued'] ? 'queued' : 'skipped';

        return response()->json([
            'status' => 'redeemed',
            'code' => $record['code'],
            'creator_id' => $record['creator_id'] ?? null,
            'product_id' => $record['product_id'] ?? null,
            'webhook' => [
                'status' => $webhookStatus,
                'event_id' => $record['event_id'],
            ],
        ]);
    }
}
