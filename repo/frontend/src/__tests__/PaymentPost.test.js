import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import PaymentPost from '@/views/PaymentPost.vue'

const postPaymentMock = vi.fn()
const listFeeAssessmentsMock = vi.fn()
const pushMock = vi.fn()

vi.mock('@/services/paymentService', () => ({
  postPayment: (...args) => postPaymentMock(...args)
}))

vi.mock('@/services/feeService', () => ({
  listFeeAssessments: (...args) => listFeeAssessmentsMock(...args)
}))

vi.mock('@/utils/logger', () => ({
  logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn(), debug: vi.fn() }
}))

vi.mock('vue-router', async importOriginal => {
  const actual = await importOriginal()
  return {
    ...actual,
    useRouter: () => ({ push: pushMock }),
    useRoute: () => ({ query: {} })
  }
})

const mountPage = () =>
  mount(PaymentPost, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({
          createSpy: vi.fn,
          initialState: {
            auth: { user: { role: 'administrator', department_id: 1 } }
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

describe('PaymentPost.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    listFeeAssessmentsMock.mockResolvedValue({ data: [] })
    postPaymentMock.mockResolvedValue({ success: true })
  })

  it('double-click guard — second submit() call while submitting is ignored', async () => {
    let resolveHold
    postPaymentMock.mockReturnValueOnce(new Promise(r => { resolveHold = r }))

    const wrapper = mountPage()
    await flush()

    wrapper.vm.form.reference_id = 'REF-001'
    wrapper.vm.form.amount = '25.00'
    wrapper.vm.form.method = 'cash'

    const first = wrapper.vm.submit()
    await nextTick()

    expect(wrapper.vm.submitting).toBe(true)

    await wrapper.vm.submit()
    await nextTick()

    resolveHold({ success: true })
    await first
    await flush()

    expect(postPaymentMock).toHaveBeenCalledOnce()
  })

  it('submit is blocked when reference_id is empty', async () => {
    const wrapper = mountPage()
    await flush()

    wrapper.vm.form.reference_id = '   '
    wrapper.vm.form.amount = '10.00'

    await wrapper.vm.submit()
    await flush()

    expect(postPaymentMock).not.toHaveBeenCalled()
  })

  it('submit is blocked when amount is zero', async () => {
    const wrapper = mountPage()
    await flush()

    wrapper.vm.form.reference_id = 'REF-XYZ'
    wrapper.vm.form.amount = '0'

    await wrapper.vm.submit()
    await flush()

    expect(postPaymentMock).not.toHaveBeenCalled()
  })
})
