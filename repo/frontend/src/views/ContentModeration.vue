<template>
  <div class="page">
    <div class="toolbar">
      <h2>Account Moderation</h2>
      <span style="color: #909399; font-size: 13px; margin-left: 12px;">
        Review banned and muted accounts — apply or lift account restrictions
      </span>
    </div>

    <el-tabs v-model="activeTab" @tab-change="onTabChange">
      <el-tab-pane label="Banned Users" name="banned" />
      <el-tab-pane label="Muted Users" name="muted" />
    </el-tabs>

    <el-card v-loading="loading">
      <el-table :data="rows" style="width: 100%" empty-text="No accounts in this moderation queue.">
        <el-table-column prop="identifier" label="Identifier" min-width="160" />
        <el-table-column prop="role" label="Role" width="110">
          <template #default="{ row }">
            <el-tag size="small" :type="roleTagType(row.role)">{{ row.role }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Status" width="130">
          <template #default="{ row }">
            <el-tag v-if="row.is_banned" type="danger" size="small">Banned</el-tag>
            <el-tag v-else-if="row.muted_until" type="warning" size="small">
              Muted until {{ formatDate(row.muted_until) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Actions" width="160" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="row.is_banned"
              size="small"
              type="success"
              :loading="actionLoading === row.id"
              @click="unban(row)"
            >
              Unban
            </el-button>
            <el-button
              v-else-if="row.muted_until"
              size="small"
              type="primary"
              :loading="actionLoading === row.id"
              @click="unmute(row)"
            >
              Unmute
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div v-if="totalPages > 1" style="margin-top: 16px; display: flex; justify-content: flex-end;">
        <el-pagination
          v-model:current-page="page"
          :page-size="perPage"
          :total="total"
          layout="prev, pager, next"
          @current-change="load"
        />
      </div>
    </el-card>

    <el-row :gutter="16" style="margin-top: 20px;">
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span style="font-weight: 600;">Bulk Actions</span></template>
          <p style="color: #606266; font-size: 13px; margin-bottom: 12px;">
            Apply moderation actions across all currently listed accounts.
          </p>
          <el-button type="danger" size="small" :loading="bulkLoading" @click="bulkBanAll">
            Ban All Listed
          </el-button>
          <el-button type="primary" size="small" :loading="bulkLoading" style="margin-left: 8px;" @click="bulkUnbanAll">
            Unban All Listed
          </el-button>

          <el-form ref="bulkFormRef" :model="bulkForm" :rules="bulkRules" label-position="top" style="margin-top: 16px;">
            <el-form-item label="Bulk action" prop="action">
              <el-select v-model="bulkForm.action" style="width: 220px;">
                <el-option label="Ban all listed" value="ban" />
                <el-option label="Unban all listed" value="unban" />
                <el-option label="Mute all listed" value="mute" />
              </el-select>
            </el-form-item>

            <el-form-item v-if="bulkForm.action === 'mute'" label="Muted until" prop="muted_until">
              <el-date-picker
                v-model="bulkForm.muted_until"
                type="datetime"
                placeholder="Select mute end time"
                style="width: 320px;"
              />
            </el-form-item>

            <el-button type="warning" size="small" :loading="bulkLoading" @click="applySelectedBulkAction">
              Apply Selected Action
            </el-button>
          </el-form>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span style="font-weight: 600;">Quick Links</span></template>
          <el-button text type="primary" @click="$router.push('/admin/recycle')">
            Recycle Bin &rarr;
          </el-button>
          <el-button text type="primary" style="margin-left: 8px;" @click="$router.push('/audit-logs')">
            Audit Logs &rarr;
          </el-button>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import {
  ElButton,
  ElCard,
  ElCol,
  ElDatePicker,
  ElForm,
  ElFormItem,
  ElMessage,
  ElOption,
  ElPagination,
  ElRow,
  ElSelect,
  ElTable,
  ElTableColumn,
  ElTabPane,
  ElTabs,
  ElTag
} from 'element-plus'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { bulkUserAction, listAdminUsers, updateAdminUser } from '@/services/userService'
import { extractError } from '@/utils/apiError'

const loading = ref(false)
const actionLoading = ref(null)
const bulkLoading = ref(false)
const rows = ref([])
const page = ref(1)
const perPage = 20
const total = ref(0)
const activeTab = ref('banned')
const bulkFormRef = ref(null)
const bulkForm = reactive({
  action: 'ban',
  muted_until: null
})

const bulkRules = {
  muted_until: [
    {
      validator: (rule, value, cb) => {
        if (bulkForm.action !== 'mute') {
          cb()
          return
        }

        if (!value) {
          cb(new Error('Muted until is required for mute action.'))
          return
        }

        if (new Date(value) < new Date(Date.now() + 23 * 60 * 60 * 1000)) {
          cb(new Error('Mute duration must be at least 24 hours from now.'))
          return
        }

        cb()
      },
      trigger: 'change'
    }
  ]
}

const totalPages = computed(() => Math.ceil(total.value / perPage))

const load = async (targetPage = page.value) => {
  page.value = targetPage
  loading.value = true
  try {
    const params = { per_page: perPage, page: page.value }
    if (activeTab.value === 'banned') params.is_banned = 1
    else params.is_muted = 1

    const data = await listAdminUsers(params)
    rows.value = data?.data?.data ?? []
    total.value = data?.data?.total ?? 0
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to load moderation queue.'))
  } finally {
    loading.value = false
  }
}

const onTabChange = () => {
  page.value = 1
  load(1)
}

const unban = async (row) => {
  actionLoading.value = row.id
  try {
    await updateAdminUser(row.id, { is_banned: false })
    ElMessage.success(`${row.identifier} has been unbanned.`)
    await load()
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to unban user.'))
  } finally {
    actionLoading.value = null
  }
}

const unmute = async (row) => {
  actionLoading.value = row.id
  try {
    await updateAdminUser(row.id, { muted_until: null })
    ElMessage.success(`${row.identifier} has been unmuted.`)
    await load()
  } catch (error) {
    ElMessage.error(extractError(error, 'Failed to unmute user.'))
  } finally {
    actionLoading.value = null
  }
}

const bulkBanAll = async () => {
  bulkForm.action = 'ban'
  await applySelectedBulkAction()
}

const bulkUnbanAll = async () => {
  bulkForm.action = 'unban'
  await applySelectedBulkAction()
}

const applySelectedBulkAction = async () => {
  if (!rows.value.length) return

  const isMute = bulkForm.action === 'mute'
  if (isMute) {
    const valid = await bulkFormRef.value?.validate().catch(() => false)
    if (!valid) return
  }

  bulkLoading.value = true
  try {
    const user_ids = rows.value.map(r => r.id)
    const payload = {
      action: bulkForm.action,
      user_ids
    }

    if (isMute) {
      payload.muted_until = bulkForm.muted_until
    }

    await bulkUserAction(payload)

    const successByAction = {
      ban: 'Bulk ban applied.',
      unban: 'Bulk unban applied.',
      mute: 'Bulk mute applied.'
    }

    ElMessage.success(successByAction[bulkForm.action] || 'Bulk action applied.')
    await load()
  } catch (error) {
    ElMessage.error(extractError(error, 'Bulk action failed.'))
  } finally {
    bulkLoading.value = false
  }
}

watch(() => bulkForm.action, (action) => {
  if (action === 'mute') {
    bulkForm.muted_until = new Date(Date.now() + 24 * 60 * 60 * 1000)
  }
})

const formatDate = (val) => {
  if (!val) return ''
  const d = new Date(val)
  return Number.isNaN(d.getTime()) ? val : d.toLocaleString()
}

const roleTagType = (role) => ({ administrator: 'danger', reviewer: 'warning', staff: '' })[role] ?? ''

onMounted(() => load())
</script>

<style scoped>
.page {
  margin: 24px auto;
  max-width: 960px;
  padding: 0 12px;
}
.toolbar {
  margin-bottom: 16px;
  display: flex;
  align-items: center;
}
</style>
