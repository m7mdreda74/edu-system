# Deployment Checklist

Status: **NOT READY — BLOCKERS REMAIN**

## Before staging

- Review the feature branch diff and all four additive migrations: parent-link lifecycle, group announcements, Question Bank, and Resource Library tables.
- Confirm backup/restore availability for `parent_student_links`.
- Confirm backup/restore availability for `learning_resources` and `learning_resource_assignments`.
- Apply all four migrations only through the approved staging/deployment process; do not run production migrations from this workspace.
- Clear/cache Laravel configuration and routes according to the host release process.
- Build and publish the matching Vite manifest/assets.
- Publish the updated `vercel.json` cron schedule and configure `CRON_SECRET` plus the documented `ACADEMIC_ALERT_*` thresholds; do not expose the secret to browser code.
- Confirm Vercel Blob private-store/OIDC settings for existing curriculum uploads; verify private upload and retrieval after redeploy.

## Staging smoke journeys

- Parent requests a link with a valid and an unknown phone; confirm generic response and throttling.
- Student approves, rejects, and allows a request to expire; confirm parent data visibility changes only after approval.
- Student opens Continue Learning and Assignment Center with an active subscription.
- Student opens the Assessment Center and sees only published quizzes from active subscriptions; verify upcoming/closed/in-progress/completed states, remaining attempts, and no question or answer-key data in the page payload.
- Student submits through the existing Learn flow; teacher grades through existing Worksheets; student and verified parent see released feedback.
- Teacher sees pending grading count scoped to owned assignments.
- Teacher opens the Gradebook, filters only their own assignment, sees submitted homework and latest quiz attempts, and does not see missing submissions as zeroes.
- Teacher moves between Gradebook pages and confirms ordering, summary totals, ownership scope, and no duplicate latest quiz attempts.
- Teacher downloads the Gradebook CSV for all records and for one owned assignment; confirm the export contains only scoped rows and spreadsheet-safe values.
- Teacher opens Performance Analytics, filters only their own assignment/group/student, sees real attendance/progress/homework/quiz trends, and receives only explainable follow-up signals; a foreign assignment returns 404.
- Teacher opens Group Announcements, can select only an owned active group, publishes a real message, and sees it in the history.
- An active subscribed student receives the announcement in the dashboard and notification bell; an expired/non-subscribed student does not.
- Academic Alerts send upcoming/missing homework, upcoming-class, recent-absence, declining-assessment, and subscription-expiration notices only to eligible students; verified linked parents receive the parent copy, unverified/revoked links do not.
- Re-running `/api/cron/academic-alerts` or `academic-alerts:send` within the configured cooldown does not create duplicate recipient notifications, and the existing live-session reminder does not duplicate an upcoming-class alert.
- A verified linked parent receives the relevant notification; an unverified or revoked link does not.
- Teacher uploads a private PDF/image/document resource, searches it by title/category/tag, attaches it to an owned assignment, opens it through the protected route, and confirms an unrelated student cannot download it.
- Teacher requests sharing, confirms another teacher cannot reuse it before approval, then confirms an admin approval exposes it for explicit attachment without exposing the stored local path or Blob URL.
- In the direct-upload environment, verify `BLOB_STORE_ID` plus `VERCEL_OIDC_TOKEN` or the approved Blob token configuration, upload a private resource through `/api/blob-upload`, complete it, and retrieve it through the protected application route after redeploy.
- Teacher creates a private Question Bank item, confirms another teacher cannot edit/delete/copy it, then marks a question shared and confirms the other teacher can copy it but still cannot edit/delete it.
- Teacher adds a bank question to a quiz, edits/deletes the bank item, and confirms the quiz keeps its copied text, options, points, and answer key; student start responses still omit `is_correct`.
- A verified linked parent switches between eligible children and sees the selected child's read-only rolling seven-day report from real records, including daily/subject/recent activity; an unverified or unrelated child cannot be selected and no parent submission control is present.
- Exercise existing login, booking, subscription, payment, live room, recorded lesson, protected file, and renewal flows.

## Release gates

- Full test suite reviewed: **332 passed, 0 failed, 2,384 assertions** locally.
- PHP GD/test environment parity addressed for image tests.
- Isolated MySQL migration and concurrency checks pass.
- Browser desktop/mobile smoke test passes with no console or failed-network errors.
- Secrets and environment configuration reviewed; no production credential is used locally.
- Explicit production approval obtained. No production deployment is authorized by this branch task.
