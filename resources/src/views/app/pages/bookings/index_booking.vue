<template>
  <div class="main-content bookings-page-modern">
    <breadcumb :page="$t('Booking_List')" :folder="$t('Bookings')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <!-- Full Page Loading Overlay for PDF Download -->
    <div v-if="isPdfLoading" class="pdf-loading-overlay">
      <div class="pdf-loading-content">
        <div class="pdf-spinner-wrapper">
          <div class="pdf-spinner"></div>
        </div>
        <h3 class="pdf-loading-title">{{ $t('Generating_PDF') || 'Generating PDF' }}</h3>
        <p class="pdf-loading-message">{{ $t('Please_wait') || 'Please wait while we prepare your document...' }}</p>
      </div>
    </div>

    <div v-else class="page-wrapper">
      <!-- Control Bar -->
      <div class="control-bar">
        <div class="control-left">
          <h5 class="mb-0 page-title">
            {{ $t('Bookings') || 'Bookings' }}
          </h5>
        </div>
        <div class="control-right">
          <button class="action-btn filter-btn" v-b-toggle.booking-filter-sidebar>
            <lucide-icon name="filter" />
            <span>{{ $t('Filter') }}</span>
          </button>

          <router-link
            :to="{ name: 'calendar_booking' }"
            class="action-btn"
          >
            <lucide-icon name="calendar-days" />
            <span>{{ $t('Calendar_View') || 'Calendar' }}</span>
          </router-link>

          <router-link to="/app/bookings/store" class="action-btn add-btn">
            <lucide-icon name="plus" />
            <span>{{ $t('Add') }}</span>
          </router-link>
        </div>
      </div>

      <!-- Table -->
      <div class="table-card">
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="bookings"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
          :search-options="{
            enabled: true,
            placeholder: $t('Search_this_table'),
          }"
          :pagination-options="{
            enabled: true,
            mode: 'records',
            nextLabel: 'next',
            prevLabel: 'prev',
          }"
          styleClass="modern-table table-hover vgt-table"
        >
          <template slot="table-row" slot-scope="props">
            <!-- Actions -->
            <span v-if="props.column.field === 'actions'">
              <div class="action-buttons-cell">
                <a
                  class="action-icon view"
                  v-b-tooltip.hover
                  :title="$t('Details')"
                  @click="showBookingDetails(props.row)"
                >
                  <lucide-icon name="eye" />
                </a>

                <router-link
                  v-b-tooltip.hover
                  :title="$t('Edit')"
                  :to="`/app/bookings/edit/${props.row.id}`"
                  class="action-icon edit"
                >
                  <lucide-icon name="pencil" />
                </router-link>

                <a
                  class="action-icon email"
                  v-b-tooltip.hover
                  :title="$t('Send_Email') || 'Send Email'"
                  @click="sendBookingEmail(props.row)"
                >
                  <lucide-icon name="mail" />
                </a>

                <a
                  class="action-icon delete"
                  v-b-tooltip.hover
                  :title="$t('Delete')"
                  @click="removeBooking(props.row.id)"
                >
                  <lucide-icon name="x" />
                </a>
              </div>
            </span>

            <!-- Status with colors -->
            <span v-else-if="props.column.field === 'status'">
              <span
                class="status-badge"
                :class="statusClass(props.row.status)"
              >
                {{ statusLabel(props.row.status) }}
              </span>
            </span>

            <!-- Price formatting -->
            <span v-else-if="props.column.field === 'price'">
              {{ formatPrice(props.row.price) }}
            </span>

            <!-- Default cell rendering -->
            <span v-else>
              {{ props.formattedRow[props.column.field] }}
            </span>
          </template>
        </vue-good-table>
      </div>
    </div>

    <!-- Filter Sidebar -->
    <b-sidebar
      id="booking-filter-sidebar"
      :title="$t('Filter')"
      bg-variant="white"
      right
      shadow
      sidebar-class="modern-sidebar"
    >
      <div class="sidebar-content">
        <b-row>
          <!-- Booking Date -->
          <b-col md="12">
            <b-form-group :label="$t('Date') || 'Date'" class="modern-form-group">
              <b-form-input
                type="date"
                v-model="filterDate"
                class="modern-input"
              />
            </b-form-group>
          </b-col>

          <!-- Status -->
          <b-col md="12">
            <b-form-group :label="$t('Status')" class="modern-form-group">
              <v-select
                v-model="filterStatus"
                :reduce="option => option.value"
                :placeholder="$t('Choose_Status')"
                :options="statusOptions"
                class="modern-select"
              />
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="getBookings(serverParams.page)"
              variant="primary"
              size="md"
              block
              class="modern-btn"
            >
              <lucide-icon name="filter" />
              {{ $t('Filter') }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button
              @click="resetFilter"
              variant="danger"
              size="md"
              block
              class="modern-btn"
            >
              <lucide-icon name="power" />
              {{ $t('Reset') }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>

    <!-- Booking Detail Modal -->
    <b-modal
      id="booking-detail-modal"
      hide-header
      hide-footer
      size="xl"
      body-class="p-0"
      modal-class="booking-detail-modal-wrapper"
      centered
    >
      <div v-if="selectedBooking" class="booking-detail-container">
        <!-- Modern Header -->
        <div class="booking-header">
          <div class="header-content">
            <div class="header-icon">
              <lucide-icon name="calendar-days" />
            </div>
            <div class="header-text">
              <h2 class="booking-title">{{ $t('Booking_Details') || 'Booking Details' }}</h2>
              <p class="booking-id">{{ selectedBooking.Ref || '#' + selectedBooking.id }}</p>
            </div>
          </div>
          <div class="header-actions">
            <button @click="Booking_PDF(selectedBooking.id)" class="action-btn pdf-btn" title="Download PDF">
              <lucide-icon name="file-text" />
            </button>
            <button @click="printBooking()" class="action-btn print-btn" title="Print">
              <lucide-icon name="receipt" />
            </button>
            <button @click="$bvModal.hide('booking-detail-modal')" class="action-btn close-btn" title="Close">
              <lucide-icon name="x" />
            </button>
          </div>
        </div>

        <!-- Content -->
        <div class="booking-content" id="booking-display-view">
          <div class="booking-grid">
            <!-- Customer Card -->
            <div class="info-card customer-card">
              <div class="card-header">
                <div class="card-icon customer-icon">
                  <lucide-icon name="shield-check" />
                </div>
                <h3 class="card-title">{{ $t('Customer') || 'Customer' }}</h3>
              </div>
              <div class="card-body">
                <div class="info-value">{{ selectedBooking.customer_name || '-' }}</div>
              </div>
            </div>

            <!-- Service Card -->
            <div class="info-card service-card">
              <div class="card-header">
                <div class="card-icon service-icon">
                  <lucide-icon name="package" />
                </div>
                <h3 class="card-title">{{ $t('Service') || 'Service' }}</h3>
              </div>
              <div class="card-body">
                <div class="info-value">{{ selectedBooking.product_name || $t('Not_Applicable') || '-' }}</div>
              </div>
            </div>

            <!-- Price Card -->
            <div class="info-card price-card">
              <div class="card-header">
                <div class="card-icon price-icon">
                  <lucide-icon name="wallet" />
                </div>
                <h3 class="card-title">{{ $t('Price') || 'Price' }}</h3>
              </div>
              <div class="card-body">
                <div class="price-value">
                  <span class="currency-symbol">$</span>
                  <span class="price-amount">{{ formatPrice(selectedBooking.price) }}</span>
                </div>
              </div>
            </div>

            <!-- Reference Card -->
            <div class="info-card reference-card">
              <div class="card-header">
                <div class="card-icon reference-icon">
                  <lucide-icon name="tag" />
                </div>
                <h3 class="card-title">{{ $t('Reference') || 'Reference' }}</h3>
              </div>
              <div class="card-body">
                <div class="info-value">{{ selectedBooking.Ref || 'N/A' }}</div>
              </div>
            </div>

            <!-- Status Card -->
            <div class="info-card status-card">
              <div class="card-header">
                <div class="card-icon status-icon">
                  <lucide-icon name="flag" />
                </div>
                <h3 class="card-title">{{ $t('Status') || 'Status' }}</h3>
              </div>
              <div class="card-body">
                <span
                  class="status-badge-modern"
                  :class="statusClass(selectedBooking.status)"
                >
                  <span class="status-dot"></span>
                  {{ statusLabel(selectedBooking.status) }}
                </span>
              </div>
            </div>

            <!-- Date & Time Card -->
            <div class="info-card datetime-card">
              <div class="card-header">
                <div class="card-icon datetime-icon">
                  <lucide-icon name="calendar" />
            </div>
                <h3 class="card-title">{{ $t('Date_Time') || 'Date & Time' }}</h3>
              </div>
              <div class="card-body">
                <div class="datetime-grid">
                  <div class="datetime-item">
                    <div class="datetime-label">{{ $t('Date') || 'Date' }}</div>
                    <div class="datetime-value">{{ selectedBooking.booking_date }}</div>
                  </div>
                  <div class="datetime-item">
                    <div class="datetime-label">{{ $t('Start_Time') || 'Start Time' }}</div>
                    <div class="datetime-value">{{ selectedBooking.booking_time }}</div>
                  </div>
                  <div class="datetime-item" v-if="selectedBooking.booking_end_time">
                    <div class="datetime-label">{{ $t('End_Time') || 'End Time' }}</div>
                    <div class="datetime-value">{{ selectedBooking.booking_end_time }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Notes Card -->
            <div class="info-card notes-card" v-if="selectedBooking.notes">
              <div class="card-header">
                <div class="card-icon notes-icon">
                  <lucide-icon name="file-text" />
                </div>
                <h3 class="card-title">{{ $t('Details') || 'Notes' }}</h3>
              </div>
              <div class="card-body">
                <div class="notes-content white-space-preline">{{ selectedBooking.notes }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Print View (Hidden) -->
        <div id="print_Booking" style="display: none;">
          <div class="invoice-print">
            <div style="text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px solid #667eea;">
              <h2 style="color: #667eea; margin: 0 0 10px 0; font-weight: 700;">{{ $t('Booking_Details') || 'Booking Details' }}</h2>
              <p style="color: #64748b; margin: 0; font-size: 16px; font-weight: 600;">{{ selectedBooking.Ref || '#' + selectedBooking.id }}</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
              <div style="background: #f8f9fc; padding: 20px; border-radius: 12px; border-left: 4px solid #667eea;">
                <h3 style="color: #667eea; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">{{ $t('Customer_Info') || 'Customer Information' }}</h3>
                <p style="margin: 0; font-size: 16px; font-weight: 600; color: #1e293b;">{{ selectedBooking.customer_name || '-' }}</p>
              </div>
              
              <div style="background: #f8f9fc; padding: 20px; border-radius: 12px; border-left: 4px solid #764ba2;">
                <h3 style="color: #764ba2; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">{{ $t('Booking_Info') || 'Booking Information' }}</h3>
                <div style="font-size: 14px; color: #475569; line-height: 1.8;">
                  <div><strong>Reference:</strong> #{{ selectedBooking.id }}</div>
                  <div><strong>Date:</strong> {{ selectedBooking.booking_date }}</div>
                  <div><strong>Status:</strong> {{ statusLabel(selectedBooking.status) }}</div>
                </div>
              </div>
            </div>
            
            <div style="background: white; border: 2px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-bottom: 30px;">
              <h3 style="color: #1e293b; margin: 0 0 20px 0; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">{{ $t('Service_Details') || 'Service Details' }}</h3>
              <table style="width: 100%; border-collapse: collapse;">
                <tbody>
                  <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 0; font-weight: 600; color: #64748b; width: 40%;">{{ $t('Service') || 'Service' }}</td>
                    <td style="padding: 12px 0; color: #1e293b; font-weight: 600;">{{ selectedBooking.product_name || $t('Not_Applicable') || '-' }}</td>
                  </tr>
                  <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 0; font-weight: 600; color: #64748b;">{{ $t('Price') }}</td>
                    <td style="padding: 12px 0; color: #667eea; font-size: 18px; font-weight: 700;">${{ formatPrice(selectedBooking.price) }}</td>
                  </tr>
                  <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 0; font-weight: 600; color: #64748b;">{{ $t('Date') }}</td>
                    <td style="padding: 12px 0; color: #1e293b; font-weight: 600;">{{ selectedBooking.booking_date }}</td>
                  </tr>
                  <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 0; font-weight: 600; color: #64748b;">{{ $t('Start_Time') || 'Start Time' }}</td>
                    <td style="padding: 12px 0; color: #1e293b; font-weight: 600;">{{ selectedBooking.booking_time }}</td>
                  </tr>
                  <tr v-if="selectedBooking.booking_end_time" style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 0; font-weight: 600; color: #64748b;">{{ $t('End_Time') || 'End Time' }}</td>
                    <td style="padding: 12px 0; color: #1e293b; font-weight: 600;">{{ selectedBooking.booking_end_time }}</td>
                  </tr>
                  <tr>
                    <td style="padding: 12px 0; font-weight: 600; color: #64748b;">{{ $t('Status') }}</td>
                    <td style="padding: 12px 0;">
                      <span style="display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;" :class="'status-' + selectedBooking.status">
                        {{ statusLabel(selectedBooking.status) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            
            <div v-if="selectedBooking.notes" style="background: #f8f9fc; border-left: 4px solid #667eea; padding: 20px; border-radius: 12px;">
              <h3 style="color: #667eea; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">{{ $t('Details') || 'Notes' }}</h3>
              <p style="margin: 0; color: #475569; line-height: 1.8; white-space: pre-line;">{{ selectedBooking.notes }}</p>
            </div>
          </div>
        </div>
      </div>
    </b-modal>
  </div>
</template>

<script>
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Bookings"
  },
  data() {
    return {
      isLoading: true,
      isPdfLoading: false,
      bookings: [],
      totalRows: 0,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      search: "",
      limit: "10",
      filterDate: "",
      filterStatus: "",
      selectedBooking: null,
      statusOptions: [
        { label: this.$t("Pending") || "Pending", value: "pending" },
        { label: this.$t("Confirmed") || "Confirmed", value: "confirmed" },
        { label: this.$t("Cancelled") || "Cancelled", value: "cancelled" },
        { label: this.$t("complete") || "Completed", value: "completed" }
      ]
    };
  },
  computed: {
    columns() {
      return [
        {
          label: this.$t("Reference") || "Reference",
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Customer"),
          field: "customer_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Product"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Price"),
          field: "price",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Date"),
          field: "booking_date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Start_Time") || "Start Time",
          field: "booking_time",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("End_Time") || "End Time",
          field: "booking_end_time",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Status"),
          field: "status",
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
    }
  },
  methods: {
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.getBookings(currentPage);
      }
    },
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.getBookings(1);
      }
    },
    onSortChange(params) {
      const field = params[0].field || "id";
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.getBookings(this.serverParams.page);
    },
    onSearch(value) {
      this.search = value.searchTerm;
      this.getBookings(this.serverParams.page);
    },
    resetFilter() {
      this.search = "";
      this.filterDate = "";
      this.filterStatus = "";
      this.getBookings(this.serverParams.page);
    },
    statusLabel(status) {
      if (status === "pending") {
        return this.$t("Pending") || "Pending";
      }
      if (status === "confirmed") {
        return this.$t("Confirmed") || "Confirmed";
      }
      if (status === "cancelled") {
        return this.$t("Cancelled") || "Cancelled";
      }
      if (status === "completed") {
        return this.$t("complete") || "Completed";
      }
      return status;
    },
    statusClass(status) {
      if (status === "pending") return "pending";
      if (status === "confirmed") return "confirmed";
      if (status === "cancelled") return "cancelled";
      if (status === "completed") return "completed";
      return "";
    },
    getBookings(page) {
      NProgress.start();
      NProgress.set(0.1);

      const params =
        "page=" +
        page +
        "&status=" +
        (this.filterStatus || "") +
        "&date=" +
        (this.filterDate || "") +
        "&SortField=" +
        this.serverParams.sort.field +
        "&SortType=" +
        this.serverParams.sort.type +
        "&search=" +
        this.search +
        "&limit=" +
        this.limit;

      axios
        .get("bookings?" + params)
        .then(response => {
          this.bookings = response.data.bookings || [];
          this.totalRows = response.data.totalRows || 0;

          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },
    removeBooking(id) {
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
          NProgress.start();
          NProgress.set(0.1);
          axios
            .delete("bookings/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              this.getBookings(this.serverParams.page);
            })
            .catch(() => {
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },
    showBookingDetails(row) {
      this.selectedBooking = Object.assign({}, row);
      this.$bvModal.show("booking-detail-modal");
    },
    formatPrice(price) {
      if (price === null || price === undefined || price === '') {
        return '-';
      }
      // Format as currency with 2 decimal places
      return parseFloat(price).toFixed(2);
    },
    //----------------------------------- Booking PDF  -------------------------\\
    Booking_PDF(id) {
      // Show full page loading overlay
      this.isPdfLoading = true;
      NProgress.start();
      NProgress.set(0.1);
     
      axios
        .get(`booking_pdf/${id}`, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "Booking_" + id + ".pdf");
          document.body.appendChild(link);
          link.click();
          
          // Clean up
          document.body.removeChild(link);
          window.URL.revokeObjectURL(url);
          
          // Hide loading overlay after a short delay
          setTimeout(() => {
            this.isPdfLoading = false;
            NProgress.done();
            this.makeToast("success", this.$t("PDF_downloaded_successfully") || "PDF downloaded successfully", this.$t("Success") || "Success");
          }, 500);
        })
        .catch(() => {
          // Hide loading overlay on error
          this.isPdfLoading = false;
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },
    //------------------------------ Print -------------------------\\
    printBooking() {
      this.$htmlToPaper('print_Booking');
    },
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },
    sendBookingEmail(row) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("bookings_send_email", { id: row.id })
        .then((response) => {
          NProgress.done();
          const msg = (response.data && response.data.message) || this.$t("SendEmail") || this.$t("Send_Email") || "Email sent successfully";
          this.makeToast("success", msg, this.$t("Success") || "Success");
        })
        .catch((err) => {
          NProgress.done();
          const msg = (err.response && err.response.data && err.response.data.message) || this.$t("InvalidData");
          this.makeToast("danger", msg, this.$t("Failed"));
        });
    }
  },
  created() {
    this.getBookings(1);
  }
};
</script>

<style scoped lang="scss">
/* ========================================
   BOOKING DETAIL MODAL
   ======================================== */

::v-deep .booking-detail-modal-wrapper {
  .modal-dialog {
    max-width: 900px !important;
    margin: 0.5rem !important;
    
    @media (max-width: 768px) {
      max-width: 100% !important;
      margin: 0 !important;
      width: 100% !important;
    }
  }

  .modal-content {
    border: none !important;
    border-radius: 20px !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15) !important;
    overflow: hidden !important;
    
    @media (max-width: 768px) {
      border-radius: 0 !important;
      min-height: 100vh !important;
      height: 100vh !important;
      display: flex !important;
      flex-direction: column !important;
    }
  }
  
  .modal-body {
    @media (max-width: 768px) {
      padding: 0 !important;
      overflow-y: auto !important;
      flex: 1 !important;
    }
  }
}

.booking-detail-container {
  background: #ffffff;
  min-height: 500px;
  
  @media (max-width: 768px) {
    min-height: auto;
    display: flex;
    flex-direction: column;
    height: 100%;
  }
}

/* Header */
.booking-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 20px 28px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    border-radius: 50%;
  }

  .header-content {
    display: flex;
    align-items: center;
    gap: 16px;
    z-index: 1;
  }

  .header-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
  }

  .header-text {
    .booking-title {
      margin: 0;
      font-size: 20px;
      font-weight: 700;
      color: white;
      letter-spacing: -0.5px;
    }

    .booking-id {
      margin: 4px 0 0 0;
      font-size: 13px;
      color: rgba(255, 255, 255, 0.9);
      font-weight: 500;
    }
  }

  .header-actions {
    display: flex;
    gap: 8px;
    z-index: 1;
  }

  .action-btn {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    color: white;
    font-size: 18px;

    &:hover {
      background: rgba(255, 255, 255, 0.25);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    &.pdf-btn:hover {
      background: rgba(239, 68, 68, 0.9);
      border-color: rgba(239, 68, 68, 0.9);
    }

    &.print-btn:hover {
      background: rgba(59, 130, 246, 0.9);
      border-color: rgba(59, 130, 246, 0.9);
    }

    &.close-btn:hover {
      background: rgba(239, 68, 68, 0.9);
      border-color: rgba(239, 68, 68, 0.9);
      transform: rotate(90deg);
    }
  }
}

