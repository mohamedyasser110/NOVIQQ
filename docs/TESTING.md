# TESTING — Company OS

## Unit (vitest, no DB)
```
npm run test -w packages/shared   # money splits, rounding, permission evaluator
npm run test -w apps/api          # ledger balance, allocation validation, state transitions (Phase 2+)
```

## Integration (SQLite, Phase 1+)
- Project/section/task CRUD with authz (employee blocked from foreign project).
- Invoice → partial collections → receivable math.
- Allocation draft → submit → approve → version snapshot immutable.
- Payout approve → mark-paid; duplicate `Idempotency-Key` rejected.
- Ledger txn atomicity (multi-entry rolls back on imbalance).

## Security (per phase)
Employee cannot read finance without grant · cannot self-grant · cannot approve outside scope · direct API (curl) cannot bypass UI · IDOR/BOLA on `:id` routes · auth rate-limit 10/min · exports scoped.

## E2E flows A–G (§20.4, Playwright, Phase 5)
A project execution · B revenue · C compensation · D expenses · E treasury (transfer ≠ revenue) · F allocation change preserves history · G permission matrix as employee/viewer.

Run: `npx prisma validate --schema prisma/schema.prisma` before every migration.
