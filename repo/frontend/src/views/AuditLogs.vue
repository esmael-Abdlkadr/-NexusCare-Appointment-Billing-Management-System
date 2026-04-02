<template>
  <div class="page">
    <div class="toolbar">
      <h2>Audit Logs</h2>
    </div>

    <el-card v-loading="loading">
      <div class="filters">
        <el-input v-model="filters.action" placeholder="Action" clearable @change="load" />
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          value-format="YYYY-MM-DD"
          range-separator="to"
          start-placeholder="From"
          end-placeholder="To"
          @change="onDateChange"
        />
      </div>

      <el-table :data="rows" stripe empty-text="No audit log entries found">
        <el-table-column prop="created_at" label="Timestamp" min-width="180" />
        <el-table-column prop="user_identifier" label="Actor" width="160" />
        <el-table-column prop="action" label="Action" width="180" />
        <el-table-column prop="target_type" label="Target Type" min-width="180" />
        <el-table-column prop="target_id" label="Target ID" width="120" />
        <el-table-column prop="ip_address" label="IP" width="140" />
      </el-table>
    </el-card>

    <el-card class="payload-card">
      <h4>Payload JSON</h4>
      <pre>{{ selectedPayload }}</pre>
    </el-card>
  </div>
</template>

<script setup>
import { ElCard, ElDatePicker, ElInput, ElMessage, ElTable, ElTableColumn } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { listAuditLogs } from '@/services/auditService'
import { extractError } from '@/utils/apiError'
import { maskPayload } from '@/utils/maskPayload'

const rows = ref([])
const dateRange = ref([])
const selectedPayload = ref('{}')
const loading = ref(false)

const filters = reactive({
  action: '',
  from: '',
  to: ''
})

const onDateChange = value => {
  filters.from = value?.[0] || ''
  filters.to = value?.[1] || ''
  load()
}

const load = async () => {
  loading.value = true
  try {
    const data = await listAuditLogs({
      action: filters.action || undefined,
      from: filters.from || undefined,
      to: filters.to || undefined
    })

    rows.value = data?.data?.data || []
    selectedPayload.value = JSON.stringify(maskPayload(rows.value[0]?.payload || {}), null, 2)
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to load audit logs.'))
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.page {
  margin: 24px auto;
  max-width: 1100px;
  padding: 0 12px;
}

.toolbar {
  margin-bottom: 16px;
}

.filters {
  display: flex;
  gap: 10px;
  margin-bottom: 12px;
}

.payload-card {
  margin-top: 12px;
}

pre {
  white-space: pre-wrap;
  background: #f7f7f7;
  border-radius: 8px;
  padding: 12px;
}
</style>
