import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { redact, SENSITIVE_KEYS, logger } from '@/utils/logger'

describe('logger redact — imported from real module', () => {
  it('redacts top-level sensitive key', () => {
    expect(redact({ password: 'x' })).toEqual({ password: '[REDACTED]' })
  })

  it('redacts nested sensitive key 2 levels deep', () => {
    expect(redact({ user: { token: 'abc' } })).toEqual({ user: { token: '[REDACTED]' } })
  })

  it('redacts nested sensitive key 3 levels deep', () => {
    const input = { data: { session: { access_token: 'tok' } } }
    expect(redact(input)).toEqual({ data: { session: { access_token: '[REDACTED]' } } })
  })

  it('handles array of objects — redacts sensitive fields inside each item', () => {
    const input = [{ id: 1, government_id: 'GOV-1' }, { id: 2, government_id: 'GOV-2' }]
    const result = redact(input)
    expect(result[0].government_id).toBe('[REDACTED]')
    expect(result[1].government_id).toBe('[REDACTED]')
    expect(result[0].id).toBe(1)
  })

  it('preserves safe sibling keys untouched', () => {
    const result = redact({ role: 'staff', secret: 'x', name: 'Alice' })
    expect(result.role).toBe('staff')
    expect(result.name).toBe('Alice')
    expect(result.secret).toBe('[REDACTED]')
  })

  it('returns primitives unchanged', () => {
    expect(redact('plain')).toBe('plain')
    expect(redact(42)).toBe(42)
    expect(redact(null)).toBeNull()
  })

  it('stops recursion at depth > 5 to prevent stack overflow', () => {
    const deep = { a: { b: { c: { d: { e: { f: { password: 'buried' } } } } } } }
    expect(() => redact(deep)).not.toThrow()
  })

  it('redacts partial key matches (user_token, password_hash)', () => {
    const result = redact({ user_token: 'tok', password_hash: '$2y$...' })
    expect(result.user_token).toBe('[REDACTED]')
    expect(result.password_hash).toBe('[REDACTED]')
  })

  it('SENSITIVE_KEYS list includes all required sensitive field names', () => {
    const required = ['password', 'token', 'government_id', 'secret', 'access_token']
    required.forEach(key => {
      expect(SENSITIVE_KEYS).toContain(key)
    })
  })
})

describe('smoke test — sensitive data never survives redaction', () => {
  it('serialised output of a realistic API response payload contains no raw sensitive values', () => {
    const dangerousPayload = {
      password: 'MySecret123!',
      token: 'eyJhbGciOiJIUzI1NiJ9.payload.sig',
      government_id: 'GOV-99999',
      secret: 'topsecretvalue',
      access_token: 'bearer-token-xyz',
      safe_name: 'Alice',
      nested: {
        user: {
          token: 'nested-token-value',
          role: 'admin'
        }
      }
    }

    const redacted = redact(dangerousPayload)
    const serialised = JSON.stringify(redacted)

    expect(serialised).not.toContain('MySecret123!')
    expect(serialised).not.toContain('eyJhbGciOiJIUzI1NiJ9.payload.sig')
    expect(serialised).not.toContain('GOV-99999')
    expect(serialised).not.toContain('topsecretvalue')
    expect(serialised).not.toContain('bearer-token-xyz')
    expect(serialised).not.toContain('nested-token-value')
    expect(serialised).toContain('[REDACTED]')
    expect(serialised).toContain('Alice')
    expect(serialised).toContain('admin')
  })
})

describe('logger — console is silent by default (VITE_ENABLE_CONSOLE_LOG not set)', () => {
  beforeEach(() => {
    vi.spyOn(console, 'info').mockImplementation(() => {})
    vi.spyOn(console, 'warn').mockImplementation(() => {})
    vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.spyOn(console, 'debug').mockImplementation(() => {})
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('logger.info does not call console.info when VITE_ENABLE_CONSOLE_LOG is not true', () => {
    logger.info('test message', { user: 'alice' })
    expect(console.info).not.toHaveBeenCalled()
  })

  it('logger.warn does not call console.warn when VITE_ENABLE_CONSOLE_LOG is not true', () => {
    logger.warn('test warning', { token: 'secret' })
    expect(console.warn).not.toHaveBeenCalled()
  })

  it('logger.error does not call console.error when VITE_ENABLE_CONSOLE_LOG is not true', () => {
    logger.error('test error', { access_token: 'tok123' })
    expect(console.error).not.toHaveBeenCalled()
  })
})
