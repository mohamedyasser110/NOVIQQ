# IMPLEMENTATION_PLAN — Company OS (SQLite-first, Postgres-ready)

**Source:** `docs/MASTER_SPEC.md` v1.0 (Oct 2026) + SQLITE amendment Part 1.2.
**Repo state at start:** empty of code (3 spec files only). Node v22.19.0, npm 10.9.3, no local Postgres → D-001 applies.
**Definition of runnable:** `apps/api` health 200 + `apps/web` renders + Prisma migrate + seed 5 fictional founders.

## Phase 0 — Assessment + Foundation + Shell [CURRENT]
- [x] Inspect repo/env (Node 22, no psql → SQLite local)
- [ ] Root workspaces + env example + docs (this file, DECISIONS, TESTING, DEPLOYMENT)
- [ ] `prisma/schema.prisma` — full domain (~35 models), SQLite provider, Postgres-compatible
- [ ] `packages/shared` — money (minor units), permissions registry, zod schemas
- [ ] `apps/api` — NestJS shell: AppModule + 19 feature stubs + Auth (JWT+bcrypt) + Health + Swagger
- [ ] `apps/web` — Next.js shell: nav (27 screens grouped), dashboard placeholders with real API health, loading/empty/error states
- [ ] Seed: 5 fictional founders + 1 sample project + sections + tasks + 1 invoice
- [ ] Checks: `tsc`, eslint, `vitest` (money/permissions), `prisma validate`
- **Done when:** both servers start, `/api/health` 200, web shows data from API, checks green.

## Phase 1 — Identity + Projects + Tasks (week 1-2)
Auth bootstrap, Users/Roles/Scopes, Projects CRUD+archive, Sections+members, Tasks/subtasks/multi-assignee/dependencies (cycle guard), comments, attachments metadata, filters/My Tasks/Kanban, calendar baseline, audit for project ops. **DoD:** 5 users collaborate; backend authz tests pass.

## Phase 2 — Clients + Project Finance (week 2-3)
Clients/contracts, invoices+lines, partial collections+receivables, expenses, allocation drafts+versioning, manual/contribution/hours/hybrid methods, contribution records, entitlement states, money-math tests. **DoD:** price→invoice→partial collect→allocate→reconcile with zero float error.

## Phase 3 — Treasury + Ledger + Approvals (week 3-4)
Accounts/categories, double-entry ledger service, budget rules, internal transfers, approval requests/decisions (two-person option), payout request→approve→mark-paid, reversals, idempotency. **DoD:** every movement traceable, duplicates rejected, balances reconcile.

## Phase 4 — Reports + Exports + Hardening (week 4-5)
12 reports + filters, CSV/XLSX (+PDF where easy) with scope enforcement, notifications center + reminders, workload view, audit search, responsive + a11y pass, `npm audit`, backup docs. **DoD:** reports equal ledger; exports scoped.

## Phase 5 — E2E + Production readiness (week 5-6)
Playwright flows A–G (§20.4), permission matrix, concurrency/idempotency tests, security review (IDOR/BOLA, rate-limit), bootstrap hardening, demo-data removal runbook, restore drill, deployment guide. **DoD:** all critical tests green, deploy+restore reproducible.

## File map (who changes what)
- `prisma/schema.prisma`, `prisma/seed.ts`, `prisma/migrations/*`
- `apps/api/src/{auth,users,projects,sections,tasks,clients,contracts,invoices,payments,expenses,allocations,treasury,payouts,approvals,reports,notifications,audit,settings,timeline}/`
- `apps/web/src/app/(dashboard|projects|tasks|finance|treasury|reports|settings)/`
- `packages/shared/src/{money.ts,permissions.ts,schemas.ts}`
