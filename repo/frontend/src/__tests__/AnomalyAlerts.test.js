import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import AnomalyAlerts from '@/views/AnomalyAlerts.vue'

// ── mocks ─────────────────────────────────────────────────────────────────────

const listAnomaliesMock = vi.fn()
const listImportsMock = vi.fn()
const acknowledgeAnomalyMock = vi.fn()

vi.mock('@/services/reconciliationService', () => ({
  listAnomalies: (...args) => listAnomaliesMock(...args),
  listImports: (...args) => listImportsMock(...args),
  acknowledgeAnomaly: (...args) => acknowledgeAnomalyMock(...args)
}))

vi.mock('@/utils/logger', () => ({
  logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn(), debug: vi.fn() }
}))

vi.mock('@/utils/apiError', () => ({
  extractError: (_err, fallback) => fallback
}))

// ── fixtures ──────────────────────────────────────────────────────────────────

const UNRESOLVED_ALERT = {
  id: 1,
  import_id: 10,
  variance_amount: '123.45',
  status: 'unresolved',
  created_at: '2026-04-01T08:00:00Z'
}

const ACKNOWLEDGED_ALERT = {
  id: 2,
  import_id: 11,
  variance_amount: '20.00',
  status: 'acknowledged',
  created_at: '2026-03-28T12:00:00Z'
}

// ── mount helper ──────────────────────────────────────────────────────────────

const mountView = () =>
  mount(AnomalyAlerts, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({ createSpy: vi.fn })
      ]
    }
  })

const flush = () => flushPromises()

// ── tests ─────────────────────────────────────────────────────────────────────

describe('AnomalyAlerts.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    listImportsMock.mockResolvedValue({ data: { data: [] } })
    listAnomaliesMock.mockResolvedValue({ data: { alerts: [], anomaly_threshold: 50.0 } })
    acknowledgeAnomalyMock.mockResolvedValue({ success: true })
  })

  it('mounts without errors and renders the heading', async () => {
    const wrapper = mountView()
    await flush()
    expect(wrapper.exists()).toBe(true)
    expect(wrapper.text()).toContain('Anomaly Alerts')
  })

  it('shows success alert when there are no unresolved anomalies', async () => {
    listAnomaliesMock.mockResolvedValue({ data: { alerts: [ACKNOWLEDGED_ALERT], anomaly_threshold: 50.0 } })
    const wrapper = mountView()
    await flush()
    expect(wrapper.text()).toContain('All anomaly alerts have been acknowledged.')
  })

  it('shows warning alert when there are unresolved anomalies', async () => {
    listAnomaliesMock.mockResolvedValue({
      data: { alerts: [UNRESOLVED_ALERT, ACKNOWLEDGED_ALERT], anomaly_threshold: 50.0 }
    })
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.unresolvedCount).toBe(1)
    // The warning alert title contains the unresolved count and threshold
    expect(wrapper.text()).toMatch(/1 unresolved anomaly alert/i)
  })

  it('renders a table row for each returned alert', async () => {
    listAnomaliesMock.mockResolvedValue({
      data: { alerts: [UNRESOLVED_ALERT, ACKNOWLEDGED_ALERT], anomaly_threshold: 50.0 }
    })
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.rows).toHaveLength(2)
    const tableRows = wrapper.findAll('.el-table__row')
    expect(tableRows.length).toBe(2)
  })

  it('shows Acknowledge button only for unresolved rows', async () => {
    listAnomaliesMock.mockResolvedValue({
      data: { alerts: [UNRESOLVED_ALERT, ACKNOWLEDGED_ALERT], anomaly_threshold: 50.0 }
    })
    const wrapper = mountView()
    await flush()
    const acknowledgeBtns = wrapper.findAll('button').filter(b => /acknowledge/i.test(b.text()))
    // Only one of the two rows is unresolved
    expect(acknowledgeBtns.length).toBe(1)
  })

  it('calls acknowledgeAnomaly with the correct id and reloads on success', async () => {
    listAnomaliesMock.mockResolvedValue({
      data: { alerts: [UNRESOLVED_ALERT], anomaly_threshold: 50.0 }
    })
    const wrapper = mountView()
    await flush()

    await wrapper.vm.acknowledge(UNRESOLVED_ALERT.id)
    await flush()

    expect(acknowledgeAnomalyMock).toHaveBeenCalledWith(UNRESOLVED_ALERT.id)
    // listAnomalies should be called again after acknowledge (initial + reload)
    expect(listAnomaliesMock.mock.calls.length).toBeGreaterThan(1)
  })

  it('builds importMap from listImports response', async () => {
    listImportsMock.mockResolvedValue({
      data: { data: [{ id: 10, filename: 'settlement_apr.csv' }] }
    })
    listAnomaliesMock.mockResolvedValue({ data: { alerts: [UNRESOLVED_ALERT], anomaly_threshold: 50.0 } })
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.importMap[10]).toBe('settlement_apr.csv')
    expect(wrapper.text()).toContain('settlement_apr.csv')
  })

  it('unresolvedCount computed returns 0 when all are acknowledged', async () => {
    listAnomaliesMock.mockResolvedValue({
      data: { alerts: [ACKNOWLEDGED_ALERT], anomaly_threshold: 50.0 }
    })
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.unresolvedCount).toBe(0)
  })
})