/* Content */
.booking-content {
  padding: 28px;
  background: #f8f9fc;
}

.booking-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
}

/* Info Cards */
.info-card {
  background: white;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    transform: scaleX(0);
    transition: transform 0.3s ease;
  }

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);

    &::before {
      transform: scaleX(1);
    }
  }

  .card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f1f5f9;
  }

  .card-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #667eea;
    flex-shrink: 0;
  }

  .customer-icon {
    color: #667eea;
  }

  .service-icon {
    color: #f5576c;
  }

  .price-icon {
    color: #4facfe;
  }

  .status-icon {
    color: #43e97b;
  }

  .datetime-icon {
    color: #fa709a;
  }

  .notes-icon {
    color: #30cfd0;
  }

  .reference-icon {
    color: #8b5cf6;
  }

  .card-title {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .card-body {
    .info-value {
      font-size: 16px;
      font-weight: 600;
      color: #1e293b;
      line-height: 1.5;
    }

    .price-value {
      display: flex;
      align-items: baseline;
      gap: 4px;

      .currency-symbol {
        font-size: 18px;
        font-weight: 600;
        color: #64748b;
      }

      .price-amount {
        font-size: 28px;
        font-weight: 700;
        color: #667eea;
        line-height: 1;
      }
    }

    .status-badge-modern {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;

      .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        animation: pulse 2s ease infinite;
      }

      &.pending {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #92400e;
        
        .status-dot {
          background: #f59e0b;
        }
      }

      &.confirmed {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        
        .status-dot {
          background: #10b981;
        }
      }

      &.cancelled {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        
        .status-dot {
          background: #ef4444;
        }
      }

      &.completed {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e40af;
        
        .status-dot {
          background: #3b82f6;
        }
      }
    }

    .datetime-grid {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .datetime-item {
      .datetime-label {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
      }

      .datetime-value {
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
      }
    }

    .notes-content {
      font-size: 14px;
      color: #475569;
      line-height: 1.6;
      padding: 12px;
      background: #f8fafc;
      border-radius: 8px;
      border-left: 3px solid #667eea;
    }
  }
}

