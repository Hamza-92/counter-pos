<template>
  <div class="main-content">
    <breadcumb :page="$t('Login_Device_Management')"  :folder="$t('Settings')" />

    <b-card>
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h5 class="mb-1">Login Device Management</h5>
          <p class="text-muted mb-0">
            Active login sessions across all users (per device / browser).
          </p>
        </div>
        <div class="d-flex">
          <b-button
            variant="outline-primary"
            class="mr-2"
            @click="LoadSecuritySessions()"
            :disabled="securitySessionsLoading || securitySessionsActionLoading"
          >
            Refresh
          </b-button>
          <b-button
            variant="danger"
            @click="LogoutAllOtherDevices()"
            :disabled="securitySessionsLoading || securitySessionsActionLoading || !hasOtherSessions"
          >
            Logout All Other Devices
          </b-button>
        </div>
      </div>

      <div v-if="securitySessionsLoading" class="py-4 text-center text-muted">
        <div class="spinner spinner-primary mr-3"></div>
      </div>

      <b-table
        v-else
        :items="securitySessions"
        :fields="securitySessionFields"
        responsive="sm"
        small
        class="mt-3"
        show-empty
        empty-text="No active sessions found."
      >
        <template #cell(user_name)="row">
          <span>{{ row.item.user_name || '-' }}</span>
        </template>

        <template #cell(device)="row">
          <div class="d-flex align-items-center">
            <span>{{ row.item.device }}</span>
            <b-badge v-if="row.item.is_current" variant="success" class="ms-2">Current</b-badge>
          </div>
        </template>

        <template #cell(ip_address)="row">
          <span>{{ row.item.ip_address || '-' }}</span>
        </template>

        <template #cell(login_at)="row">
          <span>{{ formatDateTime(row.item.login_at) }}</span>
        </template>

        <template #cell(last_activity_at)="row">
          <span>{{ row.item.last_activity_at ? formatDateTime(row.item.last_activity_at) : '-' }}</span>
        </template>

        <template #cell(actions)="row">
          <b-button
            size="sm"
            variant="danger"
            @click="LogoutSession(row.item.token_id)"
            :disabled="securitySessionsLoading || securitySessionsActionLoading || row.item.is_current"
          >
            Logout
          </b-button>
        </template>
      </b-table>
    </b-card>
  </div>
</template>

<script>
import { mapGetters } from "vuex";

export default {
  metaInfo: {
    title: "Login Device Management"
  },
  data() {
    return {
      securitySessions: [],
      securitySessionsLoading: false,
      securitySessionsActionLoading: false,
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions"]),
    hasOtherSessions() {
      return (this.securitySessions || []).some(s => !s.is_current);
    },
    securitySessionFields() {
      return [
        { key: "user_name", label: "User", tdClass: "text-left", thClass: "text-left" },
        { key: "device", label: "Device / Browser", tdClass: "text-left", thClass: "text-left" },
        { key: "ip_address", label: "IP Address", tdClass: "text-left", thClass: "text-left" },
        { key: "login_at", label: "Login date & time", tdClass: "text-left", thClass: "text-left" },
        { key: "last_activity_at", label: "Last activity", tdClass: "text-left", thClass: "text-left" },
        { key: "actions", label: "Action", tdClass: "text-right", thClass: "text-right" }
      ];
    }
  },
  created() {
    // Permission gate (UI). Backend also enforces.
    const perms = this.currentUserPermissions || [];
    const allowed = perms.includes("login_device_management") || perms.includes("setting_system");
    if (!allowed) {
      this.$router.push({ name: "not_authorize" });
      return;
    }
    this.LoadSecuritySessions();
  },
  methods: {
    formatDateTime(v) {
      try {
        if (!v) return "";
        const d = new Date(v);
        if (isNaN(d.getTime())) return String(v);
        return d.toLocaleString();
      } catch (e) {
        return String(v || "");
      }
    },
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },
    LoadSecuritySessions() {
      if (this.securitySessionsLoading) return;
      this.securitySessionsLoading = true;
      axios
        .get("security/sessions")
        .then(response => {
          this.securitySessions = (response && response.data && response.data.sessions) ? response.data.sessions : [];
        })
        .catch(error => {
          const msg =
            (error && error.response && error.response.data && (error.response.data.message || error.response.data.error)) ||
            this.$t("Failed");
          this.makeToast("danger", msg, this.$t("Failed"));
        })
        .finally(() => {
          this.securitySessionsLoading = false;
        });
    },
    LogoutSession(tokenId) {
      if (!tokenId) return;
      if (this.securitySessionsActionLoading) return;
      this.securitySessionsActionLoading = true;
      axios
        .delete(`security/sessions/${encodeURIComponent(tokenId)}`)
        .then(() => {
          this.makeToast("success", "Session logged out successfully.", this.$t("Success"));
          this.LoadSecuritySessions();
        })
        .catch(error => {
          const msg =
            (error && error.response && error.response.data && (error.response.data.message || error.response.data.error)) ||
            this.$t("Failed");
          this.makeToast("danger", msg, this.$t("Failed"));
        })
        .finally(() => {
          this.securitySessionsActionLoading = false;
        });
    },
    LogoutAllOtherDevices() {
      if (this.securitySessionsActionLoading) return;
      this.securitySessionsActionLoading = true;
      axios
        .post("security/sessions/logout-other")
        .then(response => {
          const revoked = response && response.data && typeof response.data.revoked !== "undefined" ? response.data.revoked : null;
          const msg = revoked === null ? "Logged out other devices." : `Logged out ${revoked} other device(s).`;
          this.makeToast("success", msg, this.$t("Success"));
          this.LoadSecuritySessions();
        })
        .catch(error => {
          const msg =
            (error && error.response && error.response.data && (error.response.data.message || error.response.data.error)) ||
            this.$t("Failed");
          this.makeToast("danger", msg, this.$t("Failed"));
        })
        .finally(() => {
          this.securitySessionsActionLoading = false;
        });
    }
  }
};
</script>


























































