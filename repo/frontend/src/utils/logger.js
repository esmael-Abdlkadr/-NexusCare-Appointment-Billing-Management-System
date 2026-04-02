const isDev = import.meta.env.DEV
// Console logging is opt-in via VITE_ENABLE_CONSOLE_LOG=true.
// Silent by default so no data (even redacted) appears in the browser console
// unless a developer explicitly enables it for debugging sessions.
const loggingEnabled = isDev && import.meta.env.VITE_ENABLE_CONSOLE_LOG === 'true'

export const SENSITIVE_KEYS = ['password', 'token', 'government_id', 'secret', 'access_token']

export const redact = (data, depth = 0) => {
  if (!data || typeof data !== 'object' || depth > 5) return data
  if (Array.isArray(data)) return data.map(item => redact(item, depth + 1))
  const out = {}
  for (const [k, v] of Object.entries(data)) {
    if (SENSITIVE_KEYS.some(s => k.toLowerCase().includes(s))) {
      out[k] = '[REDACTED]'
    } else if (v && typeof v === 'object') {
      out[k] = redact(v, depth + 1)
    } else {
      out[k] = v
    }
  }
  return out
}

export const logger = {
  info:  (msg, ctx) => { if (loggingEnabled) console.info(`[INFO] ${msg}`,  ctx ? redact(ctx) : '') },
  warn:  (msg, ctx) => { if (loggingEnabled) console.warn(`[WARN] ${msg}`,  ctx ? redact(ctx) : '') },
  error: (msg, ctx) => { if (loggingEnabled) console.error(`[ERROR] ${msg}`, ctx ? redact(ctx) : '') },
  debug: (msg, ctx) => { if (loggingEnabled) console.debug(`[DEBUG] ${msg}`, ctx ? redact(ctx) : '') },
}
