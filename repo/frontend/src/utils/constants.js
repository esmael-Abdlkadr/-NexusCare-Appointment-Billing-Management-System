// Appointment statuses in lifecycle order
export const APPOINTMENT_STATUSES = [
  'requested',
  'confirmed',
  'checked_in',
  'no_show',
  'completed',
  'cancelled'
]

// Element Plus tag type per appointment status
export const APPOINTMENT_STATUS_TAG = {
  requested: 'info',
  confirmed: 'success',
  checked_in: 'warning',
  no_show: 'danger',
  completed: 'success',
  cancelled: 'info'
}

// Fee assessment statuses
export const FEE_STATUSES = ['pending', 'paid', 'waived', 'written_off']

// Human-readable fee assessment status labels
export const FEE_STATUS_LABEL = {
  pending: 'Pending',
  paid: 'Paid',
  waived: 'Waived',
  written_off: 'Written Off'
}

// Fee type keys
export const FEE_TYPES = ['no_show', 'overdue', 'lost_damaged']

// Human-readable fee type labels
export const FEE_TYPE_LABEL = {
  no_show: 'No Show',
  overdue: 'Overdue',
  lost_damaged: 'Lost/Damaged'
}

// Ledger entry types that represent credits (reduce the net total)
export const LEDGER_CREDIT_TYPES = ['refund', 'waiver', 'writeoff']

// Service type autocomplete suggestions for appointment creation
export const SERVICE_TYPE_SUGGESTIONS = [
  'General Consultation',
  'Follow-up Visit',
  'Lab Review',
  'Physiotherapy',
  'Dental Checkup',
  'Eye Exam',
  'Blood Test',
  'X-Ray',
  'Mental Health Session',
  'Nutrition Consult'
]