/* Full width cards */
.datetime-card,
.notes-card {
  grid-column: 1 / -1;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

/* Responsive */
@media (max-width: 768px) {
  .booking-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .booking-header {
    padding: 16px 20px;
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    position: sticky;
    top: 0;
    z-index: 10;

    .header-content {
      width: 100%;
      gap: 12px;
    }

    .header-icon {
      width: 40px;
      height: 40px;
      font-size: 20px;
      border-radius: 10px;
    }

    .header-text {
      flex: 1;
      min-width: 0;

      .booking-title {
        font-size: 18px;
        line-height: 1.3;
      }

      .booking-id {
        font-size: 12px;
        margin-top: 2px;
      }
    }

    .header-actions {
      width: 100%;
      justify-content: flex-end;
      gap: 6px;
    }

    .action-btn {
      width: 36px;
      height: 36px;
      font-size: 16px;
      border-radius: 8px;
    }
  }

  .booking-content {
    padding: 16px;
    flex: 1;
    overflow-y: auto;
  }

  .info-card {
    padding: 16px;
    border-radius: 12px;

    .card-header {
      gap: 10px;
      margin-bottom: 12px;
      padding-bottom: 10px;
    }

    .card-icon {
      width: 36px;
      height: 36px;
      font-size: 18px;
    }

    .card-title {
      font-size: 12px;
    }

    .card-body {
      .info-value {
        font-size: 14px;
      }

      .price-value {
        .currency-symbol {
          font-size: 16px;
        }

        .price-amount {
          font-size: 24px;
        }
      }

      .status-badge-modern {
        padding: 6px 12px;
        font-size: 11px;
        gap: 6px;

        .status-dot {
          width: 6px;
          height: 6px;
        }
      }

      .datetime-item {
        .datetime-label {
          font-size: 10px;
        }

        .datetime-value {
          font-size: 14px;
        }
      }

      .notes-content {
        font-size: 13px;
        padding: 10px;
      }
    }
  }
}

@media (max-width: 480px) {
  .booking-header {
    padding: 12px 16px;

    .header-icon {
      width: 36px;
      height: 36px;
      font-size: 18px;
    }

    .header-text {
      .booking-title {
        font-size: 16px;
      }

      .booking-id {
        font-size: 11px;
      }
    }

    .action-btn {
      width: 32px;
      height: 32px;
      font-size: 14px;
    }
  }

  .booking-content {
    padding: 12px;
  }

  .booking-grid {
    gap: 12px;
  }

  .info-card {
    padding: 12px;

    .card-header {
      margin-bottom: 10px;
      padding-bottom: 8px;
    }

    .card-icon {
      width: 32px;
      height: 32px;
      font-size: 16px;
    }

    .card-title {
      font-size: 11px;
    }

    .card-body {
      .info-value {
        font-size: 13px;
      }

      .price-value {
        .currency-symbol {
          font-size: 14px;
        }

        .price-amount {
          font-size: 20px;
        }
      }
    }
  }
}

/* ========================================
   PDF LOADING OVERLAY
   ======================================== */

.pdf-loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(8px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.3s ease;
}

.pdf-loading-content {
  text-align: center;
  background: white;
  padding: 40px 50px;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 400px;
  width: 90%;
  animation: slideUp 0.4s ease;
}

.pdf-spinner-wrapper {
  margin-bottom: 24px;
  display: flex;
  justify-content: center;
}

.pdf-spinner {
  width: 60px;
  height: 60px;
  border: 5px solid #f1f5f9;
  border-top: 5px solid #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto;
}

.pdf-loading-title {
  margin: 0 0 12px 0;
  font-size: 22px;
  font-weight: 700;
  color: #1e293b;
  letter-spacing: -0.5px;
}

.pdf-loading-message {
  margin: 0;
  font-size: 14px;
  color: #64748b;
  line-height: 1.6;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

.bookings-page-modern {
  padding: 1.5rem;
  background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
  min-height: 100vh;
}

.page-wrapper {
  max-width: 1400px;
  margin: 0 auto;
}

.control-bar {
  background: white;
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  flex-wrap: wrap;
  gap: 1rem;
}

.page-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #1e293b;
}

.control-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.control-right {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.action-btn {
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.4rem 0.875rem;
  font-weight: 600;
  font-size: 0.75rem;
  color: #475569;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  text-decoration: none;

  i {
    font-size: 0.875rem;
  }

  span {
    font-size: 0.75rem;
  }

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }

  &.filter-btn:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #eff6ff;
  }

  &.add-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;

    &:hover {
      box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
    }
  }
}

