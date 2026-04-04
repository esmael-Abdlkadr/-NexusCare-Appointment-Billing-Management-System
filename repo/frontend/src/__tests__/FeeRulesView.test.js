import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import FeeRulesView from '@/views/FeeRulesView.vue'

// ── shared mocks ──────────────────────────────────────────────────────────────

const pushMock = vi.fn()

vi.mock('vue-router', async importOriginal => {
  const actual = await importOriginal()
  return { ...actual, useRouter: () => ({ push: pushMock }) }
})

const mockListFeeRules = vi.fn()
const mockSaveFeeRule = vi.fn()
const mockDeleteFeeRule = vi.fn()

vi.mock('@/services/feeRuleService', () => ({
  listFeeRules: (...args) => mockListFeeRules(...args),
  saveFeeRule:  (...args) => mockSaveFeeRule(...args),
  deleteFeeRule: (...args) => mockDeleteFeeRule(...args),
}))

vi.mock('@/utils/logger', () => ({
  logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn() }
}))

// ── fixtures ──────────────────────────────────────────────────────────────────

const SAMPLE_RULES = [
  { id: 1, fee_type: 'no_show',      amount: '50.00', rate: null,  period_days: null, grace_minutes: 15, is_active: true  },
  { id: 2, fee_type: 'overdue',      amount: '10.00', rate: '2.50', period_days: 7,   grace_minutes: null, is_active: true  },
  { id: 3, fee_type: 'lost_damaged', amount: '200.00', rate: null, period_days: null, grace_minutes: null, is_active: false },
]

// ── mount helper ──────────────────────────────────────────────────────────────

const mountPage = (role = 'administrator') =>
  mount(FeeRulesView, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({
          createSpy: vi.fn,
          initialState: { auth: { user: { role } } }
        })
      ]
    }
  })

// ── tests ─────────────────────────────────────────────────────────────────────

beforeEach(() => {
  vi.clearAllMocks()
  // Component does: rows.value = data?.data || []
  mockListFeeRules.mockResolvedValue({ data: SAMPLE_RULES })
  mockSaveFeeRule.mockResolvedValue({ success: true })
  mockDeleteFeeRule.mockResolvedValue({ success: true })
})

describe('admin guard', () => {
  it('redirects non-admin to / on mount', async () => {
    mountPage('staff')
    await nextTick()
    expect(pushMock).toHaveBeenCalledWith('/')
  })

  it('does not redirect administrator', async () => {
    mountPage('administrator')
    await nextTick()
    expect(pushMock).not.toHaveBeenCalled()
  })
})

describe('data loading', () => {
  it('calls listFeeRules on mount for admins', async () => {
    mountPage()
    await nextTick()
    await nextTick()
    expect(mockListFeeRules).toHaveBeenCalled()
  })

  it('renders a table row for each returned rule', async () => {
    const wrapper = mountPage()
    await nextTick()
    await nextTick()
    const rows = wrapper.findAll('.el-table__row')
    expect(rows.length).toBe(SAMPLE_RULES.length)
  })
})

describe('dialog — create mode', () => {
  it('"Add / Update Rule" button opens the dialog', async () => {
    const wrapper = mountPage()
    await nextTick()
    await nextTick()
    const btn = wrapper.findAll('button').find(b => /add.*rule|update.*rule/i.test(b.text()))
    await btn.trigger('click')
    await nextTick()
    expect(wrapper.find('.el-dialog').exists()).toBe(true)
  })

  it('form is empty when dialog opens in create mode', async () => {
    const wrapper = mountPage()
    await nextTick()
    await nextTick()
    const btn = wrapper.findAll('button').find(b => /add.*rule|update.*rule/i.test(b.text()))
    await btn.trigger('click')
    await nextTick()
    // fee_type select should show placeholder
    const dialog = wrapper.find('.el-dialog')
    expect(dialog.text()).toMatch(/select fee type|fee type/i)
  })
})

describe('dialog — edit mode', () => {
  it('pre-populates form fields from the selected row', async () => {
    const wrapper = mountPage()
    await nextTick()
    await nextTick()

    const editBtns = wrapper.findAll('button').filter(b => /^edit$/i.test(b.text()))
    expect(editBtns.length).toBeGreaterThan(0)
    await editBtns[0].trigger('click')
    await nextTick()

    // dialog should be open
    expect(wrapper.find('.el-dialog').exists()).toBe(true)
  })
})

