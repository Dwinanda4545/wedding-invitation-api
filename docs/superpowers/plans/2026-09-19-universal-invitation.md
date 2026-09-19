# Universal Invitation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a shareable event-level invitation link (secret token, no guest, no QR) that coexists with per-guest `/invitation/{secret_token}` links.

**Architecture:** Store `universal_invitation_token`, `universal_invitation_enabled`, and `universal_greeting` on `events`. Public reads/writes go through `/api/invitation/open/{token}` registered **before** the guest token routes. Wishes and envelopes from that path persist with `guest_id` null. Frontend reuses the existing invitation UI, maps greeting onto the display name, and force-hides QR when `is_universal` is true.

**Tech Stack:** Laravel API (`wedding-invitation-api`), React/Vite (`C:/laragon/www/wedding-invitation-web`), PHPUnit, Vitest

## Global Constraints

- Spec: `docs/superpowers/specs/2026-09-19-universal-invitation-design.md`
- Default greeting: `Yth. Bapak/Ibu/Saudara/i`
- Universal URL is a secret token, not the event slug
- Per-guest URLs keep working; guest list does not gain a fake guest
- QR is always hidden on the universal path (sections and html modes)
- Wishes and digital envelopes stay enabled; `guest_id` is null
- Wrong or disabled token returns HTTP 404 `{ "message": "Invitation not found" }`
- Register `/invitation/open/{token}` routes before `/invitation/{secret_token}` so `open` is not captured as a guest token
- Do not commit unless the user asks

---

## File map

| File | Change |
|------|--------|
| `database/migrations/2026_09_19_000000_add_universal_invitation_to_events_table.php` | Event columns |
| `database/migrations/2026_09_19_000001_make_envelope_transactions_guest_id_nullable.php` | Nullable `guest_id` |
| `app/Models/Event.php` | Fillable + casts |
| `app/Http/Controllers/Api/InvitationController.php` | Extract event payload; add `showOpen` |
| `app/Http/Controllers/Api/InvitationWishController.php` | `storeOpen` (no one-wish lock) |
| `app/Http/Controllers/Api/DigitalEnvelopeController.php` | `storeOpen` with null guest |
| `app/Services/DokuService.php` | Return URL from a path string, not only `Guest` |
| `app/Http/Requests/EventInvitationUpdateRequest.php` | Validate new fields |
| `app/Http/Controllers/Api/EventInvitationController.php` | Payload + generate token on enable + regenerate |
| `routes/api.php` | Open routes + regenerate |
| `tests/Feature/UniversalInvitationTest.php` | Feature coverage |
| `wedding-invitation-web/src/lib/invitationApi.ts` | API base helper |
| `wedding-invitation-web/src/lib/invitationTypes.ts` | `is_universal` |
| `wedding-invitation-web/src/pages/public/InvitationPage.tsx` | Open vs guest load; hide QR |
| `wedding-invitation-web/src/components/invitation/WishesSection.tsx` | Use API base |
| `wedding-invitation-web/src/components/invitation/DigitalEnvelopeSection.tsx` | Use API base |
| `wedding-invitation-web/src/components/invitation/CustomSectionFrame.tsx` | Use API base |
| `wedding-invitation-web/src/App.tsx` | Route `/invitation/open/:token` |
| `wedding-invitation-web/src/pages/admin/InvitationContentPage.tsx` | Universal card |

---

### Task 1: Schema + Event fields

**Files:**
- Create: `database/migrations/2026_09_19_000000_add_universal_invitation_to_events_table.php`
- Create: `database/migrations/2026_09_19_000001_make_envelope_transactions_guest_id_nullable.php`
- Modify: `app/Models/Event.php`

**Interfaces:**
- Produces: `Event` columns `universal_invitation_token` (nullable unique string 64), `universal_invitation_enabled` (bool default false), `universal_greeting` (nullable string 255). `envelope_transactions.guest_id` nullable, `nullOnDelete`.

- [ ] **Step 1: Events migration**

```php
Schema::table('events', function (Blueprint $table) {
    $table->string('universal_invitation_token', 64)->nullable()->unique();
    $table->boolean('universal_invitation_enabled')->default(false);
    $table->string('universal_greeting', 255)->nullable();
});
```

Down: drop those three columns.

- [ ] **Step 2: Envelope guest_id nullable**

```php
Schema::table('envelope_transactions', function (Blueprint $table) {
    $table->dropForeign(['guest_id']);
    $table->unsignedBigInteger('guest_id')->nullable()->change();
    $table->foreign('guest_id')->references('id')->on('guests')->nullOnDelete();
});
```

