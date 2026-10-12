# Company Operating System --- Master Product & Engineering Specification

**Version:** 1.0\
**Prepared:** 2026-10-09\
**Product type:** Private internal web application for a five-founder
software company\
**Primary implementation target:** OpenCode coding agent\
**Preferred stack:** Next.js + TypeScript, NestJS + TypeScript,
PostgreSQL, Prisma\
**Architecture:** Modular monolith, API-first, responsive web UI

------------------------------------------------------------------------

## 0. Instructions to the coding agent

Treat this document as the canonical product specification. Build the
application in small, verifiable phases instead of trying to generate
the entire system in one uncontrolled pass.

### Required agent behavior

1.  Inspect the current repository before changing anything. Identify
    framework, existing code, package manager, environment, and current
    implementation state.
2.  If the repository is empty, scaffold the architecture in this
    document. If it already contains a working project, preserve useful
    existing work and adapt the design rather than replacing it blindly.
3.  Create and maintain:
    -   `README.md`
    -   `docs/MASTER_SPEC.md` (copy this specification into the
        repository)
    -   `docs/IMPLEMENTATION_PLAN.md`
    -   `docs/DECISIONS.md` for material technical/product decisions
    -   `.env.example`
    -   database migrations and seed scripts
    -   automated tests
4.  First produce a short repository assessment and an implementation
    plan, then implement Phase 1. Do not stop after writing plans;
    proceed with implementation unless blocked by a genuinely necessary
    missing secret or external service.
5.  Work in small vertical slices. For each slice: implement, run
    lint/type checks/tests, fix failures, and summarize changed files
    and remaining risks.
6.  Do not claim a feature works unless it has been implemented and
    tested. Do not leave core functionality as mock data, dead buttons,
    TODO-only handlers, or frontend-only permission checks.
7.  Never expose secrets, commit `.env`, or log passwords, tokens, or
    sensitive financial data.
8.  Do not run destructive commands against a real database. Do not
    reset/drop a database or overwrite user work without explicit
    approval. Use safe migrations and backups.
9.  If a requirement is ambiguous, choose a conservative, reversible
    default, document it in `docs/DECISIONS.md`, and keep it
    configurable. Ask only when a decision truly blocks implementation.
10. Prioritize correctness, access control, financial integrity,
    usability, and maintainability over decorative animations.
11. Keep all amounts in integer minor units (e.g. piastres) or a
    carefully defined decimal type; never use binary floating point for
    money.
12. Use transactions and idempotency for payment, ledger, allocation,
    and payout operations.
13. Implement server-side authorization for every protected operation.
    Hiding UI controls is not authorization.
14. Add realistic seed data for local development, clearly marked as
    fictional.
15. At the end of each phase, provide:
    -   features completed
    -   files/modules changed
    -   commands run and their results
    -   tests added
    -   known limitations
    -   exact next step

### Delivery strategy

Build in phases: - Phase 0: Repository assessment, architecture, UI
shell, environment setup. - Phase 1: Authentication, users, permissions,
projects, sections, tasks, timeline. - Phase 2: Clients, contracts,
invoices, collections, expenses, project allocations, compensation. -
Phase 3: Company treasury, ledger, budget rules, approvals, payouts. -
Phase 4: Reports, exports, notifications, audit hardening, backups and
security review. - Phase 5: End-to-end tests, deployment documentation,
operational readiness.

The app should remain runnable after each phase.

------------------------------------------------------------------------

## 1. Product vision

Build a private **Company Operating System** for a software company
whose five founders/managers actively collaborate on projects and tasks.
The system combines project planning, task execution, team assignment,
schedules, financial planning, project compensation, company treasury,
configurable permissions, approvals, reporting, and audit history.

It is more than a to-do list. It must connect: - projects and client
commitments; - custom work sections such as UI/UX, frontend, backend,
AI, mobile, QA, DevOps, cybersecurity, documentation, and any future
custom section; - tasks and subtasks with one or more assignees; -
deadlines, dependencies, estimates, time entries, review and
acceptance; - contract value, invoicing, actual cash collection, costs,
allocations and payouts; - company reserve, marketing, working capital,
salaries/compensation, operating costs and unallocated cash; -
founder/member contribution records and project-specific compensation; -
roles, granular permissions, scoped access, approval workflows and audit
logs.

### Core product principles

1.  **Dynamic configuration:** section names, members, allocation rules,
    workflow settings, and permissions must be data-driven wherever
    practical, not hard-coded.
2.  **Work and money are separate but connected:** completing a task
    does not automatically pay a person; issuing an invoice does not
    mean cash was collected.
3.  **Planned, earned/approved, and paid amounts are distinct states.**
4.  **Historical financial records must not silently change:** approved
    plans and ledger entries are immutable or corrected with linked
    reversal/adjustment records.
5.  **Five founders can have broad administrative access**, but
    sensitive actions should be separately permissioned and auditable.
6.  **The app should be usable by a small team now and support future
    employees and projects.**
