<template>
  <div class="main-content">
    <breadcumb :page="$t('update_settings')" :folder="$t('Settings')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div class="col-md-12" v-if="!isLoading">
      <div class="card update-card">
        <div class="card-header">
          <span>{{$t('Update_Log')}}</span>
        </div>
        <div class="card-body">
          <div class="alert alert-danger">{{$t('Note_update')}}</div>

          <b-tabs active-nav-item-class="nav nav-tabs" content-class="mt-3">

            <!-- ==================== TAB 1: AUTO UPGRADE ==================== -->
            <b-tab :title="$t('Automatic_Update')" active>
              <div class="row mt-3">

                <!-- Version Comparison & Actions -->
                <div class="col-lg-8 col-md-12 mb-4">
                  <div class="card inner-card">
                    <div class="card-body p-4">
                      <!-- Status Banner -->
                      <div class="status-banner" :class="statusBannerClass">
                        <div class="d-flex align-items-center">
                          <div class="status-icon-wrapper" :class="statusIconClass">
                            <lucide-icon :name="statusIcon" :class="{ 'spin-icon': updating }" />
                          </div>
                          <div class="ml-3">
                            <h5 class="mb-1 font-weight-bold">{{ statusTitle }}</h5>
                            <p class="mb-0 small opacity-80">{{ statusDescription }}</p>
                          </div>
                        </div>
                        <span class="badge status-badge" :class="statusBadgeClass">
                          {{ statusLabel }}
                        </span>
                      </div>

                      <!-- Version Comparison -->
                      <div class="version-comparison mt-4">
                        <div class="row align-items-center">
                          <div class="col-md-5">
                            <div class="version-box current">
                              <div class="version-label text-muted small text-uppercase mb-1">
                                {{ $t('Installed_Version') }}
                              </div>
                              <div class="version-number">
                                <span class="h3 font-weight-bold mb-0">v{{ currentVersion }}</span>
                              </div>
                              <div class="text-muted small mt-1">
                                {{ $t('Currently_Running') }}
                              </div>
                            </div>
                          </div>
                          <div class="col-md-2 text-center py-3">
                            <div class="version-arrow" :class="{ 'has-update': hasUpdate }">
                              <lucide-icon name="arrow-right" style="font-size: 1.5rem;" />
                            </div>
                          </div>
                          <div class="col-md-5">
                            <div class="version-box latest" :class="{ 'highlight': hasUpdate }">
                              <div class="version-label text-muted small text-uppercase mb-1">
                                {{ $t('Latest_Version') }}
                              </div>
                              <div class="version-number">
                                <span class="h3 font-weight-bold mb-0">
                                  {{ latestVersion ? 'v' + latestVersion : '—' }}
                                </span>
                              </div>
                              <div class="small mt-1" :class="hasUpdate ? 'text-warning' : 'text-success'">
                                {{ hasUpdate ? $t('New_Version_Available') : $t('You_are_up_to_date') }}
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Action Buttons -->
                      <div class="d-flex align-items-center justify-content-between mt-4 pt-3 border-top flex-wrap" style="gap:10px;">
                        <div class="d-flex align-items-center">
                          <button
                            class="btn btn-outline-secondary btn-sm d-flex align-items-center"
                            :disabled="checking"
                            @click="checkForUpdates"
                          >
                            <span v-if="checking" class="spinner-border spinner-border-sm mr-2"></span>
                            <lucide-icon class="mr-2" name="repeat" v-else />
                            {{ $t('Check_for_Updates') }}
                          </button>
                          <span class="text-muted small ml-3" v-if="lastChecked">
                            {{ lastCheckedText }}
                          </span>
                        </div>
                        <div class="d-flex align-items-center">
                          <button
                            v-if="hasUpdate && !updating"
                            class="btn btn-primary d-flex align-items-center"
                            :disabled="SubmitProcessing || !canUpdate"
                            @click="confirmUpdate"
                          >
                            <span v-if="SubmitProcessing" class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>
                            <lucide-icon class="mr-2" name="arrow-up-circle" v-else />
                            {{ $t('Update_Now') }} — v{{ latestVersion }}
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Preflight Checks -->
                <div class="col-lg-4 col-md-12 mb-4">
                  <div class="card inner-card h-100">
                    <div class="card-body p-4">
                      <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="font-weight-bold mb-0">{{ $t('System_Checks') }}</h6>
                        <button
                          class="btn btn-link btn-sm p-0"
                          :disabled="preflightLoading"
                          @click="run_preflight"
                        >
                          <span v-if="preflightLoading" class="spinner-border spinner-border-sm"></span>
                          <lucide-icon name="repeat" v-else />
                        </button>
                      </div>
                      <div v-if="preflight" class="preflight-checks">
                        <div class="check-item" v-for="(check, key) in preflightItems" :key="key">
                          <div class="d-flex align-items-center">
                            <span class="check-dot" :class="check.ok ? 'bg-success' : 'bg-danger'"></span>
                            <span class="small">{{ check.label }}</span>
                          </div>
                        </div>
                      </div>
                      <div v-else class="text-muted small text-center py-4">
                        {{ $t('Run_checks_to_verify_readiness') }}
                      </div>
                      <div v-if="preflight" class="mt-3 pt-3 border-top">
                        <div class="d-flex align-items-center">
                          <span class="check-dot" :class="canUpdate ? 'bg-success' : 'bg-danger'"></span>
                          <span class="small font-weight-bold">
                            {{ canUpdate ? $t('All_checks_passed') : $t('Some_checks_failed') }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Update Progress -->
                <div class="col-12 mb-4" v-if="updating">
                  <div class="card inner-card">
                    <div class="card-body p-4">
                      <h6 class="font-weight-bold mb-3">{{ $t('Update_Progress') }}</h6>

                      <!-- Step Tracker -->
                      <div class="step-tracker">
                        <div
                          class="step-item"
                          v-for="(step, index) in updateSteps"
                          :key="step.key"
                          :class="stepClass(step, index)"
                        >
                          <div class="step-indicator">
                            <div class="step-circle">
                              <span v-if="isStepComplete(step)" class="step-check">&#10003;</span>
                              <span
                                v-else-if="isStepActive(step)"
                                class="spinner-border spinner-border-sm"
                              ></span>
                              <span v-else class="step-number">{{ index + 1 }}</span>
                            </div>
                            <div v-if="index < updateSteps.length - 1" class="step-line" :class="{ 'active': isStepComplete(step) }"></div>
                          </div>
                          <div class="step-content mt-2">
                            <div class="small font-weight-bold">{{ step.label }}</div>
                            <div class="text-muted" style="font-size: 0.7rem;">{{ step.description }}</div>
                          </div>
                        </div>
                      </div>

                      <!-- Progress Bar -->
                      <div class="mt-4">
                        <div class="d-flex justify-content-between mb-1">
                          <span class="small text-muted">{{ currentStepLabel }}</span>
                          <span class="small font-weight-bold">{{ updatePercent }}%</span>
                        </div>
                        <div class="progress" style="height: 6px; border-radius: 3px;">
                          <div
                            class="progress-bar progress-bar-animated"
                            :class="updateFailed ? 'bg-danger' : 'bg-primary'"
                            role="progressbar"
                            :style="{ width: updatePercent + '%' }"
                            :aria-valuenow="updatePercent"
                            aria-valuemin="0"
                            aria-valuemax="100"
                          ></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Changelog / Release Notes -->
                <div class="col-lg-8 col-md-12 mb-4" v-if="changelog && changelog.length">
                  <div class="card inner-card">
                    <div class="card-body p-4">
                      <h6 class="font-weight-bold mb-3">
                        <lucide-icon class="mr-2" name="clipboard-list" />
                        {{ $t('Release_Notes') }}
                      </h6>
                      <div class="changelog-list">
                        <div
                          v-for="(entry, idx) in changelog"
                          :key="idx"
                          class="changelog-entry"
                          :class="{ 'border-bottom pb-3 mb-3': idx < changelog.length - 1 }"
                        >
                          <div class="d-flex align-items-center mb-2">
                            <span class="badge badge-pill mr-2" :class="entry.version === latestVersion ? 'badge-primary' : 'badge-light'">
                              v{{ entry.version }}
                            </span>
                            <span class="text-muted small" v-if="entry.date">{{ entry.date }}</span>
                          </div>
                          <ul class="changelog-items mb-0 pl-3">
                            <li v-for="(item, i) in entry.items" :key="i" class="small mb-1">
                              <span class="changelog-tag mr-1" :class="'tag-' + (item.type || 'misc')">
                                {{ (item.type || 'misc').toUpperCase() }}
                              </span>
                              {{ item.text }}
                            </li>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Update History -->
                <div :class="changelog && changelog.length ? 'col-lg-4' : 'col-lg-12'" class="col-md-12 mb-4" v-if="updateHistory && updateHistory.length">
                  <div class="card inner-card">
                    <div class="card-body p-4">
                      <h6 class="font-weight-bold mb-3">
                        <lucide-icon class="mr-2" name="clock" />
                        {{ $t('Update_History') }}
                      </h6>
                      <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                          <thead>
                            <tr class="text-muted small text-uppercase">
                              <th>{{ $t('Version') }}</th>
                              <th>{{ $t('Status') }}</th>
                              <th>{{ $t('Date') }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(h, idx) in updateHistory" :key="idx">
                              <td class="font-weight-bold">v{{ h.version }}</td>
                              <td>
                                <span class="badge badge-pill" :class="h.status === 'success' ? 'badge-success' : 'badge-danger'">
                                  {{ h.status }}
                                </span>
                              </td>
                              <td class="text-muted small">{{ h.time }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </b-tab>

            <!-- ==================== TAB 2: MANUAL UPGRADE (original) ==================== -->
            <b-tab :title="$t('Manual_Update')">
              <div class="col-md-12 mt-3">
                <h5>Please follow these steps, To Update your application</h5>
                <div class="allert alert-danger">Note 1: If you have made any changes in the code manually then your changes will be lost.</div>
                <div class="allert alert-danger">Note 2: only admin or user who has permission "system_setting" he can upgrade the system</div>
                <ul>
                  <li>
                    <strong>Step 1 : </strong>Take back up of your database,  Go to <a href="/app/settings/Backup">Backup</a> Click on Generate Backup ,
                    You will find it in <strong>/storage/app/public/backup</strong>  and save it to your pc To restore it if there is an error ,
                    or Go to your PhpMyAdmin and export your database then and save it to your pc To restore it if there is an error
                  </li>

                  <li>
                    <strong>Step 2 : </strong> Take back up of your files before updating.
                  </li>

                  <li>
                    <strong>Step 3 : </strong>  Download the latest version from your codecanyon and Extract it .
                  </li>

                  <li>
                    <strong>Step 4 : </strong>  Make sure to remove the previous files , <strong>except</strong> the following :
                    <ul>
                      <li>file   : <strong>.env</strong></li>
                      <li>Folder : <strong>storage</strong></li>
                      <li>Folder : <strong>images folder in public : /public/images</strong></li>
                    </ul>
                  </li>

                  <li>
                    <strong>Step 5 : </strong> Re-upload the files and folders from the new update , <strong>except</strong> the following :
                    <ul>
                      <li>file   : <strong>.env</strong></li>
                      <li>Folder : <strong>storage</strong></li>
                      <li>Folder : <strong>images folder in public : /public/images</strong></li>
                    </ul>
                  </li>

                  <li>
                    <strong>Step 6 : </strong>Visit  http://your_app/update to update your database
                  </li>

                  <li>
                    <strong>Step 7 : </strong> Hard Clear your cache browser
                  </li>

                  <li>
                    <strong>Step 8 : </strong> You are done! Enjoy the updated application
                  </li>

                </ul>
                <div class="allert alert-danger">Note: If any pages are not loading or blank, make sure you cleared your browser cache.</div>
              </div>
            </b-tab>

          </b-tabs>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Update Settings"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      currentVersion: "",
      latestVersion: "",
      latestInfo: null,
      checking: false,
      lastChecked: null,
      preflightLoading: false,
      preflight: null,
      canUpdate: false,
      updating: false,
      updateFailed: false,
      updatePercent: 0,
      updateStep: "idle",
      progressTimer: null,
      changelog: [],
      updateHistory: [],
    };
  },

  computed: {
    hasUpdate() {
      if (!this.latestVersion || !this.currentVersion) return false;
      return this.versionCompare(this.latestVersion, this.currentVersion) > 0;
    },

    statusBannerClass() {
      if (this.updating) return "status-updating";
      if (this.updateFailed) return "status-failed";
      if (this.hasUpdate) return "status-available";
      return "status-current";
    },

    statusIconClass() {
      if (this.updating) return "icon-updating";
      if (this.updateFailed) return "icon-failed";
      if (this.hasUpdate) return "icon-available";
      return "icon-current";
    },

    statusIcon() {
      if (this.updating) return "loader";
      if (this.updateFailed) return "x";
      if (this.hasUpdate) return "download";
      return "check";
    },

    statusTitle() {
      if (this.updating) return this.$t("Updating_Application");
      if (this.updateFailed) return this.$t("Update_Failed");
      if (this.hasUpdate) return this.$t("Update_Available");
      return this.$t("System_Up_to_Date");
    },

    statusDescription() {
      if (this.updating) return this.$t("Please_wait_update_in_progress");
      if (this.updateFailed) return this.$t("Update_failed_rolled_back");
      if (this.hasUpdate)
        return this.$t("Version") + " " + this.latestVersion + " " + this.$t("is_ready_to_install");
      return this.$t("You_already_have_the_latest_version");
    },

    statusBadgeClass() {
      if (this.updating) return "badge-updating";
      if (this.updateFailed) return "badge-danger";
      if (this.hasUpdate) return "badge-warning";
      return "badge-success";
    },

    statusLabel() {
      if (this.updating) return this.$t("Updating");
      if (this.updateFailed) return this.$t("Failed");
      if (this.hasUpdate) return this.$t("Update_Available");
      return this.$t("Up_to_Date");
    },

    lastCheckedText() {
      if (!this.lastChecked) return "";
      var now = Date.now();
      var diff = Math.floor((now - this.lastChecked) / 1000);
      if (diff < 10) return this.$t("Last_checked_just_now");
      if (diff < 60) return this.$t("Last_checked") + " " + diff + "s " + this.$t("ago");
      if (diff < 3600) return this.$t("Last_checked") + " " + Math.floor(diff / 60) + "m " + this.$t("ago");
      return this.$t("Last_checked") + " " + Math.floor(diff / 3600) + "h " + this.$t("ago");
    },

    preflightItems() {
      if (!this.preflight) return [];
      var items = [];
      if (this.preflight.permissions) {
        Object.keys(this.preflight.permissions).forEach(function(k) {
          var p = this.preflight.permissions[k];
          items.push({
            label: k.replace(/_/g, " ") + " writable",
            ok: p.writable
          });
        }.bind(this));
      }
      if (this.preflight.network) {
        items.push({
          label: "Update server reachable",
          ok: this.preflight.network.ok
        });
      }
      if (this.preflight.disk) {
        var free = this.preflight.disk.storage_free || 0;
        items.push({
          label: "Disk space (" + this.formatBytes(free) + " free)",
          ok: free > 200 * 1024 * 1024
        });
      }
      return items;
    },

    updateSteps() {
      return [
        { key: "starting", label: this.$t("Preparing"), description: this.$t("Initializing_update"), threshold: 0 },
        { key: "database_backup_created", label: this.$t("Backup"), description: this.$t("Creating_backup"), threshold: 10 },
        { key: "download_complete", label: this.$t("Download"), description: this.$t("Downloading_package"), threshold: 35 },
        { key: "extracted", label: this.$t("Verify"), description: this.$t("Verifying_integrity"), threshold: 50 },
        { key: "deployment_completed", label: this.$t("Install"), description: this.$t("Deploying_files"), threshold: 70 },
        { key: "migrations_completed", label: this.$t("Migrate"), description: this.$t("Database_migrations"), threshold: 85 },
        { key: "completed", label: this.$t("Done"), description: this.$t("Finalizing"), threshold: 100 }
      ];
    },

    currentStepLabel() {
      var step = this.updateStep;
      var found = this.updateSteps.find(function(s) { return s.key === step; });
      return found ? found.label : step;
    }
  },

  methods: {
    versionCompare(a, b) {
      var pa = String(a).split(".").map(Number);
      var pb = String(b).split(".").map(Number);
      for (var i = 0; i < Math.max(pa.length, pb.length); i++) {
        var na = pa[i] || 0;
        var nb = pb[i] || 0;
        if (na > nb) return 1;
        if (na < nb) return -1;
      }
      return 0;
    },

    formatBytes(bytes) {
      if (!bytes || bytes === 0) return "0 B";
      var k = 1024;
      var sizes = ["B", "KB", "MB", "GB"];
      var i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + " " + sizes[i];
    },

    stepClass(step, index) {
      if (this.isStepComplete(step)) return "step-complete";
      if (this.isStepActive(step)) return "step-active";
      return "step-pending";
    },

    isStepComplete(step) {
      return this.updatePercent > step.threshold;
    },

    isStepActive(step) {
      var idx = this.updateSteps.indexOf(step);
      var prevThreshold = idx > 0 ? this.updateSteps[idx - 1].threshold : -1;
      return this.updatePercent > prevThreshold && this.updatePercent <= step.threshold;
    },

    checkForUpdates() {
      var self = this;
      self.checking = true;
      axios
        .get("get_version_info")
        .then(function(response) {
          var data = response.data;
          if (data && typeof data === "object") {
            self.currentVersion = data.current_version || self.currentVersion;
            self.latestVersion = data.latest_version || "";
            self.latestInfo = data.latest_info || null;
            self.changelog = data.changelog || [];
            self.updateHistory = data.update_history || [];
          } else if (typeof data === "string" && data) {
            self.latestVersion = data;
          }
          self.lastChecked = Date.now();
        })
        .catch(function() {
          self.makeToast("danger", self.$t("Failed_to_check_updates"), self.$t("Failed"));
        })
        .finally(function() {
          self.checking = false;
        });
    },

    run_preflight() {
      var self = this;
      self.preflightLoading = true;
      axios
        .get("update/preflight")
        .then(function(res) {
          self.preflight = res.data;
          self.canUpdate = !!(res.data && res.data.ok);
        })
        .catch(function() {
          self.preflight = null;
          self.canUpdate = false;
        })
        .finally(function() {
          self.preflightLoading = false;
        });
    },

    confirmUpdate() {
      var self = this;
      this.$swal({
        title: this.$t("Are_you_sure"),
        text: this.$t("Update_confirmation_text"),
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: this.$t("Yes_update"),
        cancelButtonText: this.$t("Cancel"),
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
      }).then(function(result) {
        if (result.isConfirmed || result.value) {
          self.Update_system();
        }
      }).catch(function() {
        // SweetAlert2 may not be available, run directly
        self.Update_system();
      });
    },

    //------------------------ Update ---------------------------\\
    Update_system() {
      var self = this;
      self.SubmitProcessing = true;
      self.updating = true;
      self.updateFailed = false;
      self.updatePercent = 0;
      self.updateStep = "starting";
      this.startProgressPolling();
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("one_click_update")
        .then(function(response) {
          self.SubmitProcessing = false;
          self.updating = false;
          self.stopProgressPolling();
          self.updatePercent = 100;
          self.updateStep = "completed";
          NProgress.done();
          self.makeToast("success", self.$t("Successfully_Updated"), self.$t("Success"));
          Fire.$emit("Event_update");
          setTimeout(function() {
            self.checkForUpdates();
          }, 2000);
        })
        .catch(function(error) {
          self.SubmitProcessing = false;
          self.updateFailed = true;
          self.stopProgressPolling();
          NProgress.done();
          var msg = self.$t("InvalidData");
          if (error && error.response && error.response.data) {
            msg = error.response.data.message || msg;
          }
          self.makeToast("danger", msg, self.$t("Failed"));
          setTimeout(function() {
            self.updating = false;
            self.updateFailed = false;
          }, 5000);
        });
    },

    startProgressPolling() {
      var self = this;
      if (this.progressTimer) return;
      this.progressTimer = setInterval(function() {
        axios
          .get("update/progress")
          .then(function(res) {
            if (res && res.data) {
              self.updatePercent = res.data.percent || 0;
              self.updateStep = res.data.step || "running";
              if (self.updatePercent >= 100) {
                self.stopProgressPolling();
              }
            }
          })
          .catch(function() {});
      }, 1500);
    },

    stopProgressPolling() {
      if (this.progressTimer) {
        clearInterval(this.progressTimer);
        this.progressTimer = null;
      }
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    }
  },

  //----------------------------- Created function-------------------
  created: function() {
    this.checkForUpdates();
    this.run_preflight();

    var self = this;
    Fire.$on("Event_update", function() {
      self.checkForUpdates();
    });

    // Refresh "last checked" text every 30s
    this._lastCheckedTimer = setInterval(function() {
      self.$forceUpdate();
    }, 30000);
  },

  mounted: function() {
    this.isLoading = false;
  },

  beforeDestroy: function() {
    this.stopProgressPolling();
    if (this._lastCheckedTimer) {
      clearInterval(this._lastCheckedTimer);
    }
  }
};
</script>