Down: restore non-null FK `cascadeOnDelete` only if no null rows (for this project, reverse the change).

- [ ] **Step 3: Model**

Add to `$fillable`: `universal_invitation_token`, `universal_invitation_enabled`, `universal_greeting`.

Add cast: `'universal_invitation_enabled' => 'boolean'`.

- [ ] **Step 4: Run**

`php artisan migrate`

Expected: both migrations succeed.

---

### Task 2: Public open invitation GET

**Files:**
- Modify: `app/Http/Controllers/Api/InvitationController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/UniversalInvitationTest.php`

**Interfaces:**
- Produces: `GET /api/invitation/open/{token}` JSON:
  - `is_universal: true`
  - `greeting: string` (stored greeting or `Yth. Bapak/Ibu/Saudara/i`)
  - `guest.name` equals `greeting` (compat for existing UI)
  - `guest.secret_token` equals the open token
  - `guest.qr_code_url` is `null`, `guest.is_attended` is `false`, `guest.wish` is `null`, `guest.id` is `null`
  - `event` matches guest invitation event shape (schedules, love stories, gallery, wishes)
- 404 when token missing or `universal_invitation_enabled` is false

- [ ] **Step 1: Failing test**

```php
public function test_open_invitation_returns_greeting_without_qr(): void
{
    $event = Event::query()->create([
        'name' => 'Raka & Sinta',
        'slug' => 'raka-sinta-'.uniqid(),
        'universal_invitation_token' => 'open-token-abc',
        'universal_invitation_enabled' => true,
        'universal_greeting' => 'Yth. Bapak/Ibu/Saudara/i',
    ]);

    $this->getJson('/api/invitation/open/open-token-abc')
        ->assertOk()
        ->assertJsonPath('is_universal', true)
        ->assertJsonPath('greeting', 'Yth. Bapak/Ibu/Saudara/i')
        ->assertJsonPath('guest.name', 'Yth. Bapak/Ibu/Saudara/i')
        ->assertJsonPath('guest.qr_code_url', null)
        ->assertJsonPath('event.id', $event->id);
}

public function test_disabled_open_token_is_404(): void
{
    Event::query()->create([
        'name' => 'Raka & Sinta',
        'slug' => 'raka-sinta-'.uniqid(),
        'universal_invitation_token' => 'open-token-off',
        'universal_invitation_enabled' => false,
    ]);

    $this->getJson('/api/invitation/open/open-token-off')->assertNotFound();
}
```

- [ ] **Step 2: Run** `php artisan test --filter=UniversalInvitationTest` — expect FAIL (route missing).

- [ ] **Step 3: Implement `showOpen`**

Resolve `Event` where `universal_invitation_token` matches and `universal_invitation_enabled` is true. Load the same relations as `InvitationController::show`. Reuse the event array already built in `show` by extracting `eventPayload(Event $event): array`. Return the JSON shape above. Default greeting constant: `Yth. Bapak/Ibu/Saudara/i`.

- [ ] **Step 4: Route** in `routes/api.php` **above** `Route::get('/invitation/{secret_token}', ...)`:

```php
Route::get('/invitation/open/{token}', [InvitationController::class, 'showOpen'])
    ->where('token', '[A-Za-z0-9]+');
```

- [ ] **Step 5: Run tests** — expect PASS for the two GET cases.

---

### Task 3: Open wishes

**Files:**
- Modify: `app/Http/Controllers/Api/InvitationWishController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/UniversalInvitationTest.php`

**Interfaces:**
- Produces: `POST /api/invitation/open/{token}/wishes` with body `{ guest_name, message, rsvp_status? }`
- Creates `InvitationWish` with `guest_id` null
- Does **not** return 409 if another null-guest wish exists (many visitors)
- 404 if token disabled/unknown
- No PATCH/DELETE on the open path

- [ ] **Step 1: Failing test** — two POSTs with different `guest_name` both `201`, both rows `guest_id` null, same `event_id`.

- [ ] **Step 2: Run filter** — expect FAIL.

- [ ] **Step 3: `storeOpen`**

Resolve enabled event by token (same query as Task 2; duplicate the 8-line lookup rather than a new service). Create wish:

```php
InvitationWish::create([
    'event_id' => $event->id,
    'guest_id' => null,
    'guest_name' => $request->input('guest_name'),
    'message' => $request->input('message'),
    'rsvp_status' => $request->input('rsvp_status', 'pending'),
]);
```

Reuse existing `wishPayload`. Require `guest_name` (existing `InvitationWishStoreRequest` already requires it — confirm and reuse that request).

- [ ] **Step 4: Route before guest wish routes**

