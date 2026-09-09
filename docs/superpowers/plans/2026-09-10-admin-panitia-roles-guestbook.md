# Admin / Panitia Roles + Buku Tamu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin/panitia roles with event assignment, Users management, scoped Scan Check-in, and Pusher-powered Buku Tamu with manual check-in/cancel.

**Architecture:** `users.role` + `event_user` pivot; middleware `role:admin`; `User::canAccessEvent()` for guestbook/check-in; broadcast `guest.attendance.updated` on private channel `event.{id}.guestbook`.

**Tech Stack:** Laravel Sanctum, Pusher + Echo, React admin SPA.

**Spec:** `docs/superpowers/specs/2026-09-10-admin-panitia-roles-guestbook-design.md`

## Global Constraints

- Roles only: `admin` | `panitia` (no Spatie)
- Panitia: many events via pivot; admin: all events without pivot
- Realtime: Pusher (not Reverb)
- Check-in undo allowed for both roles on accessible events
- Existing users migrate to `admin`

---

### Task 1: Schema + User/Event models

**Files:**
- Create: `database/migrations/2026_09_10_040000_add_roles_and_event_user_table.php`
- Modify: `app/Models/User.php`, `app/Models/Event.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`

- [ ] Migration: add `users.role` default admin for existing; create `event_user`
- [ ] `User::ROLE_*`, `isAdmin()`, `isPanitia()`, `canAccessEvent()`, `events()` belongsToMany
- [ ] `Event::panitiaUsers()` belongsToMany
- [ ] Tests: unit for `canAccessEvent`
- [ ] Commit

### Task 2: Auth payload + admin middleware + route groups

**Files:**
- Create: `app/Http/Middleware/EnsureUserHasRole.php`
- Modify: `bootstrap/app.php`, `app/Http/Controllers/Api/AuthController.php`, `routes/api.php`

- [ ] Alias middleware `role`
- [ ] `me`/`login` return role + assigned_events
- [ ] Wrap admin-only routes with `role:admin`; keep check-in/guestbook for all auth users
- [ ] Feature test: panitia gets 403 on POST /events
- [ ] Commit

### Task 3: Users API + Event panitia API

**Files:**
- Create: `UserController`, `EventPanitiaController`, form requests
- Modify: `routes/api.php`

- [ ] CRUD users with event_ids sync for panitia; protect self-delete / last admin
- [ ] Event panitia list/assign/unassign/create
- [ ] Feature tests
- [ ] Commit

### Task 4: Guestbook + scoped check-in + broadcast

**Files:**
- Create: `GuestbookController`, `GuestAttendanceUpdated` event, `routes/channels.php` if needed
- Modify: `CheckInController`, `CheckInRequest`, `bootstrap` broadcasting, `.env.example`

- [ ] Guestbook index + check-in + cancel
- [ ] Check-in requires `event_id`; validate guest belongs to event + canAccessEvent
- [ ] Broadcast after attendance changes
- [ ] Feature tests
- [ ] Commit

### Task 5: Frontend auth guards + layout menus

**Files:**
- Modify: `AuthContext.tsx`, `AdminLayout.tsx`, `App.tsx`, `ProtectedRoute` / new `AdminOnlyRoute`

- [ ] Extend AuthUser; role-based nav; panitia default redirect
- [ ] Commit (web repo)

### Task 6: Users page + Acara panitia section

**Files:**
- Create: `UsersPage.tsx`
- Modify: `EventsPage.tsx` or invitation/events detail for panitia section

- [ ] Full Users CRUD UI
- [ ] Panitia assign UI on event
- [ ] Commit

### Task 7: Scanner event scope + Buku Tamu + Pusher

**Files:**
- Modify: `ScannerPage.tsx`
- Create: `GuestbookPage.tsx`, Echo helper
- Modify: `package.json` (laravel-echo, pusher-js), `.env.example`

- [ ] Scanner requires event_id
- [ ] Guestbook list/filter/manual toggle + Echo subscribe
- [ ] Commit

### Task 8: Docs env notes

- [ ] Update `docs/DEPLOY-RUMAHWEB.md` with PUSHER_* and role overview
- [ ] Commit API
