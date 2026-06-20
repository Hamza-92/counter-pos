<template>
  <div class="main-content">
    <breadcumb :page="$t('POS_Receipt')" :folder="$t('Settings')" />
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="Submit_Pos_Settings" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Pos_Settings">
        <b-row class="mt-5">
          <b-col lg="12" md="12" sm="12">
            <b-card no-body :header="$t('POS_Receipt')">
              <b-card-body>
                <b-row>
                  <b-col cols="12" class="mb-4">
                    <b-alert show variant="info" class="mb-0">
                      POS receipt configuration – choose a layout and toggle what appears on the printed receipt.
                    </b-alert>
                  </b-col>
                </b-row>

                <b-row>
                  <!-- POS receipt layout selection (preview) -->
                  <b-col lg="12" md="12" sm="12" class="mb-2">
                    <b-form-group label="POS receipt layout">
                      <b-form-radio-group
                        v-model="pos_settings.receipt_layout"
                        :options="[
                          { value: 1, text: 'Layout 1 - Standard' },
                          { value: 2, text: 'Layout 2 - Compact' },
                          { value: 3, text: 'Layout 3 - Detailed' },
                          { value: 4, text: 'Layout 4 - Bilingual (AR+EN)' },
                        ]"
                        buttons
                        button-variant="outline-primary"
                        size="sm"
                      />
                    </b-form-group>
                  </b-col>

                  <!-- Select default POS layout -->
                  <b-col lg="12" md="12" sm="12" class="mb-3">
                    <b-form-group :label="$t('POS_receipt_layout_default')">
                      <b-form-select
                        v-model="pos_settings.receipt_layout"
                        :options="[
                          { value: 1, text: $t('Layout_1_Standard') },
                          { value: 2, text: $t('Layout_2_Compact') },
                          { value: 3, text: $t('Layout_3_Detailed') },
                          { value: 4, text: $t('Layout_4_Bilingual') },
                        ]"
                      />
                    </b-form-group>
                  </b-col>

                  <!-- Live receipt demo -->
                  <b-col lg="12" md="12" sm="12" class="mb-4">
                    <b-card>
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Receipt preview</h6>
                        <b-button size="sm" variant="outline-primary" @click="printPosDemo">
                          <lucide-icon class="mr-1" name="receipt" /> Print demo receipt
                        </b-button>
                      </div>
                      <div class="pos-receipt-demo" id="pos-receipt-demo">
                        <!-- Layout 1 demo (Standard) -->
                        <div v-if="currentReceiptLayout === 1" class="receipt-layout-1">
                          <div class="info text-center mb-2">
                            <div class="invoice_logo mb-1" v-show="pos_settings.show_logo !== 0">
                              <div class="demo-logo-circle">LOGO</div>
                            </div>
                            <div v-show="pos_settings.show_store_name !== 0">Demo Store</div>
                            <small v-show="pos_settings.show_reference !== 0">Ref: REF-12345</small><br v-show="pos_settings.show_reference !== 0">
                            <small v-show="pos_settings.show_address">123 Demo Street</small><br v-show="pos_settings.show_address">
                            <small v-show="pos_settings.show_phone">+123 456 789</small><br v-show="pos_settings.show_phone">
                            <small v-show="pos_settings.show_email">demo@example.com</small>
                            <div class="mt-2">
                              <small v-show="pos_settings.show_date !== 0">Date: 2025-12-10 12:34</small><br>
                              <small v-show="pos_settings.show_seller !== 0">Seller: John Doe</small><br>
                              <small v-show="pos_settings.show_customer">Customer: Jane Smith</small><br>
                              <small v-show="pos_settings.show_Warehouse">Warehouse: Main Store</small>
                            </div>
                          </div>
                          <table class="table_data w-100 mb-2" style="font-size:11px;">
                            <tbody>
                              <tr>
                                <td colspan="3">
                                  Demo Product A<br>
                                  <small>2 x 10.00</small>
                                </td>
                                <td style="text-align:right;">20.00</td>
                              </tr>
                              <tr>
                                <td colspan="3">
                                  Demo Product B<br>
                                  <small>1 x 5.00</small>
                                </td>
                                <td style="text-align:right;">5.00</td>
                              </tr>
                            </tbody>
                          </table>
                          <table class="table_data w-100" style="font-size:11px;">
                            <tbody>
                              <tr>
                                <td class="total">Total</td>
                                <td style="text-align:right;" class="total">25.00</td>
                              </tr>
                              <tr v-show="pos_settings.show_paid !== 0">
                                <td class="total">Paid</td>
                                <td style="text-align:right;" class="total">20.00</td>
                              </tr>
                              <tr v-show="pos_settings.show_due !== 0">
                                <td class="total">Due</td>
                                <td style="text-align:right;" class="total">5.00</td>
                              </tr>
                            </tbody>
                          </table>
                          <table
                            class="table_data w-100 mt-1"
                            style="font-size:11px;"
                            v-show="pos_settings.show_payments !== 0"
                          >
                            <thead>
                              <tr>
                                <th style="text-align:left;">Pay By</th>
                                <th style="text-align:right;">Amount</th>
                                <th style="text-align:right;">Change</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td>Cash</td>
                                <td style="text-align:right;">20.00</td>
                                <td style="text-align:right;">0.00</td>
                              </tr>
                            </tbody>
                          </table>
                          <p class="mt-2 mb-0 text-center" v-show="pos_settings.show_note" style="white-space:pre-line;">
                            <small><strong>{{ pos_settings.note_customer || 'Thank you for your purchase!' }}</strong></small>
                          </p>
                          <div class="mt-2 text-center" v-show="pos_settings.show_zatca_qr !== 0">
                            <div class="zatca-qr">
                              <div class="zatca-qr-title">ZATCA</div>
                              <div class="demo-qr-box"></div>
                            </div>
                          </div>
                          <!-- Barcode from Ref -->
                          <div v-if="pos_settings.show_barcode !== 0" class="mt-2 text-center">
                            <barcode
                              value="REF-12345"
                              format="CODE128"
                              textmargin="0"
                              fontSize="12"
                              height="40"
                              width="1"
                            ></barcode>
                          </div>
                        </div>

                        <!-- Layout 2 demo (Compact) -->
                        <div v-else-if="currentReceiptLayout === 2" class="receipt-layout-2">
                          <div class="info text-center mb-2">
                            <div class="demo-logo-circle small mb-1" v-show="pos_settings.show_logo !== 0">
                              LOGO
                            </div>
                            <div v-show="pos_settings.show_store_name !== 0">Demo Store</div>
                            <small v-show="pos_settings.show_reference !== 0">Ref: REF-12345</small><br v-show="pos_settings.show_reference !== 0">
                            <small v-show="pos_settings.show_address">123 Demo Street</small><br v-show="pos_settings.show_address">
                            <small v-show="pos_settings.show_phone">+123 456 789</small><br v-show="pos_settings.show_phone">
                            <small v-show="pos_settings.show_email">demo@example.com</small>
                            <div class="mt-1">
                              <small v-show="pos_settings.show_date !== 0">Date: 2025-12-10 12:34</small><br>
                              <small v-show="pos_settings.show_seller !== 0">Seller: John Doe</small><br>
                              <small v-show="pos_settings.show_customer">Customer: Jane Smith</small><br>
                              <small v-show="pos_settings.show_Warehouse">Warehouse: Main Store</small>
                            </div>
                          </div>
                          <table class="table_data w-100 mb-2" style="font-size:11px;">
                            <thead>
                              <tr>
                                <th style="text-align:left;">Item</th>
                                <th style="text-align:center;">Qty</th>
                                <th style="text-align:right;">Price</th>
                                <th style="text-align:right;">Total</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td>Demo A</td>
                                <td style="text-align:center;">2</td>
                                <td style="text-align:right;">10.00</td>
                                <td style="text-align:right;">20.00</td>
                              </tr>
                              <tr>
                                <td>Demo B</td>
                                <td style="text-align:center;">1</td>
                                <td style="text-align:right;">5.00</td>
                                <td style="text-align:right;">5.00</td>
                              </tr>
                            </tbody>
                          </table>
                          <table class="table_data w-100" style="font-size:11px;">
                            <tbody>
                              <tr v-show="pos_settings.show_tax">
                                <td class="total">Tax</td>
                                <td style="text-align:right;" class="total">1.25</td>
                              </tr>
                              <tr v-show="pos_settings.show_discount">
                                <td class="total">Discount</td>
                                <td style="text-align:right;" class="total">0.00</td>
                              </tr>
                              <tr v-show="pos_settings.show_shipping">
                                <td class="total">Shipping</td>
                                <td style="text-align:right;" class="total">1.25</td>
                              </tr>
                              <tr>
                                <td class="total">Total</td>
                                <td style="text-align:right;" class="total">25.00</td>
                              </tr>
                              <tr v-show="pos_settings.show_paid !== 0">
                                <td class="total">Paid</td>
                                <td style="text-align:right;" class="total">20.00</td>
                              </tr>
                              <tr v-show="pos_settings.show_due !== 0">
                                <td class="total">Due</td>
                                <td style="text-align:right;" class="total">5.00</td>
                              </tr>
                            </tbody>
                          </table>
                          <table
                            class="table_data w-100 mt-1"
                            style="font-size:11px;"
                            v-show="pos_settings.show_payments !== 0"
                          >
                            <thead>
                              <tr>
                                <th style="text-align:left;">Pay By</th>
                                <th style="text-align:right;">Amount</th>
                                <th style="text-align:right;">Change</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td>Cash</td>
                                <td style="text-align:right;">20.00</td>
                                <td style="text-align:right;">0.00</td>
                              </tr>
                            </tbody>
                          </table>
                          <p class="mt-2 mb-0 text-center" v-show="pos_settings.show_note" style="white-space:pre-line;">
                            <small><strong>{{ pos_settings.note_customer || 'Thank you for your purchase!' }}</strong></small>
                          </p>
                          <div class="mt-2 text-center" v-show="pos_settings.show_zatca_qr !== 0">
                            <div class="zatca-qr">
                              <div class="zatca-qr-title">ZATCA</div>
                              <div class="demo-qr-box"></div>
                            </div>
                          </div>
                          <!-- Barcode from Ref -->
                          <div v-if="pos_settings.show_barcode !== 0" class="mt-2 text-center">
                            <barcode
                              value="REF-12345"
                              format="CODE128"
                              textmargin="0"
                              fontSize="12"
                              height="40"
                              width="1"
                            ></barcode>
                          </div>
                        </div>

                        <!-- Layout 3 demo (Detailed) -->
                        <div v-else-if="currentReceiptLayout === 3" class="receipt-layout-3">
                          <div class="info mb-2">
                            <div class="d-flex justify-content-between">
                              <div>
                                <strong v-show="pos_settings.show_store_name !== 0">Demo Store</strong><br>
                                <small v-show="pos_settings.show_reference !== 0">Ref: REF-12345</small><br v-show="pos_settings.show_reference !== 0">
                                <small v-show="pos_settings.show_address">123 Demo Street</small><br>
                                <small v-show="pos_settings.show_phone">+123 456 789</small>
                              </div>
                              <div class="demo-logo-rect" v-show="pos_settings.show_logo !== 0">LOGO</div>
                            </div>
                            <div class="mt-2" style="font-size:11px;">
                              <div v-show="pos_settings.show_date !== 0">Date: 2025-12-10 12:34</div>
                              <div v-show="pos_settings.show_seller !== 0">Seller: John Doe</div>
                              <div v-show="pos_settings.show_customer">Customer: Jane Smith</div>
                              <div v-show="pos_settings.show_Warehouse">Warehouse: Main Store</div>
                            </div>
                          </div>
                          <table class="table_data w-100 mb-2" style="font-size:11px;">
                            <tbody>
                              <tr>
                                <td>
                                  <strong>Demo Product A</strong><br>
                                  <small>2 x 10.00</small>
                                </td>
                                <td style="text-align:right;">20.00</td>
                              </tr>
                              <tr>
                                <td>
                                  <strong>Demo Product B</strong><br>
                                  <small>1 x 5.00</small>
                                </td>
                                <td style="text-align:right;">5.00</td>
                              </tr>
                            </tbody>
                          </table>
                          <table class="table_data w-100" style="font-size:11px;">
                            <tbody>
                              <tr v-show="pos_settings.show_tax">
                                <td class="total">Tax</td>
                                <td style="text-align:right;" class="total">1.25</td>
                              </tr>
                              <tr v-show="pos_settings.show_discount">
                                <td class="total">Discount</td>
                                <td style="text-align:right;" class="total">0.00</td>
                              </tr>
                              <tr v-show="pos_settings.show_shipping">
                                <td class="total">Shipping</td>
                                <td style="text-align:right;" class="total">1.25</td>
                              </tr>
                              <tr>
                                <td class="total">Total</td>
                                <td style="text-align:right;" class="total">26.25</td>
                              </tr>
                              <tr v-show="pos_settings.show_paid !== 0">
                                <td class="total">Paid</td>
                                <td style="text-align:right;" class="total">25.00</td>
                              </tr>
                              <tr v-show="pos_settings.show_due !== 0">
                                <td class="total">Due</td>
                                <td style="text-align:right;" class="total">1.25</td>
                              </tr>
                            </tbody>
                          </table>
                          <table
                            class="table_data w-100 mt-1"
                            style="font-size:11px;"
                            v-show="pos_settings.show_payments !== 0"
                          >
                            <thead>
                              <tr>
                                <th style="text-align:left;">Pay By</th>
                                <th style="text-align:right;">Amount</th>
                                <th style="text-align:right;">Change</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td>Cash</td>
                                <td style="text-align:right;">25.00</td>
                                <td style="text-align:right;">0.00</td>
                              </tr>
                            </tbody>
                          </table>
                          <p class="mt-2 mb-0 text-center" v-show="pos_settings.show_note" style="white-space:pre-line;">
                            <small><strong>{{ pos_settings.note_customer || 'Thank you for your purchase!' }}</strong></small>
                          </p>
                          <div class="mt-2 text-center" v-show="pos_settings.show_zatca_qr !== 0">
                            <div class="zatca-qr">
                              <div class="zatca-qr-title">ZATCA</div>
                              <div class="demo-qr-box"></div>
                            </div>
                          </div>
                          <!-- Barcode from Ref -->
                          <div v-if="pos_settings.show_barcode !== 0" class="mt-2 text-center">
                            <barcode
                              value="REF-12345"
                              format="CODE128"
                              textmargin="0"
                              fontSize="12"
                              height="40"
                              width="1"
                            ></barcode>
                          </div>
                        </div>

                        <!-- Layout 4 demo (Bilingual AR+EN) -->
                        <div v-else class="receipt-layout-4">
                          <div class="info text-center mb-2">
                            <div class="invoice_logo mb-1" v-show="pos_settings.show_logo !== 0">
                              <div class="demo-logo-circle">LOGO</div>
                            </div>
                            <div>
                              <strong style="font-size:13px;">متجر تجريبي</strong><br>
                              <strong style="font-size:12px;">Demo Store</strong>
                            </div>
                            <div style="font-size:10px;margin-top:2px;">123 Demo Street</div>
                            <div style="font-size:10px;">+123 456 789</div>
                            <div v-show="pos_settings.show_email" style="font-size:10px;">demo@example.com</div>
                            <div v-if="setting.vat_number" style="font-size:11px;font-weight:bold;margin-top:4px;">
                              الرقم الضريبي / TRN : {{setting.vat_number}}
                            </div>
                            <div class="mt-2 mb-2" style="border-top:1px dashed #000;border-bottom:1px dashed #000;padding:4px 0;">
                              <strong>فاتورة ضريبية مبسطة</strong><br>
                              <strong>Simplified Tax Invoice</strong>
                            </div>
                          </div>
                          <div style="font-size:10px;">
                            <div v-show="pos_settings.show_reference !== 0" style="display:flex;justify-content:space-between;">
                              <span>Invoice No</span>
                              <span>REF-12345</span>
                              <span>رقم الفاتورة</span>
                            </div>
                            <div v-show="pos_settings.show_date !== 0" style="display:flex;justify-content:space-between;">
                              <span>Date</span>
                              <span>2025-12-10 12:34</span>
                              <span>تاريخ</span>
                            </div>
                            <div v-show="pos_settings.show_seller !== 0" style="display:flex;justify-content:space-between;">
                              <span>Seller</span>
                              <span>John Doe</span>
                              <span>البائع</span>
                            </div>
                            <div v-show="pos_settings.show_customer" style="display:flex;justify-content:space-between;">
                              <span>Customer</span>
                              <span>Jane Smith</span>
                              <span>العميل</span>
                            </div>
                            <div v-show="pos_settings.show_Warehouse" style="display:flex;justify-content:space-between;">
                              <span>Warehouse</span>
                              <span>Main Store</span>
                              <span>المستودع</span>
                            </div>
                          </div>
                          <table style="width:100%;margin-top:8px;font-size:10px;border-top:1px dashed #000;">
                            <thead>
                              <tr>
                                <th style="text-align:left;padding:4px 0;">Product<br>المنتج</th>
                                <th style="text-align:center;padding:4px 0;">Qty<br>كمية</th>
                                <th style="text-align:center;padding:4px 0;">Rate<br>معدل</th>
                                <th style="text-align:right;padding:4px 0;">Amount<br>مجموع</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr style="border-bottom:1px dashed #eee;">
                                <td>Demo Product A</td>
                                <td style="text-align:center">2</td>
                                <td style="text-align:center">10.00</td>
                                <td style="text-align:right">20.00</td>
                              </tr>
                              <tr style="border-bottom:1px dashed #eee;">
                                <td>Demo Product B</td>
                                <td style="text-align:center">1</td>
                                <td style="text-align:center">5.00</td>
                                <td style="text-align:right">5.00</td>
                              </tr>
                            </tbody>
                          </table>
                          <table style="width:100%;font-size:10px;border-top:1px dashed #000;margin-top:4px;">
                            <colgroup><col style="width:35%"><col style="width:5%"><col style="width:25%"><col style="width:35%"></colgroup>
                            <tbody>
                              <tr>
                                <td style="text-align:left" class="total">Sub Total</td>
                                <td class="total">:</td>
                                <td style="text-align:center" class="total">25.00</td>
                                <td style="text-align:right" class="total">المجموع الفرعي</td>
                              </tr>
                              <tr v-show="pos_settings.show_tax">
                                <td style="text-align:left" class="total">VAT @ Total</td>
                                <td class="total">:</td>
                                <td style="text-align:center" class="total">1.25</td>
                                <td style="text-align:right" class="total">قيمة الضريبة</td>
                              </tr>
                              <tr v-show="pos_settings.show_discount">
                                <td style="text-align:left" class="total">Discount</td>
                                <td class="total">:</td>
                                <td style="text-align:center" class="total">0.00</td>
                                <td style="text-align:right" class="total">تخفيض</td>
                              </tr>
                              <tr v-show="pos_settings.show_shipping">
                                <td style="text-align:left" class="total">Shipping</td>
                                <td class="total">:</td>
                                <td style="text-align:center" class="total">1.25</td>
                                <td style="text-align:right" class="total">الشحن</td>
                              </tr>
                            </tbody>
                          </table>
                          <table style="width:100%;font-size:10px;font-weight:bold;border-top:1px dashed #000;border-bottom:1px dashed #000;margin-top:4px;padding:4px 0;">
                            <colgroup><col style="width:35%"><col style="width:5%"><col style="width:25%"><col style="width:35%"></colgroup>
                            <tbody>
                              <tr>
                                <td style="text-align:left">Grand Total</td>
                                <td>:</td>
                                <td style="text-align:center">26.25</td>
                                <td style="text-align:right">المبلغ الإجمالي</td>
                              </tr>
                            </tbody>
                          </table>
                          <table style="width:100%;font-size:10px;margin-top:4px;">
                            <colgroup><col style="width:35%"><col style="width:5%"><col style="width:25%"><col style="width:35%"></colgroup>
                            <tbody>
                              <tr v-show="pos_settings.show_paid !== 0">
                                <td style="text-align:left"><strong>Paid Amount</strong></td>
                                <td><strong>:</strong></td>
                                <td style="text-align:center">25.00</td>
                                <td style="text-align:right"><strong>المبلغ المدفوع</strong></td>
                              </tr>
                              <tr v-show="pos_settings.show_due !== 0">
                                <td style="text-align:left"><strong>Balance</strong></td>
                                <td><strong>:</strong></td>
                                <td style="text-align:center">1.25</td>
                                <td style="text-align:right"><strong>الرصيد</strong></td>
                              </tr>
                            </tbody>
                          </table>
                          <table style="font-size:10px;width:100%;margin-top:4px;" v-show="pos_settings.show_payments !== 0">
                            <thead>
                              <tr style="background:#eee;">
                                <th style="text-align:left;">Paid By / طريقة الدفع:</th>
                                <th style="text-align:center;">Amount / المبلغ:</th>
                                <th style="text-align:right;">Change / الباقي:</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td style="text-align:left;">Cash</td>
                                <td style="text-align:center;">25.00</td>
                                <td style="text-align:right;">0.00</td>
                              </tr>
                            </tbody>
                          </table>
                          <p class="mt-2 mb-0 text-center" v-show="pos_settings.show_note" style="white-space:pre-line;">
                            <small><strong>{{ pos_settings.note_customer || 'Thank you for your purchase!' }}</strong></small>
                          </p>
                          <div class="mt-2 text-center" v-show="pos_settings.show_zatca_qr !== 0">
                            <div class="zatca-qr">
                              <div class="zatca-qr-title">ZATCA</div>
                              <div class="demo-qr-box"></div>
                            </div>
                          </div>
                          <div v-if="pos_settings.show_barcode !== 0" class="mt-2 text-center">
                            <barcode
                              value="REF-12345"
                              format="CODE128"
                              textmargin="0"
                              fontSize="12"
                              height="40"
                              width="1"
                            ></barcode>
                          </div>
                        </div>
                      </div>
                    </b-card>
                  </b-col>

                  <!-- Note to customer -->
                  <b-col lg="12" md="12" sm="12">
                    <validation-provider
                      name="note"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Note_to_customer') + ' ' + '*'">
                        <textarea
                          :state="getValidationState(validationContext)"
                          aria-describedby="note-feedback"
                          class="form-control"
                          :placeholder="$t('Note_to_customer')"
                          v-model="pos_settings.note_customer"
                          rows="4"
                        ></textarea>
                        <b-form-invalid-feedback id="note-feedback">
                          {{ validationContext.errors[0] }}
                        </b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Receipt-related toggles -->
                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Logo')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_logo"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Store_Name')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_store_name"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Reference')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_reference"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Date')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_date"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Seller')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_seller"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Phone')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_phone"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Address')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_address"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Email')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_email"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Customer')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_customer"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Warehouse')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_Warehouse"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Tax')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_tax"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Discount')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_discount"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Shipping')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_shipping"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_barcode')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_barcode"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Note_to_customer')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_note"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <!-- Show Paid / Due / Payments / ZATCA -->
                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Paid_Line')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_paid"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Due_Line')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_due"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_Payments_Table')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_payments"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <b-col md="4" class="mt-3 mb-3">
                    <label class="switch switch-primary mr-3">
                      {{$t('Show_ZATCA_QR')}}
                      <input
                        type="checkbox"
                        v-model="pos_settings.show_zatca_qr"
                        :true-value="1"
                        :false-value="0"
                      >
                      <span class="slider"></span>
                    </label>
                  </b-col>

                  <!-- Footer: Receipt Paper Size and Logo Size -->
                  <b-col md="12" class="mt-4 mb-3">
                    <hr class="my-4">
                    <h6 class="mb-3">{{$t('Receipt_Settings')}}</h6>
                  </b-col>

                  <!-- Receipt Paper Size -->
                  <b-col md="6" class="mt-3 mb-3">
                    <b-form-group :label="$t('Receipt_Paper_Size')">
                      <b-form-select
                        v-model="pos_settings.receipt_paper_size"
                        :options="[
                          { value: 58, text: $t('Paper_58mm') },
                          { value: 80, text: $t('Paper_80mm') },
                          { value: 88, text: $t('Paper_88mm') },
                        ]"
                      />
                    </b-form-group>
                  </b-col>

                  <!-- Logo Size -->
                  <b-col md="6" class="mt-3 mb-3">
                    <b-form-group :label="$t('Logo_Size')">
                      <b-form-select
                        v-model="logoSizeType"
                        :options="[
                          { value: 'small', text: $t('Small') + ' (40px)' },
                          { value: 'medium', text: $t('Medium') + ' (60px)' },
                          { value: 'large', text: $t('Large') + ' (80px)' },
                          { value: 'custom', text: $t('Custom') },
                        ]"
                      />
                    </b-form-group>
                  </b-col>

                  <!-- Custom Logo Size Input -->
                  <b-col md="6" class="mt-3 mb-3" v-if="logoSizeType === 'custom'">
                    <b-form-group :label="$t('Custom_Logo_Size') + ' (px)'">
                      <b-form-input
                        type="number"
                        v-model="pos_settings.logo_size"
                        placeholder="Enter size in pixels"
                        min="20"
                        max="200"
                      />
                      <small class="text-muted">{{$t('Logo_Size_Description')}}</small>
                    </b-form-group>
                  </b-col>

                  <!-- Submit -->
                  <b-col md="12" class="mt-4">
                    <b-form-group>
                      <b-button variant="primary" type="submit">
                        <lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}
                      </b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              </b-card-body>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import VueBarcode from "vue-barcode";

