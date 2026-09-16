# WhatsApp FlowKirim Multi-Device + Admin UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admins manage multiple FlowKirim WhatsApp devices, set a default sender per event, override device when sending invitations, and do all of this from the admin UI.

**Architecture:** Global `whatsapp_devices` catalog (provider device id only; Bearer token stays in `.env`). `events.whatsapp_device_id` is the default sender. Send endpoints accept optional local `device_id`, resolve active device, pass configurable JSON field to FlowKirim, and store the device on `invitation_sends`. Admin UI in `wedding-invitation-web`.

**Tech Stack:** Laravel 13 API (Sanctum, `Http` client), React + Vite admin SPA, Vitest where useful.

**Spec:** `docs/superpowers/specs/2026-09-16-whatsapp-flowkirim-multidevice-admin-design.md`

## Global Constraints

- Single `FLOWKIRIM_API_TOKEN` in env — no per-device tokens in admin
- Device resolution: request `device_id` → event default → fail
- Inactive devices cannot send
- `DELETE` device only if unused; otherwise 422 (prefer `is_active=false`)
- Sync send (no queue); bulk max 50 unchanged
- Admin-only APIs (`auth:sanctum` + `role:admin`)
- Work in both repos: `C:\laragon\www\wedding-invitation-api` and `C:\laragon\www\wedding-invitation-web`

## File map

**API (create)**
- `database/migrations/xxxx_create_whatsapp_devices_table.php`
- `database/migrations/xxxx_add_whatsapp_device_id_to_events_and_invitation_sends.php`
- `app/Models/WhatsappDevice.php`
- `app/Http/Controllers/Api/WhatsappDeviceController.php`
- `app/Http/Requests/WhatsappDeviceStoreRequest.php`
- `app/Http/Requests/WhatsappDeviceUpdateRequest.php`
- `tests/Feature/WhatsappDeviceTest.php`
- Extend: `tests/Feature/InvitationWhatsappSendTest.php`

**API (modify)**
- `config/flowkirim.php`, `.env.example`
- `app/Services/FlowkirimService.php` — `sendText(string $to, string $message, ?string $providerDeviceId = null)`
- `app/Services/InvitationWhatsappService.php` — resolve device; pass into send
- `app/Models/Event.php`, `InvitationSend.php`
- `app/Http/Requests/EventUpdateRequest.php`, `EventStoreRequest.php` (optional nullable `whatsapp_device_id`)
- `app/Http/Requests/SendInvitationRequest.php`, `BulkSendInvitationRequest.php`
- `app/Http/Controllers/Api/InvitationSendController.php`, `EventController.php` (eager load device on show/update if useful)
- `routes/api.php`

**Web (create/modify)**
- Create: `src/pages/admin/WhatsappDevicesPage.tsx`
- Modify: `src/App.tsx`, `src/components/AdminLayout.tsx`
- Modify: `src/pages/admin/EventsPage.tsx` — default device dropdown
- Modify: `src/pages/admin/GuestsPage.tsx` — send WA single/bulk UI

---

### Task 1: Schema + WhatsappDevice model

**Files:**
- Create: migrations + `app/Models/WhatsappDevice.php`
- Modify: `app/Models/Event.php`, `app/Models/InvitationSend.php`

**Produces:**
- `WhatsappDevice` with fillable: `name`, `provider_device_id`, `phone_label`, `is_active`
- Relations: `Event::whatsappDevice()`, `InvitationSend::whatsappDevice()`, `WhatsappDevice::events()`, `WhatsappDevice::invitationSends()`

- [ ] **Step 1: Create migration `create_whatsapp_devices_table`**

```php
Schema::create('whatsapp_devices', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('provider_device_id');
    $table->string('phone_label')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

- [ ] **Step 2: Create migration adding FKs**

```php
Schema::table('events', function (Blueprint $table) {
    $table->foreignId('whatsapp_device_id')->nullable()->after('hosts')
        ->constrained('whatsapp_devices')->nullOnDelete();
});

