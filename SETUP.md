# تشغيل المشروع على جهاز جديد — خطوة بخطوة

> من تحميل الريبو لحد تسجيل الدخول. (Windows — ولو Mac/Linux نفس الأوامر تقريبًا).

## 1. ثبّت المتطلبات
| البرنامج | الإصدار | تحقق |
|---|---|---|
| Git | أي حديث | `git --version` |
| Node.js | 20+ | `node --version` |
| PHP | 8.1+ | `php --version` |

- Node من https://nodejs.org (اختر LTS).
- PHP على ويندوز أسهل طريق: ثبّت **XAMPP** (فيه PHP)، وبعد التثبيت ضيف فولدر `php` بتاعه للـ PATH (مثلًا `C:\xampp\php`)، وافتح ترمينال جديد وجرّب `php --version`.

## 2. نزّل المشروع
```powershell
git clone https://github.com/mohamedyasser110/NOVIQQ.git
cd NOVIQQ
Copy-Item .env.example .env
```

## 3. ثبّت مكتبات الفرونت
```powershell
npm install
```

## 4. جهّز قاعدة البيانات (SQLite — بدون أي سيرفر)
```powershell
php apps/php-api/database/migrate.php --fresh
php apps/php-api/database/seed.php
```
هيعمل 5 founders، الدخول بتاعهم تحت.

## 5. شغّل
دبل كليك على **`RUN.BAT`** — هيفتح شباكين: الباك على `:4100` والفرونت على `:3100`.
(أو يدويًا في ترمينالين: `npm run dev:api` ثم `npm run dev:web`. للإيقاف: `STOP.BAT`.)

## 6. ادخل واستعمل
- افتح http://localhost:3100
- سجّل دخول: `founder1@example.test` / `ChangeMe123!`
- ⚠️ أول حاجة: صفحة **حسابي** ← غيّر الباسورد (ولو حابب غيّر الإيميل لإيميلك).

## اختياري
```powershell
php apps/php-api/database/demo.php   # سيناريو تجريبي كامل (عميل+مشروع+فاتورة+صرف) وتتفرج عليه من المتصفح
php apps/php-api/tests/run.php       # تست الباك (99 فحص)
npm run test -w packages/shared      # تست اللوجيك المشترك
php apps/php-api/database/wipe.php   # مسح كل الداتا مع الاحتفاظ باليوزرات
```

## مشاكل شائعة
- `php : command not found` ← الـ PATH (ارجع لخطوة 1) وافتح ترمينال جديد.
- البورت مشغول (`:4100` أو `:3100`) ← اقفل البرنامج اللي واخده أو وقّف السيرفر القديم بـ `STOP.BAT`.
- صفحة بيضا من غير ستايل ← `Ctrl+F5` (تحديث من غير كاش).
- `npm install` بطيء/بيفشل ← جرّب `npm install --no-audit --no-fund` أو اتأكد من النت والـ VPN.
