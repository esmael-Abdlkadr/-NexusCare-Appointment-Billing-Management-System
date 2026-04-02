<template>
  <div class="page">
    <div class="toolbar">
      <h2>Appointment Versions</h2>
      <el-button text @click="router.push('/appointments')">Back</el-button>
    </div>

    <el-card>
      <el-timeline>
        <el-timeline-item
          v-for="version in versions"
          :key="version.id"
          :timestamp="new Date(version.created_at).toLocaleString()"
        >
          <div class="version-card">
            <div>Changed by: {{ version.changed_by }}</div>
            <pre>{{ JSON.stringify(version.snapshot, null, 2) }}</pre>
          </div>
        </el-timeline-item>
      </el-timeline>
    </el-card>
  </div>
</template>

<script setup>
import { ElButton, ElCard, ElMessage, ElTimeline, ElTimelineItem } from 'element-plus'
import { onMounted, ref } from 'vue'
import { getAppointmentVersions } from '@/services/appointmentService'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const versions = ref([])

const loadVersions = async () => {
  try {
    const data = await getAppointmentVersions(route.params.id)
    versions.value = data?.data || []
  } catch (error) {
    ElMessage.error('Failed to load version history.')
  }
}

onMounted(loadVersions)
</script>

<style scoped>
.page {
  margin: 24px auto;
  max-width: 960px;
  padding: 0 12px;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.version-card pre {
  white-space: pre-wrap;
  background: #f7f7f7;
  border-radius: 8px;
  padding: 8px;
}
</style>
