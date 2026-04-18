import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import RecycleBinView from '@/views/RecycleBinView.vue'

// ── mocks ─────────────────────────────────────────────────────────────────────

const listRecycleBinMock = vi.fn()
const restoreItemMock = vi.fn()
const deleteItemMock = vi.fn()
const bulkRestoreMock = vi.fn()
const bulkDeleteMock = vi.fn()

vi.mock('@/services/recycleBinService', () => ({
  listRecycleBin: (...args) => listRecycleBinMock(...args),
  restoreItem: (...args) => restoreItemMock(...args),
  deleteItem: (...args) => deleteItemMock(...args),
  bulkRestore: (...args) => bulkRestoreMock(...args),
  bulkDelete: (...args) => bulkDeleteMock(...args)
}))

vi.mock('../utils/apiError.js', () => ({
  extractError: (_err, fallback) => fallback
}))

// ── fixtures ──────────────────────────────────────────────────────────────────

const USER_ROW = {
  entity_type: 'user',
  entity_id: 42,
  display_name: 'john_doe',
  deleted_at: '2026-04-10T10:00:00Z'
}

const APPT_ROW = {
  entity_type: 'appointment',
  entity_id: 7,
  display_name: null,
  deleted_at: '2026-04-11T14:00:00Z'
}

// ── mount helper ──────────────────────────────────────────────────────────────

const mountView = () =>
  mount(RecycleBinView, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({ createSpy: vi.fn })
      ]
    }
  })

const flush = () => flushPromises()

// ── tests ─────────────────────────────────────────────────────────────────────

describe('RecycleBinView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    listRecycleBinMock.mockResolvedValue({ data: [] })
    restoreItemMock.mockResolvedValue({ success: true })
    deleteItemMock.mockResolvedValue({ success: true })
    bulkRestoreMock.mockResolvedValue({ success: true })
    bulkDeleteMock.mockResolvedValue({ success: true })
  })

  it('mounts without errors and renders the heading', async () => {
    const wrapper = mountView()
    await flush()
    expect(wrapper.exists()).toBe(true)
    expect(wrapper.text()).toContain('Recycle Bin')
  })

  it('calls listRecycleBin with default entity type "user" on mount', async () => {
    mountView()
    await flush()
    expect(listRecycleBinMock).toHaveBeenCalledWith({ entity_type: 'user' })
  })

  it('renders a table row for each returned item', async () => {
    listRecycleBinMock.mockResolvedValue({ data: [USER_ROW, APPT_ROW] })
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.rows).toHaveLength(2)
    const tableRows = wrapper.findAll('.el-table__row')
    expect(tableRows.length).toBe(2)
  })

  it('shows display_name when available, falls back to Type #id', async () => {
    listRecycleBinMock.mockResolvedValue({ data: [USER_ROW, APPT_ROW] })
    const wrapper = mountView()
    await flush()
    expect(wrapper.text()).toContain('john_doe')
    expect(wrapper.text()).toContain('Appointment #7')
  })

  it('shows Restore and Permanently Delete buttons for each row', async () => {
    listRecycleBinMock.mockResolvedValue({ data: [USER_ROW] })
    const wrapper = mountView()
    await flush()
    const buttons = wrapper.findAll('button').map(b => b.text())
    expect(buttons.some(t => /restore/i.test(t))).toBe(true)
    expect(buttons.some(t => /permanently delete/i.test(t))).toBe(true)
  })

  it('calls restoreItem with correct entity_type and entity_id', async () => {
    listRecycleBinMock.mockResolvedValue({ data: [USER_ROW] })
    const wrapper = mountView()
    await flush()
    await wrapper.vm.restore(USER_ROW)
    await flush()
    expect(restoreItemMock).toHaveBeenCalledWith('user', 42)
    // list should reload after restore
    expect(listRecycleBinMock.mock.calls.length).toBeGreaterThan(1)
  })

  it('formatType maps entity_type strings to human-readable labels', async () => {
    const wrapper = mountView()
    await flush()
    expect(wrapper.vm.formatType('user')).toBe('User')
    expect(wrapper.vm.formatType('appointment')).toBe('Appointment')
    expect(wrapper.vm.formatType('resource')).toBe('Resource')
    expect(wrapper.vm.formatType('waitlist')).toBe('Waitlist')
    expect(wrapper.vm.formatType('unknown')).toBe('unknown')
  })

  it('bulk toolbar is hidden when no rows are selected', async () => {
    listRecycleBinMock.mockResolvedValue({ data: [USER_ROW] })
    const wrapper = mountView()
    await flush()
    // selectedRows is empty by default — bulk toolbar should not appear
    expect(wrapper.vm.selectedRows.length).toBe(0)
    expect(wrapper.find('.bulk-toolbar').exists()).toBe(false)
  })

  it('onSelectionChange updates selectedRows ref', async () => {
    const wrapper = mountView()
    await flush()
    wrapper.vm.onSelectionChange([USER_ROW, APPT_ROW])
    await nextTick()
    expect(wrapper.vm.selectedRows).toHaveLength(2)
  })
})