.table-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  overflow: hidden;
}

.modern-table {
  ::v-deep {
    .vgt-table {
      border: none;

      thead {
        background: #f8fafc;

        th {
          border: none;
          padding: 1rem;
          font-weight: 700;
          color: #334155;
          text-transform: uppercase;
          font-size: 0.75rem;
          letter-spacing: 0.05em;
        }
      }

      tbody {
        tr {
          border-bottom: 1px solid #f1f5f9;
          transition: all 0.2s ease;

          &:hover {
            background: #f8fafc;
          }

          td {
            padding: 1rem;
            color: #475569;
            font-size: 0.9rem;
          }
        }
      }
    }
  }
}

.action-buttons-cell {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
}

.action-icon {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;

  &.view {
    color: #0ea5e9;
    background: #e0f2fe;

    &:hover {
      background: #0ea5e9;
      color: white;
      transform: scale(1.05);
    }
  }

  &.edit {
    color: #10b981;
    background: #d1fae5;

    &:hover {
      background: #10b981;
      color: white;
      transform: scale(1.05);
    }
  }

  &.email {
    color: #6366f1;
    background: #e0e7ff;

    &:hover {
      background: #6366f1;
      color: white;
      transform: scale(1.05);
    }
  }

  &.delete {
    color: #ef4444;
    background: #fee2e2;

    &:hover {
      background: #ef4444;
      color: white;
      transform: scale(1.05);
    }
  }
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.45rem 0.9rem;
  border-radius: 999px;
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;

  &.pending {
    background: #fed7aa; /* soft orange */
    color: #9a3412;
  }

  &.confirmed {
    background: #dbeafe; /* blue */
    color: #1d4ed8;
  }

  &.cancelled {
    background: #fee2e2; /* red */
    color: #b91c1c;
  }

  &.completed {
    background: #bbf7d0; /* green */
    color: #15803d;
  }
}

