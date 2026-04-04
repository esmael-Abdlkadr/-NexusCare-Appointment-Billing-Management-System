import { describe, expect, it } from 'vitest'
import { validateIdentifier, identifierFormatHint } from '@/utils/validateIdentifier'

describe('validateIdentifier', () => {
  // ── Accepted: Employee ID style ──────────────────────────────────────
  const acceptedEmployeeIds = [
    ['staff001', 'alphanumeric employee ID'],
    ['emp-123', 'employee ID with dash'],
    ['admin_user', 'employee ID with underscore'],
    ['mgr.site1', 'employee ID with dot'],
    ['A1B', 'minimum 3-char alphanumeric'],
    ['user-name_01.test', 'mixed separators']
  ]

  it.each(acceptedEmployeeIds)('accepts employee-ID "%s" (%s)', (id) => {
    expect(validateIdentifier(id)).toBe(true)
  })

  // ── Accepted: Student ID style ───────────────────────────────────────
  const acceptedStudentIds = [
    ['STU-12345', 'standard student ID'],
    ['student001', 'alphanumeric student ID'],
    ['S2026-0001', 'year-prefixed student ID'],
    ['grad.student-99', 'student ID with mixed separators']
  ]

  it.each(acceptedStudentIds)('accepts student-ID "%s" (%s)', (id) => {
    expect(validateIdentifier(id)).toBe(true)
  })

  // ── Accepted: Email-style username ───────────────────────────────────
  const acceptedEmails = [
    ['john@clinic.org', 'standard email'],
    ['user@example.com', 'simple email'],
    ['staff.member@site.hospital.net', 'dotted local + subdomain']
  ]

  it.each(acceptedEmails)('accepts email-style "%s" (%s)', (id) => {
    expect(validateIdentifier(id)).toBe(true)
  })

  // ── Rejected cases ───────────────────────────────────────────────────
  const rejected = [
    ['ab', 'too short (2 chars)'],
    ['a'.repeat(101), 'too long (101 chars)'],
    ['.leading', 'leading dot'],
    ['-leading', 'leading dash'],
    ['_leading', 'leading underscore'],
    ['@leading', 'leading @'],
    ['user name', 'contains space'],
    ['user;drop', 'SQL injection chars'],
    ['user<script>', 'HTML injection chars'],
    ['', 'empty string'],
    [123, 'non-string (number)'],
    [null, 'null'],
    [undefined, 'undefined']
  ]

  it.each(rejected)('rejects "%s" (%s)', (id) => {
    expect(validateIdentifier(id)).toBe(false)
  })

  // ── Format hint export ───────────────────────────────────────────────
  it('exports a user-facing format hint string', () => {
    expect(typeof identifierFormatHint).toBe('string')
    expect(identifierFormatHint.length).toBeGreaterThan(0)
  })
})
