// Canonical permission registry — server-enforced.
export const PERMISSIONS = [
  "dashboard.view",
  "users.view","users.invite","users.edit","users.deactivate",
  "roles.view","roles.manage","permissions.manage",
  "projects.view","projects.create","projects.edit","projects.archive","projects.manage_members",
  "sections.view","sections.create","sections.edit","sections.archive",
  "tasks.view","tasks.create","tasks.edit","tasks.assign","tasks.change_deadline","tasks.review","tasks.complete",
  "time_entries.create","time_entries.approve",
  "clients.view","clients.manage","contracts.view","contracts.manage",
  "invoices.view","invoices.create","invoices.manage",
  "payments.view","payments.record","payments.reverse",
  "expenses.view","expenses.create","expenses.approve",
  "allocations.view","allocations.create","allocations.edit","allocations.approve",
  "finance.view","finance.record_income","finance.record_expense",
  "finance.approve_payout","finance.execute_payout","finance.reverse_transaction",
  "budget.view","budget.manage",
  "reports.view","reports.export","audit.view","settings.manage",
] as const;
export type PermKey = (typeof PERMISSIONS)[number];

export const ROLE_GRANTS: Record<string, PermKey[]> = {
  FOUNDER_ADMIN: [...PERMISSIONS],
  PROJECT_MANAGER: ["dashboard.view","projects.view","projects.create","projects.edit","projects.manage_members","sections.view","sections.create","sections.edit","tasks.view","tasks.create","tasks.edit","tasks.assign","tasks.change_deadline","tasks.review","tasks.complete","time_entries.create","time_entries.approve","clients.view","reports.view"],
  EMPLOYEE: ["dashboard.view","projects.view","sections.view","tasks.view","tasks.create","tasks.edit","time_entries.create"],
  FINANCE_MANAGER: ["dashboard.view","clients.view","clients.manage","contracts.view","contracts.manage","invoices.view","invoices.create","invoices.manage","payments.view","payments.record","expenses.view","expenses.create","expenses.approve","allocations.view","allocations.create","allocations.edit","finance.view","finance.record_income","finance.record_expense","budget.view","reports.view","reports.export"],
  VIEWER: ["dashboard.view","projects.view","sections.view","tasks.view","reports.view"],
};

/** Pure evaluator — used by API guard + unit tests. */
export function hasPermission(args: {
  userPerms: Set<string>; required: PermKey;
  scope?: string; isMember?: boolean; isAssignee?: boolean;
}): boolean {
  const { userPerms, required, scope = "ORG", isMember = false, isAssignee = false } = args;
  if (!userPerms.has(required)) return false;
  if (scope === "ORG") return true;
  if (scope === "ASSIGNED_ONLY") return isAssignee || isMember;
  if (scope === "PROJECT_IDS" || scope === "SECTION_IDS") return isMember;
  return false;
}
