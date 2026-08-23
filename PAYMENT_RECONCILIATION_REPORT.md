# Payment reconciliation report

Status date: 2026-08-23

The application now exposes a read-only command:

    php artisan audit:payment-reconciliation

It checks every paid payment for:

- an immutable teacher snapshot;
- an immutable commission percentage;
- non-negative teacher and platform amounts;
- teacher earnings plus platform commission equal to the payment amount;
- an issued invoice.

## Local database result

The command completed successfully against the local SQLite dataset:

- Paid payments: 1,092
- Gross amount: 42,237,000
- Teacher earnings: 33,671,300
- Platform commission: 8,565,700
- Issues: 0

These figures are local evidence only. They are not a production reconciliation. Run the same command against a read-only production connection after backup approval and attach its output to the release record.
