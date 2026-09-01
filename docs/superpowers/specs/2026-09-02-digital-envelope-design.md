# Amplop Digital (Digital Envelope) via Duitku

Date: 2026-09-02  
Repos: `wedding-invitation-api` (backend, payment, webhook) and `wedding-invitation-web` (public section + admin UI)

## Goal

Allow wedding guests to send monetary gifts through a **Digital Envelope** section on their personal invitation page. Payments are processed automatically via **Duitku** (platform-owned merchant account) without exposing the couple's personal bank account number.

## Decisions (brainstorming)

| Topic | Decision |
|-------|----------|
| Settlement | Single platform Duitku merchant; manual disbursement to couples offline |
| UI | New builtin section `digital_envelope` (toggle on/off in admin) |
| Privacy | Transaction list visible **only in admin**; guests see thank-you message only |
| Access | Only via guest invitation link `/invitation/:secret_token` |
| Payment UX | Redirect to Duitku payment page, return to invitation |
| Architecture | Service layer (`DuitkuService`) + thin controllers |

## Non-goals (v1)

- Automatic disbursement to couple bank accounts
- Multi payment gateway abstraction
- Email/WhatsApp notifications after payment
- CSV export of transactions
- Public event URL for non-guest payers
- Public display of envelope list or amounts on invitation page
- Midtrans Snap / `snap_token` (Duitku uses `payment_url` redirect)
- React Query (frontend continues using Axios, matching existing code)

---

## Database

### Table: `envelope_transactions`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `event_id` | FK → `events` | Required |
| `guest_id` | FK → `guests` | Required (access via `secret_token`) |
| `sender_name` | string | Display name; may differ from guest name |
| `sender_email` | string, nullable | Optional |
| `sender_phone` | string, nullable | Optional |
| `amount` | unsigned bigint | IDR integer (no decimals) |
| `message` | text, nullable | Wishes / greeting |
| `order_id` | string, unique | Format: `ENV-{event_id}-{ulid}` |
| `payment_method` | string, nullable | Filled from Duitku callback (QRIS, VA, etc.) |
| `status` | enum | `pending`, `paid`, `expired`, `failed` |
| `payment_url` | text, nullable | Duitku redirect URL |
| `duitku_reference` | string, nullable | Duitku reference from callback |
| `paid_at` | timestamp, nullable | Set when status becomes `paid` |
| `timestamps` | | |

Indexes: `event_id`, `guest_id`, `status`, `order_id` (unique).

### Settings (no new table)

Stored in existing `events.invitation_settings` JSON:

```json
{
  "sections": {
    "digital_envelope": true
  },
  "digital_envelope": {
    "presets": [50000, 100000, 200000],
    "min_amount": 10000,
    "max_amount": 10000000
  }
}
```

Defaults when `digital_envelope` key is missing: presets `[50000, 100000, 200000]`, min `10000`, max `10000000`, section enabled `false`.

### Model: `EnvelopeTransaction`

- `belongsTo` Event, Guest
- Casts: `amount` integer, `paid_at` datetime
- Scopes: `paid()`, `forEvent($eventId)`

Add `envelopeTransactions()` hasMany on `Event` model.

---

## Backend (`wedding-invitation-api`)

### New files

```
app/Models/EnvelopeTransaction.php
app/Services/DuitkuService.php
app/Http/Controllers/Api/DigitalEnvelopeController.php
app/Http/Controllers/Api/DuitkuCallbackController.php
app/Http/Controllers/Api/EventEnvelopeController.php
app/Http/Requests/DigitalEnvelopeStoreRequest.php
config/duitku.php
database/migrations/YYYY_MM_DD_create_envelope_transactions_table.php
```

### Routes (`routes/api.php`)

| Method | Endpoint | Auth | Handler |
|--------|----------|------|---------|
| `POST` | `/invitation/{secret_token}/digital-envelopes` | Public | `DigitalEnvelopeController@store` |
| `POST` | `/duitku/callback` | Public (signature) | `DuitkuCallbackController@handle` |
| `GET` | `/events/{event}/envelope-transactions` | `auth:sanctum` | `EventEnvelopeController@index` |

