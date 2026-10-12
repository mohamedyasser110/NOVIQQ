# Company Operating System — Private Internal Web App

> Modular monolith for a 5-founder software company. Projects + Tasks + Finance + Treasury + Approvals + Audit in one coherent system. **Not a UI prototype.**

**Stack (Phase 0):** Next.js 14 + TypeScript (apps/web) · NestJS 10 + TypeScript REST (apps/api) · Prisma ORM + **SQLite (local/dev)** → PostgreSQL (production switch) · Tailwind CSS

**Repo root:** `C:\Downloads\team mangment` (canonical spec: `MASTER_SPEC.md` + `docs/MASTER_SPEC.md`)

## Quick start (Windows PowerShell)

```powershell
# 1. API
cd 'C:\Downloads\team mangment\apps\api'
npm install
npx prisma generate
npx prisma migrate dev --name init
npm run seed
npm run start:dev   # http://localhost:4100/api/health

# 2. Web (new terminal)
cd 'C:\Downloads\team mangment\apps\web'
npm install
npm run dev         # http://localhost:3100
```

See `docs/IMPLEMENTATION_PLAN.md` for phases and `docs/DECISIONS.md` for every reversible default.

## Layout

```
apps/api/        # NestJS modular monolith (19 modules, Prisma, Swagger)
apps/web/        # Next.js App Router, Tailwind, API client
packages/shared/ # money utils (integer minor units), permission keys, zod schemas
prisma/          # canonical schema (SQLite dev, Postgres prod)
docs/            # MASTER_SPEC + IMPLEMENTATION_PLAN + DECISIONS + TESTING + DEPLOYMENT
```

## Rules that are never broken

- Money = integer minor units (qirsh/piasters). No `float`.
- Contract ≠ Invoiced ≠ Collected ≠ Allocated ≠ Approved ≠ Paid.
- Posted ledger entries are immutable → reversal/adjustment only.
- Server-side RBAC + scope on every route. Hiding buttons is not auth.
- Fictional seed only. No real names, passwords, or percentages in source.
- Never commit `.env`. Never `migrate reset` on user data without approval.
