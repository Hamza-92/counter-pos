<template>
  <div class="main-content">
    <breadcumb :page="$t('Printbarcode')" :folder="$t('Products')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    
    <div v-if="!isLoading" class="barcode-container">
      <b-modal hide-footer id="open_scan" size="md" title="Barcode Scanner">
        <qrcode-scanner
          :qrbox="250" 
          :fps="10" 
          style="width: 100%; height: calc(100vh - 56px);"
          @result="onScan"
        />
      </b-modal>

      <b-row>
        <!-- Configuration Card -->
        <b-col md="12" class="mb-4">
          <b-card class="config-card shadow-sm">
            <b-card-header class="config-header">
              <h5 class="mb-0">
                <lucide-icon class="mr-2" name="settings-2" />
                {{$t('Configuration') || 'Configuration'}}
              </h5>
            </b-card-header>
            <b-card-body>
              <b-row>
                <!-- Warehouse -->
                <b-col md="6" class="mb-3">
                  <validation-observer ref="show_Barcode">
                    <validation-provider name="warehouse" :rules="{ required: true}">
                      <b-form-group slot-scope="{ valid, errors }" :label="$t('warehouse') + ' ' + '*'">
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          @input="Selected_Warehouse"
                          v-model="barcode.warehouse_id"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Warehouse')"
                          :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
                        />
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </validation-observer>
                </b-col>

                <!-- Paper Size -->
                <b-col md="6" class="mb-3">
                  <b-form-group :label="$t('Paper_size')">
                    <v-select
                      v-model="paper_size"
                      @input="Selected_Paper_size"
                      :reduce="label => label.value"
                      :placeholder="$t('Paper_size')"
                      :options="getPaperSizeOptions()"
                    ></v-select>
                  </b-form-group>
                </b-col>

                <!-- Custom Sticker Dimensions -->
                <b-col md="12" v-if="paper_size === 'customstyle' || (paper_size && paper_size.startsWith('sticker_'))" class="mb-3">
                  <b-form-group :label="paper_size === 'customstyle' ? 'Custom Sticker Dimensions' : 'Sticker Dimensions'">
                    <b-row class="custom-dimensions-input">
                      <b-col md="6">
                        <b-form-input
                          v-model.number="custom_sticker_width"
                          type="number"
                          min="1"
                          placeholder="Width"
                          @input="updateCustomStickerLabel"
                          class="form-control"
                          :disabled="paper_size !== 'customstyle'"
                        ></b-form-input>
                        <small class="text-muted">Width (mm)</small>
                      </b-col>
                      <b-col md="6">
                        <b-form-input
                          v-model.number="custom_sticker_height"
                          type="number"
                          min="1"
                          placeholder="Height"
                          @input="updateCustomStickerLabel"
                          class="form-control"
                          :disabled="paper_size !== 'customstyle'"
                        ></b-form-input>
                        <small class="text-muted">Height (mm)</small>
                      </b-col>
                    </b-row>
                    <small v-if="paper_size !== 'customstyle'" class="text-muted d-block mt-2">
                      <lucide-icon class="mr-1" name="info" />
                      Dimensions are preset. Select "Stickers - Custom" to enter custom dimensions.
                    </small>
                  </b-form-group>
                </b-col>

                <!-- Display Price -->
                <b-col md="6" class="mb-3">
                  <div class="psx-form-check modern-checkbox">
                    <input type="checkbox" v-model="show_price" class="psx-checkbox psx-form-check-input" id="show_price">
                    <label class="psx-form-check-label" for="show_price">
                      <span class="checkbox-label">{{$t('Display_Price') || 'Display Price'}}</span>
                    </label>
                  </div>
                </b-col>

                <!-- Auto Print Toggle -->
                <b-col md="6" class="mb-3">
                  <div class="psx-form-check modern-checkbox">
                    <input type="checkbox" v-model="auto_print" class="psx-checkbox psx-form-check-input" id="auto_print">
                    <label class="psx-form-check-label" for="auto_print">
                      <span class="checkbox-label">{{$t('Auto_Print') || 'Auto Print'}}</span>
                    </label>
                  </div>
                </b-col>
              </b-row>
            </b-card-body>
          </b-card>
        </b-col>

        <!-- Product Search Card -->
        <b-col md="12" class="mb-4">
          <b-card class="search-card shadow-sm">
            <b-card-header class="search-header">
              <h5 class="mb-0">
                <lucide-icon class="mr-2" name="search" />
                {{$t('ProductName')}}
              </h5>
            </b-card-header>
            <b-card-body>
              <div id="autocomplete" class="autocomplete">
                <div class="input-with-icon">
                  <button type="button" class="scan-btn" @click="showModal" :title="$t('Scan_Barcode') || 'Scan Barcode'">
                    <lucide-icon name="qr-code" />
                  </button>
                  <input 
                    :placeholder="$t('Scan_Search_Product_by_Code_Name')"
                    @input='e => search_input = e.target.value' 
                    @keyup="search(search_input)"
                    @focus="handleFocus"
                    @blur="handleBlur"
                    ref="product_autocomplete"
                    class="autocomplete-input modern-input" />
                </div>
                <ul class="autocomplete-result-list" v-show="focused">
                  <li class="autocomplete-result" v-for="product_fil in product_filter" @mousedown="SearchProduct(product_fil)">
                    {{getResultValue(product_fil)}}
                  </li>
                </ul>
              </div>
            </b-card-body>
          </b-card>
        </b-col>

        <!-- Products List Card -->
        <b-col md="12" class="mb-4">
          <b-card class="products-card shadow-sm">
            <b-card-header class="products-header d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center">
                <h5 class="mb-0">
                  <lucide-icon class="mr-2" name="package" />
                  {{$t('Selected_Products') || $t('ProductName')}}
                </h5>
                <span class="badge badge-primary ml-2" v-if="products_added.length > 0">{{products_added.length}}</span>
              </div>
              <div class="action-buttons">
                <button @click="reset()" type="button" class="btn btn-outline-danger btn-sm">
                  <lucide-icon class="mr-1" name="power" />
                  {{$t('Reset')}}
                </button>
                <button
                  v-if="ShowCard"
                  @click="print_all_Barcode()"
                  type="button"
                  class="btn btn-sm print-btn"
                >
                  <lucide-icon class="mr-1" name="receipt" />
                  {{$t('print')}}
                </button>
              </div>
            </b-card-header>
            <b-card-body>
              <div class="table-responsive">
                <table class="table table-hover modern-table">
                  <thead>
                    <tr>
                      <th scope="col">{{$t('ProductName')}}</th>
                      <th scope="col">{{$t('CodeProduct')}}</th>
                      <th scope="col" class="text-center">{{$t('Quantity')}}</th>
                      <th scope="col" class="text-center">{{$t('Actions') || 'Actions'}}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="products_added.length === 0">
                      <td colspan="4" class="text-center text-muted py-4">
                        <lucide-icon class="mr-2" name="inbox" />
                        {{$t('NodataAvailable')}}
                      </td>
                    </tr>
                    <tr v-for="product in products_added" :key="product.code" class="product-row">
                      <td class="product-name">{{product.name}}</td>
                      <td class="product-code">{{product.code}}</td>
                      <td class="text-center">
                        <input
                          v-model.number="product.qte"
                          class="form-control quantity-input"
                          type="number"
                          min="1"
                          @input="autoGenerateBarcodes"
                        >
                      </td>
                      <td class="text-center">
                        <button 
                          @click="delete_Product(product.code)" 
                          class="btn btn-sm btn-outline-danger delete-btn"
                          :title="$t('Delete')"
                        >
                          <lucide-icon name="x" />
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </b-card-body>
          </b-card>
        </b-col>

        <!-- Barcode Preview Card -->
        <b-col md="12" v-if="ShowCard">
          <b-card class="preview-card shadow-sm">
            <b-card-header class="preview-header d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center">
                <h5 class="mb-0">
                  <lucide-icon class="mr-2" name="receipt" />
                  {{$t('Barcode_Preview') || 'Barcode Preview'}}
                </h5>
                <span class="badge badge-info ml-2" v-if="pages.length > 0">
                  {{pages.length}} {{$t('Pages') || 'Pages'}}
                </span>
              </div>
              <button
                @click="print_all_Barcode()"
                type="button"
                class="btn btn-sm print-btn-large"
              >
                <lucide-icon class="mr-1" name="receipt" />
                {{$t('print')}}
              </button>
            </b-card-header>
            <b-card-body>
              <div class="barcode-row" id="print_barcode_label">
                <div v-for="(page, pageIndex) in pages" :key="pageIndex">
                  <div :class="class_type_page">
                    <div class="barcode-item" :class="class_sheet" v-for="(barcode, index) in page" :key="index">
                      <div class="head_barcode text-left" style="padding-left: 10px; font-weight: bold;font-size: 10px;">
                        <span class="barcode-name">{{barcode.name}}</span>
                        <span class="barcode-price" v-if="show_price">{{currentUser.currency}} {{barcode.Net_price}}</span>
                      </div>
                      <barcode
                        class="barcode"
                        :format="barcode.Type_barcode"
                        :value="barcode.barcode"
                        textmargin="0"
                        fontoptions="bold"
                        fontSize="15"
                        height="25"
                        width="1"
                      ></barcode>
                    </div>
                  </div>
                </div>
              </div>
            </b-card-body>
          </b-card>
        </b-col>
      </b-row>
    </div>
  </div>
