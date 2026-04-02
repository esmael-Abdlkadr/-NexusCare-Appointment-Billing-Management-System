import { defineStore } from 'pinia'
import axios from 'axios'
import router from '@/router'
import { logger } from '@/utils/logger'

const clearLegacyAuthStorage = () => {
  if (typeof window === 'undefined' || !window.localStorage) {
    return
  }

  const keys = Object.keys(window.localStorage)
  keys.forEach(key => {
    const lowered = key.toLowerCase()
    if (lowered === 'user' || lowered.includes('token') || lowered.includes('jwt') || lowered.includes('auth')) {
      window.localStorage.removeItem(key)
    }
  })
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    lastVerified: 0
  }),
  actions: {
    async login(credentials) {
      clearLegacyAuthStorage()
      try {
        const response = await axios.post('/auth/login', credentials)
        const data = response.data?.data || {}

        this.user = data.user || null
        this.lastVerified = Date.now()

        logger.info('Auth: login succeeded', { role: this.user?.role, identifier: this.user?.identifier })
        return data
      } catch (error) {
        logger.warn('Auth: login failed', { status: error?.response?.status })
        throw error
      }
    },
    async init(force = false) {
      clearLegacyAuthStorage()
      try {
        const response = await axios.get('/auth/me')
        const user = response.data?.data?.user || response.data?.data || null
        this.user = user
        this.lastVerified = Date.now()
        logger.info('Auth: session restored', { role: this.user?.role })
      } catch (error) {
        logger.warn('Auth: session check failed — user treated as unauthenticated', { status: error?.response?.status })
        throw error
      }
    },
    clearUser() {
      clearLegacyAuthStorage()
      this.user = null
      this.lastVerified = 0
    },
    async logout() {
      try {
        await axios.post('/auth/logout')
      } catch (error) {
        logger.warn('Auth: server-side logout request failed', { status: error?.response?.status })
      }

      this.clearUser()
      logger.info('Auth: user logged out')

      await router.push('/login')
    }
  }
})
