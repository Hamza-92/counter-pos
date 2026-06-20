<template>
  <div class="main-content bookings-page-modern">
    <breadcumb :page="$t('Calendar_View')" :folder="$t('Bookings')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="page-wrapper">
      <!-- Control Bar -->
      <div class="control-bar">
        <div class="control-left">
          <h5 class="mb-0 page-title">
            {{ $t('Calendar_View') || 'Calendar View' }}
          </h5>
        </div>
        <div class="control-right">
          <router-link
            :to="{ name: 'index_booking' }"
            class="action-btn"
          >
            <lucide-icon name="list" />
            <span>{{ $t('Booking_List') || 'List' }}</span>
          </router-link>

          <router-link
            :to="{ name: 'store_booking' }"
            class="action-btn add-btn"
          >
            <lucide-icon name="plus" />
            <span>{{ $t('Add') }}</span>
          </router-link>
        </div>
      </div>

      <!-- Calendar -->
      <div class="calendar-card">
        <div ref="calendarEl" class="fc-container"></div>
      </div>
    </div>

    <!-- Booking Detail Modal (from calendar) -->
    <b-modal
      id="booking-calendar-detail-modal"
      :title="$t('Booking_Details') || 'Booking Details'"
      hide-footer
      size="lg"
      modal-class="booking-calendar-modal-wrapper"
    >
      <div v-if="selectedBooking">
        <b-row>
          <b-col md="6" class="mb-2">
            <strong>{{ $t('Customer') }}:</strong>
            <div>{{ selectedBooking.customer_name || '-' }}</div>
          </b-col>
          <b-col md="6" class="mb-2">
            <strong>{{ $t('Product') }}:</strong>
            <div>{{ selectedBooking.product_name || $t('Not_Applicable') || '-' }}</div>
          </b-col>

          <b-col md="6" class="mb-2">
            <strong>{{ $t('Price') }}:</strong>
            <div>{{ formatPrice(selectedBooking.price) }}</div>
          </b-col>

          <b-col md="4" class="mb-2">
            <strong>{{ $t('Date') }}:</strong>
            <div>{{ selectedBooking.booking_date }}</div>
          </b-col>
          <b-col md="4" class="mb-2">
            <strong>{{ $t('Start_Time') || 'Start Time' }}:</strong>
            <div>{{ selectedBooking.booking_time }}</div>
          </b-col>
          <b-col md="4" class="mb-2">
            <strong>{{ $t('End_Time') || 'End Time' }}:</strong>
            <div>{{ selectedBooking.booking_end_time || '-' }}</div>
          </b-col>

          <b-col md="4" class="mb-2">
            <strong>{{ $t('Status') }}:</strong>
            <div>
              <span
                class="status-badge"
                :class="statusClass(selectedBooking.status)"
              >
                {{ statusLabel(selectedBooking.status) }}
              </span>
            </div>
          </b-col>

          <b-col md="12" class="mt-3">
            <strong>{{ $t('Details') || 'Notes' }}:</strong>
            <div class="text-muted white-space-preline">
              {{ selectedBooking.notes || '-' }}
            </div>
          </b-col>
        </b-row>
      </div>
    </b-modal>
  </div>
</template>

<script>
import NProgress from "nprogress";

