<template>
  <div class="main-content">
    <breadcumb :page="$t('Create_Booking')" :folder="$t('Bookings')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="ref_create_booking" v-if="!isLoading">
      <b-form @submit.prevent="submitBooking">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>
                <!-- Customer -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider
                    name="Customer"
                    :rules="{ required: true }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Customer') + ' *'">
                      <v-select
                        :class="{ 'is-invalid': validationContext.errors.length }"
                        :state="getValidationState(validationContext)"
                        v-model="booking.customer_id"
                        :reduce="c => c.id"
                        label="name"
                        :placeholder="$t('Choose_Customer')"
                        :options="customers"
                      />
                      <b-form-invalid-feedback>
                        {{ validationContext.errors[0] }}
                      </b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Service Product (optional) -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Product" v-slot="validationContext">
                    <b-form-group :label="$t('Product')">
                      <v-select
                        :class="{ 'is-invalid': validationContext.errors.length }"
                        :state="getValidationState(validationContext)"
                        v-model="booking.product_id"
                        :reduce="p => p.id"
                        label="name"
                        :placeholder="$t('Choose_Product')"
                        :options="products"
                        @input="onProductChange"
                      />
                      <small class="text-muted d-block mt-1">
                        <span class="text-muted">Only products type service</span>
                      </small>
                      <b-form-invalid-feedback>
                        {{ validationContext.errors[0] }}
                      </b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Price -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider
                    name="Price"
                    :rules="{ numeric: true, min_value: 0 }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Price')">
                      <b-form-input
                        type="number"
                        step="0.01"
                        min="0"
                        v-model="booking.price"
                        :state="getValidationState(validationContext)"
                        aria-describedby="booking-price-feedback"
                        :placeholder="$t('Enter_Price')"
                      />
                      <b-form-invalid-feedback id="booking-price-feedback">
                        {{ validationContext.errors[0] }}
                      </b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Booking Date -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider
                    name="Booking Date"
                    :rules="{ required: true }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Date') + ' *'">
                      <b-form-input
                        type="date"
                        v-model="booking.booking_date"
                        :state="getValidationState(validationContext)"
                        aria-describedby="booking-date-feedback"
                      />
                      <b-form-invalid-feedback id="booking-date-feedback">
                        {{ validationContext.errors[0] }}
                      </b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Start Time -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider
                    name="Start Time"
                    :rules="{ required: true }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Start_Time') + ' *'">
                      <b-form-input
                        type="time"
                        v-model="booking.booking_time"
                        :state="getValidationState(validationContext)"
                        aria-describedby="booking-time-feedback"
                      />
                      <b-form-invalid-feedback id="booking-time-feedback">
                        {{ validationContext.errors[0] }}
                      </b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- End Time (optional) -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="End Time" v-slot="validationContext">
                    <b-form-group :label="$t('End_Time')">
                      <b-form-input
                        type="time"
                        v-model="booking.booking_end_time"
                        :state="getValidationState(validationContext)"
                        aria-describedby="booking-end-time-feedback"
                      />
                      <b-form-invalid-feedback id="booking-end-time-feedback">
                        {{ validationContext.errors[0] }}
                      </b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Status -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider
                    name="Status"
                    :rules="{ required: true }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Status') + ' *'">
                      <v-select
                        :class="{ 'is-invalid': validationContext.errors.length }"
                        :state="getValidationState(validationContext)"
                        v-model="booking.status"
                        :reduce="s => s.value"
                        :placeholder="$t('Choose_Status')"
                        :options="statusOptions"
                      />
                      <b-form-invalid-feedback>
                        {{ validationContext.errors[0] }}
                      </b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Notes -->
                <b-col lg="8" md="8" sm="12">
                  <validation-provider name="Notes" v-slot="validationContext">
                    <b-form-group :label="$t('Details')">
                      <textarea
                        v-model="booking.notes"
                        rows="4"
                        class="form-control"
                        :class="{ 'is-invalid': validationContext.errors.length }"
                        :state="getValidationState(validationContext)"
                        :placeholder="$t('Afewwords')"
                      ></textarea>
                      <b-form-invalid-feedback>
                        {{ validationContext.errors[0] }}
                      </b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <b-col md="12">
                  <b-form-group>
                    <b-button
                      variant="primary"
                      type="submit"
                      :disabled="submitProcessing"
                    >
                      <lucide-icon class="me-2 font-weight-bold" name="check" />
                      {{ $t('submit') }}
                    </b-button>
                    <div v-once class="typo__p" v-if="submitProcessing">
                      <div class="spinner sm spinner-primary mt-3"></div>
                    </div>
                  </b-form-group>
                </b-col>
              </b-row>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>
  </div>
</template>

<script>
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Create Booking"
  },
  data() {
    return {
      isLoading: true,
      submitProcessing: false,
      customers: [],
      products: [],
      statusOptions: [
        { label: this.$t("Pending") || "Pending", value: "pending" },
        { label: this.$t("Confirmed") || "Confirmed", value: "confirmed" },
        { label: this.$t("Cancelled") || "Cancelled", value: "cancelled" },
        { label: this.$t("complete") || "Completed", value: "completed" }
      ],
      booking: {
        customer_id: null,
        product_id: null,
        price: null,
        booking_date: new Date().toISOString().slice(0, 10),
        booking_time: "",
        booking_end_time: "",
        status: "pending",
        notes: ""
      }
    };
  },
  methods: {
    submitBooking() {
      this.$refs.ref_create_booking.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.createBooking();
        }
      });
    },
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },
    createBooking() {
      this.submitProcessing = true;
      NProgress.start();
      NProgress.set(0.1);

      const payload = {
        customer_id: this.booking.customer_id,
        product_id: this.booking.product_id,
        price: this.booking.price ? parseFloat(this.booking.price) : null,
        booking_date: this.booking.booking_date,
        booking_time: this.booking.booking_time,
        booking_end_time: this.booking.booking_end_time || null,
        status: this.booking.status,
        notes: this.booking.notes
      };

      axios
        .post("bookings", payload)
        .then(() => {
          NProgress.done();
          this.submitProcessing = false;
          this.$router.push({ name: "index_booking" });
          this.makeToast(
            "success",
            this.$t("Successfully_Created"),
            this.$t("Success")
          );
        })
        .catch(() => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.submitProcessing = false;
        });
    },
    loadMeta() {
      axios
        .get("bookings/create")
        .then(response => {
          this.customers = response.data.customers || [];
          this.products = response.data.products || [];
          this.isLoading = false;
        })
        .catch(() => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },
    onProductChange(productId) {
      if (productId) {
        const selectedProduct = this.products.find(p => p.id === productId);
        if (selectedProduct && selectedProduct.price) {
          // Prefill price from product, but allow editing
          this.booking.price = selectedProduct.price;
        }
      } else {
        // Clear price if product is deselected
        this.booking.price = null;
      }
    }
  },
  created() {
    this.loadMeta();
  }
};
</script>