Schema::table('invitation_sends', function (Blueprint $table) {
    $table->foreignId('whatsapp_device_id')->nullable()->after('sent_by')
        ->constrained('whatsapp_devices')->nullOnDelete();
});
```

(If `after('hosts')` fails on some DBs, omit `after`.)

- [ ] **Step 3: Model `WhatsappDevice` + relations on Event / InvitationSend; add `whatsapp_device_id` to Event `$fillable` and InvitationSend `$fillable`**

- [ ] **Step 4: Run migrate**

```bash
php artisan migrate --no-interaction
```

Expected: migrations DONE

- [ ] **Step 5: Commit (api repo)**

```bash
git add database/migrations app/Models
git commit -m "feat: add whatsapp_devices table and event/send FKs"
```

---

### Task 2: Flowkirim config + sendText device field

**Files:**
- Modify: `config/flowkirim.php`, `.env.example`, `app/Services/FlowkirimService.php`
- Test: extend `tests/Feature/InvitationWhatsappSendTest.php` or add unit assertion via Http::fake

**Produces:**
- `config('flowkirim.device_field')` default `device_id`
- `FlowkirimService::sendText(string $to, string $message, ?string $providerDeviceId = null): array`

- [ ] **Step 1: Write failing test** — when sending with a device, HTTP payload includes configurable device field

In `InvitationWhatsappSendTest` (after Task 3 wiring this may need device seeded; for this task test `FlowkirimService` directly):

```php
public function test_flowkirim_send_text_includes_device_field(): void
{
    config([
        'flowkirim.api_token' => 'tok',
        'flowkirim.base_url' => 'https://api.flowkirim.com',
        'flowkirim.device_field' => 'device_id',
    ]);
    Http::fake(['api.flowkirim.com/*' => Http::response(['id' => '1'], 200)]);

    app(\App\Services\FlowkirimService::class)->sendText('628111', 'hi', 'dev-abc');

    Http::assertSent(fn ($req) => $req['device_id'] === 'dev-abc' && $req['to'] === '628111');
}
```

- [ ] **Step 2: Run test — expect FAIL** (method signature / field missing)

```bash
php artisan test --filter=test_flowkirim_send_text_includes_device_field
```

- [ ] **Step 3: Implement**

`config/flowkirim.php`:
```php
'device_field' => env('FLOWKIRIM_DEVICE_FIELD', 'device_id'),
```

`.env.example`:
```env
FLOWKIRIM_DEVICE_FIELD=device_id
```

Update `sendText` payload:
```php
$payload = [
    'to' => $to,
    'message' => $message,
    'type' => 'text',
];
$field = (string) config('flowkirim.device_field', 'device_id');
if ($providerDeviceId !== null && $providerDeviceId !== '' && $field !== '') {
    $payload[$field] = $providerDeviceId;
}
```

- [ ] **Step 4: Run test — expect PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat: pass FlowKirim device field on sendText"
```

---

### Task 3: Resolve device in InvitationWhatsappService

**Files:**
- Modify: `app/Services/InvitationWhatsappService.php`

**Produces:**
- `sendToGuest(..., ?int $deviceId = null)`
- `sendToGuests(..., ?int $deviceId = null)`
- Private `resolveDevice(Event $event, ?int $deviceId): WhatsappDevice|string` — returns model or error string
- Sets `whatsapp_device_id` on create; calls `sendText($phone, $message, $device->provider_device_id)`

- [ ] **Step 1: Write failing feature tests** (in `InvitationWhatsappSendTest`)

Cases:
1. Event has default active device → send succeeds and asserts HTTP has provider id; `invitation_sends.whatsapp_device_id` set
2. Override `device_id` in body uses that device, not event default
3. No default and no body device → 422 / failed with message about device
4. Inactive device → failed, Http::assertNothingSent (or not sent for that guest)

- [ ] **Step 2: Run tests — expect FAIL**

- [ ] **Step 3: Implement resolution**

```php
private function resolveDevice(Event $event, ?int $localDeviceId): WhatsappDevice|string
{
    $id = $localDeviceId ?? $event->whatsapp_device_id;
    if ($id === null) {
        return 'No WhatsApp device selected. Set an event default or pass device_id.';
    }
    $device = WhatsappDevice::query()->find($id);
    if ($device === null) {
        return 'WhatsApp device not found.';
    }
    if (! $device->is_active) {
        return 'WhatsApp device is inactive.';
    }
    return $device;
}
```

On `attemptSend`, resolve first; if string error → create send row with `whatsapp_device_id` null (or resolved id if found but inactive) and `markFailed`. If success path, set FK and pass `provider_device_id` to Flowkirim.

- [ ] **Step 4: Update controller + FormRequests to pass optional `device_id`** (can be same commit as Task 4 if preferred; otherwise stub signature now and wire in Task 4)

Minimal for tests: update `SendInvitationRequest` / `BulkSendInvitationRequest`:

```php
'device_id' => ['sometimes', 'nullable', 'integer', 'exists:whatsapp_devices,id'],
```

Controller:
```php
$service->sendToGuest($event, $guest, $request->validated('message'), $request->user(), $request->validated('device_id'));
```

