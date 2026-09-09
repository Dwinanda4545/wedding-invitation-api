# Design: Role Admin / Panitia, Users, Buku Tamu Realtime

Date: 2026-09-10  
Status: Approved (conversation) — pending user review of this written spec

## Goal

Introduce two user roles (**admin**, **panitia**), event assignment for panitia, an admin **Users** management UI, a **Buku Tamu** page with live attendance updates via **Pusher**, and scoped **Scan Check-in** / manual check-in (including undo).

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Role model | `users.role` string enum `admin` \| `panitia` + pivot `event_user` |
| Assignment | One panitia → many events |
| Realtime | Pusher (cloud) + Laravel Echo |
| Create / assign panitia | Both: global **Users** menu and per-**Acara** panitia section |
| Check-in undo | Allowed for both admin and panitia (on events they can access) |
| Auth package | No Spatie — simple role + middleware/policies |

## Current state

- Sanctum SPA cookie auth; flat authenticated users (no roles).
- Guests have `is_attended`, `scanned_at`; `POST /api/check-in` by `secret_token` (not event-scoped).
- Admin UI: Acara + Scan Check-in only; no Users / Buku Tamu menus.

## Data model

### `users`

Add:

- `role` — `string`, values `admin` \| `panitia`, not null  
  - Migration: existing rows → `admin`

### `event_user` (pivot)

| Column | Type |
|--------|------|
| `id` | bigint PK |
| `user_id` | FK → users, cascade delete |
| `event_id` | FK → events, cascade delete |
| `timestamps` | |

Unique (`user_id`, `event_id`).

- Used for **panitia** only. Admin does not require pivot rows (implicit access to all events).
- Detaching a panitia from an event removes the pivot row only (user account may remain).

### Relationships

- `User` `belongsToMany` `Event` (via `event_user`)
- `Event` `belongsToMany` `User`

## Authorization

### Matrix

| Capability | Admin | Panitia |
|------------|-------|---------|
| Events CRUD, invitation, envelopes, guest import/edit/delete | Yes | No |
| Users CRUD (all accounts) | Yes | No |
| Assign / unassign panitia to events | Yes | No |
| Scan check-in | All events | Assigned events only |
| Buku tamu list + manual check-in / cancel | All events | Assigned events only |

### API enforcement

- Middleware `role:admin` (or equivalent) on user management and full event/guest management routes.
- Helper / policy `User::canAccessEvent(Event $event)`:
  - admin → true
  - panitia → pivot exists
- Check-in and guestbook endpoints must call this for the target `event_id`.
- Panitia calling admin-only routes → `403`.

### `GET /api/me`

Extend payload:

```json
{
  "id": 1,
  "name": "...",
  "email": "...",
  "role": "admin" | "panitia",
  "assigned_events": [
    { "id": 1, "name": "Raka & Sinta" }
  ]
}
```

- Admin: `assigned_events` may be `[]` (UI treats as “all events” via separate events list).
- Panitia: only assigned events; frontend uses this for selectors and route guards.

## API surface

### Users (admin only)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/users` | List users (+ assigned event ids/names) |
| POST | `/api/users` | Create user (`name`, `email`, `password`, `role`, `event_ids[]` if panitia) |
| GET | `/api/users/{user}` | Show |
| PUT/PATCH | `/api/users/{user}` | Update (optional password; role; event_ids for panitia) |
| DELETE | `/api/users/{user}` | Delete (forbid self-delete; forbid deleting last admin) |

Validation highlights:

- `role` in `admin`,`panitia`
- Panitia: `event_ids` optional array of existing event ids (can be empty until assigned)
- Admin role: clear `event_user` rows on save (or ignore event_ids)

### Event panitia (admin only)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/events/{event}/panitia` | List panitia assigned to event |
| POST | `/api/events/{event}/panitia` | Assign existing user id(s) and/or create panitia then assign |
| DELETE | `/api/events/{event}/panitia/{user}` | Unassign from this event |

Creating a new panitia from the event page uses the same rules as Users create (`role=panitia`) then attaches pivot.

