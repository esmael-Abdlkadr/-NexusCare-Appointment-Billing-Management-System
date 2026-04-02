<template>
  <el-alert
    v-if="visible"
    type="error"
    :closable="false"
    show-icon
    title="Scheduling conflict detected"
    class="conflict-alert"
  >
    <template #default>
      <p class="detail">
        Conflict type: <strong>{{ conflictType }}</strong>
      </p>
      <p v-if="slots.length" class="detail">Suggested next available slots:</p>
      <div class="slots">
        <el-button
          v-for="slot in slots"
          :key="slot.start_time"
          size="small"
          type="danger"
          plain
          @click="$emit('select-slot', slot)"
        >
          {{ formatSlot(slot) }}
        </el-button>
      </div>
    </template>
  </el-alert>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  conflictType: {
    type: String,
    default: ''
  },
  nextAvailableSlots: {
    type: Array,
    default: () => []
  }
})

defineEmits(['select-slot'])

const slots = computed(() => props.nextAvailableSlots || [])
const visible = computed(() => !!props.conflictType)

const formatSlot = slot => {
  const start = new Date(slot.start_time)
  const end = new Date(slot.end_time)
  return `${start.toLocaleString()} - ${end.toLocaleTimeString()}`
}
</script>

<style scoped>
.conflict-alert {
  margin-bottom: 16px;
}

.detail {
  margin: 0 0 8px;
}

.slots {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