7.  **Start with a modular monolith**, not microservices.
8.  **Responsive web first:** desktop, tablet, and mobile browser.
9.  **No mandatory third-party SaaS dependency for core features.**
10. **Do not implement bank integrations or automatic transfers unless
    explicitly requested.** Treasury accounts may initially be internal
    tracking categories.

------------------------------------------------------------------------

## 2. Users, roles and permissions

### Initial user population

-   Five founders/managers. Seed them as users or provide a secure
    first-run setup flow. Do not hard-code real passwords.
-   Additional employees/contractors can be invited later.
-   Do not assume founder names, email addresses, equity shares, or
    compensation rates. Use configurable fictional seed data and allow
    the company to set real values.

### Roles

Suggested built-in roles: - `FOUNDER_ADMIN` - `PROJECT_MANAGER` -
`EMPLOYEE` - `FINANCE_MANAGER` - `VIEWER`

Roles are templates, not the only way to grant access. Support custom
roles and individual permission overrides if practical.

### Authorization model

Use **RBAC + resource scope**: - organization-wide; - assigned projects
only; - selected projects; - selected sections; - assigned tasks only; -
financial records permitted or denied independently of task access.

Permissions must be enforced server-side on every route/service/action
and tested directly through the API.

### Suggested permission keys

-   `dashboard.view`
-   `users.view`, `users.invite`, `users.edit`, `users.deactivate`
-   `roles.view`, `roles.manage`
-   `permissions.manage`
-   `projects.view`, `projects.create`, `projects.edit`,
    `projects.archive`, `projects.delete` (prefer archive over deletion)
-   `projects.manage_members`
-   `sections.view`, `sections.create`, `sections.edit`,
    `sections.archive`
-   `tasks.view`, `tasks.create`, `tasks.edit`, `tasks.assign`,
    `tasks.change_deadline`, `tasks.review`, `tasks.complete`
-   `time_entries.create`, `time_entries.approve`
-   `clients.view`, `clients.manage`
-   `contracts.view`, `contracts.manage`
-   `invoices.view`, `invoices.create`, `invoices.manage`
-   `payments.view`, `payments.record`, `payments.reverse`
-   `expenses.view`, `expenses.create`, `expenses.approve`
-   `allocations.view`, `allocations.create`, `allocations.edit`,
    `allocations.approve`
-   `finance.view`, `finance.record_income`, `finance.record_expense`
-   `finance.approve_payout`, `finance.execute_payout`,
    `finance.reverse_transaction`
-   `budget.view`, `budget.manage`
-   `reports.view`, `reports.export`
-   `audit.view`
-   `settings.manage`

Support constraints such as `project_ids`, `section_ids`, or
`assigned_only` when applicable. Do not let a user grant permissions
that they do not themselves have authority to grant.

### Default role behavior

-   Founder/Admin: broad access according to configured grants.
-   Project Manager: manage assigned projects, sections, tasks, dates,
    and members within permitted scope; no automatic treasury/payout
    authority.
-   Employee: view and update assigned tasks, add
    comments/attachments/time entries within scope; no access to
    sensitive financial records unless explicitly granted.
-   Finance Manager: manage permitted financial records, reports, and
    reconciliation; approval/execution can be separated.
-   Viewer: read-only within permitted scope.

### Sensitive operations

-   Editing a payout or allocation after approval requires a documented
    adjustment or a new approval.
-   Separate recording a payout from approving/executing it where
    possible.
-   Audit role/permission changes.
-   Prevent accidental removal/deactivation of the last active top-level
    administrator.
-   Support MFA as a production hardening goal. Do not invent a fake MFA
    implementation; if deferred, document it.

------------------------------------------------------------------------

## 3. Information architecture and primary screens

Required screens: 1. Dashboard 2. Projects list 3. Project details 4.
Project sections and team 5. Task list / Kanban 6. My Tasks 7. Calendar
and timeline / Gantt 8. Team and member profiles 9. Roles and
permissions 10. Clients 11. Contracts and commercial terms 12. Invoices
and collections 13. Project expenses 14. Allocation plans and
contribution records 15. Company treasury and budget accounts 16.
Payouts and approvals 17. Reports and exports 18. Notifications 19.
Audit log 20. Company settings

Navigation should be simple and consistent. Use responsive layouts,
clear empty states, loading states, error states, confirmation dialogs
for sensitive actions, and accessible form controls. Do not prioritize
visual effects over usability.

### Dashboard cards/widgets

-   active projects;
-   overdue tasks and tasks due soon;
-   current project status;
-   uncollected invoices / receivables;
-   cash position based on ledger;
-   planned vs actual project cost;
-   approved but unpaid compensation;
-   reserve and budget category balances;
-   pending approvals;
-   recent activity.

Every widget must respect the signed-in user's permissions and data
scope.

------------------------------------------------------------------------

## 4. Project management domain

### Project fields