### Guestbook & attendance

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/events/{event}/guestbook` | Guests for event: id, name, guest_type, is_attended, scanned_at (search/filter query optional) |
| POST | `/api/events/{event}/guests/{guest}/check-in` | Set `is_attended=true`, `scanned_at=now()` (idempotent if already attended) |
| POST | `/api/events/{event}/guests/{guest}/check-in/cancel` | Set `is_attended=false`, `scanned_at=null` |

- Guest must belong to `{event}` or `404`.
- Caller must `canAccessEvent` or `403`.

### QR check-in (update existing)

`POST /api/check-in`

Body:

- `secret_token` (required)
- `event_id` (required)

Behavior:

1. Resolve guest by `secret_token`
2. Guest’s `event_id` must equal request `event_id` → else `422`/`404`
3. Caller must `canAccessEvent(event)` → else `403`
4. Same attendance update as today
5. Broadcast attendance update (see below)

## Broadcasting (Pusher)

### Config

API `.env`:

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=
```

Web `.env` / `.env.production`:

```env
VITE_PUSHER_APP_KEY=
VITE_PUSHER_APP_CLUSTER=
```

### Channel & event

- Channel: `private-event.{eventId}.guestbook`
- Auth: Sanctum session + verify `canAccessEvent`
- Broadcast event name: `guest.attendance.updated`
- Payload: `{ guest_id, name, guest_type, is_attended, scanned_at }`

Fired after: QR check-in, manual check-in, cancel check-in.

## Frontend

### Auth / guards

- Extend `AuthContext` / user type with `role`, `assigned_events`
- `AdminOnlyRoute` for `/admin/users`, `/admin/events` (and nested invitation/guests/envelopes)
- Panitia landing: `/admin/scanner` or `/admin/guestbook` (not events list)
- Sidebar filtered by role

### Sidebar

**Admin:** Acara | Users | Scan Check-in | Buku Tamu  

**Panitia:** Scan Check-in | Buku Tamu  

### Users page (`/admin/users`) — admin

- Table: name, email, role, assigned events, actions
- Create / edit modal: fields above; multi-select events when role = panitia
- Delete with confirm; block self / last admin with clear error

### Acara → Panitia section — admin

- List assigned panitia for current event
- Assign existing panitia (select from users with role panitia)
- Create new panitia + assign to this event
- Unassign

### Scanner (`/admin/scanner`)

- Event selector: admin = all events; panitia = `assigned_events`
- Require selected event before enabling camera
- Send `event_id` with check-in request
- Show clear error if QR guest is for another event

### Buku Tamu (`/admin/guestbook`)

- Event selector (same scoping as scanner)
- Summary counts: total / hadir / belum
- Filters: all | attended | not attended; search by name
- Row actions: Check-in / Batalkan
- Subscribe to Pusher private channel for selected event; patch row on `guest.attendance.updated`
- Initial load via `GET .../guestbook`; optional light polling fallback only if Echo fails (not primary path)

## Seed / migration notes

- Existing users → `role = admin`
- Default seeder admin remains admin
- No panitia seeded by default (optional factory for tests)

## Out of scope

- Additional roles beyond admin / panitia
- Spatie Permission
- Self-registration of panitia
- Guest self check-in from public invitation
- Reverb / self-hosted WebSocket
- Changing public invitation QR payload format

## Testing (high level)

- Migration assigns admin to existing users
- Panitia cannot hit `/api/users` or event CRUD
- Panitia check-in / guestbook only for assigned events
- Admin check-in requires matching `event_id` + token
- Manual check-in / cancel updates DB and would broadcast (faked in unit/feature where practical)
- Last-admin and self-delete protected
- Frontend route guards hide admin menus for panitia

## Deploy notes (Rumahweb)

- New migration via Git Deploy or phpMyAdmin if deploy stuck
- Add `PUSHER_*` to API `.env`; `VITE_PUSHER_*` then rebuild frontend `dist/`
- Enable Broadcasting; ensure `/broadcasting/auth` works with Sanctum SPA cookies / same-site setup
