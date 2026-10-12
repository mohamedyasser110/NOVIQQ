"use client";
import { useEffect, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import AuthGuard from "./auth-guard";
import { Icon } from "../lib/icons";

const NAV: { group: string; links: { href: string; label: string; icon: string }[] }[] = [
  { group: "الشغل", links: [{ href: "/", label: "لوحة التحكم", icon: "dashboard" }, { href: "/projects", label: "المشاريع", icon: "projects" }, { href: "/tasks", label: "المهام / كانبان", icon: "tasks" }, { href: "/timeline", label: "التقويم والخط الزمني", icon: "timeline" }, { href: "/team", label: "الفريق", icon: "team" }] },
  { group: "المالية", links: [{ href: "/clients", label: "العملاء والتعاقدات", icon: "clients" }, { href: "/invoices", label: "الفواتير والتحصيل", icon: "invoices" }, { href: "/allocations", label: "توزيع الأرباح", icon: "allocations" }, { href: "/treasury", label: "الخزنة", icon: "treasury" }, { href: "/payouts", label: "الصرف والاعتمادات", icon: "payouts" }] },
  { group: "النظام", links: [{ href: "/reports", label: "التقارير", icon: "reports" }, { href: "/audit", label: "سجل التدقيق", icon: "audit" }, { href: "/settings", label: "الإعدادات", icon: "settings" }, { href: "/account", label: "حسابي", icon: "user" }] },
];

function Brand() {
  return (
    <div dir="ltr" className="flex items-center gap-1.5">
      <img src="/logo.png" alt="noviq solutions" className="h-9 w-9 object-contain drop-shadow-[0_0_12px_rgba(249,115,22,0.35)]" />
      <div className="text-[22px] font-black leading-none tracking-tight text-white">
        noviq <span className="text-accent-500">solutions</span>
      </div>
    </div>
  );
}

/** App shell: /login renders standalone on a navy gradient.
 *  Every other page gets the responsive navy sidebar + light topbar. */
export default function Shell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [email, setEmail] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(false);

  useEffect(() => {
    try {
      const raw = localStorage.getItem("cos_user");
      setEmail(raw ? (JSON.parse(raw).email ?? null) : null);
      setCollapsed(localStorage.getItem("noviq_sb") === "1");
    } catch { setEmail(null); }
  }, [pathname]);

  function toggle() {
    setCollapsed((c) => {
      try { localStorage.setItem("noviq_sb", c ? "0" : "1"); } catch { /* ignore */ }
      return !c;
    });
  }

  useEffect(() => { setOpen(false); }, [pathname]);

  function logout() {
    localStorage.removeItem("cos_token");
    localStorage.removeItem("cos_user");
    router.push("/login");
  }

  if (pathname === "/login") {
    return (
      <div
        className="flex min-h-screen items-center justify-center p-4 sm:p-8"
        style={{ background: "radial-gradient(1000px 600px at 85% 10%, rgba(249,115,22,.22), transparent 60%), radial-gradient(900px 600px at 10% 90%, rgba(29,78,216,.4), transparent 60%), linear-gradient(160deg, #060F2B, #0B1F4D 60%, #122A63)" }}
      >
        <div className="w-full max-w-4xl">{children}</div>
      </div>
    );
  }

  const today = new Date().toLocaleDateString("ar-EG", { weekday: "long", day: "numeric", month: "long", year: "numeric" });

  const menu = (
    <>
      <div className="mb-6 flex justify-center">
        {collapsed ? (
          <img src="/logo.png" alt="noviq" className="h-9 w-9 object-contain" />
        ) : (
          <Brand />
        )}
      </div>
      {NAV.map((g) => (
        <div key={g.group} className="mb-4">
          {!collapsed && <div className="mb-1 px-2 text-[11px] font-bold uppercase tracking-wider text-white/40">{g.group}</div>}
          {g.links.map((l) => {
            const active = pathname === l.href;
            return (
              <a
                key={l.href + l.label} href={l.href} title={collapsed ? l.label : undefined}
                className={`mb-0.5 flex items-center gap-2.5 rounded-lg border-s-4 px-2 py-1.5 text-sm transition ${collapsed ? "justify-center" : ""} ${active ? "border-accent-500 bg-white/15 font-bold text-white" : "border-transparent text-white/70 hover:bg-white/10 hover:text-white"}`}
              >
                <span className={active ? "text-accent-400" : "text-white/50"}><Icon name={l.icon} size={18} /></span>
                {!collapsed && l.label}
              </a>
            );
          })}
        </div>
      ))}
      <div className="mt-auto pt-4 text-center text-[11px] text-white/30">{collapsed ? "n" : "noviq solutions"}</div>
    </>
  );

  return (
    <div className="flex min-h-screen">
      {/* desktop sidebar (collapsible to icons-only) */}
      <aside className={`hidden shrink-0 flex-col bg-navy-950 p-3 text-white transition-all duration-200 md:flex ${collapsed ? "w-[4.5rem]" : "w-60"}`}>{menu}</aside>
      {/* mobile drawer */}
      {open && (
        <div className="fixed inset-0 z-40 md:hidden">
          <div className="absolute inset-0 bg-navy-950/60" onClick={() => setOpen(false)} />
          <aside className="absolute bottom-0 top-0 start-0 flex w-64 flex-col overflow-y-auto bg-navy-950 p-4 text-white shadow-2xl">{menu}</aside>
        </div>
      )}
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between gap-2 border-b border-slate-200/80 bg-white/80 px-4 py-3 backdrop-blur sm:px-6">
          <div className="flex items-center gap-2">
            <button onClick={() => setOpen(true)} className="rounded-lg bg-slate-100 p-2 text-navy-900 md:hidden" aria-label="القائمة"><Icon name="menu" size={18} /></button>
            <button onClick={toggle} title={collapsed ? "فتح القائمة" : "طي القائمة"} className="hidden rounded-lg bg-slate-100 p-2 text-navy-900 transition hover:bg-slate-200 md:block" aria-label="طي القائمة">
              <span className={`block transition-transform ${collapsed ? "rotate-180" : ""}`}><Icon name="chev" size={18} /></span>
            </button>
            <div className="text-xs font-semibold text-slate-500 sm:text-sm">{today}</div>
          </div>
          {email && (
            <div className="flex min-w-0 items-center gap-2 text-sm">
              <span className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-navy-900 font-black text-white">{email.charAt(0).toUpperCase()}</span>
              <span className="hidden truncate font-semibold text-navy-900 sm:inline" dir="ltr">{email}</span>
              <button onClick={logout} className="rounded-xl bg-red-50 px-3 py-1.5 text-xs font-black text-red-600 transition hover:bg-red-500 hover:text-white">
                <span className="hidden sm:inline">تسجيل الخروج</span><span className="sm:hidden">خروج</span>
              </button>
            </div>
          )}
        </header>
        <main className="flex-1 p-4 sm:p-6">{children}</main>
      </div>
    </div>
  );
}

export { AuthGuard };
