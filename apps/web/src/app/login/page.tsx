"use client";
import { useState } from "react";
import { login } from "../../lib/client";
import { useRouter } from "next/navigation";

const QUICK: { label: string; hint: string; email: string; password: string }[] = [
  { label: "مؤسس", hint: "كل الصلاحيات", email: "founder1@example.test", password: "ChangeMe123!" },
  { label: "مدير عام", hint: "كل الصلاحيات", email: "amr@demo.test", password: "Demo1234!" },
  { label: "مدير مشروع", hint: "مشاريع وتاسكات", email: "sara@demo.test", password: "Demo1234!" },
  { label: "موظف", hint: "تاسكاته فقط", email: "kareem@demo.test", password: "Demo1234!" },
  { label: "مالية", hint: "فواتير وخزنة", email: "hassan@demo.test", password: "Demo1234!" },
  { label: "مشاهد", hint: "قراءة فقط", email: "rana@demo.test", password: "Demo1234!" },
];

const FEATURES = ["إدارة المشاريع والمهام لحظة بلحظة", "مالية متكاملة: فواتير وتحصيل وخزنة", "صلاحيات دقيقة وفصل للمسؤوليات"];

export default function LoginPage() {
  const [email, setEmail] = useState("founder1@example.test");
  const [password, setPassword] = useState("ChangeMe123!");
  const [show, setShow] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const router = useRouter();

  function fill(email: string, password: string) {
    setEmail(email); setPassword(password); setErr(null);
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setErr(null); setBusy(true);
    try { await login(email, password); router.push("/"); }
    catch (e: any) { setErr("بيانات الدخول غلط — اتأكد من الإيميل والباسورد"); }
    finally { setBusy(false); }
  }

  return (
    <div className="grid overflow-hidden rounded-3xl shadow-2xl md:grid-cols-2">
      {/* brand panel */}
      <div className="relative hidden flex-col justify-between overflow-hidden bg-navy-950 p-8 text-white md:flex">
        <div className="pointer-events-none absolute -left-20 -top-20 h-64 w-64 rounded-full bg-brand-500/30 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-24 -right-16 h-72 w-72 rounded-full bg-accent-500/25 blur-3xl" />
        <div className="relative flex items-center gap-1.5">
          <img src="/logo.png" alt="noviq solutions" className="h-12 w-12 object-contain drop-shadow-[0_0_16px_rgba(249,115,22,0.4)]" />
          <div className="text-2xl font-black leading-tight">noviq <span className="text-accent-500">solutions</span></div>
        </div>
        <div className="relative">
          <h2 className="text-2xl font-black leading-snug">نظام الشركة الداخلي<br />في مكان واحد.</h2>
          <ul className="mt-5 space-y-3">
            {FEATURES.map((f) => (
              <li key={f} className="flex items-center gap-2 text-sm text-white/80">
                <span className="inline-block h-2 w-2 shrink-0 rounded-full bg-accent-500" />{f}
              </li>
            ))}
          </ul>
        </div>
        <div className="relative text-xs text-white/40">مشاريع · مالية · صلاحيات · تقارير</div>
      </div>

      {/* form panel */}
      <div className="bg-white p-6 sm:p-8">
        <div className="mb-4 flex md:hidden" dir="ltr">
          <div className="flex items-center gap-2">
            <img src="/logo.png" alt="noviq" className="h-9 w-9 object-contain" />
            <div className="text-lg font-black tracking-tight text-navy-900">noviq <span className="text-accent-500">solutions</span></div>
          </div>
        </div>
        <h1 className="text-2xl font-black text-navy-900">أهلًا بعودتك</h1>
        <p className="mb-5 mt-1 text-sm text-slate-500">سجّل الدخول للوصول إلى لوحة التحكم</p>

        <div className="mb-4">
          <div className="mb-2 text-xs font-bold text-slate-500">دخول سريع حسب الدور:</div>
          <div className="grid grid-cols-3 gap-1.5">
            {QUICK.map((q) => (
              <button
                key={q.label} type="button"
                onClick={() => fill(q.email, q.password)}
                title={`${q.email} / ${q.password}`}
                className={`rounded-xl border px-2 py-1.5 text-center transition ${email === q.email ? "border-accent-500 bg-accent-50 shadow-sm" : "border-slate-200 bg-white hover:border-brand-500 hover:bg-brand-50/50"}`}
              >
                <div className={`text-xs font-black ${email === q.email ? "text-accent-600" : "text-navy-900"}`}>{q.label}</div>
                <div className="text-[10px] text-slate-400">{q.hint}</div>
              </button>
            ))}
          </div>
        </div>

        <form onSubmit={submit} className="flex flex-col gap-3">
          <label className="block">
            <span className="mb-1 block text-xs font-bold text-slate-500">الإيميل</span>
            <input className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-navy-900 transition focus:border-accent-500" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="name@company.com" dir="ltr" />
          </label>
          <label className="block">
            <span className="mb-1 block text-xs font-bold text-slate-500">الباسورد</span>
            <span className="relative block">
              <input className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-navy-900 transition focus:border-accent-500" type={show ? "text" : "password"} value={password} onChange={(e) => setPassword(e.target.value)} placeholder="••••••••" dir="ltr" />
              <button type="button" onClick={() => setShow(!show)} className="absolute end-2 top-1/2 -translate-y-1/2 rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-500 hover:bg-slate-200">
                {show ? "إخفاء" : "إظهار"}
              </button>
            </span>
          </label>
          {err && <div className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">{err}</div>}
          <button disabled={busy} className="rounded-xl bg-gradient-to-l from-accent-500 to-accent-600 px-3 py-3 text-sm font-black text-white shadow-lg shadow-accent-500/30 transition hover:brightness-105 disabled:opacity-60" type="submit">
            {busy ? "جاري الدخول…" : "دخول ←"}
          </button>
        </form>

        <p className="mt-4 border-t border-slate-100 pt-3 text-[11px] leading-relaxed text-slate-400">
          تجريبي: <span dir="ltr">founder1@example.test / ChangeMe123!</span> أو أي حساب <span dir="ltr">…@demo.test / Demo1234!</span>
        </p>
      </div>
    </div>
  );
}
