# Duitku POP Hosted Checkout (Payment Method Options)

**Date:** 2026-09-05  
**Status:** Approved  
**Context:** Digital envelope currently calls Direct API `v2/inquiry` with a fixed `paymentMethod` (`SP`). Guests cannot choose how to pay. Product choice **B**: keep the invitation form as-is; show payment method options on Duitku’s hosted checkout page.

## Goal

After submitting the amplop form, redirect the guest to Duitku’s checkout (`paymentUrl`) where they can pick QRIS, VA, e-wallet, etc. No payment-method UI on the invitation.

## Non-goals

- Payment method picker on the invitation form
- Admin configuration of allowed methods (use Duitku project settings)
- Duitku Pop JS embedded widget

## Architecture

1. Guest submits amplop form (unchanged frontend).
2. `DigitalEnvelopeController` creates `envelope_transactions` row (`pending`).
3. `DuitkuService::createTransaction` calls **POP Create Invoice** (`/api/merchant/createInvoice`) with **empty `paymentMethod`** so Duitku shows all enabled channels.
4. Persist `payment_url` (and `reference` when returned); respond `201` with `payment_url`.
5. Frontend redirects to `payment_url` (already implemented).
6. Guest selects method and pays on Duitku.
7. Duitku POSTs callback → verify **HMAC-SHA256** signature → update status / `payment_method` / `duitku_reference`.
8. Guest returns via `returnUrl` to the invitation.

## API change (backend only)

| Before | After |
|--------|--------|
| `https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry` | Sandbox: `https://api-sandbox.duitku.com/api/merchant/createInvoice` |
| Body MD5 signature | Headers: `x-duitku-timestamp`, `x-duitku-signature` (HMAC-SHA256 of `merchantCode + timestamp`), `x-duitku-merchantcode` |
| Required `paymentMethod` | Omit / empty string |
| Callback verify: MD5(`merchantCode + amount + merchantOrderId + apiKey`) | HMAC-SHA256(`merchantCode + amount + merchantOrderId`, apiKey) |

Config:

- `DUITKU_SANDBOX` still selects sandbox vs production POP host.
- `DUITKU_PAYMENT_METHOD` becomes optional; if set, passed through; if empty, hosted checkout with method list.
- Remove hard dependency on a mandatory payment method.

Email: POP requires `email`. If guest leaves it blank, send a synthetic address (e.g. `amplop+{order_id}@noreply.local`) so the form stays optional.

## Callback status mapping (POP)

| `resultCode` | Status |
|--------------|--------|
| `00` | `paid` (+ `paid_at`) |
| anything else | `failed` |

(POP docs: `00` success, `01` failed — no separate pending callback code.)

## Frontend

No UI change. Form still posts amount/sender; redirect still uses `data.payment_url`.

## Testing

- Update callback tests to HMAC-SHA256 signatures.
- Map `resultCode=01` → `failed` (not `pending`).
- Keep create-transaction tests mocked at `DuitkuService`.
- Optional unit coverage for signature helpers.

## Risks

- Project must have POP / Create Invoice enabled on Duitku sandbox.
- Callback URL must remain publicly reachable (ngrok locally).
- Synthetic email is only for API compliance; not shown to the guest.
