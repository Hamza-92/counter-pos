<template>
  <div class="main-content">
    <breadcumb :page="$t('ReturnDetail')" :folder="$t('ListReturns')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-if="!isLoading">
      <b-row>
        <b-col md="12" class="mb-5">
          <router-link
            v-if="currentUserPermissions && currentUserPermissions.includes('Sale_Returns_edit')"
            title="Edit"
            class="btn btn-success btn-icon ripple btn-sm"
            :to="'/app/sale_return/edit/'+$route.params.id+'/'+sale_return.sale_id"
          >
            <lucide-icon name="pencil" />
            <span>{{$t('EditReturn')}}</span>
          </router-link>
         
          <button @click="Return_PDF()" class="btn btn-primary btn-icon ripple btn-sm">
            <lucide-icon name="file-text" /> PDF
          </button>
          <button @click="print()" class="btn btn-warning btn-icon ripple btn-sm">
            <lucide-icon name="receipt" />
            {{$t('print')}}
          </button>
          <button
            v-if="currentUserPermissions && currentUserPermissions.includes('Sale_Returns_delete')"
            @click="Delete_Return()"
            class="btn btn-danger btn-icon ripple btn-sm"
          >
            <lucide-icon name="x" />
            {{$t('Del')}}
          </button>
        </b-col>
      </b-row>
      <div class="invoice" id="print_Invoice">
        <div class="invoice-print">
          <b-row class="justify-content-md-center">
            <h4 class="font-weight-bold">{{$t('ReturnDetail')}} : {{sale_return.Ref}}</h4>
          </b-row>
          <hr>
          <b-row class="mt-5">
            <b-col lg="4" md="4" sm="12" class="mb-4">
              <h5 class="font-weight-bold">{{$t('Customer_Info')}}</h5>

              <div>{{sale_return.client_name}}</div>
              <div>{{sale_return.client_email}}</div>
              <div>{{sale_return.client_phone}}</div>
              <div>{{sale_return.client_adr}}</div>
            </b-col>
            <b-col lg="4" md="4" sm="12" class="mb-4">
              <h5 class="font-weight-bold">{{$t('Company_Info')}}</h5>
              <div>{{company.CompanyName}}</div>
              <div>{{company.email}}</div>
              <div>{{company.CompanyPhone}}</div>
              <div>{{company.CompanyAdress}}</div>
            </b-col>
            <b-col lg="4" md="4" sm="12" class="mb-4">
              <h5 class="font-weight-bold">{{$t('Return_Info')}}</h5>

              <div>{{$t('Reference')}} : {{sale_return.Ref}}</div>
              <div>{{$t('Sale_Ref')}} : {{sale_return.sale_ref}}</div>
              <div>
                {{$t('PaymentStatus')}} :
                <span
                  v-if="sale_return.payment_status == 'paid'"
                  class="badge badge-outline-success"
                >{{$t('Paid')}}</span>
                <span
                  v-else-if="sale_return.payment_status == 'partial'"
                  class="badge badge-outline-primary"
                >{{$t('partial')}}</span>
                <span v-else class="badge badge-outline-warning">{{$t('Unpaid')}}</span>
              </div>
              <div>{{$t('warehouse')}} : {{sale_return.warehouse}}</div>
              <div>
                {{$t('Status')}} :
                <span
                  v-if="sale_return.statut == 'received'"
                  class="badge badge-outline-success"
                >{{$t('Received')}}</span>
                <span v-else class="badge badge-outline-info">{{$t('Pending')}}</span>
              </div>
            </b-col>
          </b-row>
          <b-row class="mt-3">
            <b-col md="12">
              <h5 class="font-weight-bold">{{$t('list_product_returns')}}</h5>
              <div class="alert alert-danger">{{$t('products_refunded_alert')}}</div>
              <div class="table-responsive">
                <table class="table table-hover table-md">
                  <thead class="bg-gray-300">
                    <tr>
                      <th scope="col">{{$t('ProductName')}}</th>
                      <th scope="col">{{$t('Net_Unit_Price')}}</th>
                      <th scope="col">{{$t('Qty_return')}}</th>
                      <th scope="col">{{$t('UnitPrice')}}</th>
                      <th scope="col">{{$t('Discount')}}</th>
                      <th scope="col">{{$t('Tax')}}</th>
                      <th scope="col">{{$t('SubTotal')}}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="(detail, dIdx) in details">
                      <tr :key="'r-' + dIdx">
                        <td><span>{{detail.code}} ({{detail.name}})</span>
                          <p v-show="detail.is_imei && detail.imei_number !==null ">{{$t('IMEI_SN')}} : {{detail.imei_number}}</p>
                          <span v-if="detail.is_batch_tracked" class="badge ml-1" style="background:#eef2ff; color:#4f46e5; font-weight:600; letter-spacing:0.3px;">
                            <lucide-icon name="package" style="margin-right:3px;" />{{ $t('Batches') || 'Batches' }}
                          </span>
                        </td>
                        <td>{{currentUser.currency}} {{formatNumber(detail.Net_price,3)}}</td>
                        <td>{{formatNumber(detail.quantity,2)}} {{detail.unit_sale}}</td>
                        <td>{{currentUser.currency}} {{formatNumber(detail.price,2)}}</td>
                        <td>{{currentUser.currency}} {{formatNumber(detail.DiscountNet,2)}}</td>
                        <td>{{currentUser.currency}} {{formatNumber(detail.taxe,2)}}</td>
                        <td>{{currentUser.currency}} {{detail.total.toFixed(2)}}</td>
                      </tr>
                      <tr v-if="detail.is_batch_tracked && (detail.batches || []).length" :key="'b-' + dIdx" style="background:#ffffff;">
                        <td colspan="7" style="padding:0; border-top:0;">
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
                                  <th style="padding:6px 10px; text-align:right; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Quantity') }}</th>
                                  <th style="padding:6px 10px; text-align:right; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Price') || 'Price' }}</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr v-for="(b, bIdx) in detail.batches" :key="'sb-' + dIdx + '-' + bIdx" :style="{ background: bIdx % 2 === 1 ? '#f8faff' : '#ffffff', borderTop: '1px solid #e0e7ff' }">
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
                                    {{ formatNumber(b.qty, 2) }} {{ detail.unit_sale }}
                                  </td>
                                  <td style="padding:6px 10px; text-align:right; color:#4f46e5; font-weight:600;">
                                    <span v-if="b.unit_price != null">{{ currentUser.currency }} {{ formatNumber(b.unit_price, 2) }}</span>
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
            <div class="offset-md-9 col-md-3 mt-4">
              <table class="table table-striped table-sm">
                <tbody>
                  <tr>
                    <td>{{$t('OrderTax')}}</td>
                    <td>
                      <span>{{ formatPriceWithSymbol(currentUser.currency, sale_return.TaxNet, 2) }} ({{formatNumber(sale_return.tax_rate,2)}} %)</span>
                    </td>
                  </tr>
                  <tr>
                    <td>{{$t('Discount')}}</td>
                    <td>{{ formatPriceWithSymbol(currentUser.currency, sale_return.discount, 2) }}</td>
                  </tr>
                  <tr>
                    <td>{{$t('Shipping')}}</td>
                    <td>{{ formatPriceWithSymbol(currentUser.currency, sale_return.shipping, 2) }}</td>
                  </tr>
                  <tr>
                    <td>
                      <span class="font-weight-bold">{{$t('Total')}}</span>
                    </td>
                    <td>
                      <span
                        class="font-weight-bold"
                      >{{ formatPriceWithSymbol(currentUser.currency, sale_return.GrandTotal, 2) }}</span>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <span class="font-weight-bold">{{$t('Paid')}}</span>
                    </td>
                    <td>
                      <span
                        class="font-weight-bold"
                      >{{ formatPriceWithSymbol(currentUser.currency, sale_return.paid_amount, 2) }}</span>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <span class="font-weight-bold">{{$t('Due')}}</span>
                    </td>
                    <td>
                      <span
                        class="font-weight-bold"
                      >{{ formatPriceWithSymbol(currentUser.currency, sale_return.due, 2) }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </b-row>
          <hr v-show="sale_return.note">
          <b-row class="mt-4">
           <b-col md="12">
             <p>{{sale_return.note}}</p>
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
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  computed: mapGetters(["currentUserPermissions", "currentUser"]),
  metaInfo: {
    title: "Detail Sale Return"
  },

  data() {
    return {
      isLoading: true,
      sale_return: {},
      details: [],
      company: {},
      email: {},
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },

  methods: {
    //-----------------------------------  Sale Return PDF -------------------------\\
    Return_PDF() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
     
       axios
        .get(`return_sale_pdf/${id}`, {
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
            "Sale_Return-" + this.sale_return.Ref + ".pdf"
          );
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

    //------------------------------ Print -------------------------\\
    print() {
      this.$htmlToPaper('print_Invoice');
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

    //----------------------------------- Get Details Sale Return ------------------------------\\
    Get_Details() {
      let id = this.$route.params.id;
      axios
        .get(`returns/sale/${id}`)
        .then(response => {
          this.sale_return = response.data.sale_Return;
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

    //---------------------  Delete Return ------------------------\\
    Delete_Return() {
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
            .delete("returns/sale/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              this.$router.push({ name: "index_sale_return" });
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