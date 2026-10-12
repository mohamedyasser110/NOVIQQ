"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { fmtDate } from "../../lib/ar";
import { Page, Err, Select, Status } from "../../lib/ui";

export default function TimelinePage() {
  const [projects, setProjects] = useState<any[]>([]);
  const [pid, setPid] = useState("");
  const [tasks, setTasks] = useState<any[]>([]);
  const [overdue, setOverdue] = useState<any[]>([]);
  const [upcoming, setUpcoming] = useState<any[]>([]);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const [p, o, u] = await Promise.all([api("/projects"), api("/timeline/overdue"), api("/timeline/upcoming?days=30")]);
        setProjects(Array.isArray(p) ? p : []);
        setOverdue(Array.isArray(o) ? o : []);
        setUpcoming(Array.isArray(u) ? u : []);
      } catch (e: any) { setErr(e.message); }
    })();
  }, []);

  useEffect(() => {
    if (!pid) { setTasks([]); return; }
    api(`/timeline/tasks?projectId=${pid}`).then((t) => setTasks(Array.isArray(t) ? t : [])).catch((e: any) => setErr(e.message));
  }, [pid]);

  const row = (t: any, tone: string) => (
    <div key={t.id} className={`flex flex-wrap items-center justify-between gap-2 rounded-2xl border bg-white p-3 text-sm shadow-card ${tone}`}>
      <div><b className="text-navy-900">{t.title}</b> <Status v={t.status} /></div>
      <div className="text-xs text-slate-500">التسليم: {t.dueDate ? fmtDate(t.dueDate) : "—"}</div>
    </div>
  );

  return (
    <Page title="التقويم والخط الزمني">
      <Err msg={err} />
      <h2 className="mb-2 mt-2 text-base font-black text-red-600">متأخر ({overdue.length})</h2>
      <div className="grid gap-2">{overdue.map((t) => row(t, "border-red-200"))}</div>

      <h2 className="mb-2 mt-6 text-base font-black text-amber-600">القادم خلال 30 يوم ({upcoming.length})</h2>
      <div className="grid gap-2">{upcoming.map((t) => row(t, "border-amber-200"))}</div>

      <h2 className="mb-2 mt-6 text-base font-black text-navy-900">مهام حسب المشروع</h2>
      <Select className="mb-3" value={pid} onChange={(e) => setPid(e.target.value)}>
        <option value="">اختر مشروعًا…</option>
        {projects.map((p) => <option key={p.id} value={p.id}>{p.code} — {p.name}</option>)}
      </Select>
      <div className="grid gap-2">{tasks.map((t) => row(t, "border-slate-200/80"))}</div>
    </Page>
  );
}
