<template>
  <div class="page">
    <div class="toolbar">
      <h2>Ledger</h2>
      <el-button
        text
        type="primary"
        size="small"
        @click="router.push('/fee-rules')"
      >
        Configure Fee Rules &rarr;
      </el-button>
    </div>

    <el-card v-loading="loading">
      <el-table :data="rows" stripe empty-text="No ledger entries found.">
        <el-table-column prop="created_at" label="Created At" min-width="180" />
        <el-table-column prop="entry_type" label="Type" width="130" />
        <el-table-column prop="reference_id" label="Reference" min-width="160" />
        <el-table-column prop="client_id" label="Client ID" width="120" />
        <el-table-column prop="amount" label="Amount" width="120" />
        <el-table-column prop="description" label="Description" min-width="220" />
      </el-table>
      <div class="totals">
        <strong>Net total:</strong> {{ netTotal.toFixed(2) }}
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ElButton, ElCard, ElMessage, ElTable, ElTableColumn } from 'element-plus'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getLedger } from '@/services/ledgerService'
import { extractError } from '@/utils/apiError'
import { LEDGER_CREDIT_TYPES } from '@/utils/constants'

const router = useRouter()

const rows = ref([])
const loading = ref(false)

const netTotal = computed(() => {
  return rows.value.reduce((sum, row) => {
    const amount = Number(row.amount || 0)
    const sign = LEDGER_CREDIT_TYPES.includes(row.entry_type) ? -1 : 1
    return sum + sign * amount
  }, 0)
})

const load = async () => {
  loading.value = true
  try {
    const data = await getLedger()
    rows.value = data?.data || []
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to load ledger.'))
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.page {
  margin: 24px auto;
  max-width: 1080px;
  padding: 0 12px;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.totals {
  margin-top: 16px;
  text-align: right;
}
</style>
