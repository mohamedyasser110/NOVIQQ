# الرفع على Hostinger (Shared Hosting) — خطوة بخطوة

> الفكرة: الفرونت (Next.js) يترفع كملفات ثابتة، والباك (PHP) + MySQL يشتغلوا على نفس الدومين.
> مثال الدومين في الشرح: `https://company.example` — بدّلها بدومينك.

## المتطلبات قبل الرفع
- خطة Hostinger فيها **MySQL + phpMyAdmin** (كل خطط الـ Shared فيها).
- الدومين مربوط بالاستضافة والـ SSL شغال (hPanel ← Security ← SSL ← Force HTTPS: ON).
- من hPanel ← Advanced ← **PHP Configuration** اختر **8.1 أو أعلى** (الكود Pure PHP بدون Composer).

## المرحلة 1 — قاعدة البيانات (10 دقائق)
1. من hPanel افتح **Databases ← Management** وأنشئ:
   - Database: مثلًا `u123456789_company`
   - User + Password قوية، وضيف اليوزر على الداتا بيز بكل الصلاحيات — **احفظ الأسماء والباسورد**.
2. افتح **phpMyAdmin** (زرار من نفس صفحة Databases) وادخل على الداتا بيز الجديدة.
3. من تبويب **Import** ارفع ملف `apps/php-api/database/schema.mysql.sql` من جهازك ودوس Go — لازم تشوف ~39 جدول فارغين (اليوزرات هتتعمل في الخطوة الجاية).

## المرحلة 2 — رفع الباك PHP (File Manager)
ارفع بالترتيب ده من hPanel ← **File Manager**:
1. محتويات `apps/php-api/public/` (ملف `index.php` + `.htaccess`) ← إلى `public_html/api/`
2. فولدرات `lib/` و `modules/` و `config/` و `database/` ← إلى `public_html/api/app/` (يعني يبقى عندك `public_html/api/app/lib/...`)
3. ملف `apps/php-api/app.htaccess` ← إلى `public_html/api/app/.htaccess` (بيمنع الوصول لملفات الكود والأسرار من المتصفح).
4. أنشئ ملف `public_html/api/app/config/.env.php` بالمحتوى ده (ببياناتك الحقيقية):
```php
<?php return [
  'DB_DRIVER' => 'mysql',
  'DB_HOST' => 'localhost',
  'DB_NAME' => 'u123456789_company',
  'DB_USER' => 'u123456789_admin',
  'DB_PASS' => 'PUT-REAL-PASSWORD-HERE',
  'JWT_ACCESS_SECRET' => 'PUT-32-CHAR-MIN-RANDOM-SECRET-HERE',
  'JWT_ACCESS_TTL' => '900',
  'CORS_ORIGIN' => 'https://company.example',
  'BOOTSTRAP_ENABLED' => 'true',
  'BOOTSTRAP_PASSWORD' => 'ChangeMe123!',
];
```
5. ⚠️ امسح من السيرفر: `public_html/api/app/database/seed.php` و `wipe.php` و `demo.php` و `import_users.php` (أدوات محلية خطيرة على الإنتاج).
6. اختبر في المتصفح: `https://company.example/api/health` — لازم يرجع `{"status":"ok","db":"up",...}`.
7. أنشئ اليوزرات: ابعت طلب **POST** واحد (من Postman أو `curl`) إلى `https://company.example/api/auth/bootstrap` بدون body — هيعمل 5 founders بباسورد `BOOTSTRAP_PASSWORD` اللي كتبته في `.env.php`. بعدها رجّع `BOOTSTRAP_ENABLED` إلى `'false'`، وسجّل دخول وغيّر الباسورد من صفحة **حسابي**.

## المرحلة 3 — بناء ورفع الفرونت (من جهازك)
1. على جهازك من فولدر المشروع نفّذ (بدّل الدومين):
```powershell
$env:NEXT_PUBLIC_API_URL="https://company.example/api"; npm run build -w apps/web
```
2. هيتولد فولدر `apps/web/out/` — ارفع **محتوياته** (مش الفولدر نفسه) إلى `public_html/`.
3. ارفع ملف `apps/web/hostinger.htaccess` إلى `public_html/.htaccess`.
4. افتح `https://company.example/login` وسجّل دخول — كل الصفحات (منها **حسابي** الجديدة) شغالة على نفس الدومين، فمفيش مشاكل CORS.

## ملاحظات
- أي تعديل بعد كده في الفرونت = عيد الخطوة 3 فقط. أي تعديل في الباك = ارفع الملفات المتغيرة فقط لنفس أماكنها.
- التست المحلي قبل الرفع: `php apps/php-api/tests/run.php` (شغال على `test.db` المنعزلة، لا يمس داتا حقيقية).
- النسخ الاحتياطي: من hPanel ← Backups قبل أي تحديث كبير.