-   id, project code, name, description, goals/scope;
-   client reference;
-   project manager;
-   status;
-   start date, planned delivery date, actual delivery/closure dates;
-   currency;
-   contract value;
-   payment terms / milestones;
-   estimated budget and actual costs;
-   project members;
-   risk/notes;
-   attachments and linked documents;
-   created/updated metadata and audit trail.

Suggested statuses: `DRAFT`, `PLANNING`, `ACTIVE`, `ON_HOLD`,
`IN_REVIEW`, `DELIVERED`, `CLOSED`, `CANCELLED`.

Do not close a project silently while tasks, invoices, or approved
payouts remain unresolved. Allow an authorized user to close with
warnings and an explicit reason when policy permits.

### Project lifecycle

1.  Create project and client details.
2.  Define scope, price, dates, and payment terms.
3.  Create/customize sections.
4.  Assign members and section leads.
5.  Create tasks, subtasks, dependencies, estimates and acceptance
    criteria.
6.  Prepare and approve the allocation plan if compensation is used.
7.  Execute work and track progress.
8.  Review/QA and accept delivery.
9.  Record invoices, collections and expenses.
10. Reconcile compensation and company allocations.
11. Close with unresolved items explicitly documented.

### Dynamic project sections

Examples only: - UI Design - UX Research - Frontend Development -
Backend Development - AI / Machine Learning - Mobile Development -
Database - DevOps & Deployment - Cybersecurity - Testing & QA -
Documentation - Client Communication

Sections must be configurable per project and reusable as templates.
Each section can have: - name, description, color/icon; - lead and
multiple members; - start/end dates; - tasks; - dependencies; -
progress; - estimated and actual cost; - optional allocation
budget/percentage; - archived state.

A person can work in multiple sections. A section can have multiple
members. The system must not assume one section equals one employee.

------------------------------------------------------------------------

## 5. Tasks, subtasks and work tracking

### Task fields

-   id, project_id, optional section_id;
-   title, description, acceptance criteria;
-   parent_task_id for subtasks;
-   status, priority;
-   start date and due date;
-   estimated hours and actual approved hours;
-   progress percentage if used;
-   one or more assignees;
-   optional primary owner;
-   reviewer;
-   dependency links;
-   attachments, links, comments;
-   created_by, updated_by, timestamps;
-   audit/activity history.

Suggested statuses: `BACKLOG`, `TODO`, `IN_PROGRESS`, `IN_REVIEW`,
`CHANGES_REQUESTED`, `BLOCKED`, `DONE`, `CANCELLED`.

A blocked flag may alternatively be represented separately from the
workflow status if this improves usability; document the choice.

### Required behaviors

-   Create, edit, archive tasks and create nested subtasks.
-   Assign multiple users and optionally designate one primary owner.
-   Add comments, mentions, files, and links to repositories or pull
    requests.
-   Record work/time entries with approval where appropriate.
-   Submit a task for review; reviewer can accept or request changes.
-   Track due date changes and require a reason for material changes.
-   Support priority and filters by project, section, user, status,
    deadline, and priority.
-   Show overdue tasks and upcoming deadlines.
-   Prevent invalid dependency cycles.
-   Do not automatically assign equal financial shares merely because
    several people are assigned to a task.
-   Completion criteria should be configurable and include review if
    required.

### Progress calculation

Choose and document a consistent rule: - section/project progress may be
calculated from weighted tasks; - task weights may use estimates or
manual weights; - avoid a naive average if tasks differ substantially in
size; - allow a manual override only with permission and audit logging.

------------------------------------------------------------------------

## 6. Timeline, calendar and workload

Features: - calendar day/week/month views; - project timeline and Gantt
view; - drag-and-drop rescheduling if supported; - task dependencies and
critical upcoming milestones; - original due date and revised due date
history; - overdue indicators; - reminders before due dates; - workload
view by user and section; - working days, time zone and holidays
configurable by the company; - date-range filters for 7/14/30 days and
custom ranges.

When a task date changes, show affected dependent tasks and ask before
cascading changes. Do not silently shift dependent deadlines. If Gantt
functionality requires a library, choose a maintained option compatible
with the app and document licensing/limitations.

------------------------------------------------------------------------

## 7. Financial model: distinguish revenue, cash, cost and compensation

This domain is high risk. Implement it explicitly and test it
thoroughly.

### Required separate concepts

1.  **Contract value / revenue plan:** what the customer agreed to pay.
2.  **Invoices / receivables:** amounts billed and due.
3.  **Collections:** money actually received.
4.  **Project expenses/costs:** costs associated with doing the work.
5.  **Allocation plan:** planned distribution of a project
    budget/compensation pool across sections and people.
6.  **Earned/approved compensation:** amount accepted as due under
    company policy.
7.  **Payouts:** money actually paid.
8.  **Company treasury:** actual recorded cash movement and internal
    budget categorization.
9.  **Profit:** calculated under a documented formula; do not equate
    revenue minus a few known costs with distributable profit unless all
    relevant costs/obligations are accounted for.

### Money representation

