# DOKU Checkout (Replace Duitku)

**Date:** 2026-09-08  
**Status:** Approved  
**Decision:** Full replace Duitku with DOKU Checkout (option A). Credentials filled later (option B).

## Goal

Digital envelope payments use **DOKU Checkout** hosted page (guest picks VA/QRIS/e-wallet there). Remove all Duitku integration.

## Flow

1. Guest submits amplop form (unchanged).
2. Backend creates `envelope_transactions` (`pending`, `order_id` = invoice number).
3. `DokuService::createTransaction` → `POST /checkout/v1/payment` with DOKU signature headers.
4. Persist `payment_url` + `payment_reference` (Request-Id / DOKU ids).
5. Frontend redirects to `payment.url`.
6. DOKU webhook `POST /api/doku/notification` → verify signature → mark `paid` on `SUCCESS`.
7. Admin list syncs unsettled rows via Check Status API.

## Config

```env
DOKU_CLIENT_ID=
DOKU_SECRET_KEY=
DOKU_SANDBOX=true
DOKU_NOTIFICATION_PATH=/api/doku/notification
FRONTEND_URL=http://localhost:5173
```

Sandbox base: `https://api-sandbox.doku.com`  
Production base: `https://api.doku.com`

## Schema

Rename `duitku_reference` → `payment_reference`.

## Remove

`DuitkuService`, `DuitkuCallbackController`, `config/duitku.php`, `DUITKU_*` env, `/api/duitku/callback`.

## Notes

- Checkout: ignore notification `FAILED` (guest may retry another method).
- Empty Client ID / Secret → 503 “Payment service unavailable”.
- Local webhook still needs public URL (ngrok) configured in DOKU Dashboard.
