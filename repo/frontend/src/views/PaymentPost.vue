<template>
  <div class="page">
    <div class="toolbar">
      <h2>Post Payment</h2>
      <el-button
        v-if="isAdmin"
        text
        type="primary"
        size="small"
        @click="router.push('/fee-rules')"
      >
        Configure Fee Rules &rarr;
      </el-button>
    </div>

    <el-card>
      <el-form label-position="top" :model="form">
        <el-form-item label="Reference ID">
          <el-input v-model="form.reference_id" maxlength="100" />
        </el-form-item>

        <el-form-item label="Amount">
          <el-input v-model="form.amount" placeholder="0.00" style="width: 200px">
            <template #prefix>$</template>
          </el-input>
        </el-form-item>

        <el-form-item label="Method">
          <el-radio-group v-model="form.method">
            <el-radio value="cash">Cash</el-radio>
            <el-radio value="check">Check</el-radio>
            <el-radio value="terminal_batch">Terminal Batch</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="Fee Assessment ID (optional)">
          <el-select
            v-model="form.fee_assessment_id"
            clearable
            filterable
            placeholder="Select fee (optional)"
            style="width: 100%"
            @change="onFeeSelect"
          >
            <el-option
              v-for="fee in feeOptions"
              :key="fee.id"
              :label="`#${fee.id} — ${FEE_TYPE_LABEL[fee.fee_type] || fee.fee_type} $${Number(fee.amount).toFixed(2)}`"
              :value="fee.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item v-if="form.method === 'terminal_batch'" label="Terminal Batch CSV">
          <el-upload
            action="#"
            :auto-upload="false"
            :limit="1"
            accept=".csv,.txt"
            :on-change="onBatchFileChange"
            :on-remove="onBatchFileRemove"
          >
            <el-button>Choose CSV</el-button>
            <template #tip>
              <div style="color: #909399; font-size: 12px; margin-top: 4px;">
                Accepted: .csv or .txt, max 10 MB
              </div>
            </template>
          </el-upload>
          <span v-if="form.method === 'terminal_batch' && !batchFile" style="color: #e6a23c; font-size: 12px; margin-top: 4px; display: block;">
            A batch file is required for Terminal Batch payments.
          </span>
        </el-form-item>

        <el-form-item label="Notes">
          <el-input v-model="form.notes" type="textarea" :rows="3" />
        </el-form-item>

        <el-button type="primary" :loading="submitting" @click="submit">Post Payment</el-button>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import {
  ElButton,
  ElCard,
  ElForm,
  ElFormItem,
  ElInput,
  ElMessage,
  ElOption,
  ElRadio,
  ElRadioGroup,
  ElSelect,
  ElUpload
} from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { listFeeAssessments } from '@/services/feeService'
import { postPayment } from '@/services/paymentService'
import { useAuthStore } from '@/stores/auth'
import { extractError } from '@/utils/apiError'
import { FEE_TYPE_LABEL } from '@/utils/constants'
import { logger } from '@/utils/logger'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const isAdmin = computed(() => authStore.user?.role === 'administrator')
const submitting = ref(false)
const feeOptions = ref([])
const batchFile = ref(null)

const onBatchFileChange = (uploadFile) => {
  batchFile.value = uploadFile.raw
}

const onBatchFileRemove = () => {
  batchFile.value = null
}

const form = reactive({
  reference_id: '',
  amount: '',
  method: 'cash',
  fee_assessment_id: null,
  notes: ''
})

const loadDropdowns = async () => {
  try {
    const data = await listFeeAssessments({ status: 'pending', per_page: 100 })
    feeOptions.value = data?.data?.data || []
  } catch (error) {
    logger.warn('Could not load pending fee options', { error: error?.message })
    feeOptions.value = []
  }
}

const onFeeSelect = feeId => {
  const fee = feeOptions.value.find(f => f.id === feeId)
  if (fee && !form.amount) {
    form.amount = String(fee.amount)
  }
}

const submit = async () => {
  if (submitting.value) {
    return
  }

  if (!form.reference_id.trim()) {
    ElMessage.warning('Reference ID is required.')
    return
  }

  if (!parseFloat(form.amount) || parseFloat(form.amount) <= 0) {
    ElMessage.warning('Enter a valid amount greater than 0.')
    return
  }

  if (form.method === 'terminal_batch' && !batchFile.value) {
    ElMessage.warning('Please select a terminal batch CSV file.')
    return
  }

  submitting.value = true
  try {
    let payload
    let config = {}

    if (form.method === 'terminal_batch' && batchFile.value) {
      payload = new FormData()
      payload.append('reference_id', form.reference_id)
      payload.append('amount', String(parseFloat(form.amount) || 0))
      payload.append('method', form.method)
      payload.append('batch_file', batchFile.value)
      if (form.fee_assessment_id) payload.append('fee_assessment_id', String(form.fee_assessment_id))
      if (form.notes) payload.append('notes', form.notes)
      config = { headers: { 'Content-Type': 'multipart/form-data' } }
    } else {
      payload = {
        reference_id: form.reference_id,
        amount: parseFloat(form.amount) || 0,
        method: form.method,
        fee_assessment_id: form.fee_assessment_id || undefined,
        notes: form.notes || undefined
      }
    }

    const data = await postPayment(payload, config)
    if (data?.success) {
      ElMessage.success(`Payment ${form.reference_id} posted successfully.`)
      form.reference_id = ''
      form.amount = ''
      form.method = 'cash'
      form.fee_assessment_id = null
      form.notes = ''
      batchFile.value = null
      await loadDropdowns()
      return
    }

    ElMessage.error(data?.error || 'Failed to post payment.')
  } catch (error) {
    logger.error('Payment post failed', { reference: form.reference_id, method: form.method, error: error?.message })
    ElMessage.error(extractError(error, 'Failed to post payment.'))
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  if (route.query.fee_id) {
    form.fee_assessment_id = Number(route.query.fee_id)
  }
  if (route.query.amount) {
    form.amount = Number(route.query.amount)
  }
  await loadDropdowns()
})
</script>

<style scoped>
.page {
  margin: 24px auto;
  max-width: 760px;
  padding: 0 12px;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
</style>
