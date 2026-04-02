import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/components/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'Login',
      component: () => import('@/views/Login.vue'),
      meta: { public: true }
    },
    {
      path: '/forbidden',
      name: 'Forbidden',
      component: () => import('@/views/ForbiddenView.vue'),
      meta: { public: true }
    },
    {
      path: '/',
      component: AppLayout,
      redirect: '/appointments',
      children: [
        {
          path: 'appointments',
          name: 'AppointmentList',
          component: () => import('@/views/AppointmentList.vue'),
          meta: { title: 'Appointments', roles: ['staff', 'reviewer', 'administrator'] }
        },
        {
          path: 'appointments/create',
          name: 'AppointmentCreate',
          component: () => import('@/views/AppointmentCreate.vue'),
          meta: { title: 'New Appointment', roles: ['staff', 'administrator'] }
        },
        {
          path: 'appointments/:id/versions',
          name: 'AppointmentVersions',
          component: () => import('@/views/AppointmentVersions.vue'),
          meta: { title: 'Appointment History', roles: ['reviewer', 'administrator'] }
        },
        {
          path: 'waitlist',
          name: 'WaitlistView',
          component: () => import('@/views/WaitlistView.vue'),
          meta: { title: 'Waitlist', roles: ['staff', 'administrator'] }
        },
        {
          path: 'payments/post',
          name: 'PaymentPost',
          component: () => import('@/views/PaymentPost.vue'),
          meta: { title: 'Post Payment', roles: ['staff', 'administrator'] }
        },
        {
          path: 'fees',
          name: 'FeeAssessmentList',
          component: () => import('@/views/FeeAssessmentList.vue'),
          meta: { title: 'Fee Assessments', roles: ['staff', 'reviewer', 'administrator'] }
        },
        {
          path: 'fee-rules',
          name: 'FeeRules',
          component: () => import('@/views/FeeRulesView.vue'),
          meta: { title: 'Fee Rules', roles: ['administrator'] }
        },
        {
          path: 'ledger',
          name: 'LedgerView',
          component: () => import('@/views/LedgerView.vue'),
          meta: { title: 'Ledger', roles: ['administrator'] }
        },
        {
          path: 'reconciliation/import',
          name: 'ReconciliationImport',
          component: () => import('@/views/ReconciliationImport.vue'),
          meta: { title: 'Import Settlement', roles: ['reviewer', 'administrator'] }
        },
        {
          path: 'reconciliation/exceptions',
          name: 'ReconciliationExceptions',
          component: () => import('@/views/ReconciliationExceptions.vue'),
          meta: { title: 'Reconciliation Exceptions', roles: ['reviewer', 'administrator'] }
        },
        {
          path: 'reconciliation/anomalies',
          name: 'AnomalyAlerts',
          component: () => import('@/views/AnomalyAlerts.vue'),
          meta: { title: 'Anomaly Alerts', roles: ['reviewer', 'administrator'] }
        },
        {
          path: 'admin/users',
          name: 'UserManagement',
          component: () => import('@/views/UserManagement.vue'),
          meta: { title: 'User Management', roles: ['administrator'] }
        },
        {
          path: 'admin/moderation',
          name: 'ContentModeration',
          component: () => import('@/views/ContentModeration.vue'),
          meta: { title: 'Content Moderation', roles: ['administrator'] }
        },
        {
          path: 'admin/recycle',
          name: 'RecycleView',
          component: () => import('../views/RecycleBinView.vue'),
          meta: { title: 'Recycle Bin', roles: ['administrator'] }
        },
        {
          path: 'audit-logs',
          name: 'AuditLogs',
          component: () => import('@/views/AuditLogs.vue'),
          meta: { title: 'Audit Logs', roles: ['reviewer', 'administrator'] }
        },
        {
          path: 'reports',
          name: 'ReportsView',
          component: () => import('@/views/ReportsView.vue'),
          meta: { title: 'Reports', roles: ['reviewer', 'administrator'] }
        }
      ]
    }
  ]
})

// Exported so the guard logic can be unit-tested independently of the browser environment.
export const applyNavigationGuard = async (to, from, next, authStore) => {
  if (to.meta?.public) {
    next()
    return
  }

  try {
    await authStore.init()
  } catch {
    authStore.clearUser()
    next('/login')
    return
  }

  if (!authStore.user) {
    next('/login')
    return
  }

  const requiredRoles = to.meta?.roles
  if (requiredRoles && !requiredRoles.includes(authStore.user.role)) {
    next('/forbidden')
    return
  }

  next()
}

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  return applyNavigationGuard(to, from, next, authStore)
})

export default router
