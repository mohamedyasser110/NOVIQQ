"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { useMe } from "../../lib/use-me";
import { g } from "../../lib/ar";
import { Page, Card, Btn, Input, Err, Empty, Status } from "../../lib/ui";

export default function PayoutsPage() {
  const { can } = useMe();
  const [payouts, setPayouts] = useState<any[]>([]);
  const [approvals, setApprovals] = useState<any[]>([]);
  const [err, setErr] = useState<string | null>(null);
  const [ref, setRef] = useState<Record<string, string>>({});

  async function load() {
    try {
      const [p, a] = await Promise.all([api("/payouts"), api("/approvals?state=PENDING")]);
      setPayouts(Array.isArray(p) ? p : []);
      setApprovals(Array.isArray(a) ? a : []);
    } catch (e: any) { setErr(e.message); }
  }
  useEffect(() => { load(); }, []);

  async function act(id: string, action: string) {
    setErr(null);
    try {
      const body: any = {};
      if (action === "mark-paid") {
        const r = ref[id]?.trim() || prompt("مرجع الدفع (إجباري):");
        if (!r?.trim()) return;
        body.reference = r.trim();
      }
      await api(`/payouts/${id}/${action}`, { method: "POST", body: JSON.stringify(body) });
      await load();
    } catch (e: any) { setErr(e.message); }
  }

  async function decide(id: string, decision: string) {
    setErr(null);
    try {
      const comment = prompt(`تعليق على ${decision === "APPROVED" ? "القبول" : "الرفض"} (اختياري):`) ?? undefined;
      await api(`/approvals/${id}/decide`, { method: "POST", body: JSON.stringify({ decision, comment }) });
      await load();
    } catch (e: any) { setErr(e.message); }
  }

  return (
    <Page title="الصرف والاعتمادات" sub="طلب ← اعتماد (الطالب لا يعتمد طلبه) ← تعليم كمدفوع">
      <Err msg={err} />
      <h2 className="mb-2 text-base font-black text-navy-900">المدفوعات ({payouts.length})</h2>
      <div className="mb-6 grid gap-2">
        {payouts.map((p) => (
          <Card key={p.id} className="!p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div><b className="text-navy-900">{g(p.amount)} {p.currency}</b> <span className="text-sm text-slate-500">← {p.payee?.name ?? p.payeeId} · {p.project?.code ?? ""} · <span dir="ltr">{p.reference}</span></span> <Status v={p.status} /></div>
              <div className="flex gap-2">
                {p.status === "REQUESTED" && can("finance.approve_payout") && <Btn v="accent" className="!py-1 text-xs" onClick={() => act(p.id, "approve")}>اعتماد</Btn>}
                {p.status === "APPROVED" && can("finance.execute_payout") && (
                  <span className="flex gap-2">
                    <Input className="w-36 !py-1 text-xs" placeholder="مرجع الدفع" value={ref[p.id] ?? ""} onChange={(e) => setRef({ ...ref, [p.id]: e.target.value })} />
                    <Btn className="!py-1 text-xs" onClick={() => act(p.id, "mark-paid")}>تعليم كمدفوع</Btn>
                  </span>
                )}
              </div>
            </div>
          </Card>
        ))}
        {payouts.length === 0 && <Empty text="لا توجد مدفوعات بعد." />}
      </div>

      <h2 className="mb-2 text-base font-black text-navy-900">اعتمادات معلقة ({approvals.length})</h2>
      <div className="grid gap-2">
        {approvals.map((a) => (
          <Card key={a.id} className="!p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div className="text-sm"><b className="text-navy-900">{a.action}</b> <span className="text-slate-500">{a.entityType} · بواسطة {a.requester?.name ?? a.requesterId}{a.reason ? ` · ${a.reason}` : ""}</span></div>
              <div className="flex gap-2">
                <Btn v="accent" className="!py-1 text-xs" onClick={() => decide(a.id, "APPROVED")}>قبول</Btn>
                <Btn className="!py-1 text-xs" onClick={() => decide(a.id, "REJECTED")}>رفض</Btn>
              </div>
            </div>
          </Card>
        ))}
        {approvals.length === 0 && <Empty text="الطابور فارغ." />}
      </div>
    </Page>
  );
}
