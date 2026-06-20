<template>
  <div class="main-content">
    <breadcumb :page="$t('TransferDetail')" :folder="$t('ListTransfers')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-if="!isLoading" class="shadow-sm">
      <b-row>
        <b-col md="12" class="mb-3">
          <router-link
            :to="{ name: 'index_transfer' }"
            class="btn btn-secondary btn-icon ripple btn-sm mr-2"
          >
            <lucide-icon name="arrow-left" />
            <span>{{$t('Back')}}</span>
          </router-link>
          <router-link
            v-if="currentUserPermissions && currentUserPermissions.includes('transfer_edit')"
            title="Edit"
            class="btn btn-success btn-icon ripple btn-sm mr-2"
            :to="{ name:'edit_transfer', params: { id: $route.params.id } }"
          >
            <lucide-icon name="pencil" />
            <span>{{$t('Edit')}}</span>
          </router-link>
          <button @click="Print_Transfer_PDF()" class="btn btn-primary btn-icon ripple btn-sm mr-2">
            <lucide-icon name="file-text" />
            {{$t('PDF')}}
          </button>
          <button @click="print()" class="btn btn-warning btn-icon ripple btn-sm mr-2">
            <lucide-icon name="receipt" />
            {{$t('print')}}
          </button>
          <button
            v-if="transfer.approval_status === 'pending' && currentUserPermissions && currentUserPermissions.includes('transfer_edit')"
            @click="Approve_Transfer()"
            class="btn btn-info btn-icon ripple btn-sm mr-2"
          >
            <lucide-icon name="check" />
            {{$t('Approve')}}
          </button>
          <button
            v-if="currentUserPermissions && currentUserPermissions.includes('transfer_delete')"
            @click="Delete_Transfer()"
            class="btn btn-danger btn-icon ripple btn-sm"
          >
            <lucide-icon name="x" />
            {{$t('Del')}}
          </button>
        </b-col>
      </b-row>

      <div class="invoice mt-5" id="print_Invoice">
        <div class="invoice-print">
          <b-row class="justify-content-md-center mb-4">
            <h4 class="font-weight-bold">{{$t('TransferDetail')}} : {{transfer.Ref}}</h4>
          </b-row>
          <hr>

          <b-row class="mt-5">
            <b-col lg="4" md="6" sm="12" class="mb-4">
              <b-card class="h-100 shadow-sm">
                <h5 class="font-weight-bold mb-3 text-primary">
                  <lucide-icon class="mr-2" name="home" />{{$t('FromWarehouse')}}
                </h5>
                <div class="transfer-info">
                  <p class="mb-2"><strong>{{transfer.from_warehouse}}</strong></p>
                </div>
              </b-card>
            </b-col>
            <b-col lg="4" md="6" sm="12" class="mb-4">
              <b-card class="h-100 shadow-sm">
                <h5 class="font-weight-bold mb-3 text-success">
                  <lucide-icon class="mr-2" name="home" />{{$t('ToWarehouse')}}
                </h5>
                <div class="transfer-info">
                  <p class="mb-2"><strong>{{transfer.to_warehouse}}</strong></p>
                </div>
              </b-card>
            </b-col>
            <b-col lg="4" md="6" sm="12" class="mb-4">
              <b-card class="h-100 shadow-sm">
                <h5 class="font-weight-bold mb-3 text-info">
                  <lucide-icon class="mr-2" name="file-text" />{{$t('Transfer_Info')}}
                </h5>
                <div class="transfer-info">
                  <p class="mb-2">
                    <strong>{{$t('Reference')}}:</strong> {{transfer.Ref}}
                  </p>
                  <p class="mb-2">
                    <strong>{{$t('date')}}:</strong> {{formatDisplayDate(transfer.date)}}
                  </p>
                  <p class="mb-2">
                    <strong>{{$t('Status')}}:</strong>
                    <span
                      v-if="transfer.statut == 'completed'"
                      class="badge badge-outline-success ml-2"
                    >{{$t('complete')}}</span>
                    <span
                      v-else-if="transfer.statut == 'sent'"
                      class="badge badge-outline-warning ml-2"
                    >{{$t('Sent')}}</span>
                    <span v-else class="badge badge-outline-danger ml-2">{{$t('Pending')}}</span>
                  </p>
                  <p class="mb-2">
                    <strong>{{$t('Approval')}}:</strong>
                    <span
                      v-if="!transfer.approval_status || transfer.approval_status === 'approved'"
                      class="badge badge-outline-success ml-2"
                    >{{ $t('Approved') }}</span>
                    <span
                      v-else-if="transfer.approval_status === 'pending'"
                      class="badge badge-outline-warning ml-2"
                    >{{ $t('Pending_Approval') }}</span>
                    <span
                      v-else-if="transfer.approval_status === 'rejected'"
                      class="badge badge-outline-danger ml-2"
                    >{{ $t('Rejected') }}</span>
                  </p>
                </div>
              </b-card>
            </b-col>
          </b-row>

          <b-row class="mt-4">
            <b-col md="12">
              <h5 class="font-weight-bold mb-3">
                <lucide-icon class="mr-2" name="package" />{{$t('Order_Summary')}}
              </h5>
              <div class="table-responsive">
                <table class="table table-hover table-bordered">
                  <thead class="bg-light">
                    <tr>
                      <th scope="col" class="text-left">{{$t('ProductName')}}</th>
                      <th scope="col" class="text-center">{{$t('CodeProduct')}}</th>
                      <th scope="col" class="text-center">{{$t('Quantity')}}</th>
                      <th scope="col" class="text-right">{{$t('SubTotal')}}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="(detail, index) in details">
                      <tr :key="'r-' + index">
                        <td class="text-left">
                          <span class="font-weight-bold">{{detail.name}}</span>
                          <span v-if="detail.is_batch_tracked" class="badge ml-1" style="background:#eef2ff; color:#4f46e5; font-weight:600; letter-spacing:0.3px;">
                            <lucide-icon name="package" style="margin-right:3px;" />{{ $t('Batches') || 'Batches' }}
                          </span>
                        </td>
                        <td class="text-center">{{detail.code}}</td>
                        <td class="text-center">
                          <span class="badge badge-primary">{{formatNumber(detail.quantity, 2)}} {{detail.unit}}</span>
                        </td>
                        <td class="text-right font-weight-bold">
                          {{currentUser.currency}} {{formatNumber(detail.total, 2)}}
                        </td>
                      </tr>
                      <tr v-if="detail.is_batch_tracked && (detail.batches || []).length" :key="'b-' + index" style="background:#ffffff;">
                        <td colspan="4" style="padding:0; border-top:0;">
                          <div style="margin:6px 4px 12px 4px; border:1px solid #e0e7ff; border-radius:8px; overflow:hidden; background:#f8faff;">
                            <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 12px; background:#4f46e5; color:#fff;">
                              <div style="display:flex; align-items:center; gap:8px;">
                                <lucide-icon name="package" />
                                <span style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.3px;">{{ $t('Batches') || 'Batches' }}</span>
                              </div>
                              <span style="font-size:11px; font-weight:600; background:rgba(255,255,255,0.22); padding:1px 8px; border-radius:10px;">
                                {{ detail.batches.length }} {{ $t('items') || 'items' }}
                              </span>
                            </div>
                            <table style="width:100%; border-collapse:collapse; font-size:12px;">
                              <thead>
                                <tr style="background:#eef2ff;">
                                  <th style="padding:6px 10px; text-align:left; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Batch_No') || 'Batch No' }}</th>
                                  <th style="padding:6px 10px; text-align:left; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Mfg_Date') || 'Mfg Date' }}</th>
                                  <th style="padding:6px 10px; text-align:left; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Expiry_Date') || 'Expiry Date' }}</th>
                                  <th style="padding:6px 10px; text-align:right; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Quantity') || 'Quantity' }}</th>
                                  <th style="padding:6px 10px; text-align:right; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Cost') || 'Cost' }}</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr v-for="(b, bIdx) in detail.batches" :key="'tb-' + index + '-' + bIdx" :style="{ background: bIdx % 2 === 1 ? '#f8faff' : '#ffffff', borderTop: '1px solid #e0e7ff' }">
                                  <td style="padding:6px 10px; font-weight:600; color:#1f2937;">
                                    <span v-if="b.batch_no">{{ b.batch_no }}</span>
                                    <span v-else style="color:#9ca3af; font-style:italic;">—</span>
                                  </td>
                                  <td style="padding:6px 10px; color:#374151;">
                                    <span v-if="b.mfg_date">{{ b.mfg_date }}</span>
                                    <span v-else style="color:#9ca3af;">—</span>
                                  </td>
                                  <td style="padding:6px 10px;">
                                    <span v-if="b.expiry_date" :style="expiry_pill_style(b.expiry_date)">{{ b.expiry_date }}</span>
                                    <span v-else style="color:#9ca3af;">—</span>
                                  </td>
                                  <td style="padding:6px 10px; text-align:right; color:#1f2937; font-weight:600;">
                                    {{ formatNumber(b.qty, 2) }} {{ detail.unit }}
                                  </td>
                                  <td style="padding:6px 10px; text-align:right; color:#4f46e5; font-weight:600;">
                                    <span v-if="b.unit_cost != null">{{ currentUser.currency }} {{ formatNumber(b.unit_cost, 2) }}</span>
                                    <span v-else style="color:#9ca3af;">—</span>
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </b-col>
          </b-row>

          <b-row class="mt-4">
            <b-col md="12" class="text-right">
              <div class="offset-md-8 col-md-4">
                <table class="table table-striped table-sm">
                  <tbody>
                    <tr>
                      <td class="font-weight-bold">{{$t('Items')}}:</td>
                      <td class="text-right">{{transfer.items}}</td>
                    </tr>
                    <tr>
                      <td class="font-weight-bold">{{$t('Total')}}:</td>
                      <td class="text-right">
                        <span class="font-weight-bold text-primary" style="font-size: 1.2em">
                          {{currentUser.currency}} {{formatNumber(transfer.GrandTotal, 2)}}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </b-col>
          </b-row>

          <hr v-if="transfer.note" class="mt-4">
          <b-row v-if="transfer.note" class="mt-4">
            <b-col md="12">
              <h5 class="font-weight-bold mb-2">
                <lucide-icon class="mr-2" name="sticky-note" />{{$t('Note')}}
              </h5>
              <div class="p-3 bg-light rounded">
                <p class="mb-0">{{transfer.note}}</p>
              </div>
            </b-col>
          </b-row>
        </div>
      </div>
    </b-card>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import Util from '../../../../utils';

