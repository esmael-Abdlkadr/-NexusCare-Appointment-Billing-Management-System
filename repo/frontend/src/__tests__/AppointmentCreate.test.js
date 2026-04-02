import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import AppointmentCreate from '@/views/AppointmentCreate.vue'

const pushMock = vi.fn()

const axiosGetMock = vi.fn(() => Promise.resolve({ data: { data: [] } }))
const axiosPostMock = vi.fn(() => Promise.resolve({ data: { success: true } }))

vi.mock('axios', () => ({
  default: {
    get: (...args) => axiosGetMock(...args),
    post: (...args) => axiosPostMock(...args)
  }
}))

vi.mock('vue-router', async importOriginal => {
  const actual = await importOriginal()
  return {
    ...actual,
    useRouter: () => ({ push: pushMock })
  }
})

const mountPage = () => {
  return mount(AppointmentCreate, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({
          stubActions: false,
          createSpy: vi.fn,
          initialState: {
            auth: {
              user: {
                role: 'staff',
                department_id: 1
              }
            }
          }
        })
      ]
    }
  })
}

const flush = async () => {
  await nextTick()
  await Promise.resolve()
  await nextTick()
}

describe('AppointmentCreate.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    axiosGetMock.mockResolvedValue({ data: { data: [] } })
    axiosPostMock.mockResolvedValue({ data: { success: true } })
  })

  it('renders without errors in empty data state', () => {
    const wrapper = mountPage()
    expect(wrapper.find('h2').text()).toBe('Create Appointment')
    expect(wrapper.find('.form-card').exists()).toBe(true)
  })

  it('shows ConflictAlert when conflictType is set', async () => {
    const wrapper = mountPage()
    wrapper.vm.conflictType = 'provider_unavailable'
    await nextTick()
    expect(wrapper.find('.conflict-alert').exists()).toBe(true)
  })

  it('submit button is disabled while submitting', async () => {
    const wrapper = mountPage()
    wrapper.vm.submitting = true
    await nextTick()

    const submitButton = wrapper
      .findAllComponents({ name: 'ElButton' })
      .find(component => component.text().includes('Create Appointment'))

    expect(submitButton).toBeTruthy()
    expect(submitButton.props('loading')).toBe(true)
  })

  it('conflict: 409 APPOINTMENT_CONFLICT response populates conflictType and nextAvailableSlots', async () => {
    const slots = [
      { start_time: '2026-04-01T10:00:00Z', end_time: '2026-04-01T10:30:00Z' },
      { start_time: '2026-04-01T11:00:00Z', end_time: '2026-04-01T11:30:00Z' }
    ]
    const conflictError = Object.assign(new Error('conflict'), {
      response: {
        status: 409,
        data: {
          error: 'APPOINTMENT_CONFLICT',
          data: { conflict_type: 'provider_unavailable', next_available_slots: slots }
        }
      }
    })

    // checkConflict service uses axios.get('/appointments', ...) — reject only for that URL
    axiosGetMock.mockImplementation(url => {
      if (url === '/appointments') return Promise.reject(conflictError)
      return Promise.resolve({ data: { data: [] } })
    })

    const wrapper = mountPage()
    await flush()

    wrapper.vm.form.provider_id = 1
    wrapper.vm.form.resource_id = 2
    wrapper.vm.form.start_time = '2026-04-01T09:00:00'
    wrapper.vm.form.end_time = '2026-04-01T09:30:00'
    await wrapper.vm.checkConflict()
    await flush()

    expect(wrapper.vm.conflictType).toBe('provider_unavailable')
    expect(wrapper.vm.nextAvailableSlots).toHaveLength(2)
    expect(wrapper.find('.conflict-alert').exists()).toBe(true)
  })

  it('conflict: selecting a suggested slot applies times to form and clears conflict state', async () => {
    const wrapper = mountPage()
    wrapper.vm.conflictType = 'provider_unavailable'
    wrapper.vm.nextAvailableSlots = [
      { start_time: '2026-04-01T10:00:00Z', end_time: '2026-04-01T10:30:00Z' }
    ]
    await nextTick()

    wrapper.vm.applySuggestedSlot({ start_time: '2026-04-01T10:00:00Z', end_time: '2026-04-01T10:30:00Z' })
    await nextTick()

    expect(wrapper.vm.conflictType).toBe('')
    expect(wrapper.vm.nextAvailableSlots).toHaveLength(0)
    expect(wrapper.vm.form.start_time).toBe('2026-04-01T10:00:00')
    expect(wrapper.vm.form.end_time).toBe('2026-04-01T10:30:00')
  })
})
