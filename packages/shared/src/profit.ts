// Profit policy — D-011. All amounts Int minor units. No float.
import { toMinor } from "./money";

export interface ProfitInputs {
  // planning side
  contractValue: number;
  estExpenses: number;
  plannedAllocations: number;
  // actual side
  collected: number;
  actualExpenses: number;
  approvedCompensation: number;
  taxesFees: number;
  otherCosts: number;
  // distribution guard
  reserve: number;
  outstandingObligations: number;
  financialReview: boolean;
  approvalGranted: boolean;
  waiverReason?: string;
}

/** 1) Estimated profit — shown with "تقديري" badge, never used for payouts. */
export function estimatedProfit(i: Pick<ProfitInputs, "contractValue" | "estExpenses" | "plannedAllocations">): number {
  return i.contractValue - i.estExpenses - i.plannedAllocations;
}

/** 2) Actual profit — only meaningful when collections/expenses are final. */
export function actualProfit(i: Pick<ProfitInputs, "collected" | "actualExpenses" | "approvedCompensation" | "taxesFees" | "otherCosts">): number {
  return i.collected - i.actualExpenses - i.approvedCompensation - i.taxesFees - i.otherCosts;
}

/**
 * 3) Distributable — hard guard. Throws unless:
 *    financialReview === true AND approvalGranted === true AND
 *    (outstandingObligations === 0 OR explicit waiverReason).
 * The 30,000 remainder in the 100k/20k/50k example NEVER passes this gate alone.
 */
export function assertDistributable(i: ProfitInputs): number {
  if (!i.financialReview) throw new Error("profit distribution blocked: financial review not completed");
  if (!i.approvalGranted) throw new Error("profit distribution blocked: approval required (finance.approve_payout)");
  if (i.outstandingObligations > 0 && !i.waiverReason) {
    throw new Error(`profit distribution blocked: outstanding obligations ${i.outstandingObligations} require documented waiver`);
  }
  const actual = actualProfit(i);
  const dist = actual - i.reserve - (i.waiverReason ? 0 : i.outstandingObligations);
  if (dist < 0) throw new Error("profit distribution blocked: negative distributable");
  return dist;
}

/** Canonical company example (EGP): 100k revenue, 20k expenses, 50k team → 30k is NOT distributable. */
export function companyExample() {
  const contractValue = toMinor("100000");
  const estExpenses = toMinor("20000");
  const plannedAllocations = toMinor("50000");
  return {
    contractValue, estExpenses, plannedAllocations,
    estimated: estimatedProfit({ contractValue, estExpenses, plannedAllocations }), // 30,000.00
  };
}
