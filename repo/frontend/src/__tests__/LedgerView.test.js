import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import LedgerView from '@/views/LedgerView.vue'

// ── mocks ─────────────────────────────────────────────────────────────────────

const pushMock = vi.fn()

vi.mock('vue-router', async importOriginal => {
  const actual = await importOriginal()
  return { ...actual, useRouter: () => ({ push: pushMock }) }
})

const getLedgerMock = vi.fn()

vi.mock('@/services/ledgerService', () => ({
  getLedger: (...args) => getLedgerMock(...args)
}))

vi.mock('@/utils/apiError', () => ({
  extractError: (_err, fallback) => fallback
}))

// LEDGER_CREDIT_TYPES: ['refund', 'waiver', 'writeoff']
vi.mock('@/utils/constants', () => ({
  LEDGER_CREDIT_TYPES: ['refund', 'waiver', 'writeoff']
}))

// ── fixtures ──────────────────────────────────────────────────────────────────

const DEBIT_ENTRY = {
  id: 1,
  entry_type: 'fee',
  reference_id: 'APT-100',
  client_id: 5,
  amount: '50.00',
  description: 'No-show fee',
  created_at: '2026-04-01T09:00:00Z'
}

const CREDIT_ENTRY = {
  id: 2,
  entry_type: 'refund',
  reference_id: 'PMT-200',
  client_id: 5,
  amount: '20.00',
  description: 'Partial refund',
  created_at: '2026-04-02T11:00:00Z'
}

// ── mount helper ──────────────────────────────────────────────────────────────

const mountView = () =>
  mount(LedgerView, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({ createSpy: vi.fn })
      ]
    }
  })

const flush = () => flushPromises()

// ── tests ─────────────────────────────────────────────────────────────────────

describe('LedgerView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getLedgerMock.mockResolvedValue({ data: [] })
  })

  it('mounts without errors and renders the heading', async () => {
    const wrapper = mountView()
    await flush()
    expect(wrapper.exists()).toBe(true)
    expect(wrapper.text()).toContain('Ledger')
  })

  it('calls getLedger on mount', async () => {
    mountView()
    await flush()
    expect(getLedgerMock).toHaveBeenCalledTimes(1)
  })

  it('renders a table row for each returned entry', async () => {
    getLedgerMock.mockResolvedValue({ data: [DEBIT_ENTRY, CREDIT_ENTRY] })
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.rows).toHaveLength(2)
    const tableRows = wrapper.findAll('.el-table__row')
    expect(tableRows.length).toBe(2)
  })

  it('renders key table columns', async () => {
    const wrapper = mountView()
    await flush()
    const text = wrapper.text()
    expect(text).toMatch(/Type/i)
    expect(text).toMatch(/Amount/i)
    expect(text).toMatch(/Description/i)
  })

  it('netTotal computed correctly sums debits minus credits', async () => {
    // DEBIT_ENTRY (fee: +50), CREDIT_ENTRY (refund: -20) => net = 30
    getLedgerMock.mockResolvedValue({ data: [DEBIT_ENTRY, CREDIT_ENTRY] })
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.netTotal).toBeCloseTo(30.0, 2)
  })

  it('netTotal is 0 when rows are empty', async () => {
    getLedgerMock.mockResolvedValue({ data: [] })
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.netTotal).toBe(0)
  })

  it('displays the net total in the totals section', async () => {
    getLedgerMock.mockResolvedValue({ data: [DEBIT_ENTRY, CREDIT_ENTRY] })
    const wrapper = mountView()
    await flush()
    expect(wrapper.text()).toContain('Net total:')
    expect(wrapper.text()).toContain('30.00')
  })

  it('"Configure Fee Rules" button triggers router navigation to /fee-rules', async () => {
    const wrapper = mountView()
    await flush()
    const feeBtn = wrapper.findAll('button').find(b => /configure fee rules/i.test(b.text()))
    expect(feeBtn).toBeDefined()
    await feeBtn.trigger('click')
    await nextTick()
    expect(pushMock).toHaveBeenCalledWith('/fee-rules')
  })

  it('credit-type entries (refund/waiver/writeoff) reduce the netTotal', async () => {
    const waiverEntry = { ...CREDIT_ENTRY, entry_type: 'waiver', amount: '15.00' }
    const writeoffEntry = { ...CREDIT_ENTRY, id: 3, entry_type: 'writeoff', amount: '10.00' }
    getLedgerMock.mockResolvedValue({ data: [DEBIT_ENTRY, waiverEntry, writeoffEntry] })
    // net = +50 - 15 - 10 = 25
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.netTotal).toBeCloseTo(25.0, 2)
  })
})