CSRF exemption in `bootstrap/app.php`:

```php
$middleware->validateCsrfTokens(except: ['api/duitku/callback']);
```

Rate limit public store endpoint: `throttle:10,1`.

### `DigitalEnvelopeController::store`

1. Resolve `Guest` by `secret_token` with `event` (404 if not found).
2. Check `invitation_settings.sections.digital_envelope !== false` (403 if disabled).
3. Validate via `DigitalEnvelopeStoreRequest`:
   - `sender_name`: required, string, max 255
   - `sender_email`: nullable, email, max 255
   - `sender_phone`: nullable, string, max 20
   - `amount`: required, integer, between min/max from event settings
   - `message`: nullable, string, max 2000
4. Generate `order_id`: `ENV-{event_id}-{ulid}`.
5. Create `EnvelopeTransaction` with status `pending`.
6. Call `DuitkuService::createTransaction($transaction)` → returns `payment_url`.
7. Save `payment_url` on transaction.
8. Return 201:

```json
{
  "data": {
    "order_id": "ENV-1-01J...",
    "payment_url": "https://sandbox.duitku.com/...",
    "amount": 100000,
    "status": "pending"
  }
}
```

On Duitku API failure: mark transaction `failed`, return 503.

### `DuitkuService`

Responsibilities:

- `createTransaction(EnvelopeTransaction $tx): string` — call Duitku create-invoice API, return `payment_url`
- `verifyCallbackSignature(array $payload): bool` — verify per Duitku docs
- `mapStatus(string $duitkuStatus): string` — map to `paid` / `expired` / `failed`

Request payload includes:

- `merchantOrderId` = `order_id`
- `paymentAmount` = `amount`
- `productDetails` = e.g. "Amplop Digital - {event name}"
- `returnUrl` = `{FRONTEND_URL}/invitation/{secret_token}?envelope=success&order_id={order_id}`
- `callbackUrl` = `{APP_URL}/api/duitku/callback`
- `customerVaName` = `sender_name`
- `email` = `sender_email` (if provided)

### `DuitkuCallbackController::handle`

1. Verify signature — 403 if invalid (log attempt).
2. Find transaction by `merchantOrderId`.
3. If not found — 404 (log warning).
4. If already `paid` — return 200 (idempotent).
5. Map Duitku status → update `status`, `payment_method`, `duitku_reference`, `paid_at`.
6. Return 200 OK.

### `EventEnvelopeController::index`

- Auth required; authorize user owns event (follow existing event policy pattern).
- Paginated list, ordered by `created_at` desc.
- Response includes summary: `{ total_paid_amount, paid_count, pending_count }`.
- Fields per row: `id`, `sender_name`, `sender_email`, `sender_phone`, `amount`, `message`, `payment_method`, `status`, `paid_at`, `created_at`.

### Public invitation payload

`GET /api/invitation/{secret_token}` must **not** include envelope transactions. No changes to public payload except `invitation_settings` already exposes section toggle.

---

## Frontend (`wedding-invitation-web`)

### New files

```
src/components/invitation/DigitalEnvelopeSection.tsx
src/pages/admin/EnvelopeTransactionsPage.tsx
src/lib/envelopeTypes.ts
```

### Changes to existing files

- `src/lib/invitationTypes.ts` — add `digital_envelope` to `SectionVisibility`, `SectionBgKey`, `BUILTIN_SECTION_KEYS`, `BUILTIN_SECTION_LABELS`, `DEFAULT_SECTION_ORDER`, `InvitationSettings.digital_envelope`
- `src/components/invitation/SectionInvitation.tsx` — render `digital_envelope` case
- `src/pages/public/InvitationPage.tsx` — read `?envelope=success|failed&order_id=` on mount, pass to section, clean URL via `replaceState`
- `src/pages/admin/InvitationContentPage.tsx` — section visibility toggle for `digital_envelope`
- `src/pages/admin/EventsPage.tsx` — link to envelope transactions page
- `src/App.tsx` — route `/admin/events/:id/envelopes`

### `DigitalEnvelopeSection`

