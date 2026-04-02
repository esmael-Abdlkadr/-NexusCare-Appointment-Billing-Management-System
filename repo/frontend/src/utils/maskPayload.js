export const SENSITIVE_KEYS = [
  'password',
  'password_hash',
  'government_id',
  'phone',
  'token',
  'access_token',
  'secret',
  'credit_card',
  'ssn'
]

export const maskPayload = payload => {
  if (!payload || typeof payload !== 'object') return payload

  const masked = Array.isArray(payload) ? [] : {}

  for (const [key, value] of Object.entries(payload)) {
    const isSensitive = SENSITIVE_KEYS.some(sensitiveKey => key.toLowerCase().includes(sensitiveKey))
    masked[key] = isSensitive ? '***REDACTED***' : (typeof value === 'object' ? maskPayload(value) : value)
  }

  return masked
}
