<template>
  <div class="auth-gate">
    <div v-if="checking" class="auth-gate-loading">
      <div class="auth-gate-spinner"></div>
      <p>Checking access...</p>
    </div>
    <router-view v-else />
  </div>
</template>

<script>
export default {
  name: 'PortalAuthGate',
  data() {
    return { checking: true };
  },
  mounted() {
    var self = this;
    axios.get('/portal/me')
      .then(function (r) {
        if (r.data && r.data.portal_client) {
          self.checking = false;
        } else {
          window.location.replace('/portal/login');
        }
      })
      .catch(function () {
        window.location.replace('/portal/login');
      });
  },
};
</script>

<style scoped>
.auth-gate { min-height: 100vh; }
.auth-gate-loading {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  background: #f1f5f9;
}
.auth-gate-loading p { margin: 0; color: #64748b; font-size: 0.95rem; }
.auth-gate-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #1e293b;
  border-radius: 50%;
  animation: auth-gate-spin 0.8s linear infinite;
}
@keyframes auth-gate-spin {
  to { transform: rotate(360deg); }
}
</style>