-   Never use JavaScript binary floating-point for financial arithmetic.
-   Use integer minor units where the currency supports it, or
    PostgreSQL `NUMERIC/DECIMAL` with an explicit precision policy.
-   Store currency with monetary records.
-   Avoid silently mixing currencies. If multi-currency is implemented,
    store original currency and exchange-rate details for converted
    reporting.
-   Round deterministically and allocate any residual minor units using
    a documented rule.

### Project financial profile

Include: - contract value; - currency; - invoice milestones; - invoiced
amount; - collected amount; - receivable balance; - direct expenses; -
section allocations; - member allocations; - planned compensation; -
approved compensation; - paid compensation; - estimated margin and
actual margin; - reconciliation/closure status.

Do not treat contract value as cash. Example: contract value EGP
100,000, collections EGP 40,000 means only EGP 40,000 has been
collected.

### Project allocation plans

Allow custom allocation per project and/or reusable templates. For each
allocation plan: - plan version; - status such as draft, submitted,
approved, superseded; - basis/amount being allocated; - currency; -
allocation lines; - created/approved by and timestamps; - effective
date; - change reason; - audit history.

Example illustrative distribution for a project worth EGP 100,000: -
Frontend 25% = EGP 25,000 - Backend 30% = EGP 30,000 - UI/UX 15% = EGP
15,000 - AI 20% = EGP 20,000 - QA & Deployment 10% = EGP 10,000

These values are examples only, not defaults to impose on real company
finances. Sections may be absent from a project. Unallocated amounts
must be visible and intentional.

### Allocation within a section

Example: Backend section pool = EGP 30,000: - Member A: 50% = EGP
15,000 - Member B: 30% = EGP 9,000 - Member C: 20% = EGP 6,000

Support these allocation methods, chosen explicitly per
project/section: - manual percentages/amounts; - contribution-based; -
approved-hours-based; - hybrid model.

For a hybrid model, an example is 60% of the pool distributed by agreed
contribution weights and 40% by approved hours. All weights and
calculations must be visible before approval. Do not imply these example
weights are recommended defaults.

### Allocation validation

-   Section percentages cannot exceed the allocation basis.
-   Show any unallocated balance.
-   Reject invalid/negative allocations unless a supported adjustment
    workflow explicitly permits them.
-   Use deterministic rounding.
-   If multiple currencies exist, validate within currency and never sum
    different currencies without conversion.
-   Validate both UI and server-side.
-   Save approved allocation plans as versioned snapshots.
-   Editing a draft should not alter an approved snapshot.
-   Changes to approved allocations require a new version or adjustment
    workflow, reason, authorization, and audit event.
-   Past payouts must not be rewritten by a new allocation plan.

### Status definitions

-   `ALLOCATED`: planned amount only.
-   `EARNED/APPROVED`: amount has met the policy conditions and been
    approved.
-   `PAID`: cash payout has been recorded.
-   Optional `ON_HOLD` or `DISPUTED` for reviewed issues.

Define company policy for when compensation becomes earned: task
acceptance, section completion, project delivery, client collection, or
a combination. Make the rule explicit and configurable where possible.
Never infer that a completed task automatically triggers payment.

### Contribution records

Per member and project/section: - agreed contribution weight; - tasks
completed and accepted; - approved time entries; -
responsibility/complexity notes; - planned allocation; - approved
compensation; - paid compensation; - outstanding amount; - change
history.

Do not calculate a person's ownership of the company based on task
counts or contribution scores.

------------------------------------------------------------------------

## 8. Company treasury and budget

Treasury initially tracks the company's actual recorded inflows/outflows
and internal budget categories. It does not imply a real bank
integration or actual transfer between bank accounts.

### Suggested accounts/categories

-   `OPERATING_EXPENSES`
-   `COMPANY_RESERVE`
-   `MARKETING`
-   `WORKING_CAPITAL`
-   `SALARIES_COMPENSATION`
-   `TAXES_FEES`
-   `PROJECT_EXPENSES`
-   `UNALLOCATED_CASH`

Allow authorized users to create and configure additional categories.

### Allocation rules

Support rules based on: - each collection; - defined net profit after
specified costs/obligations; - fixed monthly amount; - manual
per-collection allocation; - project type or another explicit filter if
justified.

Example only: 15% reserve, 10% marketing, 20% working capital, 25%
compensation, 10% operations, 20% unallocated. Do not impose these as
company defaults without user configuration.

Every rule must state its calculation basis. A percentage of gross
collections is not the same as a percentage of net profit. Avoid
double-allocation of the same money.

### Treasury operations

-   record income/collection;
-   record expense;
-   internal transfer between budget accounts;
-   allocation;
-   payout;
-   refund;
-   reversal/adjustment. Each operation must include a unique reference,
    date, amount, currency, source/destination when relevant, related
    project/client/person, attachments, creator, approval state, and
    audit metadata.

Internal transfer between reserve and marketing is not new company
income. An allocation is not necessarily a bank transfer. Clearly
distinguish budget commitment from actual cash movement.

