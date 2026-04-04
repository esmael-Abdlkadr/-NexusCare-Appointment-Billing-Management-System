<template>
  <div class="page">
    <div class="toolbar">
      <h2>Waitlist</h2>
      <div class="scope-badges">
        <el-tag size="small" type="info">Site #{{ scope.siteId }}</el-tag>
        <el-tag size="small" type="info">Dept #{{ scope.departmentId }}</el-tag>
      </div>
      <el-button type="primary" @click="dialogVisible = true">Add Waitlist Entry</el-button>
    </div>

    <el-alert
      v-for="entry in proposedEntries"
      :key="`proposal-${entry.id}`"
      class="proposal-alert"
      type="warning"
      :closable="false"
      show-icon
      :title="`Backfill proposed for waitlist #${entry.id}`"
    >
      <template #default>
        <el-button size="small" type="warning" @click="openConfirmDialog(entry)">Confirm Backfill</el-button>
      </template>
    </el-alert>

    <el-card v-loading="loading">
      <el-table :data="rows" stripe>
        <el-table-column prop="id" label="ID" width="80" sortable />
        <el-table-column label="Client" min-width="150">
          <template #default="{ row }">
            {{ clientMap[row.client_id] || row.client_id }}
          </template>
        </el-table-column>
        <el-table-column prop="service_type" label="Service" min-width="160" />
        <el-table-column prop="priority" label="Priority" width="120" sortable />
        <el-table-column prop="status" label="Status" width="140" />
        <el-table-column label="Preferred Start" min-width="200">
          <template #default="{ row }">
            {{ formatDateTime(row.preferred_start) }}
          </template>
        </el-table-column>
        <el-table-column label="Preferred End" min-width="200">
          <template #default="{ row }">
            {{ formatDateTime(row.preferred_end) }}
          </template>
        </el-table-column>
        <el-table-column label="Actions" width="120">
          <template #default="{ row }">
            <el-button
              v-if="row.status === 'waiting'"
              type="danger"
              size="small"
              text
              @click="removeEntry(row)"
            >
              Remove
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="page"
        :page-size="20"
        :total="total"
        layout="prev, pager, next, total"
        @current-change="load"
        style="margin-top: 16px; justify-content: flex-end;"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" title="Add Waitlist Entry" width="560px">
      <el-form ref="addFormRef" label-position="top" :model="addForm" :rules="addFormRules">
        <el-form-item label="Client" prop="client_id">
          <el-select
            v-model="addForm.client_id"
            filterable
            class="w-full"
            placeholder="Search client by identifier"
          >
            <el-option
              v-for="client in clients"
              :key="client.id"
              :label="client.identifier"
              :value="client.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="Service Type" prop="service_type">
          <el-input v-model="addForm.service_type" maxlength="100" />
        </el-form-item>
        <el-form-item label="Priority">
          <el-input-number v-model="addForm.priority" :min="1" :max="9999" />
        </el-form-item>
        <el-form-item label="Preferred Start" prop="preferred_start">
          <el-date-picker v-model="addForm.preferred_start" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss" />
        </el-form-item>
        <el-form-item label="Preferred End" prop="preferred_end">
          <el-date-picker v-model="addForm.preferred_end" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancel</el-button>
        <el-button type="primary" :loading="addSubmitting" :disabled="addSubmitting" @click="addEntry">Add</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="confirmDialogVisible" title="Confirm Backfill" width="560px">
      <el-form ref="confirmFormRef" label-position="top" :model="confirmForm" :rules="confirmFormRules">
        <el-form-item label="Provider" prop="provider_id">
          <el-select v-model="confirmForm.provider_id" filterable class="w-full" placeholder="Search provider">
            <el-option
              v-for="provider in providers"
              :key="provider.id"
              :label="provider.identifier"
              :value="provider.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="Resource" prop="resource_id">
          <el-select v-model="confirmForm.resource_id" class="w-full" placeholder="Select room/resource">
            <el-option
              v-for="resource in resources"
              :key="resource.id"
              :label="resource.name"
              :value="resource.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="Department ID">
          <el-input-number v-model="confirmForm.department_id" :min="1" />
        </el-form-item>
        <el-form-item label="Start Time" prop="start_time">
          <el-date-picker v-model="confirmForm.start_time" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss" />
        </el-form-item>
        <el-form-item label="End Time" prop="end_time">
          <el-date-picker v-model="confirmForm.end_time" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="confirmDialogVisible = false">Cancel</el-button>
        <el-button type="primary" :loading="confirmSubmitting" :disabled="confirmSubmitting" @click="confirmBackfill">Book Slot</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ElMessage } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { listResources } from '@/services/resourceService'
import { searchUsers } from '@/services/userService'
import { addWaitlistEntry, confirmBackfill as confirmBackfillService, listWaitlist, removeWaitlistEntry } from '@/services/waitlistService'

const authStore = useAuthStore()
const scope = computed(() => ({
  siteId: authStore.user?.site_id || 1,
  departmentId: authStore.user?.department_id || 1
}))

const rows = ref([])
const loading = ref(false)
const page = ref(1)
const total = ref(0)
const dialogVisible = ref(false)
const confirmDialogVisible = ref(false)
const selectedWaitlistId = ref(null)
const clients = ref([])
const providers = ref([])
const resources = ref([])
const addSubmitting = ref(false)
const confirmSubmitting = ref(false)
const addFormRef = ref(null)
const confirmFormRef = ref(null)

