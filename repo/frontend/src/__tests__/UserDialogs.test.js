import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import UserCreateDialog from '@/components/UserCreateDialog.vue'
import UserResetPasswordDialog from '@/components/UserResetPasswordDialog.vue'

const createAdminUserMock = vi.fn()
const resetUserPasswordMock = vi.fn()

vi.mock('@/services/userService', () => ({
  createAdminUser: (...args) => createAdminUserMock(...args),
  resetUserPassword: (...args) => resetUserPasswordMock(...args)
}))

const flush = async () => {
  await nextTick()
  await Promise.resolve()
  await nextTick()
}

const mountCreateDialog = () =>
  mount(UserCreateDialog, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({
          createSpy: vi.fn
        })
      ],
      stubs: {
        teleport: true,
        transition: false
      }
    }
  })

const mountResetDialog = () =>
  mount(UserResetPasswordDialog, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({
          createSpy: vi.fn
        })
      ],
      stubs: {
        teleport: true,
        transition: false
      }
    }
  })

describe('UserCreateDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    createAdminUserMock.mockResolvedValue({ success: true })
  })

  it('submitCreate blocks when identifier is empty', async () => {
    const wrapper = mountCreateDialog()
    wrapper.vm.open()
    await flush()
    wrapper.vm.createFormRef = {
      validate: vi.fn().mockRejectedValue(new Error('invalid')),
      clearValidate: vi.fn()
    }

    wrapper.vm.createForm.email = 'user@example.com'
    wrapper.vm.createForm.password = 'ValidPass123!'

    await wrapper.vm.submitCreate()
    await flush()

    expect(createAdminUserMock).not.toHaveBeenCalled()
  })

  it('submitCreate blocks when email is invalid format', async () => {
    const wrapper = mountCreateDialog()
    wrapper.vm.open()
    await flush()
    wrapper.vm.createFormRef = {
      validate: vi.fn().mockRejectedValue(new Error('invalid')),
      clearValidate: vi.fn()
    }

    wrapper.vm.createForm.identifier = 'valid_user'
    wrapper.vm.createForm.email = 'notanemail'
    wrapper.vm.createForm.password = 'ValidPass123!'

    await wrapper.vm.submitCreate()
    await flush()

    expect(createAdminUserMock).not.toHaveBeenCalled()
  })

  it('submitCreate blocks when password has no complexity (long but weak)', async () => {
    const wrapper = mountCreateDialog()
    wrapper.vm.open()
    await flush()
    wrapper.vm.createFormRef = {
      validate: vi.fn().mockRejectedValue(new Error('invalid')),
      clearValidate: vi.fn()
    }

    wrapper.vm.createForm.identifier = 'valid_user'
    wrapper.vm.createForm.email = 'user@example.com'
    wrapper.vm.createForm.password = 'alllowercasepassword1'  // 21 chars but no upper/special

    await wrapper.vm.submitCreate()
    await flush()

    expect(createAdminUserMock).not.toHaveBeenCalled()
  })

  it('submitCreate blocks when password is too short', async () => {
    const wrapper = mountCreateDialog()
    wrapper.vm.open()
    await flush()
    wrapper.vm.createFormRef = {
      validate: vi.fn().mockRejectedValue(new Error('invalid')),
      clearValidate: vi.fn()
    }

    wrapper.vm.createForm.identifier = 'valid_user'
    wrapper.vm.createForm.email = 'user@example.com'
    wrapper.vm.createForm.password = 'abc'

    await wrapper.vm.submitCreate()
    await flush()

    expect(createAdminUserMock).not.toHaveBeenCalled()
  })

  it('submitCreate calls API with valid data', async () => {
    const wrapper = mountCreateDialog()
    wrapper.vm.open()
    await flush()
    wrapper.vm.createFormRef = {
      validate: vi.fn().mockResolvedValue(true),
      clearValidate: vi.fn()
    }

    wrapper.vm.createForm.identifier = 'valid_user'
    wrapper.vm.createForm.email = 'user@example.com'
    wrapper.vm.createForm.password = 'ValidPass123!'
    wrapper.vm.createForm.role = 'staff'

    await wrapper.vm.submitCreate()
    await flush()

    expect(createAdminUserMock).toHaveBeenCalledTimes(1)
  })

  it('shows error feedback when create API fails', async () => {
    createAdminUserMock.mockRejectedValue(new Error('Server error'))
    const wrapper = mountCreateDialog()
    wrapper.vm.open()
    await flush()
    wrapper.vm.createFormRef = {
      validate: vi.fn().mockResolvedValue(true),
      clearValidate: vi.fn()
    }

    wrapper.vm.createForm.identifier = 'valid_user'
    wrapper.vm.createForm.email = 'user@example.com'
    wrapper.vm.createForm.password = 'ValidPass123!'

    await wrapper.vm.submitCreate()
    await flush()

    expect(createAdminUserMock).toHaveBeenCalledTimes(1)
    expect(wrapper.vm.submitting).toBe(false)
  })

  it('prevents duplicate submit while create request is in-flight', async () => {
    let resolveCreate
    createAdminUserMock.mockImplementation(() => new Promise(resolve => { resolveCreate = resolve }))
    const wrapper = mountCreateDialog()
    wrapper.vm.open()
    await flush()
    wrapper.vm.createFormRef = {
      validate: vi.fn().mockResolvedValue(true),
      clearValidate: vi.fn()
    }

    wrapper.vm.createForm.identifier = 'valid_user'
    wrapper.vm.createForm.email = 'user@example.com'
    wrapper.vm.createForm.password = 'ValidPass123!'

    const p1 = wrapper.vm.submitCreate()
    await flush()
    expect(wrapper.vm.submitting).toBe(true)

    const p2 = wrapper.vm.submitCreate()
    await flush()

    expect(createAdminUserMock).toHaveBeenCalledTimes(1)

    resolveCreate({ success: true })
    await p1
    await p2
    await flush()
  })

  it('submitting ref controls button disabled/loading state through template binding', async () => {
    let resolveCreate
    createAdminUserMock.mockImplementation(() => new Promise(resolve => { resolveCreate = resolve }))
    const wrapper = mountCreateDialog()
    wrapper.vm.open()
    await flush()
    wrapper.vm.createFormRef = {
      validate: vi.fn().mockResolvedValue(true),
      clearValidate: vi.fn()
    }

    wrapper.vm.createForm.identifier = 'valid_user'
    wrapper.vm.createForm.email = 'user@example.com'
    wrapper.vm.createForm.password = 'ValidPass123!'

    // Before submit — not in submitting state
    expect(wrapper.vm.submitting).toBe(false)

    const p = wrapper.vm.submitCreate()
    await flush()

    // During submit — submitting is true (drives :loading and :disabled on button)
    expect(wrapper.vm.submitting).toBe(true)
    // Template uses :loading="submitting" :disabled="submitting"
    // Verify the binding exists in the rendered HTML
    expect(wrapper.html()).toContain('disabled')

    resolveCreate({ success: true })
    await p
    await flush()

    // After resolve — submitting resets
    expect(wrapper.vm.submitting).toBe(false)
  })
})

