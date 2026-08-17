# Per-section custom code (Mode 1 / Mode 2)

Date: 2026-08-17  
Repos: `wedding-invitation-api` (settings validation) and `wedding-invitation-web` (editor + runtime)

## Goal

Every invitation **section** (except QR) can run in one of two modes:

- **Mode 1 — Existing:** current React components and form editors.
- **Mode 2 — Custom code:** author HTML, CSS, native JS, and HTTPS libraries/plugins. That code **replaces the entire section box** (title, background, padding included).

The public invitation stays a React page. Mode 2 sections render in **isolated iframes**. QR, music, cover-open, and check-in stay owned by the host page.

## Non-goals (v1)

- Whole-page HTML mode (`invitation_mode: html`) stays as-is and is separate. When that mode is on, `section_custom` is ignored.
- QR has no Mode 2 switch.
- Mode 2 Wishes is display-only: `{{wishes_json}}` / `invitation.data.wishes` are available; there is **no** `invitation.submitWish()` in v1. The RSVP form remains Mode 1 only.
- No visual (WYSIWYG) builder for Mode 2.
- No `allow-same-origin` on iframes. Mode 2 cannot read cookies, Sanctum session, or call the parent DOM.

## Eligible sections

| Key | Mode 2? | Notes |
|-----|---------|--------|
| `cover` | Yes | Full-viewport overlay until `invitation.open()`. Respects `cover_enabled`. |
| `hero` | Yes | Always after cover when content is visible; not in `section_order`. |
| `couple` | Yes | |
| `schedule` | Yes | |
| `love_story` | Yes | |
| `gallery` | Yes | |
| `wishes` | Yes | Display-only if custom (see non-goals). |
| `hosts` | Yes | |
| `custom:<id>` | Yes | Mode 1 = current sanitized CKEditor HTML. Mode 2 = iframe code. |
| `qr` | **No** | Always `QrSection`. |

Hidden sections stay hidden: `sections.<key> === false`, `custom.enabled === false`, or `cover_enabled === false`.

## Data model

Stored in existing JSON `events.invitation_settings.section_custom`. **No migration.**

```ts
type SectionCustomLibrary = {
  id: string
  name: string
  src: string          // must be https://
  kind: 'js' | 'css'
}

type SectionCustomCode = {
  mode: 'existing' | 'custom'
  html: string
  css: string
  js: string
  libraries: SectionCustomLibrary[]
}

type InvitationSettings = {
  // ...existing fields...
  section_custom?: Record<string, SectionCustomCode>
}
```

Rules:

- Keys are `cover` | `hero` | builtin section keys except `qr` | `custom:<uuid>`.
- Missing key or `mode: 'existing'` → React path.
- Switching mode **must not** wipe the other mode’s data (form fields stay; html/css/js/libraries stay).
- `mergeSettings()` copies `section_custom` through (default `{}`).
- Max length per `html` / `css` / `js` field: **200_000 characters**. Reject on save if exceeded.
- Max **20** libraries per section.
- `src` must match `https://` and must not contain whitespace, `javascript:`, or `data:`.

### API

`EventInvitationUpdateRequest` already accepts `invitation_settings` as an array. Add nested validation (or a sanitizer in the controller) for `invitation_settings.section_custom`:

- `mode` in `existing,custom`
- `html`, `css`, `js` strings with max length
- `libraries.*.src` HTTPS
- `libraries.*.kind` in `js,css`
- Drop unknown keys and any `qr` entry

No new endpoints.

## Runtime (guest + admin preview)

New renderer: `CustomSectionFrame` (web). Used by `SectionInvitation` and the admin preview.

### When to use it

In `SectionInvitation`:

- Cover: if `section_custom.cover.mode === 'custom'` and `cover_enabled`, render `CustomSectionFrame` instead of `CoverSection`. Parent still owns open/closed: closed → frame visible (fullscreen); open → frame hidden. Music still starts on `invitation.open()`.
- Hero: if custom, replace `HeroSection` + `SectionBackgroundShell`.
- Built-ins in `renderBuiltin`: if that key is custom, skip `SectionBackgroundShell` and the React section; render only `CustomSectionFrame`.
- Custom HTML sections: if `section_custom['custom:'+id].mode === 'custom'`, skip `CustomSection` (CKEditor HTML) and render the iframe.
- QR: unchanged.

