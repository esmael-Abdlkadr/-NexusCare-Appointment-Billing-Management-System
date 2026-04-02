<template>
  <el-dialog v-model="visible" title="Cancel Appointment" width="460px">
    <el-input
      v-model="reason"
      type="textarea"
      :rows="4"
      placeholder="Enter cancel reason"
    />
    <template #footer>
      <el-button size="small" @click="visible = false">Close</el-button>
      <el-button size="small" type="danger" :loading="submitting" @click="confirmCancel">Confirm Cancel</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ElMessage } from 'element-plus'
import { ref } from 'vue'
import { updateAppointmentStatus } from '@/services/appointmentService'

const emit = defineEmits(['cancelled'])

const visible = ref(false)
const appointmentId = ref(null)
const reason = ref('')
const submitting = ref(false)

const open = appointment => {
  appointmentId.value = appointment?.id ?? null
  reason.value = ''
  visible.value = true
}

const confirmCancel = async () => {
  if (submitting.value) return
  const note = reason.value.trim()
  if (note.length < 5) {
    ElMessage.error('Cancel reason must be at least 5 characters')
    return
  }

  submitting.value = true
  try {
    await updateAppointmentStatus(appointmentId.value, {
      status: 'cancelled',
      cancel_reason: note,
      reason: note
    })
    visible.value = false
    ElMessage.success('Appointment cancelled')
    emit('cancelled')
  } catch {
    ElMessage.error('Failed to cancel appointment')
  } finally {
    submitting.value = false
  }
}

defineExpose({ open })
</script>
