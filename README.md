# Company Operating System — Private Internal Web App

> Modular system for a software company. Projects + Tasks + Finance + Treasury + Approvals + Audit in one coherent system. **Not a UI prototype.**

**Stack:** Next.js 14 + TypeScript (apps/web, static-export ready) · **PHP 8.1+ native REST, zero dependencies (apps/php-api)** · **MySQL (production) / SQLite (local dev)** · Tailwind CSS

**تشغيل على جهاز جديد؟** افتح [`SETUP.md`](SETUP.md) — من الصفر لحد تسجيل الدخول خطوة بخطوة.
**الرفع على استضافة؟** افتح [`docs/HOSTINGER_DEPLOY.md`](docs/HOSTINGER_DEPLOY.md).

## Quick start (Windows PowerShell)

```powershell
git clone https://github.com/mohamedyasser110/NOVIQQ.git
cd NOVIQQ
npm install
php apps/php-api/database/migrate.php --fresh
php apps/php-api/database/seed.php
.\RUN.BAT   # API http://localhost:4100/api/health · WEB http://localhost:3100
# login: founder1@example.test / ChangeMe123!
```

See `docs/` for spec, plans, testing and deployment notes.

## Layout

```
apps/php-api/    # PHP native API (20 modules, JWT, double-entry ledger, 99 smoke tests)
apps/web/        # Next.js App Router, Tailwind, API client (static export -> out/)
packages/shared/ # money utils (integer minor units), permission keys, zod schemas
docs/            # specs + plans + HOSTINGER_DEPLOY + TESTING
SETUP.md         # fresh-machine setup guide
```

## Rules that are never broken

- Money = integer minor units (qirsh/piasters). No `float`.
- Contract ≠ Invoiced ≠ Collected ≠ Allocated ≠ Approved ≠ Paid.
- Posted ledger entries are immutable → reversal/adjustment only.
- Server-side RBAC + scope on every route. Hiding buttons is not auth.
- Fictional seed only. No real names, passwords, or percentages in source.
- Never commit `.env`. Never `migrate reset` on user data without approval.
