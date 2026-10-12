"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { useMe } from "../../lib/use-me";
import { g } from "../../lib/ar";
import { Page, Btn, Input, Err, Empty, Status } from "../../lib/ui";

export default function InvoicesPage() {
  const { can } = useMe();
  const [items, setItems] = useState<any[]>([]);
  const [err, setErr] = useState<string | null>(null);
  const [pay, setPay] = useState<Record<string, string>>({});

  async function load() {
    try { const r = await api("/invoices"); setItems(Array.isArray(r) ? r : []); } catch (e: any) { setErr(e.message); }
  }
  useEffect(() => { load(); }, []);

  async function collect(id: string) {
    const major = Number(pay[id] || 0);
    if (!(major > 0)) return setErr("اكتب مبلغًا موجبًا");
    setErr(null);
    try {
      await api(`/invoices/${id}/collections`, {
        method: "POST",
        headers: { "Idempotency-Key": `ui-${id}-${Date.now()}` },
        body: JSON.stringify({ amount: Math.round(major * 100), method: "BANK_TRANSFER" }),
      });
      await load();
    } catch (e: any) { setErr(e.message); }
  }

  return (
    <Page title="الفواتير والتحصيل" sub="المفوتر ≠ المحصّل ≠ الكاش · المبالغ بالجنيه">
      <Err msg={err} />
      <div className="grid gap-3">
        {items.map((i) => {
          const cols = Array.isArray(i.collections) ? i.collections : [];
          const collected = cols.filter((c: any) => c.approved).reduce((s: number, c: any) => s + c.amount, 0);
          const recv = Math.max(0, i.amount - collected);
          const pct = i.amount ? Math.min(100, Math.round((collected / i.amount) * 100)) : 0;
          return (
            <div key={i.id} className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-card">
              <div className="flex items-center justify-between">
                <div><b className="text-navy-900" dir="ltr">{i.number}</b> <span className="text-sm text-slate-500">{i.client?.name}</span></div>
                <Status v={i.status} />
              </div>
              <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                <div className="h-full rounded-full bg-gradient-to-l from-brand-500 to-accent-500" style={{ width: `${pct}%` }} />
              </div>
              <div className="mt-2 flex gap-5 text-sm">
                <span className="text-slate-500">مفوتر: <b className="text-navy-900">{g(i.amount)}</b></span>
                <span className="text-slate-500">محصّل: <b className="text-emerald-600">{g(collected)}</b></span>
                <span className="text-slate-500">متبقي: <b className="text-accent-600">{g(recv)}</b></span>
              </div>
              {recv > 0 && can("payments.record") && (
                <div className="mt-3 flex gap-2 border-t border-slate-100 pt-3">
                  <Input className="w-36 !py-1 text-xs" placeholder="المبلغ" value={pay[i.id] ?? ""} onChange={(e) => setPay({ ...pay, [i.id]: e.target.value })} />
                  <Btn v="accent" className="!py-1 text-xs" onClick={() => collect(i.id)}>تسجيل تحصيل</Btn>
                </div>
              )}
            </div>
          );
        })}
        {items.length === 0 && <Empty text="لا توجد فواتير بعد." />}
      </div>
    </Page>
  );
}
