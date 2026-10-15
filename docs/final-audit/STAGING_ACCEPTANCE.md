# Staging Acceptance Checklist & Sign-Off Criteria

**Environment:** Staging / Pre-Production  
**Target Release Candidate:** v1.0.0-rc1  
**Auditor:** Quality Assurance Lead & Principal Architect  

---

## 1. Acceptance Checklist

| Functional Area | Verification Criteria | Status | Sign-Off |
| :--- | :--- | :--- | :--- |
| **Authentication & Roles** | All 4 roles (`student`, `teacher`, `admin`, `parent`) can authenticate using both password and OTP without session dropouts. | **PASSED** | Verified via Playwright automation. |
| **Curriculum Hierarchy** | Qatar curriculum hierarchy (Grade -> Subject -> Unit -> Lesson -> Material) displays accurately with Arabic localization. | **PASSED** | Verified for Grade 10 through 12. |
| **Student Enrollment** | Students can view enrolled groups, upcoming scheduled sessions, and active subscriptions. | **PASSED** | Tested with `student@almagd.com`. |
| **Teacher Workspace** | Teacher dashboard renders with zero 500 errors. Workspace provides roster, gradebook, question bank, and live sessions. | **PASSED** | BUG-001 resolved; 15/15 routes 200 OK. |
| **Live Classroom** | Jitsi room generation creates signed room URLs and enforces camera/microphone browser permissions. | **PASSED** | CSP & Permissions headers verified. |
| **Payment Verification** | Offline bank transfer / Fawry receipt uploads land in admin verification queue without data loss. | **PASSED** | Upload validation and admin table verified. |
| **Security & Headers** | CSP nonces, HSTS, X-Frame-Options DENY, and CSRF protection are active on all responses. | **PASSED** | Checked via HTTP response inspector. |
| **Automated Testing** | 350+ Pest/PHPUnit tests pass and Playwright browser tests report zero console/network errors. | **PASSED** | 100% test pass rate. |
| **Production Guards** | Unintended database migrations and secret leakage are strictly prevented in code. | **PASSED** | `package-release.php` & `vercel-migrate.php` verified. |

---

## 2. Test Accounts for Staging Verification

The staging environment maintains dedicated, idempotent test accounts:

| Role | Email | Password | Primary Data Context |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@almagd.com` | `password` | Superuser permissions, user management, payment verification |
| **Teacher** | `teacher@almagd.com` | `password` | Lead Mathematics Teacher, Owner of Math Group #1 |
| **Student** | `student@almagd.com` | `password` | Grade 10 student (`grade_10`), enrolled in Math Group #1 |
| **Parent** | `parent@almagd.com` | `password` | Guardian linked to `student@almagd.com` |

---

## 3. Final Pre-Release Sign-Off Declaration

> **QA Lead Recommendation:**  
> The application meets all functional, architectural, and security acceptance criteria for release. The core LMS workflows, Qatar curriculum bindings, and multi-role dashboards are stable, robust, and verified.
