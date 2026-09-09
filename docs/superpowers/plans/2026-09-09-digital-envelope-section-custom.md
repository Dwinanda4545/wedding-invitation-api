# Digital Envelope Mode 2 Implementation Plan

> **For agentic workers:** Implement task-by-task.

**Goal:** Enable Mode 2 custom code for Amplop Digital with payment bridge.

**Architecture:** Eligible key + seed + `createEnvelope` postMessage handled in `CustomSectionFrame`.

**Tech Stack:** React, Laravel sanitizer, PHPUnit/Vitest

## Global Constraints

- Keep Existing React form unchanged
- Payment API stays on host page (not raw iframe fetch)
- No commit unless asked

---

### Task 1: Eligible keys + payload
### Task 2: Seed + invitation.createEnvelope bridge
### Task 3: CustomSectionFrame handler + editor hint
### Task 4: Tests
