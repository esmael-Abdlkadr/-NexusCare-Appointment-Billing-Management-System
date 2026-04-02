<template>
  <div class="page">
    <div class="header">
      <h2>Fee Rules</h2>
      <p>Configure fee policy parameters for this site.</p>
    </div>

    <el-card v-loading="loading">
      <el-table :data="rows" stripe empty-text="No fee rules found.">
        <el-table-column label="Fee Type" min-width="160">
          <template #default="{ row }">{{ feeTypeLabel(row.fee_type) }}</template>
        </el-table-column>
        <el-table-column label="Amount" width="130">
          <template #default="{ row }">{{ formatAmount(row.amount) }}</template>
        </el-table-column>
        <el-table-column label="Rate" width="120">
          <template #default="{ row }">{{ formatRate(row.rate) }}</template>
        </el-table-column>
        <el-table-column label="Period / Grace" min-width="170">
          <template #default="{ row }">{{ formatPeriodGrace(row) }}</template>
        </el-table-column>
        <el-table-column label="Status" width="120">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'">
              {{ row.is_active ? 'Active' : 'Inactive' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Actions" min-width="180">
          <template #default="{ row }">
            <el-button size="small" type="primary" plain @click="openEdit(row)">Edit</el-button>
            <el-button v-if="row.is_active" size="small" type="danger" plain @click="deactivate(row)">Deactivate</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="actions">
        <el-button type="primary" @click="openCreate">Add / Update Rule</el-button>
      </div>
    </el-card>

    <el-dialog v-model="dialogVisible" title="Fee Rule" width="520px">
      <el-form label-position="top" :model="form">
        <el-form-item label="Fee Type">
          <el-select v-model="form.fee_type" placeholder="Select fee type" class="w-full">
            <el-option label="No Show" value="no_show" />
            <el-option label="Overdue" value="overdue" />
            <el-option label="Lost / Damaged" value="lost_damaged" />
          </el-select>
        </el-form-item>

        <el-form-item label="Amount">
          <el-input-number v-model="form.amount" :min="0" :precision="2" :step="1" class="w-full" />
        </el-form-item>

        <el-form-item label="Rate">
          <el-input-number v-model="form.rate" :min="0" :precision="2" :step="0.1" class="w-full" />
        </el-form-item>

        <el-form-item label="Period Days">
          <el-input-number v-model="form.period_days" :min="1" :step="1" class="w-full" />
        </el-form-item>

        <el-form-item label="Grace Minutes">
          <el-input-number v-model="form.grace_minutes" :min="0" :step="1" class="w-full" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">Cancel</el-button>
        <el-button type="primary" :loading="submitting" @click="submit">Save Rule</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ElMessage } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { deleteFeeRule, listFeeRules, saveFeeRule } from '@/services/feeRuleService'
import { useAuthStore } from '@/stores/auth'
import { extractError } from '@/utils/apiError'
import { logger } from '@/utils/logger'

const router = useRouter()
const authStore = useAuthStore()

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const rows = ref([])

const form = reactive({
  fee_type: '',
  amount: 0,
  rate: null,
  period_days: null,
  grace_minutes: null
})

const resetForm = () => {
  form.fee_type = ''
  form.amount = 0
  form.rate = null
  form.period_days = null
  form.grace_minutes = null
}

const feeTypeLabel = type => {
  const map = {
    no_show: 'No Show',
    overdue: 'Overdue',
    lost_damaged: 'Lost / Damaged'
  }
  return map[type] || type
}

const formatAmount = value => `$${Number(value || 0).toFixed(2)}`

const formatRate = value => {
  const num = Number(value || 0)
  return num > 0 ? `${num.toFixed(2)}%` : '—'
}

const formatPeriodGrace = row => {
  if (row.period_days) return `${row.period_days} days`
  if (row.grace_minutes !== null && row.grace_minutes !== undefined) return `${row.grace_minutes} min grace`
  return '—'
}

const load = async () => {
  loading.value = true
  try {
    const data = await listFeeRules()
    rows.value = data?.data || []
  } catch (err) {
    logger.error('Failed to load fee rules', { error: err?.message })
    ElMessage.error(extractError(err, 'Operation failed.'))
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  resetForm()
  dialogVisible.value = true
}

const openEdit = row => {
  form.fee_type = row.fee_type || ''
  form.amount = Number(row.amount || 0)
  form.rate = row.rate === null || row.rate === undefined ? null : Number(row.rate)
  form.period_days = row.period_days === null || row.period_days === undefined ? null : Number(row.period_days)
  form.grace_minutes = row.grace_minutes === null || row.grace_minutes === undefined ? null : Number(row.grace_minutes)
  dialogVisible.value = true
}

const submit = async () => {
  submitting.value = true
  try {
    const payload = {
      fee_type: form.fee_type,
      amount: Number(form.amount || 0)
    }

    if (form.rate !== null && form.rate !== undefined) payload.rate = Number(form.rate)
    if (form.period_days !== null && form.period_days !== undefined) payload.period_days = Number(form.period_days)
    if (form.grace_minutes !== null && form.grace_minutes !== undefined) payload.grace_minutes = Number(form.grace_minutes)

    const data = await saveFeeRule(payload)
    if (data?.success) {
      ElMessage.success('Fee rule saved.')
      dialogVisible.value = false
      await load()
      return
    }
    ElMessage.error('Operation failed.')
  } catch (err) {
    logger.error('Failed to save fee rule', { feeType: form.fee_type, error: err?.message })
    ElMessage.error(extractError(err, 'Operation failed.'))
  } finally {
    submitting.value = false
  }
}

const deactivate = async row => {
  try {
    const data = await deleteFeeRule(row.id)
    if (data?.success) {
      ElMessage.success('Fee rule deactivated.')
      await load()
      return
    }
    ElMessage.error('Operation failed.')
  } catch (err) {
    logger.error('Failed to deactivate fee rule', { id: row.id, error: err?.message })
    ElMessage.error(extractError(err, 'Operation failed.'))
  }
}

onMounted(async () => {
  if (authStore.user?.role !== 'administrator') {
    ElMessage.error('Access denied.')
    await router.push('/')
    return
  }

  await load()
})
</script>

<style scoped>
.page {
  max-width: 1100px;
  margin: 24px auto;
  padding: 0 12px;
}

.header {
  margin-bottom: 16px;
}

.header h2 {
  margin: 0;
}

.header p {
  margin: 6px 0 0;
  color: #6b7280;
}

.actions {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}

.w-full {
  width: 100%;
}
</style>
