"use client";
import { useEffect, useState } from "react";
import { api } from "../lib/client";
import { g } from "../lib/ar";
import { Icon } from "../lib/icons";
import { Page, Stat, Err } from "../lib/ui";

export default function Dashboard() {
  const [d, setD] = useState<any>(null);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const [projects, overdue, recv, cash, approvals, tasks, invoices, expenses] = await Promise.all([
          api("/projects"),
          api("/timeline/overdue"),
          api("/reports/outstanding-receivables"),
          api("/treasury/cash-position"),
          api("/approvals?state=PENDING"),
          api("/tasks"),
          api("/invoices"),
          api("/expenses"),
        ]);
        const t = Array.isArray(tasks) ? tasks : [];
        const done = t.filter((x: any) => x.status === "DONE").length;
        const inv = Array.isArray(invoices) ? invoices : [];
        const exp = Array.isArray(expenses) ? expenses : [];
        setD({
          active: (Array.isArray(projects) ? projects : []).filter((p: any) => p.status === "ACTIVE").length,
          totalProjects: (Array.isArray(projects) ? projects : []).length,
          overdue: (Array.isArray(overdue) ? overdue : []).length,
          receivable: (Array.isArray(recv) ? recv : []).reduce((s: number, r: any) => s + (r.receivable ?? 0), 0),
          cash: cash?.cash ?? 0,
          pending: (Array.isArray(approvals) ? approvals : []).length,
          totalTasks: t.length,
          doneRate: t.length ? Math.round((done / t.length) * 100) : 0,
          invoiced: inv.reduce((s: number, x: any) => s + (x.amount ?? 0), 0),
          expenses: exp.reduce((s: number, x: any) => s + (x.amount ?? 0), 0),
        });
      } catch (e: any) { setErr(e.message); }
    })();
  }, []);

  const collectRate = d && d.invoiced ? Math.round(((d.invoiced - d.receivable) / d.invoiced) * 100) : 0;
  const cards = d ? [
    { k: "المشاريع النشطة", v: `${d.active} / ${d.totalProjects}`, note: "من المشاريع", icon: <Icon name="projects" size={22} />, tone: "navy" },
    { k: "مهام متأخرة", v: String(d.overdue), note: "فات ميعادها ولسه مخلصتش", icon: <Icon name="clock" size={22} />, tone: "accent" },
    { k: "مستحقات (جنيه)", v: g(d.receivable), note: `مفوتر − محصّل · التحصيل ${collectRate}٪`, accent: true, icon: <Icon name="wallet" size={22} />, tone: "accent", bar: collectRate },
    { k: "كاش الخزنة (جنيه)", v: g(d.cash), note: "دخل − مصروف/صرف", icon: <Icon name="treasury" size={22} />, tone: "green" },
    { k: "اعتمادات معلقة", v: String(d.pending), note: "مستنية قرار", icon: <Icon name="pen" size={22} />, tone: "blue" },
    { k: "إجمالي المهام", v: String(d.totalTasks), note: "كل الحالات", icon: <Icon name="tasks" size={22} />, tone: "blue" },
    { k: "نسبة الإنجاز", v: `${d.doneRate}٪`, note: "مهام منجزة من الكل", icon: <Icon name="check" size={22} />, tone: "green", bar: d.doneRate },
    { k: "إجمالي المفوتر (جنيه)", v: g(d.invoiced), note: "كل الفواتير", icon: <Icon name="invoices" size={22} />, tone: "navy" },
    { k: "إجمالي المصاريف (جنيه)", v: g(d.expenses), note: "كل المصاريف", icon: <Icon name="cash" size={22} />, tone: "accent" },
  ] : [];

  return (
    <Page title="لوحة التحكم">
      <Err msg={err} />
      {d && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {cards.map((c: any) => (
            <Stat key={c.k} label={c.k} value={c.v} note={c.note} accent={c.accent} icon={c.icon} tone={c.tone} bar={c.bar} />
          ))}
        </div>
      )}
    </Page>
  );
}
