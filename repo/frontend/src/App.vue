<template>
  <el-config-provider>
    <router-view />
  </el-config-provider>
</template>

<script setup>
import { ElConfigProvider } from 'element-plus'
</script>

<style>
html,
body,
#app {
  margin: 0;
  width: 100%;
  min-height: 100%;
}

body {
  font-family: 'Segoe UI', 'Helvetica Neue', sans-serif;
  background: #ffffff;
}
</style>
