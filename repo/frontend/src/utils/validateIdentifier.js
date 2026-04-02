const IDENTIFIER_REGEX = /^[A-Za-z0-9][A-Za-z0-9._@\-]*$/

export const validateIdentifier = identifier => {
  if (typeof identifier !== 'string') return false

  const trimmed = identifier.trim()
  if (trimmed.length < 3 || trimmed.length > 100) return false

  return IDENTIFIER_REGEX.test(trimmed)
}
