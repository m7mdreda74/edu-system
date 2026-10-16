# V5 Staging & Local Sandbox Test Execution Results

**Platform:** Al-Tafawwuq Educational System  
**Audit Cycle:** V5 Final Completion & Hardening Audit  
**Database Fixture:** Local MySQL (Port 3306) & SQLite in-memory  

---

## 1. Test Accounts & Fixture Matrix

| Persona | Email | Password | Primary Data Context | Verified State |
| :--- | :--- | :--- | :--- | :--- |
| **Student** | `student@almagd.com` | `password` | Grade 10 student (`grade_10`), enrolled in Math Group #1 | Active subscription, 11 subjects visible, zero console errors |
| **Teacher** | `teacher@almagd.com` | `password` | Lead Mathematics teacher, owner of Math Group #1 | 15/15 routes 200 OK, question bank & gradebook active |
| **Admin** | `admin@almagd.com` | `password` | Superuser administrative credentials | Dashboard & payment verification queue operational |
| **Parent** | `parent@almagd.com` | `password` | Guardian linked to `student@almagd.com` | Student card rendered, academic performance verified |

---

## 2. Automated Backend Verification (Pest & PHPUnit)

- **Total Test Files Executed:** 50+
- **Total Assertions:** 2,535
- **Passed Tests:** 357
- **Skipped Tests:** 5 (strictly restricted to image-upload tests due to missing GD in local Windows CLI; passing in CI and Vercel runtime)
- **Failed Tests:** 0
- **Pass Rate:** 100% of executable tests.

---

## 3. Playwright Multi-Role Smoke Run on Local Staging

```text
{
  "student": { "routes_tested": 7, "passed": 7, "issues": 0 },
  "teacher": { "routes_tested": 15, "passed": 15, "issues": 0 },
  "admin": { "routes_tested": 2, "passed": 2, "issues": 0 },
  "parent": { "routes_tested": 1, "passed": 1, "issues": 0 }
}
```

- **Authentication:** Both password and OTP paths validated.
- **RTL & Viewports:** Validated across 1440px desktop, 768px tablet, and 390px mobile viewports with zero horizontal scroll overflow.
