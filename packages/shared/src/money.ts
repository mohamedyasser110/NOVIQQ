// Money utilities — integer minor units ONLY (e.g. piasters/qirsh). No float.
export const CURRENCY_DEFAULT = "EGP";

/** Convert major units string ("123.45") to minor units int (12345). Deterministic. */
export function toMinor(major: string | number, decimals = 2): number {
  const s = String(major).trim();
  if (!/^-?\d+(\.\d+)?$/.test(s)) throw new Error(`invalid money: ${major}`);
  const [i, f = ""] = s.split(".");
  const frac = (f + "00").slice(0, decimals);
  return Number(i) * 10 ** decimals + (Number(i) < 0 ? -Number(frac) : Number(frac));
}

export function toMajor(minor: number, decimals = 2): string {
  const neg = minor < 0;
  const a = Math.abs(minor);
  const base = 10 ** decimals;
  const s = `${Math.floor(a / base)}.${String(a % base).padStart(decimals, "0")}`;
  return neg ? `-${s}` : s;
}

export interface SplitInput { key: string; percent?: number; amount?: number; weight?: number }
export interface SplitOutput { key: string; amount: number }

/**
 * Largest-remainder split of `basis` minor units across lines.
 * - percent lines: floor(basis*pct) then distribute residual 1-unit at a time by largest fraction.
 * - weight lines: proportional to weight.
 * - amount lines: fixed, residual shown separately (not forced to sum).
 */
export function splitAllocation(basis: number, lines: SplitInput[]): { out: SplitOutput[]; unallocated: number } {
  if (!Number.isInteger(basis) || basis < 0) throw new Error("basis must be non-negative int");
  const fixed = lines.filter((l) => l.amount !== undefined);
  const pct = lines.filter((l) => l.percent !== undefined);
  const w = lines.filter((l) => l.amount === undefined && l.percent === undefined);
  const out: SplitOutput[] = [];
  let assigned = 0;

  for (const l of fixed) {
    if (!Number.isInteger(l.amount!) || l.amount! < 0) throw new Error(`bad fixed amount ${l.key}`);
    out.push({ key: l.key, amount: l.amount! });
    assigned += l.amount!;
  }
  if (pct.length) {
    const totalPct = pct.reduce((s, l) => s + (l.percent ?? 0), 0);
    if (totalPct - 100 > 1e-9) throw new Error(`percentages exceed 100: ${totalPct}`);
    const raw = pct.map((l) => ({ key: l.key, raw: (basis * (l.percent ?? 0)) / 100 }));
    const floored = raw.map((r) => ({ ...r, amt: Math.floor(r.raw) }));
    let used = floored.reduce((s, r) => s + r.amt, 0);
    // residual by largest fraction
    const order = [...floored].sort((a, b) => b.raw - b.amt - (a.raw - a.amt));
    let resid = basis - assigned - used;
    // if fixed + pct target exceeds basis, pct gets what remains
    if (resid < 0) resid = 0;
    // cap distribution to pct lines proportionally: distribute min(resid, ...)
    for (const o of order) {
      if (resid <= 0) break;
      // don't exceed the line's fair share ceiling
      if (o.amt < Math.ceil(o.raw)) { o.amt += 1; used += 1; resid -= 1; }
    }
    for (const r of floored) { out.push({ key: r.key, amount: r.amt }); assigned += r.amt; }
  }
  if (w.length) {
    const totalW = w.reduce((s, l) => s + (l.weight ?? 0), 0);
    if (totalW <= 0) throw new Error("weights must sum > 0");
    const remain = basis - assigned;
    if (remain < 0) throw new Error("fixed amounts exceed basis");
    const raw = w.map((l) => ({ key: l.key, raw: (remain * (l.weight ?? 0)) / totalW }));
    const floored = raw.map((r) => ({ ...r, amt: Math.floor(r.raw) }));
    let used = floored.reduce((s, r) => s + r.amt, 0);
    const order = [...floored].sort((a, b) => b.raw - b.amt - (a.raw - a.amt));
    let resid = remain - used;
    for (const o of order) { if (resid <= 0) break; o.amt += 1; used += 1; resid -= 1; }
    for (const r of floored) { out.push({ key: r.key, amount: r.amt }); assigned += r.amt; }
  }
  return { out, unallocated: basis - assigned };
}

export function assertLedgerBalanced(entries: { amount: number }[]): void {
  const sum = entries.reduce((s, e) => s + e.amount, 0);
  if (sum !== 0) throw new Error(`ledger entries must sum to 0, got ${sum}`);
}
