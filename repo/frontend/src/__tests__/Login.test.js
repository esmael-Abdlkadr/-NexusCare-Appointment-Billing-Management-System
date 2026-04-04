import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { mount } from '@vue/test-utils'
import Login from '@/views/Login.vue'
import { useAuthStore } from '@/stores/auth'

const pushMock = vi.fn()

vi.mock('vue-router', async importOriginal => {
  const actual = await importOriginal()
  return {
    ...actual,
    useRouter: () => ({ push: pushMock })
  }
})

const mountLogin = () => {
  return mount(Login, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({
          stubActions: false,
          createSpy: vi.fn
        })
      ]
    }
  })
}

describe('Login.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders identifier and password inputs', () => {
    const wrapper = mountLogin()
    expect(wrapper.find('input[placeholder="Enter identifier"]').exists()).toBe(true)
    expect(wrapper.find('input[placeholder="Enter password"]').exists()).toBe(true)
  })

  it('shows error when identifier is empty on submit', async () => {
    const wrapper = mountLogin()
    await wrapper.get('button.signin-btn').trigger('click')
    expect(wrapper.text()).toContain('Please enter identifier and password.')
  })

  it('shows identifier format error for invalid identifier', async () => {
    const wrapper = mountLogin()
    await wrapper.get('input[placeholder="Enter identifier"]').setValue('@bad')
    await wrapper.get('input[placeholder="Enter password"]').setValue('ValidPass@123')
    await wrapper.get('button.signin-btn').trigger('click')
    expect(wrapper.text()).toContain('Enter a valid employee ID, student ID, or email-style username')
  })

  it('calls authStore.login with correct payload', async () => {
    const wrapper = mountLogin()
    const authStore = useAuthStore()
    const loginSpy = vi.spyOn(authStore, 'login').mockResolvedValue({})

    await wrapper.get('input[placeholder="Enter identifier"]').setValue('staff1')
    await wrapper.get('input[placeholder="Enter password"]').setValue('Staff@NexusCare1')
    await wrapper.get('button.signin-btn').trigger('click')

    expect(loginSpy).toHaveBeenCalledWith({
      identifier: 'staff1',
      password: 'Staff@NexusCare1'
    })
  })

  it('shows locked account message for ACCOUNT_LOCKED error', async () => {
    const wrapper = mountLogin()
    const authStore = useAuthStore()
    vi.spyOn(authStore, 'login').mockRejectedValue({
      response: {
        status: 423,
        data: {
          error: 'ACCOUNT_LOCKED',
          data: {
            locked_until: '2026-12-31T23:59:59Z'
          }
        }
      }
    })

    await wrapper.get('input[placeholder="Enter identifier"]').setValue('staff1')
    await wrapper.get('input[placeholder="Enter password"]').setValue('Staff@NexusCare1')
    await wrapper.get('button.signin-btn').trigger('click')

    expect(wrapper.text().toLowerCase()).toContain('locked')
  })
})
