import { describe, expect, it } from 'vitest'
import { getVisibleNavSections } from '@/composables/useNavSections'

const routeMetaByName = {
  AppointmentList: { roles: ['staff', 'reviewer', 'administrator'] },
  AppointmentVersions: { roles: ['reviewer', 'administrator'] },
  WaitlistView: { roles: ['staff', 'administrator'] },
  FeeAssessmentList: { roles: ['staff', 'reviewer', 'administrator'] },
  PaymentPost: { roles: ['staff', 'administrator'] },
  LedgerView: { roles: ['administrator'] },
  ReconciliationImport: { roles: ['reviewer', 'administrator'] },
  ReconciliationExceptions: { roles: ['reviewer', 'administrator'] },
  AnomalyAlerts: { roles: ['reviewer', 'administrator'] },
  UserManagement: { roles: ['administrator'] },
  AccountModeration: { roles: ['administrator'] },
  RecycleView: { roles: ['administrator'] },
  AuditLogs: { roles: ['reviewer', 'administrator'] },
  ReportsView: { roles: ['reviewer', 'administrator'] }
}

const labels = sections => sections.flatMap(section => section.items.map(item => item.label))

describe('useNavSections', () => {
  it('staff sees Appointments, Waitlist, Fee Assessments, Post Payment - NOT Ledger or Admin', () => {
    const staffLabels = labels(getVisibleNavSections('staff', routeMetaByName))
    expect(staffLabels).toContain('Appointments')
    expect(staffLabels).toContain('Waitlist')
    expect(staffLabels).toContain('Fee Assessments')
    expect(staffLabels).toContain('Post Payment')
    expect(staffLabels).not.toContain('Ledger')
    expect(staffLabels).not.toContain('User Management')
  })

  it('reviewer sees Reconciliation group, Audit Logs, Reports - NOT Ledger or User Management', () => {
    const reviewerLabels = labels(getVisibleNavSections('reviewer', routeMetaByName))
    expect(reviewerLabels).toContain('Import CSV')
    expect(reviewerLabels).toContain('Exceptions')
    expect(reviewerLabels).toContain('Anomaly Alerts')
    expect(reviewerLabels).toContain('Audit Logs')
    expect(reviewerLabels).toContain('Reports')
    expect(reviewerLabels).not.toContain('Ledger')
    expect(reviewerLabels).not.toContain('User Management')
  })

  it('administrator sees all sections including Ledger, User Management, Recycle Bin', () => {
    const adminLabels = labels(getVisibleNavSections('administrator', routeMetaByName))
    expect(adminLabels).toContain('Ledger')
    expect(adminLabels).toContain('User Management')
    expect(adminLabels).toContain('Account Moderation')
    expect(adminLabels).toContain('Recycle Bin')
    expect(adminLabels).toContain('Audit Logs')
    expect(adminLabels).toContain('Reports')
  })

  it('unknown role sees no protected sections', () => {
    const unknownLabels = labels(getVisibleNavSections('guest', routeMetaByName))
    expect(unknownLabels).toHaveLength(0)
  })
})
