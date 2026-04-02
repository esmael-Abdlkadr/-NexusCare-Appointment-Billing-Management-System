import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import App from './App.vue'
import router from './router'
import axios from 'axios'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(ElementPlus)

// Set axios base URL
axios.defaults.baseURL = '/api'
axios.defaults.withCredentials = true

// Centralized 401 interceptor — clears auth state and redirects to login
axios.interceptors.response.use(
  response => response,
  async error => {
    if (error?.response?.status === 401) {
      const currentPath = router.currentRoute.value.path
      if (currentPath !== '/login') {
        // Dynamically import to avoid circular dependency at module load time
        const { useAuthStore } = await import('./stores/auth')
        const authStore = useAuthStore()
        authStore.logout()
        router.push('/login')
      }
    }
    return Promise.reject(error)
  }
)

app.mount('#app')
