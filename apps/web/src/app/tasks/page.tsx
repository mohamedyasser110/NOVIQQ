"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { ar } from "../../lib/ar";
import { Page, Btn, Err, Empty, Status, Pill } from "../../lib/ui";

const NEXT: Record<string, string> = { BACKLOG: "TODO", TODO: "IN_PROGRESS", IN_PROGRESS: "IN_REVIEW", IN_REVIEW: "DONE" };
const FILTERS = ["", "BACKLOG", "TODO", "IN_PROGRESS", "IN_REVIEW", "DONE"];
const FILTER_AR: Record<string, string> = { "": "الكل", BACKLOG: "متراكم", TODO: "جديد", IN_PROGRESS: "جارٍ", IN_REVIEW: "قيد المراجعة", DONE: "منجز" };

export default function TasksPage() {
  const [items, setItems] = useState<any[]>([]);
  const [status, setStatus] = useState("");
  const [err, setErr] = useState<string | null>(null);

  async function load(s = status) {
    try { const r = await api(`/tasks${s ? `?status=${s}` : ""}`); setItems(Array.isArray(r) ? r : []); }
    catch (e: any) { setErr(e.message); }
  }
  useEffect(() => { load(); }, []);

  async function advance(id: string, cur: string) {
    const nx = NEXT[cur];
    if (!nx) return;
    setErr(null);
    try {
      await api(`/tasks/${id}`, { method: "PATCH", body: JSON.stringify({ status: nx }) });
      await load();
    } catch (e: any) { setErr(e.message); }
  }

  return (
    <Page title="المهام" sub={`${items.length} مهمة`}>
      <div className="mb-3 flex flex-wrap gap-1.5">
        {FILTERS.map((s) => (
          <button key={s || "ALL"} onClick={() => { setStatus(s); load(s); }}
            className={`rounded-full px-3 py-1 text-xs font-bold transition ${status === s ? "bg-navy-900 text-white shadow" : "bg-white text-slate-600 border border-slate-200 hover:border-navy-900"}`}>
            {FILTER_AR[s]}
          </button>
        ))}
      </div>
      <Err msg={err} />
      <div className="grid gap-2">
        {items.map((t) => (
          <div key={t.id} className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200/80 bg-white p-3 text-sm shadow-card">
            <div>
              <b className="text-navy-900">{t.title}</b>
              <div className="mt-1 flex gap-1"><Status v={t.status} /><Pill c="slate">{ar(t.priority)}</Pill></div>
            </div>
            {NEXT[t.status] && <Btn v="primary" onClick={() => advance(t.id, t.status)}>← {ar(NEXT[t.status])}</Btn>}
          </div>
        ))}
        {items.length === 0 && <Empty text="لا توجد مهام في هذا الفلتر." />}
      </div>
    </Page>
  );
}
