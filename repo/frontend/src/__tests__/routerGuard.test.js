/**
 * Route-integration tests for the navigation guard (applyNavigationGuard).
 *
 * These sit between pure unit tests (composable logic) and full E2E browser
 * tests. They exercise the guard's three decision branches:
 *   1. Public routes → always allowed
 *   2. Protected routes, unauthenticated → redirect /login
 *   3. Protected routes, wrong role → redirect /forbidden
 *   4. Protected routes, correct role → allowed
 *   5. authStore.init() throws → clear user, redirect /login
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { applyNavigationGuard } from '@/router/index.js'

// ── helpers ──────────────────────────────────────────────────────────────────

const makeAuthStore = ({ user = null, initThrows = false } = {}) => ({
  user,
  clearUser: vi.fn(),
  init: initThrows
    ? vi.fn().mockRejectedValue(new Error('401'))
    : vi.fn().mockResolvedValue(undefined),
})

const makeRoute = ({ path = '/', meta = {} } = {}) => ({ path, meta })

const captureNext = () => {
  const calls = []
  const next = vi.fn(arg => calls.push(arg ?? 'ALLOWED'))
  next.calledWith = arg => calls.includes(arg)
  next.wasAllowed = () => calls.includes('ALLOWED')
  return next
}

// ── 1. Public routes ──────────────────────────────────────────────────────────

describe('public routes', () => {
  it('allows unauthenticated access to /login', async () => {
    const to = makeRoute({ path: '/login', meta: { public: true } })
    const next = captureNext()
    const authStore = makeAuthStore({ user: null })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next.wasAllowed()).toBe(true)
    expect(authStore.init).not.toHaveBeenCalled()
  })

  it('allows unauthenticated access to /forbidden', async () => {
    const to = makeRoute({ path: '/forbidden', meta: { public: true } })
    const next = captureNext()

    await applyNavigationGuard(to, {}, next, makeAuthStore())

    expect(next.wasAllowed()).toBe(true)
  })
})

// ── 2. Unauthenticated user on protected route ────────────────────────────────

describe('unauthenticated user', () => {
  it('redirects to /login when no user after init', async () => {
    const to = makeRoute({ path: '/appointments', meta: { roles: ['staff', 'administrator'] } })
    const next = captureNext()
    const authStore = makeAuthStore({ user: null })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next).toHaveBeenCalledWith('/login')
  })

  it('redirects to /login when init throws (e.g. 401)', async () => {
    const to = makeRoute({ path: '/ledger', meta: { roles: ['administrator'] } })
    const next = captureNext()
    const authStore = makeAuthStore({ initThrows: true })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(authStore.clearUser).toHaveBeenCalled()
    expect(next).toHaveBeenCalledWith('/login')
  })
})

// ── 3. Wrong role on protected route ─────────────────────────────────────────

describe('role enforcement', () => {
  it('redirects staff to /forbidden when accessing admin-only /ledger', async () => {
    const to = makeRoute({ path: '/ledger', meta: { roles: ['administrator'] } })
    const next = captureNext()
    const authStore = makeAuthStore({ user: { role: 'staff' } })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next).toHaveBeenCalledWith('/forbidden')
  })

  it('redirects staff to /forbidden when accessing /fee-rules', async () => {
    const to = makeRoute({ path: '/fee-rules', meta: { roles: ['administrator'] } })
    const next = captureNext()
    const authStore = makeAuthStore({ user: { role: 'staff' } })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next).toHaveBeenCalledWith('/forbidden')
  })

  it('redirects staff to /forbidden when accessing /admin/users', async () => {
    const to = makeRoute({ path: '/admin/users', meta: { roles: ['administrator'] } })
    const next = captureNext()
    const authStore = makeAuthStore({ user: { role: 'staff' } })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next).toHaveBeenCalledWith('/forbidden')
  })

  it('redirects staff to /forbidden when accessing reviewer-only /audit-logs', async () => {
    const to = makeRoute({ path: '/audit-logs', meta: { roles: ['reviewer', 'administrator'] } })
    const next = captureNext()
    const authStore = makeAuthStore({ user: { role: 'staff' } })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next).toHaveBeenCalledWith('/forbidden')
  })

  it('redirects reviewer to /forbidden for admin-only /ledger', async () => {
    const to = makeRoute({ path: '/ledger', meta: { roles: ['administrator'] } })
    const next = captureNext()
    const authStore = makeAuthStore({ user: { role: 'reviewer' } })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next).toHaveBeenCalledWith('/forbidden')
  })
})

// ── 4. Correct role — route allowed ──────────────────────────────────────────

describe('authorised access', () => {
  it('allows staff to access /appointments', async () => {
    const to = makeRoute({ path: '/appointments', meta: { roles: ['staff', 'reviewer', 'administrator'] } })
    const next = captureNext()
    const authStore = makeAuthStore({ user: { role: 'staff' } })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next.wasAllowed()).toBe(true)
  })

  it('allows reviewer to access /audit-logs', async () => {
    const to = makeRoute({ path: '/audit-logs', meta: { roles: ['reviewer', 'administrator'] } })
    const next = captureNext()
    const authStore = makeAuthStore({ user: { role: 'reviewer' } })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next.wasAllowed()).toBe(true)
  })

  it('allows admin to access /ledger', async () => {
    const to = makeRoute({ path: '/ledger', meta: { roles: ['administrator'] } })
    const next = captureNext()
    const authStore = makeAuthStore({ user: { role: 'administrator' } })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next.wasAllowed()).toBe(true)
  })

  it('allows admin to access /fee-rules', async () => {
    const to = makeRoute({ path: '/fee-rules', meta: { roles: ['administrator'] } })
    const next = captureNext()
    const authStore = makeAuthStore({ user: { role: 'administrator' } })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next.wasAllowed()).toBe(true)
  })

  it('allows routes with no role restriction for any authenticated user', async () => {
    const to = makeRoute({ path: '/dashboard', meta: {} })
    const next = captureNext()
    const authStore = makeAuthStore({ user: { role: 'staff' } })

    await applyNavigationGuard(to, {}, next, authStore)

    expect(next.wasAllowed()).toBe(true)
  })
})
