"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { useMe } from "../../lib/use-me";
import { Page, Card, Btn, Input, Err, Ok, Empty } from "../../lib/ui";

const KEY_AR: Record<string, string> = {
  EARN_CONDITION: "شرط الاستحقاق", PAYOUT_APPROVAL_MODE: "سياسة اعتماد الصرف", COMPANY_CURRENCY: "عملة الشركة",
};

export default function SettingsPage() {
  const { can } = useMe();
  const [items, setItems] = useState<any[]>([]);
  const [err, setErr] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);
  const [vals, setVals] = useState<Record<string, string>>({});

  async function load() {
    try {
      const r = await api("/settings");
      const list = Array.isArray(r) ? r : [];
      setItems(list);
      const v: Record<string, string> = {};
      for (const s of list) v[s.key] = s.value;
      setVals(v);
    } catch (e: any) { setErr(e.message); }
  }
  useEffect(() => { load(); }, []);

  async function save(key: string) {
    setErr(null); setOk(null);
    try {
      await api(`/settings/${key}`, { method: "POST", body: JSON.stringify({ value: vals[key] ?? "" }) });
      setOk("تم الحفظ بنجاح");
      await load();
    } catch (e: any) { setErr(e.message); }
  }

  return (
    <Page title="الإعدادات" sub="العملة وشرط الاستحقاق وسياسة الاعتماد — التغييرات تُطبق فورًا">
      <Err msg={err} />
      <Ok msg={ok} />
      <Card>
        <div className="grid gap-3">
          {items.map((s) => (
            <div key={s.key} className="flex flex-col gap-2 border-b border-slate-100 pb-3 last:border-0 last:pb-0 sm:flex-row sm:items-center sm:gap-3">
              <b className="shrink-0 text-navy-900 sm:w-52">{KEY_AR[s.key] ?? s.key}<span className="block text-xs font-semibold text-slate-400" dir="ltr">{s.key}</span></b>
              <Input className="flex-1" value={vals[s.key] ?? ""} onChange={(e) => setVals({ ...vals, [s.key]: e.target.value })} dir="ltr" disabled={!can("settings.manage")} />
              {can("settings.manage") && <Btn v="accent" onClick={() => save(s.key)}>حفظ</Btn>}
            </div>
          ))}
          {items.length === 0 && !err && <div className="text-sm text-slate-500">جاري التحميل…</div>}
          {items.length === 0 && err && <Empty text="لا يمكن عرض الإعدادات — تحتاج صلاحية." />}
        </div>
      </Card>
    </Page>
  );
}