export default {
  name: "BookingCalendar",
  metaInfo: {
    title: "Bookings Calendar"
  },
  data() {
    return {
      isLoading: true,
      calendar: null,
      selectedBooking: null,
      calendarInitialized: false,
      statusColors: {
        pending: { bg: "#fed7aa", border: "#f97316", text: "#9a3412" },
        confirmed: { bg: "#dbeafe", border: "#3b82f6", text: "#1d4ed8" },
        cancelled: { bg: "#fee2e2", border: "#ef4444", text: "#b91c1c" },
        completed: { bg: "#bbf7d0", border: "#22c55e", text: "#15803d" }
      }
    };
  },
  watch: {
    isLoading(newVal) {
      // When isLoading becomes false, the calendar element should be available
      if (!newVal && !this.calendarInitialized) {
        this.$nextTick(() => {
          // Wait a bit more to ensure the element is in the DOM
          setTimeout(() => {
            if (this.$refs.calendarEl && !this.calendarInitialized) {
              this.initCalendar();
            }
          }, 200);
        });
      }
    }
  },
  methods: {
    async ensureFullCalendar() {
      if (window.FullCalendar && window.FullCalendar.Calendar) {
        return;
      }

      if (!this._loadingFullCalendar) {
        this._loadingFullCalendar = new Promise((resolve, reject) => {
          // CSS
          const cssId = "fullcalendar-main-css";
          if (!document.getElementById(cssId)) {
            const link = document.createElement("link");
            link.id = cssId;
            link.rel = "stylesheet";
            link.href =
              "https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css";
            document.head.appendChild(link);
          }

          // JS
          const script = document.createElement("script");
          script.src =
            "https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js";
          script.async = true;
          script.onload = () => resolve();
          script.onerror = (err) => reject(err);
          document.head.appendChild(script);
        });
      }

      return this._loadingFullCalendar;
    },

    async initCalendar() {
      try {
        NProgress.start();
        NProgress.set(0.1);

        // Wait for DOM to be ready
        await this.$nextTick();
        
        // Wait a bit more to ensure the element is rendered
        await new Promise(resolve => setTimeout(resolve, 100));

        await this.ensureFullCalendar();

        // Wait again after FullCalendar loads
        await this.$nextTick();

        const el = this.$refs.calendarEl;
        if (!el) {
          console.error('Calendar element not found. Retrying...');
          // Retry after a delay
          setTimeout(() => {
            this.initCalendar();
          }, 500);
          return;
        }

        if (!window.FullCalendar || !window.FullCalendar.Calendar) {
          console.error('FullCalendar library not loaded');
          this.isLoading = false;
          NProgress.done();
          return;
        }

        const Calendar = window.FullCalendar.Calendar;

        // Destroy existing calendar if it exists
        if (this.calendar) {
          this.calendar.destroy();
        }

        // Detect mobile view
        const isMobile = window.innerWidth < 768;
        
        this.calendar = new Calendar(el, {
          initialView: isMobile ? "dayGridMonth" : "dayGridMonth",
          height: "auto",
          headerToolbar: {
            left: isMobile ? "prev,next" : "prev,next today",
            center: "title",
            right: isMobile ? "dayGridMonth" : "dayGridMonth,timeGridWeek,timeGridDay"
          },
          firstDay: 1,
          selectable: false,
          navLinks: true,
          locale: 'en',
          eventTimeFormat: { 
            hour: "2-digit", 
            minute: "2-digit", 
            hour12: false 
          },
          events: (info, success, failure) => {
            this.fetchEvents(info.startStr, info.endStr, success, failure);
          },
          eventClick: (info) => {
            this.handleEventClick(info);
          },
          eventDisplay: 'block',
          displayEventTime: true,
          displayEventEnd: true,
          // Mobile-specific settings
          ...(isMobile && {
            dayMaxEvents: 2,
            moreLinkClick: 'popover'
          })
        });
        
        // Handle window resize
        const handleResize = () => {
          if (this.calendar) {
            const isMobileNow = window.innerWidth < 768;
            const currentView = this.calendar.view.type;
            
            // Update header toolbar based on screen size
            if (isMobileNow) {
              this.calendar.setOption('headerToolbar', {
                left: "prev,next",
                center: "title",
                right: "dayGridMonth"
              });
            } else {
              this.calendar.setOption('headerToolbar', {
                left: "prev,next today",
                center: "title",
                right: "dayGridMonth,timeGridWeek,timeGridDay"
              });
            }
            
            this.calendar.render();
          }
        };
        
        window.addEventListener('resize', handleResize);
        this._resizeHandler = handleResize;

        this.calendar.render();
        
        // Force a refresh after a short delay to ensure events load
        setTimeout(() => {
          if (this.calendar) {
            this.calendar.refetchEvents();
          }
        }, 500);
        this.calendarInitialized = true;
      } catch (e) {
        console.error('Error initializing calendar:', e);
        this.makeToast("danger", "Failed to load calendar. Please refresh the page.", "Error");
      } finally {
        this.isLoading = false;
        NProgress.done();
      }
    },

    fetchEvents(startStr, endStr, successCallback, failureCallback) {
      axios
        .get("bookings", {
          params: {
            from: startStr,
            to: endStr,
            limit: -1,
            SortField: "booking_date",
            SortType: "asc"
          }
        })
        .then(({ data }) => {
          const bookings = data.bookings || [];
          const events = bookings.map((b) => {
            if (!b.booking_date) {
              return null; // Skip bookings without a date
            }

            const date = b.booking_date;
            // Normalize time format (handle H:i:s to H:i)
            let startTime = (b.booking_time || "00:00").slice(0, 5);
            let endTime = b.booking_end_time ? b.booking_end_time.slice(0, 5) : null;

            // Create ISO datetime strings
            const start = date + "T" + startTime + ":00";
            
            // For end time, if not provided, add 1 hour to start time for all-day events
            let end = null;
            if (endTime && endTime !== "") {
              end = date + "T" + endTime + ":00";
            } else {
              // If no end time, make it a 1-hour event
              const startDate = new Date(start);
              startDate.setHours(startDate.getHours() + 1);
              end = startDate.toISOString().slice(0, 19);
            }

            const colors = this.statusColors[b.status] || this.statusColors.pending;

            return {
              id: String(b.id),
              title: b.customer_name || `Booking #${b.id}`,
              start: start,
              end: end,
              allDay: false,
              backgroundColor: colors.bg,
              borderColor: colors.border,
              textColor: colors.text,
              extendedProps: {
                booking: b
              }
            };
          }).filter(event => event !== null); // Remove any null events

          if (successCallback) {
            successCallback(events);
          }
        })
        .catch((err) => {
          console.error('Error fetching calendar events:', err);
          if (failureCallback) {
            failureCallback(err);
          } else {
            // If no failure callback, still call success with empty array
            if (successCallback) {
              successCallback([]);
            }
          }
        });
    },

    handleEventClick(info) {
      const data = (info && info.event && info.event.extendedProps && info.event.extendedProps.booking) || null;
      if (!data) return;

      this.selectedBooking = {
        id: data.id,
        customer_name: data.customer_name,
        product_name: data.product_name,
        booking_date: data.booking_date,
        booking_time: data.booking_time,
        booking_end_time: data.booking_end_time,
        status: data.status,
        notes: data.notes
      };

      this.$bvModal.show("booking-calendar-detail-modal");
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
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },
    formatPrice(price) {
      if (price === null || price === undefined || price === '') {
        return '-';
      }
      // Format as currency with 2 decimal places
      return parseFloat(price).toFixed(2);
    }
  },
  mounted() {
    // Set isLoading to false so the calendar element is rendered
    // The watcher will then initialize the calendar once the element is available
    this.$nextTick(() => {
      this.isLoading = false;
    });
  },
  beforeDestroy() {
    // Remove resize listener
    if (this._resizeHandler) {
      window.removeEventListener('resize', this._resizeHandler);
    }
    
    // Clean up calendar instance
    if (this.calendar) {
      this.calendar.destroy();
      this.calendar = null;
    }
  }
};
</script>