</template>

<script>
import VueBarcode from "vue-barcode";
import NProgress from "nprogress";
import { mapActions, mapGetters } from "vuex";

export default {
  components: {
    barcode: VueBarcode
  },
  data() {
    return {
      focused: false,
      timer:null,
      search_input:'',
      product_filter:[],
      isLoading: true,
      ShowCard: false,
      barcode: {
        product_id: "",
        warehouse_id: "",
        qte: 10
      },
      count: "",
      paper_size:"",
      sheets:'',
      total_a4:'',
      class_sheet:'',
      class_type_page:'',
      rest:'',     
      warehouses: [],
      submitStatus: null,
      show_price:true,
      auto_print: true,
      products_added: [],
      pages: [],
      products: [],
      product: {
        name: "",
        code: "",
        Type_barcode: "",
        barcode:"",
        Net_price:"",
      },
      printTimeout: null,
      isGenerating: false,
      custom_sticker_width: 50,
      custom_sticker_height: 25
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    canGenerateBarcodes() {
      const hasPaperSize = this.paper_size && 
                          (this.sheets > 0 || 
                           this.paper_size === 'customstyle' || 
                           (this.paper_size && this.paper_size.startsWith('sticker_')));
      return this.products_added.length > 0 && 
             hasPaperSize &&
             this.barcode.warehouse_id;
    }
  },

  watch: {
    products_added: {
      handler() {
        if (this.canGenerateBarcodes) {
          this.autoGenerateBarcodes();
        }
      },
      deep: true
    },
    paper_size(newVal, oldVal) {
      // Only regenerate if paper size actually changed and we have the necessary data
      if (newVal !== oldVal && newVal) {
        this.$nextTick(() => {
          if (this.canGenerateBarcodes) {
            this.autoGenerateBarcodes(true); // Skip auto-print when paper size changes via watcher
          } else if (this.products_added.length > 0 && this.barcode.warehouse_id) {
            // Clear view if we can't generate with new paper size
            this.ShowCard = false;
          }
        });
      }
    },
    show_price() {
      if (this.canGenerateBarcodes) {
        this.autoGenerateBarcodes();
      }
    }
  },

  methods: {

    loadPurchaseBarcodes(purchaseId) {
      NProgress.start();
      NProgress.set(0.1);

      axios
        .get("purchases/" + purchaseId + "/barcodes")
        .then(response => {
          const data = response.data || {};

          if (data.warehouse_id) {
            this.barcode.warehouse_id = data.warehouse_id;
          }

          const items = data.products || [];
          this.products_added = items.map(p => ({
            code: p.code,
            barcode: p.barcode,
            name: p.name,
            Type_barcode: p.Type_barcode,
            Net_price: p.Net_price,
            qte: p.qte
          }));

          // Default paper size if not selected
          if (!this.paper_size) {
            this.paper_size = "style40";
            this.Selected_Paper_size("style40");
          } else if (this.paper_size === 'customstyle' || (this.paper_size && this.paper_size.startsWith('sticker_'))) {
            // Apply custom dimensions if sticker style is already selected
            if (this.paper_size.startsWith('sticker_')) {
              const option = this.getPaperSizeOptions().find(opt => opt.value === this.paper_size);
              if (option && option.width && option.height) {
                this.custom_sticker_width = option.width;
                this.custom_sticker_height = option.height;
              }
            }
            this.applyCustomStickerDimensions();
          }

          // Auto-generate will be triggered by watcher
          if (this.canGenerateBarcodes) {
            this.autoGenerateBarcodes();
          }

          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => {
          setTimeout(() => NProgress.done(), 500);
        });
    },

    showModal() {
      this.$bvModal.show('open_scan');
      
    },

    onScan (decodedText, decodedResult) {
      const code = decodedText;
      this.search_input = code;
      this.search();
      this.$bvModal.hide('open_scan');
    },

    Per_Page(){
      this.total_a4 = parseInt(this.barcode.qte/this.sheets);
      this.rest = this.barcode.qte%this.sheets;
    },
 //---------------------- Event Selected_Paper_size------------------------------\\
    Selected_Paper_size(value) {
      if(value == 'style40'){
        this.sheets = 40;
        this.class_sheet = 'style40';
        this.class_type_page = 'barcodea4';
      }else if(value == 'style30'){
        this.sheets = 30;
        this.class_type_page = 'barcode_non_a4';
        this.class_sheet = 'style30';
      }else if(value == 'style24'){
        this.sheets = 24;
        this.class_sheet = 'style24';
       this.class_type_page = 'barcodea4';
      }else if(value == 'style20'){
        this.sheets = 20;
        this.class_sheet = 'style20';
        this.class_type_page = 'barcode_non_a4';
      }else if(value == 'style18'){
        this.sheets =  18;
        this.class_sheet = 'style18';
        this.class_type_page = 'barcodea4';
      }else if(value == 'style14'){
        this.sheets = 14;
        this.class_sheet = 'style14';
        this.class_type_page = 'barcode_non_a4';
      }else if(value == 'style12'){
        this.sheets = 12;
        this.class_sheet = 'style12';
       this.class_type_page = 'barcodea4';
      }else if(value == 'style10'){
        this.sheets = 10;
        this.class_sheet = 'style10';
       this.class_type_page = 'barcode_non_a4';
      }else if(value == 'customstyle'){
        this.sheets = 1;
        this.class_sheet = 'customstyle';
        this.class_type_page = 'barcode_custom';
        // Apply custom dimensions
        this.applyCustomStickerDimensions();
      }else if(value && value.startsWith('sticker_')){
        // Handle predefined sticker sizes
        this.sheets = 1;
        this.class_sheet = 'customstyle';
        this.class_type_page = 'barcode_custom';
        
        // Extract dimensions from option
        const option = this.getPaperSizeOptions().find(opt => opt.value === value);
        if (option && option.width && option.height) {
          this.custom_sticker_width = option.width;
          this.custom_sticker_height = option.height;
          this.applyCustomStickerDimensions();
        }
      }
     
      this.Per_Page();
      
      // Force regeneration when paper size changes (skip auto-print so user can preview first)
      this.$nextTick(() => {
        if (this.canGenerateBarcodes) {
          this.autoGenerateBarcodes(true); // Skip auto-print when paper size changes
        } else if (this.products_added.length > 0 && this.barcode.warehouse_id) {
          // If we have products but can't generate yet, clear the view
          this.ShowCard = false;
        }
      });
    },
    // Get paper size options with dynamic sticker label
    getPaperSizeOptions() {
      const baseOptions = [
        {label: '40 per sheet (a4) (1.799 * 1.003)', value: 'style40'},
        {label: '30 per sheet (2.625 * 1)', value: 'style30'},
        {label: '24 per sheet (a4) (2.48 * 1.334)', value: 'style24'},
        {label: '20 per sheet (4 * 1)', value: 'style20'},
        {label: '18 per sheet (a4) (2.5 * 1.835)', value: 'style18'},
        {label: '14 per sheet (4 * 1.33)', value: 'style14'},
        {label: '12 per sheet (a4) (2.5 * 2.834)', value: 'style12'},
        {label: '10 per sheet (4 * 2)', value: 'style10'},
      ];
      
      // Add sticker size options
      const stickerOptions = [
        {label: 'Stickers - 50mm x 25mm', value: 'sticker_50x25', width: 50, height: 25},
        {label: 'Stickers - 50mm x 30mm', value: 'sticker_50x30', width: 50, height: 30},
        {label: 'Stickers - 53mm x 32mm (Avery 22806)', value: 'sticker_53x32', width: 53, height: 32},
        {label: 'Stickers - 57mm x 32mm', value: 'sticker_57x32', width: 57, height: 32},
        {label: 'Stickers - 63mm x 29mm', value: 'sticker_63x29', width: 63, height: 29},
        {label: 'Stickers - 63mm x 38mm', value: 'sticker_63x38', width: 63, height: 38},
        {label: 'Stickers - 70mm x 36mm', value: 'sticker_70x36', width: 70, height: 36},
        {label: 'Stickers - 70mm x 37mm', value: 'sticker_70x37', width: 70, height: 37},
        {label: 'Stickers - 74mm x 52mm', value: 'sticker_74x52', width: 74, height: 52},
        {label: 'Stickers - 80mm x 50mm', value: 'sticker_80x50', width: 80, height: 50},
        {label: 'Stickers - 100mm x 50mm', value: 'sticker_100x50', width: 100, height: 50},
        {label: 'Stickers - 100mm x 70mm', value: 'sticker_100x70', width: 100, height: 70},
        {label: 'Stickers - 105mm x 37mm', value: 'sticker_105x37', width: 105, height: 37},
        {label: 'Stickers - 105mm x 48mm', value: 'sticker_105x48', width: 105, height: 48},
        {label: 'Stickers - 105mm x 74mm', value: 'sticker_105x74', width: 105, height: 74},
        {label: 'Stickers - 148mm x 105mm (A5)', value: 'sticker_148x105', width: 148, height: 105},
      ];
      
      // Add sticker options to base options
      stickerOptions.forEach(option => {
        baseOptions.push({
          label: option.label,
          value: option.value,
          width: option.width,
          height: option.height
        });
      });
      
      // Add custom sticker option
      baseOptions.push({label: 'Stickers - Custom Value', value: 'customstyle'});
      
      return baseOptions;
    },
    // Update custom sticker label in options
    updateCustomStickerLabel() {
      if (this.paper_size === 'customstyle' || (this.paper_size && this.paper_size.startsWith('sticker_'))) {
        this.applyCustomStickerDimensions();
        if (this.canGenerateBarcodes) {
          this.autoGenerateBarcodes(true); // Skip auto-print when dimensions change
        }
      }
    },
    // Apply custom sticker dimensions to CSS
    applyCustomStickerDimensions() {
      this.$nextTick(() => {
        const styleId = 'custom-sticker-dimensions';
        let styleElement = document.getElementById(styleId);
        
        if (!styleElement) {
          styleElement = document.createElement('style');
          styleElement.id = styleId;
          document.head.appendChild(styleElement);
        }
        
        const widthMM = this.custom_sticker_width || 50;
        const heightMM = this.custom_sticker_height || 25;
        
        // Convert mm to CSS units (1mm = 3.7795275590551px, but we'll use mm directly)
        styleElement.textContent = `
          .barcode_custom {
            width: ${widthMM}mm !important;
            height: ${heightMM}mm !important;
          }
        `;
      });
    },
    //------ Auto Generate Barcodes
    autoGenerateBarcodes(skipAutoPrint = false) {
      if (this.isGenerating) return;
      
      this.isGenerating = true;
      
      // Clear any pending print timeout
      if (this.printTimeout) {
        clearTimeout(this.printTimeout);
        this.printTimeout = null;
      }

      // Use nextTick to ensure DOM updates are complete
      this.$nextTick(() => {
        if (this.canGenerateBarcodes) {
          this.generatePages();
          this.ShowCard = true;
          
          // Auto-print after a short delay if enabled and not skipped
          if (this.auto_print && this.pages.length > 0 && !skipAutoPrint) {
            this.printTimeout = setTimeout(() => {
              this.print_all_Barcode();
            }, 1500);
          }
        } else {
          this.ShowCard = false;
        }
        
        this.isGenerating = false;
      });
    },

    //------ Validate Form (kept for backward compatibility)
    submit() {
      this.$refs.show_Barcode.validate().then(success => {
        if (!success) {
          return;
        } else {
          this.autoGenerateBarcodes();
        }
      });
    },
    //---Validate State Fields
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },
      handleFocus() {
      this.focused = true
    },
    handleBlur() {
      this.focused = false
    },

      //-----------------------------------Delete Product ------------------------------\\
      delete_Product(code) {
      for (var i = 0; i < this.products_added.length; i++) {
        if (code === this.products_added[i].code) {
          this.products_added.splice(i, 1);
          // Auto-regenerate after deletion
          if (this.canGenerateBarcodes) {
            this.autoGenerateBarcodes();
          } else {
            this.ShowCard = false;
          }
          break;
        }
      }
    },
    
   // Search Products
    search(){
      if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
      }
      if (this.search_input.length < 2) {
        return this.product_filter= [];
      }
      if (this.barcode.warehouse_id != "" &&  this.barcode.warehouse_id != null) {
          this.timer = setTimeout(() => {
          const product_filter = this.products.filter(product => product.code === this.search_input || product.barcode.includes(this.search_input));
            if(product_filter.length === 1){
                this.SearchProduct(product_filter[0])
            }else{
              let tokens = this.search_input.toLowerCase().split(' ');
                this.product_filter=  this.products.filter(product => {

                  return tokens.every(token =>
                      product.name.toLowerCase().includes(token) 
                      ||  product.code.toLowerCase().includes(token)
                      ||  product.barcode.toLowerCase().includes(token)
                      ||  (product.note && product.note.toLowerCase().includes(token))
                  );
                // this.product_filter=  this.products.filter(product => {
                //   return (
                //     product.name.toLowerCase().includes(this.search_input.toLowerCase()) ||
                //     product.code.toLowerCase().includes(this.search_input.toLowerCase()) ||
                //     product.barcode.toLowerCase().includes(this.search_input.toLowerCase())
                //     );
                });
                 // Check if product_filter is empty and show alert
                 if (this.product_filter.length <= 0) {
                  this.makeToast(
                    "warning",
                    "Product Not Found",
                    "Warning"
                  );

                }
            }
        }, 800);
      } else {
        this.makeToast(
          "warning",
          this.$t("SelectWarehouse"),
          this.$t("Warning")
        );
      }
    },
    //------ Search Result value
    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },
   
     //------ Submit Search Product
     SearchProduct(result) {
      const existingProduct = this.products_added.find(product => product.code === result.code);

      if (existingProduct) {
        this.makeToast("warning", this.$t("AlreadyAdd"), this.$t("Warning"));
      } else {
        this.products_added.push({
          code: result.code,
          barcode: result.barcode,
          name: result.name,
          Type_barcode: result.Type_barcode,
          Net_price: result.Net_price,
          qte: 1, // Default quantity
        });
        // Auto-generate will be triggered by watcher
      }

      this.search_input = '';
      this.$refs.product_autocomplete.value = "";
      this.product_filter = [];
    },
    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },
    //------------------------------------ Get Products By Warehouse -------------------------\\
    Get_Products_By_Warehouse(id) {
      // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
      axios
        .get("get_Products_by_warehouse/" + id + "?stock=" + 0 + "&product_service=" + 1 + "&product_combo=" + 1)
         .then(response => {
            this.products = response.data;
             NProgress.done();
            })
          .catch(error => {
          });
    },
    //-------------------------------------- Print Barcode -------------------------\\
    print_all_Barcode() {
      var divContents = document.getElementById("print_barcode_label").innerHTML;
      var a = window.open("", "", "height=500, width=500");
      a.document.write(
        '<link rel="stylesheet" href="/assets_setup/css/print_label.css"><html>'
      );
      a.document.write("<body >");
      a.document.write(divContents);
      a.document.write("</body></html>");
      a.document.close();

      setTimeout(() => {
         a.print();
      }, 1000);

      
    },

    generatePages() {
      let allBarcodes = [];
      this.products_added.forEach(product => {
        for (let i = 0; i < product.qte; i++) {
          allBarcodes.push({
            name: product.name,
            barcode: product.barcode,
            Type_barcode: product.Type_barcode,
            Net_price: product.Net_price
          });
        }
      });

      this.pages = [];
      while (allBarcodes.length > 0) {
        this.pages.push(allBarcodes.splice(0, this.sheets));
      }
    },
   
    //-------------------------------------- Show Barcode -------------------------\\
    showBarcode() {
      // this.Per_Page();
      // this.count = this.barcode.qte;
      this.generatePages();
      this.ShowCard = true;
    },
    //---------------------- Event Select Warehouse ------------------------------\\
    Selected_Warehouse(value) {
      this.search_input= '';
      this.product_filter = [];
      this.Get_Products_By_Warehouse(value);
    },
    //----------------------------------- GET Barcode Elements -------------------------\\
    Get_Elements: function() {
      axios
        .get("barcode_create_page")
        .then(response => {
          this.warehouses = response.data.warehouses;
          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },
    //----------------------------------- Reset Data -------------------------\\
    reset() {
      this.ShowCard = false;
      this.products = [];
      this.products_added = [];
      this.product.name = "";
      this.product.code = "";
      this.product.Net_price = "";
      this.barcode.qte = 10;
      this.count = 10;
      this.barcode.warehouse_id = "";
      this.paper_size = "";
      this.sheets = "";
      this.search_input= '';
      if (this.$refs.product_autocomplete) {
        this.$refs.product_autocomplete.value = "";
      }
      this.product_filter = [];
      this.pages = [];
      this.custom_sticker_width = 50;
      this.custom_sticker_height = 25;
      
      // Clear any pending print timeout
      if (this.printTimeout) {
        clearTimeout(this.printTimeout);
        this.printTimeout = null;
      }
      
      // Remove custom style
      const styleElement = document.getElementById('custom-sticker-dimensions');
      if (styleElement) {
        styleElement.remove();
      }
      
      // Reset sheets for sticker sizes
      if (this.paper_size && this.paper_size.startsWith('sticker_')) {
        this.sheets = 1;
      }
    }
  }, //end Methods
  //-----------------------------Created function-------------------
  created: function() {
    this.Get_Elements();

    const purchaseId = this.$route && this.$route.query
      ? this.$route.query.purchase_id
      : null;

    if (purchaseId) {
      this.loadPurchaseBarcodes(purchaseId);
    }
  },
  mounted() {
    // Apply custom dimensions if sticker style is selected on mount
    if (this.paper_size === 'customstyle' || (this.paper_size && this.paper_size.startsWith('sticker_'))) {
      this.$nextTick(() => {
        // If it's a predefined sticker, get dimensions from option
        if (this.paper_size.startsWith('sticker_')) {
          const option = this.getPaperSizeOptions().find(opt => opt.value === this.paper_size);
          if (option && option.width && option.height) {
            this.custom_sticker_width = option.width;
            this.custom_sticker_height = option.height;
          }
        }
        this.applyCustomStickerDimensions();
      });
    }
  }
};
</script>