- [ ] **Step 5: Run InvitationWhatsapp tests — expect PASS**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: resolve WhatsApp device on invitation send"
```

---

### Task 4: WhatsappDevice CRUD API + event default field

**Files:**
- Create: `WhatsappDeviceController`, store/update FormRequests
- Modify: `routes/api.php`, `EventUpdateRequest`, `EventStoreRequest` (optional), `EventController` show/update to `load('whatsappDevice')`, `InvitationSendController::sendPayload` to include device summary

**Produces:** REST `/api/whatsapp-devices`; events accept `whatsapp_device_id`

- [ ] **Step 1: Failing tests in `tests/Feature/WhatsappDeviceTest.php`**

- Admin can create/list/update/toggle `is_active`
- Panitia forbidden
- DELETE unused → 200; DELETE referenced by event → 422
- PATCH event with `whatsapp_device_id` persists

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement controller**

```php
// destroy
if ($device->events()->exists() || $device->invitationSends()->exists()) {
    return response()->json([
        'message' => 'Device is in use. Deactivate it instead of deleting.',
    ], 422);
}
$device->delete();
```

Payload helper:
```php
[
  'id' => $device->id,
  'name' => $device->name,
  'provider_device_id' => $device->provider_device_id,
  'phone_label' => $device->phone_label,
  'is_active' => $device->is_active,
  'created_at' => $device->created_at,
  'updated_at' => $device->updated_at,
]
```

Routes inside `role:admin`:
```php
Route::apiResource('whatsapp-devices', WhatsappDeviceController::class);
```

`EventUpdateRequest` add:
```php
'whatsapp_device_id' => ['sometimes', 'nullable', 'integer', 'exists:whatsapp_devices,id'],
```

`sendPayload` add nested `device` when relation loaded or loadAlways.

- [ ] **Step 4: Run WhatsappDeviceTest + InvitationWhatsappSendTest — PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat: WhatsApp device CRUD and event default device"
```

---

### Task 5: Frontend — Devices page + nav

**Repo:** `C:\laragon\www\wedding-invitation-web`

**Files:**
- Create: `src/pages/admin/WhatsappDevicesPage.tsx`
- Modify: `src/App.tsx`, `src/components/AdminLayout.tsx`

- [ ] **Step 1: Add nav link + route** `/admin/whatsapp-devices` under `AdminOnlyRoute`

- [ ] **Step 2: Build CRUD page** mirroring `UsersPage` patterns (`api`, `ensureCsrfCookie`, form + table)

Fields: name, provider_device_id, phone_label, is_active checkbox. Actions: save, edit, deactivate/activate, delete (show API error if 422).

- [ ] **Step 3: Manual smoke** — list empty, create device, toggle active

- [ ] **Step 4: Commit (web repo)**

```bash
git commit -m "feat: admin WhatsApp devices page"
```

---

### Task 6: Frontend — event default device

**Files:**
- Modify: `src/pages/admin/EventsPage.tsx`

- [ ] **Step 1: Load `/api/whatsapp-devices` (filter active for select; show inactive label if current default inactive)**

- [ ] **Step 2: Extend `EventRow` with `whatsapp_device_id: number | null`

- [ ] **Step 3: Form select “Device pengirim default”; include `whatsapp_device_id` in create/update payload (null allowed)

- [ ] **Step 4: Ensure event show/list returns the field (API already does via fillable/casts — may need `EventController` to append or client reads from GET by id when editing)

When editing, if list payload lacks FK, `GET /api/events/{id}` on startEdit.

- [ ] **Step 5: Commit**

```bash
git commit -m "feat: set default WhatsApp device on events"
```

---

### Task 7: Frontend — Guests send WhatsApp UI

**Files:**
- Modify: `src/pages/admin/GuestsPage.tsx`

- [ ] **Step 1: Load event (`GET /api/events/:id`) + devices list on mount**

- [ ] **Step 2: UI for single send** — button per row “Kirim WA”; modal/panel with message textarea (default template including `{nama}` and `{link}`), device select (default = event.whatsapp_device_id), submit → `POST .../guests/{id}/send-invitation`

- [ ] **Step 3: Bulk send** — checkboxes or “kirim terpilih”; `POST .../guests/send-invitations` with `guest_ids`, `message`, optional `device_id`; show sent/failed counts

- [ ] **Step 4: Surface errors** from 422 `data.error_message` / bulk results

- [ ] **Step 5: Optional** — small “Riwayat” fetch `GET .../guests/{id}/invitation-sends` or link text showing last status if already loaded

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: send invitation WhatsApp from guests admin page"
```

---

### Task 8: Verification + docs touch-up

- [ ] **Step 1: API full related tests**

```bash
cd C:\laragon\www\wedding-invitation-api
php artisan test --filter=InvitationWhatsapp
php artisan test --filter=WhatsappDevice
```

Expected: all pass

- [ ] **Step 2: Web build**

```bash
cd C:\laragon\www\wedding-invitation-web
npm run build
```

Expected: exit 0

- [ ] **Step 3: Confirm `.env.example` has `FLOWKIRIM_DEVICE_FIELD`

- [ ] **Step 4: No commit required unless docs/env drifted; if so commit docs only

---

## Spec coverage checklist

| Spec item | Task |
|-----------|------|
| `whatsapp_devices` table | 1 |
| Event + send FKs | 1 |
| `FLOWKIRIM_DEVICE_FIELD` + payload | 2 |
| Resolve device order + inactive | 3 |
| Device CRUD + DELETE rules | 4 |
| Event `whatsapp_device_id` API | 4 |
| Send optional `device_id` + history device | 3–4 |
| Admin Devices page | 5 |
| Event default dropdown | 6 |
| Guests send UI single/bulk | 7 |
| Tests | 2–4, 8 |

## Out of scope (do not implement)

- FlowKirim QR pairing / auto-sync `/v1/devices`
- Per-device tokens
- Queue workers / webhooks
