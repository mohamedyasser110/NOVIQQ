# PHP Native API + MySQL (cPanel-ready, zero dependencies)

بديل `apps/api` (NestJS) — نفس الـ 81 endpoint بنفس paths وشكل JSON، عشان الفرونت `apps/web` ميتغيرش غير سطر `NEXT_PUBLIC_API_URL`.

## التشغيل محليًا (SQLite, بدون MySQL)
```powershell
php apps/php-api/database/migrate.php --fresh
php apps/php-api/database/seed.php
php -S 127.0.0.1:4100 -t apps/php-api/public apps/php-api/server.php
# health: http://127.0.0.1:4100/api/health
# login: founder1@example.test / ChangeMe123!
```
ثم في `apps/web/.env.local`: `NEXT_PUBLIC_API_URL=http://127.0.0.1:4100/api`

## التست (92 assertion, بدون سيرفر — على قاعدة test.db المنعزلة، لا يمس app.db)
```powershell
php apps/php-api/tests/run.php
```

## مسح الداتا مع الاحتفاظ باليوزرات
```powershell
php apps/php-api/database/wipe.php
```

## الرفع على cPanel (MySQL)
1. cPanel → MySQL: أنشئ DB + user وضيفه على الداتا بيز.
2. phpMyAdmin → استورد `apps/php-api/database/schema.mysql.sql`.
3. ارفع محتويات `apps/php-api/public/` إلى `public_html/api/` وباقي فولدرات `apps/php-api/` إلى مكان خارج `public_html` (مثلًا `~/php-api/`)، وعدّل مسار `require` في `index.php` لو لزم.
4. أنشئ `apps/php-api/config/.env.php` على السيرفر (خارج `public_html`):
```php
<?php return [
  'DB_DRIVER' => 'mysql', 'DB_HOST' => 'localhost', 'DB_NAME' => 'xxx_company',
  'DB_USER' => 'xxx_user', 'DB_PASS' => 'SECRET',
  'JWT_ACCESS_SECRET' => '32-char-min-production-secret-xxxx',
  'CORS_ORIGIN' => 'https://your-domain.com', 'BOOTSTRAP_ENABLED' => 'true',
];
```
5. شغّل مرة واحدة: `php database/seed.php` (أو نفّذ `POST /api/auth/bootstrap`) ثم احذف `seed.php` وخلي `BOOTSTRAP_ENABLED=false`.
6. اختبر `https://your-domain.com/api/health` ثم غيّر `NEXT_PUBLIC_API_URL` في الفرونت لرابط الـ API الجديد.

## ملاحظات
- JWT يدوي HS256 + `password_hash BCRYPT cost 12` — نفس سلوك Nest (توكن 15 دقيقة، بدون refresh).
- الفلوس `INT minor units` + دفتر مزدوج `sum=0` إجباري + `Idempotency-Key` للتحصيل والقيود والصرف.
- `payouts`: الطالب لا يعتمد طلبه (SoD) + الاستحقاق لازم `APPROVED` قبل الصرف.