<style scoped>
  .barcode-container {
    padding: 0;
  }

  /* Card Styles */
  .config-card,
  .search-card,
  .products-card,
  .preview-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
  }

  .config-card:hover,
  .search-card:hover,
  .products-card:hover,
  .preview-card:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
    border-color: #d1d5db;
  }

  .config-header,
  .search-header,
  .products-header,
  .preview-header {
    background: #f9fafb;
    color: #1f2937;
    border-bottom: 1px solid #e5e7eb;
    padding: 1rem 1.5rem;
    border-radius: 8px 8px 0 0;
  }

  .config-header h5,
  .search-header h5,
  .products-header h5,
  .preview-header h5 {
    color: #1f2937;
    font-weight: 600;
    font-size: 1rem;
    margin: 0;
  }

  .config-header h5 i,
  .search-header h5 i,
  .products-header h5 i,
  .preview-header h5 i {
    color: #8b5cf6;
    margin-right: 0.5rem;
  }

  /* Input Styles */
  .input-with-icon {
    display: flex;
    align-items: stretch;
    position: relative;
  }

  .scan-btn {
    background: #8b5cf6;
    border: 1px solid #7c3aed;
    color: white;
    padding: 0.625rem 1rem;
    border-radius: 6px 0 0 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 48px;
  }

  .scan-btn:hover {
    background: #7c3aed;
    border-color: #6d28d9;
  }

  .scan-btn:active {
    transform: scale(0.98);
  }

  .scan-btn i {
    font-size: 1.1rem;
  }

  .modern-input {
    flex: 1;
    padding: 0.625rem 1rem;
    border: 1px solid #d1d5db;
    border-left: none;
    border-radius: 0 6px 6px 0;
    font-size: 0.9375rem;
    transition: all 0.2s ease;
    background: #ffffff;
  }

  .modern-input:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
  }

  .modern-input::placeholder {
    color: #9ca3af;
  }

  /* Autocomplete */
  .autocomplete {
    position: relative;
  }

  .autocomplete-result-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #d1d5db;
    border-top: none;
    border-radius: 0 0 6px 6px;
    max-height: 280px;
    overflow-y: auto;
    z-index: 1000;
    margin-top: -1px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  }

  .autocomplete-result {
    padding: 0.75rem 3rem;
    cursor: pointer;
    transition: all 0.15s ease;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.9375rem;
    color: #374151;
  }

  .autocomplete-result:hover {
    background: #f9fafb;
    color: #1f2937;
  }

  .autocomplete-result:last-child {
    border-bottom: none;
  }

  /* Table Styles */
  .modern-table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
  }

  .modern-table thead th {
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 600;
    color: #374151;
    padding: 0.875rem 1rem;
    font-size: 0.8125rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
  }

  .modern-table tbody tr {
    transition: background-color 0.15s ease;
    border-bottom: 1px solid #f3f4f6;
  }

  .modern-table tbody tr:hover {
    background: #f9fafb;
  }

  .modern-table tbody tr:last-child {
    border-bottom: none;
  }

  .product-row td {
    padding: 1rem;
    vertical-align: middle;
    font-size: 0.9375rem;
  }

  .product-name {
    font-weight: 500;
    color: #1f2937;
  }

  .product-code {
    color: #6b7280;
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
  }

  .quantity-input {
    width: 90px;
    text-align: center;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    transition: all 0.2s ease;
    font-size: 0.9375rem;
  }

  .quantity-input:focus {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    outline: none;
  }

  .delete-btn {
    border: 1px solid #fee2e2;
    background: #fef2f2;
    color: #dc2626;
    padding: 0.5rem 0.75rem;
    transition: all 0.2s ease;
    border-radius: 6px;
    font-size: 0.875rem;
  }

  .delete-btn:hover {
    background: #fee2e2;
    border-color: #fecaca;
    color: #b91c1c;
  }

  /* Checkbox Styles */
  .modern-checkbox {
    display: flex;
    align-items: center;
    padding: 0.875rem 1rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    transition: all 0.2s ease;
  }

  .modern-checkbox:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
  }

  .modern-checkbox input[type="checkbox"] {
    margin-right: 0.75rem;
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #8b5cf6;
  }

  .checkbox-label {
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    font-size: 0.9375rem;
    user-select: none;
  }

  /* Buttons */
  .action-buttons {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .print-btn,
  .print-btn-large {
    background: #8b5cf6;
    border: 1px solid #7c3aed;
    color: white;
    font-weight: 500;
    transition: all 0.2s ease;
  }

  .print-btn:hover,
  .print-btn-large:hover {
    background: #7c3aed;
    border-color: #6d28d9;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(139, 92, 246, 0.2);
  }

  .print-btn:active,
  .print-btn-large:active {
    transform: translateY(0);
  }

  .print-btn-large {
    padding: 0.625rem 1.5rem;
    font-size: 0.9375rem;
  }

  /* Badge Styles */
  .badge {
    padding: 0.375em 0.75em;
    font-weight: 600;
    border-radius: 4px;
    font-size: 0.75rem;
  }

  .badge-primary {
    background: #ede9fe;
    color: #6d28d9;
  }

  .badge-info {
    background: #dbeafe;
    color: #1e40af;
  }

  /* Barcode Preview */
  .barcode-row {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
  }

  /* Form Group Labels */
  ::v-deep .form-group label {
    font-weight: 500;
    color: #374151;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
  }

  /* Card Body */
  ::v-deep .card-body {
    padding: 1.5rem;
  }

  /* Empty State */
  .modern-table tbody tr td.text-center.text-muted {
    padding: 2rem 1rem;
    font-size: 0.9375rem;
  }

  .modern-table tbody tr td.text-center.text-muted i {
    font-size: 2rem;
    opacity: 0.3;
    margin-bottom: 0.5rem;
    display: block;
  }

  /* Responsive */
  @media (max-width: 768px) {
    .action-buttons {
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    .action-buttons button {
      flex: 1;
      min-width: 120px;
    }

    .scan-btn {
      min-width: 44px;
      padding: 0.625rem 0.875rem;
    }

    .products-header,
    .preview-header {
      flex-direction: column;
      align-items: flex-start !important;
      gap: 1rem;
    }

    .products-header .d-flex,
    .preview-header .d-flex {
      width: 100%;
      justify-content: space-between;
    }
  }

  /* Loading State */
  .loading_page {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 400px;
  }

  /* Custom Sticker Dimensions */
  .custom-dimensions-input {
    margin-top: 0.5rem;
  }

  .custom-dimensions-input .form-control {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9375rem;
    transition: all 0.2s ease;
  }

  .custom-dimensions-input .form-control:focus {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    outline: none;
  }

  .custom-dimensions-input .form-control:disabled {
    background-color: #f9fafb;
    border-color: #e5e7eb;
    color: #6b7280;
    cursor: not-allowed;
  }

  .custom-dimensions-input small {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #6b7280;
  }

  .custom-dimensions-input small.text-muted i {
    font-size: 0.875rem;
    margin-right: 0.25rem;
  }

  /* Additional refinements */
  ::v-deep .v-select .vs__dropdown-toggle {
    border-color: #d1d5db;
    border-radius: 6px;
    padding: 0.5rem;
  }

  ::v-deep .v-select .vs__dropdown-toggle:focus-within {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
  }

  ::v-deep .v-select.vs--open .vs__dropdown-toggle {
    border-color: #8b5cf6;
  }
</style>

<!-- Non-scoped dark-mode overrides. The scoped block above gets a
     [data-v-xxxx] attribute on every selector and beats the global
     dark-theme rules in _dark.scss. Re-declare this page's surfaces
     (without `scoped`) so .dark-theme on <body> can reach them. -->
<style>
/* Cards — white bg + light border on every card on the page. */
.dark-theme .barcode-container .config-card,
.dark-theme .barcode-container .search-card,
.dark-theme .barcode-container .products-card,
.dark-theme .barcode-container .preview-card {
  background: #202020;
  border-color: #2a2a2a;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
}
.dark-theme .barcode-container .config-card:hover,
.dark-theme .barcode-container .search-card:hover,
.dark-theme .barcode-container .products-card:hover,
.dark-theme .barcode-container .preview-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.5);
  border-color: #2f2f2f;
}

