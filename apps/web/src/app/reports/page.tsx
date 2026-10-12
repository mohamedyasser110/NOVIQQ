"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { g } from "../../lib/ar";
import { Page, Err, Select, Stat, Table, Td, Empty } from "../../lib/ui";

export default function ReportsPage() {
  const [projects, setProjects] = useState<any[]>([]);
  const [pid, setPid] = useState("");
  const [fin, setFin] = useState<any>(null);
  const [recv, setRecv] = useState<any[]>([]);
  const [cash, setCash] = useState<any>(null);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    Promise.all([api("/projects"), api("/reports/outstanding-receivables"), api("/reports/cash-flow")])
      .then(([p, r, c]) => {
        const list = Array.isArray(p) ? p : [];
        setProjects(list); setRecv(Array.isArray(r) ? r : []); setCash(c);
        if (list.length && !pid) { setPid(list[0].id); loadFin(list[0].id); }
      })
      .catch((e: any) => setErr(e.message));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function loadFin(id: string) {
    try { setFin(await api(`/reports/project-financial/${id}`)); }
    catch (e: any) { setErr(e.message); }
  }

  function pick(id: string) { setPid(id); setFin(null); if (id) loadFin(id); }

  return (
    <Page title="التقارير">
      <Err msg={err} />
      {cash && (
        <div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
          <Stat label="الداخل" value={`${g(cash.inflow)} جنيه`} />
          <Stat label="الخارج" value={`${g(cash.outflow)} جنيه`} />
          <Stat label="الصافي" value={`${g(cash.net)} جنيه`} accent />
        </div>
      )}

      <h2 className="mb-2 text-base font-black text-navy-900">مالية المشروع</h2>
      <Select className="mb-3" value={pid} onChange={(e) => pick(e.target.value)}>
        {projects.map((p) => <option key={p.id} value={p.id}>{p.code} — {p.name}</option>)}
      </Select>
      {fin && (
        <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
          {[["التعاقد", fin.contractValue], ["مفوتر", fin.invoiced], ["محصّل", fin.collected], ["متبقي", fin.receivable], ["مصاريف", fin.expenses], ["مستحقات معتمدة", fin.approvedCompensation], ["مستحقات مدفوعة", fin.paidCompensation], ["الهامش", fin.margin]].map(([k, v]) => (
            <Stat key={k as string} label={k as string} value={g(v as number)} />
          ))}
        </div>
      )}

      <h2 className="mb-2 text-base font-black text-navy-900">مستحقات معلقة ({recv.length})</h2>
      {recv.length > 0 ? (
        <Table head={["الفاتورة", "العميل", "مفوتر", "محصّل", "عليه"]}>
          {recv.map((r) => (
            <tr key={r.number} className="hover:bg-brand-50/50">
              <Td className="font-bold" ><span dir="ltr">{r.number}</span></Td>
              <Td>{r.client}</Td>
              <Td>{g(r.amount)}</Td>
              <Td className="font-bold text-emerald-600">{g(r.collected)}</Td>
              <Td className="font-black text-accent-600">{g(r.receivable)}</Td>
            </tr>
          ))}
        </Table>
      ) : <Empty text="لا يوجد شيء معلق." />}
    </Page>
  );
}
