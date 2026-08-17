# Per-section custom code Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let each invitation section (except QR) switch between the existing React component and isolated iframe HTML/CSS/JS with HTTPS libraries.

**Architecture:** Store `section_custom` in `invitation_settings` JSON. Pure helpers in `sectionCustom.ts` build placeholders, payload, and `srcdoc`. `CustomSectionFrame` hosts a sandboxed iframe. Admin edits via CodeMirror tabs. API sanitizes `section_custom` on save.

**Tech Stack:** React 19, TypeScript, CodeMirror 6 (`@uiw/react-codemirror`), Vitest, Laravel FormRequest + PHPUnit.

## Global Constraints

- QR never has Mode 2; `invitation_mode: html` ignores `section_custom`.
- Iframe sandbox: `allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox` — never `allow-same-origin`.
- Library URLs HTTPS only; html/css/js max 200_000 chars; max 20 libraries.
- Mode switch must not wipe the other mode’s data.
- Cover `open-cover` postMessage honored only when `sectionKey === 'cover'`.
- `invitation.data` omits `secret_token`.
- No database migration.

---

### Task 1: Types + mergeSettings

**Files:**
- Modify: `wedding-invitation-web/src/lib/invitationTypes.ts`

**Produces:** `SectionCustomLibrary`, `SectionCustomCode`, `section_custom` on `InvitationSettings`, `EMPTY_SECTION_CUSTOM`, `getSectionCustom()`, `isSectionCustomMode()`.

- [x] Add types and merge passthrough (implemented in this session)

### Task 2: Pure helpers + Vitest

**Files:**
- Create: `wedding-invitation-web/src/lib/sectionCustom.ts`
- Create: `wedding-invitation-web/src/lib/sectionCustom.test.ts`
- Modify: `wedding-invitation-web/package.json`, `vitest.config.ts`

**Produces:** `isHttpsLibraryUrl`, `applySectionCustomPlaceholders`, `buildSectionCustomPayload`, `buildSectionCustomSrcdoc`, `SECTION_CUSTOM_SANDBOX`, presets, starter HTML.

- [x] Unit tests then implementation

### Task 3: CustomSectionFrame + SectionInvitation

**Files:**
- Create: `wedding-invitation-web/src/components/invitation/CustomSectionFrame.tsx`
- Modify: `wedding-invitation-web/src/components/invitation/SectionInvitation.tsx`
- Modify: `wedding-invitation-web/src/components/invitation/invitation.css` (iframe fill for cover wrapper)

- [x] Render iframe; branch cover/hero/builtins/custom; QR unchanged

### Task 4: Admin editor

**Files:**
- Create: `wedding-invitation-web/src/pages/admin/SectionCustomCodeEditor.tsx`
- Modify: `wedding-invitation-web/src/pages/admin/InvitationContentPage.tsx`

- [x] Mode switch + CodeMirror tabs + live preview; wire cover/hero/sections/content tabs

### Task 5: API sanitizer

**Files:**
- Create: `wedding-invitation-api/app/Support/SectionCustomSanitizer.php`
- Create: `wedding-invitation-api/tests/Unit/SectionCustomSanitizerTest.php`
- Modify: `wedding-invitation-api/app/Http/Requests/EventInvitationUpdateRequest.php`

- [x] Drop `qr`, invalid URLs, oversize fields; merge sanitized settings
