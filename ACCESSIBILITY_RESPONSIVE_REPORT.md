# Accessibility and responsive report

Status date: 2026-08-23

## Code-level coverage

- Arabic RTL layout and shared app shell are used across the public Inertia pages.
- Modal focus, Escape, Tab, and body-scroll behavior have shared coverage where applicable.
- Form validation errors are rendered in Arabic and remain available to screen-reader labels.
- Sensitive tables keep actions visible at desktop widths and remain scrollable at narrow widths.

## Local browser smoke evidence

Playwright checked home, login, and registration at 320, 375, 414, 768, 1024, and 1440 pixels. All 18 combinations returned HTTP 200, had no horizontal overflow, and produced no CSP or application console errors.

## Manual Chromium matrix still required

Check home, login, registration, checkout, student dashboard, admin payments, settings, and live room at:

    320, 375, 414, 768, 1024, 1440

For each page record:

- horizontal overflow and clipped controls;
- keyboard-only tab order and visible focus;
- modal focus trap and Escape behavior;
- error message association and contrast;
- touch target size and mobile file-upload usability;
- CSP console errors and missing assets.

This report is a checklist, not a claim that the real production domain has already passed manual browser verification.
