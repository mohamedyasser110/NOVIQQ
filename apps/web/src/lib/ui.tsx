"use client";
import React from "react";
import { ar } from "./ar";

/* ---------- layout primitives ---------- */
export function Page({ title, sub, children }: { title: string; sub?: string; children: React.ReactNode }) {
  return (
    <div>
      <h1 className="text-2xl font-extrabold text-navy-900">{title}</h1>
      {sub && <p className="mb-4 mt-1 text-sm text-slate-500">{sub}</p>}
      {!sub && <div className="mb-4" />}
      {children}
    </div>
  );
}

export function Card({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return <div className={`rounded-2xl border border-slate-200/80 bg-white p-4 shadow-card ${className}`}>{children}</div>;
}

export function Err({ msg }: { msg: string | null }) {
  if (!msg) return null;
  return <div className="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{msg}</div>;
}

export function Ok({ msg }: { msg: string | null }) {
  if (!msg) return null;
  return <div className="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{msg}</div>;
}

export function Empty({ text }: { text: string }) {
  return <div className="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-6 text-center text-sm text-slate-500">{text}</div>;
}

/* ---------- controls ---------- */
type BtnVar = "primary" | "accent" | "ghost" | "danger" | "dark";
const BTN: Record<BtnVar, string> = {
  primary: "bg-brand-500 text-white hover:bg-brand-600",
  accent: "bg-accent-500 text-white hover:bg-accent-600",
  ghost: "bg-slate-100 text-navy-900 hover:bg-slate-200",
  danger: "bg-red-500 text-white hover:bg-red-600",
  dark: "bg-navy-900 text-white hover:bg-navy-800",
};

export function Btn({ v = "ghost", className = "", ...rest }: React.ButtonHTMLAttributes<HTMLButtonElement> & { v?: BtnVar }) {
  return <button {...rest} className={`rounded-xl px-3 py-1.5 text-sm font-bold shadow-sm transition ${BTN[v]} ${className}`} />;
}

export function Input(props: React.InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={`rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-sm text-navy-900 placeholder:text-slate-400 ${props.className ?? ""}`} />;
}

export function Select(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return <select {...props} className={`rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-sm text-navy-900 ${props.className ?? ""}`} />;
}

/* ---------- data display ---------- */
const PILL: Record<string, string> = {
  green: "bg-emerald-50 text-emerald-700 border-emerald-200",
  amber: "bg-amber-50 text-amber-700 border-amber-200",
  red: "bg-red-50 text-red-700 border-red-200",
  blue: "bg-brand-50 text-brand-700 border-brand-100",
  slate: "bg-slate-100 text-slate-600 border-slate-200",
  orange: "bg-accent-50 text-accent-600 border-accent-100",
};

export function Pill({ c = "slate", children }: { c?: keyof typeof PILL; children: React.ReactNode }) {
  return <span className={`inline-block rounded-full border px-2.5 py-0.5 text-xs font-bold ${PILL[c]}`}>{children}</span>;
}

/** Status value (English DB enum) → colored Arabic pill. */
export function Status({ v }: { v: string }) {
  const s = String(v);
  const green = ["DONE", "PAID", "ACTIVE", "APPROVED", "ISSUED", "SIGNED", "EARNED"].includes(s);
  const amber = ["IN_REVIEW", "IN_PROGRESS", "PARTIAL", "SUBMITTED", "PENDING", "REQUESTED", "ALLOCATED", "PLANNING"].includes(s);
  const red = ["BLOCKED", "OVERDUE", "REJECTED", "CANCELLED"].includes(s);
  const blue = ["TODO", "BACKLOG", "SIGNED"].includes(s);
  const c = green ? "green" : amber ? "amber" : red ? "red" : blue ? "blue" : "slate";
  return <Pill c={c as any}>{ar(s)}</Pill>;
}

export function Stat({ label, value, note, accent = false, icon, tone = "navy", bar }: { label: string; value: string; note?: string; accent?: boolean; icon?: React.ReactNode; tone?: "navy" | "accent" | "green" | "blue"; bar?: number }) {
  const tones: Record<string, string> = {
    navy: "from-navy-900 to-brand-500",
    accent: "from-accent-400 to-accent-600",
    green: "from-emerald-400 to-emerald-600",
    blue: "from-sky-400 to-brand-600",
  };
  return (
    <div className="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-4 shadow-card transition duration-200 hover:-translate-y-1 hover:shadow-xl">
      <div className={`pointer-events-none absolute -end-8 -top-8 h-28 w-28 rounded-full bg-gradient-to-br ${tones[tone]} opacity-[0.07] transition duration-200 group-hover:opacity-[0.14]`} />
      <div className="flex items-start justify-between gap-2">
        <div>
          <div className="text-xs font-bold uppercase tracking-wide text-slate-400">{label}</div>
          <div className={`mt-1 text-[1.7rem] font-black leading-none ${accent ? "text-accent-600" : "text-navy-900"}`}>{value}</div>
          {note && <div className="mt-1.5 text-xs text-slate-400">{note}</div>}
        </div>
        {icon && <span className={`rounded-2xl bg-gradient-to-br p-2.5 text-white shadow ${tones[tone]}`}>{icon}</span>}
      </div>
      {typeof bar === "number" && (
        <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
          <div className={`h-full rounded-full bg-gradient-to-l ${tones[tone]} transition-all`} style={{ width: `${Math.max(0, Math.min(100, bar))}%` }} />
        </div>
      )}
    </div>
  );
}

export function Table({ head, children }: { head: string[]; children: React.ReactNode }) {
  return (
    <div className="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-card">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-slate-200 bg-slate-50 text-slate-500">
            {head.map((h) => (
              <th key={h} className="px-4 py-2.5 text-start text-xs font-bold">{h}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">{children}</tbody>
      </table>
    </div>
  );
}

export function Td({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return <td className={`px-4 py-2.5 text-navy-900 ${className}`}>{children}</td>;
}
