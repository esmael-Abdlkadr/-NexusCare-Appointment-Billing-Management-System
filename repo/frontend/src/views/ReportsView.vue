<template>
  <div class="page">
    <div class="toolbar">
      <h2>Reports</h2>
    </div>

    <el-card>
      <el-form label-position="top">
        <el-form-item label="Date Range">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            value-format="YYYY-MM-DD"
            range-separator="to"
            start-placeholder="From"
            end-placeholder="To"
          />
        </el-form-item>

        <el-form-item label="Format">
          <el-select v-model="format" style="width: 220px">
            <el-option label="CSV" value="csv" />
            <el-option label="XLSX" value="xlsx" />
          </el-select>
        </el-form-item>

        <div class="buttons">
          <el-button type="primary" @click="download('appointments')">Download Appointments</el-button>
          <el-button type="primary" @click="download('financial')">Download Financial</el-button>
          <el-button type="primary" @click="download('audit')">Download Audit</el-button>
        </div>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ElButton, ElCard, ElDatePicker, ElForm, ElFormItem, ElMessage, ElOption, ElSelect } from 'element-plus'
import { ref } from 'vue'
import { getReport } from '@/services/reportService'

const dateRange = ref([])
const format = ref('csv')

const download = async type => {
  try {
    const from = dateRange.value?.[0]
    const to = dateRange.value?.[1]

    const response = await getReport(type, {
      from: from || undefined,
      to: to || undefined,
      format: format.value
    })

    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    const ext = format.value === 'xlsx' ? 'xlsx' : 'csv'
    a.download = `${type}_report.${ext}`
    document.body.appendChild(a)
    a.click()
    a.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    ElMessage.error('Failed to download report.')
  }
}
</script>

<style scoped>
.page {
  margin: 24px auto;
  max-width: 780px;
  padding: 0 12px;
}

.toolbar {
  margin-bottom: 16px;
}

.buttons {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
</style>
