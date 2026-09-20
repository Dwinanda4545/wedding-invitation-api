# WhatsApp Force LTR (Arabic Mixed Messages)

**Date:** 2026-09-20  
**Status:** Approved (option A)  
**Repos:** `wedding-invitation-api` (`config/flowkirim.php`, `InvitationWhatsappService`)

## Problem

WhatsApp uses the Unicode Bidirectional Algorithm. When an invitation template starts with Arabic script (e.g. بسم الله…), the message bubble becomes RTL, so Indonesian lines appear right-aligned.

## Decision

- **Config only (A):** `FLOWKIRIM_FORCE_LTR` in `.env` / `config/flowkirim.php`.
- No admin UI toggle.
- When enabled, prepend Unicode LRM (`U+200E`) to the rendered message body before save/send so WhatsApp treats the bubble as LTR. Arabic glyphs still render correctly; Latin paragraphs stay left-aligned.

## Behavior

| `FLOWKIRIM_FORCE_LTR` | Effect |
|----------------------|--------|
| `true` (default) | Prepend LRM once if not already present |
| `false` | No change (raw rendered template) |

Applied in `InvitationWhatsappService::renderMessage()` so `invitation_sends.message_body` matches what FlowKirim receives.

## Out of scope

- Per-send UI toggle
- Auto-detect Arabic only
- Changing the admin textarea preview direction (optional later)

## Success criteria

1. Template starting with Arabic + Indonesian body sends as left-aligned bubble when config is on.
2. Config can be disabled via `.env` without code change.
3. Unit test covers LRM prefix when enabled / absent when disabled.
