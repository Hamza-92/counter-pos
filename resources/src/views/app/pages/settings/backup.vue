<template>
  <div class="main-content">
    <breadcumb :page="$t('BackupDatabase')" :folder="$t('Settings')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <b-card class="wrapper" v-if="!isLoading">
      <!-- Backup destination (clear + simple) -->
      <b-row class="mb-4">
        <b-col lg="12" md="12" sm="12">
          <b-card no-body class="mb-0">
            <b-card-body>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Backup destination</h5>
              </div>

              <b-row>
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Destination">
                    <b-form-radio-group
                      v-model="backupDestination"
                      :options="[
                        { value: 'local', text: 'Local only' },
                        { value: 'cloud', text: 'Cloud (upload after local backup)' },
                      ]"
                      stacked
                    />
                    <small class="text-muted d-block mt-1">
                      Local backups path: <code>/storage/app/public/backup</code>.
                    </small>
                  </b-form-group>
                </b-col>

                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Cloud path / folder (optional)" v-if="backupDestination === 'cloud'">
                    <b-form-input
                      v-model="setting.backup_cloud_path"
                      placeholder="e.g. StockyBackups/"
                    />
                  </b-form-group>
                </b-col>
              </b-row>

              <b-row v-if="backupDestination === 'cloud'">
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Cloud provider">
                    <b-form-select
                      v-model="setting.backup_cloud_provider"
                      :options="[
                        { value: null, text: 'Select provider' },
                        { value: 'google_drive', text: 'Google Drive' },
                        { value: 'dropbox', text: 'Dropbox' },
                        { value: 's3', text: 'S3-compatible (AWS/MinIO/etc.)' },
                      ]"
                    />
                    <small class="text-muted d-block mt-1">
                      Cloud upload runs after the backup is generated locally.
                    </small>
                  </b-form-group>
                </b-col>
              </b-row>

              <!-- S3 fields -->
              <b-row v-if="backupDestination === 'cloud' && setting.backup_cloud_provider === 's3'">
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Bucket">
                    <b-form-input v-model="setting.backup_s3_bucket" placeholder="Bucket name" />
                  </b-form-group>
                </b-col>
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Region">
                    <b-form-input v-model="setting.backup_s3_region" placeholder="e.g. us-east-1" />
                  </b-form-group>
                </b-col>
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Access key">
                    <b-form-input v-model="setting.backup_s3_access_key" placeholder="Access key" />
                  </b-form-group>
                </b-col>
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Secret key (leave blank to keep current)">
                    <b-form-input type="text" v-model="setting.backup_s3_secret_key" placeholder="Secret key" />
                  </b-form-group>
                </b-col>
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Endpoint (optional for MinIO)">
                    <b-form-input v-model="setting.backup_s3_endpoint" placeholder="e.g. https://minio.example.com" />
                  </b-form-group>
                </b-col>
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Path-style URLs (MinIO often requires this)">
                    <b-form-checkbox switch v-model="setting.backup_s3_path_style">Enable</b-form-checkbox>
                  </b-form-group>
                </b-col>
              </b-row>

              <!-- Google Drive fields -->
              <b-row v-if="backupDestination === 'cloud' && setting.backup_cloud_provider === 'google_drive'">
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Folder ID (optional)">
                    <b-form-input v-model="setting.backup_gdrive_folder_id" placeholder="Google Drive folder id" />
                  </b-form-group>
                </b-col>
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Access token (optional, short-lived)">
                    <b-form-input type="text" v-model="setting.backup_gdrive_access_token" placeholder="Bearer token" />
                  </b-form-group>
                </b-col>
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Refresh token (recommended)">
                    <b-form-input type="text" v-model="setting.backup_gdrive_refresh_token" placeholder="Refresh token" />
                  </b-form-group>
                </b-col>
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Client ID">
                    <b-form-input v-model="setting.backup_gdrive_client_id" placeholder="OAuth client id" />
                  </b-form-group>
                </b-col>
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Client secret (leave blank to keep current)">
                    <b-form-input type="text" v-model="setting.backup_gdrive_client_secret" placeholder="OAuth client secret" />
                  </b-form-group>
                </b-col>
              </b-row>

              <!-- Dropbox fields -->
              <b-row v-if="backupDestination === 'cloud' && setting.backup_cloud_provider === 'dropbox'">
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Dropbox folder path (optional)">
                    <b-form-input v-model="setting.backup_dropbox_path" placeholder="e.g. /StockyBackups" />
                  </b-form-group>
                </b-col>
                <b-col lg="6" md="6" sm="12" class="mb-3">
                  <b-form-group label="Access token (leave blank to keep current)">
                    <b-form-input type="text" v-model="setting.backup_dropbox_access_token" placeholder="Dropbox token" />
                  </b-form-group>
                </b-col>
              </b-row>

              <div class="d-flex justify-content-end">
                <b-button variant="primary" @click="Submit_Backup_Settings()">
                  Save backup settings
                </b-button>
              </div>
            </b-card-body>
          </b-card>
        </b-col>
      </b-row>

      <b-alert v-if="backupError" show variant="danger" dismissible @dismissed="backupError = null" class="mb-3">
        <h6 class="alert-heading">Backup Configuration Required</h6>
        <p class="mb-2"><strong>mysqldump not found.</strong> Please configure DUMP_PATH in your .env file.</p>
        <p class="mb-2"><strong>For Laragon on Windows:</strong></p>
        <ol class="mb-2 pl-3">
          <li>Open your <code>.env</code> file in the project root</li>
          <li>Find your MySQL version folder in <code>C:\laragon\bin\mysql\</code></li>
          <li>Add this line (replace with your actual version):</li>
        </ol>
        <pre class="bg-light p-2 mb-2"><code>DUMP_PATH="C:\\laragon\\bin\\mysql\\mysql-8.0.30\\bin\\mysqldump.exe"</code></pre>
        <p class="mb-0">Or use forward slashes: <code>DUMP_PATH="C:/laragon/bin/mysql/mysql-8.0.30/bin/mysqldump.exe"</code></p>
        <p class="mb-0 mt-2"><small>After updating .env, run: <code>php artisan config:clear</code></small></p>
      </b-alert>
      
      <span class="alert alert-danger">{{$t('You_will_find_your_backup_on')}} <strong>/storage/app/public/backup</strong> {{$t('and_save_it_to_your_pc')}}</span>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="backups"
        styleClass="table-hover tableOne vgt-table"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button
            @click="GenerateBackup()"
            size="sm"
            class="btn-rounded"
            variant="btn btn-primary btn-icon m-1"
          >
            <lucide-icon name="plus" />
            {{$t('GenerateBackup')}}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <!-- <a v-b-tooltip.hover @click="DownloadBackup(props.row.date)" title="Download">
              <lucide-icon class="text-25 text-success" name="download" />
            </a> -->
            <a title="Delete" v-b-tooltip.hover @click="DeleteBackup(props.row.date)">
              <lucide-icon class="text-25 text-danger" name="x" />
            </a>
          </span>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>



