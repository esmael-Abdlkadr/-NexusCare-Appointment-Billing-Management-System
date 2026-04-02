import { APIRequestContext } from '@playwright/test'

const BASE = 'http://localhost:80/api'

export async function apiToken(request: APIRequestContext, identifier: string, password: string): Promise<string> {
  const res = await request.post(`${BASE}/auth/login`, {
    headers: { 'X-Client-Type': 'api' },
    data: { identifier, password }
  })
  const body = await res.json()
  return body?.data?.access_token || body?.data?.token || body?.token || ''
}

export async function apiGet(request: APIRequestContext, token: string, path: string, params = {}) {
  const url = new URL(`${BASE}${path}`)
  Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, String(v)))
  const res = await request.get(url.toString(), {
    headers: { Authorization: `Bearer ${token}` }
  })
  return res.json()
}

export async function apiPost(request: APIRequestContext, token: string, path: string, data: object) {
  const res = await request.post(`${BASE}${path}`, {
    headers: { Authorization: `Bearer ${token}` },
    data
  })
  return res.json()
}

export async function apiDelete(request: APIRequestContext, token: string, path: string) {
  const res = await request.delete(`${BASE}${path}`, {
    headers: { Authorization: `Bearer ${token}` }
  })
  return res.json()
}
