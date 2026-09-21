# Multiple Universal Invitations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace single event-level universal invite with many named `universal_invitations` rows (name, greeting, token, enabled).

**Architecture:** New table + model; public open routes resolve by invitation token; admin CRUD under `/events/{event}/universal-invitations`; drop old event columns; no data migration.

**Tech Stack:** Laravel, React admin, PHPUnit

## Global Constraints

- Spec: `docs/superpowers/specs/2026-09-21-multiple-universal-invitations-design.md`
- Default greeting: `Yth. Bapak/Ibu/Saudara/i`
- Old event fields dropped; old links not migrated
- Public URL stays `/invitation/open/{token}`
- Do not commit unless user asks

---

### Task 1: Migration + model
### Task 2: Rewrite public open resolution + tests
### Task 3: Admin CRUD API; remove event-level fields
### Task 4: Frontend list manager on InvitationContentPage
### Task 5: Verify tests
