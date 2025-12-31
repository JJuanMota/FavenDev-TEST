# GiftFlow Redeem Service

Minimal Laravel 12 service that redeems gift codes, dispatches signed webhooks, and keeps both redemption and delivery idempotent without a database (file-based storage in `storage/app/*.json`).

## Quick start (Docker)
- `cp .env.example .env` and set `APP_KEY` if empty (run `docker compose exec app php artisan key:generate` after first start).
- `docker compose up --build -d`
- `docker compose exec app php artisan giftflow:seed` (auto-seeds on first boot if the file is missing)
- `docker compose exec app php artisan test`
- Optional worker (queue defaults to `sync`, so this is only needed if you switch drivers): `docker compose exec app php artisan queue:work`

App URL: `http://localhost:8000` (update `APP_URL` if you change the port/host).

## API
### Redeem a code
`POST /api/redeem`

```json
{
  "code": "GFLOW-TEST-0001",
  "user": { "email": "newuser@example.com" }
}
```

**200**
```json
{
  "status": "redeemed",
  "code": "GFLOW-TEST-0001",
  "creator_id": "creator_123",
  "product_id": "product_abc",
  "webhook": {
    "status": "queued",
    "event_id": "evt_..."
  }
}
```
Errors: 404 (code not found), 409 (already redeemed), 422 (validation).

Idempotency: `event_id` is deterministic (`code + email`). The same user redeeming again returns 200 with `webhook.status = skipped` and no new job; a different user gets 409.

### Mock issuer webhook
`POST /api/webhook/issuer-platform`

- Header `X-GiftFlow-Signature`: `HMAC-SHA256(raw_body, GIFTFLOW_WEBHOOK_SECRET)`
- Payload:
```json
{
  "event_id": "evt_...",
  "type": "giftcard.redeemed",
  "data": {
    "code": "GFLOW-TEST-0001",
    "email": "newuser@example.com",
    "creator_id": "creator_123",
    "product_id": "product_abc"
  },
  "sent_at": "2025-12-29T00:00:00Z"
}
```
- Responses: 200 on success, 401 on bad signature, 200 + `status: duplicate` when the same `event_id` is re-sent (idempotent sink).

## Seeding & persistence
- Command: `php artisan giftflow:seed` seeds:
  - `GFLOW-TEST-0001` available
  - `GFLOW-TEST-0002` available
  - `GFLOW-USED-0003` already redeemed
  - `GFLOW-INVALID-XXXX` intentionally absent
- Files:
  - Gift codes: `storage/app/giftcodes.json`
  - Received webhook events: `storage/app/webhook_events.json`
- Files are gitignored; seeds also run automatically on boot if the codes file is missing (skipped during tests).

## Architecture notes
- `RedeemService` coordinates validation, deterministic `event_id`, state updates, and queue dispatch.
- `GiftCodeRepository` and `WebhookEventRepository` are file-backed stores for codes and received event IDs (idempotent delivery).
- `DispatchGiftWebhook` job sends the webhook to the in-app mock endpoint with the HMAC signature.
- Config lives in `config/giftflow.php` (`GIFTFLOW_WEBHOOK_SECRET`, `GIFTFLOW_ISSUER_URL`, file paths, event prefix).
- Queue driver defaults to `sync`; swap `QUEUE_CONNECTION` to redis/beanstalk/etc. if you want async + a worker container.

## Testing
- `docker compose exec app php artisan test`
- Coverage includes successful redeem, conflict on already redeemed, idempotent same-user redeem (no duplicate webhook), and webhook signature/idempotency handling.

## Tradeoffs / next steps
- File storage is simple and transparent but not built for heavy concurrency; a real database or cache would be needed for production scale.
- Webhook sender relies on the consumer’s idempotency (event_id) instead of a full retry/outbox model; could be extended with retry logs/metrics.
- Redis + rate limiting on `/api/redeem` and OpenAPI docs would be natural extensions if needed.
