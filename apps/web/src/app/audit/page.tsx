"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { fmtDateTime } from "../../lib/ar";
import { Page, Btn, Input, Err, Empty, Table, Td } from "../../lib/ui";

export default function AuditPage() {
  const [items, setItems] = useState<any[]>([]);
  const [action, setAction] = useState("");
  const [err, setErr] = useState<string | null>(null);

  async function load(a = action) {
    try {
      const q = a ? `?action=${encodeURIComponent(a)}&limit=100` : `?limit=100`;
      const r = await api(`/audit-logs${q}`);
      setItems(Array.isArray(r) ? r : []);
    } catch (e: any) { setErr(e.message); }
  }
  useEffect(() => { load(); }, []);

  return (
    <Page title="سجل التدقيق">
      <div className="mb-3 flex flex-wrap gap-2">
        <Input placeholder="ابحث بالحدث (مثال auth.login)" value={action} onChange={(e) => setAction(e.target.value)} dir="ltr" className="min-w-52 flex-1" />
        <Btn onClick={() => load()}>بحث</Btn>
        <Btn onClick={() => { setAction(""); load(""); }}>مسح</Btn>
      </div>
      <Err msg={err} />
      {items.length > 0 ? (
        <Table head={["الحدث", "الكيان", "التوقيت", "التفاصيل"]}>
          {items.map((l) => (
            <tr key={l.id} className="hover:bg-brand-50/50">
              <Td className="font-bold" ><span dir="ltr">{l.action}</span></Td>
              <Td className="text-slate-500">{l.entity}</Td>
              <Td className="whitespace-nowrap text-xs text-slate-500">{fmtDateTime(l.createdAt)}</Td>
              <Td className="text-xs text-slate-500">{l.diff ?? ""}{l.reason ? ` · ${l.reason}` : ""}</Td>
            </tr>
          ))}
        </Table>
      ) : !err ? <div className="text-sm text-slate-500">جاري التحميل…</div> : <Empty text="لا توجد مدخلات." />}
    </Page>
  );
}
