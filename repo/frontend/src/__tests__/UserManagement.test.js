import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import UserManagement from '@/views/UserManagement.vue'

// ── mocks ─────────────────────────────────────────────────────────────────────

const listAdminUsersMock = vi.fn()
const updateAdminUserMock = vi.fn()
const deleteAdminUserMock = vi.fn()
const unlockUserMock = vi.fn()
const bulkUserActionMock = vi.fn()

vi.mock('@/services/userService', () => ({
  listAdminUsers: (...args) => listAdminUsersMock(...args),
  updateAdminUser: (...args) => updateAdminUserMock(...args),
  deleteAdminUser: (...args) => deleteAdminUserMock(...args),
  unlockUser: (...args) => unlockUserMock(...args),
  bulkUserAction: (...args) => bulkUserActionMock(...args),
  searchUsers: vi.fn().mockResolvedValue({ data: [] })
}))

// Stub child components to avoid deep dependency chains
vi.mock('@/components/UserCreateDialog.vue', () => ({
  default: { template: '<div class="user-create-dialog-stub" />', name: 'UserCreateDialog', methods: { open: vi.fn() } }
}))

vi.mock('@/components/UserResetPasswordDialog.vue', () => ({
  default: { template: '<div class="user-reset-pwd-dialog-stub" />', name: 'UserResetPasswordDialog', methods: { open: vi.fn() } }
}))

vi.mock('../utils/apiError.js', () => ({
  extractError: (_err, fallback) => fallback
}))

// ── fixtures ──────────────────────────────────────────────────────────────────

const ACTIVE_USER = { id: 1, identifier: 'alice', role: 'staff', site_id: 1, is_banned: false, muted_until: null }
const BANNED_USER = { id: 2, identifier: 'bob', role: 'reviewer', site_id: 1, is_banned: true, muted_until: null }

const PAGE_RESPONSE = { data: { data: [ACTIVE_USER, BANNED_USER], total: 2 } }

// ── mount helper ──────────────────────────────────────────────────────────────

const mountView = (authUser = { role: 'administrator', site_id: 1, department_id: 1 }) =>
  mount(UserManagement, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({
          createSpy: vi.fn,
          initialState: { auth: { user: authUser } }
        })
      ]
    }
  })

const flush = () => flushPromises()

// ── tests ─────────────────────────────────────────────────────────────────────

describe('UserManagement.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    listAdminUsersMock.mockResolvedValue(PAGE_RESPONSE)
    updateAdminUserMock.mockResolvedValue({ success: true })
    deleteAdminUserMock.mockResolvedValue({ success: true })
    unlockUserMock.mockResolvedValue({ success: true })
    bulkUserActionMock.mockResolvedValue({ success: true })
  })

  it('mounts without errors and renders the heading', async () => {
    const wrapper = mountView()
    await flush()
    expect(wrapper.exists()).toBe(true)
    expect(wrapper.text()).toContain('User Management')
  })

  it('calls listAdminUsers on mount with site/department scope', async () => {
    mountView()
    await flush()
    expect(listAdminUsersMock).toHaveBeenCalledWith(
      expect.objectContaining({ site_id: 1, department_id: 1, page: 1 })
    )
  })

  it('renders a table row for each returned user', async () => {
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.rows).toHaveLength(2)
    const tableRows = wrapper.findAll('.el-table__row')
    expect(tableRows.length).toBe(2)
  })

  it('shows Ban/Unban button label based on is_banned status', async () => {
    const wrapper = mountView()
    await flush()
    const buttonTexts = wrapper.findAll('button').map(b => b.text())
    // Active user (alice) gets "Ban", banned user (bob) gets "Unban"
    expect(buttonTexts).toContain('Ban')
    expect(buttonTexts).toContain('Unban')
  })

  it('shows Create User button', async () => {
    const wrapper = mountView()
    await flush()
    const createBtn = wrapper.findAll('button').find(b => /create user/i.test(b.text()))
    expect(createBtn).toBeDefined()
  })

  it('toggleBan calls updateAdminUser with flipped is_banned value', async () => {
    const wrapper = mountView()
    await flush()
    await wrapper.vm.toggleBan(ACTIVE_USER)
    await flush()
    expect(updateAdminUserMock).toHaveBeenCalledWith(
      ACTIVE_USER.id,
      expect.objectContaining({ is_banned: true })
    )
  })

  it('openChangeRoleDialog sets dialog visible and userId', async () => {
    const wrapper = mountView()
    await flush()
    wrapper.vm.openChangeRoleDialog(ACTIVE_USER)
    await nextTick()
    expect(wrapper.vm.changeRoleDialog.visible).toBe(true)
    expect(wrapper.vm.changeRoleDialog.userId).toBe(ACTIVE_USER.id)
    expect(wrapper.vm.changeRoleDialog.role).toBe(ACTIVE_USER.role)
  })

  it('onFilterChange resets page to 1 and reloads', async () => {
    const wrapper = mountView()
    await flush()
    wrapper.vm.page = 3
    await wrapper.vm.onFilterChange()
    await flush()
    expect(wrapper.vm.page).toBe(1)
    // listAdminUsers should be called more than once (mount + filter change)
    expect(listAdminUsersMock.mock.calls.length).toBeGreaterThan(1)
  })

  it('scope reflects authStore user site_id and department_id', async () => {
    const wrapper = mountView({ role: 'administrator', site_id: 5, department_id: 3 })
    await flush()
    expect(wrapper.vm.scope.siteId).toBe(5)
    expect(wrapper.vm.scope.departmentId).toBe(3)
  })
})
