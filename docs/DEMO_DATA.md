# DEMO DATASET — fully fictional, for local testing only.

## Load
```
cd 'C:\Downloads\team mangment\apps\api'
npm run seed:demo
```
Idempotent — safe to re-run. Clears demo rows, recreates them. Does NOT touch the original `founder*@example.test` accounts.

## Accounts (all roles for permission testing)

| Email | Role | Permissions |
|---|---|---|
| amr@demo.test | FOUNDER_ADMIN | 55 (all, incl. finance.execute_payout) |
| laila@demo.test | FOUNDER_ADMIN | 55 |
| omar@demo.test | FOUNDER_ADMIN | 55 |
| nour@demo.test | FOUNDER_ADMIN | 55 |
| youssef@demo.test | FOUNDER_ADMIN | 55 |
| sara@demo.test | PROJECT_MANAGER | 20 |
| kareem@demo.test | EMPLOYEE | 7 |
| mona@demo.test | EMPLOYEE | 7 |
| hassan@demo.test | FINANCE_MANAGER | 22 |
| rana@demo.test | VIEWER | 5 |

**Password: `Demo1234!`** (all demo users) — fictional, dev-only. Override with `DEMO_PASSWORD` env.

## Data created
- **Projects:** `DEMO-PLATFORM` (active, 100k EGP), `DEMO-MOBILE` (planning), `DEMO-LEGACY` (closed)
- **Sections:** Backend / Frontend / AI / Testing (multi-member each)
- **Tasks:** 7 incl. DONE, IN_REVIEW, BLOCKED-overdue, 1 subtask, dependency chain, time entries (approved + pending)
- **Finance:** contract CTR-DEMO-001, INV-2026-001 (40k of 100k collected → PARTIAL), INV-2026-002 (overdue, uncollected), 3 expenses (APPROVED/SUBMITTED), 5 double-entry ledger txns (income, transfer-not-revenue, expense, allocation)
- **Allocation:** plan v1 SUPERSEDED + v2 APPROVED (HYBRID 50/30/20 on 50k basis) → 3 entitlements (PAID / APPROVED / ALLOCATED), 1 payout
- **Governance:** scopes (ORG + PROJECT_IDS), 4 notifications, 1 pending approval, 3 company settings

## Login test scripts
```
powershell -NoProfile -ExecutionPolicy Bypass -File 'C:\Downloads\team mangment\scripts\test-users.ps1'
powershell -NoProfile -ExecutionPolicy Bypass -File 'C:\Downloads\team mangment\scripts\test-allocation.ps1'
```

## Security guarantees verified by test-users.ps1
- self-escalation to FOUNDER_ADMIN → 403
- self-deactivation → 403
- employee granting roles → blocked
- employee creating users → 403
- permission override grant/revoke works dynamically
- last-admin guard enforced