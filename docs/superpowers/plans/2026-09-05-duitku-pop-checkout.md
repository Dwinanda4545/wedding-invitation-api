# Duitku POP Hosted Checkout Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Switch digital-envelope payments from Direct API inquiry to Duitku POP `createInvoice` so guests choose payment methods on Duitku’s hosted checkout.

**Architecture:** Replace `DuitkuService` inquiry + MD5 callback with POP createInvoice (HMAC headers) + HMAC-SHA256 callback verification. Frontend unchanged.

**Tech Stack:** Laravel, Duitku POP API, PHPUnit feature tests

## Global Constraints

- No invitation-form payment-method UI
- Empty `paymentMethod` for full channel list (unless `DUITKU_PAYMENT_METHOD` set)
- Synthetic email when guest email is null
- Do not commit unless user asks

---

## File map

| File | Change |
|------|--------|
| `config/duitku.php` | POP base URLs; optional `payment_method` |
| `app/Services/DuitkuService.php` | createInvoice + HMAC callback |
| `tests/Feature/DigitalEnvelopeTest.php` | HMAC signatures; status map |
| `.env.example` / `.env.production.example` | Document optional payment method |
| `.env` | Clear or empty `DUITKU_PAYMENT_METHOD` for hosted options |

---

### Task 1: Config for POP endpoints

- [ ] Update `config/duitku.php`:
  - `base_url` → sandbox `https://api-sandbox.duitku.com`, prod `https://api-prod.duitku.com`
  - `payment_method` → `env('DUITKU_PAYMENT_METHOD', '')` (empty default)

### Task 2: Rewrite `DuitkuService::createTransaction`

- [ ] Build timestamp (ms), HMAC-SHA256 header signature
- [ ] POST `{base}/api/merchant/createInvoice` with JSON body (no body signature)
- [ ] `paymentMethod` from config (may be `''`)
- [ ] Email fallback when null/empty
- [ ] Accept `statusCode === '00'` and non-empty `paymentUrl`
- [ ] Optionally persist `reference` onto the transaction before return (caller may update `payment_url` only today — update service or controller to save `duitku_reference` from response)

### Task 3: Callback HMAC + status map

- [ ] `verifyCallbackSignature`: HMAC-SHA256(`merchantCode+amount+merchantOrderId`, apiKey)
- [ ] `mapStatus`: `00` → paid, else → failed

### Task 4: Tests + env docs

- [ ] Fix DigitalEnvelopeTest callback signatures to HMAC
- [ ] Assert `resultCode=01` fails if covered; keep paid/idempotent tests green
- [ ] Update `.env.example` notes; set local `.env` `DUITKU_PAYMENT_METHOD=` empty
- [ ] Run `php artisan test --filter=DigitalEnvelope`

### Task 5: Manual smoke (local)

- [ ] `config:clear`, submit amplop, confirm redirect to `app-sandbox.duitku.com/redirect_checkout` with method list