### Ledger integrity

-   Use an append-only or correction-by-reversal pattern for posted
    financial transactions.
-   Never silently edit or delete posted entries.
-   Use database transactions for multi-row financial operations.
-   Add idempotency keys or equivalent duplicate protection to sensitive
    create operations.
-   Enforce consistent debit/credit or account-balance logic. Document
    whether the implementation uses a double-entry ledger or a simpler
    account movement ledger. For a real accounting-grade implementation,
    prefer double-entry accounting.
-   Provide reconciliation reports and a way to match recorded
    collections/payouts against external records.
-   Prevent funds from being allocated or paid twice.
-   Show available, reserved/allocated, and paid balances separately.

------------------------------------------------------------------------

## 9. Founder ownership vs compensation vs profit distribution

Keep three concepts separate: 1. **Project compensation:** payment for
work performed on a project. 2. **Founder ownership/equity:** ownership
percentage, if the company chooses to record it. 3. **Profit
distribution:** distribution of company profits under the company's
approved policy.

A founder may earn project compensation independently of their ownership
percentage. Do not infer ownership from tasks completed, hours, or
contribution scores.

If ownership/profit-distribution functionality is included, make it
configurable and approval-based. Treat the product as an internal
management record, not a substitute for professional accounting, tax,
legal, or shareholder documentation.

------------------------------------------------------------------------

## 10. Invoices, payments, expenses and payouts

### Clients/contracts

-   client name and contacts;
-   contract reference, scope, amount, currency, dates;
-   payment terms/milestones;
-   signed document attachments;
-   status and audit history.

### Invoices

-   invoice number;
-   client/project;
-   issue date, due date;
-   line items, tax/discount fields if configured;
-   amount, currency, status;
-   partial payments and outstanding balance;
-   attachment/export.

Do not invent local tax rules. Keep tax fields configurable and document
that jurisdiction-specific tax behavior requires validation by a
qualified professional.

### Collections

-   link collection to invoice and client/project;
-   amount/date/currency/payment method/reference;
-   proof/receipt attachment;
-   recorder and approval;
-   duplicate protection;
-   support partial collections and reversals.

### Expenses

-   project or company-wide classification;
-   category, amount, currency, date, vendor, receipt;
-   submitted/approved/rejected/paid states;
-   approver and audit log.

### Payouts

-   payee;
-   related project/allocation;
-   approved amount and currency;
-   requested/approved/paid/rejected/held status;
-   payment date and reference;
-   evidence/receipt attachment;
-   record approver and executor separately where possible;
-   enforce no overpayment and no duplicate payout.

------------------------------------------------------------------------

## 11. Approval workflows

Build a configurable but simple workflow. Do not over-engineer a generic
workflow engine in the first release.

Initial approval candidates: - allocation plan approval; - expense
approval; - payout approval; - change to an approved allocation; -
financial correction/reversal; - project closure with unresolved
financial items; - role/permission changes if policy requires.

Each approval request should record: - entity type and ID; - requested
action; - requester; - approver(s); - state; - timestamps; -
reason/comments; - before/after summary; - decision history.

An approver should not approve an action they are forbidden to approve.
Consider requiring a second person for high-impact financial actions;
make the policy configurable. Avoid accidental self-approval of payouts
or allocation changes when separation of duties is enabled.

------------------------------------------------------------------------

## 12. Reports and exports

Required reports: - project financial summary: contract, invoices,
collections, receivables, costs, allocations, approved compensation,
payouts, margin; - section performance and budget; - member
task/workload/contribution report; - approved vs paid compensation; -
cash flow by period; - budget category balances; - outstanding client
receivables; - unpaid approved team compensation; - project delay
report; - planned vs actual costs; - audit and approval history for
authorized users.

Filters: - date range; - project; - client; - section; - member; -
status; - currency; - account/category.

Exports: - CSV and XLSX/Excel at minimum; - PDF where practical. All
exports must enforce the same authorization scope as the source screen
and must not leak hidden financial fields.

------------------------------------------------------------------------

## 13. Notifications and collaboration

Internal notification center: - task assigned; - due date approaching; -
task overdue; - comment mention; - review requested; - changes
requested; - project schedule changed; - allocation
submitted/approved/changed; - expense/payout awaiting approval; -
collection or payout recorded for authorized users.

Email notifications can be added behind configuration. The app must
remain usable if no external email provider is configured. Users may
mute non-critical notification types, but cannot disable mandatory
security/audit controls.

------------------------------------------------------------------------

## 14. Audit trail

Audit at minimum: - sign-in/security events where appropriate; - user
creation/deactivation; - role/permission changes; - project and deadline
changes; - task assignment and status changes; - allocation plan
creation, approval and supersession; - invoices, collections, expenses,
transfers and payouts; - financial reversals and corrections; - approval
decisions; - export actions for sensitive reports where practical.