Page-level chrome always stays: `DecorLayers`, sakura, `MusicPlayer`, footer, viewport frame.

### Iframe document (`srcdoc`)

The host builds a full HTML document (not user-authored `<html>` wrapping — user HTML is the **body inner HTML**):

1. `<base target="_blank">`
2. Minimal CSS reset (`html,body { margin:0; }`)
3. User CSS in `<style>`
4. CSS libraries as `<link rel="stylesheet" href="...">` in listed order
5. Body: user HTML after placeholder substitution
6. Bootstrap script that defines `window.invitation` (see below)
7. JS libraries as `<script src>` in listed order
8. User JS in `<script>` (wrapped in try/catch; errors postMessage to parent)
9. ResizeObserver on `document.documentElement` posting height

`sandbox` attribute: `allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox`  
**Do not** set `allow-same-origin`.

Height: parent listens for `postMessage` and sets iframe `height`. Minimum height 1px; cover iframe uses `100%` of overlay instead of content height.

### `postMessage` protocol

All messages from iframe:

```ts
{
  source: 'inv-section'
  sectionKey: string
  type: 'resize' | 'open-cover' | 'error'
  height?: number
  message?: string
}
```

Parent:

- Ignore messages without `source === 'inv-section'`.
- `open-cover` is honored **only** if `sectionKey === 'cover'` (then `handleOpenCover()`).
- `error` is shown under the admin preview; on the guest page it is `console.warn` only.
- `resize` updates that iframe’s height.

### `window.invitation`

```ts
invitation = {
  sectionKey: string
  open(): void  // posts open-cover; parent ignores unless cover
  data: InvitationPayload
}
```

`InvitationPayload` is a JSON-serializable snapshot:

- `guest`: `{ name, guest_type }` (no `secret_token`)
- `event`: `{ name, event_date, location }`
- `couple`: merged couple_info
- `hosts`, `schedules`, `love_stories`, `gallery`, `wishes`
- `theme`: `{ tagColor, pageTextColor, fontFamily }` (so custom CSS can match the template if desired)

Do **not** put `secret_token`, admin URLs, or check-in endpoints on this object.

## Placeholders

Applied to **user HTML only** (not to CSS/JS). JS should use `invitation.data`.

Text placeholders are HTML-escaped. `*_json` placeholders are inserted as raw JSON (no HTML wrap, no color span). This differs from whole-page HTML mode, which wraps values in a colored `<span>` — Mode 2 must not do that, or JSON and layout break.

| Placeholder | Value |
|-------------|--------|
| `{{guest_name}}` / `{{nama_tamu}}` | Guest name |
| `{{guest_type}}` / `{{tipe_tamu}}` | `Tamu VIP` or `Tamu` |
| `{{event_name}}` / `{{nama_acara}}` | Event name |
| `{{event_date}}` / `{{tanggal}}` | Localized id-ID datetime, or `—` |
| `{{event_location}}` / `{{lokasi}}` | Location or `—` |
| `{{groom_name}}` | Groom full name |
| `{{groom_nickname}}` | Groom nickname |
| `{{bride_name}}` | Bride full name |
| `{{bride_nickname}}` | Bride nickname |
| `{{couple_initial}}` | Couple initial |
| `{{cover_title}}` | Cover title setting |
| `{{cover_subtitle}}` | Cover subtitle setting |
| `{{couple_json}}` | JSON couple object |
| `{{gallery_json}}` | JSON gallery array (`id`, `caption`, `image_url`) |
| `{{schedules_json}}` | JSON schedules |
| `{{love_stories_json}}` | JSON love stories |
| `{{hosts_json}}` | JSON hosts |
| `{{wishes_json}}` | JSON wishes (public fields only) |

Unknown `{{tokens}}` are left unchanged.

## Admin editor

