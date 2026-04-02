<template>
  <el-dialog v-model="visible" title="Reschedule Appointment" width="520px">
    <div class="reschedule-fields">
      <el-form ref="rescheduleFormRef" :model="{ startTime, endTime, reason }" label-position="top">
        <el-form-item label="Start Time">
          <el-date-picker v-model="startTime" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss" placeholder="Select start time" />
        </el-form-item>
        <el-form-item label="End Time">
          <el-date-picker v-model="endTime" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss" placeholder="Select end time" />
        </el-form-item>
        <el-form-item label="Reason for Rescheduling" prop="reason" :rules="[{ required: true, min: 5, message: 'Reason must be at least 5 characters.', trigger: 'blur' }]">
          <el-input
            v-model="reason"
            type="textarea"
            :rows="3"
            placeholder="Describe the reason for rescheduling..."
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <div v-if="conflictType" class="inline-conflict">
        <p class="inline-conflict-title">Conflict: {{ conflictType }}</p>
        <div class="slot-list">
          <el-button v-for="slot in nextSlots" :key="`${slot.start_time}-${slot.end_time}`" size="small" plain @click="applySlot(slot)">
            {{ formatDateTime(slot.start_time) }}
          </el-button>
        </div>
      </div>
    </div>
    <template #footer>
      <el-button size="small" @click="visible = false">Close</el-button>
      <el-button size="small" type="primary" @click="confirmReschedule">Save</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ElMessage } from 'element-plus'
import { ref } from 'vue'
import { rescheduleAppointment } from '@/services/appointmentService'

const emit = defineEmits(['rescheduled'])

const visible = ref(false)
const appointmentId = ref(null)
const startTime = ref('')
const endTime = ref('')
const reason = ref('')
const conflictType = ref('')
const nextSlots = ref([])
const rescheduleFormRef = ref(null)

const clearConflict = () => {
  conflictType.value = ''
  nextSlots.value = []
}

const open = appointment => {
  appointmentId.value = appointment?.id ?? null
  startTime.value = appointment?.start_time ? String(appointment.start_time).slice(0, 19) : ''
  endTime.value = appointment?.end_time ? String(appointment.end_time).slice(0, 19) : ''
  reason.value = ''
  clearConflict()
  rescheduleFormRef.value?.clearValidate()
  visible.value = true
}

const applySlot = slot => {
  startTime.value = String(slot.start_time).replace('Z', '').slice(0, 19)
  endTime.value = String(slot.end_time).replace('Z', '').slice(0, 19)
  clearConflict()
}

const confirmReschedule = async () => {
  if (!startTime.value || !endTime.value) {
    ElMessage.error('Start and end times are required')
    return
  }

  const valid = await rescheduleFormRef.value?.validate().catch(() => false)
  if (!valid) return

  try {
    await rescheduleAppointment(appointmentId.value, {
      start_time: startTime.value,
      end_time: endTime.value,
      reason: reason.value
    })
    visible.value = false
    ElMessage.success('Appointment rescheduled')
    emit('rescheduled')
  } catch (error) {
    if (error?.response?.status === 409 && error?.response?.data?.error === 'APPOINTMENT_CONFLICT') {
      conflictType.value = error.response.data.data?.conflict_type || 'unknown'
      nextSlots.value = error.response.data.data?.next_available_slots || []
      ElMessage.error('Reschedule conflict detected')
      return
    }
    ElMessage.error('Failed to reschedule appointment')
  }
}

const formatDateTime = value => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString()
}

defineExpose({ open })
</script>

<style scoped>
.reschedule-fields { padding-top: 6px; }
.inline-conflict { margin-top: 6px; border: 1px solid #f6c4c4; border-radius: 8px; background: #fff7f7; padding: 10px; }
.inline-conflict-title { margin: 0 0 8px; color: #bf2f2f; font-weight: 600; }
.slot-list { display: flex; flex-wrap: wrap; gap: 8px; }
</style>
