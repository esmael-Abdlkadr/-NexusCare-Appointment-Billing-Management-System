<template>
  <div class="page">
    <div class="toolbar">
      <h2>Recycle Bin</h2>
      <el-select v-model="entityType" style="width: 180px" @change="load">
        <el-option label="Users" value="user" />
        <el-option label="Appointments" value="appointment" />
        <el-option label="Resources" value="resource" />
        <el-option label="Waitlist" value="waitlist" />
      </el-select>
    </div>

    <el-card v-loading="loading">
      <div v-if="selectedRows.length > 0" class="bulk-toolbar">
        <span class="bulk-count">{{ selectedRows.length }} selected</span>
        <el-button size="small" type="primary" @click="bulkRestore">Bulk Restore</el-button>
        <el-button size="small" type="danger" @click="bulkDelete">Bulk Delete</el-button>
      </div>

      <el-table ref="tableRef" :data="rows" stripe @selection-change="onSelectionChange">
        <el-table-column type="selection" width="46" />
        <el-table-column label="Type" width="140">
          <template #default="{ row }">{{ formatType(row.entity_type) }}</template>
        </el-table-column>
        <el-table-column label="Identifier / Name" min-width="220">
          <template #default="{ row }">{{ row.display_name || `${formatType(row.entity_type)} #${row.entity_id}` }}</template>
        </el-table-column>
        <el-table-column label="Deleted At" min-width="180">
          <template #default="{ row }">{{ formatDateTime(row.deleted_at) }}</template>
        </el-table-column>
        <el-table-column label="Actions" width="250">
          <template #default="{ row }">
            <el-button size="small" type="primary" @click="restore(row)">Restore</el-button>
            <el-button size="small" type="danger" @click="confirmDelete(row)">Permanently Delete</el-button>
          </template>
        </el-table-column>

        <template #empty>
          <el-empty description="Recycle bin is empty" />
        </template>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ElMessage, ElMessageBox } from 'element-plus'
import { onMounted, ref } from 'vue'
import {
  bulkDelete as bulkDeleteItems,
  bulkRestore as bulkRestoreItems,
  deleteItem,
  listRecycleBin,
  restoreItem
} from '@/services/recycleBinService'
import { extractError } from '../utils/apiError.js'

const rows = ref([])
const entityType = ref('user')
const tableRef = ref(null)
const selectedRows = ref([])
const loading = ref(false)

const formatType = type => {
  const map = {
    user: 'User',
    appointment: 'Appointment',
    resource: 'Resource',
    waitlist: 'Waitlist'
  }
  return map[type] || type
}

const formatDateTime = value => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString()
}

const load = async () => {
  loading.value = true
  try {
    const data = await listRecycleBin({ entity_type: entityType.value })
    rows.value = data?.data || []
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to load recycle bin.'))
  } finally {
    loading.value = false
  }
}

const onSelectionChange = selection => {
  selectedRows.value = selection || []
}

const clearSelection = () => {
  selectedRows.value = []
  tableRef.value?.clearSelection()
}

const toBulkItems = () => selectedRows.value.map(r => ({ entity_type: r.entity_type, entity_id: r.entity_id }))

const bulkRestore = async () => {
  const items = toBulkItems()
  if (!items.length) return

  try {
    const data = await bulkRestoreItems(items)
    if (data?.success) {
      ElMessage.success('Selected records restored.')
      clearSelection()
      await load()
      return
    }
    ElMessage.error(data?.error || 'Bulk restore failed.')
  } catch (error) {
    ElMessage.error(extractError(error, 'Bulk restore failed.'))
  }
}

const bulkDelete = async () => {
  const items = toBulkItems()
  if (!items.length) return

  try {
    await ElMessageBox.confirm(
      'This will permanently delete all selected records. Continue?',
      'Confirm Bulk Permanent Deletion',
      {
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        type: 'warning'
      }
    )

    const data = await bulkDeleteItems(items)
    if (data?.success) {
      ElMessage.success('Selected records permanently deleted.')
      clearSelection()
      await load()
      return
    }

    ElMessage.error(data?.error || 'Bulk permanent delete failed.')
  } catch (error) {
    if (error === 'cancel' || error === 'close') {
      return
    }
    ElMessage.error(extractError(error, 'Bulk permanent delete failed.'))
  }
}

const restore = async row => {
  try {
    const data = await restoreItem(row.entity_type, row.entity_id)
    if (data?.success) {
      ElMessage.success('Record restored successfully.')
      await load()
      return
    }
    ElMessage.error(data?.error || 'Failed to restore record.')
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to restore record.'))
  }
}

const confirmDelete = async row => {
  try {
    await ElMessageBox.confirm(
      'This will permanently delete the record. Continue?',
      'Confirm Permanent Deletion',
      {
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        type: 'warning'
      }
    )

    const data = await deleteItem(row.entity_type, row.entity_id)

    if (data?.success) {
      ElMessage.success('Record permanently deleted.')
      await load()
      return
    }

    ElMessage.error(data?.error || 'Failed to permanently delete record.')
  } catch (error) {
    if (error === 'cancel' || error === 'close') {
      return
    }
    ElMessage.error(extractError(error, 'Failed to permanently delete record.'))
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
  margin-bottom: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.bulk-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.bulk-count {
  font-size: 13px;
  color: #6b7280;
}
</style>
