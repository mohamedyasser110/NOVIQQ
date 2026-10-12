# WORKFLOWS — التشغيل العملي من المشروع حتى الصرف (9 خطوات)

> المصدر: رسالة الشركة المعتمدة (6 خطوات) + استكمال الدورة المالية حتى مراجعة الربح (D-011).

## الخطوات 1–6 (التشغيل)

### 1. Create Project — إدخال العميل والعقد والسعر والموعد
- `POST /clients` → `POST /contracts` → `POST /projects` (code فريد، contractValue قروش، currency)
- إرفاق العقد الموقع (Attachment) + الشروط/المواعيد. الحالة تبدأ `DRAFT → PLANNING → ACTIVE`.
- صلاحية: `projects.create` + `contracts.manage`.

### 2. Configure Sections — الأقسام والمسؤولون والتواريخ
- `POST /projects/:id/sections` (أسماء حرة: Frontend/Backend/AI/QA…) + `POST /sections/:id/members` + Lead وتواريخ.
- عضو واحد ←→ N أقسام. صلاحية: `sections.create`.

### 3. Create Tasks — المهام والمراجعون والتبعيات
- `POST /tasks` (assignees متعددون + reviewer + dependencies مع منع الدوائر) → Kanban/My Tasks.
- تعديل المواعيد بسبب موثق + إظهار أثر المهام التابعة دون تحريك صامت. صلاحية: `tasks.create/assign`.

### 4. Approve Allocation Plan — اعتماد مخصصات الأقسام والأعضاء
- `POST /allocation-plans` (draft: نسب/مبالغ/هجين 60/40) → `POST /allocation-plans/:id/submit` → `:id/approve` → snapshot نسخة `v1`.
- التحقق: المجموع ≤ الأساس + المتبقي غير المخصص ظاهر + تقريب حتمي. أي تعديل لاحق = نسخة جديدة، والمدفوعات القديمة لا تُمس.
- صلاحية: `allocations.create/approve`.

### 5. Execute & Track — الحالات والساعات والتسليمات
- تحديث `IN_PROGRESS → IN_REVIEW → DONE` + time entries (اعتماد اختياري) + مراجعة وقبول.
- إنجاز المهمة **لا يولّد مستحقاً مالياً تلقائياً** (الفصل المقدس شغل≠فلوس).

### 6. Collect Revenue — الفواتير والمتحصلات الفعلية فقط
- `POST /invoices` (بنود + ضريبة/خصم قابل للضبط) → `POST /invoices/:id/collections` (جزئي مدعوم + `Idempotency-Key` + إيصال).
- الفاتورة ≠ النقد. المتحصل = ما دخل الخزينة فعلاً.

## الخطوات 7–9 (المالية الختامية)

### 7. Treasury Allocation — توزيع المتحصل على فئات الخزينة
- `POST /ledger-transactions` (تحويل داخلي بين `COMPANY_RESERVE/MARKETING/WORKING_CAPITAL…`) — التحويل الداخلي **ليس إيراداً جديداً**.

### 8. Payouts — المستحقات: طلب → اعتماد ثنائي → صرف
- `POST /payouts` (مرجع entitlement المعتمد) → `:id/approve` (شخصان عند فصل المهام) → `:id/mark-paid` (مرجع بنكي + إيصال). منع التكرار والزيادة عن المعتمد.

### 9. Profit Review — الربح التقديري ثم الفعلي، والتوزيع بقرار
- **المثال المعتمد:** 100,000 إيراد − 20,000 مصروفات − 50,000 مخصصات = 30,000 **متبقٍ غير قابل للتوزيع تلقائياً**.
- الداشبورد يعرض `estimatedProfit` بشارة "تقديري"، و`actualProfit = collected − actualExpenses − approvedComp − taxesFees − otherCosts` عند اكتمال البيانات.
- `assertDistributable()` تمنع برمجياً أي توزيع قبل `financialReview + approval + تصفير الالتزامات (أو إعفاء موثق)`. القرار للشركة والمراجعة المالية، وليس معادلة تلقائية.
- الكود: `packages/shared/src/profit.ts` + 3 اختبارات خضراء.
