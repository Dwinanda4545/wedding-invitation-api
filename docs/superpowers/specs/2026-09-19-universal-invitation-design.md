# Design: Universal Invitation (Open Link)

Date: 2026-09-19  
Status: Approved (pending user review of written spec)

## Goal

Provide a shareable invitation link that is **not tied to one guest**, for WhatsApp/Instagram group sharing. Same event content as guest invitations, with a configurable generic greeting and **no QR section**. Per-guest invitation URLs continue to work in parallel.

## Decisions

| Topic | Choice |
|-------|--------|
| Purpose | Share to groups (many people, one link) |
| Greeting | Editable generic text (default: “Yth. Bapak/Ibu/Saudara/i”) |
| Interactive (wishes, digital envelope) | Remain enabled without a registered guest |
| URL shape | Secret token (hard to guess), not event slug |
| Architecture | Event-level open token (approach 2) — not a fake guest row |
| Coexistence | Per-guest `/invitation/{secret_token}` and universal `/invitation/open/{token}` both live |

## Architecture

### Parallel access paths

| Path | Route (web) | API | QR | Greeting |
|------|-------------|-----|----|----------|
| Per-guest | `/invitation/{secret_token}` | `GET /api/invitation/{secret_token}` | Per settings / check-in | `guest.name` |
| Universal | `/invitation/open/{universal_token}` | `GET /api/invitation/open/{token}` | Always hidden | `universal_greeting` |

### Event fields (new)

- `universal_invitation_token` — unique secret string, nullable until enabled
- `universal_invitation_enabled` — boolean (default `false`)
- `universal_greeting` — string (default `Yth. Bapak/Ibu/Saudara/i`)

Admin can enable/disable, edit greeting, copy link, and regenerate token (invalidates the previous link).

### Public API

- `GET /api/invitation/open/{token}`
  - Resolves event where token matches and `universal_invitation_enabled` is true
  - Returns invitation payload shaped like the guest invitation where practical
  - **Omits** guest identity and `qr_code_url`
  - Exposes greeting via a clear field (e.g. `greeting` or `universal_greeting`) for the frontend
  - Unknown / disabled token → **404** (same UX as missing guest invite)

- Dedicated open-token write endpoints mirroring guest ones, e.g.:
  - `POST /api/invitation/open/{token}/wishes`
  - `POST /api/invitation/open/{token}/digital-envelope`
  - (update/delete wishes as needed, same rules as guest path but resolved via open token → event)

## Interactive features

### Wishes

- `invitation_wishes.guest_id` is already nullable
- From universal link: store with `event_id` + `guest_id = null`; sender name from form
- No synthetic guest row

### Digital envelope

- Make `envelope_transactions.guest_id` **nullable** (migration), mirroring wishes
- From universal link: `event_id` + `guest_id = null`; sender data from form
- Check-in / QR are not involved

### Unchanged

- Per-guest wishes/envelopes still bind to `guest_id` when opened via guest token
- Scanner / check-in remain guest-token only
- Guest list admin UI does not gain a fake “universal” guest

## Frontend

### Public

- New route: `/invitation/open/:token`
- Reuse `SectionInvitation` / HTML invitation modes
- Pass `universal_greeting` wherever `guest.name` is used for cover/salutation
- Force-hide QR (do not render `QrSection`; ignore `sections.qr` for this path)
- Cover, music, gallery, wishes, envelope behave as today; API calls use open-token endpoints

### Admin

- On event invitation settings: “Undangan Universal” card
  - Toggle enable/disable
  - Edit greeting
  - Copy link + regenerate token
- Guest list unchanged

## Edge cases

- Universal disabled or wrong token → 404
- Both `sections` and `html` invitation modes: no QR on universal path
- Universal wishes/envelopes do not appear on a guest detail page; they remain visible in event-level lists if those already exist
- Per-guest and universal links can be used simultaneously without interfering
- Regenerating token immediately invalidates the old URL

## Out of scope

- Event-slug public URLs
- RSVP / attendance on the universal link
- Creating a hidden system guest for envelopes
- Changing check-in / scanner flows
- WhatsApp bulk-send using the universal link (admin copy-paste only for v1)

## Verification

- Open universal link → generic greeting, no QR
- Submit wish + envelope from universal link → stored with `guest_id` null
- Per-guest link still shows QR when section QR is on
- Disable or regenerate → old universal link fails with 404
