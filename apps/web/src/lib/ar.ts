// Arabic display labels for system status values (DB values stay in English).
const MAP: Record<string, string> = {
  DRAFT: "مسودة", ACTIVE: "نشط", PLANNING: "تخطيط", CLOSED: "مغلق",
  BACKLOG: "متراكم", TODO: "جديد", IN_PROGRESS: "جارٍ", IN_REVIEW: "قيد المراجعة", DONE: "منجز", BLOCKED: "معطّل",
  ISSUED: "صادرة", PARTIAL: "جزئي", PAID: "مدفوع", OVERDUE: "متأخرة", CANCELLED: "ملغاة",
  SUBMITTED: "مقدَّم", APPROVED: "معتمد", REJECTED: "مرفوض",
  REQUESTED: "مطلوب", ALLOCATED: "مخصَّص", EARNED: "مستحَق", SUPERSEDED: "مستبدَل",
  PENDING: "معلَّق", SIGNED: "موقَّع",
  INCOME: "دخل", EXPENSE: "مصروف", TRANSFER: "تحويل", ALLOCATION: "توزيع", PAYOUT: "صرف", REVERSAL: "عكس", ADJUSTMENT: "تسوية", REFUND: "استرداد",
  MANUAL: "يدوي", HYBRID: "مختلط",
};

export const ar = (v: any) => MAP[String(v)] ?? String(v);

/** Money: Western digits (clear for finance), EGP suffix handled by caller. */
export const g = (m: number) => (Number(m) / 100).toLocaleString("en-US", { minimumFractionDigits: 2 });

export const fmtDate = (d: any) => {
  try { return new Date(d).toLocaleDateString("ar-EG"); } catch { return "—"; }
};

export const fmtDateTime = (d: any) => {
  try { return new Date(d).toLocaleString("ar-EG"); } catch { return ""; }
};
