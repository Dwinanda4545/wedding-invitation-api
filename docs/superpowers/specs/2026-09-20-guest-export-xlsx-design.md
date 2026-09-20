# Design: Guest Export XLSX (with Invitation Links)

Date: 2026-09-20  
Status: Approved (pending user review of written spec)

## Goal

Let admins download an **XLSX** of all active guests for an event, including each guest’s **invitation URL**, for sharing / offline tracking.

## Decisions

| Topic | Choice |
|-------|--------|
| Format | XLSX only |
| Scope | All non–soft-deleted guests for the event |
| Columns | name, phone_number, guest_type, relation, invitation_url, is_attended, scanned_at |
| Architecture | API generates file via ZipArchive (no PhpSpreadsheet; shared-hosting safe) |
| Selection filter | Not supported in v1 (export all only) |

## API

| Method | Path | Behavior |
|--------|------|----------|
| GET | `/api/events/{event}/guests/export` | Stream XLSX download (admin auth) |

- Register **before** `/events/{event}/guests/{guest}` routes.
- Soft-deleted guests excluded (default SoftDeletes scope).
- Order: `name` ascending, then `id`.
- Content-Type: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
- Filename: `guests-{eventId}-{YmdHis}.xlsx` (via `Content-Disposition`)

### Row mapping

| Column | Source |
|--------|--------|
| `name` | `guests.name` |
| `phone_number` | `guests.phone_number` (empty if null) |
| `guest_type` | `guests.guest_type` |
| `relation` | `guest_relations.label` via relation (empty if null) |
| `invitation_url` | `{FRONTEND_URL}/invitation/{secret_token}`; empty string if `FRONTEND_URL` unset |
| `is_attended` | `Ya` if true, else `Tidak` |
| `scanned_at` | ISO-like datetime string, or empty if null |

No DB migration.

## Backend components

- `GuestExportService`: build minimal OOXML workbook with ZipArchive + sheet XML; stream via `StreamedResponse`.
- `GuestController::export(Event, GuestExportService)`: load guests with `relation`, call service.
- Reuse `config('app.frontend_url')` the same way as `guestPayload()` invitation links.

Empty guest list → XLSX with header row only (still 200).

## Frontend

- `GuestsPage`: button **Export XLSX** next to **Download template**.
- Same blob-download pattern as `downloadImportTemplate()`.
- Prefer filename from `Content-Disposition`; fallback `guests-export.xlsx`.
- Toast on success; surface API/JSON error message on failure.

## Out of scope

- CSV / dual format
- Export selected rows only
- Including soft-deleted guests
- PhpSpreadsheet dependency
- Changing invitation URL path (stays `/invitation/{token}`)

## Deploy notes

- API: push + cPanel Git deploy (no Composer change).
- Frontend: rebuild zip and upload; verify new `index-*.js` in view-source.
- Production `.env` must have `FRONTEND_URL=https://wedding-invitation.sanadwi.my.id` so `invitation_url` is filled.