/* Card headers */
.dark-theme .barcode-container .config-header,
.dark-theme .barcode-container .search-header,
.dark-theme .barcode-container .products-header,
.dark-theme .barcode-container .preview-header {
  background: #292929;
  color: #d8d8d8;
  border-bottom-color: #2a2a2a;
}
.dark-theme .barcode-container .config-header h5,
.dark-theme .barcode-container .search-header h5,
.dark-theme .barcode-container .products-header h5,
.dark-theme .barcode-container .preview-header h5 {
  color: #d8d8d8;
}

/* Search input + autocomplete dropdown */
.dark-theme .barcode-container .modern-input {
  background: #1a1a1a;
  border-color: #2a2a2a;
  color: #d8d8d8;
}
.dark-theme .barcode-container .modern-input::placeholder {
  color: rgba(216, 216, 216, 0.45);
}
.dark-theme .barcode-container .modern-input:focus {
  border-color: #a78bfa;
  box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.18);
}
.dark-theme .barcode-container .autocomplete-result-list {
  background: #202020;
  border-color: #2a2a2a;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.5);
}
.dark-theme .barcode-container .autocomplete-result {
  background: #202020;
  color: #d8d8d8;
  border-bottom-color: #2a2a2a;
}
.dark-theme .barcode-container .autocomplete-result:hover {
  background: rgba(139, 92, 246, 0.18);
  color: #a78bfa;
}

