<template>
  <section class="page">
    <div class="toolbar">
      <el-select v-model="filters.status" clearable placeholder="Filter by status" class="status-filter" @change="handleStatusChange">
        <el-option v-for="status in APPOINTMENT_STATUSES" :key="status" :value="status" :label="status" />
      </el-select>

      <el-button :icon="Plus" type="primary" @click="router.push('/appointments/create')">New Appointment</el-button>
    </div>

    <el-card v-loading="loading">
      <el-table :data="rows" stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="Client" min-width="140">
          <template #default="{ row }">
            {{ row.client?.identifier || row.client_id || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="service_type" label="Service Type" min-width="180" />
        <el-table-column label="Provider" min-width="160">
          <template #default="{ row }">
            {{ row.provider?.identifier || row.provider_id || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="Start Time" min-width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.start_time) }}
          </template>
        </el-table-column>
        <el-table-column label="End Time" min-width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.end_time) }}
          </template>
        </el-table-column>
        <el-table-column label="Status" width="140">
          <template #default="{ row }">
            <el-tag :type="APPOINTMENT_STATUS_TAG[row.status] || 'info'">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Actions" min-width="240">
          <template #default="{ row }">
            <div class="actions-row">
              <el-button
                v-if="primaryAction(row)"
                :type="primaryAction(row).type"
                size="small"
                @click="handleTransition(row, primaryAction(row).status)"
              >
                {{ primaryAction(row).label }}
              </el-button>

              <el-dropdown trigger="click" @command="command => handleMoreCommand(command, row)">
                <el-button size="small">⋯</el-button>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item
                      v-for="item in secondaryActions(row)"
                      :key="`${row.id}-${item.command}`"
                      :command="item.command"
                    >
                      {{ item.label }}
                    </el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
            </div>
          </template>
        </el-table-column>

        <template #empty>
          <el-empty description="No appointments yet" />
        </template>
      </el-table>

      <div class="pagination-row">
        <el-pagination
          v-model:current-page="page"
          :page-size="15"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="loadAppointments"
        />
      </div>
    </el-card>

    <AppointmentCancelDialog ref="cancelDialogRef" @cancelled="loadAppointments(page)" />
    <AppointmentRescheduleDialog ref="rescheduleDialogRef" @rescheduled="loadAppointments(page)" />
  </section>
</template>

<script setup>
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppointmentCancelDialog from '@/components/AppointmentCancelDialog.vue'
import AppointmentRescheduleDialog from '@/components/AppointmentRescheduleDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { listAppointments, updateAppointmentStatus } from '@/services/appointmentService'
import { APPOINTMENT_STATUSES, APPOINTMENT_STATUS_TAG } from '@/utils/constants'

const router = useRouter()
const authStore = useAuthStore()
const role = computed(() => authStore.user?.role || 'staff')

const filters = reactive({
  status: ''
})

const rows = ref([])
const page = ref(1)
const total = ref(0)
const loading = ref(false)
const cancelDialogRef = ref(null)
const rescheduleDialogRef = ref(null)

const formatDateTime = value => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString()
}

const isStaffOrAdmin = computed(() => ['staff', 'administrator'].includes(role.value))
const isAdminOrReviewer = computed(() => ['administrator', 'reviewer'].includes(role.value))

const primaryAction = row => {
  if (row.status === 'requested' && isAdminOrReviewer.value) {
    return { label: 'Confirm', status: 'confirmed', type: 'primary' }
  }

  if (row.status === 'confirmed' && isStaffOrAdmin.value) {
    return { label: 'Check In', status: 'checked_in', type: 'warning' }
  }

  if (row.status === 'checked_in' && isStaffOrAdmin.value) {
    return { label: 'Complete', status: 'completed', type: 'success' }
  }

  return null
}

const secondaryActions = row => {
  const actions = []

  if (isStaffOrAdmin.value) {
    if (row.status === 'confirmed') {
      actions.push({ label: 'Reschedule', command: 'reschedule' })
      actions.push({ label: 'Cancel', command: 'cancel' })
      actions.push({ label: 'No Show', command: 'no_show' })
    }

    if (row.status === 'checked_in') {
      actions.push({ label: 'Cancel', command: 'cancel' })
    }
  }

  actions.push({ label: 'View History', command: 'view_history' })

  return actions
}

const loadAppointments = async (targetPage = page.value) => {
  page.value = targetPage
  loading.value = true

  try {
    const data = await listAppointments({
      status: filters.status || undefined,
      page: page.value
    })

    const payload = data?.data || {}
    rows.value = payload?.data || []
    total.value = payload?.total || 0
  } catch (error) {
    ElMessage.error('Failed to load appointments.')
  } finally {
    loading.value = false
  }
}

const handleStatusChange = async () => {
  page.value = 1
  await loadAppointments(1)
}

const handleTransition = async (row, status) => {
  try {
    await updateAppointmentStatus(row.id, { status })
    ElMessage.success('Status updated')
    await loadAppointments(page.value)
  } catch (error) {
    if (error?.response?.status === 422 && error?.response?.data?.error === 'INVALID_TRANSITION') {
      ElMessage.error('Invalid status transition')
      return
    }

    ElMessage.error('Failed to update status')
  }
}

const viewVersions = row => {
  router.push(`/appointments/${row.id}/versions`)
}

const handleMoreCommand = (command, row) => {
  if (command === 'reschedule') {
    rescheduleDialogRef.value?.open(row)
    return
  }

  if (command === 'cancel') {
    cancelDialogRef.value?.open(row)
    return
  }

  if (command === 'no_show') {
    handleTransition(row, 'no_show')
    return
  }

  if (command === 'view_history') {
    viewVersions(row)
  }
}

onMounted(() => loadAppointments(1))
</script>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.toolbar {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 12px;
}

.status-filter {
  width: 200px;
}

.actions-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pagination-row {
  display: flex;
  justify-content: flex-end;
  padding-top: 6px;
}
</style>
