# Design: Guest Relation Labels (Per Event)

Date: 2026-09-19  
Status: Approved (pending user review of written spec)

## Goal

Add an optional **relation** label on guests (family/social tie to the couple), with a **per-event selectable list** that admins can add, edit, reorder, and remove. VIP/Regular `guest_type` stays unchanged. Relation appears in admin Guests UI and Guestbook/check-in — **not** on the public invitation.

## Decisions

| Topic | Choice |
|-------|--------|
| Meaning | Family/social relation (separate from VIP/Regular) |
| Option scope | Per event |
| Required on guest | Optional (nullable) |
| Visibility | Admin guests + Guestbook/check-in |
| Architecture | Table `guest_relations` + FK `guests.guest_relation_id` (approach 2) |
| Delete option | Hard delete; guests using it get `guest_relation_id = null` |

## Data model

### `guest_relations`

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint PK | |
| `event_id` | FK → events | cascade on delete |
| `label` | string 255 | display text |
| `sort_order` | unsigned int | default 0 |
| timestamps | | |

Unique: `(event_id, label)`.

### `guests`

- Add `guest_relation_id` nullable FK → `guest_relations`, `nullOnDelete`
- Existing `guest_type` enum `VIP` \| `Regular` unchanged

### Models

- `GuestRelation` belongs to `Event`; has many `Guest`
- `Guest` belongs to `GuestRelation` (optional)
- `Event` has many `GuestRelation` ordered by `sort_order`, then `id`

## API

All under admin auth, event-scoped (same middleware as guest CRUD).

| Method | Path | Behavior |
|--------|------|----------|
| GET | `/api/events/{event}/guest-relations` | List ordered options |
| POST | `/api/events/{event}/guest-relations` | Create `{ label, sort_order? }` |
| PUT/PATCH | `/api/events/{event}/guest-relations/{relation}` | Update label / sort_order |
| DELETE | `/api/events/{event}/guest-relations/{relation}` | Hard delete; guests nullified via FK |

Guest store/update/import:

- Accept optional `guest_relation_id`
- Must belong to the same `event_id` or null → otherwise 422
- Guest JSON includes `relation: { id, label } | null`
- Guestbook guest payloads include the same `relation` field

Validation:

- `label`: required, string, max 255, unique per event
- Empty / duplicate label → 422

## Admin UI (`GuestsPage`)

- **Kelola Relasi** panel: add, rename, delete, reorder (up/down buttons)
- Guest form: optional Relasi `<select>` alongside Tipe
- Guests table: Relasi column

## Import Excel

- New column `relation` (label text)
- Match case-insensitive to existing `guest_relations.label` for that event
- No match → leave null + per-row warning
- Downloadable template includes `relation` sample column

## Guestbook / check-in

- Show relation label next to guest name when present
- No change to scan/check-in flow

## Out of scope (v1)

- Public invitation display
- Filtering/stats by relation
- Soft-deleted relation options
- Global (cross-event) relation catalog

## Verification

- Create 2–3 relations → appear in guest select
- Save guest with/without relation → table + guestbook match
- Import with `relation` column → match or warn
- Delete option → removed from select; prior guests show empty relation
- Public invitation unchanged