```php
Route::post('/invitation/open/{token}/wishes', [InvitationWishController::class, 'storeOpen'])
    ->where('token', '[A-Za-z0-9]+');
```

- [ ] **Step 5: Tests PASS.** Existing `InvitationWishOnceTest` still passes (guest path unchanged).

---

### Task 4: Open digital envelope + DOKU return URL

**Files:**
- Modify: `app/Services/DokuService.php`
- Modify: `app/Http/Controllers/Api/DigitalEnvelopeController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/UniversalInvitationTest.php`

**Interfaces:**
- `DokuService::createTransaction(EnvelopeTransaction $transaction, string $invitationPath): string`
- `$invitationPath` is `/invitation/{secret_token}` or `/invitation/open/{token}` (no query string)
- Guest `store()` passes `'/invitation/'.$guest->secret_token`
- Produces: `POST /api/invitation/open/{token}/digital-envelopes` creates row with `guest_id` null
- 403 when envelope settings disabled (same message as guest path)
- 404 when token invalid

- [ ] **Step 1: Change DOKU return URL**

Replace the `Guest $guest` parameter. Build:

```php
$returnUrl = $frontendUrl.$invitationPath.'?order_id='.urlencode($invoiceNumber);
```

Update the only caller in `DigitalEnvelopeController::store`.

- [ ] **Step 2: `storeOpen`**

Copy `store` but resolve event via open token, set `guest_id` null, and call:

```php
$this->doku->createTransaction($transaction, '/invitation/open/'.$token);
```

Order id stays `ENV-{event_id}-{ulid}`.

- [ ] **Step 3: Route**

```php
Route::post('/invitation/open/{token}/digital-envelopes', [DigitalEnvelopeController::class, 'storeOpen'])
    ->where('token', '[A-Za-z0-9]+');
```

- [ ] **Step 4: Test without hitting DOKU**

Mock `DokuService` in the test:

```php
$this->mock(DokuService::class, function ($mock) {
    $mock->shouldReceive('createTransaction')
        ->once()
        ->withArgs(fn ($tx, $path) => $path === '/invitation/open/open-token-abc')
        ->andReturn('https://pay.example/checkout');
});
```

Assert 201 and `EnvelopeTransaction` `guest_id` is null. Envelope is off unless `invitation_settings.sections.digital_envelope` is true (`EnvelopeSettings`). Create the event with:

```php
'invitation_settings' => ['sections' => ['digital_envelope' => true]],
```

- [ ] **Step 5: `php artisan test --filter=UniversalInvitationTest` PASS.**

---

### Task 5: Admin enable, greeting, copy link, regenerate

**Files:**
- Modify: `app/Http/Requests/EventInvitationUpdateRequest.php`
- Modify: `app/Http/Controllers/Api/EventInvitationController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/UniversalInvitationTest.php`

**Interfaces:**
- `PUT/PATCH /api/events/{event}/invitation` accepts:
  - `universal_invitation_enabled` boolean
  - `universal_greeting` nullable string max 255
- When enabled flips to true and token is null, generate `Str::random(64)` (alphanumeric) and save it
- `payload()` includes `universal_invitation_enabled`, `universal_greeting`, `universal_invitation_url` (`config('app.frontend_url').'/invitation/open/'.$token` or null)
- Does **not** return the raw token separately if the URL is enough; include token inside the URL only
- `POST /api/events/{event}/universal-invitation/regenerate` (auth, same middleware as invitation update) replaces token, returns updated payload. Old token 404s.

- [ ] **Step 1: Validation rules**

```php
'universal_invitation_enabled' => ['sometimes', 'boolean'],
'universal_greeting' => ['sometimes', 'nullable', 'string', 'max:255'],
```

- [ ] **Step 2: In `update`, after `$event->update(...)`**

If enabled and token empty:

```php
if ($event->universal_invitation_enabled && ! $event->universal_invitation_token) {
    $event->forceFill([
        'universal_invitation_token' => Str::random(64),
    ])->save();
}
```

`Str::random(64)` is already `[A-Za-z0-9]`, which matches the route constraint.

- [ ] **Step 3: `regenerateUniversal`**

Always set a new `Str::random(64)`, keep enabled flag as-is, return `{ data: payload }`.

- [ ] **Step 4: Auth route** next to invitation update routes:

```php
Route::post('/events/{event}/universal-invitation/regenerate', [EventInvitationController::class, 'regenerateUniversal']);
```

- [ ] **Step 5: Feature test** acting as an admin user (copy auth setup from an existing event feature test, e.g. how `EventInvitation` update is authenticated). Assert enable generates a URL containing `/invitation/open/`, regenerate changes the URL, and the previous token returns 404.

