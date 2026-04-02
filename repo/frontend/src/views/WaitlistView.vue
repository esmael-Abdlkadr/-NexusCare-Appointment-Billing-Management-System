<template>
  <div class="page">
    <div class="toolbar">
      <h2>Waitlist</h2>
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
      <el-form label-position="top" :model="addForm">
        <el-form-item label="Client">
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
        <el-form-item label="Service Type">
          <el-input v-model="addForm.service_type" maxlength="100" />
        </el-form-item>
        <el-form-item label="Priority">
          <el-input-number v-model="addForm.priority" :min="1" :max="9999" />
        </el-form-item>
        <el-form-item label="Preferred Start">
          <el-date-picker v-model="addForm.preferred_start" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss" />
        </el-form-item>
        <el-form-item label="Preferred End">
          <el-date-picker v-model="addForm.preferred_end" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancel</el-button>
        <el-button type="primary" @click="addEntry">Add</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="confirmDialogVisible" title="Confirm Backfill" width="560px">
      <el-form label-position="top" :model="confirmForm">
        <el-form-item label="Provider">
          <el-select v-model="confirmForm.provider_id" filterable class="w-full" placeholder="Search provider">
            <el-option
              v-for="provider in providers"
              :key="provider.id"
              :label="provider.identifier"
              :value="provider.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="Resource">
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
        <el-form-item label="Start Time">
          <el-date-picker v-model="confirmForm.start_time" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss" />
        </el-form-item>
        <el-form-item label="End Time">
          <el-date-picker v-model="confirmForm.end_time" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="confirmDialogVisible = false">Cancel</el-button>
        <el-button type="primary" @click="confirmBackfill">Book Slot</el-button>
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
    const data = await listWaitlist(page.value)
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
    const data = await searchUsers()
    clients.value = data?.data || []
  } catch (error) {
    clients.value = []
  }

  try {
    const data = await searchUsers({ role: 'staff' })
    providers.value = data?.data || []
  } catch (error) {
    providers.value = []
  }

  try {
    const data = await listResources()
    resources.value = data?.data || []
  } catch {
    ElMessage.error('Failed to load resources. Please try again.')
    resources.value = []
  }
}

const addEntry = async () => {
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
  }
}

const openConfirmDialog = entry => {
  selectedWaitlistId.value = entry.id
  confirmDialogVisible.value = true
  confirmForm.start_time = entry.preferred_start ? String(entry.preferred_start).slice(0, 19) : ''
  confirmForm.end_time = entry.preferred_end ? String(entry.preferred_end).slice(0, 19) : ''
}

const confirmBackfill = async () => {
  if (!selectedWaitlistId.value) {
    return
  }

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
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.proposal-alert {
  margin-bottom: 12px;
}

.w-full {
  width: 100%;
}
</style>
