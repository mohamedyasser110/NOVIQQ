"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { useMe } from "../../lib/use-me";
import { g, ar } from "../../lib/ar";
import { Page, Card, Btn, Input, Select, Err, Empty, Status } from "../../lib/ui";

type Line = { userId: string; percent: string };

export default function AllocationsPage() {
  const { can } = useMe();
  const [projects, setProjects] = useState<any[]>([]);
  const [users, setUsers] = useState<any[]>([]);
  const [pid, setPid] = useState("");
  const [plans, setPlans] = useState<any[]>([]);
  const [err, setErr] = useState<string | null>(null);
  const [basis, setBasis] = useState("");
  const [lines, setLines] = useState<Line[]>([{ userId: "", percent: "" }]);

  useEffect(() => {
    Promise.all([api("/projects"), api("/users")])
      .then(([p, u]) => { setProjects(Array.isArray(p) ? p : []); setUsers(Array.isArray(u) ? u : []); })
      .catch((e: any) => setErr(e.message));
  }, []);

  async function loadPlans(id: string) {
    try { const r = await api(`/allocation-plans?projectId=${id}`); setPlans(Array.isArray(r) ? r : []); }
    catch (e: any) { setErr(e.message); }
  }

  function pick(id: string) { setPid(id); setPlans([]); if (id) loadPlans(id); }

  async function act(id: string, action: string) {
    setErr(null);
    try {
      let body: any = {};
      if (action === "supersede") {
        const reason = prompt("سبب الاستبدال (إجباري):");
        if (!reason?.trim()) return;
        body = { reason };
      }
      await api(`/allocation-plans/${id}/${action}`, { method: "POST", body: JSON.stringify(body) });
      await loadPlans(pid);
    } catch (e: any) { setErr(e.message); }
  }

  async function create(e: React.FormEvent) {
    e.preventDefault(); setErr(null);
    const b = Number(basis);
    if (!pid) return setErr("اختر المشروع");
    if (!(b > 0)) return setErr("اكتب أساسًا موجبًا (بالجنيه)");
    const ls = lines.filter((l) => l.userId && Number(l.percent) > 0).map((l) => ({ userId: l.userId, percent: Number(l.percent) }));
    if (!ls.length) return setErr("أضف سطرًا واحدًا على الأقل بنسبة مئوية");
    try {
      await api("/allocation-plans", { method: "POST", body: JSON.stringify({ projectId: pid, basis: Math.round(b * 100), method: "HYBRID", lines: ls }) });
      setBasis(""); setLines([{ userId: "", percent: "" }]);
      await loadPlans(pid);
    } catch (e: any) { setErr(e.message); }
  }

  return (
    <Page title="توزيع الأرباح" sub="مسودة ← تقديم ← اعتماد ← استبدال (السجل محفوظ)">
      <Err msg={err} />
      <Select className="mb-3" value={pid} onChange={(e) => pick(e.target.value)}>
        <option value="">اختر المشروع…</option>
        {projects.map((p) => <option key={p.id} value={p.id}>{p.code} — {p.name}</option>)}
      </Select>

      {pid && can("allocations.create") && (
        <Card className="mb-3 border-accent-100">
          <div className="mb-2 font-black text-navy-900">خطة جديدة <span className="text-xs font-semibold text-slate-400">(الأساس بالجنيه · النسب ≤ 100)</span></div>
          <form onSubmit={create}>
            <div className="mb-2 flex flex-wrap gap-2">
              <Input className="w-40" placeholder="الأساس بالجنيه" value={basis} onChange={(e) => setBasis(e.target.value)} />
              <Btn v="accent" type="submit">إنشاء مسودة</Btn>
              <Btn type="button" onClick={() => setLines([...lines, { userId: "", percent: "" }])}>+ سطر</Btn>
            </div>
            {lines.map((l, i) => (
              <div key={i} className="mb-1 flex gap-2">
                <Select value={l.userId} onChange={(e) => { const c = [...lines]; c[i].userId = e.target.value; setLines(c); }}>
                  <option value="">العضو…</option>
                  {users.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}
                </Select>
                <Input className="w-24" placeholder="٪" value={l.percent} onChange={(e) => { const c = [...lines]; c[i].percent = e.target.value; setLines(c); }} />
              </div>
            ))}
          </form>
        </Card>
      )}

      <div className="grid gap-2">
        {plans.map((p) => (
          <Card key={p.id} className="!p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div><b className="text-navy-900">{g(p.basis)} جنيه</b> <span className="text-xs text-slate-500">[{ar(p.method)}]</span> <Status v={p.status} />{p.reason ? <span className="text-xs text-slate-400"> · {p.reason}</span> : null}</div>
              <div className="flex gap-2">
                {p.status === "DRAFT" && can("allocations.create") && <Btn className="!py-1 text-xs" onClick={() => act(p.id, "submit")}>تقديم</Btn>}
                {p.status === "SUBMITTED" && can("allocations.approve") && <Btn v="accent" className="!py-1 text-xs" onClick={() => act(p.id, "approve")}>اعتماد</Btn>}
                {(p.status === "APPROVED" || p.status === "SUBMITTED") && can("allocations.edit") && <Btn className="!py-1 text-xs" onClick={() => act(p.id, "supersede")}>استبدال</Btn>}
              </div>
            </div>
            {(p.versions ?? []).map((v: any) => (
              <div key={v.id} className="mt-1 text-xs text-slate-500">
                إصدار {v.version} <Status v={v.status} />: {(v.lines ?? []).map((l: any) => `${g(l.amount)}`).join(" · ")}
              </div>
            ))}
          </Card>
        ))}
        {pid && plans.length === 0 && <Empty text="لا توجد خطط لهذا المشروع بعد." />}
      </div>
    </Page>
  );
}