<script>
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Backup"
  },
  data() {
    return {
      backups: [],
      isLoading: true,
      totalRows: "",
      backupError: null,
      setting: {
        id: "",
        // Optional cloud backup destination (local backup remains default)
        backup_cloud_enabled: false,
        backup_cloud_provider: null,
        backup_cloud_path: "",
        // S3-compatible
        backup_s3_bucket: "",
        backup_s3_region: "",
        backup_s3_access_key: "",
        backup_s3_secret_key: "",
        backup_s3_endpoint: "",
        backup_s3_path_style: false,
        // Google Drive
        backup_gdrive_folder_id: "",
        backup_gdrive_access_token: "",
        backup_gdrive_refresh_token: "",
        backup_gdrive_client_id: "",
        backup_gdrive_client_secret: "",
        // Dropbox
        backup_dropbox_path: "",
        backup_dropbox_access_token: "",
        // Flags (populated by API) to show if secrets are already saved (but hidden)
        backup_s3_has_secret_key: false,
        backup_gdrive_has_access_token: false,
        backup_gdrive_has_refresh_token: false,
        backup_gdrive_has_client_secret: false,
        backup_dropbox_has_access_token: false,
      }
    };
  },

  computed: {
    columns() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Filesize"),
          field: "size",
          tdClass: "text-left",
          thClass: "text-left"
        },

        {
          label: this.$t("Action"),
          field: "actions",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    },
    // Backup destination selector (simple UI):
    // - local => no cloud upload, keep local
    // - cloud => upload to cloud, delete local after successful upload
    backupDestination: {
      get() {
        const cloudRaw = this.setting ? this.setting.backup_cloud_enabled : false;
        const cloud = (cloudRaw === true || cloudRaw === 1 || cloudRaw === '1' || cloudRaw === 'true');
        return cloud ? 'cloud' : 'local';
      },
      set(v) {
        if (!this.setting) return;
        this.setting.backup_cloud_enabled = (v === 'cloud');
      }
    }
  },

  methods: {
    //---------------------------------- Get Settings --------------------\\
    Get_Settings() {
      axios
        .get("get_Settings_data", { params: { include_secrets: 1 } })
        .then(response => {
          // Merge to preserve default keys/reactivity for newly added settings fields
          this.setting = { ...this.setting, ...(response.data.settings || {}) };
        })
        .catch(error => {
          // Silently fail if settings endpoint doesn't exist
        });
    },

    //---------------------------------- Submit Backup Settings --------------------\\
    Submit_Backup_Settings() {
      NProgress.start();
      NProgress.set(0.1);
      var self = this;
      self.data = new FormData();
      self.data.append("backup_cloud_enabled", self.setting.backup_cloud_enabled ? 1 : 0);
      self.data.append("backup_cloud_provider", self.setting.backup_cloud_provider || "");
      self.data.append("backup_cloud_path", self.setting.backup_cloud_path || "");

      // S3-compatible
      self.data.append("backup_s3_bucket", self.setting.backup_s3_bucket || "");
      self.data.append("backup_s3_region", self.setting.backup_s3_region || "");
      self.data.append("backup_s3_access_key", self.setting.backup_s3_access_key || "");
      self.data.append("backup_s3_secret_key", self.setting.backup_s3_secret_key || "");
      self.data.append("backup_s3_endpoint", self.setting.backup_s3_endpoint || "");
      self.data.append("backup_s3_path_style", self.setting.backup_s3_path_style ? 1 : 0);

      // Google Drive
      self.data.append("backup_gdrive_folder_id", self.setting.backup_gdrive_folder_id || "");
      self.data.append("backup_gdrive_access_token", self.setting.backup_gdrive_access_token || "");
      self.data.append("backup_gdrive_refresh_token", self.setting.backup_gdrive_refresh_token || "");
      self.data.append("backup_gdrive_client_id", self.setting.backup_gdrive_client_id || "");
      self.data.append("backup_gdrive_client_secret", self.setting.backup_gdrive_client_secret || "");

      // Dropbox
      self.data.append("backup_dropbox_path", self.setting.backup_dropbox_path || "");
      self.data.append("backup_dropbox_access_token", self.setting.backup_dropbox_access_token || "");
      self.data.append("_method", "put");

      axios
        .post("settings/" + self.setting.id, self.data)
        .then(response => {
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          const msg =
            (error && error.response && error.response.data && (error.response.data.message || error.response.data.error)) ||
            this.$t("InvalidData");
          this.makeToast("danger", msg, this.$t("Failed"));
          NProgress.done();
        });
    },

    //---------------------------------- Generate Backup --------------------\\

    GenerateBackup() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("generate_new_backup")
        .then(response => {
          Fire.$emit("Generate_Backup");
          
          // Check if backup was successful
          if (response.data && response.data.success === false) {
            // Backup generation failed
            const errorMsg = response.data.error || response.data.message || this.$t("Failed_to_generate_backup") || "Failed to generate backup";
            
            // Check if it's a mysqldump not found error
            if (errorMsg.includes('mysqldump') && errorMsg.includes('not found')) {
              this.backupError = true;
            }
            
            this.makeToast("danger", errorMsg, this.$t("Failed"));
          } else {
            // Clear any previous errors on success
            this.backupError = null;
            // Backup successful
            const message = this.$t("Backup_generated_successfully") || "Backup generated successfully";
            
            this.makeToast("success", message, this.$t("Success"));
          }
          
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(error => {
          // Handle error response
          let errorMsg = this.$t("Failed_to_generate_backup") || "Failed to generate backup";
          
          if (error.response && error.response.data) {
            if (error.response.data.error) {
              errorMsg = error.response.data.error;
            } else if (error.response.data.message) {
              errorMsg = error.response.data.message;
            }
          } else if (error.message) {
            errorMsg = error.message;
          }
          
          // Check if it's a mysqldump not found error
          if (errorMsg.includes('mysqldump') && errorMsg.includes('not found')) {
            this.backupError = true;
          }
          
          this.makeToast("danger", errorMsg, this.$t("Failed"));
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        });
    },

  
    //----------------------------------------  Get All backups -------------------------\\
    Get_Backups() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("get_backup")
        .then(response => {
          this.backups = response.data.backups;
          this.totalRows = response.data.totalRows;

          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
        })
        .catch(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //---------------------------------- Make Toast --------------------\\
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //--------------------------------- Delete Backup --------------------\\
    DeleteBackup(date) {
      this.$swal({
        title: this.$t("Delete_Title"),
        text: this.$t("Delete_Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete_cancelButtonText"),
        confirmButtonText: this.$t("Delete_confirmButtonText")
      }).then(result => {
        if (result.value) {
          axios
            .delete("delete_backup/" + date)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );

              Fire.$emit("Delete_Backup");
            })
            .catch(() => {
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    }
  }, //end Method

  //----------------------------- Created function-------------------
  created: function() {
    this.Get_Settings();
    this.Get_Backups();

    Fire.$on("Generate_Backup", () => {
      setTimeout(() => {
        this.Get_Backups();
      }, 500);
    });

    Fire.$on("Delete_Backup", () => {
      setTimeout(() => {
        this.Get_Backups();
        // Complete the animation of the  progress bar.
        NProgress.done();
      }, 500);
    });
  }
};
</script>