Audit record fields: - actor user ID; - action; - entity type and ID; -
timestamp; - request/correlation ID; - before/after values or safe
diff; - reason where required; - relevant scope/metadata.

Do not log passwords, tokens, full secrets, or unnecessary sensitive
data. Protect audit records from ordinary users' edits/deletions.

------------------------------------------------------------------------

## 15. Technical architecture and stack

Preferred stack: - Frontend: Next.js + TypeScript. - UI: Tailwind CSS
plus a maintained accessible component library. - Backend: NestJS +
TypeScript REST API. - Database: PostgreSQL. - ORM: Prisma. - Optional
later: Redis for queues/cache; background worker for reminders, reports,
or email. - Testing: unit, integration, API authorization, and browser
end-to-end tests. - API docs: OpenAPI/Swagger. - Deployment:
environment-based configuration, HTTPS, managed or maintained
PostgreSQL, automated backup policy.

### Architecture

Browser → Next.js UI → NestJS REST API → PostgreSQL via Prisma.

Backend modules: - `AuthModule` - `UsersModule` - `PermissionsModule` -
`ProjectsModule` - `SectionsModule` - `TasksModule` - `TimelineModule` -
`ClientsModule` - `ContractsModule` - `InvoicesModule` -
`PaymentsModule` - `ExpensesModule` - `AllocationsModule` -
`TreasuryModule` - `PayoutsModule` - `ApprovalsModule` -
`ReportsModule` - `NotificationsModule` - `AuditModule` -
`SettingsModule`

Start as a modular monolith. Do not introduce microservices, event
streaming, Kubernetes, or Redis unless there is a clear demonstrated
need.

### Engineering requirements

-   Validate all request payloads.
-   Centralize business logic in services/domain modules, not UI
    components.
-   Use database transactions for related state changes.
-   Add pagination, filtering and sorting on list endpoints.
-   Use database indexes for common filters and foreign keys.
-   Handle concurrency when two founders edit/approve the same financial
    item.
-   Use optimistic concurrency/version checks or equivalent for critical
    records.
-   Provide stable error shapes and meaningful UI error messages.
-   Use secure cookies/session strategy or a well-supported
    authentication solution; do not invent cryptography.
-   Store secrets only in environment variables/secret manager.
-   Use migrations; never rely on ad-hoc production schema mutation.
-   Include health checks and structured server logs without sensitive
    payloads.
-   Document local setup and deployment.

------------------------------------------------------------------------

## 16. Suggested data model

The final schema may evolve, but should cover the following entities and
relationships.

### Identity and permissions

-   `User`
-   `Role`
-   `Permission`
-   `UserRole`
-   `RolePermission`
-   `UserPermissionOverride` if needed
-   `ProjectAccessScope` or equivalent

### Project execution

-   `Client`
-   `Project`
-   `ProjectMember`
-   `ProjectSection`
-   `SectionMember`
-   `Task`
-   `TaskAssignee`
-   `TaskDependency`
-   `TaskComment`
-   `TaskAttachment`
-   `TimeEntry`
-   `ProjectTemplate`
-   `SectionTemplate`

### Commercial and financial

-   `Contract`
-   `Invoice`
-   `InvoiceLine`
-   `Collection` or `PaymentReceipt`
-   `Expense`
-   `FinancialAccount`
-   `LedgerTransaction`
-   `LedgerEntry`
-   `AllocationRule`
-   `AllocationPlan`
-   `AllocationPlanVersion`
-   `AllocationLine`
-   `ContributionRecord`
-   `CompensationEntitlement`
-   `Payout`
-   `ApprovalRequest`
-   `ApprovalDecision`
-   `BudgetPeriod` if needed
-   `Currency` or currency code field
-   `AuditLog`
-   `Notification`
-   `Attachment`
-   `CompanySetting`

### Relationship rules

-   A project has many sections and tasks.
-   A section belongs to a project and has many members/tasks.
-   Tasks may have parent tasks and dependencies.
-   Tasks and sections may have many assignees/members.
-   A project has commercial/financial records distinct from task
    execution.
-   An allocation plan has versioned lines tied to sections and/or
    people.
-   A payout references an approved entitlement/allocation and ledger
    movement.
-   A ledger transaction may have multiple ledger entries if using
    double-entry accounting.
-   Approval requests point to the entity being approved and keep
    immutable decision history.

Use foreign keys, uniqueness constraints, check constraints where
practical, and database transactions. Avoid storing important relational
data as unvalidated JSON blobs. JSON may be used for carefully scoped
metadata or audit diffs.

------------------------------------------------------------------------

## 17. API design expectations

Use REST endpoints with consistent naming, pagination, validation,
authorization and OpenAPI documentation. Exact endpoint names may follow
the chosen conventions, but cover at least:

-   `/auth/*`
-   `/users`, `/roles`, `/permissions`
-   `/projects`, `/projects/:id/members`, `/projects/:id/sections`
-   `/sections`, `/sections/:id/members`
-   `/tasks`, `/tasks/:id/assignees`, `/tasks/:id/comments`,
    `/tasks/:id/time-entries`