/* Selected-products table */
.dark-theme .barcode-container .modern-table thead th {
  background: #292929;
  color: rgba(216, 216, 216, 0.85);
  border-bottom-color: #2a2a2a;
}
.dark-theme .barcode-container .modern-table tbody tr {
  border-bottom-color: #2a2a2a;
}
.dark-theme .barcode-container .modern-table tbody tr:hover {
  background: rgba(139, 92, 246, 0.08);
}
.dark-theme .barcode-container .product-row td {
  color: #d8d8d8;
}
.dark-theme .barcode-container .product-name {
  color: #d8d8d8;
}
.dark-theme .barcode-container .product-code {
  color: rgba(216, 216, 216, 0.65);
}
.dark-theme .barcode-container .modern-table tbody tr td.text-center.text-muted {
  color: rgba(216, 216, 216, 0.55) !important;
}

/* Quantity input + delete button */
.dark-theme .barcode-container .quantity-input {
  background: #1a1a1a;
  border-color: #2a2a2a;
  color: #d8d8d8;
}
.dark-theme .barcode-container .quantity-input:focus {
  border-color: #a78bfa;
  box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.18);
}
.dark-theme .barcode-container .delete-btn {
  background: rgba(220, 38, 38, 0.12);
  border-color: rgba(220, 38, 38, 0.35);
  color: #f87171;
}
.dark-theme .barcode-container .delete-btn:hover {
  background: rgba(220, 38, 38, 0.22);
  border-color: rgba(220, 38, 38, 0.55);
  color: #fff;
}

