<template>
  <el-dialog v-model="visible" title="Reset Password" width="420px">
    <el-form ref="resetFormRef" :model="form" :rules="resetRules" label-position="top">
      <el-form-item label="New Password" prop="password">
        <el-input v-model="form.password" show-password placeholder="Min 12 chars, upper/lower/digit/special" />
      </el-form-item>
      <el-form-item label="Verification Note" prop="note">
        <el-input v-model="form.note" placeholder="e.g. Verified identity in person" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">Cancel</el-button>
      <el-button type="primary" @click="submitResetPassword">Reset</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ElMessage } from 'element-plus'
import { ref } from 'vue'
import { resetUserPassword } from '@/services/userService'
import { extractError } from '@/utils/apiError'

const emit = defineEmits(['reset'])

const visible = ref(false)
const targetUser = ref(null)
const form = ref({ password: '', note: '' })
const resetFormRef = ref(null)

const resetRules = {
  password: [
    { required: true, message: 'Password is required.', trigger: 'blur' },
    {
      validator: (_, value, callback) => {
        const password = String(value || '')
        const isStrong =
          password.length >= 12 &&
          /[A-Z]/.test(password) &&
          /[a-z]/.test(password) &&
          /\d/.test(password) &&
          /[^A-Za-z0-9]/.test(password)

        if (!isStrong) {
          callback(new Error('Min 12 chars with upper, lower, digit and special character.'))
          return
        }

        callback()
      },
      trigger: 'blur'
    }
  ],
  note: [
    { required: true, message: 'Verification note is required.', trigger: 'blur' },
    { min: 5, message: 'Verification note must be at least 5 characters.', trigger: 'blur' }
  ]
}

const open = user => {
  targetUser.value = user
  form.value = { password: '', note: '' }
  resetFormRef.value?.clearValidate()
  visible.value = true
}

const submitResetPassword = async () => {
  if (!targetUser.value) {
    return
  }

  const valid = await resetFormRef.value?.validate().catch(() => false)
  if (!valid) return

  try {
    const data = await resetUserPassword(targetUser.value.id, {
      new_password: form.value.password,
      verification_note: form.value.note
    })

    if (data?.success) {
      ElMessage.success('Password reset successfully.')
      visible.value = false
      targetUser.value = null
      form.value = { password: '', note: '' }
      emit('reset')
      return
    }

    ElMessage.error(data?.error || 'Failed to reset password.')
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to reset password.'))
  }
}

defineExpose({ open })
</script>
