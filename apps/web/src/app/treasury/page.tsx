"use client";
import { useEffect, useState } from "react";
import { api } from "../../lib/client";
import { useMe } from "../../lib/use-me";
import { g } from "../../lib/ar";
import { Page, Btn, Input, Err, Stat, Status, Table, Td } from "../../lib/ui";

export default function TreasuryPage() {
  const { can } = useMe();
  const [balances, setBalances] = useState<any[]>([]);
  const [cash, setCash] = useState<any>(null);
  const [txns, setTxns] = useState<any[]>([]);
  const [err, setErr] = useState<string | null>(null);
  const [reason, setReason] = useState<Record<string, string>>({});

  async function load() {
    try {
      const [b, c, t] = await Promise.all([api("/treasury/balances"), api("/treasury/cash-position"), api("/ledger-transactions")]);
      setBalances(Array.isArray(b) ? b : []);
      setCash(c);
      setTxns(Array.isArray(t) ? t : []);
    } catch (e: any) { setErr(e.message); }
  }
  useEffect(() => { load(); }, []);

  async function reverse(id: string) {
    const r = reason[id]?.trim();
    if (!r) return setErr("العكس يحتاج سببًا");
    setErr(null);
    try {
      await api(`/ledger-transactions/${id}/reverse`, { method: "POST", body: JSON.stringify({ reason: r }) });
      await load();
    } catch (e: any) { setErr(e.message); }
  }

  return (
    <Page title="الخزنة" sub="التحويلات الداخلية ليست إيرادًا · التصحيح بالعكس فقط">
      <Err msg={err} />
      {cash && (
        <div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
          <Stat label="الداخل" value={`${g(cash.inflow)} جنيه`} />
          <Stat label="الخارج" value={`${g(cash.outflow)} جنيه`} />
          <Stat label="صافي الكاش" value={`${g(cash.cash)} جنيه`} accent />
        </div>
      )}

      <h2 className="mb-2 text-base font-black text-navy-900">أرصدة الحسابات</h2>
      <div className="mb-4">
        <Table head={["الحساب", "الرصيد"]}>
          {balances.map((a) => (
            <tr key={a.key} className="hover:bg-brand-50/50">
              <Td><b dir="ltr">{a.key}</b> <span className="text-xs text-slate-400">{a.label}</span></Td>
              <Td className="font-black">{g(a.balance)} {a.currency}</Td>
            </tr>
          ))}
        </Table>
      </div>

      <h2 className="mb-2 text-base font-black text-navy-900">الدفتر ({txns.length})</h2>
      <Table head={["المرجع", "النوع", "القيود", "إجراء"]}>
        {txns.map((t) => (
          <tr key={t.id} className="hover:bg-brand-50/50">
            <Td><b dir="ltr">{t.reference}</b><div className="text-xs text-slate-400">{t.note ?? ""}</div></Td>
            <Td><Status v={t.type} /></Td>
            <Td className="text-xs text-slate-500">{(t.entries ?? []).map((e: any) => `${e.account?.key ?? e.accountId}: ${g(e.amount)}`).join(" · ")}</Td>
            <Td>
              {t.type !== "REVERSAL" && can("finance.reverse_transaction") ? (
                <span className="flex gap-1">
                  <Input className="w-28 !py-1 text-xs" placeholder="سبب العكس" value={reason[t.id] ?? ""} onChange={(e) => setReason({ ...reason, [t.id]: e.target.value })} />
                  <Btn className="!py-1 text-xs" onClick={() => reverse(t.id)}>عكس</Btn>
                </span>
              ) : <span className="text-xs text-slate-300">—</span>}
            </Td>
          </tr>
        ))}
      </Table>
    </Page>
  );
}
