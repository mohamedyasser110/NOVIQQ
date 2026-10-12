"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { Page, Err, Empty, Pill, Table, Td } from "../../lib/ui";

const ROLE_AR: Record<string, string> = {
  FOUNDER_ADMIN: "مدير عام", PROJECT_MANAGER: "مدير مشروع", EMPLOYEE: "موظف", FINANCE_MANAGER: "مدير مالي", VIEWER: "مشاهد",
};

export default function TeamPage() {
  const [items, setItems] = useState<any[]>([]);
  const [err, setErr] = useState<string | null>(null);

  async function load() {
    try { const u = await api("/users"); setItems(Array.isArray(u) ? u : []); }
    catch (e: any) { setErr(e.message); }
  }
  useEffect(() => { load(); }, []);

  return (
    <Page title="الفريق" sub={`${items.length} عضو · الأدوار من الـ API`}>
      <Err msg={err} />
      {items.length > 0 ? (
        <Table head={["العضو", "الإيميل", "الدور", "الحالة"]}>
          {items.map((u) => (
            <tr key={u.id} className="hover:bg-brand-50/50">
              <Td className="font-bold">{u.name}</Td>
              <Td><span dir="ltr" className="text-slate-500">{u.email}</span></Td>
              <Td>
                <div className="flex gap-1">
                  {(u.roles ?? []).map((r: any) => (
                    <Pill key={r.role.key} c="blue">{ROLE_AR[r.role.key] ?? r.role.key}</Pill>
                  ))}
                </div>
              </Td>
              <Td>{u.isActive ? <Pill c="green">نشط</Pill> : <Pill c="red">موقوف</Pill>}</Td>
            </tr>
          ))}
        </Table>
      ) : !err ? <div className="text-sm text-slate-500">جاري التحميل…</div> : <Empty text="لا يمكن عرض الفريق — تحتاج صلاحية." />}
    </Page>
  );
}
