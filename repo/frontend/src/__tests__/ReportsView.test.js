import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ReportsView from '@/views/ReportsView.vue'

const getReportMock = vi.fn()

vi.mock('@/services/reportService', () => ({
  getReport: (...args) => getReportMock(...args)
}))

const flush = async () => {
  await nextTick()
  await Promise.resolve()
  await nextTick()
}

const mountReports = () =>
  mount(ReportsView, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({ createSpy: vi.fn })
      ],
      stubs: {
        teleport: true,
        transition: false
      }
    }
  })

describe('ReportsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows error message when download fails', async () => {
    getReportMock.mockRejectedValue(new Error('Network Error'))
    const wrapper = mountReports()

    await wrapper.vm.download('appointments')
    await flush()

    expect(getReportMock).toHaveBeenCalledTimes(1)
  })

  it('does not trigger duplicate download while request is in-flight', async () => {
    let resolveDownload
    getReportMock.mockImplementation(() => new Promise(resolve => { resolveDownload = resolve }))
    const wrapper = mountReports()

    // Start first download
    const p1 = wrapper.vm.download('appointments')
    await flush()

    // downloadingType should track the active type
    expect(wrapper.vm.downloadingType).toBe('appointments')

    // Attempt second download while first is in-flight
    const p2 = wrapper.vm.download('financial')
    await flush()

    expect(getReportMock).toHaveBeenCalledTimes(1)

    // Resolve to clean up
    resolveDownload({ data: new Blob(['']) })
    await p1
    await p2
    await flush()
  })

  it('re-enables download after failure', async () => {
    getReportMock.mockRejectedValueOnce(new Error('fail'))
    const wrapper = mountReports()

    await wrapper.vm.download('appointments')
    await flush()

    expect(wrapper.vm.downloadingType).toBeNull()
  })

  it('sets per-button loading state matching the requested type', async () => {
    let resolveDownload
    getReportMock.mockImplementation(() => new Promise(resolve => { resolveDownload = resolve }))
    const wrapper = mountReports()

    const p = wrapper.vm.download('financial')
    await flush()

    // Only the financial button should show as the active type
    expect(wrapper.vm.downloadingType).toBe('financial')

    resolveDownload({ data: new Blob(['']) })
    await p
    await flush()

    expect(wrapper.vm.downloadingType).toBeNull()
  })
})