/* Modern-checkbox tiles */
.dark-theme .barcode-container .modern-checkbox {
  background: #292929;
  border-color: #2a2a2a;
}
.dark-theme .barcode-container .modern-checkbox:hover {
  background: rgba(139, 92, 246, 0.1);
  border-color: #2f2f2f;
}
.dark-theme .barcode-container .checkbox-label {
  color: #d8d8d8;
}

/* Badges (count badges next to the section titles) */
.dark-theme .barcode-container .badge-primary {
  background: rgba(139, 92, 246, 0.2);
  color: #a78bfa;
}
.dark-theme .barcode-container .badge-info {
  background: rgba(59, 130, 246, 0.2);
  color: #93c5fd;
}

/* Barcode preview surface — keep the printed area readable.
   Use a near-black surface; the inner barcode SVGs render their own
   black bars so they stay visible. */
.dark-theme .barcode-container .barcode-row {
  background: #1a1a1a;
  border-color: #2a2a2a;
}

/* Custom sticker dimensions inputs */
.dark-theme .barcode-container .custom-dimensions-input .form-control {
  background: #1a1a1a;
  border-color: #2a2a2a;
  color: #d8d8d8;
}
.dark-theme .barcode-container .custom-dimensions-input .form-control:focus {
  border-color: #a78bfa;
  box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.18);
}
.dark-theme .barcode-container .custom-dimensions-input .form-control:disabled {
  background: #232323;
  border-color: #2a2a2a;
  color: rgba(216, 216, 216, 0.45);
}
.dark-theme .barcode-container .custom-dimensions-input small,
.dark-theme .barcode-container .custom-dimensions-input small.text-muted {
  color: rgba(216, 216, 216, 0.6);
}

/* Form-group labels — the scoped ::v-deep rule pins them to #374151
   (dark slate), which becomes unreadable on dark. We have to use the
   same ::v-deep route so we can outweigh the scoped specificity. */
.dark-theme .barcode-container >>> .form-group label {
  color: #d8d8d8;
}

/* v-select toggle (scan barcode warehouse + paper size dropdowns) */
.dark-theme .barcode-container >>> .v-select .vs__dropdown-toggle {
  background: #1a1a1a;
  border-color: #2a2a2a;
}
.dark-theme .barcode-container >>> .v-select .vs__selected,
.dark-theme .barcode-container >>> .v-select .vs__search {
  color: #d8d8d8;
}
</style>