import { describe, expect, it } from 'vitest'
import { validateIdentifier } from '@/utils/validateIdentifier'

describe('validateIdentifier', () => {
  it('accepts valid identifiers', () => {
    expect(validateIdentifier('staff001')).toBe(true)
    expect(validateIdentifier('john@clinic.org')).toBe(true)
  })

  it('rejects too short identifiers', () => {
    expect(validateIdentifier('ab')).toBe(false)
  })

  it('rejects leading special characters', () => {
    expect(validateIdentifier('.leading')).toBe(false)
  })

  it('rejects spaces', () => {
    expect(validateIdentifier('user name')).toBe(false)
  })

  it('rejects identifiers over 100 chars', () => {
    expect(validateIdentifier('a'.repeat(101))).toBe(false)
  })
})
