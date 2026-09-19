# Guest Relation Labels Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Per-event configurable guest relation options (CRUD) with optional FK on guests, shown in admin Guests + Guestbook.

**Architecture:** New `guest_relations` table; `guests.guest_relation_id` nullable FK with `nullOnDelete`. Admin CRUD under `/api/events/{event}/guest-relations`. Guest payloads and guestbook include `relation: { id, label } | null`. Import matches label text case-insensitively.

**Tech Stack:** Laravel API, React admin UI, PHPUnit

## Global Constraints

- Spec: `docs/superpowers/specs/2026-09-19-guest-relation-labels-design.md`
- Relation optional; VIP/Regular unchanged
- Per-event options only
- Show in admin guests + guestbook; not on public invitation
- Hard delete options; guests nullified via FK
- Do not commit unless user asks

---

## File map

| File | Change |
|------|--------|
| `database/migrations/2026_09_19_120000_create_guest_relations_table.php` | Create table + FK on guests |
| `app/Models/GuestRelation.php` | Model |
| `app/Models/Guest.php` / `Event.php` | Relations + fillable |
| `app/Http/Controllers/Api/GuestRelationController.php` | CRUD |
| Requests for store/update relation | Validation |
| `GuestController`, store/update requests | Accept + payload relation |
| `GuestbookController` | Include relation |
| `GuestImportService` | Column `relation` |
| `routes/api.php` | Routes |
| `tests/Feature/GuestRelationTest.php` | Feature tests |
| `GuestsPage.tsx`, `GuestbookPage.tsx` | UI |

---

### Task 1: Migration + models

- Create `guest_relations` (`event_id`, `label`, `sort_order`, unique event+label)
- Add `guests.guest_relation_id` nullable FK `nullOnDelete`
- Models + Event `guestRelations()` ordered by sort_order, id

### Task 2: GuestRelation CRUD API + tests

- Controller + FormRequests
- Routes under `role:admin` next to guests
- Tests: create, duplicate 422, update, delete nullifies guest FK

### Task 3: Wire guest CRUD + guestbook + import

- Validate `guest_relation_id` belongs to event
- Payload `relation`
- Import column `relation` + template sample
- Guestbook index includes relation

### Task 4: Frontend GuestsPage + GuestbookPage

- Kelola Relasi panel
- Select on form + table column
- Guestbook show label

### Task 5: Verify

- `php artisan test --filter=GuestRelation`
- Manual: CRUD options, assign guest, guestbook, import
