# Doa & Ucapan Scroll + Admin List Design

## Goal

1. Public invitation “Doa & Ucapan” history scrolls inside a fixed-height box (~300px) so the section does not grow unbounded.
2. Admin can open a per-event “Lihat semua” page listing every wish (no public 30/50 limit).

## Public invitation

- In `WishesSection`, wrap the wish cards list in a scroll container (`max-height: ~300px`, `overflow-y: auto`).
- Keep the “Doa & Ucapan dari tamu” heading and thank-you line outside the scroll area.
- Form submit stays unchanged above the list.

## Admin

- New route: `/admin/events/:id/wishes`.
- New API: `GET /api/events/{event}/wishes` (auth admin), paginated, ordered latest first. Payload: id, guest_name, message, rsvp_status, created_at.
- UI mirrors envelope transactions page: back link, event name, table of all wishes.
- Entry points: “Ucapan” / “Lihat semua” link on Events list actions; also link from Invitation content page settings area near universal/wishes context if natural.

## Out of scope

- Editing/deleting wishes from admin.
- Changing public invitation fetch limit (still capped for page load; admin list is the full history).
