import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { flushPromises, mount } from '@vue/test-utils'
import AppointmentList from '@/views/AppointmentList.vue'

const listAppointmentsMock = vi.fn()
const updateStatusMock = vi.fn()

vi.mock('@/services/appointmentService', () => ({
  listAppointments: (...args) => listAppointmentsMock(...args),
  updateAppointmentStatus: (...args) => updateStatusMock(...args)
}))

vi.mock('vue-router', async importOriginal => {
  const actual = await importOriginal()
  return {
    ...actual,
    useRouter: () => ({ push: vi.fn() })
  }
})

const REQUESTED_ROW = {
  id: 1,
  client: { identifier: 'client001' },
  provider: { identifier: 'staff001' },
  service_type: 'Consultation',
  start_time: '2026-05-01T09:00:00',
  end_time: '2026-05-01T09:30:00',
  status: 'requested'
}

const CONFIRMED_ROW = {
  id: 2,
  client: { identifier: 'client002' },
  provider: { identifier: 'staff001' },
  service_type: 'Follow-up',
  start_time: '2026-05-01T10:00:00',
  end_time: '2026-05-01T10:30:00',
  status: 'confirmed'
}

const mountView = (role = 'staff', rows = [REQUESTED_ROW, CONFIRMED_ROW]) => {
  listAppointmentsMock.mockResolvedValue({
    data: { data: rows, total: rows.length, current_page: 1, per_page: 15, last_page: 1 }
  })

  return mount(AppointmentList, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({
          initialState: {
            auth: { user: { role, site_id: 1, department_id: 2 } }
          },
          stubActions: false
        })
      ],
      stubs: {
        AppointmentCancelDialog: true,
        AppointmentRescheduleDialog: true
      }
    }
  })
}

describe('AppointmentList role-action parity', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('staff sees Confirm button for requested appointments', async () => {
    const wrapper = mountView('staff', [REQUESTED_ROW])
    await flushPromises()

    const confirmBtn = wrapper.findAll('.el-button').filter(btn => btn.text() === 'Confirm')
    expect(confirmBtn.length).toBeGreaterThan(0)
    expect(listAppointmentsMock).toHaveBeenCalledWith(
      expect.objectContaining({
        site_id: 1,
        department_id: 2
      })
    )
  })

  it('administrator sees Confirm button for requested appointments', async () => {
    const wrapper = mountView('administrator', [REQUESTED_ROW])
    await flushPromises()

    const confirmBtn = wrapper.findAll('.el-button').filter(btn => btn.text() === 'Confirm')
    expect(confirmBtn.length).toBeGreaterThan(0)
  })

  it('reviewer does NOT see Confirm button for requested appointments', async () => {
    const wrapper = mountView('reviewer', [REQUESTED_ROW])
    await flushPromises()

    const confirmBtn = wrapper.findAll('.el-button').filter(btn => btn.text() === 'Confirm')
    expect(confirmBtn.length).toBe(0)
  })

  it('staff sees Check In button for confirmed appointments', async () => {
    const wrapper = mountView('staff', [CONFIRMED_ROW])
    await flushPromises()

    const checkInBtn = wrapper.findAll('.el-button').filter(btn => btn.text() === 'Check In')
    expect(checkInBtn.length).toBeGreaterThan(0)
  })

  it('staff sees dropdown actions for confirmed appointments', async () => {
    const wrapper = mountView('staff', [CONFIRMED_ROW])
    await flushPromises()

    // The "more" dropdown trigger should be visible
    const moreBtn = wrapper.findAll('.el-button').filter(btn => btn.text().includes('⋯'))
    expect(moreBtn.length).toBeGreaterThan(0)
  })

  it('reviewer does NOT see Check In button (not staff/admin)', async () => {
    const wrapper = mountView('reviewer', [CONFIRMED_ROW])
    await flushPromises()

    const checkInBtn = wrapper.findAll('.el-button').filter(btn => btn.text() === 'Check In')
    expect(checkInBtn.length).toBe(0)
  })
})
