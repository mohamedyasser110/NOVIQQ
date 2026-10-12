"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { useMe } from "../../lib/use-me";
import { g } from "../../lib/ar";
import { Page, Card, Btn, Input, Select, Err, Status, Table, Td } from "../../lib/ui";

export default function ClientsPage() {
  const { can } = useMe();
  const [clients, setClients] = useState<any[]>([]);
  const [contracts, setContracts] = useState<any[]>([]);
  const [err, setErr] = useState<string | null>(null);
  const [name, setName] = useState("");
  const [contact, setContact] = useState("");
  const [ref, setRef] = useState("");
  const [clientId, setClientId] = useState("");
  const [amount, setAmount] = useState("");

  async function load() {
    try {
      const [c, k] = await Promise.all([api("/clients"), api("/contracts")]);
      setClients(Array.isArray(c) ? c : []);
      setContracts(Array.isArray(k) ? k : []);
    } catch (e: any) { setErr(e.message); }
  }
  useEffect(() => { load(); }, []);

  async function createClient(e: React.FormEvent) {
    e.preventDefault(); setErr(null);
    try {
      await api("/clients", { method: "POST", body: JSON.stringify({ name, contact: contact || undefined }) });
      setName(""); setContact(""); await load();
    } catch (e: any) { setErr(e.message); }
  }

  async function createContract(e: React.FormEvent) {
    e.preventDefault(); setErr(null);
    const major = Number(amount);
    if (!ref.trim()) return setErr("مرجع العقد مطلوب");
    if (!clientId) return setErr("اختر العميل");
    if (!(major > 0)) return setErr("اكتب مبلغًا موجبًا (بالجنيه)");
    try {
      await api("/contracts", { method: "POST", body: JSON.stringify({ reference: ref.trim(), clientId, amount: Math.round(major * 100) }) });
      setRef(""); setAmount(""); await load();
    } catch (e: any) { setErr(e.message); }
  }

  return (
    <Page title="العملاء والتعاقدات">
      <Err msg={err} />
      <h2 className="mb-2 text-base font-black text-navy-900">العملاء ({clients.length})</h2>
      {can("clients.manage") && (
        <Card className="mb-3">
          <form onSubmit={createClient} className="flex flex-wrap gap-2">
            <Input placeholder="اسم العميل" value={name} onChange={(e) => setName(e.target.value)} />
            <Input placeholder="جهة التواصل (اختياري)" value={contact} onChange={(e) => setContact(e.target.value)} />
            <Btn v="accent" type="submit">إضافة عميل</Btn>
          </form>
        </Card>
      )}
      <Table head={["العميل", "التواصل"]}>
        {clients.map((c) => (
          <tr key={c.id} className="hover:bg-brand-50/50">
            <Td className="font-bold">{c.name}</Td>
            <Td className="text-slate-500">{c.contact ?? "—"}</Td>
          </tr>
        ))}
      </Table>

      <h2 className="mb-2 mt-6 text-base font-black text-navy-900">التعاقدات ({contracts.length})</h2>
      {can("contracts.manage") && (
        <Card className="mb-3">
          <form onSubmit={createContract} className="flex flex-wrap gap-2">
            <Input placeholder="المرجع (مثال CTR-001)" value={ref} onChange={(e) => setRef(e.target.value)} dir="ltr" />
            <Select value={clientId} onChange={(e) => setClientId(e.target.value)}>
              <option value="">اختر العميل…</option>
              {clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </Select>
            <Input className="w-36" placeholder="المبلغ بالجنيه" value={amount} onChange={(e) => setAmount(e.target.value)} />
            <Btn v="accent" type="submit">إضافة عقد</Btn>
          </form>
        </Card>
      )}
      <Table head={["المرجع", "العميل", "المشروع", "المبلغ", "الحالة"]}>
        {contracts.map((k) => (
          <tr key={k.id} className="hover:bg-brand-50/50">
            <Td className="font-bold" ><span dir="ltr">{k.reference}</span></Td>
            <Td>{k.client?.name}</Td>
            <Td><span dir="ltr">{k.project?.code ?? "—"}</span></Td>
            <Td className="font-black text-navy-900">{g(k.amount)} جنيه</Td>
            <Td><Status v={k.status} /></Td>
          </tr>
        ))}
      </Table>
    </Page>
  );
}