.modern-sidebar {
  ::v-deep {
    .b-sidebar-header {
      padding: 1.5rem;
      border-bottom: 2px solid #f1f5f9;
    }
  }
}

.sidebar-content {
  padding: 1.5rem;
}

.modern-form-group {
  margin-bottom: 1.5rem;

  ::v-deep {
    label {
      font-weight: 600;
      color: #334155;
      font-size: 0.875rem;
      margin-bottom: 0.5rem;
    }
  }
}

.modern-input {
  border-radius: 10px;
  border: 2px solid #e2e8f0;
  padding: 0.75rem 1rem;
  transition: all 0.3s ease;

  &:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
}

.modern-select {
  ::v-deep {
    .vs__dropdown-toggle {
      border-radius: 10px;
      border: 2px solid #e2e8f0;
      padding: 0.5rem 1rem;
    }

    &.vs--open .vs__dropdown-toggle {
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
  }
}

.modern-btn {
  border-radius: 10px;
  font-weight: 600;
  padding: 0.75rem 1.5rem;
  transition: all 0.3s ease;
  border: none;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }

  i {
    margin-right: 0.5rem;
  }
}

.white-space-preline {
  white-space: pre-line;
}

@media (max-width: 991px) {
  .bookings-page-modern {
    padding: 1rem;
  }

  .control-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .control-left,
  .control-right {
    width: 100%;
    justify-content: center;
  }

  .control-right {
    flex-direction: column;

    .action-btn {
      width: 100%;
      justify-content: center;
    }
  }
}
</style>