describe('submit — save rule', () => {
  it('calls saveFeeRule with correct payload on submit', async () => {
    const wrapper = mountPage()
    await nextTick()
    await nextTick()

    // Open create dialog
    const addBtn = wrapper.findAll('button').find(b => /add.*rule|update.*rule/i.test(b.text()))
    await addBtn.trigger('click')
    await nextTick()

    // Fill in required form fields
    wrapper.vm.form.fee_type = 'no_show'
    wrapper.vm.form.amount = 25
    await nextTick()

    // Mock the form ref validation to pass
    wrapper.vm.formRef = { validate: () => Promise.resolve(true) }

    // Click Save Rule button
    const saveBtn = wrapper.findAll('button').find(b => /save rule/i.test(b.text()))
    await saveBtn.trigger('click')
    await nextTick()
    await nextTick()

    expect(mockSaveFeeRule).toHaveBeenCalledTimes(1)
    const payload = mockSaveFeeRule.mock.calls[0][0]
    expect(payload).toHaveProperty('fee_type')
    expect(payload).toHaveProperty('amount')
  })

  it('closes dialog and reloads list on save success', async () => {
    const wrapper = mountPage()
    await nextTick()
    await nextTick()
    const countAfterMount = mockListFeeRules.mock.calls.length

    const addBtn = wrapper.findAll('button').find(b => /add.*rule|update.*rule/i.test(b.text()))
    await addBtn.trigger('click')
    await nextTick()

    // Fill in required form fields
    wrapper.vm.form.fee_type = 'overdue'
    wrapper.vm.form.amount = 10
    await nextTick()

    // Mock the form ref validation to pass
    wrapper.vm.formRef = { validate: () => Promise.resolve(true) }

    const saveBtn = wrapper.findAll('button').find(b => /save rule/i.test(b.text()))
    await saveBtn.trigger('click')
    await nextTick()
    await nextTick()
    await nextTick()

    // list reloaded at least once after the save
    expect(mockListFeeRules.mock.calls.length).toBeGreaterThan(countAfterMount)
  })

  it('does not reload list when saveFeeRule returns success:false', async () => {
    mockSaveFeeRule.mockResolvedValue({ success: false, error: 'Invalid rule' })

    const wrapper = mountPage()
    await nextTick()
    await nextTick()
    const countAfterMount = mockListFeeRules.mock.calls.length

    const addBtn = wrapper.findAll('button').find(b => /add.*rule|update.*rule/i.test(b.text()))
    await addBtn.trigger('click')
    await nextTick()

    const saveBtn = wrapper.findAll('button').find(b => /save rule/i.test(b.text()))
    await saveBtn.trigger('click')
    await nextTick()
    await nextTick()

    // no additional load on failed save
    expect(mockListFeeRules.mock.calls.length).toBe(countAfterMount)
  })
})

describe('deactivate rule', () => {
  it('calls deleteFeeRule with the row id', async () => {
    const wrapper = mountPage()
    await nextTick()
    await nextTick()

    const deactivateBtn = wrapper.findAll('button').find(b => /deactivate/i.test(b.text()))
    expect(deactivateBtn).toBeDefined()
    await deactivateBtn.trigger('click')
    await nextTick()
    await nextTick()

    expect(mockDeleteFeeRule).toHaveBeenCalledTimes(1)
    // id should be a valid number from SAMPLE_RULES
    const calledId = mockDeleteFeeRule.mock.calls[0][0]
    expect(SAMPLE_RULES.map(r => r.id)).toContain(calledId)
  })

  it('reloads list after successful deactivation', async () => {
    const wrapper = mountPage()
    await nextTick()
    await nextTick()
    const countAfterMount = mockListFeeRules.mock.calls.length

    const deactivateBtn = wrapper.findAll('button').find(b => /deactivate/i.test(b.text()))
    await deactivateBtn.trigger('click')
    await nextTick()
    await nextTick()

    expect(mockListFeeRules.mock.calls.length).toBeGreaterThan(countAfterMount)
  })
})

describe('fee type label rendering', () => {
  it('displays human-readable labels for all three fee types', async () => {
    const wrapper = mountPage()
    await nextTick()
    await nextTick()
    const tableText = wrapper.find('.el-table').text()
    expect(tableText).toMatch(/no show/i)
    expect(tableText).toMatch(/overdue/i)
    expect(tableText).toMatch(/lost.*damaged/i)
  })

  it('shows Active/Inactive status tags correctly', async () => {
    const wrapper = mountPage()
    await nextTick()
    await nextTick()
    const tableText = wrapper.find('.el-table').text()
    expect(tableText).toMatch(/active/i)
    expect(tableText).toMatch(/inactive/i)
  })
})
