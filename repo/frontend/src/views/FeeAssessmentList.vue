<template>
  <div class="page">
    <el-card v-loading="loading">
      <div class="filters">
        <el-select
          v-model="filters.status"
          clearable
          placeholder="Filter by status"
          style="width: 200px"
          @change="load"
        >
          <el-option v-for="status in FEE_STATUSES" :key="status" :label="FEE_STATUS_LABEL[status] || status" :value="status" />
        </el-select>
        <el-button
          v-if="isStaffOrAdmin"
          type="warning"
          size="small"
          style="margin-left: 12px"
          @click="assessDialogVisible = true"
        >
          + Assess Lost/Damaged Fee
        </el-button>
        <el-button
          v-if="isAdmin"
          text
          type="primary"
          size="small"
          style="margin-left: auto"
          @click="router.push('/fee-rules')"
        >
          Configure Fee Rules &rarr;
        </el-button>
      </div>

      <el-table :data="rows" stripe empty-text="No fee assessments found">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="Client" min-width="130">
          <template #default="{ row }">{{ row.client?.identifier || '-' }}</template>
        </el-table-column>
        <el-table-column label="Type" min-width="160">
          <template #default="{ row }">
            <div>{{ FEE_TYPE_LABEL[row.fee_type] || row.fee_type }}</div>
            <div v-if="feeRuleRate(row.fee_type)" class="rule-rate">
              Policy: ${{ feeRuleRate(row.fee_type) }}
            </div>
          </template>
        </el-table-column>
        <el-table-column label="Amount" width="110">
          <template #default="{ row }">${{ Number(row.amount).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column label="Due Date" width="150">
          <template #default="{ row }">{{ formatDate(row.due_date) }}</template>
        </el-table-column>
        <el-table-column label="Status" width="120">
          <template #default="{ row }">
            <el-tag :type="tagType(row.status)" size="small">{{ FEE_STATUS_LABEL[row.status] || row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Actions" min-width="220">
          <template #default="{ row }">
            <el-button
              v-if="row.status === 'pending'"
              size="small"
              type="success"
              plain
              @click="openPayment(row)"
            >
              Post Payment
            </el-button>
            <el-button
              v-if="row.status === 'pending' && canApproveWaiver"
              size="small"
              type="primary"
              @click="openWaiver(row)"
            >
              Approve Waiver
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- Approve Waiver Dialog -->
    <el-dialog v-model="waiverDialog" title="Approve Waiver" width="480px">
      <el-form label-position="top" :model="waiverForm">
        <el-form-item label="Waiver Type">
          <el-radio-group v-model="waiverForm.waiver_type">
            <el-radio value="waived">Waived</el-radio>
            <el-radio value="written_off">Written Off</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="Waiver Note (min 5 characters)">
          <el-input v-model="waiverForm.waiver_note" type="textarea" :rows="3" placeholder="Enter reason for waiver..." />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="waiverDialog = false">Cancel</el-button>
        <el-button type="primary" :loading="submittingWaiver" @click="submitWaiver">Submit</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="assessDialogVisible" title="Assess Lost/Damaged Fee" width="480px">
      <el-form ref="assessFormRef" :model="assessForm" :rules="assessRules" label-position="top">
        <el-form-item label="Client" prop="client_id">
          <el-select v-model="assessForm.client_id" filterable placeholder="Search client" style="width: 100%">
            <el-option
              v-for="client in clients"
              :key="client.id"
              :label="client.identifier"
              :value="client.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="Amount ($)" prop="amount">
          <el-input-number v-model="assessForm.amount" :min="0.01" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="Notes" prop="notes">
          <el-input
            v-model="assessForm.notes"
            type="textarea"
            :rows="3"
            placeholder="Describe the lost/damaged item..."
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="assessDialogVisible = false">Cancel</el-button>
        <el-button type="primary" :loading="assessing" @click="submitAssess">Submit</el-button>
      </template>
    </el-dialog>

    <!-- Post Payment Dialog -->
    <el-dialog v-model="paymentDialog" title="Post Payment" width="480px">
      <el-form label-position="top" :model="paymentForm">
        <el-form-item label="Fee">
          <div class="fee-summary">
            <el-tag type="warning" size="small">{{ FEE_TYPE_LABEL[selectedFee?.fee_type] || selectedFee?.fee_type }}</el-tag>
            <span>${{ Number(selectedFee?.amount || 0).toFixed(2) }}</span>
            <span class="fee-client">{{ selectedFee?.client?.identifier }}</span>
          </div>
        </el-form-item>
        <el-form-item label="Reference ID">
          <el-input v-model="paymentForm.reference_id" placeholder="e.g. CHK-001, RCPT-2024-001" />
        </el-form-item>
        <el-form-item label="Amount">
          <el-input v-model="paymentForm.amount" placeholder="0.00" style="width: 180px">
            <template #prefix>$</template>
          </el-input>
        </el-form-item>
        <el-form-item label="Method">
          <el-radio-group v-model="paymentForm.method">
            <el-radio value="cash">Cash</el-radio>
            <el-radio value="check">Check</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="Notes (optional)">
          <el-input v-model="paymentForm.notes" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
          <el-button text type="primary" size="small" @click="goToBatchPage">
            Pay via Terminal Batch &rarr;
          </el-button>
          <div>
            <el-button @click="paymentDialog = false">Cancel</el-button>
            <el-button type="primary" :loading="submittingPayment" @click="submitPayment">Post Payment</el-button>
          </div>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import {
  ElButton,
  ElCard,
  ElDialog,
  ElForm,
  ElFormItem,
  ElInput,
  ElInputNumber,
  ElMessage,
  ElOption,
  ElRadio,
  ElRadioGroup,
  ElSelect,
  ElTable,
  ElTableColumn,
  ElTag
} from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  assessLostDamagedFee,
  listFeeAssessments,
  submitWaiver as submitWaiverRequest
} from '@/services/feeService'
import { listFeeRules } from '@/services/feeRuleService'
import { postPayment } from '@/services/paymentService'
import { searchUsers } from '@/services/userService'
import { useAuthStore } from '@/stores/auth'
import { extractError } from '@/utils/apiError'
import { FEE_STATUSES, FEE_STATUS_LABEL, FEE_TYPE_LABEL } from '@/utils/constants'
import { logger } from '@/utils/logger'

const router = useRouter()
const authStore = useAuthStore()
const rows = ref([])
const loading = ref(false)
const filters = reactive({ status: '' })

// Active fee rules keyed by fee_type — surfaces the configured policy rate
const feeRulesMap = ref({})
const loadFeeRules = async () => {
  try {
    const data = await listFeeRules()
    const rules = data?.data?.data ?? data?.data ?? []
    feeRulesMap.value = Object.fromEntries(
      rules.filter(r => r.is_active).map(r => [r.fee_type, Number(r.amount).toFixed(2)])
    )
  } catch {
    // Non-critical — policy rates are display-only
  }
}
const feeRuleRate = feeType => feeRulesMap.value[feeType] ?? null

const loadClients = async () => {
  try {
    const data = await searchUsers()
    clients.value = data?.data || []
  } catch {
    clients.value = []
  }
}

// Waiver
const waiverDialog = ref(false)
const submittingWaiver = ref(false)
const selectedId = ref(null)
const canApproveWaiver = computed(() => ['reviewer', 'administrator'].includes(authStore.user?.role))
const isStaffOrAdmin = computed(() => ['staff', 'administrator'].includes(authStore.user?.role))
const isAdmin = computed(() => authStore.user?.role === 'administrator')
const waiverForm = reactive({ waiver_type: 'waived', waiver_note: '' })

// Lost/damaged assess
const assessDialogVisible = ref(false)
const assessing = ref(false)
const assessFormRef = ref(null)
const assessForm = reactive({ client_id: null, amount: null, notes: '' })
const clients = ref([])
const assessRules = {
  client_id: [{ required: true, message: 'Client is required.', trigger: 'change' }],
  amount: [{ required: true, message: 'Amount is required.', trigger: 'blur' }]
}

// Payment
const paymentDialog = ref(false)
const submittingPayment = ref(false)
const selectedFee = ref(null)
const paymentForm = reactive({ reference_id: '', amount: '', method: 'cash', notes: '' })

const tagType = status => {
  const map = { paid: 'success', waived: 'success', written_off: 'info', pending: 'warning' }
  return map[status] || 'info'
}

const formatDate = val => {
  if (!val) return '-'
  return new Date(val).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const load = async () => {
  loading.value = true
  try {
    const data = await listFeeAssessments({ status: filters.status || undefined })
    rows.value = data?.data?.data || []
  } catch (error) {
    logger.error('Failed to load fee assessments', { error: error?.message })
    ElMessage.error(extractError(error, 'Failed to load fee assessments.'))
  } finally {
    loading.value = false
  }
}

const openWaiver = row => {
  selectedId.value = row.id
  waiverForm.waiver_type = 'waived'
  waiverForm.waiver_note = ''
  waiverDialog.value = true
}

const submitWaiver = async () => {
  if (!selectedId.value || submittingWaiver.value) return
  submittingWaiver.value = true
  try {
    const data = await submitWaiverRequest(selectedId.value, waiverForm)
    if (data?.success) {
      ElMessage.success('Waiver approved successfully.')
      waiverDialog.value = false
      await load()
      return
    }
    ElMessage.error(data?.error || 'Failed to approve waiver.')
  } catch (error) {
    logger.error('Failed to approve waiver', { id: selectedId.value, error: error?.message })
    ElMessage.error(extractError(error, 'Failed to approve waiver.'))
  } finally {
    submittingWaiver.value = false
  }
}

const submitAssess = async () => {
  const valid = await assessFormRef.value?.validate().catch(() => false)
  if (!valid || assessing.value) return

  assessing.value = true
  try {
    const data = await assessLostDamagedFee({
      client_id: assessForm.client_id,
      amount: assessForm.amount,
      notes: assessForm.notes || undefined
    })

    if (data?.success) {
      ElMessage.success('Lost/damaged fee assessed.')
      assessDialogVisible.value = false
      assessForm.client_id = null
      assessForm.amount = null
      assessForm.notes = ''
      await load()
      return
    }

    ElMessage.error(data?.error || 'Failed to assess fee.')
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to assess fee.'))
  } finally {
    assessing.value = false
  }
}

const openPayment = row => {
  selectedFee.value = row
  paymentForm.reference_id = ''
  paymentForm.amount = String(row.amount)
  paymentForm.method = 'cash'
  paymentForm.notes = ''
  paymentDialog.value = true
}

const goToBatchPage = () => {
  paymentDialog.value = false
  router.push({
    path: '/payments/post',
    query: {
      fee_id: selectedFee.value?.id,
      amount: selectedFee.value?.amount
    }
  })
}

const submitPayment = async () => {
  if (submittingPayment.value) return
  if (!paymentForm.reference_id.trim()) {
    ElMessage.warning('Reference ID is required.')
    return
  }
  const amount = parseFloat(paymentForm.amount)
  if (!amount || amount <= 0) {
    ElMessage.warning('Enter a valid amount greater than 0.')
    return
  }
  submittingPayment.value = true
  try {
    const data = await postPayment({
      reference_id: paymentForm.reference_id,
      amount,
      method: paymentForm.method,
      fee_assessment_id: selectedFee.value?.id,
      notes: paymentForm.notes || undefined
    })
    if (data?.success) {
      ElMessage.success(`Payment ${paymentForm.reference_id} posted successfully.`)
      paymentDialog.value = false
      await load()
      return
    }
    ElMessage.error(data?.error || 'Failed to post payment.')
  } catch (error) {
    logger.error('Failed to post payment from fee list', { reference: paymentForm.reference_id, feeId: selectedFee.value?.id, error: error?.message })
    ElMessage.error(extractError(error, 'Failed to post payment.'))
  } finally {
    submittingPayment.value = false
  }
}

onMounted(() => {
  load()
  loadClients()
  loadFeeRules()
})
</script>

<style scoped>
.page {
  max-width: 1100px;
}

.filters {
  display: flex;
  align-items: center;
  margin-bottom: 16px;
}

.fee-summary {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  background: #f5f7fa;
  border-radius: 6px;
  font-size: 14px;
}

.fee-client {
  color: #6b7280;
}

.rule-rate {
  font-size: 11px;
  color: #9ca3af;
  margin-top: 2px;
}
</style>
