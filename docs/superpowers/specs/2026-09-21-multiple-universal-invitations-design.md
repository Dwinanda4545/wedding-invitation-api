# Design: Multiple Universal Invitations (Named Open Links)

Date: 2026-09-21  
Status: Approved (pending user review of written spec)

## Goal

Replace the single event-level universal invitation with **many named open links per event** (e.g. “Grup Keluarga A”, “Grup Keluarga B”). Each link has its own admin label, public greeting, enable toggle, and secret token. Per-guest invitations and “no QR” behavior stay the same.

## Decisions

| Topic | Choice |
|-------|--------|
| What is saved per link | Admin **name/label** + public **greeting** |
| Enable/disable | Per-item toggle |
| Existing single link | **Not migrated** — admin recreates; old URLs die after deploy |
| Architecture | Table `universal_invitations` (not JSON on events) |
| Public URL | Unchanged shape: `/invitation/open/{token}` |

## Data model

### New table `universal_invitations`

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint PK | |
| `event_id` | FK → events | cascade on delete |
| `name` | string 255 | admin label, required |
| `greeting` | string 255 | public salutation; empty → default |
| `token` | string 64 | unique, secret |
| `enabled` | boolean | default `true` |
| `sort_order` | unsigned int | default 0 |
| timestamps | | |

Default greeting constant (unchanged): `Yth. Bapak/Ibu/Saudara/i`.

### Remove from `events`

Drop after cutover:

- `universal_invitation_token`
- `universal_invitation_enabled`
- `universal_greeting`

No data migration from these columns.

### Models

- `UniversalInvitation` belongs to `Event`
- `Event` hasMany `universalInvitations` ordered by `sort_order`, `id`
- Public resolve: find by `token` where `enabled = true`

## API

### Public (same paths, new resolution)

- `GET /api/invitation/open/{token}`
- `POST /api/invitation/open/{token}/wishes`
- `POST /api/invitation/open/{token}/digital-envelopes`

Resolve `UniversalInvitation` (enabled) → load its `event`. Payload:

- `is_universal: true`
- `greeting` / `guest.name` = invitation greeting (or default)
- `guest.secret_token` = open token (compat for wish/envelope client)
- no QR; wishes/envelopes still `guest_id` null
- Optional v1: include `universal_invitation: { id, name }` for debugging only — **not required** on public page

404 if token missing, disabled, or deleted.

### Admin

Under event admin auth:

| Method | Path | Behavior |
|--------|------|----------|
| GET | `/api/events/{event}/universal-invitations` | List items + `url` each |
| POST | `/api/events/{event}/universal-invitations` | Create; auto-generate token |
| PUT/PATCH | `/api/events/{event}/universal-invitations/{id}` | Update name, greeting, enabled, sort_order |
| DELETE | `/api/events/{event}/universal-invitations/{id}` | Hard delete |
| POST | `/api/events/{event}/universal-invitations/{id}/regenerate` | New token; old URL 404 |

Remove event-level `universal_invitation_*` from invitation update/payload and `POST .../universal-invitation/regenerate`.

Validation:

- `name`: required, string, max 255
- `greeting`: nullable string, max 255
- `enabled`: boolean
- `sort_order`: integer ≥ 0

## Frontend

### Admin (`InvitationContentPage`)

Replace single-card toggle with a **list manager**:

- Add form: name + greeting → create
- Per row: name, greeting, enabled toggle, copy link, regenerate, delete, reorder (↑/↓)
- Empty state: explain old single-link fields are gone; create new links

### Public

- Keep `/invitation/open/:token`
- Greeting from API as today; QR still forced off

## Out of scope (v1)

- Migrating old event columns into rows
- Attributing wishes/envelopes to a specific universal invitation id
- Stats per group
- WhatsApp bulk-send of universal links

## Verification

- Create two links with different greetings → each URL shows its greeting, no QR
- Disable one → only that token 404s
- Regenerate / delete → old token 404s
- Wish from an open link still stores with `guest_id` null
- Guest invitation URLs unchanged
