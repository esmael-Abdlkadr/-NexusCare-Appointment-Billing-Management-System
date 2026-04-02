<template>
  <el-container class="layout-shell">
    <el-aside width="240px" class="sidebar">
      <div class="brand">NexusCare</div>
      <el-menu
        class="nav-menu"
        :default-active="activeMenu"
        :collapse-transition="false"
        background-color="#1e2a3a"
        text-color="#cfd7e3"
        active-text-color="#ffffff"
        @select="handleMenuSelect"
      >
        <template v-for="section in visibleSections" :key="section.title">
          <div class="menu-title">{{ section.title }}</div>
          <el-menu-item
            v-for="item in section.items"
            :key="item.index"
            :index="item.index"
          >
            {{ item.label }}
          </el-menu-item>
        </template>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="topbar">
        <div class="page-title">{{ pageTitle }}</div>
        <div class="topbar-actions">
          <el-tag :type="roleTagType" effect="light">{{ roleLabel }}</el-tag>
          <span class="identifier">{{ identifier }}</span>
          <el-avatar :size="30">{{ avatarLetter }}</el-avatar>
          <el-button plain @click="openProfile">My Profile</el-button>
          <el-button :icon="SwitchButton" type="danger" plain @click="handleLogout">Logout</el-button>
        </div>
      </el-header>

      <el-main class="content">
        <router-view />
      </el-main>
    </el-container>

    <el-dialog v-model="profileOpen" title="My Profile" width="360px">
      <div class="profile-grid">
        <div class="profile-label">Identifier</div>
        <div>{{ profileUser.identifier || '-' }}</div>
        <div class="profile-label">Role</div>
        <div>{{ profileUser.role || '-' }}</div>
        <div class="profile-label">Site</div>
        <div>{{ profileUser.site_id ?? '-' }}</div>
        <div class="profile-label">Email</div>
        <div>{{ maskedEmail }}</div>
        <div class="profile-label">Phone</div>
        <div>{{ maskedPhone }}</div>
      </div>
    </el-dialog>
  </el-container>
</template>

<script setup>
import axios from 'axios'
import { ElMessage } from 'element-plus'
import { SwitchButton } from '@element-plus/icons-vue'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNavSections } from '@/composables/useNavSections'

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()

const profileOpen = ref(false)
const fetchedProfile = ref(null)

const profileUser = computed(() => fetchedProfile.value || authStore.user || {})
const role = computed(() => profileUser.value.role || authStore.user?.role || 'staff')
const identifier = computed(() => profileUser.value.identifier || authStore.user?.identifier || 'User')
const roleLabel = computed(() => role.value)

const roleTagType = computed(() => {
  const map = {
    administrator: 'danger',
    reviewer: 'warning',
    staff: 'primary'
  }

  return map[role.value] || 'info'
})

const avatarLetter = computed(() => identifier.value.charAt(0).toUpperCase())
const pageTitle = computed(() => route.meta?.title || 'NexusCare')

const routeMetaByName = computed(() => {
  const lookup = {}
  router.getRoutes().forEach(record => {
    if (record.name) {
      lookup[String(record.name)] = record.meta || {}
    }
  })
  return lookup
})

const visibleSections = useNavSections(role, routeMetaByName)

const activeMenu = computed(() => {
  const path = route.path
  if (path.includes('/appointments/') && path.endsWith('/versions')) return '__appointment_history__'
  if (path.startsWith('/appointments')) return '/appointments'
  if (path.startsWith('/waitlist')) return '/waitlist'
  if (path.startsWith('/fees')) return '/fees'
  if (path.startsWith('/payments/post')) return '/payments/post'
  if (path.startsWith('/ledger')) return '/ledger'
  if (path.startsWith('/reconciliation/import')) return '/reconciliation/import'
  if (path.startsWith('/reconciliation/exceptions')) return '/reconciliation/exceptions'
  if (path.startsWith('/reconciliation/anomalies')) return '/reconciliation/anomalies'
  if (path.startsWith('/admin/users')) return '/admin/users'
  if (path.startsWith('/admin/recycle')) return '/admin/recycle'
  if (path.startsWith('/audit-logs')) return '/audit-logs'
  if (path.startsWith('/reports')) return '/reports'
  return '/appointments'
})

const maskPhone = value => {
  if (!value) return '-'
  const digits = String(value).replace(/\D+/g, '')
  const last4 = digits.slice(-4).padStart(4, '*')
  return `(***) ***-${last4}`
}

const maskEmail = value => {
  if (!value) return '-'
  const parts = String(value).split('@')
  if (parts.length !== 2) return '***'
  const first = parts[0].slice(0, 1)
  return `${first}***@${parts[1]}`
}

const maskedEmail = computed(() => {
  if (role.value === 'administrator') return profileUser.value.email || '-'
  return maskEmail(profileUser.value.email)
})

const maskedPhone = computed(() => {
  if (role.value === 'administrator') return profileUser.value.phone || '-'
  return maskPhone(profileUser.value.phone)
})

const handleMenuSelect = index => {
  if (index === '__appointment_history__') {
    if (route.params?.id) {
      router.push({ name: 'AppointmentVersions', params: { id: route.params.id } })
      return
    }

    router.push('/appointments')
    return
  }

  router.push(index)
}

const openProfile = async () => {
  profileOpen.value = true

  try {
    const { data } = await axios.get('/auth/me')
    const userData = data?.data?.user || data?.data || null
    if (userData) {
      fetchedProfile.value = userData
    }
  } catch (error) {
    ElMessage.error('Unable to load profile details.')
  }
}

const handleLogout = async () => {
  await authStore.logout()
}
</script>

<style scoped>
.layout-shell {
  min-height: 100vh;
  background: #ffffff;
}

.sidebar {
  width: 240px;
  background: #1e2a3a;
  color: #ffffff;
  border-right: 1px solid #16202d;
}

.brand {
  height: 56px;
  display: flex;
  align-items: center;
  padding: 0 18px;
  font-size: 20px;
  font-weight: 700;
  letter-spacing: 0.2px;
}

.nav-menu {
  border-right: none;
}

:deep(.el-menu-item) {
  white-space: nowrap;
  overflow: visible;
  font-size: 13px;
}

.menu-title {
  color: #8fa1b8;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.8px;
  padding: 14px 20px 6px;
}

:deep(.el-menu-item.is-active) {
  background-color: #25364b;
}

.topbar {
  height: 56px;
  border-bottom: 1px solid #e7ecf3;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
}

.page-title {
  font-size: 18px;
  font-weight: 700;
  color: #1f2d3d;
}

.topbar-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.identifier {
  color: #6b7280;
  font-size: 14px;
}

.content {
  padding: 24px;
  background: #ffffff;
}

.profile-grid {
  display: grid;
  grid-template-columns: 100px 1fr;
  row-gap: 10px;
  column-gap: 10px;
}

.profile-label {
  color: #6b7280;
  font-weight: 600;
}
</style>
