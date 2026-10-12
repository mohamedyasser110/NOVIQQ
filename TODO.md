# TODO — Company OS (SQLite live, ports API=4100 WEB=3100)

## ✅ Done
- [x] Repo scaffold (monorepo + docs + env)
- [x] Prisma SQLite schema (38 tables) + migrate + seed (5 founders + DEMO-001)
- [x] **Demo dataset** (`npm run seed:demo`): 10 users across 5 roles, 3 projects, 7 tasks, 2 invoices, 5 ledger txns, allocation v1+v2, entitlements, payouts — see docs/DEMO_DATA.md
- [x] **UsersModule fixed + hardened**: real RBAC, effective permissions, role assign, overrides, last-admin guard, self-escalation block, invite permission gate
- [x] **API stable** on :4100 | **WEB stable** on :3100 — all 14 pages 200
- [x] **Phase 1 complete**: Projects + Sections + Tasks (CRUD, multi-assignee, deps, cycle guard, comments, time entries, RBAC)
- [x] **Phase 2 core complete**: Clients + Contracts + Invoices (partial collections, receivable, idempotency, reversal) + Allocations (draft→submit→approve→supersede, deterministic split, ALLOCATED entitlements)
- [x] Shared: money (5 tests) + permissions (3) + profit D-011 (3) = 11/11 green
- [x] API shell builds clean + web tsc clean
- [x] WORKFLOWS.md (9 steps) + DECISIONS D-001..D-011

## 🔄 In progress — Phase 1 vertical slice: Projects (real CRUD)
- [x] JWT guard (`jwt.guard.ts`) + Projects CRUD + archive + members + audit
- [x] Sections CRUD + members per project (`sections.service/controller/module`)
- [x] `nest build` clean with ProjectsModule + SectionsModule + TasksModule wired
- [x] Verify live: `/api/health` 200 + login (seed founder) + `GET /projects` with token
- [x] E2E slice live: project→section→2 tasks→dependency→cycle 400→status→comment
- [x] Guards live: 401 no-auth, 400 due-date-without-reason, cycle BadRequest
- [x] `TasksModule` real: CRUD + subtasks + multi-assignee + dependency cycle guard
- [x] Web live pages: `/login` + `/projects` (create) + `/tasks` (filter + advance) — tsc clean
- [x] Start web dev :3100 + all 14 nav pages return 200 (no 404s)
- [x] `InvoicesModule` real: create + partial collections + receivable + reversal + idempotency
- [x] Live invoice proof: billed 100,000 / collected 40,000 / receivable 60,000 [PARTIAL]
- [x] Guards live: duplicate key → 409, overpayment → 400

- [x] `ClientsModule` real: clients + contracts CRUD (contract syncs project.contractValue)
- [x] `AllocationsModule` real: draft → submit → approve → supersede, deterministic split, ALLOCATED entitlements
- [x] Live proof: HYBRID 60/30/10 on 100,000 EGP → 60,000/30,000/10,000 → 3 entitlements
- [x] Guards live: approve before submit → 400, approve without member lines → 400, supersede requires reason
- [x] Supersede keeps approved snapshot: v1 SUPERSEDED + v2 DRAFT, history intact

## ⏳ Next — Phase 2 remainder
- [ ] Expenses + approval states (SUBMITTED → APPROVED → PAID)
- [ ] Contribution records CRUD (weights, notes)
- [ ] Entitlement state transitions: ALLOCATED → EARNED → APPROVED → PAID (policy-gated)
- [ ] API authz tests (employee blocked from foreign project)
- [ ] Web: `/projects`, `/tasks` pages bound to live API + login page
- [ ] Re-run: build + vitest + seed + health green

## ⏳ Next — Phase 2 (finance)
- [ ] Invoices + partial collections + receivables math
- [ ] Allocation drafts + versioning + hybrid methods
- [ ] Contribution records + entitlement states

## ⏳ Later — Phase 3/4/5
- [ ] Treasury + double-entry ledger + approvals + payouts + idempotency
- [ ] Reports + exports (scoped) + notifications + audit search
- [ ] E2E A–G + security review + deploy runbook