const addForm = reactive({
  client_id: null,
  service_type: '',
  priority: 1,
  preferred_start: '',
  preferred_end: ''
})

const confirmForm = reactive({
  provider_id: null,
  resource_id: null,
  department_id: authStore.user?.department_id || 1,
  start_time: '',
  end_time: ''
})

const proposedEntries = computed(() => rows.value.filter(item => item.status === 'proposed'))

const addFormRules = {
  client_id: [{ required: true, message: 'Client is required.', trigger: 'change' }],
  service_type: [{ required: true, message: 'Service type is required.', trigger: 'blur' }],
  preferred_start: [{ required: true, message: 'Preferred start time is required.', trigger: 'change' }],
  preferred_end: [{ required: true, message: 'Preferred end time is required.', trigger: 'change' }]
}

const confirmFormRules = {
  provider_id: [{ required: true, message: 'Provider is required.', trigger: 'change' }],
  resource_id: [{ required: true, message: 'Resource is required.', trigger: 'change' }],
  start_time: [{ required: true, message: 'Start time is required.', trigger: 'change' }],
  end_time: [{ required: true, message: 'End time is required.', trigger: 'change' }]
}

const clientMap = computed(() => {
  return clients.value.reduce((acc, user) => {
    acc[user.id] = user.identifier
    return acc
  }, {})
})

const formatDateTime = val => {
  if (!val) return '-'
  return new Date(val).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const load = async (targetPage = page.value) => {
  page.value = targetPage
  loading.value = true
  try {
    const data = await listWaitlist({
      page: page.value,
      per_page: 20,
      site_id: scope.value.siteId,
      department_id: scope.value.departmentId
    })
    rows.value = data?.data?.data || []
    total.value = data?.data?.total || 0
  } catch (error) {
    ElMessage.error('Failed to load waitlist.')
  } finally {
    loading.value = false
  }
}

const loadUsersAndResources = async () => {
  try {
    const data = await searchUsers({
      site_id: scope.value.siteId,
      department_id: scope.value.departmentId
    })
    clients.value = data?.data || []
  } catch (error) {
    clients.value = []
  }

  try {
    const data = await searchUsers({
      role: 'staff',
      site_id: scope.value.siteId,
      department_id: scope.value.departmentId
    })
    providers.value = data?.data || []
  } catch (error) {
    providers.value = []
  }

  try {
    const data = await listResources({
      site_id: scope.value.siteId,
      department_id: scope.value.departmentId
    })
    resources.value = data?.data || []
  } catch {
    ElMessage.error('Failed to load resources. Please try again.')
    resources.value = []
  }
}

const addEntry = async () => {
  if (addSubmitting.value) return

  const valid = await addFormRef.value?.validate().catch(() => false)
  if (!valid) return

  if (addForm.preferred_start && addForm.preferred_end && addForm.preferred_start >= addForm.preferred_end) {
    ElMessage.error('Preferred end must be after preferred start.')
    return
  }

  addSubmitting.value = true
  try {
    const data = await addWaitlistEntry(addForm)

    if (data?.success) {
      ElMessage.success('Added to waitlist.')
      dialogVisible.value = false
      await load()
      return
    }

    ElMessage.error('Unable to add waitlist entry.')
  } catch (error) {
    ElMessage.error('Failed to add waitlist entry.')
  } finally {
    addSubmitting.value = false
  }
}

const openConfirmDialog = entry => {
  selectedWaitlistId.value = entry.id
  confirmDialogVisible.value = true
  confirmForm.start_time = entry.preferred_start ? String(entry.preferred_start).slice(0, 19) : ''
  confirmForm.end_time = entry.preferred_end ? String(entry.preferred_end).slice(0, 19) : ''
}

const confirmBackfill = async () => {
  if (!selectedWaitlistId.value || confirmSubmitting.value) {
    return
  }

  const valid = await confirmFormRef.value?.validate().catch(() => false)
  if (!valid) return

  if (confirmForm.start_time && confirmForm.end_time && confirmForm.start_time >= confirmForm.end_time) {
    ElMessage.error('End time must be after start time.')
    return
  }

  confirmSubmitting.value = true
  try {
    const data = await confirmBackfillService(selectedWaitlistId.value, confirmForm)

    if (data?.success) {
      ElMessage.success('Backfill confirmed and appointment booked.')
      confirmDialogVisible.value = false
      await load()
      return
    }

    ElMessage.error(data?.error || 'Unable to confirm backfill.')
  } catch (error) {
    ElMessage.error('Failed to confirm backfill.')
  } finally {
    confirmSubmitting.value = false
  }
}

const removeEntry = async row => {
  try {
    const data = await removeWaitlistEntry(row.id)
    if (data?.success) {
      ElMessage.success('Waitlist entry removed.')
      await load()
      return
    }
    ElMessage.error(data?.error || 'Unable to remove waitlist entry.')
  } catch (error) {
    ElMessage.error('Failed to remove waitlist entry.')
  }
}

onMounted(async () => {
  await loadUsersAndResources()
  await load()
})
</script>

<style scoped>
.page {
  margin: 24px auto;
  max-width: 1080px;
  padding: 0 12px;
}

.toolbar {
  display: flex;
  justify-content: flex-start;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.scope-badges {
  display: flex;
  gap: 6px;
}

.proposal-alert {
  margin-bottom: 12px;
}

.w-full {
  width: 100%;
}
</style>
