<template>
  <div class="page">
    <div class="header-row">
      <h2>Create Appointment</h2>
      <el-button text @click="router.push('/appointments')">Back to list</el-button>
    </div>

    <el-card class="form-card">
      <ConflictAlert
        :conflict-type="conflictType"
        :next-available-slots="nextAvailableSlots"
        @select-slot="applySuggestedSlot"
      />

      <el-form label-position="top" :model="form" @submit.prevent="handleSubmit">
        <el-form-item label="Client">
          <el-select
            v-model="form.client_id"
            filterable
            placeholder="Search client by identifier"
            class="w-full"
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
          <el-autocomplete
            v-model="form.service_type"
            :fetch-suggestions="queryServiceTypes"
            clearable
            placeholder="Enter service type"
          />
        </el-form-item>

        <el-form-item label="Provider">
          <el-select
            v-model="form.provider_id"
            filterable
            placeholder="Search provider"
            class="w-full"
            @change="checkConflict"
          >
            <el-option
              v-for="provider in providers"
              :key="provider.id"
              :label="provider.identifier"
              :value="provider.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="Resource">
          <el-select
            v-model="form.resource_id"
            placeholder="Select room/resource"
            class="w-full"
            @change="checkConflict"
          >
            <el-option
              v-for="resource in resources"
              :key="resource.id"
              :label="resource.name"
              :value="resource.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="Start Time">
          <el-date-picker
            v-model="form.start_time"
            type="datetime"
            value-format="YYYY-MM-DDTHH:mm:ss"
            placeholder="Select start time"
            @change="checkConflict"
          />
        </el-form-item>

        <el-form-item label="End Time">
          <el-date-picker
            v-model="form.end_time"
            type="datetime"
            value-format="YYYY-MM-DDTHH:mm:ss"
            placeholder="Select end time"
            @change="checkConflict"
          />
        </el-form-item>

        <div class="actions">
          <el-button type="primary" :loading="submitting" @click="handleSubmit">Create Appointment</el-button>
        </div>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ElMessage } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import ConflictAlert from '@/components/ConflictAlert.vue'
import { checkConflict as checkAppointmentConflict, createAppointment } from '@/services/appointmentService'
import { listResources } from '@/services/resourceService'
import { useAuthStore } from '@/stores/auth'
import { searchUsers } from '@/services/userService'
import { SERVICE_TYPE_SUGGESTIONS } from '@/utils/constants'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  client_id: null,
  provider_id: null,
  resource_id: null,
  department_id: authStore.user?.department_id || null,
  service_type: '',
  start_time: '',
  end_time: ''
})

const clients = ref([])
const providers = ref([])
const resources = ref([])

const submitting = ref(false)
const checkingConflict = ref(false)
const conflictType = ref('')
const nextAvailableSlots = ref([])

const clearConflict = () => {
  conflictType.value = ''
  nextAvailableSlots.value = []
}

const checkConflict = async () => {
  if (!form.provider_id || !form.resource_id || !form.start_time || !form.end_time || checkingConflict.value) {
    return
  }

  checkingConflict.value = true

  try {
    const data = await checkAppointmentConflict({
      check_conflict: 1,
      provider_id: form.provider_id,
      resource_id: form.resource_id,
      start_time: form.start_time,
      end_time: form.end_time
    })

    if (data?.success) {
      clearConflict()
    }
  } catch (error) {
    if (error?.response?.status === 409 && error?.response?.data?.error === 'APPOINTMENT_CONFLICT') {
      conflictType.value = error.response.data.data?.conflict_type || 'unknown'
      nextAvailableSlots.value = error.response.data.data?.next_available_slots || []
      ElMessage.warning('Selected slot has a conflict. Choose a suggested slot.')
    } else {
      ElMessage.error('Failed to check conflicts.')
    }
  } finally {
    checkingConflict.value = false
  }
}

const applySuggestedSlot = slot => {
  form.start_time = slot.start_time.replace('Z', '').slice(0, 19)
  form.end_time = slot.end_time.replace('Z', '').slice(0, 19)
  clearConflict()
}

const queryServiceTypes = (queryString, callback) => {
  const value = queryString?.trim().toLowerCase() || ''
  const list = SERVICE_TYPE_SUGGESTIONS
    .filter(item => item.toLowerCase().includes(value))
    .map(item => ({ value: item }))
  callback(list)
}

const loadUsers = async () => {
  try {
    const providersResponse = await searchUsers({ role: 'staff' })
    providers.value = providersResponse?.data || []
  } catch (error) {
    providers.value = []
  }

  try {
    const clientsResponse = await searchUsers()
    clients.value = clientsResponse?.data || []
  } catch (error) {
    clients.value = []
  }
}

const loadResources = async () => {
  try {
    const data = await listResources()
    resources.value = data?.data || []
  } catch {
    ElMessage.error('Failed to load resources. Please try again.')
    resources.value = []
  }
}

const handleSubmit = async () => {
  if (submitting.value) {
    return
  }

  // Pre-submit validation — all fields are required
  if (!form.client_id) {
    ElMessage.warning('Please select a client.')
    return
  }
  if (!form.service_type || !form.service_type.trim()) {
    ElMessage.warning('Please enter a service type.')
    return
  }
  if (!form.provider_id) {
    ElMessage.warning('Please select a provider.')
    return
  }
  if (!form.resource_id) {
    ElMessage.warning('Please select a room/resource.')
    return
  }
  if (!form.start_time) {
    ElMessage.warning('Please select a start time.')
    return
  }
  if (!form.end_time) {
    ElMessage.warning('Please select an end time.')
    return
  }
  if (form.start_time >= form.end_time) {
    ElMessage.warning('End time must be after start time.')
    return
  }

  submitting.value = true

  try {
    const data = await createAppointment(form)

    if (data?.success) {
      ElMessage.success('Appointment created successfully.')
      await router.push('/appointments')
      return
    }

    ElMessage.error('Unable to create appointment.')
  } catch (error) {
    if (error?.response?.status === 409 && error?.response?.data?.error === 'APPOINTMENT_CONFLICT') {
      conflictType.value = error.response.data.data?.conflict_type || 'unknown'
      nextAvailableSlots.value = error.response.data.data?.next_available_slots || []
      ElMessage.warning('Appointment conflict found. Please pick another slot.')
      return
    }

    if (error?.response?.status === 403) {
      ElMessage.error('You do not have permission to create appointments.')
      return
    }

    ElMessage.error('Failed to create appointment.')
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  form.department_id = authStore.user?.department_id || 1
  await loadUsers()
  await loadResources()
})
</script>

<style scoped>
.page {
  max-width: 760px;
  margin: 24px auto;
  padding: 0 12px;
}

.header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.actions {
  margin-top: 8px;
}

.w-full {
  width: 100%;
}
</style>