-   `/clients`, `/contracts`
-   `/invoices`, `/invoices/:id/collections`
-   `/expenses`
-   `/allocation-plans`, `/allocation-plans/:id/submit`, `/approve`,
    `/supersede`
-   `/financial-accounts`, `/ledger-transactions`
-   `/payouts`, `/payouts/:id/approve`, `/payouts/:id/mark-paid`
-   `/approvals`
-   `/reports/*`
-   `/notifications`
-   `/audit-logs`
-   `/settings`

Protect state transitions on the server; do not allow a client to set a
protected status directly if an approval/action endpoint is required.

------------------------------------------------------------------------

## 18. Security, privacy and resilience

Required baseline: - password hashing using a recognized library and
appropriate modern algorithm; - secure session handling and cookie
settings; - rate limiting on authentication and sensitive endpoints; -
CSRF protections appropriate to the chosen auth strategy; - XSS
prevention and output escaping; - strict request validation; - safe
upload handling and access-controlled downloads; - least-privilege
authorization; - secrets excluded from source control; - HTTPS in
production; - security headers; - dependency vulnerability checks; -
audit logs for sensitive actions; - encrypted backups and access
control; - restore drill documented and tested; - protection against
duplicate payment/collection submissions; - transaction-safe financial
state changes; - test for insecure direct object references
(IDOR/BOLA); - production seed credentials disabled or changed before
deployment.

MFA is recommended for founder/admin accounts. Implement using a proven
provider/library or document as a production blocker; do not simulate
MFA.

------------------------------------------------------------------------

## 19. Test strategy

### Unit tests

-   allocation math and rounding;
-   percentages and residual balances;
-   profit/cash distinction;
-   state transition rules;
-   permission evaluation;
-   progress calculation;
-   ledger balance logic.

### Integration tests

-   project/section/task CRUD with authorization;
-   multiple assignees;
-   task dependency cycle prevention;
-   invoices and partial collections;
-   expenses and approvals;
-   allocation plan versioning;
-   payouts and duplicate protection;
-   ledger transaction atomicity;
-   audit log creation;
-   export scope.

### Security tests

-   employee cannot access unauthorized project;
-   employee cannot read finance endpoints without permission;
-   user cannot grant higher permissions;
-   user cannot approve an operation outside their authority;
-   direct API calls cannot bypass UI controls;
-   IDOR/BOLA checks;
-   authentication rate limits;
-   sensitive exports are scoped.

### End-to-end flows

1.  Create project → add sections → assign members → create tasks →
    complete/review tasks.
2.  Create client/contract → issue invoice → record partial collection →
    view receivable.
3.  Create allocation plan → validate → submit → approve → generate
    entitlement → approve payout → mark paid.
4.  Record expense → approve → record payment/ledger movement.
5.  Allocate collected cash among treasury categories → transfer between
    categories → verify no duplicate income.
6.  Change an approved allocation → verify prior version and payouts
    remain unchanged.
7.  Test employee and viewer roles against all sensitive screens and
    APIs.

------------------------------------------------------------------------

## 20. UI/UX guidance

Create a premium, clean, professional SaaS interface suitable for an
internal software company. Prioritize clarity and density of useful
information rather than flashy effects.

Recommended direction: - dark navy/neutral base with a restrained orange
accent if aligned with company branding; - light and dark theme only if
easy to maintain consistently; - consistent spacing and typography; -
readable tables with filters and saved views where practical; - Kanban
board and table view for tasks; - clear timeline and deadline
indicators; - allocation editor with live calculations, warnings and a
preview before saving; - treasury dashboard showing actual balance,
reserved amount and available balance separately; - accessible dialogs
for destructive/sensitive actions; - responsive layout on laptop, tablet
and phone.

Do not use fake charts or fake financial numbers in production. Demo
seed values must be clearly identified as sample data. Empty states
should guide users to create their first project or configure company
settings.

------------------------------------------------------------------------

## 21. Environment, setup and deployment

Provide: - `.env.example` with names and safe placeholders only; - clear
instructions for installing dependencies; - local PostgreSQL setup; -
migration and seed commands; - development server commands; -
test/lint/typecheck commands; - build commands; - deployment guide; -
backup and restore guide; - admin bootstrap guide; - instructions to
rotate secrets and remove demo data before production.

Do not hard-code database credentials. Do not require GitHub for local
development. Git is recommended for version history, but the project
should be runnable directly from the local workspace.

### Safe command behavior

Before any destructive database command, explain what it does and
require explicit approval if it can erase data. Prefer additive
migrations. Never run `db push --force-reset`, database drop/reset, or
destructive cleanup against a user's existing database without clear
authorization.

------------------------------------------------------------------------

## 22. Phase-by-phase implementation plan and definition of done

### Phase 0 --- Assessment and scaffolding

-   inspect repository and runtime;
-   decide monorepo layout (for example `apps/web`, `apps/api`,
    `packages/shared`);
