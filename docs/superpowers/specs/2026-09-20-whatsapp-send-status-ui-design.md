# WhatsApp Send Status History UI

**Date:** 2026-09-20  
**Status:** Implemented  
**Decision:** Tab “Riwayat WA” on Guests page + summary API (approach 2).

## Goal

Show clear send status for WhatsApp invitations at event level: who is never sent / last sent / last failed, plus full attempt history.

## API

`GET /api/events/{event}/invitation-sends/summary`

Returns guest-centric latest status counts plus attempt totals:

- `guests_total`, `never_sent`, `latest_sent`, `latest_failed`, `latest_pending`
- `attempts_sent`, `attempts_failed`, `attempts_pending`

List remains `GET /api/events/{event}/invitation-sends` with `status` / pagination.

## UI

On Guests admin page: tabs **Tamu** | **Riwayat WA**.

Riwayat WA: summary cards + filter + paginated table (time, guest, phone, status badge, device, error).
