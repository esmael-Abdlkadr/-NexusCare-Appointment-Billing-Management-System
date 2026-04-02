<template>
  <div class="page">
    <div class="toolbar">
      <h2>Anomaly Alerts</h2>
    </div>

    <el-alert
      v-if="unresolvedCount > 0"
      type="warning"
      :closable="false"
      :title="`${unresolvedCount} unresolved anomaly alert(s) — daily variance exceeded $${anomalyThreshold.toFixed(2)}`"
      class="mb"
    />
    <el-alert
      v-else
      type="success"
      :closable="false"
      title="All anomaly alerts have been acknowledged."
      class="mb"
    />

    <el-card v-loading="loading">
      <el-table :data="rows" stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="Import" min-width="180">
          <template #default="{ row }">{{ importMap[row.import_id] || `Import #${row.import_id}` }}</template>
        </el-table-column>
        <el-table-column label="Variance ($)" width="140">
          <template #default="{ row }">
            <span style="font-weight:600; color:#f56c6c">${{ Number(row.variance_amount || 0).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Status" width="130">
          <template #default="{ row }">
            <el-tag :type="row.status === 'acknowledged' ? 'success' : 'danger'" size="small">
              {{ row.status === 'acknowledged' ? 'Acknowledged' : 'Unresolved' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Created" min-width="180">
          <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="Action" width="140">
          <template #default="{ row }">
            <el-button
              v-if="row.status === 'unresolved'"
              size="small"
              type="primary"
              @click="acknowledge(row.id)"
            >
              Acknowledge
            </el-button>
          </template>
        </el-table-column>

        <template #empty>
          <el-empty description="No anomaly alerts" />
        </template>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ElAlert, ElButton, ElCard, ElEmpty, ElMessage, ElTable, ElTableColumn, ElTag } from 'element-plus'
import { computed, onMounted, ref } from 'vue'
import { acknowledgeAnomaly, listAnomalies, listImports } from '@/services/reconciliationService'
import { extractError } from '@/utils/apiError'
import { logger } from '@/utils/logger'

const rows = ref([])
const importMap = ref({})
const loading = ref(false)
const anomalyThreshold = ref(50.0)
const unresolvedCount = computed(() => rows.value.filter(item => item.status === 'unresolved').length)

const formatDateTime = val => {
  if (!val) return '-'
  return new Date(val).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const loadImports = async () => {
  try {
    const data = await listImports({ per_page: 100 })
    const list = data?.data?.data || data?.data || []
    list.forEach(i => {
      importMap.value[i.id] = i.filename || `Import #${i.id}`
    })
  } catch (error) {
    logger.warn('Could not load import names for anomaly context', { message: error?.message })
  }
}

const load = async () => {
  loading.value = true
  try {
    const data = await listAnomalies()
    anomalyThreshold.value = data?.data?.anomaly_threshold ?? 50.0
    rows.value = data?.data?.alerts ?? data?.data ?? []
  } catch (error) {
    logger.error('Failed to load anomaly alerts', { error: error?.message })
    ElMessage.error(extractError(error, 'Failed to load anomaly alerts.'))
  } finally {
    loading.value = false
  }
}

const acknowledge = async id => {
  try {
    const data = await acknowledgeAnomaly(id)
    if (data?.success) {
      ElMessage.success('Anomaly acknowledged.')
      await load()
      return
    }
    ElMessage.error(data?.error || 'Failed to acknowledge anomaly.')
  } catch (error) {
    logger.error('Failed to acknowledge anomaly', { id, error: error?.message })
    ElMessage.error(extractError(error, 'Failed to acknowledge anomaly.'))
  }
}

onMounted(async () => {
  await loadImports()
  await load()
})
</script>

<style scoped>
.page {
  margin: 24px auto;
  max-width: 980px;
  padding: 0 12px;
}

.toolbar {
  margin-bottom: 16px;
}

.mb {
  margin-bottom: 12px;
}
</style>
