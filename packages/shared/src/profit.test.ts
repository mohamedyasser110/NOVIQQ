import { describe, it, expect } from "vitest";
import { estimatedProfit, actualProfit, assertDistributable, companyExample } from "./profit";
import { toMinor } from "./money";

describe("profit policy D-011 — 100k/20k/50k example", () => {
  it("estimated remainder is 30k but NOT distributable alone", () => {
    const ex = companyExample();
    expect(ex.estimated).toBe(toMinor("30000"));
    // gate blocks: no review, no approval, obligations pending
    expect(() =>
      assertDistributable({
        contractValue: ex.contractValue, estExpenses: ex.estExpenses, plannedAllocations: ex.plannedAllocations,
        collected: toMinor("40000"), actualExpenses: toMinor("20000"),
        approvedCompensation: toMinor("10000"), taxesFees: toMinor("5000"), otherCosts: toMinor("2000"),
        reserve: toMinor("3000"), outstandingObligations: toMinor("10000"),
        financialReview: false, approvalGranted: false,
      }),
    ).toThrow(/financial review/);
  });

  it("actual profit deducts taxes/fees/other costs", () => {
    const actual = actualProfit({
      collected: toMinor("100000"), actualExpenses: toMinor("20000"),
      approvedCompensation: toMinor("50000"), taxesFees: toMinor("5000"), otherCosts: toMinor("2000"),
    });
    expect(actual).toBe(toMinor("23000")); // not 30k
  });

  it("distributable requires review + approval + zero obligations (or waiver)", () => {
    const base = {
      contractValue: toMinor("100000"), estExpenses: toMinor("20000"), plannedAllocations: toMinor("50000"),
      collected: toMinor("100000"), actualExpenses: toMinor("20000"),
      approvedCompensation: toMinor("50000"), taxesFees: toMinor("5000"), otherCosts: toMinor("2000"),
      reserve: toMinor("3000"), outstandingObligations: toMinor("10000"),
      financialReview: true, approvalGranted: true,
    };
    expect(() => assertDistributable(base)).toThrow(/outstanding obligations/);
    // with documented waiver, obligations stay reserved (not distributed)
    const withWaiver = assertDistributable({ ...base, waiverReason: "board waiver #12, obligations carried to next quarter" });
    expect(withWaiver).toBe(toMinor("20000")); // 23k actual − 3k reserve
    // clean close: no obligations
    const clean = assertDistributable({ ...base, outstandingObligations: 0 });
    expect(clean).toBe(toMinor("20000"));
  });
});
