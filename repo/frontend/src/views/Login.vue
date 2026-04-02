<template>
  <div class="login-shell">
    <aside class="brand-panel">
      <div class="brand-content">
        <h1>NexusCare</h1>
        <p>Appointment &amp; Billing Management</p>
        <div class="pill-row">
          <span class="pill">Scheduling</span>
          <span class="dot">&middot;</span>
          <span class="pill">Billing</span>
          <span class="dot">&middot;</span>
          <span class="pill">Reconciliation</span>
        </div>
      </div>
    </aside>

    <section class="form-panel">
      <div class="login-card">
        <h2>Welcome back</h2>
        <p class="subtext">Sign in to your account</p>

        <el-form :model="form" label-position="top" @submit.prevent="handleLogin">
          <el-form-item label="Identifier">
            <el-input
              v-model="form.identifier"
              placeholder="Enter identifier"
              autocomplete="username"
              @keyup.enter="handleLogin"
            >
              <template #prefix>
                <el-icon><User /></el-icon>
              </template>
            </el-input>
          </el-form-item>

          <el-form-item label="Password">
            <el-input
              v-model="form.password"
              placeholder="Enter password"
              type="password"
              show-password
              autocomplete="current-password"
              @keyup.enter="handleLogin"
            >
              <template #prefix>
                <el-icon><Lock /></el-icon>
              </template>
            </el-input>
          </el-form-item>

          <el-button class="signin-btn" type="primary" :loading="loading" @click="handleLogin">Sign In</el-button>

          <el-alert
            v-if="errorMessage"
            class="inline-alert"
            type="error"
            :title="errorMessage"
            :closable="false"
            show-icon
          />
        </el-form>

        <p class="footer-note">NexusCare v1.0 · Internal Use Only</p>
      </div>
    </section>
  </div>
</template>

<script setup>
import { Lock, User } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import { validateIdentifier } from '@/utils/validateIdentifier'
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const form = ref({
  identifier: '',
  password: ''
})
const loading = ref(false)
const errorMessage = ref('')

const handleLogin = async () => {
  if (loading.value) {
    return
  }

  errorMessage.value = ''

  if (!form.value.identifier || !form.value.password) {
    errorMessage.value = 'Please enter identifier and password.'
    return
  }

  if (!validateIdentifier(form.value.identifier)) {
    errorMessage.value = 'Identifier must be 3–100 characters and start with a letter or number.'
    return
  }

  loading.value = true

  try {
    await authStore.login({
      identifier: form.value.identifier,
      password: form.value.password
    })

    errorMessage.value = ''
    await router.push('/')
  } catch (error) {
    const status = error?.response?.status
    const apiCode = error?.response?.data?.error
    const meta = error?.response?.data?.data || {}

    if (status === 401) {
      errorMessage.value = 'Invalid identifier or password.'
      return
    }

    if (status === 403 && apiCode === 'ACCOUNT_BANNED') {
      errorMessage.value = 'Your account is banned. Please contact an administrator.'
      return
    }

    if (status === 403 && apiCode === 'ACCOUNT_MUTED') {
      errorMessage.value = `Your account is muted until ${meta.muted_until || 'further notice'}.`
      return
    }

    if (status === 423 && apiCode === 'ACCOUNT_LOCKED') {
      errorMessage.value = `Account locked until ${meta.locked_until || 'later'}.`
      return
    }

    errorMessage.value = 'Unable to log in right now. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-shell {
  display: flex;
  min-height: 100vh;
  width: 100%;
}

.brand-panel {
  flex: 1;
  background: #1e2a3a;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
}

.brand-content {
  max-width: 420px;
}

.brand-content h1 {
  margin: 0;
  font-size: 36px;
  line-height: 1.15;
  font-weight: 800;
}

.brand-content p {
  margin: 12px 0 0;
  color: #8fa1b8;
  font-size: 16px;
}

.pill-row {
  margin-top: 28px;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.pill {
  color: #cfd7e3;
  font-size: 12px;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.dot {
  color: #6f829a;
}

.form-panel {
  flex: 1;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 28px;
}

.login-card {
  width: 100%;
  max-width: 400px;
}

.login-card h2 {
  margin: 0;
  color: #1f2d3d;
  font-size: 24px;
  font-weight: 700;
}

.subtext {
  margin: 8px 0 22px;
  color: #7c8898;
  font-size: 14px;
}

.signin-btn {
  width: 100%;
  height: 44px;
  border-radius: 10px;
  margin-top: 6px;
}

.inline-alert {
  margin-top: 12px;
}

.footer-note {
  margin-top: 20px;
  color: #9aa6b6;
  font-size: 12px;
  text-align: center;
}

@media (max-width: 767px) {
  .brand-panel {
    display: none;
  }

  .form-panel {
    flex: 1 1 100%;
    padding: 22px;
  }
}
</style>