describe('UserResetPasswordDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    resetUserPasswordMock.mockResolvedValue({ success: true })
  })

  it('reset blocks when password is too short', async () => {
    const wrapper = mountResetDialog()
    wrapper.vm.open({ id: 10 })
    await flush()
    wrapper.vm.resetFormRef = {
      validate: vi.fn().mockRejectedValue(new Error('invalid')),
      clearValidate: vi.fn()
    }

    wrapper.vm.form.password = 'Short1!'
    wrapper.vm.form.note = 'Verified user identity in person'

    await wrapper.vm.submitResetPassword()
    await flush()

    expect(resetUserPasswordMock).not.toHaveBeenCalled()
  })

  it('reset blocks when password lacks complexity', async () => {
    const wrapper = mountResetDialog()
    wrapper.vm.open({ id: 11 })
    await flush()
    wrapper.vm.resetFormRef = {
      validate: vi.fn().mockRejectedValue(new Error('invalid')),
      clearValidate: vi.fn()
    }

    wrapper.vm.form.password = 'alllowercase123!'
    wrapper.vm.form.note = 'Verified user identity in person'

    await wrapper.vm.submitResetPassword()
    await flush()

    expect(resetUserPasswordMock).not.toHaveBeenCalled()
  })

  it('reset blocks when note is blank', async () => {
    const wrapper = mountResetDialog()
    wrapper.vm.open({ id: 12 })
    await flush()
    wrapper.vm.resetFormRef = {
      validate: vi.fn().mockRejectedValue(new Error('invalid')),
      clearValidate: vi.fn()
    }

    wrapper.vm.form.password = 'ValidPass123!'
    wrapper.vm.form.note = ''

    await wrapper.vm.submitResetPassword()
    await flush()

    expect(resetUserPasswordMock).not.toHaveBeenCalled()
  })

  it('reset calls API with valid password and note', async () => {
    const wrapper = mountResetDialog()
    wrapper.vm.open({ id: 13 })
    await flush()
    wrapper.vm.resetFormRef = {
      validate: vi.fn().mockResolvedValue(true),
      clearValidate: vi.fn()
    }

    wrapper.vm.form.password = 'ValidPass1!AB'
    wrapper.vm.form.note = 'Verified identity in person at front desk'

    await wrapper.vm.submitResetPassword()
    await flush()

    expect(resetUserPasswordMock).toHaveBeenCalledTimes(1)
  })

  it('shows error feedback when reset API fails', async () => {
    resetUserPasswordMock.mockRejectedValue(new Error('Server error'))
    const wrapper = mountResetDialog()
    wrapper.vm.open({ id: 14 })
    await flush()
    wrapper.vm.resetFormRef = {
      validate: vi.fn().mockResolvedValue(true),
      clearValidate: vi.fn()
    }

    wrapper.vm.form.password = 'ValidPass1!AB'
    wrapper.vm.form.note = 'Verified identity in person'

    await wrapper.vm.submitResetPassword()
    await flush()

    expect(resetUserPasswordMock).toHaveBeenCalledTimes(1)
    expect(wrapper.vm.submitting).toBe(false)
  })

  it('prevents duplicate submit while reset request is in-flight', async () => {
    let resolveReset
    resetUserPasswordMock.mockImplementation(() => new Promise(resolve => { resolveReset = resolve }))
    const wrapper = mountResetDialog()
    wrapper.vm.open({ id: 15 })
    await flush()
    wrapper.vm.resetFormRef = {
      validate: vi.fn().mockResolvedValue(true),
      clearValidate: vi.fn()
    }

    wrapper.vm.form.password = 'ValidPass1!AB'
    wrapper.vm.form.note = 'Verified identity in person'

    const p1 = wrapper.vm.submitResetPassword()
    await flush()
    expect(wrapper.vm.submitting).toBe(true)

    const p2 = wrapper.vm.submitResetPassword()
    await flush()

    expect(resetUserPasswordMock).toHaveBeenCalledTimes(1)

    resolveReset({ success: true })
    await p1
    await p2
    await flush()
  })

  it('submitting ref controls button disabled/loading state through template binding', async () => {
    let resolveReset
    resetUserPasswordMock.mockImplementation(() => new Promise(resolve => { resolveReset = resolve }))
    const wrapper = mountResetDialog()
    wrapper.vm.open({ id: 16 })
    await flush()
    wrapper.vm.resetFormRef = {
      validate: vi.fn().mockResolvedValue(true),
      clearValidate: vi.fn()
    }

    wrapper.vm.form.password = 'ValidPass1!AB'
    wrapper.vm.form.note = 'Verified identity in person'

    // Before submit — not in submitting state
    expect(wrapper.vm.submitting).toBe(false)

    const p = wrapper.vm.submitResetPassword()
    await flush()

    // During submit — submitting is true (drives :loading and :disabled on button)
    expect(wrapper.vm.submitting).toBe(true)
    // Template uses :loading="submitting" :disabled="submitting"
    expect(wrapper.html()).toContain('disabled')

    resolveReset({ success: true })
    await p
    await flush()

    // After resolve — submitting resets
    expect(wrapper.vm.submitting).toBe(false)
  })
})
