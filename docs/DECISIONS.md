# DECISIONS — Architectural Decision Records (append-only)

## D-001 — SQLite local, PostgreSQL production (2026-10-09)
- **Context:** MASTER_SPEC (root file) says PostgreSQL; amended Part 1.2 pasted 2026-10-09 says "Use SQLITE"; this Windows machine has Node 22 + npm 10 but **no `psql`/Postgres server**.
- **Decision:** Prisma `provider = "sqlite"` for local/dev (`DATABASE_URL=file:./dev.db`). Schema written Postgres-compatible (no SQLite-only hacks) so production switches by changing provider + `DATABASE_URL` to `postgresql://…`.
- **Consequences:** No local Postgres install needed; `NUMERIC` → `Int` minor units (qirsh) works on both. Revisit before production: run `prisma migrate` against staging Postgres.
- **Reversible:** Yes.

## D-002 — Monorepo layout (2026-10-09)
- **Decision:** `apps/web` (Next.js) + `apps/api` (NestJS) + `packages/shared` + `prisma/` canonical schema + `docs/`. npm workspaces at root.
- **Why:** Matches spec §16.3, keeps module boundaries clear, single `git` history.

## D-003 — Auth strategy (2026-10-09)
- **Decision:** JWT access (15 min, Bearer) + rotating refresh (7 d, httpOnly Secure cookie) + bcrypt (cost 12). First-run `POST /auth/bootstrap` seeds 5 fictional founders only when `BOOTSTRAP_ENABLED=true` and users table empty. Rate-limit auth 10/min/IP. MFA deferred → documented production blocker, no fake MFA.
- **Why:** Stateless API, web+mobile friendly, no custom crypto.

## D-004 — Money (2026-10-09)
- **Decision:** All amounts `Int` minor units + `currency` text on every financial row. Deterministic rounding: largest-remainder on allocation lines, residual → first line by sort key, documented in `packages/shared/src/money.ts`. Never `Float`.
- **Earn condition default:** `project_delivery_and_collection` (configurable per project in `CompanySetting`). Payout approvals default `two_person` for amounts > 0 when separation-of-duties enabled.

## D-005 — Ledger (2026-10-09)
- **Decision:** Double-entry (`LedgerTransaction` 1—N `LedgerEntry`, sum(entries)=0 enforced in service + test). Append-only: no update/delete on posted rows; corrections via `REVERSAL`/`ADJUSTMENT` linked rows. Idempotency-Key header required on POST /invoices/:id/collections, /payouts, /ledger-transactions.

## D-006 — Permissions (2026-10-09)
- **Decision:** RBAC + scope (`ORG | PROJECT_IDS | SECTION_IDS | ASSIGNED_ONLY`). 50-key registry in `packages/shared/src/permissions.ts`, enforced by `PermissionsGuard` server-side. Last-admin guard + no self-grant-escalation. Frontend hiding is UX only.

## D-007 — Progress (2026-10-09)
- **Decision:** Weighted by `estimateHours ?? weight ?? 1`; manual override requires `tasks.edit` + audit reason. Documented in code + tests.

## D-008 — Attachments (2026-10-09)
- **Decision (Phase 0-1):** Local `./uploads` with random filenames, MIME allowlist, size cap 10 MB, access-controlled download (`attachments.view` scope). S3-compatible switch later via `STORAGE_DRIVER` env.

## D-009 — Timeline/Gantt lib (2026-10-09)
- **Decision:** No external Gantt dep in Phase 0. Custom timeline (CSS grid) + calendar views; evaluate `gantt-task-react` in Phase 1 only if maintained + MIT-compatible.

## D-010 — What is NOT built in v1
- No bank integration / auto transfers. No real tax engine (configurable tax fields only + "consult professional" notice). No email provider required (in-app notifications; SMTP behind config later).

## D-011 — Profit policy: estimated vs actual vs distributable (2026-10-09)
- **Context:** مثال الشركة المعتمد: إيراد 100,000 + مصروفات مباشرة 20,000 + مخصصات فريق 50,000 → المتبقي 30,000 **ليس ربحاً قابلاً للتوزيع تلقائياً**.
- **Decision:** ثلاثة أرقام منفصلة دائماً (كلها `Int` قروش):
  1. `estimatedProfit = contractValue − estExpenses − plannedAllocations` (يُعرض بشارة "تقديري").
  2. `actualProfit = collected − actualExpenses − approvedCompensation − taxesFees − otherCosts` (يظهر فقط عند اكتمال البيانات).
  3. `distributable = actualProfit − reserve − outstandingObligations` ولا يُحسب إلا بعد `financialReview=true` + اعتماد `finance.approve_payout` — **النظام يمنع التوزيع التلقائي برمجياً** (`assertDistributable` ترمي خطأ عند أي التزام معلق غير مُعفى بسبب موثق).
- **Why:** الضرائب/الرسوم/التكاليف الأخرى والالتزامات المتبقية تحددها السياسة المحاسبية للشركة، وليست معادلة ثابتة. الكود في `packages/shared/src/profit.ts` + اختبار المثال 100k/20k/50k.
- **Reversible:** الصيغة قابلة للضبط عبر `CompanySetting` (`PROFIT_FORMULA_VERSION`) دون تغيير الـ API.