<style scoped>
/* ============================================
   UPDATE SETTINGS - MODERN UI
   ============================================ */

.update-card {
  border-radius: 12px;
}

.inner-card {
  border: none;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
  transition: box-shadow 0.2s ease;
}
.inner-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08), 0 2px 4px rgba(0, 0, 0, 0.04);
}

/* Status Banner */
.status-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-radius: 10px;
  transition: all 0.3s ease;
}
.status-current {
  background: rgba(16, 185, 129, 0.08);
}
.status-available {
  background: rgba(245, 158, 11, 0.08);
}
.status-updating {
  background: rgba(59, 130, 246, 0.08);
}
.status-failed {
  background: rgba(239, 68, 68, 0.08);
}

.status-icon-wrapper {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
}
.icon-current {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
}
.icon-available {
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
}
.icon-updating {
  background: rgba(59, 130, 246, 0.15);
  color: #3b82f6;
}
.icon-failed {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.status-badge {
  font-size: 0.75rem;
  padding: 6px 14px;
  border-radius: 20px;
  font-weight: 600;
  letter-spacing: 0.02em;
}
.badge-updating {
  background: rgba(59, 130, 246, 0.15);
  color: #3b82f6;
}

/* Version Comparison */
.version-box {
  padding: 20px;
  border-radius: 10px;
  background: #f8fafc;
  text-align: center;
  transition: all 0.3s ease;
  border: 1px solid transparent;
}
.version-box.highlight {
  background: rgba(245, 158, 11, 0.06);
  border-color: rgba(245, 158, 11, 0.2);
}
.version-arrow {
  color: #cbd5e1;
  transition: color 0.3s ease;
}
.version-arrow.has-update {
  color: #f59e0b;
}

/* Preflight Checks */
.check-item {
  padding: 8px 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.04);
}
.check-item:last-child {
  border-bottom: none;
}
.check-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  margin-right: 10px;
  flex-shrink: 0;
}