---

### Task 6: Frontend public page

**Files:**
- Create: `wedding-invitation-web/src/lib/invitationApi.ts`
- Modify: `wedding-invitation-web/src/lib/invitationTypes.ts`
- Modify: `wedding-invitation-web/src/App.tsx`
- Modify: `wedding-invitation-web/src/pages/public/InvitationPage.tsx`
- Modify: `WishesSection.tsx`, `DigitalEnvelopeSection.tsx`, `CustomSectionFrame.tsx`
- Test: `wedding-invitation-web/src/lib/invitationApi.test.ts`

**Interfaces:**
- `invitationApiBase(isUniversal: boolean, token: string): string` returns `/api/invitation/open/${token}` or `/api/invitation/${token}`
- `InvitationResponse.is_universal?: boolean`
- Route `/invitation/open/:token` renders `InvitationPage` with open mode
- Open mode: no 15s QR poll; do not render `QrSection` even if `sections.qr !== false` and even in html mode
- Wish/envelope POSTs use `invitationApiBase`

- [ ] **Step 1: Failing Vitest**

```ts
import { expect, it } from 'vitest'
import { invitationApiBase } from './invitationApi'

it('uses open prefix when universal', () => {
  expect(invitationApiBase(true, 'abc')).toBe('/api/invitation/open/abc')
  expect(invitationApiBase(false, 'abc')).toBe('/api/invitation/abc')
})
```

Run: `npm test -- src/lib/invitationApi.test.ts` (cwd web) — FAIL, then add the helper — PASS.

- [ ] **Step 2: Types** — add `is_universal?: boolean` and `greeting?: string` on `InvitationResponse`.

- [ ] **Step 3: `App.tsx`** — register **before** `/invitation/:secret_token`:

```tsx
<Route path="/invitation/open/:token" element={<InvitationPage open />} />
```

- [ ] **Step 4: `InvitationPage`**

Accept `open?: boolean`. Read `token` from `useParams`. Fetch `invitationApiBase(!!open, token)`. Skip the 15s interval when `open`. When rendering, if `data.is_universal` or `open`, do not mount `QrSection` (both sections mode and the html-mode append). Pass `guest.name` through as today (API already sets it to the greeting).

- [ ] **Step 5: Replace hardcoded `/api/invitation/${secretToken}/...`** in `WishesSection`, `DigitalEnvelopeSection`, and `CustomSectionFrame` with `invitationApiBase(Boolean(data.is_universal), secretToken)`.

- [ ] **Step 6: `npm test` in the web repo for the new test file.**

---

### Task 7: Admin card

**Files:**
- Modify: `wedding-invitation-web/src/pages/admin/InvitationContentPage.tsx` (and its load/save types if invitation payload is typed locally)

**Interfaces:**
- Card title: `Undangan Universal`
- Checkbox bound to `universal_invitation_enabled`
- Text input bound to `universal_greeting` (placeholder `Yth. Bapak/Ibu/Saudara/i`)
- When URL is present: readonly link + copy button
- Button `Regenerate link` calls `POST /api/events/${eventId}/universal-invitation/regenerate` and replaces local URL
- Save uses the existing invitation PUT, including the two new fields

- [ ] **Step 1:** Extend the invitation GET state with the three payload fields from Task 5.
- [ ] **Step 2:** Render the card near cover/music settings (around the falling-leaves checkbox, ~line 800).
- [ ] **Step 3:** Include the fields in the save body already sent by this page.

No new guest row. Do not add the universal link to WhatsApp bulk send.

---

### Task 8: Verification

- [ ] API: `php artisan test --filter=UniversalInvitationTest` and `php artisan test --filter=InvitationWishOnceTest`
- [ ] Web: `npm test -- src/lib/invitationApi.test.ts`
- [ ] Manual: enable universal in admin, open `/invitation/open/{token}` — greeting visible, QR absent, wish POST succeeds
- [ ] Manual: a normal guest link still shows QR when `sections.qr` is on
- [ ] Manual: regenerate — old open URL 404, new URL 200

---

## Spec coverage

| Spec item | Task |
|-----------|------|
| Event token + enabled + greeting | 1, 5 |
| GET open payload, no QR, 404 | 2 |
| Wishes with null guest, many senders | 3 |
| Envelope null guest + return URL | 4 |
| Admin toggle, greeting, copy, regenerate | 5, 7 |
| Frontend route, hide QR, same template | 6 |
| Guest links unchanged | 2–4 (routes ordered first; guest tests kept) |
| Out of scope: slug URL, RSVP product, fake guest, scanner, WhatsApp bulk | not implemented |
