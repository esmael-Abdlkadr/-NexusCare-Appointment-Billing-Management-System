import { describe, expect, it } from 'vitest'
import { maskPayload, SENSITIVE_KEYS } from '@/utils/maskPayload'

describe('maskPayload', () => {
  it('redacts password key', () => {
    expect(maskPayload({ password: 'Secret@123' })).toEqual({ password: '***REDACTED***' })
  })

  it('redacts government_id key', () => {
    expect(maskPayload({ government_id: 'GOV-12345' })).toEqual({ government_id: '***REDACTED***' })
  })

  it('passes through safe identifier key unchanged', () => {
    expect(maskPayload({ identifier: 'staff001' })).toEqual({ identifier: 'staff001' })
  })

  it('redacts nested token fields', () => {
    expect(maskPayload({ inner: { token: 'abc' } })).toEqual({ inner: { token: '***REDACTED***' } })
  })

  it('returns non-object payload as-is', () => {
    expect(maskPayload('raw')).toBe('raw')
    expect(maskPayload(null)).toBeNull()
  })

  it('redacts phone key', () => {
    expect(maskPayload({ phone: '+1-555-0100' })).toEqual({ phone: '***REDACTED***' })
  })

  it('redacts credit_card key', () => {
    expect(maskPayload({ credit_card: '4111111111111111' })).toEqual({ credit_card: '***REDACTED***' })
  })

  it('redacts ssn key', () => {
    expect(maskPayload({ ssn: '123-45-6789' })).toEqual({ ssn: '***REDACTED***' })
  })

  it('redacts access_token key', () => {
    expect(maskPayload({ access_token: 'eyJhbGci...' })).toEqual({ access_token: '***REDACTED***' })
  })

  it('redacts sensitive fields 3+ levels deep', () => {
    const input = { user: { session: { data: { password_hash: '$2y$...' } } } }
    const result = maskPayload(input)
    expect(result.user.session.data.password_hash).toBe('***REDACTED***')
  })

  it('redacts sensitive fields in array of objects', () => {
    const input = [{ id: 1, password: 'secret' }, { id: 2, password: 'another' }]
    const result = maskPayload(input)
    expect(result[0].password).toBe('***REDACTED***')
    expect(result[1].password).toBe('***REDACTED***')
    expect(result[0].id).toBe(1)
  })

  it('redacts partial key matches (e.g. user_token, phone_number)', () => {
    expect(maskPayload({ user_token: 'abc', phone_number: '555' }))
      .toEqual({ user_token: '***REDACTED***', phone_number: '***REDACTED***' })
  })

  it('preserves non-sensitive sibling keys when redacting', () => {
    const input = { name: 'Alice', password: 'secret', role: 'staff' }
    const result = maskPayload(input)
    expect(result.name).toBe('Alice')
    expect(result.role).toBe('staff')
    expect(result.password).toBe('***REDACTED***')
  })

  it('SENSITIVE_KEYS list includes all required fields', () => {
    const required = ['password', 'government_id', 'token', 'phone', 'credit_card', 'ssn', 'secret']
    required.forEach(key => {
      expect(SENSITIVE_KEYS.some(s => s === key || key.includes(s) || s.includes(key))).toBe(true)
    })
  })
})
