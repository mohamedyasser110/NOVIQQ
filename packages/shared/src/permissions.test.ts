import { describe, it, expect } from "vitest";
import { hasPermission, ROLE_GRANTS } from "./permissions";

describe("RBAC evaluator", () => {
  it("founder has everything", () => {
    const perms = new Set<string>(ROLE_GRANTS.FOUNDER_ADMIN);
    expect(hasPermission({ userPerms: perms, required: "finance.execute_payout" })).toBe(true);
  });
  it("employee cannot touch finance", () => {
    const perms = new Set<string>(ROLE_GRANTS.EMPLOYEE);
    expect(hasPermission({ userPerms: perms, required: "finance.execute_payout" })).toBe(false);
  });
  it("assigned-only scope blocks strangers", () => {
    const perms = new Set<string>(["tasks.view"]);
    expect(hasPermission({ userPerms: perms, required: "tasks.view", scope: "ASSIGNED_ONLY", isAssignee: false })).toBe(false);
    expect(hasPermission({ userPerms: perms, required: "tasks.view", scope: "ASSIGNED_ONLY", isAssignee: true })).toBe(true);
  });
});