-   establish formatting, linting, type checks, test setup;
-   configure environment variables;
-   scaffold database/ORM;
-   create README and implementation docs;
-   create consistent UI shell and navigation;
-   provide health check. **Done when:** app and API start locally,
    database connection works, checks pass, and setup is documented.

### Phase 1 --- Identity, permissions, projects and tasks

-   authentication and secure bootstrap;
-   users, roles, permission registry, resource scopes;
-   project CRUD and archive;
-   configurable sections and section members;
-   tasks, subtasks, multi-assignees, dependencies, comments,
    attachments metadata;
-   task filters, My Tasks, Kanban/list;
-   timeline/calendar baseline;
-   audit logging for core project changes;
-   seed five fictional founders and sample project data. **Done when:**
    five users can sign in, collaborate, and access only permitted
    records; API authorization tests pass.

### Phase 2 --- Client and project finance

-   clients and contracts;
-   invoices and line items;
-   partial collections and receivables;
-   expenses and receipts;
-   allocation plan drafts and versioning;
-   section/member allocation methods;
-   contribution records;
-   compensation entitlement states;
-   allocation math tests. **Done when:** a sample project can be
    priced, invoiced, partially collected, allocated, and reconciled
    without floating-point errors.

### Phase 3 --- Treasury, ledger and approvals

-   treasury categories/accounts;
-   ledger model and transaction services;
-   company allocation rules;
-   internal transfers;
-   approval requests and decisions;
-   payout requests, approval and marking paid;
-   reversal/correction flows;
-   audit log hardening and duplicate protection. **Done when:** every
    treasury movement is traceable, balances reconcile, duplicate
    operations are rejected, and approved history is preserved.

### Phase 4 --- Reports, notifications and hardening

-   financial and project reports;
-   CSV/XLSX exports and PDF if practical;
-   notifications and reminders;
-   workload view;
-   audit search;
-   backup/restore docs;
-   accessibility and responsive improvements;
-   dependency/security checks. **Done when:** reports match underlying
    records and export permissions are enforced.

### Phase 5 --- End-to-end validation and production readiness

-   full E2E flows;
-   access control matrix tests;
-   concurrency/idempotency tests for money movement;
-   production configuration review;
-   secure admin bootstrap;
-   backup restore drill;
-   deploy documentation;
-   remove or disable demo credentials and sample data for production.
    **Done when:** critical security/financial tests pass and
    deployment/restore steps are documented and reproducible.

------------------------------------------------------------------------

## 23. Acceptance criteria checklist

-   [ ] Five founders can work in parallel.
-   [ ] New employees can be added without schema changes.
-   [ ] Projects can have custom sections.
-   [ ] A section can have multiple members.
-   [ ] A member can work across sections and projects.
-   [ ] Tasks support subtasks, multi-assignees, dependencies, reviews,
    and deadlines.
-   [ ] Timeline shows overdue and upcoming work.
-   [ ] Permissions are enforced server-side and scoped by resource.
-   [ ] Project price is separate from invoiced and collected amounts.
-   [ ] Expenses are recorded and approval controlled.
-   [ ] Section and member allocations are configurable.
-   [ ] Allocation plans are versioned and approved snapshots stay
    unchanged.
-   [ ] Planned, approved/earned and paid amounts are distinct.
-   [ ] Treasury categories and allocation rules are configurable.
-   [ ] Internal transfers are not counted as new revenue.
-   [ ] Posted ledger entries cannot be silently edited/deleted.
-   [ ] Duplicate collection/payout operations are prevented.
-   [ ] Founder ownership, project compensation and profit distribution
    are separate concepts.
-   [ ] Reports and exports respect permissions.
-   [ ] Sensitive actions are audited.
-   [ ] Backups and restoration are documented and tested.
-   [ ] No core screen contains non-functional buttons or
    placeholder-only logic.
-   [ ] Lint, typecheck, unit tests and critical integration/E2E tests
    pass.

------------------------------------------------------------------------

## 24. Product decisions that must remain configurable

Do not assume the following: - the real names/emails of the five
founders; - equity/ownership percentages; - default project allocation
percentages; - treasury allocation percentages; - when compensation
becomes earned; - whether payout approval requires one or two people; -
the company's tax treatment; - which financial basis is used for company
allocations (gross collections vs net profit); - whether task completion
requires review; - working days, holidays, time zone; - whether
multi-currency is enabled; - whether email/MFA integrations are
configured.

Use safe, transparent defaults for development and record all
non-trivial assumptions in `docs/DECISIONS.md`.

------------------------------------------------------------------------

## 25. Required first response from OpenCode

When this specification is supplied, the coding agent's first response
should: 1. summarize the detected repository state; 2. list the
top-level implementation steps; 3. identify any genuine blockers; 4.
state the first vertical slice it will implement; 5. then begin work
without waiting for another confirmation, unless an action risks
destroying user data or requires a missing secret.

The goal is a reliable, testable internal management system---not a
superficial UI mockup.
