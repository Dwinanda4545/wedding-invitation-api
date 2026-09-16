# WhatsApp Invitation Sending (FlowKirim)

**Date:** 2026-09-16  
**Status:** Approved  
**Decision:** Manual send (per guest + bulk), sync delivery (shared hosting), editable message template, full send history.

## Goal

Admins can send wedding invitation links to guests via WhatsApp using the FlowKirim REST API, targeting each guest’s `phone_number`.

## Decisions

- **Trigger:** Manual only (admin action). No auto-send on create/import.
- **Scope:** Single guest + bulk (max 50 guests per request).
- **Message:** Admin-editable template with placeholders `{nama}` and `{link}`.
- **Delivery:** Synchronous HTTP (no queue) — suitable for shared hosting without long-running workers.
- **History:** Every attempt stored in `invitation_sends` (`pending` → `sent` | `failed`).
- **Provider:** FlowKirim `POST /v1/messages` with Bearer token.

## Architecture

1. Admin calls send endpoint with message template.
2. `InvitationWhatsappService` normalizes phone, renders placeholders, inserts `pending` row.
3. `FlowkirimService` POSTs to FlowKirim.
4. Row updated to `sent` or `failed`.
5. Bulk continues on per-guest failure; response includes counts + details.

## Data model

Table `invitation_sends`:

| Column | Notes |
|--------|--------|
| `event_id`, `guest_id` | FKs |
| `phone_number` | Normalized `62…` used for send |
| `message_body` | Final rendered text |
| `status` | `pending` \| `sent` \| `failed` |
| `provider` | `flowkirim` |
| `provider_message_id` | Nullable |
| `error_message` | Nullable |
| `sent_by` | Admin user id |
| `sent_at` | Nullable |
| timestamps | |

No status columns on `guests`. Invitation URL: `{FRONTEND_URL}/invitation/{secret_token}`.

## API

Admin Sanctum + `role:admin`:

- `POST /api/events/{event}/guests/{guest}/send-invitation` — `{ "message": "..." }`
- `POST /api/events/{event}/guests/send-invitations` — `{ "guest_ids": [...], "message": "..." }`
- `GET /api/events/{event}/invitation-sends` — filters: `guest_id`, `status`
- `GET /api/events/{event}/guests/{guest}/invitation-sends`

## Config

```env
FLOWKIRIM_API_TOKEN=
FLOWKIRIM_BASE_URL=https://api.flowkirim.com
FLOWKIRIM_TIMEOUT=20
```

## Error handling

- Missing/invalid phone → `failed`, no API call.
- HTTP failure → `failed` + truncated error.
- Bulk continues independently per guest.
- Bulk capped at 50 ids.

## Out of scope (v1)

- Queue/cron workers
- Delivery webhooks (delivered/read)
- Auto-send on guest create/CSV import
- Frontend UI (API only)
