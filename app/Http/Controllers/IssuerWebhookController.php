<?php

namespace App\Http\Controllers;

use App\Repositories\WebhookEventRepository;
use Illuminate\Http\Request;

class IssuerWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookEventRepository $events)
    {
        $rawBody = $request->getContent();
        $provided = (string) $request->header('X-GiftFlow-Signature', '');
        $secret = config('giftflow.webhook_secret');
        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (!hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $eventId = $request->input('event_id');
        if (!$eventId) {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        if ($events->hasEvent($eventId)) {
            return response()->json(['status' => 'duplicate', 'event_id' => $eventId]);
        }

        $events->recordEvent($eventId);

        return response()->json(['status' => 'received', 'event_id' => $eventId]);
    }
}
