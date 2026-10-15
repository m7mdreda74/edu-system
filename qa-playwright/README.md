# Playwright QA harness

These scripts require an explicitly supplied local or approved staging target. They do not contain accounts or default to the live deployment.

From this directory, install dependencies and provide test-only credentials through environment variables:

~~~powershell
$env:QA_BASE_URL = 'http://127.0.0.1:8000'
$env:QA_STUDENT_EMAIL = 'student@example.test'
$env:QA_STUDENT_PASSWORD = 'test-password'
npm run audit
~~~

The audit is read-only by default. Upload coverage additionally requires QA_ALLOW_MUTATIONS=true, QA_CHECKOUT_ID, and QA_TRANSFER_PHONE. A known production host also requires QA_ALLOW_PRODUCTION=true; use both flags only with explicit approval and an isolated test record.

The admin payment check uses QA_ADMIN_EMAIL and QA_ADMIN_PASSWORD and is read-only. It also requires QA_BASE_URL and the production approval guard for the known live host.

For reusable role coverage, provide QA_ROLE, QA_LOGIN_EMAIL, and QA_LOGIN_PASSWORD, then run npm run role-smoke. Supported roles are admin, teacher, student, and parent; QA_ROUTES can override the default route list with a comma-separated set.

For read-only deployment parity, provide QA_BASE_URL and run npm run deployment-smoke. It checks the public pages, health dependencies, and the public autocomplete JSON contract. The known production host requires QA_ALLOW_PRODUCTION=true and the command never submits forms or changes data.

No browser result is considered a release pass until it is run against an approved local or staging environment and its screenshots, console, and network output are reviewed.
