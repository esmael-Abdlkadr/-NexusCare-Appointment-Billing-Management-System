import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ReconciliationExceptions from '@/views/ReconciliationExceptions.vue'

const listExceptionsMock = vi.fn()
const listImportsMock = vi.fn()
const resolveExceptionMock = vi.fn()

vi.mock('@/services/reconciliationService', () => ({
  listExceptions: (...args) => listExceptionsMock(...args),
  listImports: (...args) => listImportsMock(...args),
  resolveException: (...args) => resolveExceptionMock(...args)
}))

vi.mock('@/utils/logger', () => ({
  logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn(), debug: vi.fn() }
}))

const mountView = () =>
  mount(ReconciliationExceptions, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({
          createSpy: vi.fn,
          initialState: {
            auth: {
              user: { role: 'reviewer' }
            }
          }
        })
      ]
    }
  })

const flush = async () => {
  await nextTick()
  await Promise.resolve()
  await nextTick()
}

describe('ReconciliationExceptions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    listImportsMock.mockResolvedValue({ success: true, data: [] })
    resolveExceptionMock.mockResolvedValue({ success: true })
  })

  it('renders rows from paginator-shaped response', async () => {
    listExceptionsMock.mockResolvedValue({
      success: true,
      data: {
        current_page: 1,
        data: [{ id: 1, import_id: 1, reason: 'AMOUNT_MISMATCH', status: 'unresolved' }],
        total: 1
      }
    })

    const wrapper = mountView()
    await flush()

    expect(wrapper.text()).toContain('Amount Mismatch')
    expect(wrapper.findAll('.el-table__row').length).toBe(1)
  })

  it('handles flat array fallback without crashing', async () => {
    listExceptionsMock.mockResolvedValue({ success: true, data: [] })

    const wrapper = mountView()
    await flush()

    expect(wrapper.exists()).toBe(true)
    expect(wrapper.findAll('.el-table__row').length).toBe(0)
  })

  it('resolveRules enforce a minimum of 10 characters on resolution_note', () => {
    const wrapper = mountView()
    const minRule = wrapper.vm.resolveRules.resolution_note.find(r => r.min !== undefined)
    expect(minRule).toBeTruthy()
    expect(minRule.min).toBe(10)
  })

  it('submitResolve does not call API when form validate rejects (simulates short note)', async () => {
    listExceptionsMock.mockResolvedValue({
      success: true,
      data: {
        current_page: 1,
        data: [{ id: 5, import_id: 1, reason: 'AMOUNT_MISMATCH', status: 'unresolved' }],
        total: 1
      }
    })

    const wrapper = mountView()
    await flush()

    wrapper.vm.openResolve({ id: 5 })
    await nextTick()

    // Simulate a failed form validation (el-form.validate() rejects in JSDOM when fields are invalid)
    wrapper.vm.resolveFormRef = {
      validate: () => Promise.reject(new Error('validation failed')),
      clearValidate: vi.fn()
    }
    wrapper.vm.resolveForm.resolution_note = 'short'

    await wrapper.vm.submitResolve()
    await flush()

    expect(resolveExceptionMock).not.toHaveBeenCalled()
  })

  it('valid resolution note (>= 10 chars) calls resolveException when form validates', async () => {
    listExceptionsMock.mockResolvedValue({
      success: true,
      data: {
        current_page: 1,
        data: [{ id: 5, import_id: 1, reason: 'AMOUNT_MISMATCH', status: 'unresolved' }],
        total: 1
      }
    })

    const wrapper = mountView()
    await flush()

    wrapper.vm.openResolve({ id: 5 })
    await nextTick()

    // Simulate successful form validation
    wrapper.vm.resolveFormRef = {
      validate: () => Promise.resolve(true),
      clearValidate: vi.fn()
    }
    wrapper.vm.resolveForm.resolution_note = 'Reconciled against original bank statement'

    await wrapper.vm.submitResolve()
    await flush()

    expect(resolveExceptionMock).toHaveBeenCalledWith(
      5,
      expect.objectContaining({ resolution_note: 'Reconciled against original bank statement' })
    )
  })
})