/* Step Tracker */
.step-tracker {
  display: flex;
  justify-content: space-between;
  padding: 0 10px;
}
.step-item {
  flex: 1;
  text-align: center;
  position: relative;
}
.step-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
}
.step-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 600;
  background: #f1f5f9;
  color: #94a3b8;
  border: 2px solid #e2e8f0;
  transition: all 0.3s ease;
  z-index: 1;
  flex-shrink: 0;
}
.step-line {
  flex: 1;
  height: 2px;
  background: #e2e8f0;
  margin: 0 -2px;
  transition: background 0.4s ease;
}
.step-line.active {
  background: #3b82f6;
}

.step-complete .step-circle {
  background: #3b82f6;
  border-color: #3b82f6;
  color: #fff;
}
.step-active .step-circle {
  border-color: #3b82f6;
  color: #3b82f6;
  background: rgba(59, 130, 246, 0.08);
}
.step-check {
  font-size: 0.85rem;
}

/* Changelog */
.changelog-items {
  list-style: none;
}
.changelog-items li {
  position: relative;
  padding-left: 4px;
}
.changelog-tag {
  display: inline-block;
  font-size: 0.6rem;
  padding: 1px 6px;
  border-radius: 3px;
  font-weight: 700;
  letter-spacing: 0.04em;
  vertical-align: middle;
}
.tag-fix { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.tag-feature { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.tag-improvement { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.tag-misc { background: rgba(107, 114, 128, 0.1); color: #6b7280; }

/* Spin Animation */
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.spin-icon {
  animation: spin 1.2s linear infinite;
  display: inline-block;
}

/* Dark Mode */
.dark-mode .inner-card,
[data-theme="dark"] .inner-card {
  background: #1e293b;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}
.dark-mode .inner-card:hover,
[data-theme="dark"] .inner-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}
.dark-mode .version-box,
[data-theme="dark"] .version-box {
  background: #0f172a;
}
.dark-mode .version-box.highlight,
[data-theme="dark"] .version-box.highlight {
  background: rgba(245, 158, 11, 0.08);
  border-color: rgba(245, 158, 11, 0.25);
}
.dark-mode .step-circle,
[data-theme="dark"] .step-circle {
  background: #334155;
  border-color: #475569;
  color: #94a3b8;
}
.dark-mode .step-line,
[data-theme="dark"] .step-line {
  background: #334155;
}
.dark-mode .check-item,
[data-theme="dark"] .check-item {
  border-color: rgba(255, 255, 255, 0.06);
}
.dark-mode .status-banner h5,
[data-theme="dark"] .status-banner h5 {
  color: #f1f5f9;
}

/* Responsive */
@media (max-width: 768px) {
  .status-banner {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  .version-arrow {
    transform: rotate(90deg);
  }
  .step-tracker {
    flex-wrap: wrap;
    gap: 8px;
  }
  .step-item {
    flex: 0 0 calc(25% - 6px);
  }
  .step-line {
    display: none;
  }
}

@media (max-width: 576px) {
  .step-item {
    flex: 0 0 calc(33.33% - 6px);
  }
}
</style>
