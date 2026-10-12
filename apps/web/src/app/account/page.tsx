"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { Page, Card, Btn, Input, Err, Ok, Pill } from "../../lib/ui";

const ROLE_AR: Record<string, string> = {
  FOUNDER_ADMIN: "مدير عام", PROJECT_MANAGER: "مدير مشروع", EMPLOYEE: "موظف", FINANCE_MANAGER: "مدير مالي", VIEWER: "مشاهد",
};

export default function AccountPage() {
  const [me, setMe] = useState<any>(null);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [cur, setCur] = useState("");
  const [next, setNext] = useState("");
  const [confirm, setConfirm] = useState("");
  const [err, setErr] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);

  async function load() {
    try {
      const m = await api("/users/me");
      setMe(m); setName(m.name ?? ""); setEmail(m.email ?? "");
    } catch (e: any) { setErr(e.message); }
  }
  useEffect(() => { load(); }, []);

  async function saveName(e: React.FormEvent) {
    e.preventDefault(); setErr(null); setOk(null);
    if (!name.trim()) return setErr("اكتب الاسم");
    if (!email.trim()) return setErr("اكتب الإيميل");
    try {
      const m = await api("/users/me", { method: "PATCH", body: JSON.stringify({ name: name.trim(), email: email.trim() }) });
      setMe(m);
      try {
        const raw = localStorage.getItem("cos_user");
        if (raw) localStorage.setItem("cos_user", JSON.stringify({ ...JSON.parse(raw), name: m.name, email: m.email }));
      } catch { /* ignore */ }
      setOk("تم حفظ البيانات بنجاح");
    } catch (e: any) { setErr(e.message); }
  }

  async function changePw(e: React.FormEvent) {
    e.preventDefault(); setErr(null); setOk(null);
    if (next.length < 8) return setErr("الباسورد الجديد لازم 8 حروف على الأقل");
    if (next !== confirm) return setErr("تأكيد الباسورد غير متطابق");
    try {
      await api("/users/me/password", { method: "POST", body: JSON.stringify({ currentPassword: cur, newPassword: next }) });
      setCur(""); setNext(""); setConfirm("");
      setOk("تم تغيير الباسورد بنجاح");
    } catch (e: any) { setErr(e.message); }
  }

  return (
    <Page title="حسابي" sub="إعدادات حسابك — متاحة لكل الأدوار">
      <Err msg={err} />
      <Ok msg={ok} />
      <div className="grid gap-3 lg:grid-cols-2">
        <Card>
          <div className="mb-3 font-black text-navy-900">البيانات</div>
          <form onSubmit={saveName} className="grid gap-2">
            <div className="text-sm text-slate-500">الإيميل (هتدخل بيه بعد كده)</div>
            <Input dir="ltr" value={email} onChange={(e) => setEmail(e.target.value)} />
            <div className="text-sm text-slate-500">الاسم</div>
            <div className="flex gap-2">
              <Input className="flex-1" value={name} onChange={(e) => setName(e.target.value)} />
              <Btn v="accent" type="submit">حفظ</Btn>
            </div>
          </form>
          <div className="mt-4 flex flex-wrap gap-1">
            {(me?.roles ?? []).map((r: string) => (
              <Pill key={r} c="blue">{ROLE_AR[r] ?? r}</Pill>
            ))}
          </div>
        </Card>
        <Card>
          <div className="mb-3 font-black text-navy-900">تغيير الباسورد</div>
          <form onSubmit={changePw} className="grid gap-2">
            <Input type="password" placeholder="الباسورد الحالي" value={cur} onChange={(e) => setCur(e.target.value)} />
            <Input type="password" placeholder="الباسورد الجديد (8+ حروف)" value={next} onChange={(e) => setNext(e.target.value)} />
            <Input type="password" placeholder="تأكيد الباسورد الجديد" value={confirm} onChange={(e) => setConfirm(e.target.value)} />
            <Btn type="submit">تغيير الباسورد</Btn>
          </form>
        </Card>
      </div>
    </Page>
  );
}
