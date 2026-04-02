export const extractError = (error, fallback = 'Request failed.') => {
  const body = error?.response?.data

  // Laravel standard 422: { message, errors: { field: ["msg"] } }
  if (body?.errors && typeof body.errors === 'object') {
    const msgs = Object.values(body.errors).flat()
    if (msgs.length) return msgs.join(' ')
  }

  // Custom API format: { success: false, error: "VALIDATION_ERROR", data: { field: ["msg"] } }
  if (body?.error === 'VALIDATION_ERROR' && body?.data && typeof body.data === 'object') {
    const msgs = Object.values(body.data).flat()
    if (msgs.length) return msgs.join(' ')
  }

  return body?.error || body?.message || fallback
}
