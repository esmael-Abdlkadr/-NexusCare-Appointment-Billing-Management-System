import { computed, unref } from 'vue'

export const NAV_SECTIONS = [
  {
    title: 'SCHEDULING',
    items: [
      { label: 'Appointments', index: '/appointments', routeName: 'AppointmentList' },
      { label: 'Appointment History', index: '__appointment_history__', routeName: 'AppointmentVersions' },
      { label: 'Waitlist', index: '/waitlist', routeName: 'WaitlistView' }
    ]
  },
  {
    title: 'BILLING',
    items: [
      { label: 'Fee Assessments', index: '/fees', routeName: 'FeeAssessmentList' },
      { label: 'Post Payment', index: '/payments/post', routeName: 'PaymentPost' },
      { label: 'Ledger', index: '/ledger', routeName: 'LedgerView' }
    ]
  },
  {
    title: 'RECONCILIATION',
    items: [
      { label: 'Import CSV', index: '/reconciliation/import', routeName: 'ReconciliationImport' },
      { label: 'Exceptions', index: '/reconciliation/exceptions', routeName: 'ReconciliationExceptions' },
      { label: 'Anomaly Alerts', index: '/reconciliation/anomalies', routeName: 'AnomalyAlerts' }
    ]
  },
  {
    title: 'ADMIN',
    items: [
      { label: 'Fee Rules', index: '/fee-rules', routeName: 'FeeRules', icon: 'Setting', route: '/fee-rules', roles: ['administrator'] },
      { label: 'User Management', index: '/admin/users', routeName: 'UserManagement' },
      { label: 'Content Moderation', index: '/admin/moderation', routeName: 'ContentModeration' },
      { label: 'Recycle Bin', index: '/admin/recycle', routeName: 'RecycleView' }
    ]
  },
  {
    title: 'COMPLIANCE',
    items: [
      { label: 'Audit Logs', index: '/audit-logs', routeName: 'AuditLogs' },
      { label: 'Reports', index: '/reports', routeName: 'ReportsView' }
    ]
  }
]

const canAccess = (userRole, routeMeta, itemRoles) => {
  const roles = routeMeta?.roles || itemRoles
  if (!Array.isArray(roles) || roles.length === 0) {
    return true
  }
  return roles.includes(userRole)
}

export const getVisibleNavSections = (userRole, routeMetaByName = {}) => {
  return NAV_SECTIONS
    .map(section => {
      const items = section.items.filter(item => canAccess(userRole, routeMetaByName[item.routeName], item.roles))
      return { ...section, items }
    })
    .filter(section => section.items.length > 0)
}

export const useNavSections = (roleRef, routeMetaByNameRef) => {
  return computed(() => getVisibleNavSections(unref(roleRef), unref(routeMetaByNameRef) || {}))
}
