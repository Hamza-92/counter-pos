<template>
  <div class="main-content">
    <breadcumb class="no-print" :page="$t('SaleDetail')" :folder="$t('Sales')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3 no-print"></div>

    <b-card v-if="!isLoading" class="print-card">
      <b-row class="no-print">
        <b-col md="12" class="mb-4">
          <div class="action-buttons-wrapper">
            <!-- Navigation Actions Group -->
            <div class="button-group navigation-actions">
              <router-link
                :to="{ name: 'index_sales' }"
                class="action-btn btn-back"
                title="Back"
              >
                <lucide-icon name="chevron-left" />
                <span>{{$t('Back')}}</span>
              </router-link>
            </div>

            <!-- Primary Actions Group -->
            <div class="button-group primary-actions">
              <router-link
                v-if="currentUserPermissions && currentUserPermissions.includes('Sales_edit') && sale.sale_has_return == 'no'"
                title="Edit"
                class="action-btn btn-edit"
                :to="{ name:'edit_sale', params: { id: $route.params.id } }"
              >
                <lucide-icon name="pencil" />
                <span>{{$t('EditSale')}}</span>
              </router-link>

              <button
                v-if="currentUserPermissions && currentUserPermissions.includes('Sales_delete') && sale.sale_has_return == 'no'"
                @click="Delete_Sale()"
                class="action-btn btn-delete"
                title="Delete"
              >
                <lucide-icon name="x" />
                <span>{{$t('Del')}}</span>
              </button>
            </div>

            <!-- Communication Actions Group -->
            <div class="button-group communication-actions">
              <button @click="Send_Email()" class="action-btn btn-email" title="Send Email">
                <lucide-icon name="mail" />
                <span>{{$t('Email')}}</span>
              </button>
              <button @click="Sale_SMS()" class="action-btn btn-sms" title="Send SMS">
                <lucide-icon name="message-square" />
                <span>SMS</span>
              </button>
            </div>

            <!-- Export & Print Actions Group -->
            <div class="button-group export-actions">
              <button @click="Sale_PDF()" class="action-btn btn-pdf" title="Download PDF">
                <lucide-icon name="file-text" />
                <span>PDF</span>
              </button>
              <button @click="print()" class="action-btn btn-print" title="Print">
                <lucide-icon name="receipt" />
                <span>{{$t('print')}}</span>
              </button>
            </div>
          </div>
        </b-col>
      </b-row>
      <div class="invoice" id="print_Invoice">
        <div class="invoice-print">
          <!-- Header Section -->
          <table class="invoice-header-table">
            <tr>
              <td class="invoice-logo-cell">
                <img
                  v-if="company.logo"
                  :src="'/images/' + company.logo"
                  alt="Logo"
                  class="invoice-logo"
                  :style="{
                    maxWidth: (company.invoice_logo_width || 180) + 'px',
                    maxHeight: (company.invoice_logo_height || 60) + 'px'
                  }"
                />
              </td>
              <td class="invoice-title-cell">
                <div class="invoice-main-title">SALES INVOICE</div>
                <div class="invoice-ref-badge">{{sale.Ref}}</div>
                <table class="invoice-meta-table">
                  <tr>
                    <td class="invoice-meta-label">Date:</td>
                    <td class="invoice-meta-value">{{formatDisplayDate(sale.date)}}</td>
                  </tr>
                  <tr>
                    <td class="invoice-meta-label">Invoice #:</td>
                    <td class="invoice-meta-value">{{sale.Ref}}</td>
                  </tr>
                  <tr>
                    <td class="invoice-meta-label">Status:</td>
                    <td class="invoice-meta-value">
                      <span :class="getStatusBadgeClass(sale.statut)">{{sale.statut}}</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="invoice-meta-label">Payment:</td>
                    <td class="invoice-meta-value">
                      <span :class="getPaymentBadgeClass(sale.payment_status)">{{sale.payment_status}}</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- Divider -->
          <div class="invoice-divider"></div>

          <!-- Bill To / From Section -->
          <table class="invoice-billto-table">
            <tr>
              <td class="invoice-billto-cell">
                <div class="invoice-box">
                  <div class="invoice-box-header">BILL TO</div>
                  <div class="invoice-box-content">
                    <div class="invoice-box-name">{{sale.client_name}}</div>
                    <div class="invoice-box-details">
                      <div><strong>Phone:</strong> {{sale.client_phone}}</div>
                      <div><strong>Email:</strong> {{sale.client_email}}</div>
                      <div><strong>Address:</strong> {{sale.client_adr}}</div>
                      <div v-if="sale.client_tax"><strong>Tax #:</strong> {{sale.client_tax}}</div>
                    </div>
                  </div>
                </div>
              </td>
              <td class="invoice-spacer-cell"></td>
              <td class="invoice-billto-cell">
                <div class="invoice-box">
                  <div class="invoice-box-header">FROM</div>
                  <div class="invoice-box-content">
                    <div class="invoice-box-name">{{company.CompanyName}}</div>
                    <div class="invoice-box-details">
                      <div><strong>Phone:</strong> {{company.CompanyPhone}}</div>
                      <div><strong>Email:</strong> {{company.email}}</div>
                      <div><strong>Address:</strong> {{company.CompanyAdress}}</div>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </table>

          <!-- Products Table - Desktop View -->
          <table class="invoice-products-table invoice-products-desktop">
            <thead>
              <tr class="invoice-products-header">
                <th class="invoice-products-th-left">PRODUCT</th>
                <th class="invoice-products-th-right">PRICE</th>
                <th class="invoice-products-th-right">QTY</th>
                <th class="invoice-products-th-right">DISC</th>
                <th class="invoice-products-th-right">TAX</th>
                <th class="invoice-products-th-right">TOTAL</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(detail, index) in details" :key="index" :class="{'invoice-products-row-even': index % 2 === 1}">
                <td class="invoice-product-name-cell">
                  <div class="invoice-product-name">{{detail.name}}</div>
                  <div class="invoice-product-code">Code: {{detail.code}}</div>
                  <div v-if="detail.is_imei && detail.imei_number !== null" class="invoice-product-imei">SN: {{detail.imei_number}}</div>
                  <div v-if="detail.is_batch_tracked && (detail.batches || []).length > 0" class="invoice-product-batches">
                    <div class="invoice-product-batches-title">{{ $t('Batches') || 'Batches' }}</div>
                    <div class="invoice-product-batch-chips">
                      <span v-for="(b, bIdx) in detail.batches" :key="'b-' + index + '-' + bIdx" class="invoice-product-batch-chip">
                        <strong>{{ b.batch_no }}</strong>
                        <span v-if="b.expiry_date" :style="expiry_pill_style(b.expiry_date)">{{ b.expiry_date }}</span>
                        <span class="invoice-product-batch-qty">× {{ formatNumber(Number(b.qty) || 0, 2) }}</span>
                      </span>
                    </div>
                  </div>
                </td>
                <td class="invoice-product-price-cell">{{ formatPriceDisplay(detail.price, 2) }}</td>
                <td class="invoice-product-price-cell">{{formatNumber(detail.quantity,2)}} {{detail.unit_sale}}</td>
                <td class="invoice-product-discount-cell">{{ formatPriceDisplay(detail.DiscountNet, 2) }}</td>
                <td class="invoice-product-price-cell">{{ formatPriceDisplay(detail.taxe, 2) }}</td>
                <td class="invoice-product-total-cell">{{ formatPriceDisplay(detail.total, 2) }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Products Cards - Mobile/Tablet View -->
          <div class="invoice-products-mobile">
            <div class="invoice-products-mobile-header">PRODUCTS</div>
            <div 
              v-for="(detail, index) in details" 
              :key="index" 
              class="invoice-product-card"
            >
              <div class="invoice-product-card-header">
                <div class="invoice-product-card-name">{{detail.name}}</div>
                <div class="invoice-product-card-total">{{ formatPriceDisplay(detail.total, 2) }}</div>
              </div>
              <div class="invoice-product-card-body">
                <div class="invoice-product-card-row">
                  <span class="invoice-product-card-label">Code:</span>
                  <span class="invoice-product-card-value">{{detail.code}}</span>
                </div>
                <div v-if="detail.is_imei && detail.imei_number !== null" class="invoice-product-card-row">
                  <span class="invoice-product-card-label">SN:</span>
                  <span class="invoice-product-card-value">{{detail.imei_number}}</span>
                </div>
                <div v-if="detail.is_batch_tracked && (detail.batches || []).length > 0" class="invoice-product-card-batches">
                  <div class="invoice-product-batches-title">{{ $t('Batches') || 'Batches' }}</div>
                  <div class="invoice-product-batch-chips">
                    <span v-for="(b, bIdx) in detail.batches" :key="'mb-' + index + '-' + bIdx" class="invoice-product-batch-chip">
                      <strong>{{ b.batch_no }}</strong>
                      <span v-if="b.expiry_date" :style="expiry_pill_style(b.expiry_date)">{{ b.expiry_date }}</span>
                      <span class="invoice-product-batch-qty">× {{ formatNumber(Number(b.qty) || 0, 2) }}</span>
                    </span>
                  </div>
                </div>
                <div class="invoice-product-card-details">
                  <div class="invoice-product-card-detail-item">
                    <span class="invoice-product-card-detail-label">Price:</span>
                    <span class="invoice-product-card-detail-value">{{ formatPriceDisplay(detail.price, 2) }}</span>
                  </div>
                  <div class="invoice-product-card-detail-item">
                    <span class="invoice-product-card-detail-label">Qty:</span>
                    <span class="invoice-product-card-detail-value">{{formatNumber(detail.quantity,2)}} {{detail.unit_sale}}</span>
                  </div>
                  <div class="invoice-product-card-detail-item">
                    <span class="invoice-product-card-detail-label">Disc:</span>
                    <span class="invoice-product-card-detail-value discount">{{ formatPriceDisplay(detail.DiscountNet, 2) }}</span>
                  </div>
                  <div class="invoice-product-card-detail-item">
                    <span class="invoice-product-card-detail-label">Tax:</span>
                    <span class="invoice-product-card-detail-value">{{ formatPriceDisplay(detail.taxe, 2) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Summary Section -->
          <table class="invoice-summary-wrapper">
            <tr>
              <td class="invoice-summary-spacer"></td>
              <td class="invoice-summary-cell">
                <table class="invoice-summary-table">
                  <tr class="invoice-summary-row-alt">
                    <td class="invoice-summary-label">Subtotal:</td>
                    <td class="invoice-summary-value">{{ formatPriceWithSymbol(currentUser.currency, invoiceSubtotal, 2) }}</td>
                  </tr>
                  <tr class="invoice-summary-row">
                    <td class="invoice-summary-label">Order Tax:</td>
                    <td class="invoice-summary-value">{{ formatPriceWithSymbol(currentUser.currency, sale.TaxNet, 2) }}</td>
                  </tr>
                  <tr class="invoice-summary-row-alt">
                    <td class="invoice-summary-label">Discount:</td>
                    <td class="invoice-summary-discount-value">
                      <template v-if="String(sale.discount_Method || '2') === '1'">
                        - {{ formatNumber(sale.discount, 2) }}% ({{ formatPriceWithSymbol(currentUser.currency, manualSaleDiscountAmount, 2) }})
                      </template>
                      <template v-else>
                        - {{ formatPriceWithSymbol(currentUser.currency, manualSaleDiscountAmount, 2) }}
                      </template>
                    </td>
                  </tr>
                  <tr v-if="sale.discount_from_points && Number(sale.discount_from_points) > 0" class="invoice-summary-row-alt">
                    <td class="invoice-summary-label">Discount from Points:</td>
                    <td class="invoice-summary-discount-value">- {{ formatPriceWithSymbol(currentUser.currency, sale.discount_from_points, 2) }}</td>
                  </tr>
                  <tr class="invoice-summary-row">
                    <td class="invoice-summary-label">Shipping:</td>
                    <td class="invoice-summary-value">{{ formatPriceWithSymbol(currentUser.currency, sale.shipping, 2) }}</td>
                  </tr>
                  <tr class="invoice-summary-total-row">
                    <td class="invoice-summary-total-label">TOTAL:</td>
                    <td class="invoice-summary-total-value">{{ formatPriceWithSymbol(currentUser.currency, sale.GrandTotal, 2) }}</td>
                  </tr>
                  <tr class="invoice-summary-paid-row">
                    <td class="invoice-summary-paid-label">Paid Amount:</td>
                    <td class="invoice-summary-paid-value">{{ formatPriceWithSymbol(currentUser.currency, sale.paid_amount, 2) }}</td>
                  </tr>
                  <tr class="invoice-summary-due-row">
                    <td class="invoice-summary-due-label">Amount Due:</td>
                    <td class="invoice-summary-due-value">{{ formatPriceWithSymbol(currentUser.currency, sale.due, 2) }}</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- Footer -->
          <div class="invoice-footer">
            <div v-if="company.is_invoice_footer && company.invoice_footer" class="invoice-footer-text">
              <p>{{company.invoice_footer}}</p>
            </div>
            <div class="invoice-footer-thanks">
              <p>Thank you for your business!</p>
            </div>
          </div>
        </div>
      </div>
    </b-card>
  </div>
</template>

<script>

import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";
import Util from "../../../../utils/index";

export default {
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),

    // Sum of line totals before order-level tax/discount/shipping
    invoiceSubtotal() {
      try {
        const details = Array.isArray(this.details) ? this.details : [];
        return details.reduce((sum, d) => {
          const n = Number(d && d.total != null ? d.total : 0);
          return sum + (Number.isFinite(n) ? n : 0);
        }, 0);
      } catch (e) {
        return 0;
      }
    },

    // Manual discount amount only (excluding discount from points)
    manualSaleDiscountAmount() {
      try {
        const sale = this.sale || {};
        const discMethod = String(sale.discount_Method || '2');
        const discVal = Number(sale.discount || 0);
        // Use actual order subtotal from line totals
        const subtotal = Number(this.invoiceSubtotal || 0);
        if (!Number.isFinite(subtotal) || subtotal <= 0) {
          return 0;
        }

        if (discMethod === '1') {
          // Percentage discount: use subtotal * %
          return parseFloat((subtotal * (discVal / 100)).toFixed(2));
        }
        // Fixed discount
        return parseFloat(Math.min(discVal, subtotal).toFixed(2));
      } catch (e) {
        return 0;
      }
    },
  },
  metaInfo: {
    title: "Detail Sale"
  },

  data() {
    return {
      isLoading: true,
      sale: {},
      details: [],
      variants: [],
      company: {},
      email: {
        to: "",
        subject: "",
        message: "",
        client_name: "",
        Sale_Ref: ""
      },
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },

  methods: {
   

    //----------------------------------- Invoice Sale PDF  -------------------------\\
    Sale_PDF() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
     
       axios
        .get(`sale_pdf/${id}`, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "Sale_" + this.sale.Ref + ".pdf");
          document.body.appendChild(link);
          link.click();
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        });
    },

     //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
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

    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing formatNumber helper to preserve current behavior.
    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 0;
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(number, decimals, effectiveKey);
      } catch (e) {
        return this.formatNumber(number, dec);
      }
    },

    formatPriceWithSymbol(symbol, number, dec) {
      const safeSymbol = symbol || "";
      const value = this.formatPriceDisplay(number, dec);
      return safeSymbol ? `${safeSymbol} ${value}` : value;
    },

    //------------------------------ Format Display Date -------------------------\\
    formatDisplayDate(value) {
      const dateFormat = this.$store.getters.getDateFormat || Util.getDateFormat(this.$store);
      return Util.formatDisplayDate(value, dateFormat);
    },

    //------------------------------ Expiry Pill Style -------------------------\\
    expiry_pill_style(dateStr) {
      const base = {
        display: "inline-block",
        padding: "2px 8px",
        fontSize: "11px",
        fontWeight: "600",
        borderRadius: "10px",
        marginLeft: "6px",
      };
      if (!dateStr) return Object.assign({}, base, { background: "#f3f4f6", color: "#6b7280" });
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const exp = new Date(dateStr);
      if (isNaN(exp.getTime())) return Object.assign({}, base, { background: "#f3f4f6", color: "#6b7280" });
      exp.setHours(0, 0, 0, 0);
      const diffDays = Math.round((exp - today) / (1000 * 60 * 60 * 24));
      if (diffDays < 0) return Object.assign({}, base, { background: "#fee2e2", color: "#991b1b" });
      if (diffDays <= 30) return Object.assign({}, base, { background: "#fef3c7", color: "#92400e" });
      return Object.assign({}, base, { background: "#dcfce7", color: "#166534" });
    },

    //------------------------------ Get Status Badge Class -------------------------\\
    getStatusBadgeClass(status) {
      const statusLower = (status || '').toLowerCase();
      const statusClasses = {
        'completed': 'invoice-status-badge invoice-status-completed',
        'paid': 'invoice-status-badge invoice-status-completed',
        'pending': 'invoice-status-badge invoice-status-pending',
        'unpaid': 'invoice-status-badge invoice-status-pending',
        'partial': 'invoice-status-badge invoice-status-partial',
      };
      return statusClasses[statusLower] || 'invoice-status-badge invoice-status-default';
    },

    //------------------------------ Get Payment Badge Class -------------------------\\
    getPaymentBadgeClass(paymentStatus) {
      const paymentLower = (paymentStatus || '').toLowerCase();
      const paymentClasses = {
        'paid': 'invoice-status-badge invoice-status-completed',
        'pending': 'invoice-status-badge invoice-status-pending',
        'unpaid': 'invoice-status-badge invoice-status-pending',
        'partial': 'invoice-status-badge invoice-status-partial',
      };
      return paymentClasses[paymentLower] || 'invoice-status-badge invoice-status-default';
    },

    //------------------------------ Print -------------------------\\
    print() {
      // Fetch HTML from sale_pdf.blade.php template and print it
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      let printWindow = null;
      let printTriggered = false;
      let closeTimeout = null;
      
      axios
        .get(`sale_print_html/${id}`)
        .then(response => {
          // Create a new window with the HTML content
          printWindow = window.open('', '_blank', 'width=800,height=600');
          if (printWindow) {
            printWindow.document.open();
            printWindow.document.write(response.data);
            printWindow.document.close();
            
            // Function to close the print window
            const closePrintWindow = () => {
              if (closeTimeout) {
                clearTimeout(closeTimeout);
              }
              if (printWindow && !printWindow.closed) {
                printWindow.close();
              }
            };
            
            // Function to trigger print once
            const triggerPrint = () => {
              if (printTriggered) {
                return; // Already triggered, don't trigger again
              }
              
              if (printWindow && printWindow.document.readyState === 'complete') {
                printTriggered = true;
                try {
                  // Trigger print
                  printWindow.focus();
                  printWindow.print();
                  
                  // Listen for print dialog close (whether user prints or cancels)
                  // afterprint event fires when print dialog closes (modern browsers)
                  const handleAfterPrint = () => {
                    closePrintWindow();
                  };
                  
                  printWindow.addEventListener('afterprint', handleAfterPrint, { once: true });
                  
                  // Also use matchMedia listener as fallback for better browser support
                  if (printWindow.matchMedia) {
                    const mediaQueryList = printWindow.matchMedia('print');
                    const handleMediaChange = (mql) => {
                      if (!mql.matches) {
                        // Print dialog closed
                        closePrintWindow();
                        mediaQueryList.removeListener(handleMediaChange);
                      }
                    };
                    mediaQueryList.addListener(handleMediaChange);
                  }
                  
                  // Fallback: close window after reasonable delay if events don't fire
                  // This handles edge cases and older browsers
                  closeTimeout = setTimeout(closePrintWindow, 2000);
                } catch (e) {
                  console.error('Print error:', e);
                  closePrintWindow();
                }
              }
            };
            
            // Wait for content to load, then print
            if (printWindow.document.readyState === 'complete') {
              // Content already loaded
              setTimeout(triggerPrint, 100);
            } else {
              // Wait for load event
              printWindow.onload = function() {
                setTimeout(triggerPrint, 100);
              };
            }
          }
          
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(error => {
          console.error('Print error:', error);
          this.makeToast('danger', this.$t('PrintError') || 'Print failed', this.$t('Failed'));
          if (printWindow && !printWindow.closed) {
            printWindow.close();
          }
          setTimeout(() => NProgress.done(), 500);
        });
    },


    Send_Email() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .post("sales_send_email", {
          id: id,
        })
        .then(response => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast(
            "success",
            this.$t("SendEmail"),
            this.$t("Success")
          );
        })
        .catch(error => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("SMTPIncorrect"), this.$t("Failed"));
        });
    },

    //---------SMS notification
     Sale_SMS() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .post("sales_send_sms", {
          id: id,
        })
        .then(response => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast(
            "success",
            this.$t("Send_SMS"),
            this.$t("Success")
          );
        })
        .catch(error => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("sms_config_invalid"), this.$t("Failed"));
        });
    },

    //----------------------------------- Get Details Sale ------------------------------\\
    Get_Details() {
      let id = this.$route.params.id;
      axios
        .get(`sales/${id}`)
        .then(response => {
          this.sale = response.data.sale;
          this.details = response.data.details;
          this.company = response.data.company;
          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //------------------------------------------ DELETE Sale ------------------------------\\
    Delete_Sale() {
      let id = this.$route.params.id;
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
            .delete("sales/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              this.$router.push({ name: "index_sales" });
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
  }, //end Methods

  //----------------------------- Created function-------------------

  created: function() {
    this.Get_Details();
  }
};
</script>

<style scoped>
.main-content {
  width: 100%;
  max-width: 100%;
  overflow-x: hidden;
}

.action-buttons-wrapper {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: center;
  padding: 1rem 0;
}

.button-group {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.625rem 1.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  text-decoration: none;
  position: relative;
  overflow: hidden;
}

.action-btn i {
  font-size: 1rem;
  line-height: 1;
}

.action-btn span {
  white-space: nowrap;
}

.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.action-btn:active {
  transform: translateY(0);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Back Button */
.btn-back {
  background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
  color: #ffffff;
}

.btn-back:hover {
  background: linear-gradient(135deg, #5a6268 0%, #545b62 100%);
  color: #ffffff;
}

/* Edit Button */
.btn-edit {
  background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
  color: #ffffff;
}

.btn-edit:hover {
  background: linear-gradient(135deg, #218838 0%, #1aa179 100%);
  color: #ffffff;
}

/* Delete Button */
.btn-delete {
  background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
  color: #ffffff;
}

.btn-delete:hover {
  background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
  color: #ffffff;
}

/* Email Button */
.btn-email {
  background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
  color: #ffffff;
}

.btn-email:hover {
  background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
  color: #ffffff;
}

/* SMS Button */
.btn-sms {
  background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
  color: #ffffff;
}

.btn-sms:hover {
  background: linear-gradient(135deg, #5a6268 0%, #545b62 100%);
  color: #ffffff;
}

/* PDF Button */
.btn-pdf {
  background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
  color: #ffffff;
}

.btn-pdf:hover {
  background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
  color: #ffffff;
}

/* Print Button */
.btn-print {
  background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
  color: #212529;
}

.btn-print:hover {
  background: linear-gradient(135deg, #e0a800 0%, #d39e00 100%);
  color: #212529;
}

/* Products - Desktop/Default Styles */
.invoice-products-mobile {
  display: none;
}

.invoice-products-desktop {
  display: table;
}

/* Responsive Design - Tablet */
@media (max-width: 1024px) and (min-width: 769px) {
  .invoice-print {
    padding: 12px 15px;
    font-size: 8.5pt;
  }

  .invoice-header-table {
    margin-bottom: 10px;
  }

  .invoice-logo {
    max-height: 50px;
    max-width: 150px;
  }

  .invoice-main-title {
    font-size: 16pt;
  }

  /* Bill To / From - Stack vertically on tablet */
  .invoice-billto-table {
    display: block;
    margin-bottom: 15px;
  }

  .invoice-billto-table tr {
    display: block;
  }

  .invoice-billto-cell {
    display: block;
    width: 100% !important;
    margin-bottom: 12px;
  }

  .invoice-spacer-cell {
    display: none;
  }

  /* Show mobile cards on tablet, hide desktop table */
  .invoice-products-desktop {
    display: none;
  }

  .invoice-products-mobile {
    display: block;
    margin-bottom: 15px;
  }

  .invoice-products-mobile-header {
    font-size: 10pt;
    padding: 10px 14px;
  }

  .invoice-product-card {
    padding: 14px;
  }

  .invoice-product-card-name {
    font-size: 10pt;
  }

  .invoice-product-card-total {
    font-size: 11pt;
  }

  .invoice-product-card-row {
    font-size: 8pt;
  }

  .invoice-product-card-detail-label {
    font-size: 7.5pt;
  }

  .invoice-product-card-detail-value {
    font-size: 9pt;
  }

  /* Summary - Full width on tablet for better visibility */
  .invoice-summary-wrapper {
    display: block;
    margin-bottom: 15px;
  }

  .invoice-summary-wrapper tr {
    display: block;
  }

  .invoice-summary-spacer {
    display: none;
  }

  .invoice-summary-cell {
    display: block;
    width: 100% !important;
  }

  .invoice-summary-table {
    width: 100%;
  }

  .invoice-summary-label,
  .invoice-summary-value,
  .invoice-summary-discount-value {
    padding: 6px 12px;
    font-size: 8pt;
  }

  .invoice-summary-value,
  .invoice-summary-discount-value {
    text-align: right !important;
  }

  .invoice-summary-total-label,
  .invoice-summary-total-value {
    padding: 8px 12px;
    font-size: 10pt;
  }

  .invoice-summary-total-value {
    text-align: right !important;
  }

  .invoice-summary-paid-label,
  .invoice-summary-paid-value,
  .invoice-summary-due-label,
  .invoice-summary-due-value {
    padding: 6px 12px;
    font-size: 8.5pt;
  }

  .invoice-summary-paid-value,
  .invoice-summary-due-value {
    text-align: right !important;
  }
}

/* Responsive Design - Mobile and Tablet */
@media (max-width: 768px) {
  .print-card {
    padding: 0.5rem;
  }

  .print-card .card-body {
    padding: 0.75rem;
  }

  .action-buttons-wrapper {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
    padding: 0.75rem 0;
  }

  .button-group {
    flex-direction: row;
    flex-wrap: wrap;
    width: 100%;
    gap: 0.5rem;
  }

  .action-btn {
    flex: 1 1 auto;
    min-width: calc(50% - 0.25rem);
    justify-content: center;
    padding: 0.625rem 0.875rem;
    font-size: 0.8125rem;
  }

  .action-btn span {
    display: none;
  }

  .action-btn i {
    font-size: 1.1rem;
  }

  /* Invoice responsive styles */
  .invoice {
    padding: 10px;
    border-radius: 0;
    overflow-x: hidden;
    width: 100%;
    max-width: 100%;
  }

  .invoice-print {
    padding: 10px 12px;
    font-size: 8pt;
  }

  /* Header - Stack vertically on mobile */
  .invoice-header-table {
    display: block;
    margin-bottom: 15px;
  }

  .invoice-header-table tr {
    display: block;
  }

  .invoice-logo-cell {
    display: block;
    width: 100%;
    text-align: center;
    margin-bottom: 10px;
  }

  .invoice-logo {
    max-height: 50px;
    max-width: 150px;
  }

  .invoice-title-cell {
    display: block;
    width: 100%;
    text-align: center;
  }

  .invoice-main-title {
    font-size: 14pt;
    margin-bottom: 8px;
  }

  .invoice-ref-badge {
    font-size: 9pt;
    padding: 4px 10px;
    margin-bottom: 10px;
  }

  .invoice-meta-table {
    width: 100%;
    margin: 10px auto 0;
    max-width: 300px;
  }

  .invoice-meta-label,
  .invoice-meta-value {
    text-align: center;
    padding: 4px;
    font-size: 7.5pt;
  }

  /* Bill To / From - Stack vertically on mobile */
  .invoice-billto-table {
    display: block;
    margin-bottom: 15px;
  }

  .invoice-billto-table tr {
    display: block;
  }

  .invoice-billto-cell {
    display: block;
    width: 100% !important;
    margin-bottom: 12px;
  }

  .invoice-spacer-cell {
    display: none;
  }

  .invoice-box-header {
    font-size: 8.5pt;
    padding: 6px 10px;
  }

  .invoice-box-content {
    padding: 10px;
  }

  .invoice-box-name {
    font-size: 9.5pt;
  }

  .invoice-box-details {
    font-size: 7pt;
  }

  /* Products - Hide desktop table, show mobile cards */
  .invoice-products-desktop {
    display: none;
  }

  .invoice-products-mobile {
    display: block;
    margin-bottom: 15px;
  }

  .invoice-products-mobile-header {
    background: #1a56db;
    color: #ffffff;
    padding: 8px 12px;
    font-size: 9pt;
    font-weight: bold;
    text-transform: uppercase;
    border-radius: 4px 4px 0 0;
    margin-bottom: 0;
  }

  .invoice-product-card {
    border: 1px solid #e5e7eb;
    border-top: none;
    background: #ffffff;
    padding: 12px;
  }

  .invoice-product-card:nth-child(even) {
    background: #f9fafb;
  }

  .invoice-product-card:last-child {
    border-radius: 0 0 4px 4px;
  }

  .invoice-product-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e5e7eb;
  }

  .invoice-product-card-name {
    font-size: 9pt;
    font-weight: 600;
    color: #1f2937;
    flex: 1;
    margin-right: 10px;
  }

  .invoice-product-card-total {
    font-size: 10pt;
    font-weight: bold;
    color: #1a56db;
    white-space: nowrap;
  }

  .invoice-product-card-body {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .invoice-product-card-row {
    display: flex;
    font-size: 7.5pt;
    color: #6b7280;
  }

  .invoice-product-card-label {
    font-weight: 600;
    color: #1f2937;
    margin-right: 8px;
    min-width: 50px;
  }

  .invoice-product-card-value {
    color: #6b7280;
  }

  .invoice-product-card-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 6px;
    padding-top: 8px;
    border-top: 1px dashed #e5e7eb;
  }

  .invoice-product-card-detail-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .invoice-product-card-detail-label {
    font-size: 7pt;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
  }

  .invoice-product-card-detail-value {
    font-size: 8pt;
    font-weight: 600;
    color: #1f2937;
  }

  .invoice-product-card-detail-value.discount {
    color: #ef4444;
  }

  /* Summary - Full width on mobile */
  .invoice-summary-wrapper {
    margin-bottom: 15px;
  }

  .invoice-summary-spacer {
    display: none;
  }

  .invoice-summary-cell {
    display: block;
    width: 100% !important;
  }

  .invoice-summary-table {
    width: 100%;
  }

  .invoice-summary-label,
  .invoice-summary-value,
  .invoice-summary-discount-value {
    padding: 6px 10px;
    font-size: 7.5pt;
  }

  .invoice-summary-value,
  .invoice-summary-discount-value {
    text-align: right !important;
  }

  .invoice-summary-total-label,
  .invoice-summary-total-value {
    padding: 8px 10px;
    font-size: 9pt;
  }

  .invoice-summary-total-value {
    text-align: right !important;
  }

  .invoice-summary-paid-label,
  .invoice-summary-paid-value {
    padding: 6px 10px;
    font-size: 8pt;
  }

  .invoice-summary-paid-value {
    text-align: right !important;
  }

  .invoice-summary-due-label,
  .invoice-summary-due-value {
    padding: 6px 10px;
    font-size: 8pt;
  }

  .invoice-summary-due-value {
    text-align: right !important;
  }

  /* Footer */
  .invoice-footer {
    margin-top: 15px;
    padding-top: 12px;
  }

  .invoice-footer-text p {
    font-size: 7pt;
  }

  .invoice-footer-thanks p {
    font-size: 9pt;
  }
}

/* Responsive Design - Small Mobile */
@media (max-width: 576px) {
  .action-btn {
    padding: 0.75rem 0.5rem;
    font-size: 0.75rem;
    min-width: calc(50% - 0.25rem);
  }

  .invoice-print {
    padding: 8px 10px;
    font-size: 7.5pt;
  }

  .invoice-main-title {
    font-size: 12pt;
  }

  .invoice-ref-badge {
    font-size: 8pt;
    padding: 3px 8px;
  }

  .invoice-meta-table {
    max-width: 100%;
  }

  .invoice-meta-label,
  .invoice-meta-value {
    font-size: 7pt;
  }

  .invoice-box-header {
    font-size: 8pt;
    padding: 5px 8px;
  }

  .invoice-box-content {
    padding: 8px;
  }

  .invoice-box-name {
    font-size: 9pt;
  }

  .invoice-box-details {
    font-size: 6.5pt;
  }

  /* Mobile cards on small screens */
  .invoice-products-mobile-header {
    font-size: 8pt;
    padding: 6px 10px;
  }

  .invoice-product-card {
    padding: 10px;
  }

  .invoice-product-card-header {
    margin-bottom: 8px;
    padding-bottom: 6px;
  }

  .invoice-product-card-name {
    font-size: 8.5pt;
  }

  .invoice-product-card-total {
    font-size: 9pt;
  }

  .invoice-product-card-row {
    font-size: 7pt;
  }

  .invoice-product-card-label {
    min-width: 45px;
    font-size: 6.5pt;
  }

  .invoice-product-card-details {
    gap: 6px;
    margin-top: 4px;
    padding-top: 6px;
  }

  .invoice-product-card-detail-label {
    font-size: 6.5pt;
  }

  .invoice-product-card-detail-value {
    font-size: 7.5pt;
  }

  .invoice-summary-label,
  .invoice-summary-value,
  .invoice-summary-discount-value {
    padding: 5px 8px;
    font-size: 7pt;
  }

  .invoice-summary-value,
  .invoice-summary-discount-value {
    text-align: right !important;
  }

  .invoice-summary-total-label,
  .invoice-summary-total-value {
    padding: 7px 8px;
    font-size: 8.5pt;
  }

  .invoice-summary-total-value {
    text-align: right !important;
  }

  .invoice-summary-paid-label,
  .invoice-summary-paid-value,
  .invoice-summary-due-label,
  .invoice-summary-due-value {
    padding: 5px 8px;
    font-size: 7.5pt;
  }

  .invoice-summary-paid-value,
  .invoice-summary-due-value {
    text-align: right !important;
  }
}

/* Print styles for screen preview (also used by print) */

/* Screen-only styles - hide certain elements in print */
@media screen {
  .no-print {
    display: block;
  }
  
  .invoice {
    background: white;
    padding: 20px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
}

@media print {
  .no-print {
    display: none !important;
  }
}

.invoice-print {
  font-family: 'DejaVu Sans', 'Arial', sans-serif;
  font-size: 9pt;
  color: #1f2937;
  line-height: 1.4;
  padding: 15px 20px;
  max-width: 100%;
}

/* Header Section */
.invoice-header-table {
  width: 100%;
  margin-bottom: 12px;
  border-collapse: collapse;
}

.invoice-logo-cell {
  width: 30%;
  vertical-align: top;
}

.invoice-logo {
  max-height: 60px;
  max-width: 180px;
}

.invoice-title-cell {
  width: 70%;
  vertical-align: top;
  text-align: right;
}

.invoice-main-title {
  font-size: 18pt;
  font-weight: bold;
  color: #1a56db;
  margin-bottom: 6px;
  letter-spacing: 0.5px;
}

.invoice-ref-badge {
  display: inline-block;
  background: #f3f4f6;
  padding: 5px 12px;
  border-radius: 4px;
  font-size: 10pt;
  font-weight: bold;
  color: #4b5563;
  margin-bottom: 8px;
}

.invoice-meta-table {
  width: 100%;
  font-size: 8pt;
  margin-top: 6px;
  border-collapse: collapse;
}

.invoice-meta-label {
  text-align: right;
  color: #6b7280;
  font-weight: 600;
  padding: 3px;
}

.invoice-meta-value {
  text-align: right;
  color: #1f2937;
  font-weight: 500;
  padding: 3px;
}

.invoice-status-badge {
  padding: 3px 8px;
  border-radius: 3px;
  font-size: 7pt;
  font-weight: bold;
  text-transform: uppercase;
  display: inline-block;
}

.invoice-status-completed {
  background: #d1fae5;
  color: #065f46;
}

.invoice-status-pending {
  background: #fef3c7;
  color: #92400e;
}

.invoice-status-partial {
  background: #dbeafe;
  color: #1e40af;
}

.invoice-status-default {
  background: #e5e7eb;
  color: #374151;
}

/* Divider */
.invoice-divider {
  height: 2px;
  background: #1a56db;
  margin: 8px 0 10px 0;
}

/* Bill To / From Section */
.invoice-billto-table {
  width: 100%;
  margin-bottom: 12px;
  border-collapse: collapse;
}

.invoice-billto-cell {
  width: 48%;
  vertical-align: top;
}

.invoice-spacer-cell {
  width: 4%;
}

.invoice-box {
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.invoice-box-header {
  background: #1a56db;
  padding: 5px 10px;
  border-bottom: 1px solid #3b82f6;
  color: #ffffff;
  font-size: 9pt;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.invoice-box-content {
  padding: 8px 10px;
  background: #f9fafb;
}

.invoice-box-name {
  font-size: 10pt;
  font-weight: bold;
  color: #1f2937;
  margin-bottom: 4px;
}

.invoice-box-details {
  font-size: 7.5pt;
  color: #6b7280;
  line-height: 1.5;
}

.invoice-box-details div {
  margin-bottom: 2px;
}

.invoice-box-details strong {
  color: #1f2937;
}

/* Products Table */
.invoice-products-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
  border: 1px solid #e5e7eb;
}

.invoice-products-header {
  background: #1a56db;
}

.invoice-products-th-left {
  padding: 6px 5px;
  text-align: left;
  font-size: 8pt;
  font-weight: bold;
  color: #ffffff;
  text-transform: uppercase;
  border-right: 1px solid rgba(255,255,255,0.2);
}

.invoice-products-th-right {
  padding: 6px 5px;
  text-align: right;
  font-size: 8pt;
  font-weight: bold;
  color: #ffffff;
  text-transform: uppercase;
  border-right: 1px solid rgba(255,255,255,0.2);
}

.invoice-products-table thead tr th:last-child {
  border-right: none;
}

.invoice-product-name-cell {
  padding: 5px;
  vertical-align: top;
}

.invoice-product-name {
  font-weight: 600;
  font-size: 8.5pt;
  color: #1f2937;
  margin-bottom: 1px;
}

.invoice-product-code {
  font-size: 7pt;
  color: #6b7280;
}

.invoice-product-imei {
  font-size: 7pt;
  color: #3b82f6;
  margin-top: 1px;
}

.invoice-product-batches {
  margin-top: 4px;
}

.invoice-product-batches-title {
  font-size: 7pt;
  font-weight: 600;
  color: #6366f1;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 2px;
}

.invoice-product-batch-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.invoice-product-batch-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 6px;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 6px;
  font-size: 7.5pt;
  color: #1f2937;
  line-height: 1.3;
}

.invoice-product-batch-chip strong {
  color: #4338ca;
  font-weight: 600;
}

.invoice-product-batch-qty {
  color: #374151;
  font-weight: 500;
}

.invoice-product-card-batches {
  margin: 6px 0;
  padding: 6px 8px;
  background: #f8fafc;
  border-left: 3px solid #6366f1;
  border-radius: 4px;
}

.invoice-product-price-cell {
  padding: 5px;
  text-align: right;
  font-size: 8.5pt;
  color: #1f2937;
}

.invoice-product-discount-cell {
  padding: 5px;
  text-align: right;
  font-size: 8.5pt;
  color: #ef4444;
}

.invoice-product-total-cell {
  padding: 5px;
  text-align: right;
  font-size: 9pt;
  font-weight: bold;
  color: #1a56db;
}

.invoice-products-row-even {
  background: #f9fafb;
}

.invoice-products-table tbody tr {
  border-bottom: 1px solid #e5e7eb;
}

/* Summary Section */
.invoice-summary-wrapper {
  width: 100%;
  margin-bottom: 10px;
  border-collapse: collapse;
}

.invoice-summary-spacer {
  width: 58%;
}

.invoice-summary-cell {
  width: 42%;
  vertical-align: top;
}

.invoice-summary-table {
  width: 100%;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  border-collapse: collapse;
}

.invoice-summary-row {
  background: #ffffff;
  border-bottom: 1px solid #e5e7eb;
}

.invoice-summary-row-alt {
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.invoice-summary-label {
  padding: 5px 10px;
  font-size: 8pt;
  font-weight: 600;
  color: #6b7280;
}

.invoice-summary-value {
  padding: 5px 10px;
  text-align: right !important;
  font-size: 8.5pt;
  font-weight: 600;
  color: #1f2937;
}

.invoice-summary-discount-value {
  padding: 5px 10px;
  text-align: right !important;
  font-size: 8.5pt;
  font-weight: 600;
  color: #ef4444;
}

.invoice-summary-total-row {
  background: #1a56db;
}

.invoice-summary-total-label {
  padding: 8px 10px;
  font-size: 10pt;
  font-weight: bold;
  color: #ffffff;
}

.invoice-summary-total-value {
  padding: 8px 10px;
  text-align: right !important;
  font-size: 11pt;
  font-weight: bold;
  color: #ffffff;
}

.invoice-summary-paid-row {
  background: #d1fae5;
  border-bottom: 1px solid #a7f3d0;
}

.invoice-summary-paid-label {
  padding: 6px 10px;
  font-size: 8.5pt;
  font-weight: bold;
  color: #065f46;
}

.invoice-summary-paid-value {
  padding: 6px 10px;
  text-align: right !important;
  font-size: 9pt;
  font-weight: bold;
  color: #065f46;
}

.invoice-summary-due-row {
  background: #fef3c7;
}

.invoice-summary-due-label {
  padding: 6px 10px;
  font-size: 8.5pt;
  font-weight: bold;
  color: #92400e;
}

.invoice-summary-due-value {
  padding: 6px 10px;
  text-align: right !important;
  font-size: 9pt;
  font-weight: bold;
  color: #92400e;
}

/* Footer */
.invoice-footer {
  margin-top: 15px;
  padding-top: 10px;
  border-top: 2px solid #e5e7eb;
}

.invoice-footer-text {
  padding: 8px 10px;
  background: #f9fafb;
  border-left: 3px solid #1a56db;
  border-radius: 3px;
  margin-bottom: 10px;
}

.invoice-footer-text p {
  font-size: 7.5pt;
  color: #6b7280;
  line-height: 1.5;
  margin: 0;
}

.invoice-footer-thanks {
  text-align: center;
  padding: 8px 0;
}

.invoice-footer-thanks p {
  font-size: 10pt;
  font-weight: bold;
  color: #1a56db;
  margin: 0;
  letter-spacing: 0.3px;
}
</style>

<!-- Non-scoped styles for htmlToPaper - these will be copied to print window -->
<style>
/* Base invoice styles for print window */
#print_Invoice {
  font-family: 'DejaVu Sans', 'Arial', sans-serif;
}

.invoice-print {
  font-family: 'DejaVu Sans', 'Arial', sans-serif;
  font-size: 9pt;
  color: #1f2937;
  line-height: 1.4;
  padding: 15px 20px;
  max-width: 100%;
}

/* Header Section */
.invoice-header-table {
  width: 100%;
  margin-bottom: 12px;
  border-collapse: collapse;
}

.invoice-logo-cell {
  width: 30%;
  vertical-align: top;
}

.invoice-logo {
  max-height: 60px;
  max-width: 180px;
}

.invoice-title-cell {
  width: 70%;
  vertical-align: top;
  text-align: right;
}

.invoice-main-title {
  font-size: 18pt;
  font-weight: bold;
  color: #1a56db;
  margin-bottom: 6px;
  letter-spacing: 0.5px;
}

.invoice-ref-badge {
  display: inline-block;
  background: #f3f4f6;
  padding: 5px 12px;
  border-radius: 4px;
  font-size: 10pt;
  font-weight: bold;
  color: #4b5563;
  margin-bottom: 8px;
}

.invoice-meta-table {
  width: 100%;
  font-size: 8pt;
  margin-top: 6px;
  border-collapse: collapse;
}

.invoice-meta-label {
  text-align: right;
  color: #6b7280;
  font-weight: 600;
  padding: 3px;
}

.invoice-meta-value {
  text-align: right;
  color: #1f2937;
  font-weight: 500;
  padding: 3px;
}

.invoice-status-badge {
  padding: 3px 8px;
  border-radius: 3px;
  font-size: 7pt;
  font-weight: bold;
  text-transform: uppercase;
  display: inline-block;
}

.invoice-status-completed {
  background: #d1fae5;
  color: #065f46;
}

.invoice-status-pending {
  background: #fef3c7;
  color: #92400e;
}

.invoice-status-partial {
  background: #dbeafe;
  color: #1e40af;
}

.invoice-status-default {
  background: #e5e7eb;
  color: #374151;
}

.invoice-divider {
  height: 2px;
  background: #1a56db;
  margin: 8px 0 10px 0;
}

/* Bill To / From Section */
.invoice-billto-table {
  width: 100%;
  margin-bottom: 12px;
  border-collapse: collapse;
}

.invoice-billto-cell {
  width: 48%;
  vertical-align: top;
}

.invoice-spacer-cell {
  width: 4%;
}

.invoice-box {
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.invoice-box-header {
  background: #1a56db;
  padding: 5px 10px;
  border-bottom: 1px solid #3b82f6;
  color: #ffffff;
  font-size: 9pt;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.invoice-box-content {
  padding: 8px 10px;
  background: #f9fafb;
}

.invoice-box-name {
  font-size: 10pt;
  font-weight: bold;
  color: #1f2937;
  margin-bottom: 4px;
}

.invoice-box-details {
  font-size: 7.5pt;
  color: #6b7280;
  line-height: 1.5;
}

.invoice-box-details div {
  margin-bottom: 2px;
}

.invoice-box-details strong {
  color: #1f2937;
}

/* Products Table */
.invoice-products-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
  border: 1px solid #e5e7eb;
}

.invoice-products-header {
  background: #1a56db;
}

.invoice-products-th-left {
  padding: 6px 5px;
  text-align: left;
  font-size: 8pt;
  font-weight: bold;
  color: #ffffff;
  text-transform: uppercase;
  border-right: 1px solid rgba(255,255,255,0.2);
}

.invoice-products-th-right {
  padding: 6px 5px;
  text-align: right;
  font-size: 8pt;
  font-weight: bold;
  color: #ffffff;
  text-transform: uppercase;
  border-right: 1px solid rgba(255,255,255,0.2);
}

.invoice-products-table thead tr th:last-child {
  border-right: none;
}

.invoice-product-name-cell {
  padding: 5px;
  vertical-align: top;
}

.invoice-product-name {
  font-weight: 600;
  font-size: 8.5pt;
  color: #1f2937;
  margin-bottom: 1px;
}

.invoice-product-code {
  font-size: 7pt;
  color: #6b7280;
}

.invoice-product-imei {
  font-size: 7pt;
  color: #3b82f6;
  margin-top: 1px;
}

.invoice-product-batches {
  margin-top: 4px;
}

.invoice-product-batches-title {
  font-size: 7pt;
  font-weight: 600;
  color: #6366f1;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 2px;
}

.invoice-product-batch-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.invoice-product-batch-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 6px;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 6px;
  font-size: 7.5pt;
  color: #1f2937;
  line-height: 1.3;
}

.invoice-product-batch-chip strong {
  color: #4338ca;
  font-weight: 600;
}

.invoice-product-batch-qty {
  color: #374151;
  font-weight: 500;
}

.invoice-product-card-batches {
  margin: 6px 0;
  padding: 6px 8px;
  background: #f8fafc;
  border-left: 3px solid #6366f1;
  border-radius: 4px;
}

.invoice-product-price-cell {
  padding: 5px;
  text-align: right;
  font-size: 8.5pt;
  color: #1f2937;
}

.invoice-product-discount-cell {
  padding: 5px;
  text-align: right;
  font-size: 8.5pt;
  color: #ef4444;
}

.invoice-product-total-cell {
  padding: 5px;
  text-align: right;
  font-size: 9pt;
  font-weight: bold;
  color: #1a56db;
}

.invoice-products-row-even {
  background: #f9fafb;
}

.invoice-products-table tbody tr {
  border-bottom: 1px solid #e5e7eb;
}

/* Summary Section */
.invoice-summary-wrapper {
  width: 100%;
  margin-bottom: 10px;
  border-collapse: collapse;
}

.invoice-summary-spacer {
  width: 58%;
}

.invoice-summary-cell {
  width: 42%;
  vertical-align: top;
}

.invoice-summary-table {
  width: 100%;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  border-collapse: collapse;
}

.invoice-summary-row {
  background: #ffffff;
  border-bottom: 1px solid #e5e7eb;
}

.invoice-summary-row-alt {
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.invoice-summary-label {
  padding: 5px 10px;
  font-size: 8pt;
  font-weight: 600;
  color: #6b7280;
}

.invoice-summary-value {
  padding: 5px 10px;
  text-align: right !important;
  font-size: 8.5pt;
  font-weight: 600;
  color: #1f2937;
}

.invoice-summary-discount-value {
  padding: 5px 10px;
  text-align: right !important;
  font-size: 8.5pt;
  font-weight: 600;
  color: #ef4444;
}

.invoice-summary-total-row {
  background: #1a56db;
}

.invoice-summary-total-label {
  padding: 8px 10px;
  font-size: 10pt;
  font-weight: bold;
  color: #ffffff;
}

.invoice-summary-total-value {
  padding: 8px 10px;
  text-align: right !important;
  font-size: 11pt;
  font-weight: bold;
  color: #ffffff;
}

.invoice-summary-paid-row {
  background: #d1fae5;
  border-bottom: 1px solid #a7f3d0;
}

.invoice-summary-paid-label {
  padding: 6px 10px;
  font-size: 8.5pt;
  font-weight: bold;
  color: #065f46;
}

.invoice-summary-paid-value {
  padding: 6px 10px;
  text-align: right !important;
  font-size: 9pt;
  font-weight: bold;
  color: #065f46;
}

.invoice-summary-due-row {
  background: #fef3c7;
}

.invoice-summary-due-label {
  padding: 6px 10px;
  font-size: 8.5pt;
  font-weight: bold;
  color: #92400e;
}

.invoice-summary-due-value {
  padding: 6px 10px;
  text-align: right !important;
  font-size: 9pt;
  font-weight: bold;
  color: #92400e;
}

/* Footer */
.invoice-footer {
  margin-top: 15px;
  padding-top: 10px;
  border-top: 2px solid #e5e7eb;
}

.invoice-footer-text {
  padding: 8px 10px;
  background: #f9fafb;
  border-left: 3px solid #1a56db;
  border-radius: 3px;
  margin-bottom: 10px;
}

.invoice-footer-text p {
  font-size: 7.5pt;
  color: #6b7280;
  line-height: 1.5;
  margin: 0;
}

.invoice-footer-thanks {
  text-align: center;
  padding: 8px 0;
}

.invoice-footer-thanks p {
  font-size: 10pt;
  font-weight: bold;
  color: #1a56db;
  margin: 0;
  letter-spacing: 0.3px;
}

@media print {
  @page {
    size: A4;
    margin: 10mm 15mm;
  }
  
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }
  
  body {
    font-family: 'DejaVu Sans', 'Arial', sans-serif;
    font-size: 9pt;
    color: #1f2937;
    line-height: 1.4;
    padding: 15px 20px;
    max-width: 100%;
    background: white;
  }
  
  /* Hide everything except the invoice */
  body > *:not(#print_Invoice) {
    display: none !important;
  }
  
  #print_Invoice {
    position: relative;
    width: 100%;
    margin: 0;
    padding: 0;
    background: white;
    display: block !important;
  }
  
  .invoice-print {
    padding: 15px 20px;
    max-width: 100%;
    font-size: 9pt;
    background: white;
    color: #1f2937;
  }
  
  /* Hide non-printable elements */
  .no-print {
    display: none !important;
  }
  
  /* Ensure colors print */
  .invoice-logo,
  .invoice-status-badge,
  .invoice-box-header,
  .invoice-products-header,
  .invoice-summary-total-row,
  .invoice-summary-paid-row,
  .invoice-summary-due-row {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  
  /* Prevent page breaks */
  .invoice-header-table,
  .invoice-billto-table,
  .invoice-products-table,
  .invoice-summary-wrapper,
  .invoice-summary-table {
    page-break-inside: avoid;
  }
  
  .invoice-box,
  .invoice-summary-table tr {
    page-break-inside: avoid;
  }
}
</style>