export default {
  components: {
    barcode: VueBarcode,
  },
  metaInfo: {
    title: "POS Receipt"
  },
  data() {
    return {
      isLoading: true,
      logoSizeType: 'medium', // Track the selected logo size type
      setting: {
        vat_number: '',
      },
      pos_settings: {
        note_customer: "",
        show_logo: "",
        logo_size: 60,
        show_store_name: "",
        show_reference: "",
        show_date: "",
        show_seller: "",
        show_note: "",
        show_barcode: "",
        show_discount: "",
        show_tax: "",
        show_shipping: "",
        show_phone: "",
        show_email: "",
        show_address: "",
        show_customer: "",
        show_Warehouse: "",
        is_printable: "",
        products_per_page: "",
        receipt_layout: 1,
        receipt_paper_size: 80,
        show_paid: "",
        show_due: "",
        show_payments: "",
        show_zatca_qr: "",
        cash_drawer_auto_open: false,
        cash_drawer_printer_name: "",
      }
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),

    // Normalize POS receipt layout selection (1, 2, 3, or 4) for demo preview
    currentReceiptLayout() {
      const raw = this.pos_settings && this.pos_settings.receipt_layout != null
        ? this.pos_settings.receipt_layout
        : 1;
      const n = Number(raw) || 1;
      return [1, 2, 3, 4].includes(n) ? n : 1;
    },
  },

  watch: {
    logoSizeType(newVal) {
      // Watch for changes to logoSizeType and update logo_size accordingly
      this.onLogoSizeTypeChange(newVal);
    }
  },

  methods: {
    ...mapActions(["refreshUserPermissions"]),

    // Handle logo size type change
    onLogoSizeTypeChange(value) {
      // value is already set to logoSizeType via v-model, but we use it to update logo_size
      // Update logo_size based on the selected type
      if (!this.pos_settings) return;
      const selectedValue = value || this.logoSizeType;
      if (selectedValue === 'small') {
        this.pos_settings.logo_size = 40;
      } else if (selectedValue === 'medium') {
        this.pos_settings.logo_size = 60;
      } else if (selectedValue === 'large') {
        this.pos_settings.logo_size = 80;
      }
      // If 'custom', don't change logo_size, let user input handle it
      // But ensure logo_size has a valid value if it's empty
      if (selectedValue === 'custom' && (!this.pos_settings.logo_size || this.pos_settings.logo_size === '')) {
        this.pos_settings.logo_size = 60; // Default to 60 if empty
      }
    },

    //------------- Submit Validation Pos Setting
    Submit_Pos_Settings() {
      this.$refs.Submit_Pos_Settings.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Update_Pos_Settings();
        }
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

    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //---------------------------------- Update_Pos_Settings ----------------\\
    Update_Pos_Settings() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .put("pos_settings/" + this.pos_settings.id, {
          note_customer: this.pos_settings.note_customer,
          show_logo: this.pos_settings.show_logo,
          logo_size: this.pos_settings.logo_size,
          show_store_name: this.pos_settings.show_store_name,
          show_reference: this.pos_settings.show_reference,
          show_date: this.pos_settings.show_date,
          show_seller: this.pos_settings.show_seller,
          show_note: this.pos_settings.show_note,
          show_barcode: this.pos_settings.show_barcode,
          show_discount: this.pos_settings.show_discount,
          show_tax: this.pos_settings.show_tax,
          show_shipping: this.pos_settings.show_shipping,
          show_phone: this.pos_settings.show_phone,
          show_email: this.pos_settings.show_email,
          show_address: this.pos_settings.show_address,
          show_customer: this.pos_settings.show_customer,
          show_Warehouse: this.pos_settings.show_Warehouse,
          is_printable: this.pos_settings.is_printable,
          receipt_paper_size: this.pos_settings.receipt_paper_size,
          show_paid: this.pos_settings.show_paid,
          show_due: this.pos_settings.show_due,
          show_payments: this.pos_settings.show_payments,
          show_zatca_qr: this.pos_settings.show_zatca_qr,
          receipt_layout: this.pos_settings.receipt_layout,
        })
        .then(response => {
          Fire.$emit("Event_Pos_Settings");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    // Print the live POS receipt demo using the same print CSS as real POS receipts
    printPosDemo() {
      try {
        const el = document.getElementById("pos-receipt-demo");
        if (!el) return;
        const divContents = el.innerHTML;
        const w = window.open("", "", "height=600,width=400");
        w.document.write('<html><head>');
        w.document.write('<link rel="stylesheet" href="/css/pos_print.css">');
        w.document.write("</head><body>");
        w.document.write(divContents);
        w.document.write("</body></html>");
        w.document.close();
        setTimeout(() => {
          w.print();
        }, 500);
      } catch (e) {
        // silently ignore print errors in settings preview
      }
    },

    //---------------------------------- Get_Vat_Number (from ZATCA tab) ----------------\\
    get_vat_number() {
      axios
        .get("get_Settings_data_api")
        .then(response => {
          if (response.data && response.data.settings) {
            this.setting.vat_number = response.data.settings.vat_number || '';
          }
        })
        .catch(() => {});
    },

    //---------------------------------- Get_pos_Settings ----------------\\
    get_pos_Settings() {
      axios
        .get("get_pos_Settings")
        .then(response => {
          this.pos_settings = {
            ...this.pos_settings,
            ...(response.data && response.data.pos_settings ? response.data.pos_settings : {}),
          };
          // Ensure logo_size has a default value if not present
          if (this.pos_settings.logo_size === undefined || this.pos_settings.logo_size === null || this.pos_settings.logo_size === '') {
            this.pos_settings.logo_size = 60;
          }
          // Set logoSizeType based on logo_size value
          const size = Number(this.pos_settings.logo_size);
          if (size === 40) {
            this.logoSizeType = 'small';
          } else if (size === 60) {
            this.logoSizeType = 'medium';
          } else if (size === 80) {
            this.logoSizeType = 'large';
          } else {
            this.logoSizeType = 'custom';
          }
          this.isLoading = false;
        })
        .catch(error => {
          this.isLoading = false;
        });
    },
  },

  created() {
    this.get_pos_Settings();
    this.get_vat_number();

    Fire.$on("Event_Pos_Settings", () => {
      this.get_pos_Settings();
      this.get_vat_number();
    });
  }
};
</script>

<style scoped>
.pos-receipt-demo {
  /* Approximate 88mm receipt width at 96dpi: ~332px */
  width: 330px;
  max-width: 100%;
  margin: 0 auto;
  background: #ffffff;
  padding: 10px;
  border: 1px dashed #dee2e6;
  font-size: 11px;
}

.pos-receipt-demo .info {
  text-align: center;
}

.pos-receipt-demo .table_data {
  width: 100%;
}

/* Demo logo styles */
.demo-logo-circle {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: #e9ecef;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: bold;
  color: #6c757d;
}

.demo-logo-circle.small {
  width: 40px;
  height: 40px;
  font-size: 8px;
}

.demo-logo-rect {
  width: 60px;
  height: 40px;
  background: #e9ecef;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 8px;
  font-weight: bold;
  color: #6c757d;
  border-radius: 4px;
}

.demo-qr-box {
  width: 80px;
  height: 80px;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  margin: 0 auto;
}

.zatca-qr {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.zatca-qr-title {
  font-weight: 700;
  font-size: 10px;
  margin-bottom: 4px;
  letter-spacing: 1px;
  text-transform: uppercase;
}

/* Layout 3 specific styles */
.receipt-layout-3 .info {
  text-align: left;
}

.receipt-layout-3 .info .d-flex {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

/* Responsive styles for mobile */
@media (max-width: 768px) {
  /* Make layout radio buttons responsive */
  .form-group {
    width: 100%;
  }

  .btn-group-toggle.btn-group {
    display: flex;
    flex-wrap: wrap;
    width: 100%;
  }

  .btn-group-toggle.btn-group .btn {
    flex: 1;
    min-width: 0;
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .pos-receipt-demo {
    width: 100%;
    padding: 12px;
    font-size: 10px;
  }

  .pos-receipt-demo .table_data {
    font-size: 10px !important;
  }

  .demo-logo-circle {
    width: 50px;
    height: 50px;
    font-size: 9px;
  }

  .demo-logo-circle.small {
    width: 35px;
    height: 35px;
    font-size: 7px;
  }

  .demo-logo-rect {
    width: 50px;
    height: 35px;
    font-size: 7px;
  }

  .demo-qr-box {
    width: 70px;
    height: 70px;
  }

  .zatca-qr-title {
    font-size: 9px;
  }

  /* Make tables horizontally scrollable on mobile if needed */
  .pos-receipt-demo {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .pos-receipt-demo .table_data {
    min-width: 100%;
  }

  .pos-receipt-demo .table_data td,
  .pos-receipt-demo .table_data th {
    white-space: nowrap;
    padding: 2px 4px;
  }

  /* Allow text wrapping for product names */
  .pos-receipt-demo .table_data td:first-child {
    white-space: normal;
    word-wrap: break-word;
  }

  /* Adjust layout 3 header for mobile */
  .receipt-layout-3 .info .d-flex {
    flex-direction: column;
    gap: 8px;
  }

  .receipt-layout-3 .demo-logo-rect {
    align-self: flex-end;
  }
}

@media (max-width: 480px) {
  /* Stack layout buttons vertically on small screens */
  .btn-group-toggle.btn-group {
    flex-direction: column;
  }

  .btn-group-toggle.btn-group .btn {
    width: 100%;
    margin-bottom: 4px;
    border-radius: 0.25rem !important;
  }

  .btn-group-toggle.btn-group .btn:first-child {
    border-top-left-radius: 0.25rem !important;
    border-top-right-radius: 0.25rem !important;
    border-bottom-left-radius: 0.25rem !important;
    border-bottom-right-radius: 0.25rem !important;
  }

  .btn-group-toggle.btn-group .btn:last-child {
    border-bottom-left-radius: 0.25rem !important;
    border-bottom-right-radius: 0.25rem !important;
    margin-bottom: 0;
  }

  .btn-group-toggle.btn-group .btn {
    font-size: 0.8rem;
    padding: 0.375rem 0.5rem;
    white-space: normal;
    word-wrap: break-word;
  }

  .pos-receipt-demo {
    padding: 8px;
    font-size: 9px;
  }

  .pos-receipt-demo .table_data {
    font-size: 9px !important;
  }

  .demo-logo-circle {
    width: 40px;
    height: 40px;
    font-size: 8px;
  }

  .demo-logo-circle.small {
    width: 30px;
    height: 30px;
    font-size: 6px;
  }

  .demo-logo-rect {
    width: 40px;
    height: 30px;
    font-size: 6px;
  }

  .demo-qr-box {
    width: 60px;
    height: 60px;
  }

  .zatca-qr-title {
    font-size: 8px;
  }

  /* Ensure text doesn't overflow */
  .pos-receipt-demo small {
    word-wrap: break-word;
    overflow-wrap: break-word;
  }

  /* Barcode container already handles sizing via max-width: 100% */
}

/* Ensure receipt preview card is responsive */
@media (max-width: 768px) {
  .pos-receipt-demo {
    margin: 0;
  }
}

/* Make sure tables don't break layout on very small screens */
@media (max-width: 360px) {
  .pos-receipt-demo {
    font-size: 8px;
    padding: 6px;
  }

  .pos-receipt-demo .table_data {
    font-size: 8px !important;
  }

  .pos-receipt-demo td,
  .pos-receipt-demo th {
    padding: 2px 4px;
  }
}
</style>


