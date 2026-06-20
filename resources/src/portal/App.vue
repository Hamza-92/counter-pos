<template>
  <div id="portal" class="portal-app">
    <router-view />
  </div>
</template>

<script>
export default {
  name: 'PortalApp',
};
</script>

<style>
.portal-app {
  min-height: 100vh;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
</style>
