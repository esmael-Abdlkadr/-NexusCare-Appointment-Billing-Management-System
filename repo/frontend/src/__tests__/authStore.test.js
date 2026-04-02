import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('axios', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn()
  }
}))

vi.mock('@/router', () => ({
  default: {
    push: vi.fn()
  }
}))

import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.removeItem('user')
  })

  it('clearUser resets user and verification timestamp', () => {
    const store = useAuthStore()
    store.user = { identifier: 'staff001' }
    store.lastVerified = Date.now()

    store.clearUser()

    expect(store.user).toBeNull()
    expect(store.lastVerified).toBe(0)
  })

  it('clearUser does not leave user key in localStorage', () => {
    const store = useAuthStore()
    store.clearUser()
    expect(localStorage.getItem('user')).toBeNull()
  })

  it('init calls /auth/me and updates user', async () => {
    axios.get.mockResolvedValueOnce({
      data: {
        data: {
          user: {
            id: 1,
            identifier: 'admin001',
            role: 'administrator'
          }
        }
      }
    })

    const store = useAuthStore()
    await store.init()

    expect(axios.get).toHaveBeenCalledWith('/auth/me')
    expect(store.user).toEqual({ id: 1, identifier: 'admin001', role: 'administrator' })
    expect(store.lastVerified).toBeGreaterThan(0)
  })
})