<style scoped lang="scss">
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

  &.add-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;

    &:hover {
      box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
    }
  }
}

.calendar-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  overflow: hidden;
  
  @media (max-width: 768px) {
    border-radius: 12px;
    padding: 1rem;
  }
  
  @media (max-width: 480px) {
    border-radius: 0;
    padding: 0.75rem;
    margin-left: -1rem;
    margin-right: -1rem;
  }
}

.fc-container {
  min-height: 600px;
  width: 100%;
  
  @media (max-width: 768px) {
    min-height: 500px;
  }
  
  @media (max-width: 480px) {
    min-height: 400px;
  }
}

/* FullCalendar custom styles */
:deep(.fc) {
  font-family: inherit;
  
  @media (max-width: 768px) {
    font-size: 0.875rem;
  }
  
  @media (max-width: 480px) {
    font-size: 0.75rem;
  }
}

/* Header Toolbar */
:deep(.fc-header-toolbar) {
  @media (max-width: 768px) {
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem !important;
    
    .fc-toolbar-chunk {
      display: flex;
      justify-content: center;
      width: 100%;
    }
    
    .fc-button-group {
      button {
        padding: 0.4rem 0.6rem;
        font-size: 0.75rem;
      }
    }
    
    .fc-button {
      padding: 0.4rem 0.8rem;
      font-size: 0.75rem;
    }
    
    .fc-toolbar-title {
      font-size: 1rem !important;
      margin: 0.5rem 0;
    }
  }
  
  @media (max-width: 480px) {
    .fc-button-group {
      button {
        padding: 0.3rem 0.5rem;
        font-size: 0.7rem;
      }
    }
    
    .fc-button {
      padding: 0.3rem 0.6rem;
      font-size: 0.7rem;
    }
    
    .fc-toolbar-title {
      font-size: 0.9rem !important;
    }
  }
}

