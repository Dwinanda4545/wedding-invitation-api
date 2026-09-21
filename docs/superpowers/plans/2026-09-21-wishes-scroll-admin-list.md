# Wishes Scroll + Admin List Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Scrollable public wish history (~300px) + admin per-event full wish list page.

**Architecture:** Public UI-only scroll wrapper in `WishesSection`. Admin list mirrors envelope transactions: Laravel index endpoint + React page + Events/Invitation links.

**Tech Stack:** Laravel API, React Vite SPA, existing Sanctum admin routes.

## File map

| File | Role |
|------|------|
| `app/Http/Controllers/Api/EventWishController.php` | Admin index |
| `routes/api.php` | Register GET events/{event}/wishes |
| `tests/Feature/EventWishAdminTest.php` | Auth + list test |
| `wedding-invitation-web/.../WishesSection.tsx` | Scroll container |
| `wedding-invitation-web/.../EventWishesPage.tsx` | Admin list UI |
| `App.tsx` | Route |
| `EventsPage.tsx` | Link “Ucapan” |
| `InvitationContentPage.tsx` | Link “Lihat semua” |

### Task 1: API + test

- Write failing feature test for admin list.
- Implement `EventWishController@index` paginated.
- Register route in admin group.

### Task 2: Public scroll

- Wrap wish list in `max-h-[300px] overflow-y-auto` (plus light scrollbar styling if needed in CSS).

### Task 3: Admin page + links

- Create `EventWishesPage`, wire route, add Events + Invitation links.

### Task 4: Verify

- Run PHPUnit for new test; build frontend if needed.
