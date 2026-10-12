<?php
// Permissions.php — port of permissions.registry.ts (53 keys + 5 role grants).
class Permissions {
  const ALL = [
    'dashboard.view',
    'users.view','users.invite','users.edit','users.deactivate',
    'roles.view','roles.manage','permissions.manage',
    'projects.view','projects.create','projects.edit','projects.archive','projects.manage_members',
    'sections.view','sections.create','sections.edit','sections.archive',
    'tasks.view','tasks.create','tasks.edit','tasks.assign','tasks.change_deadline','tasks.review','tasks.complete',
    'time_entries.create','time_entries.approve',
    'clients.view','clients.manage','contracts.view','contracts.manage',
    'invoices.view','invoices.create','invoices.manage',
    'payments.view','payments.record','payments.reverse',
    'expenses.view','expenses.create','expenses.approve',
    'allocations.view','allocations.create','allocations.edit','allocations.approve',
    'finance.view','finance.record_income','finance.record_expense',
    'finance.approve_payout','finance.execute_payout','finance.reverse_transaction',
    'budget.view','budget.manage',
    'reports.view','reports.export','audit.view','settings.manage',
  ];
  public static function grants($roleKey) {
    switch ($roleKey) {
      case 'FOUNDER_ADMIN': return self::ALL;
      case 'PROJECT_MANAGER': return ['dashboard.view','projects.view','projects.create','projects.edit','projects.manage_members','sections.view','sections.create','sections.edit','tasks.view','tasks.create','tasks.edit','tasks.assign','tasks.change_deadline','tasks.review','tasks.complete','time_entries.create','time_entries.approve','clients.view','reports.view'];
      case 'EMPLOYEE': return ['dashboard.view','projects.view','sections.view','tasks.view','tasks.create','tasks.edit','time_entries.create'];
      case 'FINANCE_MANAGER': return ['dashboard.view','clients.view','clients.manage','contracts.view','contracts.manage','invoices.view','invoices.create','invoices.manage','payments.view','payments.record','expenses.view','expenses.create','expenses.approve','allocations.view','allocations.create','allocations.edit','finance.view','finance.record_income','finance.record_expense','budget.view','reports.view','reports.export'];
      case 'VIEWER': return ['dashboard.view','projects.view','sections.view','tasks.view','reports.view'];
      default: return [];
    }
  }
}
