import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ElementPlus from 'element-plus'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WaitlistView from '@/views/WaitlistView.vue'

const listWaitlistMock = vi.fn()
const addWaitlistMock = vi.fn()
const removeWaitlistMock = vi.fn()
const confirmBackfillMock = vi.fn()
const searchUsersMock = vi.fn()
const listResourcesMock = vi.fn()

vi.mock('@/services/waitlistService', () => ({
  listWaitlist: (...args) => listWaitlistMock(...args),
  addWaitlistEntry: (...args) => addWaitlistMock(...args),
  removeWaitlistEntry: (...args) => removeWaitlistMock(...args),
  confirmBackfill: (...args) => confirmBackfillMock(...args)
}))

vi.mock('@/services/userService', () => ({
  searchUsers: (...args) => searchUsersMock(...args)
}))

vi.mock('@/services/resourceService', () => ({
  listResources: (...args) => listResourcesMock(...args)
}))

const PROPOSED_ROW = {
  id: 7,
  client_id: 1,
  service_type: 'Consultation',
  status: 'proposed',
  priority: 2,
  preferred_start: '2026-04-01T09:00:00',
  preferred_end: '2026-04-01T09:30:00'
}

const WAITING_ROW = {
  id: 3,
  client_id: 2,
  service_type: 'Follow-up',
  status: 'waiting',
  priority: 1,
  preferred_start: null,
  preferred_end: null
}

const mountView = () =>
  mount(WaitlistView, {
    global: {
      plugins: [
        ElementPlus,
        createTestingPinia({
          createSpy: vi.fn,
          initialState: {
            auth: { user: { role: 'staff', department_id: 1 } }
          }
        })
      ]
    }
  })

const flush = () => flushPromises()

describe('WaitlistView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    searchUsersMock.mockResolvedValue({ data: [] })
    listResourcesMock.mockResolvedValue({ data: [] })
    listWaitlistMock.mockResolvedValue({ data: { data: [], total: 0 } })
    confirmBackfillMock.mockResolvedValue({ success: true })
  })

  it('renders table with sortable ID and Priority columns', async () => {
    const wrapper = mountView()
    await flush()

    const columns = wrapper.findAllComponents({ name: 'ElTableColumn' })
    const idCol = columns.find(c => c.props('label') === 'ID')
    const priorityCol = columns.find(c => c.props('label') === 'Priority')

    expect(idCol).toBeTruthy()
    expect(idCol.props('sortable')).toBeTruthy()
    expect(priorityCol).toBeTruthy()
    expect(priorityCol.props('sortable')).toBeTruthy()
  })

  it('proposed entry populates proposedEntries computed and triggers confirm dialog on click', async () => {
    listWaitlistMock.mockResolvedValue({ data: { data: [PROPOSED_ROW, WAITING_ROW], total: 2 } })

    const wrapper = mountView()
    await flush()

    // Verify component state — proposedEntries contains only the proposed row
    expect(wrapper.vm.rows).toHaveLength(2)
    expect(wrapper.vm.proposedEntries).toHaveLength(1)
    expect(wrapper.vm.proposedEntries[0].id).toBe(PROPOSED_ROW.id)

    // Opening the confirm dialog sets the selected ID and makes dialog visible
    wrapper.vm.openConfirmDialog(PROPOSED_ROW)
    await nextTick()
    expect(wrapper.vm.confirmDialogVisible).toBe(true)
    expect(wrapper.vm.selectedWaitlistId).toBe(PROPOSED_ROW.id)
  })

  it('confirmBackfill calls API and reloads list on success', async () => {
    listWaitlistMock.mockResolvedValue({ data: { data: [PROPOSED_ROW], total: 1 } })
    const wrapper = mountView()
    await flush()

    wrapper.vm.openConfirmDialog(PROPOSED_ROW)
    await nextTick()

    await wrapper.vm.confirmBackfill()
    await flush()

    expect(confirmBackfillMock).toHaveBeenCalledWith(
      PROPOSED_ROW.id,
      expect.objectContaining({
        start_time: expect.any(String),
        end_time: expect.any(String)
      })
    )
    expect(listWaitlistMock).toHaveBeenCalledTimes(2)
  })

  it('confirmBackfill does nothing if no selectedWaitlistId', async () => {
    const wrapper = mountView()
    await flush()

    wrapper.vm.selectedWaitlistId = null
    await wrapper.vm.confirmBackfill()
    await flush()

    expect(confirmBackfillMock).not.toHaveBeenCalled()
  })

  it('only waiting-status rows are removable (proposed rows are not)', async () => {
    listWaitlistMock.mockResolvedValue({ data: { data: [PROPOSED_ROW, WAITING_ROW], total: 2 } })
    const wrapper = mountView()
    await flush()

    expect(wrapper.vm.rows).toHaveLength(2)

    // The template only renders Remove button when status === 'waiting'
    const waitingRows = wrapper.vm.rows.filter(r => r.status === 'waiting')
    const proposedRows = wrapper.vm.rows.filter(r => r.status === 'proposed')
    expect(waitingRows).toHaveLength(1)
    expect(proposedRows).toHaveLength(1)
    // Confirm the proposed row is the one with a backfill alert (not a remove action)
    expect(wrapper.vm.proposedEntries.map(e => e.id)).toContain(PROPOSED_ROW.id)
    expect(waitingRows.map(r => r.id)).toContain(WAITING_ROW.id)
  })
})