/* Calendar Grid */
:deep(.fc-daygrid-day-frame) {
  @media (max-width: 480px) {
    min-height: 60px !important;
  }
}

:deep(.fc-daygrid-day-number) {
  @media (max-width: 480px) {
    padding: 2px 4px !important;
    font-size: 0.75rem !important;
  }
}

:deep(.fc-col-header-cell) {
  @media (max-width: 480px) {
    padding: 0.5rem 0.25rem !important;
    
    .fc-col-header-cell-cushion {
      font-size: 0.7rem !important;
    }
  }
}

:deep(.fc-event) {
  cursor: pointer;
  border-radius: 4px;
  
  @media (max-width: 480px) {
    font-size: 0.7rem;
    padding: 1px 2px;
    margin: 1px 0;
  }
}

:deep(.fc-daygrid-event) {
  border-radius: 4px;
  padding: 2px 4px;
  
  @media (max-width: 480px) {
    padding: 1px 2px;
    font-size: 0.7rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

:deep(.fc-timegrid-event) {
  border-radius: 4px;
  
  @media (max-width: 480px) {
    font-size: 0.7rem;
  }
}

:deep(.fc-more-link) {
  @media (max-width: 480px) {
    font-size: 0.7rem;
    padding: 2px 4px;
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
    background: #fed7aa;
    color: #9a3412;
  }

  &.confirmed {
    background: #dbeafe;
    color: #1d4ed8;
  }

  &.cancelled {
    background: #fee2e2;
    color: #b91c1c;
  }

  &.completed {
    background: #bbf7d0;
    color: #15803d;
  }
}

.white-space-preline {
  white-space: pre-line;
}

/* Booking Detail Modal Responsive */
::v-deep .booking-calendar-modal-wrapper {
  .modal-dialog {
    @media (max-width: 768px) {
      max-width: 100% !important;
      margin: 0 !important;
      width: 100% !important;
    }
  }

  .modal-content {
    @media (max-width: 768px) {
      border-radius: 0 !important;
      min-height: 100vh !important;
      height: 100vh !important;
    }
  }
  
  .modal-body {
    @media (max-width: 768px) {
      padding: 1rem !important;
      overflow-y: auto !important;
    }
    
    @media (max-width: 480px) {
      padding: 0.75rem !important;
    }
  }
  
  .modal-header {
    @media (max-width: 768px) {
      padding: 1rem !important;
      border-bottom: 2px solid #f1f5f9;
    }
    
    @media (max-width: 480px) {
      padding: 0.75rem !important;
      
      .modal-title {
        font-size: 1rem !important;
      }
    }
  }
}

/* Responsive columns in modal */
@media (max-width: 768px) {
  ::v-deep .booking-calendar-modal-wrapper {
    .row {
      margin-left: -0.5rem;
      margin-right: -0.5rem;
    }
    
    [class*="col-"] {
      padding-left: 0.5rem;
      padding-right: 0.5rem;
      margin-bottom: 0.75rem;
    }
    
    strong {
      font-size: 0.875rem;
      display: block;
      margin-bottom: 0.25rem;
      color: #64748b;
    }
    
    .status-badge {
      font-size: 0.7rem;
      padding: 0.35rem 0.7rem;
    }
  }
}

@media (max-width: 991px) {
  .bookings-page-modern {
    padding: 1rem;
  }

  .control-bar {
    flex-direction: column;
    align-items: stretch;
    padding: 1rem;
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

@media (max-width: 768px) {
  .bookings-page-modern {
    padding: 0.75rem;
  }
  
  .page-wrapper {
    max-width: 100%;
  }

  .control-bar {
    padding: 0.875rem;
    border-radius: 12px;
    margin-bottom: 1rem;
  }

  .page-title {
    font-size: 1rem;
  }

  .calendar-card {
    border-radius: 12px;
    padding: 0.75rem;
  }
}

@media (max-width: 480px) {
  .bookings-page-modern {
    padding: 0.5rem;
    background: #f5f7fa;
  }

  .control-bar {
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 0.75rem;
  }

  .page-title {
    font-size: 0.9rem;
  }

  .control-right {
    gap: 0.5rem;
    
    .action-btn {
      padding: 0.35rem 0.75rem;
      font-size: 0.7rem;
      
      i {
        font-size: 0.8rem;
      }
      
      span {
        font-size: 0.7rem;
      }
    }
  }
}
</style>












