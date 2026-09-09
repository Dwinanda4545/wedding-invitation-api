# Digital Envelope Mode 2 Custom Design

**Date:** 2026-09-09  
**Status:** Approved  

## Goal

Make Amplop Digital support Mode Existing + Mode Custom (HTML/CSS/JS) like other sections, with a host payment bridge.

## Changes

1. Add `digital_envelope` to `SECTION_CUSTOM_ELIGIBLE_KEYS` and `SectionCustomSanitizer::ELIGIBLE_KEYS`.
2. Seed HTML/CSS/JS mirroring the React envelope form.
3. `invitation.createEnvelope(payload)` in iframe → postMessage → parent calls API → returns `payment_url` → iframe/parent redirect.
4. Payload includes `envelope: { presets, min_amount, max_amount, payment_result }`.

## Non-goals

- Admin UI for presets/min/max
- Changing DOKU integration
