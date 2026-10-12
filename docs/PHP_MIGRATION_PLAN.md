# خطة التحويل: NestJS + SQLite → PHP Native + MySQL

> **الحالة: ✅ تم التنفيذ كاملًا — `apps/php-api` شغال + 92/92 تست ناجحة (`php apps/php-api/tests/run.php`).**
> التفاصيل: `apps/php-api/README.md` (تشغيل + رفع cPanel).

> القيود المتفق عليها: Pure PHP بدون Composer — نفس JWT الحالي — قاعدة MySQL نضيفة — استضافة Shared cPanel.
> الهدف: نفس الـ contract (نفس paths + شكل JSON) عشان الفرونت `apps/web/src/lib/client.ts:1` ميتغيرش غير سطر `NEXT_PUBLIC_API_URL`.

---

## المرحلة 0 — Scaffold والبنية الأساسية
**الهدف:** هيكل `apps/php-api` شغال + اتصال MySQL + health check.
- [ ] إنشاء هيكل:
  ```
  apps/php-api/
    public/index.php public/.htaccess
    config/config.php
    lib/Db.php lib/Jwt.php lib/Auth.php lib/Response.php lib/Validate.php lib/Money.php
    modules/ database/schema.sql database/seed.php
  ```
- [ ] `config/config.php`: قراءة env (`DB_HOST/DB_NAME/DB_USER/DB_PASS/JWT_ACCESS_SECRET/JWT_ACCESS_TTL/CORS_ORIGIN`) من ملف خارج `public_html`.
- [ ] `lib/Db.php`: PDO singleton + `ERRMODE_EXCEPTION` + prepared statements فقط.
- [ ] `lib/Response.php`: `json($status, $data)` + فورمات أخطاء موحد (`401/403/404/422`).
- [ ] `public/.htaccess`: rewrite كل `/api/*` على `index.php` + Router ي parse `REQUEST_URI` و `REQUEST_METHOD`.
- [ ] `GET /api/health`: `SELECT 1` على MySQL.
- [ ] معيار القبول: `GET /api/health → 200 {"ok":true}` على سيرفر محلي (XAMPP/PHP 8.1+).

## المرحلة 1 — Auth + Users + Permissions (بدونها nothing works)
**الهدف:** دخول + صلاحيات مطابقة للنظام الحالي.
- [ ] `lib/Jwt.php` يدوي: `base64url + hash_hmac HS256 + exp=900s` بنفس `JWT_ACCESS_SECRET` الحالي.
- [ ] `lib/Auth.php`: port لـ `apps/api/src/access.ts` — `effectivePermissions()` (اتحاد الـ roles + تطبيق overrides) + `requirePerm()` ترمي 403 + `JwtGuard` (يقرأ `Authorization: Bearer` ويتأكد `isActive`).
- [ ] Endpoints (نفس paths الحالية):
  - `POST /api/auth/bootstrap` (يشتغل لو `BOOTSTRAP_ENABLED=true` والجدول فاضي — ينشئ 5 founders بـ `password_hash BCRYPT cost 12`)
  - `POST /api/auth/login → {accessToken, user}`
  - `GET /api/users` `GET /api/users/me` (قبل `:id`) `POST /api/users` `POST /api/users/:id/{roles,overrides,deactivate}`
  - `GET /api/permissions` `GET /api/roles` `GET /api/users/:id/effective-permissions`
- [ ] معيار القبول: login من الفرونت الحالي + تخزين `cos_token` + فتح لوحة التحكم بدون تعديل `client.ts`.

## المرحلة 2 — قاعدة MySQL الكاملة (39 جدول)
**الهدف:** ترجمة `prisma/schema.prisma` كاملة لـ MySQL نضيفة.
- [ ] كتابة `database/schema.sql`:
  - `cuid → VARCHAR(30) PK` — `DateTime → DATETIME(3)` بصيغة ISO8601 — `Boolean → TINYINT(1)`
  - الفلوس `INT minor units` (ممنوع Float) — Enums `VARCHAR(30)`
  - كل FKs + `ON DELETE CASCADE` + indexes على `@@unique` الحالية
  - ترتيب الإنشاء: Identity → Clients/Contracts → Projects → Tasks → Finance → Governance
