// Explicit identifier format categories per prompt requirements:
// 1. Employee ID: alphanumeric with optional dots, dashes, underscores (e.g. "staff001", "emp-123")
// 2. Student ID: same character set as employee ID (e.g. "STU-12345", "student001")
// 3. Email-style username: local@domain format (e.g. "john@clinic.org")

const EMPLOYEE_STUDENT_ID_REGEX = /^[A-Za-z0-9][A-Za-z0-9._\-]{2,99}$/
const EMAIL_STYLE_REGEX = /^[A-Za-z0-9][A-Za-z0-9._\-]*@[A-Za-z0-9][A-Za-z0-9.\-]*\.[A-Za-z]{2,}$/

export const validateIdentifier = identifier => {
  if (typeof identifier !== 'string') return false

  const trimmed = identifier.trim()
  if (trimmed.length < 3 || trimmed.length > 100) return false

  return EMPLOYEE_STUDENT_ID_REGEX.test(trimmed) || EMAIL_STYLE_REGEX.test(trimmed)
}

export const identifierFormatHint =
  'Enter an employee ID (e.g. staff001), student ID (e.g. STU-12345), or email (e.g. user@clinic.org)'