export default {
  computed: mapGetters(["currentUserPermissions", "currentUser"]),
  metaInfo: {
    title: "Transfer Detail"
  },

  data() {
    return {
      isLoading: true,
      transfer: {},
      details: []
    };
  },

  methods: {
    //----------------------------------- Print Transfer PDF -------------------------\\
    Print_Transfer_PDF() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .get(`transfer_pdf/${id}`, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute(
            "download",
            "Transfer_" + this.transfer.Ref + ".pdf"
          );
          document.body.appendChild(link);
          link.click();
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => {
          // Complete the animation of theprogress bar.
          setTimeout(() => NProgress.done(), 500);
        });
    },

    //------------------------------ Print -------------------------\\
    print() {
      this.$htmlToPaper('print_Invoice');
    },

    //------------------------------Formetted Numbers -------------------------\\
    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    expiry_pill_style(dateStr) {
      const base = {
        display: "inline-block",
        padding: "2px 8px",
        borderRadius: "10px",
        fontSize: "11px",
        fontWeight: "600",
      };
      if (!dateStr) return Object.assign({}, base, { background: "#f3f4f6", color: "#6b7280" });
      const today = new Date(); today.setHours(0, 0, 0, 0);
      const exp = new Date(dateStr);
      if (isNaN(exp.getTime())) return Object.assign({}, base, { background: "#f3f4f6", color: "#6b7280" });
      exp.setHours(0, 0, 0, 0);
      const diffDays = Math.round((exp - today) / (1000 * 60 * 60 * 24));
      if (diffDays < 0) return Object.assign({}, base, { background: "#fee2e2", color: "#991b1b" });
      if (diffDays <= 30) return Object.assign({}, base, { background: "#fef3c7", color: "#92400e" });
      return Object.assign({}, base, { background: "#dcfce7", color: "#166534" });
    },

    //----------------------------------------- Format Display Date -------------------------------\\
    formatDisplayDate(value) {
      if (!value) return '';
      // Get date format from Vuex store (loaded from database) or fallback
      const dateFormat = this.$store.getters.getDateFormat || Util.getDateFormat(this.$store);
      return Util.formatDisplayDate(value, dateFormat);
    },

    //----------------------------------- Get Details Transfer ------------------------------\\
    Get_Transfer_Details() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .get("transfers/" + id)
        .then(response => {
          this.transfer = response.data.transfer;
          this.details = response.data.details;
          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
        })
        .catch(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
          this.$swal(
            this.$t("Failed"),
            this.$t("Failed_to_load_transfer_details"),
            "warning"
          );
        });
    },

    //---------------------------------- Approve Transfer ----------------------\\
    Approve_Transfer() {
      this.$swal({
        title: this.$t("Approve_Transfer"),
        text: this.$t("Are_you_sure_you_want_to_approve_this_transfer"),
        type: "question",
        showCancelButton: true,
        confirmButtonColor: "#28a745",
        cancelButtonColor: "#6c757d",
        cancelButtonText: this.$t("Cancel"),
        confirmButtonText: this.$t("Approve")
      }).then(result => {
        if (result.value) {
          // Start the progress bar.
          NProgress.start();
          NProgress.set(0.1);
          let id = this.$route.params.id;
          axios
            .post("transfers/" + id + "/approve")
            .then(() => {
              this.$swal(
                this.$t("Success"),
                this.$t("Transfer_approved_successfully"),
                "success"
              );
              // Reload transfer details
              this.Get_Transfer_Details();
            })
            .catch(() => {
              // Complete the animation of theprogress bar.
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Failed"),
                this.$t("Failed_to_approve_transfer"),
                "warning"
              );
            });
        }
      });
    },

    //---------------------------------- Delete Transfer ----------------------\\
    Delete_Transfer() {
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
          // Start the progress bar.
          NProgress.start();
          NProgress.set(0.1);
          let id = this.$route.params.id;
          axios
            .delete("transfers/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              // Redirect to transfers list
              this.$router.push({ name: "index_transfer" });
            })
            .catch(() => {
              // Complete the animation of theprogress bar.
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    }
  },

  //-----------------------------Autoload function-------------------
  created: function() {
    this.Get_Transfer_Details();
  }
};
</script>

<style scoped>
.transfer-info p {
  margin-bottom: 0.5rem;
}

.invoice {
  background: #fff;
  padding: 20px;
}

.invoice-print {
  background: #fff;
}

.table th {
  border-top: 1px solid #dee2e6;
  font-weight: 600;
}

.bg-light {
  background-color: #f8f9fa !important;
}

.shadow-sm {
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
}
</style>

