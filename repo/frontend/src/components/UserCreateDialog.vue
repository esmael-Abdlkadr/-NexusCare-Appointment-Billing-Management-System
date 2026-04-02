<template>
  <el-dialog v-model="visible" title="Create User" width="480px">
    <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-position="top">
      <el-form-item label="Identifier (username)" prop="identifier"><el-input v-model="createForm.identifier" /></el-form-item>
      <el-form-item label="Email" prop="email"><el-input v-model="createForm.email" /></el-form-item>
      <el-form-item label="Password" prop="password"><el-input v-model="createForm.password" show-password placeholder="Min 12 chars, upper/lower/digit/special" /></el-form-item>
      <el-form-item label="Role" prop="role">
        <el-select v-model="createForm.role">
          <el-option label="Staff" value="staff" />
          <el-option label="Reviewer" value="reviewer" />
          <el-option label="Administrator" value="administrator" />
        </el-select>
      </el-form-item>
      <el-form-item label="Site ID"><el-input-number v-model="createForm.site_id" :min="1" /></el-form-item>
      <el-form-item label="Department ID"><el-input-number v-model="createForm.department_id" :min="1" /></el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">Cancel</el-button>
      <el-button type="primary" @click="submitCreate">Create</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ElMessage } from 'element-plus'
import { ref } from 'vue'
import { createAdminUser } from '@/services/userService'
import { extractError } from '@/utils/apiError'

const emit = defineEmits(['created'])

const visible = ref(false)
const createFormRef = ref(null)
const createForm = ref({
  identifier: '',
  email: '',
  password: '',
  role: 'staff',
  site_id: 1,
  department_id: 1
})

const createRules = {
  identifier: [
    { required: true, message: 'Identifier is required.', trigger: 'blur' },
    { min: 3, message: 'Identifier must be at least 3 characters.', trigger: 'blur' },
    { pattern: /^[a-zA-Z0-9_]+$/, message: 'Identifier must contain only letters, numbers, and underscore.', trigger: 'blur' }
  ],
  email: [
    { required: true, message: 'Email is required.', trigger: 'blur' },
    { type: 'email', message: 'Email must be valid.', trigger: 'blur' }
  ],
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
  role: [
    { required: true, message: 'Role is required.', trigger: 'change' }
  ]
}

const resetForm = () => {
  createForm.value = {
    identifier: '',
    email: '',
    password: '',
    role: 'staff',
    site_id: 1,
    department_id: 1
  }
}

const open = () => {
  resetForm()
  createFormRef.value?.clearValidate()
  visible.value = true
}

const submitCreate = async () => {
  const valid = await createFormRef.value?.validate().catch(() => false)
  if (!valid) return

  try {
    const data = await createAdminUser(createForm.value)
    if (data?.success) {
      ElMessage.success('User created successfully.')
      visible.value = false
      resetForm()
      emit('created')
      return
    }
    ElMessage.error(data?.error || 'Failed to create user.')
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to create user.'))
  }
}

defineExpose({ open })
</script>
