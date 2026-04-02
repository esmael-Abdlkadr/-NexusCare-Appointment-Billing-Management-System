import { beforeEach, describe, expect, it, vi } from 'vitest'
import ElementPlus from 'element-plus'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import AppointmentCancelDialog from '@/components/AppointmentCancelDialog.vue'

const updateStatusMock = vi.fn()

vi.mock('@/services/appointmentService', () => ({
  updateAppointmentStatus: (...args) => updateStatusMock(...args)
}))

const mountDialog = () =>
  mount(AppointmentCancelDialog, {
    global: { plugins: [ElementPlus] }
  })

const flush = async () => {
  await nextTick()
  await Promise.resolve()
  await nextTick()
}

describe('AppointmentCancelDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    updateStatusMock.mockResolvedValue({ success: true })
  })

  it('short cancel reason (< 5 chars) does not call API', async () => {
    const wrapper = mountDialog()
    wrapper.vm.open({ id: 42 })
    await nextTick()

    wrapper.vm.reason = 'hi'
    await wrapper.vm.confirmCancel()
    await flush()

    expect(updateStatusMock).not.toHaveBeenCalled()
  })

  it('empty cancel reason does not call API', async () => {
    const wrapper = mountDialog()
    wrapper.vm.open({ id: 42 })
    await nextTick()

    wrapper.vm.reason = ''
    await wrapper.vm.confirmCancel()
    await flush()

    expect(updateStatusMock).not.toHaveBeenCalled()
  })

  it('valid reason (>= 5 chars) calls updateAppointmentStatus with cancelled status', async () => {
    const wrapper = mountDialog()
    wrapper.vm.open({ id: 42 })
    await nextTick()

    wrapper.vm.reason = 'Client requested rescheduling'
    await wrapper.vm.confirmCancel()
    await flush()

    expect(updateStatusMock).toHaveBeenCalledOnce()
    expect(updateStatusMock).toHaveBeenCalledWith(
      42,
      expect.objectContaining({ status: 'cancelled', cancel_reason: 'Client requested rescheduling' })
    )
  })

  it('double-call guard — second call while submitting is ignored', async () => {
    let resolveHold
    updateStatusMock.mockReturnValueOnce(new Promise(r => { resolveHold = r }))

    const wrapper = mountDialog()
    wrapper.vm.open({ id: 5 })
    await nextTick()
    wrapper.vm.reason = 'Valid reason here'

    const first = wrapper.vm.confirmCancel()
    await nextTick()
    await wrapper.vm.confirmCancel()

    resolveHold({ success: true })
    await first
    await flush()

    expect(updateStatusMock).toHaveBeenCalledOnce()
  })
})
