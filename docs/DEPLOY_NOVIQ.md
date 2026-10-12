# خطة الرفع: GitHub → system.noviq.solutions (Hostinger Shared)

> الريبو: https://github.com/mohamedyasser110/NOVIQQ (برانش `main`)
> الناتج: الموقع شغال على `https://system.noviq.solutions/` والـ API على `https://system.noviq.solutions/api`

## الخريطة
| الحاجة | مكانها |
|---|---|
| الفرونت (ملفات ثابتة) | فولدر الدومين نفسه (جذر الـ subdomain) |
| الباك PHP | `…/api/` (ملفا `index.php` + `.htaccess`) |
| كود الباك + الإعدادات | `…/api/app/` (ممنوع الوصول ليها من المتصفح) |
| الداتا بيز | MySQL على نفس الاستضافة |

## المرحلة 0 — تجهيز الاستضافة (مرة واحدة)
1. من hPanel تأكد إن الـ subdomain **`system`** مضاف تحت دومين `noviq.solutions` وبيفتح صفحة (حتى لو فاضية).
2. اعرف **document root** بتاعه: hPanel ← Websites ← اختر `system.noviq.solutions` ← وشوف مسار الفولدر (غالبًا `public_html/system` أو `domains/system.noviq.solutions/public_html`). سمّيه في الخطوات الجاية `ROOT`.
3. فعّل **SSL** و **Force HTTPS** للدومين.
4. hPanel ← Advanced ← **PHP Configuration**: اختر **8.1 أو أعلى** للدومين ده.
5. hPanel ← **Databases**: أنشئ DB + user (مثلًا `uXXXX_noviq`) وضيف اليوزر بكل الصلاحيات — **احفظ الأسماء والباسورد في ملف عندك**.

## المرحلة 1 — تحميل الكود من GitHub (على جهازك)
```powershell
git clone https://github.com/mohamedyasser110/NOVIQQ.git deploy
cd deploy
git pull origin main   # للتحديثات الجاية: نفس الأمرين
```
(بدون git؟ من صفحة الريبو دوس `Code` ← `Download ZIP` وفك الضغط.)

## المرحلة 2 — قاعدة البيانات
1. hPanel ← Databases ← افتح **phpMyAdmin** على الداتا بيز الجديدة.
2. تبويب **Import** ← ارفع ملف `apps/php-api/database/schema.mysql.sql` من فولدر `deploy` ← Go.
3. لازم تشوف ~39 جدول فاضيين.

## المرحلة 3 — رفع الباك (File Manager)
1. ارفع محتويات `deploy/apps/php-api/public/` (ملف `index.php` + `.htaccess`) ← إلى `ROOT/api/`.
2. ارفع فولدرات `lib/` و `modules/` و `config/` و `database/` ← إلى `ROOT/api/app/`.
3. ارفع `deploy/apps/php-api/app.htaccess` ← باسم `ROOT/api/app/.htaccess`.
4. أنشئ `ROOT/api/app/config/.env.php` بالمحتوى ده (ببياناتك):
```php
<?php return [
  'DB_DRIVER' => 'mysql',
  'DB_HOST' => 'localhost',
  'DB_NAME' => 'uXXXX_noviq',
  'DB_USER' => 'uXXXX_admin',
  'DB_PASS' => 'PUT-REAL-PASSWORD-HERE',
  'JWT_ACCESS_SECRET' => 'PUT-32-CHAR-MIN-RANDOM-SECRET-HERE',
  'JWT_ACCESS_TTL' => '900',
  'CORS_ORIGIN' => 'https://system.noviq.solutions',
  'BOOTSTRAP_ENABLED' => 'true',
  'BOOTSTRAP_PASSWORD' => 'ChangeMe123!',
];
```
5. ⚠️ امسح من السيرفر: `ROOT/api/app/database/{seed,wipe,demo,import_users}.php`.
6. افتح `https://system.noviq.solutions/api/health` — لازم `{"status":"ok","db":"up",...}`.
7. ابعت **POST** واحد (Postman) إلى `https://system.noviq.solutions/api/auth/bootstrap` بدون body — يعمل 5 founders.
8. رجّع `BOOTSTRAP_ENABLED` إلى `'false'` في `.env.php`.

## المرحلة 4 — بناء ورفع الفرونت
```powershell
cd deploy
npm install
$env:NEXT_PUBLIC_API_URL="https://system.noviq.solutions/api"; npm run build -w apps/web
```
1. ارفع **محتويات** `apps/web/out/` ← إلى `ROOT/` مباشرة.
2. ارفع `apps/web/hostinger.htaccess` ← باسم `ROOT/.htaccess`.
3. افتح `https://system.noviq.solutions/login` وسجّل دخول.

## المرحلة 5 — تحقق ما بعد الرفع
- [ ] `/api/health` → ok + db up
- [ ] دخول founder + تغيير الباسورد من صفحة **حسابي**
- [ ] اعمل مشروع تجريبي + مهمة + فاتورة تحصيل، اتأكد الأرقام بتتحدث
- [ ] امسح المشروع التجريبي (أو `wipe.php` محليًا ممنوع على الإنتاج — امسح يدويًا من الواجهة)
- [ ] `BOOTSTRAP_ENABLED=false` + ملفات الأدوات ممسوحة من السيرفر

## المرحلة 6 — أي تحديث بعد كده
1. `git pull origin main` في فولدر `deploy` (أو ZIP جديد).
2. فرونت متغير؟ عيد بناء الخطوة 4 وارفع `out/` + الملفات المتغيرة فقط.
3. باك متغير؟ ارفع الملفات المتغيرة لنفس أماكنها (`api/` أو `api/app/...`).
4. سكيمة DB اتغيرت؟ طبّق التعديل يدويًا من phpMyAdmin (مفيش migrate تلقائي على الـ Shared).
