import { describe, it, expect } from "vitest";
import { toMinor, toMajor, splitAllocation, assertLedgerBalanced } from "./money";

describe("money minor units", () => {
  it("converts major->minor without float error", () => {
    expect(toMinor("100000")).toBe(10000000);
    expect(toMinor("0.1") + toMinor("0.2")).toBe(toMinor("0.3"));
  });
  it("round-trips", () => {
    expect(toMajor(toMinor("25000.75"))).toBe("25000.75");
  });
  it("splits 100k EGP example deterministically", () => {
    const basis = toMinor("100000");
    const { out, unallocated } = splitAllocation(basis, [
      { key: "frontend", percent: 25 },
      { key: "backend", percent: 30 },
      { key: "uiux", percent: 15 },
      { key: "ai", percent: 20 },
      { key: "qa", percent: 10 },
    ]);
    const sum = out.reduce((s, o) => s + o.amount, 0);
    expect(sum).toBe(basis);
    expect(unallocated).toBe(0);
    expect(out.find((o) => o.key === "backend")!.amount).toBe(toMinor("30000"));
  });
  it("rejects percentages > 100", () => {
    expect(() => splitAllocation(1000, [{ key: "a", percent: 60 }, { key: "b", percent: 50 }])).toThrow();
  });
  it("ledger must balance", () => {
    expect(() => assertLedgerBalanced([{ amount: 6000 }, { amount: -6000 }])).not.toThrow();
    expect(() => assertLedgerBalanced([{ amount: 6000 }, { amount: -5000 }])).toThrow();
  });
});