- [ ] `database/seed.php`: founders + roles الـ 5 + الـ 55 permission من `permissions.registry.ts` + settings (`COMPANY_CURRENCY=EGP`, `Africa/Cairo`).
- [ ] معيار القبول: import واحد من phpMyAdmin بدون errors + `seed.php` يتنفذ مرة واحدة ثم يتمسح.

## المرحلة 3 — الموديولات الأساسية (Core)
**الهدف:** المشاريع والمهام.
- [ ] `projects`: `GET / POST / PATCH / :id/archive / :id/members (GET+POST)` — 7 endpoints
- [ ] `sections`: `GET /projects/:projectId/sections` + `POST /sections` + `PATCH /sections/:id` + members
- [ ] `tasks`: list/get/create/update + `dependencies + comments + time-entries`
- [ ] `clients`: `GET/POST /clients` — `contracts`: `GET/POST /contracts`
- [ ] `timeline`: `tasks/overdue/upcoming/workload` — `settings`: `GET / POST /settings/:key`
- [ ] `notifications`: list + `read + read-all` — `audit`: `GET /audit-logs`
- [ ] معيار القبول: إنشاء مشروع → section → task → تعليق → time-entry من الفرونت الحالي بدون errors.

## المرحلة 4 — المالية (حساسة — double-entry)
**الهدف:** الفواتير والخزنة بنفس قواعد `money.ts`.
- [ ] `lib/Money.php`: port لـ `splitAllocation + assertLedgerBalanced (sum=0)`.
- [ ] `invoices`: list/get/create + `collections` (يدعم `Idempotency-Key`) + `collections/:id/reverse`
- [ ] `expenses`: create + `approve/reject/mark-paid`
- [ ] `treasury`: `financial-accounts/balances/cash-position` + `ledger-transactions (POST + reverse)`
- [ ] `payouts`: list/request (`Idempotency-Key`) + `approve/mark-paid`
- [ ] `allocations`: `allocation-plans (list/create/submit/approve/supersede)`
- [ ] `payments`: list + `reverse`
- [ ] معيار القبول: فاتورة → تحصيل → قيد دفتري متوازن + كاش الخزنة يتحدث في الداشبورد.

## المرحلة 5 — الحوكمة والتقارير
**الهدف:** الاعتمادات والتقارير.
- [ ] `approvals`: `GET ?state` + request + `:id/decide {decision,comment}`
- [ ] `reports`: `project-financial/:id` + `section-performance/:id` + `team-contribution/:id` + `cash-flow` + `outstanding-receivables`
- [ ] معيار القبول: كل تقارير الفرونت ترجع نفس أرقام NestJS على نفس الداتا.

## المرحلة 6 — الربط والتنظيف
- [ ] تغيير سطر واحد في الفرونت: `NEXT_PUBLIC_API_URL` → رابط `public_html/api` الجديد.
- [ ] اختبار شامل: login → dashboard (الكروت الـ 9) → كل CRUD — مقارنة response-by-response مع NestJS.
- [ ] توثيق `docs/DEPLOYMENT.md`: خطوات cPanel (إنشاء DB/user + import + config خارج public_html + صلاحيات ملفات).
- [ ] أرشفة `apps/api` (rename لـ `apps/api-nest-legacy`) وإخراجها من `workspaces` في `package.json:6` — بدون مسح نهائي إلا بعد أسبوع استقرار.

## المرحلة 7 — رفع cPanel
1. رفع `public/` → `public_html/api/` و `config/` خارج `public_html`.
2. إنشاء MySQL DB + user من cPanel + import `schema.sql`.
3. ضبط `.env` الإنتاج (`JWT secrets` جديدة 32+ حرف + `CORS_ORIGIN` الدومين الفعلي + `BOOTSTRAP_ENABLED=false` بعد الـ seed).
4. تشغيل `seed.php` مرة واحدة ثم حذفه + اختبار `/api/health` على الدومين.

---

## ملاحظات تقنية ثابتة
- PHP >= 8.1 + `pdo_mysql` — ممنوع `composer` — ممنوع concat في SQL.
- نفس status codes: `401` بدون توكن (الفرونت بيعمل redirect `/login`) — `403` نقص صلاحية — `422` validation.
- `password_hash()` بدل `bcryptjs` — نفس cost 12 عشان التوافق السلوكي.
