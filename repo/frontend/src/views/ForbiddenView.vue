<template>
  <section class="forbidden-page">
    <el-result
      icon="error"
      title="Access Denied"
      sub-title="You don't have permission to view this page."
    >
      <template #extra>
        <el-button type="primary" @click="goBack">Go Back</el-button>
      </template>
    </el-result>
  </section>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const goBack = () => {
  if (window.history.length > 1) {
    router.back()
    return
  }

  router.push('/appointments')
}
</script>

<style scoped>
.forbidden-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
</style>
