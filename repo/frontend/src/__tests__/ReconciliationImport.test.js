import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ReconciliationImport from '@/views/ReconciliationImport.vue'

// ── mocks ─────────────────────────────────────────────────────────────────────

const importSettlementMock = vi.fn()
const listImportsMock = vi.fn()

vi.mock('@/services/reconciliationService', () => ({
  importSettlement: (...args) => importSettlementMock(...args),
  listImports: (...args) => listImportsMock(...args)
}))

vi.mock('@/utils/apiError', () => ({
  extractError: (_err, fallback) => fallback
}))

vi.mock('@/utils/logger', () => ({
  logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn(), debug: vi.fn() }
}))

// ── fixtures ──────────────────────────────────────────────────────────────────

const IMPORT_RECORD = {
  id: 1,
  filename: 'settlement_apr.csv',
  row_count: 100,
  matched_count: 95,
  discrepancy_count: 5,
  daily_variance: '125.00',
  created_at: '2026-04-01T09:00:00Z'
}

const IMPORT_SUCCESS_RESPONSE = {
  success: true,
  data: {
    import: {
      row_count: 100,
      matched_count: 95,
      discrepancy_count: 5,
      daily_variance: '125.00'
    },
    anomaly_alert: false,
    anomaly_threshold: 50.0
  }
}

const IMPORT_ANOMALY_RESPONSE = {
  success: true,
  data: {
    import: {
      row_count: 50,
      matched_count: 40,
      discrepancy_count: 10,
      daily_variance: '300.00'
    },
    anomaly_alert: true,
    anomaly_threshold: 50.0
  }
}

// ── mount helper ──────────────────────────────────────────────────────────────

const mountView = () =>
  mount(ReconciliationImport, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({ createSpy: vi.fn })
      ]
    }
  })

const flush = () => flushPromises()

// ── tests ─────────────────────────────────────────────────────────────────────

describe('ReconciliationImport.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    listImportsMock.mockResolvedValue({ data: { data: [IMPORT_RECORD] } })
    importSettlementMock.mockResolvedValue(IMPORT_SUCCESS_RESPONSE)
  })

  it('mounts without errors and renders the heading', async () => {
    const wrapper = mountView()
    await flush()
    expect(wrapper.exists()).toBe(true)
    expect(wrapper.text()).toContain('Reconciliation Import')
  })

  it('loads recent import history on mount', async () => {
    mountView()
    await flush()
    expect(listImportsMock).toHaveBeenCalledWith({ per_page: 5 })
  })

  it('renders the recent imports table with rows from history', async () => {
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.recentImports).toHaveLength(1)
    expect(wrapper.text()).toContain('settlement_apr.csv')
  })

  it('Import button is disabled when no file is selected', async () => {
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.selectedFile).toBeNull()
    const importBtn = wrapper.findAll('button').find(b => /import settlement file/i.test(b.text()))
    expect(importBtn).toBeDefined()
    expect(importBtn.attributes('disabled')).toBeDefined()
  })

  it('shows CSV format hint', async () => {
    const wrapper = mountView()
    await flush()
    expect(wrapper.text()).toContain('transaction_id')
    expect(wrapper.text()).toContain('amount')
  })

  it('clearFile sets selectedFile to null', async () => {
    const wrapper = mountView()
    await flush()
    // Simulate a file being selected
    wrapper.vm.selectedFile = new File(['data'], 'test.csv', { type: 'text/csv' })
    await nextTick()
    wrapper.vm.clearFile()
    await nextTick()
    expect(wrapper.vm.selectedFile).toBeNull()
  })

  it('resetForm clears both summary and selectedFile', async () => {
    const wrapper = mountView()
    await flush()
    wrapper.vm.summary = IMPORT_SUCCESS_RESPONSE.data
    wrapper.vm.selectedFile = new File(['data'], 'test.csv', { type: 'text/csv' })
    await nextTick()
    wrapper.vm.resetForm()
    await nextTick()
    expect(wrapper.vm.summary).toBeNull()
    expect(wrapper.vm.selectedFile).toBeNull()
  })

  it('submit shows warning if no file selected', async () => {
    const wrapper = mountView()
    await flush()
    // No file selected — submit should not call importSettlement
    await wrapper.vm.submit()
    await flush()
    expect(importSettlementMock).not.toHaveBeenCalled()
  })

  it('submit calls importSettlement with FormData when file is selected', async () => {
    const wrapper = mountView()
    await flush()
    const fakeFile = new File(['col1,col2\nval1,val2'], 'settlement.csv', { type: 'text/csv' })
    wrapper.vm.selectedFile = fakeFile
    await nextTick()
    await wrapper.vm.submit()
    await flush()
    expect(importSettlementMock).toHaveBeenCalledTimes(1)
    const [formData] = importSettlementMock.mock.calls[0]
    expect(formData).toBeInstanceOf(FormData)
  })

  it('on successful import, sets summary and shows result panel', async () => {
    const wrapper = mountView()
    await flush()
    wrapper.vm.selectedFile = new File(['data'], 'test.csv', { type: 'text/csv' })
    await nextTick()
    await wrapper.vm.submit()
    await flush()
    expect(wrapper.vm.summary).not.toBeNull()
    expect(wrapper.vm.summary.import.row_count).toBe(100)
  })

  it('shows anomaly alert warning when anomaly_alert is true in response', async () => {
    importSettlementMock.mockResolvedValue(IMPORT_ANOMALY_RESPONSE)
    const wrapper = mountView()
    await flush()
    wrapper.vm.selectedFile = new File(['data'], 'test.csv', { type: 'text/csv' })
    await nextTick()
    await wrapper.vm.submit()
    await flush()
    expect(wrapper.vm.summary.anomaly_alert).toBe(true)
    // The anomaly alert text should be present in the rendered output
    expect(wrapper.text()).toMatch(/anomaly alert created/i)
  })
})
