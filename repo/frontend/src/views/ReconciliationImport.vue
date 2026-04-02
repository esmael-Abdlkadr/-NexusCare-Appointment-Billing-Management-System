<template>
  <div class="page">
    <div class="toolbar">
      <h2>Reconciliation Import</h2>
    </div>

    <el-card>
      <el-upload
        drag
        :auto-upload="false"
        :limit="1"
        :on-change="onFileChange"
        accept=".csv"
      >
        <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
        <div class="el-upload__text">Drop CSV here or click to upload</div>
      </el-upload>

      <div v-if="selectedFile" class="file-selected">
        <el-icon><Document /></el-icon>
        <span>{{ selectedFile.name }}</span>
        <el-button text type="danger" size="small" @click="clearFile">Remove</el-button>
      </div>

      <el-alert
        type="info"
        :closable="false"
        class="format-hint"
        title="Expected CSV columns: transaction_id, amount, type, timestamp, terminal_id"
      />

      <el-button
        type="primary"
        :loading="uploading"
        :disabled="!selectedFile"
        style="width: 100%; margin-top: 12px"
        @click="submit"
      >
        {{ uploading ? 'Importing...' : 'Import Settlement File' }}
      </el-button>

      <div v-if="summary" class="result-panel">
        <div class="result-title">
          <el-icon color="#67c23a"><CircleCheckFilled /></el-icon>
          Import Complete
        </div>
        <div class="result-grid">
          <div class="result-item">
            <div class="result-label">Total Rows</div>
            <div class="result-value">{{ summary.import?.row_count ?? '-' }}</div>
          </div>
          <div class="result-item">
            <div class="result-label">Matched</div>
            <div class="result-value matched">{{ summary.import?.matched_count ?? '-' }}</div>
          </div>
          <div class="result-item">
            <div class="result-label">Exceptions</div>
            <div class="result-value exceptions">{{ summary.import?.discrepancy_count ?? '-' }}</div>
          </div>
          <div class="result-item">
            <div class="result-label">Daily Variance</div>
            <div class="result-value">${{ Number(summary.import?.daily_variance || 0).toFixed(2) }}</div>
          </div>
        </div>

        <el-alert
          v-if="summary.anomaly_alert"
          type="warning"
          :closable="false"
          :title="`⚠️ Anomaly alert created — variance exceeds $${Number(summary.anomaly_threshold || 0).toFixed(2)}. Review in Anomaly Alerts.`"
          class="mt"
        />

        <el-alert
          v-if="summary.import?.discrepancy_count > 0"
          type="error"
          :closable="false"
          :title="`${summary.import.discrepancy_count} exception(s) require review. Go to Exceptions page.`"
          class="mt"
        />

        <el-button text @click="resetForm" class="mt">Import Another File</el-button>
      </div>
    </el-card>

    <el-card class="history-card" header="Recent Imports">
      <el-table :data="recentImports" stripe size="small">
        <el-table-column label="Date" min-width="160">
          <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column prop="filename" label="File" min-width="200" />
        <el-table-column prop="row_count" label="Rows" width="80" />
        <el-table-column prop="matched_count" label="Matched" width="90" />
        <el-table-column prop="discrepancy_count" label="Exceptions" width="100" />
        <el-table-column label="Variance" width="110">
          <template #default="{ row }">${{ Number(row.daily_variance || 0).toFixed(2) }}</template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import {
  ElAlert,
  ElButton,
  ElCard,
  ElIcon,
  ElMessage,
  ElOption,
  ElTable,
  ElTableColumn,
  ElUpload
} from 'element-plus'
import { CircleCheckFilled, Document, UploadFilled } from '@element-plus/icons-vue'
import { onMounted, ref } from 'vue'
import { importSettlement, listImports } from '@/services/reconciliationService'
import { extractError } from '@/utils/apiError'
import { logger } from '@/utils/logger'

const selectedFile = ref(null)
const uploading = ref(false)
const summary = ref(null)
const recentImports = ref([])

const onFileChange = file => {
  selectedFile.value = file.raw || null
}

const clearFile = () => {
  selectedFile.value = null
}

const resetForm = () => {
  summary.value = null
  selectedFile.value = null
}

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

const loadHistory = async () => {
  try {
    const data = await listImports({ per_page: 5 })
    recentImports.value = data?.data?.data || data?.data || []
  } catch (error) {
    logger.error('Failed to load import history', { error: error?.message })
    ElMessage.error(extractError(error, 'Failed to load import history.'))
  }
}

const submit = async () => {
  if (!selectedFile.value || uploading.value) {
    ElMessage.warning('Please select a CSV file first.')
    return
  }

  uploading.value = true

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)

    const data = await importSettlement(formData)

    if (data?.success) {
      summary.value = data.data
      ElMessage.success('Settlement imported successfully.')
      await loadHistory()
      return
    }

    ElMessage.error(data?.error || 'Import failed.')
  } catch (error) {
    if (error?.response?.status === 409) {
      logger.warn('Settlement import rejected — duplicate file', { file: selectedFile.value?.name })
      ElMessage.error('This file has already been imported (duplicate detected).')
      return
    }

    logger.error('Settlement import failed', { file: selectedFile.value?.name, error: error?.message })
    ElMessage.error(extractError(error, 'Import failed.'))
  } finally {
    uploading.value = false
  }
}

onMounted(loadHistory)
</script>

<style scoped>
.page {
  margin: 24px auto;
  max-width: 900px;
  padding: 0 12px;
}

.toolbar {
  margin-bottom: 16px;
}

.result-panel {
  margin-top: 20px;
  padding: 16px;
  background: #f0f9eb;
  border-radius: 8px;
}

.result-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 600;
  color: #67c23a;
  margin-bottom: 16px;
}

.result-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.result-item {
  text-align: center;
  padding: 12px;
  background: #ffffff;
  border-radius: 6px;
}

.result-label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

.result-value {
  font-size: 22px;
  font-weight: 700;
  color: #1f2d3d;
}

.result-value.matched {
  color: #67c23a;
}

.result-value.exceptions {
  color: #f56c6c;
}

.history-card {
  margin-top: 20px;
}

.format-hint {
  margin: 12px 0;
}

.file-selected {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #f5f7fa;
  border-radius: 6px;
  margin-top: 8px;
  font-size: 14px;
}

.mt {
  margin-top: 12px;
}
</style>