On `InvitationContentPage`, each eligible section panel gets a Mode switch (Existing / Custom code).

- **Existing:** current UI unchanged.
- **Custom code:** hide that section’s Mode 1 form; show `SectionCustomCodeEditor`:
  - Tabs: HTML, CSS, JS, Libraries
  - Libraries: checkboxes for presets + add HTTPS URL (kind js/css, display name)
  - Live preview: same `CustomSectionFrame` as guest, using current form state (unsaved OK)
  - Error line under preview for iframe `error` messages
  - Cover preview documents `invitation.open()` in the starter comment

**First time** a section is switched to custom and `html` is empty, seed a starter template (HTML comment listing placeholders +, for cover, `invitation.open()` example). Never overwrite non-empty html/css/js.

QR panel: no switch.

Save: existing “Simpan pengaturan” includes `section_custom` inside `invitation_settings`.

### Library presets (v1)

| Name | kind | src |
|------|------|-----|
| GSAP 3 | js | `https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js` |
| AOS | css | `https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css` |
| AOS | js | `https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js` |
| Splide | css | `https://cdn.jsdelivr.net/npm/@splidejs/splide@4/dist/css/splide.min.css` |
| Splide | js | `https://cdn.jsdelivr.net/npm/@splidejs/splide@4/dist/js/splide.min.js` |
| jQuery 3 | js | `https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js` |
| Animate.css | css | `https://cdn.jsdelivr.net/npm/animate.css@4/animate.min.css` |

Custom URLs: HTTPS only, same validation as API.

Editor implementation: CodeMirror 6 (`@codemirror/lang-html`, `css`, `javascript`) in a dedicated component so `InvitationContentPage.tsx` does not grow further. Fallback to `<textarea>` is not the v1 target.

## Security

- Isolation = iframe sandbox without `allow-same-origin`, not DOMPurify (Mode 2 is intentionally executable).
- Mode 1 custom sections and CKEditor quotes stay sanitized as today.
- Parent only accepts `open-cover` from `sectionKey === 'cover'`.
- `invitation.data` omits secrets.
- Library URLs HTTPS-only on client and server.
- Iframe links open in a new tab.

This is **trusted-admin** code on a guest-facing page. Only authenticated event admins can save it. That is accepted.

## Files (expected)

Web:

- `src/lib/invitationTypes.ts` — types + merge
- `src/lib/sectionCustom.ts` — placeholders, srcdoc builder, URL validation, presets, starter templates
- `src/components/invitation/CustomSectionFrame.tsx` — iframe + resize + messages
- `src/components/invitation/SectionInvitation.tsx` — branch existing vs custom
- `src/pages/admin/SectionCustomCodeEditor.tsx` — switch + tabs + preview
- `src/pages/admin/InvitationContentPage.tsx` — wire editor per section

API:

- `app/Http/Requests/EventInvitationUpdateRequest.php` — nested rules / sanitize `section_custom`

## Testing

Manual (required):

1. All sections `existing` → Cover, gallery, QR, music, line spacing unchanged.
2. Toggle a section to custom and back → Mode 1 fields and Mode 2 code both persist after save/reload.
3. Cover custom: `invitation.open()` opens content and starts music; the same call from a couple iframe is ignored.
4. Placeholders: `{{guest_name}}` and `{{nama_tamu}}` show the guest; `{{gallery_json}}` parses with `JSON.parse` in user JS.
5. Enable Splide preset in a custom gallery section; carousel runs inside the iframe.
6. Reject `http://` library URL in the editor (and API if posted directly).
7. Mixed order: cover custom, couple existing, custom-section custom, QR existing — order and QR check-in still work.
8. Admin preview iframe matches the guest invitation for that section.
9. `invitation_mode: html` still uses the old whole-page HTML path.

Automated (if straightforward): unit tests for placeholder substitution, HTTPS URL validation, and srcdoc not including `allow-same-origin`.

## Deploy

- API: pull PHP request changes only (no migration).
- Web: `npm run build` with production `VITE_API_BASE_URL`, upload `dist/`.
- No PHP upload-limit change for this feature.
