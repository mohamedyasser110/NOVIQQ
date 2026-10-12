"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { Page, Card, Btn, Input, Err, Empty, Status } from "../../lib/ui";

export default function ProjectsPage() {
  const [items, setItems] = useState<any[]>([]);
  const [code, setCode] = useState("");
  const [name, setName] = useState("");
  const [err, setErr] = useState<string | null>(null);

  async function load() {
    try { const r = await api("/projects"); setItems(Array.isArray(r) ? r : []); } catch (e: any) { setErr(e.message); }
  }
  useEffect(() => { load(); }, []);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    setErr(null);
    try {
      await api("/projects", { method: "POST", body: JSON.stringify({ code, name }) });
      setCode(""); setName(""); await load();
    } catch (e: any) { setErr(e.message); }
  }

  return (
    <Page title="المشاريع" sub={`${items.length} مشروع`}>
      <Err msg={err} />
      <Card className="mb-3">
        <form onSubmit={create} className="flex flex-col gap-2 sm:flex-row">
          <Input value={code} onChange={(e) => setCode(e.target.value)} placeholder="الكود e.g. WEB-002" dir="ltr" required />
          <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="اسم المشروع" className="sm:min-w-52 sm:flex-1" required />
          <Btn v="accent" type="submit">إنشاء مشروع</Btn>
        </form>
      </Card>
      <div className="grid gap-2">
        {items.map((p) => (
          <Card key={p.id} className="!p-3">
            <div className="flex items-center justify-between">
              <div><b className="text-navy-900" dir="ltr">{p.code}</b> <span className="text-slate-600">— {p.name}</span></div>
              <Status v={p.status} />
            </div>
          </Card>
        ))}
        {items.length === 0 && <Empty text="لا توجد مشاريع بعد — أنشئ أول واحد من فوق." />}
      </div>
    </Page>
  );
}
