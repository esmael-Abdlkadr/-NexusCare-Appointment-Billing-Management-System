<template>
  <div class="page">
    <div class="toolbar">
      <h2>User Management</h2>
      <div class="scope-badges">
        <el-tag size="small" type="info">Site #{{ scope.siteId }}</el-tag>
        <el-tag size="small" type="info">Dept #{{ scope.departmentId }}</el-tag>
      </div>
    </div>
    <el-card v-loading="loading">
      <div style="display:flex; align-items:center; margin-bottom:12px">
        <el-input
          v-model="filterIdentifier"
          placeholder="Search by identifier..."
          clearable
          @change="onFilterChange"
          style="width:240px"
        />
        <el-select
          v-model="filterRole"
          placeholder="All roles"
          clearable
          @change="onFilterChange"
          style="width:160px; margin-left:8px"
        >
          <el-option label="staff" value="staff" />
          <el-option label="reviewer" value="reviewer" />
          <el-option label="administrator" value="administrator" />
        </el-select>
        <el-button type="primary" @click="openCreateDialog" style="margin-left:auto">+ Create User</el-button>
      </div>

      <div v-if="selectedRows.length > 0" class="bulk-toolbar">
        <span class="bulk-count">{{ selectedRows.length }} selected</span>
        <el-button size="small" type="warning" @click="bulkBan">Bulk Ban</el-button>
        <el-button size="small" type="danger" @click="bulkDelete">Bulk Delete</el-button>
      </div>

      <el-table ref="tableRef" :data="rows" stripe @selection-change="onSelectionChange">
        <el-table-column type="selection" width="46" />
        <el-table-column prop="identifier" label="Identifier" min-width="170" />
        <el-table-column label="Role" width="140">
          <template #default="{ row }">
            <el-tag size="small">{{ row.role }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Site" width="120">
          <template #default="{ row }">Site #{{ row.site_id }}</template>
        </el-table-column>
        <el-table-column label="Muted Until" min-width="190">
          <template #default="{ row }">{{ row.muted_until ? formatDate(row.muted_until) : '—' }}</template>
        </el-table-column>
        <el-table-column label="Status" width="120">
          <template #default="{ row }">
            <el-tag :type="row.is_banned ? 'danger' : 'success'" size="small">
              {{ row.is_banned ? 'Banned' : 'Active' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Actions" min-width="260">
          <template #default="{ row }">
            <el-button size="small" type="primary" @click="toggleBan(row)">{{ row.is_banned ? 'Unban' : 'Ban' }}</el-button>
            <el-dropdown trigger="click" @command="command => handleMoreCommand(command, row)">
              <el-button size="small">More ▼</el-button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="change_role">Change Role</el-dropdown-item>
                  <el-dropdown-item command="mute">Mute 24h</el-dropdown-item>
                  <el-dropdown-item command="reset">Reset Password</el-dropdown-item>
                  <el-dropdown-item command="unlock">Unlock</el-dropdown-item>
                  <el-dropdown-item command="delete" style="color:#f56c6c">Delete</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="page"
        :page-size="perPage"
        :total="total"
        layout="total, prev, pager, next"
        @current-change="load"
        style="margin-top:16px; text-align:right"
      />
      <UserCreateDialog ref="createDialogRef" @created="load(1)" />
      <UserResetPasswordDialog ref="resetPwdDialogRef" @reset="() => {}" />

      <!-- Change Role Dialog -->
      <el-dialog v-model="changeRoleDialog.visible" title="Change User Role" width="360px">
        <el-form label-position="top">
          <el-form-item label="New Role">
            <el-select v-model="changeRoleDialog.role" style="width:100%">
              <el-option label="Staff" value="staff" />
              <el-option label="Reviewer" value="reviewer" />
              <el-option label="Administrator" value="administrator" />
            </el-select>
          </el-form-item>
        </el-form>
        <template #footer>
          <el-button @click="changeRoleDialog.visible = false">Cancel</el-button>
          <el-button type="primary" @click="submitChangeRole">Save</el-button>
        </template>
      </el-dialog>
    </el-card>
  </div>
</template>

<script setup>
import { ElMessage, ElMessageBox } from 'element-plus'
import { onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import UserCreateDialog from '@/components/UserCreateDialog.vue'
import UserResetPasswordDialog from '@/components/UserResetPasswordDialog.vue'
import {
  bulkUserAction,
  deleteAdminUser,
  listAdminUsers,
  unlockUser,
  updateAdminUser
} from '@/services/userService'
import { extractError } from '../utils/apiError.js'

const rows = ref([])
const authStore = useAuthStore()
const scope = ref({
  siteId: authStore.user?.site_id || 1,
  departmentId: authStore.user?.department_id || 1
})
const loading = ref(false)
const filterIdentifier = ref('')
const filterRole = ref('')
const page = ref(1)
const perPage = ref(20)
const total = ref(0)
const createDialogRef = ref(null)
const tableRef = ref(null)
const selectedRows = ref([])
const resetPwdDialogRef = ref(null)
const changeRoleDialog = ref({ visible: false, userId: null, role: 'staff' })

const formatDate = iso => {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const load = async (targetPage = page.value) => {
  page.value = targetPage
  loading.value = true
  try {
    const params = {
      identifier: filterIdentifier.value || undefined,
      role: filterRole.value || undefined,
      page: page.value,
      per_page: perPage.value,
      site_id: scope.value.siteId,
      department_id: scope.value.departmentId
    }
    const data = await listAdminUsers(params)
    rows.value = data?.data?.data || []
    total.value = data?.data?.total || 0
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to load users.'))
  } finally {
    loading.value = false
  }
}

const onFilterChange = async () => {
  page.value = 1
  await load(1)
}

const toggleBan = async row => {
  try {
    const data = await updateAdminUser(row.id, { is_banned: !row.is_banned })
    if (data?.success) {
      ElMessage.success('User updated.')
      await load(page.value)
      return
    }
    ElMessage.error(data?.error || 'Failed to update user.')
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to update user.'))
  }
}

const mute24h = async row => {
  try {
    const until = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString()
    const data = await updateAdminUser(row.id, { muted_until: until })
    if (data?.success) {
      ElMessage.success('User muted for 24h.')
      await load(page.value)
      return
    }
    ElMessage.error(data?.error || 'Failed to mute user.')
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to mute user.'))
  }
}

const onSelectionChange = selection => {
  selectedRows.value = selection || []
}

const clearSelection = () => {
  selectedRows.value = []
  tableRef.value?.clearSelection()
}

const bulkBan = async () => {
  const ids = selectedRows.value.map(row => row.id)
  if (!ids.length) return
  try {
    const data = await bulkUserAction({
      action: 'ban',
      user_ids: ids,
      ids
    })
    if (data?.success) {
      ElMessage.success('Bulk ban completed.')
      clearSelection()
      await load(page.value)
      return
    }

    ElMessage.error(data?.error || 'Bulk ban failed.')
  } catch (error) {
    ElMessage.error(extractError(error, 'Bulk ban failed.'))
  }
}

const bulkDelete = async () => {
  const ids = selectedRows.value.map(row => row.id)
  if (!ids.length) return
  try {
    await ElMessageBox.confirm(
      'This will soft-delete all selected users. Continue?',
      'Confirm Bulk Delete',
      {
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        type: 'warning'
      }
    )
    const data = await bulkUserAction({
      action: 'delete',
      user_ids: ids,
      ids
    })
    if (data?.success) {
      ElMessage.success('Bulk delete completed.')
      clearSelection()
      await load(page.value)
      return
    }

    ElMessage.error(data?.error || 'Bulk delete failed.')
  } catch (error) {
    if (error === 'cancel' || error === 'close') {
      return
    }
    ElMessage.error(extractError(error, 'Bulk delete failed.'))
  }
}

const unlock = async row => {
  try {
    const data = await unlockUser(row.id)
    if (data?.success) {
      ElMessage.success('User unlocked.')
      await load(page.value)
      return
    }
    ElMessage.error(data?.error || 'Failed to unlock user.')
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to unlock user.'))
  }
}

const softDelete = async row => {
  try {
    const data = await deleteAdminUser(row.id)
    if (data?.success) {
      ElMessage.success('User deleted (soft).')
      await load(page.value)
      return
    }
    ElMessage.error(data?.error || 'Failed to delete user.')
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to delete user.'))
  }
}

const openChangeRoleDialog = row => {
  changeRoleDialog.value = { visible: true, userId: row.id, role: row.role }
}

const submitChangeRole = async () => {
  try {
    const data = await updateAdminUser(changeRoleDialog.value.userId, { role: changeRoleDialog.value.role })
    if (data?.success) {
      ElMessage.success('Role updated.')
      changeRoleDialog.value.visible = false
      await load(page.value)
      return
    }
    ElMessage.error(data?.error || 'Failed to update role.')
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to update role.'))
  }
}

const handleMoreCommand = (command, row) => {
  if (command === 'change_role') {
    openChangeRoleDialog(row)
    return
  }
  if (command === 'mute') {
    mute24h(row)
    return
  }
  if (command === 'reset') {
    resetPwdDialogRef.value?.open(row)
    return
  }

  if (command === 'unlock') {
    unlock(row)
    return
  }
  if (command === 'delete') {
    softDelete(row)
  }
}

const openCreateDialog = () => {
  createDialogRef.value?.open()
}

onMounted(() => load(1))
</script>

<style scoped>
.page { margin: 24px auto; max-width: 1200px; padding: 0 12px; }
.toolbar { margin-bottom: 16px; }
.scope-badges { display: flex; gap: 6px; margin-top: 8px; }
.bulk-toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.bulk-count { font-size: 13px; color: #6b7280; }
</style>
