<template>
  <div class="page">
    <div class="toolbar">
      <h2>Reconciliation Exceptions</h2>
    </div>

    <el-card v-loading="loading">
      <el-tabs v-model="tab" @tab-change="load">
        <el-tab-pane label="All" name="all" />
        <el-tab-pane label="Unresolved" name="unresolved" />
        <el-tab-pane label="Resolved" name="resolved" />
      </el-tabs>

      <el-table :data="rows" stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="Import" min-width="180">
          <template #default="{ row }">{{ importMap[row.import_id] || `Import #${row.import_id}` }}</template>
        </el-table-column>
        <el-table-column label="Reason" width="180">
          <template #default="{ row }">
            <el-tag :type="reasonType(row.reason)">{{ formatReason(row.reason) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Status" width="130">
          <template #default="{ row }">
            <el-tag :type="row.status === 'resolved' ? 'success' : 'danger'" size="small">
              {{ row.status === 'resolved' ? 'Resolved' : 'Unresolved' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Expected" width="110">
          <template #default="{ row }">
            {{ row.expected_amount != null ? '$' + Number(row.expected_amount).toFixed(2) : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="Actual" width="110">
          <template #default="{ row }">
            {{ row.actual_amount != null ? '$' + Number(row.actual_amount).toFixed(2) : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="resolution_note" label="Resolution" min-width="220" />
        <el-table-column label="Actions" width="140">
          <template #default="{ row }">
            <el-button
              v-if="row.status === 'unresolved' && canResolve"
              size="small"
              type="primary"
              @click="openResolve(row)"
            >
              Resolve
            </el-button>
          </template>
        </el-table-column>

        <template #empty>
          <el-empty description="No exceptions found" />
        </template>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" title="Resolve Exception" width="520px">
      <el-form ref="resolveFormRef" :model="resolveForm" :rules="resolveRules" label-position="top">
        <el-form-item label="Resolution Note" prop="resolution_note">
          <el-input
            v-model="resolveForm.resolution_note"
            type="textarea"
            :rows="3"
            placeholder="Describe how this exception was resolved (min 10 characters)..."
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancel</el-button>
        <el-button type="primary" :loading="resolving" @click="submitResolve">Save</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import {
  ElButton,
  ElCard,
  ElDialog,
  ElEmpty,
  ElForm,
  ElFormItem,
  ElInput,
  ElMessage,
  ElTabPane,
  ElTable,
  ElTableColumn,
  ElTabs,
  ElTag
} from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { listExceptions, listImports, resolveException } from '@/services/reconciliationService'
import { useAuthStore } from '@/stores/auth'
import { extractError } from '@/utils/apiError'
import { logger } from '@/utils/logger'

const authStore = useAuthStore()
const rows = ref([])
const tab = ref('all')
const dialogVisible = ref(false)
const activeId = ref(null)
const resolveForm = reactive({ resolution_note: '' })
const resolveFormRef = ref(null)
const resolving = ref(false)
const importMap = ref({})
const loading = ref(false)
const canResolve = computed(() => ['reviewer', 'administrator'].includes(authStore.user?.role))

const resolveRules = {
  resolution_note: [
    { required: true, message: 'Resolution note is required.', trigger: 'blur' },
    { min: 10, message: 'Resolution note must be at least 10 characters.', trigger: 'blur' }
  ]
}

const formatReason = reason => {
  const map = {
    ORDER_NOT_FOUND: 'Order Not Found',
    AMOUNT_MISMATCH: 'Amount Mismatch'
  }
  return map[reason] || reason
}

const reasonType = reason => {
  if (reason === 'ORDER_NOT_FOUND') return 'danger'
  if (reason === 'AMOUNT_MISMATCH') return 'warning'
  return 'info'
}

const loadImports = async () => {
  try {
    const data = await listImports({ per_page: 100 })
    const list = data?.data?.data || data?.data || []
    list.forEach(i => {
      importMap.value[i.id] = i.filename || `Import #${i.id}`
    })
  } catch (error) {
    logger.warn('Could not load import names for exception context', { error: error?.message })
  }
}

const load = async () => {
  loading.value = true
  try {
    const status = tab.value === 'all' ? undefined : tab.value
    const data = await listExceptions({ status })
    rows.value = data?.data?.data ?? []
  } catch (error) {
    logger.error('Failed to load reconciliation exceptions', { error: error?.message })
    ElMessage.error(extractError(error, 'Failed to load exceptions.'))
  } finally {
    loading.value = false
  }
}

const openResolve = row => {
  activeId.value = row.id
  resolveForm.resolution_note = ''
  dialogVisible.value = true
  // Reset field-level validation state from any previous open
  resolveFormRef.value?.clearValidate()
}

const submitResolve = async () => {
  if (!activeId.value || resolving.value) return

  const valid = await resolveFormRef.value?.validate().catch(() => false)
  if (!valid) return

  resolving.value = true
  try {
    const data = await resolveException(activeId.value, {
      resolution_note: resolveForm.resolution_note.trim()
    })

    if (data?.success) {
      ElMessage.success('Exception resolved.')
      dialogVisible.value = false
      await load()
      return
    }

    ElMessage.error(data?.error || 'Failed to resolve exception.')
  } catch (error) {
    logger.error('Failed to resolve exception', { id: activeId.value, error: error?.message })
    ElMessage.error(extractError(error, 'Failed to resolve exception.'))
  } finally {
    resolving.value = false
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
  max-width: 1080px;
  padding: 0 12px;
}

.toolbar {
  margin-bottom: 16px;
}
</style>
