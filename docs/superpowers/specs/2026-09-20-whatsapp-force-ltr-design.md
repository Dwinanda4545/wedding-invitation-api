# WhatsApp Force LTR (Arabic Mixed Messages)

**Date:** 2026-09-20  
**Updated:** 2026-09-20 (iOS / Web strengthening)  
**Status:** Approved (option A)  
**Repos:** `wedding-invitation-api` (`config/flowkirim.php`, `InvitationWhatsappService`)

## Problem

WhatsApp uses the Unicode Bidirectional Algorithm. When an invitation template starts with Arabic script (e.g. بسم الله…), the message bubble becomes RTL, so Indonesian lines appear right-aligned.

A single leading LRM (`U+200E`) is often enough on Android, but **iPhone WhatsApp and WhatsApp Web/Desktop** re-evaluate direction per paragraph from the first strong character, so Latin lines still flip RTL.

## Decision

- **Config only (A):** `FLOWKIRIM_FORCE_LTR` in `.env` / `config/flowkirim.php`.
- When enabled, apply a stronger LTR package:
  1. Prefix **every non-empty line** with LRM (`U+200E`)
  2. Wrap the whole body in LRE…PDF (`U+202A` … `U+202C`)
- Arabic glyphs still render correctly; bubble / Latin paragraphs stay left-aligned on Android, iOS, and Web.
- Application is idempotent (marks are stripped before re-applying).

## Behavior

| `FLOWKIRIM_FORCE_LTR` | Effect |
|----------------------|--------|
| `true` (default) | Per-line LRM + LRE/PDF wrap |
| `false` | No change (raw rendered template) |

Applied in `InvitationWhatsappService::renderMessage()` so `invitation_sends.message_body` matches what FlowKirim receives.

## Out of scope

- Per-send UI toggle
- Auto-detect Arabic only
- Changing the admin textarea preview direction (optional later)

## Success criteria

1. Template starting with Arabic + Indonesian body sends left-aligned on Android, iPhone, and WhatsApp Web when config is on.
2. Config can be disabled via `.env` without code change.
3. Unit tests cover LRE/PDF + per-line LRM when enabled, absence when disabled, and idempotent re-apply.