Form fields:

- Sender name (pre-filled from guest name)
- Amount presets (from settings) + custom amount input
- Message (optional)
- Email / phone (optional)

State: `idle` → `submitting` → redirect to `payment_url` → on return `success` | `failed` | `expired`.

Submit: `POST /api/invitation/{secretToken}/digital-envelopes` then `window.location.href = payment_url`.

Success UI: thank-you card (no transaction list). Failed/expired: retry button.

### `EnvelopeTransactionsPage` (admin)

Route: `/admin/events/:id/envelopes`

Table columns: date, sender, amount (Rp formatted), message (truncated), payment method, status badge, paid_at.

Summary bar: total collected (paid only).

---

## Data flow

```
Guest submits form
  → POST /api/invitation/{token}/digital-envelopes
  → Create pending EnvelopeTransaction
  → DuitkuService.createTransaction → payment_url
  → Frontend redirects to Duitku

Guest pays on Duitku
  → Duitku POST /api/duitku/callback → update status to paid
  → Duitku redirects guest to returnUrl

Guest lands on invitation ?envelope=success
  → Frontend shows thank-you message

Admin views /admin/events/{id}/envelopes
  → GET /api/events/{id}/envelope-transactions
```

---

## Error handling

| Scenario | HTTP | Response / UI |
|----------|------|---------------|
| Invalid `secret_token` | 404 | "Invitation not found" |
| Section disabled | 403 | "Amplop digital tidak tersedia" |
| Validation error | 422 | Field errors |
| Duitku API failure | 503 | "Payment service unavailable" |
| Invalid webhook signature | 403 | Log + reject |
| Unknown order in callback | 404 | Log warning |
| Duplicate paid callback | 200 | Idempotent no-op |
| Network error (frontend) | — | Generic retry message |

Security:

- Duitku API key only in backend `.env`
- Webhook signature verification required
- Amount validated server-side against event settings
- No payment secrets in frontend

---

## Configuration

### Backend `.env`

```env
DUITKU_MERCHANT_CODE=your_merchant_code
DUITKU_API_KEY=your_api_key
DUITKU_SANDBOX=true
DUITKU_CALLBACK_URL="${APP_URL}/api/duitku/callback"
FRONTEND_URL=http://localhost:5173
```

### Frontend `.env`

```env
VITE_API_BASE_URL=http://localhost:8000
```

### `config/duitku.php`

```php
return [
    'merchant_code' => env('DUITKU_MERCHANT_CODE'),
    'api_key' => env('DUITKU_API_KEY'),
    'sandbox' => env('DUITKU_SANDBOX', true),
    'base_url' => env('DUITKU_SANDBOX', true)
        ? 'https://sandbox.duitku.com'
        : 'https://passport.duitku.com',
    'callback_url' => env('DUITKU_CALLBACK_URL'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];
```

---

## Testing

### Backend (PHPUnit, mock `DuitkuService`)

| Test | Assert |
|------|--------|
| `test_guest_can_create_envelope_transaction` | 201, `payment_url` present, DB `pending` |
| `test_invalid_amount_rejected` | 422 |
| `test_disabled_section_returns_403` | 403 |
| `test_callback_updates_status_to_paid` | `paid`, `paid_at` set |
| `test_callback_rejects_invalid_signature` | 403 |
| `test_callback_is_idempotent` | Second callback leaves `paid` unchanged |
| `test_admin_can_list_transactions` | 200, paginated, summary totals |
| `test_public_invitation_excludes_envelope_transactions` | No envelope data in invitation JSON |

### Frontend

No mandatory unit tests for v1 (consistent with `WishesSection`). Optional: amount formatting helper.

---

## Implementation order

1. Migration + model + config
2. `DuitkuService` (sandbox integration)
3. `DigitalEnvelopeController` + request validation
4. `DuitkuCallbackController` + CSRF exempt
5. `EventEnvelopeController` (admin list)
6. Backend feature tests
7. Frontend types + `DigitalEnvelopeSection`
8. Section integration + return URL handling
9. Admin transactions page + events link + section toggle
10. Manual E2E test in Duitku sandbox
