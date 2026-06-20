<template>
  <div class="main-content product-create-page">
    <breadcumb :page="'Update Product'" :folder="$t('Products')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="Edit_Product" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Product" enctype="multipart/form-data">
        <!-- ========== PAGE HERO ========== -->
        <div class="page-hero">
          <div class="page-hero__main">
            <div class="page-hero__eyebrow">
              <lucide-icon name="package" />
              <span>{{ $t('Products') }}</span>
            </div>
            <h1 class="page-hero__title">{{ $t('UpdateProduct') || 'Update Product' }}</h1>
            <p class="page-hero__subtitle">
              {{ $t('EditProductDetailsHint') || 'Review and update product information, pricing and stock. Changes apply once you save.' }}
            </p>
          </div>
          <div class="page-hero__actions">
            <b-button variant="outline-secondary" class="hero-btn" @click="$router.back()">
              <lucide-icon name="arrow-left" /> {{ $t('Cancel') || 'Cancel' }}
            </b-button>
            <b-button variant="primary" type="submit" class="hero-btn hero-btn--primary" :disabled="SubmitProcessing">
              <lucide-icon name="check" />
              <span>{{ $t('Save_Changes') || $t('submit') }}</span>
            </b-button>
          </div>
        </div>
        <!-- Barcode Scanner Modal -->
        <b-modal hide-footer id="open_scan" size="md" :title="$t('Barcode_Scanner')">
          <qrcode-scanner
            :qrbox="250"
            :fps="10"
            style="width: 100%; height: calc(100vh - 56px);"
            @result="onScan"
          />
        </b-modal>

        <!-- Quick Add Warehouse Location Modal -->
        <validation-observer ref="QuickWarehouseLocation">
          <b-modal
            id="Quick_Add_Warehouse_Location"
            hide-footer
            size="md"
            :title="$t('Add') + ' ' + $t('Warehouse_Location')"
          >
            <b-form @submit.prevent="submitQuickWarehouseLocation">
              <b-row>
                <b-col md="12">
                  <validation-provider name="Warehouse" :rules="{ required: true }" v-slot="v">
                    <b-form-group :label="$t('Warehouse') + ' *'">
                      <b-form-select
                        v-model="quickWarehouseLocation.warehouse_id"
                        :options="warehouses.map(w => ({ value: w.id, text: w.name }))"
                        :state="getValidationState(v)"
                        aria-describedby="QuickWarehouseLocationWarehouse-feedback"
                        :disabled="quickWarehouseLocationWarehouseLocked"
                      />
                      <b-form-invalid-feedback id="QuickWarehouseLocationWarehouse-feedback">
                        {{ v.errors[0] }}
                      </b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <b-col md="12">
                  <validation-provider name="Rack/Location code" :rules="{ required: true }" v-slot="v">
                    <b-form-group :label="$t('Rack_Location_Code') + ' *'">
                      <b-form-input
                        v-model="quickWarehouseLocation.code"
                        :placeholder="$t('Enter_Rack_Location_Code')"
                        :state="getValidationState(v)"
                        aria-describedby="QuickWarehouseLocationCode-feedback"
                      />
                      <b-form-invalid-feedback id="QuickWarehouseLocationCode-feedback">
                        {{ v.errors[0] }}
                      </b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <b-col md="12">
                  <b-form-group :label="$t('Location_Name')">
                    <b-form-input
                      v-model="quickWarehouseLocation.name"
                      :placeholder="$t('Enter_Location_Name')"
                    />
                  </b-form-group>
                </b-col>

                <b-col md="12" class="mt-3">
                  <b-button
                    variant="primary"
                    type="submit"
                    :disabled="quickWarehouseLocationSubmitting"
                  >
                    <lucide-icon class="me-2 font-weight-bold" name="check" /> {{ $t('submit') }}
                  </b-button>
                  <div v-if="quickWarehouseLocationSubmitting" class="spinner-inline">
                    <div class="spinner sm spinner-primary mt-2"></div>
                  </div>
                </b-col>
              </b-row>
            </b-form>
          </b-modal>
        </validation-observer>

        <b-row class="product-create-grid">
          <!-- Main Content Column -->
          <b-col lg="8" class="mb-4 product-create-main">
            <!-- ========== SECTION 1: BASIC INFORMATION ========== -->
            <div class="form-section" id="section-basic">
              <div class="section-header">
                <lucide-icon class="section-icon" name="file" />
                <h4 class="section-title">{{ $t('BasicInformation') }}</h4>
              </div>
              <b-card class="section-card">
                <b-row>
                  <!-- Product Name -->
                  <b-col md="6" class="mb-3">
                    <validation-provider
                      name="Name"
                      :rules="{required:true , min:3 , max:55}"
                      v-slot="validationContext"
                    >
                      <b-form-group>
                        <template #label>
                          <span class="label-with-help">
                            {{ $t('Name_product') }} *
                            <span
                              class="label-help-icon"
                              v-b-tooltip.hover.top
                              :title="$t('ProductNameTooltip')"
                              tabindex="0"
                              role="button"
                              :aria-label="$t('ProductNameTooltip')"
                            >
                              <lucide-icon name="info" />
                            </span>
                          </span>
                        </template>
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Name-feedback"
                          :placeholder="$t('Enter_Name_Product')"
                          v-model="product.name"
                          class="form-control-modern"
                        ></b-form-input>
                        <b-form-invalid-feedback id="Name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Barcode Symbology -->
                  <b-col md="6" class="mb-3">
                    <validation-provider name="Barcode Symbology" :rules="{ required: true}">
                      <b-form-group slot-scope="{ valid, errors }">
                        <template #label>
                          <span class="label-with-help">
                            {{ $t('BarcodeSymbology') }} *
                            <span
                              class="label-help-icon"
                              v-b-tooltip.hover.top
                              :title="$t('BarcodeSymbologyTooltip')"
                              tabindex="0"
                              role="button"
                              :aria-label="$t('BarcodeSymbologyTooltip')"
                            >
                              <lucide-icon name="info" />
                            </span>
                          </span>
                        </template>
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="product.Type_barcode"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Symbology')"
                          :options="[
                            {label: 'Code 128', value: 'CODE128'},
                            {label: 'Code 39', value: 'CODE39'},
                            {label: 'EAN8', value: 'EAN8'},
                            {label: 'EAN13', value: 'EAN13'},
                            {label: 'UPC', value: 'UPC'},
                          ]"
                        ></v-select>
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Product Code -->
                  <b-col md="6" class="mb-3">
                    <validation-provider name="Code Product" :rules="{ required: true}">
                      <b-form-group slot-scope="{ valid, errors }">
                        <template #label>
                          <span class="label-with-help">
                            {{ $t('CodeProduct') }} *
                            <span
                              class="label-help-icon"
                              v-b-tooltip.hover.top
                              :title="$t('ProductCodeTooltip')"
                              tabindex="0"
                              role="button"
                              :aria-label="$t('ProductCodeTooltip')"
                            >
                              <lucide-icon name="info" />
                            </span>
                          </span>
                        </template>
                        <div class="input-group modern-input-group">
                          <div class="input-group-prepend">
                            <button type="button" class="btn-icon-scan" @click="showModal" title="Scan">
                              <img src="/assets_setup/scan.png" alt="Scan" class="scan-icon" />
                            </button>
                          </div>
                          <b-form-input
                            :class="{'is-invalid': !!errors.length}"
                            :state="errors[0] ? false : (valid ? true : null)"
                            aria-describedby="CodeProduct-feedback"
                            type="text"
                            v-model="product.code"
                            :placeholder="$t('Enter_Product_Code')"
                          ></b-form-input>
                          <div class="input-group-append">
                            <button type="button" class="btn-icon-gen" @click="generateNumber()" title="Generate">
                              <lucide-icon name="barcode" />
                            </button>
                          </div>
                        </div>
                        <b-alert
                          show
                          variant="danger"
                          class="mt-2 mb-0"
                          v-if="code_exist !=''"
                        >{{ code_exist }}</b-alert>
                        <b-form-invalid-feedback id="CodeProduct-feedback" v-if="errors[0]">{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Categories (multi-select; first = primary) -->
                  <b-col md="6" class="mb-3">
                    <validation-provider name="category" :rules="{ required: true}">
                      <b-form-group slot-scope="{ valid, errors }">
                        <template #label>
                          <span class="label-with-help">
                            {{ $t('MultiCategoriesLabel') }} *
                            <span
                              class="label-help-icon"
                              v-b-tooltip.hover.top
                              :title="$t('CategoriesMultiTooltip')"
                              tabindex="0"
                              role="button"
                              :aria-label="$t('CategoriesMultiTooltip')"
                            >
                              <lucide-icon name="info" />
                            </span>
                          </span>
                        </template>
                        <b-form-input v-model="product.category_id" class="sr-only" tabindex="-1" aria-hidden="true" />
                        <v-select
                          multiple
                          :close-on-select="false"
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          :reduce="o => o.value"
                          :placeholder="$t('Choose_Category')"
                          v-model="product.assigned_category_ids"
                          :options="categories.map(c => ({ label: c.name, value: c.id }))"
                        />
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Subcategories (multi-select; first = primary) -->
                  <b-col md="6" class="mb-3">
                    <b-form-group>
                      <template #label>
                        <span class="label-with-help">
                          {{ $t('MultiSubcategoriesLabel') }}
                          <span
                            class="label-help-icon"
                            v-b-tooltip.hover.top
                            :title="$t('SubcategoriesMultiTooltip')"
                            tabindex="0"
                            role="button"
                            :aria-label="$t('SubcategoriesMultiTooltip')"
                          >
                            <lucide-icon name="info" />
                          </span>
                        </span>
                      </template>
                      <v-select
                        multiple
                        :close-on-select="false"
                        :reduce="o => o.value"
                        v-model="product.assigned_subcategory_ids"
                        :options="subcategoryOptionsFiltered"
                        :placeholder="$t('Choose_Sub_Category')"
                        :disabled="!(product.assigned_category_ids && product.assigned_category_ids.length)"
                      />
                    </b-form-group>
                  </b-col>

                  <!-- Brand -->
                  <b-col md="6" class="mb-3">
                    <b-form-group>
                      <template #label>
                        <span class="label-with-help">
                          {{ $t('Brand') }}
                          <span
                            class="label-help-icon"
                            v-b-tooltip.hover.top
                            :title="$t('BrandTooltip')"
                            tabindex="0"
                            role="button"
                            :aria-label="$t('BrandTooltip')"
                          >
                            <lucide-icon name="info" />
                          </span>
                        </span>
                      </template>
                      <v-select
                        :placeholder="$t('Choose_Brand')"
                        :reduce="label => label.value"
                        v-model="product.brand_id"
                        :options="brands.map(brands => ({label: brands.name, value: brands.id}))"
                      />
                    </b-form-group>
                  </b-col>

                  <!-- Description -->
                  <b-col md="12" class="mb-3">
                    <b-form-group>
                      <template #label>
                        <span class="label-with-help">
                          {{ $t('Description') }}
                          <span
                            class="label-help-icon"
                            v-b-tooltip.hover.top
                            :title="$t('DescriptionTooltip')"
                            tabindex="0"
                            role="button"
                            :aria-label="$t('DescriptionTooltip')"
                          >
                            <lucide-icon name="info" />
                          </span>
                        </span>
                      </template>
                      <textarea
                        rows="4"
                        class="form-control"
                        :placeholder="$t('Afewwords')"
                        v-model="product.note"
                      ></textarea>
                    </b-form-group>
                  </b-col>
                </b-row>
              </b-card>
            </div>

            <!-- ========== PRODUCT IMAGES GALLERY ========== -->
            <div class="form-section" id="section-gallery">
              <div class="section-header">
                <lucide-icon class="section-icon" name="upload" />
                <h4 class="section-title">{{ $t('ProductImagesGallery') }}</h4>
              </div>
              <b-card class="section-card product-gallery-section-card">
                <b-card-body class="product-gallery-card-body">
                  <p class="product-gallery-intro text-muted small mb-3 mb-md-4">{{ $t('ProductGalleryHint') }}</p>

                  <label
                    class="product-gallery-dropzone mb-3 mb-md-4"
                    :class="{ 'product-gallery-dropzone-disabled': hasPendingGalleryUploads }"
                  >
                    <span class="product-gallery-dropzone-inner">
                      <lucide-icon class="product-gallery-dropzone-icon" name="upload" />
                      <span class="product-gallery-dropzone-title">{{ $t('AddProductImages') }}</span>
                      <span class="product-gallery-dropzone-sub small text-muted">{{ $t('Supported_formats_JPG_PNG_GIF') }}</span>
                    </span>
                    <input
                      type="file"
                      accept="image/*"
                      multiple
                      class="product-gallery-file-native"
                      :disabled="hasPendingGalleryUploads"
                      @change="onGalleryExtraSelected"
                    >
                  </label>

                  <div
                    v-if="!product_images.length"
                    class="product-gallery-empty text-center text-muted small py-4 px-2 rounded"
                  >
                    {{ $t('NoProductImagesYet') }}
                  </div>

                  <draggable
                    v-else
                    v-model="product_images"
                    handle=".gallery-drag-handle"
                    :disabled="hasPendingGalleryUploads"
                    class="product-gallery-draggable"
                    @end="touchGalleryOrder"
                  >
                    <div
                      v-for="(row, idx) in product_images"
                      :key="row._uid || ('id-' + row.id)"
                      class="gallery-item-card d-flex align-items-center"
                      :class="{ 'gallery-item-card--main': row.is_main }"
                    >
                      <span class="gallery-drag-handle flex-shrink-0" title="Reorder">
                        <lucide-icon name="grip-vertical" />
                      </span>
                      <div
                        class="position-relative flex-shrink-0 rounded overflow-hidden gallery-thumb-select"
                        :class="{ 'gallery-thumb-main': row.is_main }"
                        :title="$t('ClickImageToSetMain')"
                        role="button"
                        tabindex="0"
                        @click="setGalleryMain(row)"
                        @keyup.enter="setGalleryMain(row)"
                      >
                        <img
                          :src="row.url || (row.image_path ? '/images/products/' + row.image_path : '')"
                          class="d-block gallery-item-thumb"
                          alt=""
                        >
                      </div>
                      <div class="flex-grow-1 gallery-item-meta ms-2 me-2">
                        <div class="small text-truncate font-weight-medium text-dark">{{ row.image_path }}</div>
                        <div v-if="row.is_main" class="mt-1">
                          <b-badge variant="success" class="gallery-main-badge">{{ $t('MainImage') }}</b-badge>
                        </div>
                      </div>
                      <b-button
                        size="sm"
                        variant="outline-danger"
                        class="flex-shrink-0 gallery-remove-btn"
                        @click="removeGalleryRow(idx)"
                      >
                        <lucide-icon name="x" />
                      </b-button>
                    </div>
                  </draggable>
                </b-card-body>
              </b-card>
            </div>

            <!-- ========== SECTION 2: INVENTORY ========== -->
            <div class="form-section" id="section-inventory">
              <div class="section-header">
                <lucide-icon class="section-icon" name="package" />
                <h4 class="section-title">{{ $t('Inventory') }}</h4>
              </div>
              <b-card class="section-card">
                <b-row>
                  <!-- Product Type (display only) -->
                  <b-col md="6" class="mb-3" v-if="product.type == 'is_single'">
                    <b-form-group :label="$t('type')">
                      <b-form-input value="Standard Product" disabled="disabled"></b-form-input>
                    </b-form-group>
                  </b-col>
                  <b-col md="6" class="mb-3" v-else-if="product.type == 'is_variant'">
                    <b-form-group :label="$t('type')">
                      <b-form-input value="Variable Product" disabled="disabled"></b-form-input>
                    </b-form-group>
                  </b-col>
                  <b-col md="6" class="mb-3" v-else-if="product.type == 'is_service'">
                    <b-form-group :label="$t('type')">
                      <b-form-input value="Service Product" disabled="disabled"></b-form-input>
                    </b-form-group>
                  </b-col>
                  <b-col md="6" class="mb-3" v-else-if="product.type == 'is_combo'">
                    <b-form-group :label="$t('type')">
                      <b-form-input value="Combo Product" disabled="disabled"></b-form-input>
                    </b-form-group>
                  </b-col>

                  <!-- Unit Product -->
                  <b-col md="6" class="mb-3" v-if="product.type != 'is_service'">
                    <validation-provider name="Unit Product" :rules="{ required: true}">
                      <b-form-group
                        slot-scope="{ valid, errors }"
                        :label="$t('UnitProduct') + ' *'"
                      >
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="product.unit_id"
                          @input="Selected_Unit"
                          :placeholder="$t('Choose_Unit_Product')"
                          :reduce="label => label.value"
                          :options="units.map(units => ({label: units.name, value: units.id}))"
                        />
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Unit Sale -->
                  <b-col md="6" class="mb-3" v-if="product.type != 'is_service'">
                    <validation-provider name="Unit Sale" :rules="{ required: true}">
                      <b-form-group
                        slot-scope="{ valid, errors }"
                        :label="$t('UnitSale') + ' *'"
                      >
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="product.unit_sale_id"
                          :placeholder="$t('Choose_Unit_Sale')"
                          :reduce="label => label.value"
                          :options="units_sub.map(units_sub => ({label: units_sub.name, value: units_sub.id}))"
                        />
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Unit Purchase -->
                  <b-col md="6" class="mb-3" v-if="product.type != 'is_service'">
                    <validation-provider name="Unit Purchase" :rules="{ required: true}">
                      <b-form-group
                        slot-scope="{ valid, errors }"
                        :label="$t('UnitPurchase') + ' *'"
                      >
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="product.unit_purchase_id"
                          :placeholder="$t('Choose_Unit_Purchase')"
                          :reduce="label => label.value"
                          :options="units_sub.map(units_sub => ({label: units_sub.name, value: units_sub.id}))"
                        />
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Stock Alert -->
                  <b-col md="6" class="mb-3" v-if="product.type != 'is_service'">
                    <validation-provider
                      name="Stock Alert"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('StockAlert')">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="StockAlert-feedback"
                          type="text"
                          placeholder="0"
                          v-model="product.stock_alert"
                        ></b-form-input>
                        <b-form-invalid-feedback id="StockAlert-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Weight -->
                  <b-col md="6" class="mb-3" v-if="product.type != 'is_service'">
                    <validation-provider
                      name="Weight"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Weight')">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Weight-feedback"
                          type="text"
                          placeholder="0.00"
                          v-model="product.weight"
                        ></b-form-input>
                        <b-form-invalid-feedback id="Weight-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Dimensions (in) -->
                  <b-col md="12" class="mb-2" v-if="product.type != 'is_service'">
                    <h6 class="mb-2">{{ $t('Dimensions_in') }}</h6>
                  </b-col>

                  <!-- Length -->
                  <b-col md="4" class="mb-3" v-if="product.type != 'is_service'">
                    <validation-provider
                      name="Length"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Length')">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Length-feedback"
                          type="text"
                          placeholder="0.00"
                          v-model="product.length"
                        ></b-form-input>
                        <b-form-invalid-feedback id="Length-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Width -->
                  <b-col md="4" class="mb-3" v-if="product.type != 'is_service'">
                    <validation-provider
                      name="Width"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Width')">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Width-feedback"
                          type="text"
                          placeholder="0.00"
                          v-model="product.width"
                        ></b-form-input>
                        <b-form-invalid-feedback id="Width-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Height -->
                  <b-col md="4" class="mb-3" v-if="product.type != 'is_service'">
                    <validation-provider
                      name="Height"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Height')">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Height-feedback"
                          type="text"
                          placeholder="0.00"
                          v-model="product.height"
                        ></b-form-input>
                        <b-form-invalid-feedback id="Height-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>
                </b-row>
              </b-card>
            </div>

            <!-- ========== SECTION 3: VARIANTS (if applicable) ========== -->
            <div class="form-section" id="section-variants" v-if="product.type == 'is_variant'">
              <div class="section-header">
                <lucide-icon class="section-icon" name="settings" />
                <h4 class="section-title">{{ $t('Variants') }}</h4>
              </div>
              <b-card class="section-card">
                <div class="variant-input-group mb-3">
                  <b-form-group>
                    <b-input-group>
                      <b-form-input
                        :placeholder="$t('Enter_the_Variant')"
                        v-model="tag"
                        class="form-control-modern"
                      ></b-form-input>
                      <b-input-group-append>
                        <b-button variant="primary" @click="add_variant(tag)">
                          <lucide-icon class="me-2" name="plus" />{{ $t('Add') }}
                        </b-button>
                      </b-input-group-append>
                    </b-input-group>
                  </b-form-group>
                </div>

                <div class="table-responsive" v-if="variants.length > 0">
                  <table class="table table-hover table-modern">
                    <thead>
                      <tr>
                        <th>{{ $t('Code') }}</th>
                        <th>{{ $t('Name') }}</th>
                        <th>{{ $t('Cost') }}</th>
                        <th>{{ $t('Retail Price') }}</th>
                        <th>{{ $t('Wholesale_Price') }}</th>
                        <th>{{ $t('Min_Selling_Price') }}</th>
                        <th class="text-center" style="width: 50px;"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="variant in variants" :key="variant.var_id">
                        <td><b-form-input v-model="variant.code" type="text" size="sm"></b-form-input></td>
                        <td><b-form-input v-model="variant.text" type="text" size="sm"></b-form-input></td>
                        <td><b-form-input v-model="variant.cost" type="text" size="sm"></b-form-input></td>
                        <td><b-form-input v-model="variant.price" type="text" size="sm"></b-form-input></td>
                        <td><b-form-input v-model="variant.wholesale" type="text" size="sm"></b-form-input></td>
                        <td><b-form-input v-model="variant.min_price" type="text" size="sm"></b-form-input></td>
                        <td class="text-center">
                          <b-button
                            variant="danger"
                            size="sm"
                            @click="delete_variant(variant.var_id)"
                            title="Delete"
                          >
                            <lucide-icon name="x" />
                          </b-button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div v-else class="alert alert-info">
                  {{ $t('NodataAvailable') }}
                </div>
              </b-card>
            </div>

            <!-- ========== SECTION 4: PRICING & TAX ========== -->
            <div class="form-section" id="section-pricing">
              <div class="section-header">
                <lucide-icon class="section-icon" name="tag" />
                <h4 class="section-title">{{ $t('PricingAndTax') }}</h4>
              </div>
              <b-card class="section-card">
                <b-row>
                  <!-- Product Cost -->
                  <b-col md="6" class="mb-3" v-if="product.type == 'is_single' || product.type == 'is_combo'">
                    <validation-provider
                      name="Product Cost"
                      :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('ProductCost') + ' *'">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="ProductCost-feedback"
                          type="text"
                          placeholder="0.00"
                          v-model="product.cost"
                        ></b-form-input>
                        <b-form-invalid-feedback id="ProductCost-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Product Price -->
                  <b-col
                    md="6"
                    class="mb-2"
                    v-if="product.type == 'is_single' || product.type == 'is_service' || product.type == 'is_combo'"
                  >
                    <validation-provider
                      name="Product Price"
                      :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Retail Price') + ' *'">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="ProductPrice-feedback"
                          label="Price"
                          :placeholder="$t('Enter_Product_Price')"
                          v-model="product.price"
                        ></b-form-input>

                        <b-form-invalid-feedback id="ProductPrice-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Wholesale Price (optional) -->
                  <b-col
                    md="6"
                    class="mb-2"
                    v-if="product.type == 'is_single' || product.type == 'is_service' || product.type == 'is_combo'"
                  >
                    <validation-provider
                      name="Wholesale Price"
                      :rules="{ regex: /^\d*\.?\d*$/ }"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Wholesale_Price')">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="WholesalePrice-feedback"
                          :placeholder="$t('Enter_Wholesale_Price')"
                          v-model="product.wholesale_price"
                        ></b-form-input>

                        <b-form-invalid-feedback id="WholesalePrice-feedback">
                          {{ validationContext.errors[0] }}
                        </b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Minimum Selling Price (optional) -->
                  <b-col
                    md="6"
                    class="mb-2"
                    v-if="product.type == 'is_single' || product.type == 'is_service' || product.type == 'is_combo'"
                  >
                    <validation-provider
                      name="Minimum Selling Price"
                      :rules="{ regex: /^\d*\.?\d*$/ }"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Minimum_Selling_Price')">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="MinPrice-feedback"
                          :placeholder="$t('Enter_Minimum_Selling_Price')"
                          v-model="product.min_price"
                        ></b-form-input>

                        <b-form-invalid-feedback id="MinPrice-feedback">
                          {{ validationContext.errors[0] }}
                        </b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Tax Rate -->
                  <b-col md="6" class="mb-3">
                    <validation-provider
                      name="Order Tax"
                      :rules="{regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('OrderTax')">
                        <b-input-group append="%">
                          <b-form-input
                            :state="getValidationState(validationContext)"
                            aria-describedby="OrderTax-feedback"
                            type="text"
                            placeholder="0"
                            v-model.number="product.TaxNet"
                          ></b-form-input>
                        </b-input-group>
                        <b-form-invalid-feedback id="OrderTax-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Tax Method -->
                  <b-col md="6" class="mb-3">
                    <validation-provider name="Tax Method" :rules="{ required: true}">
                      <b-form-group
                        slot-scope="{ valid, errors }"
                        :label="$t('TaxMethod') + ' *'"
                      >
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="product.tax_method"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Method')"
                          :options="[
                            {label: 'Exclusive', value: '1'},
                            {label: 'Inclusive', value: '2'}
                          ]"
                        ></v-select>
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Discount Method -->
                  <b-col md="6" class="mb-3">
                    <validation-provider name="Discount Method" :rules="{ required: true}">
                      <b-form-group slot-scope="{ valid, errors }" :label="$t('Discount_Method') + ' *'">
                        <v-select
                          v-model="product.discount_method"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Method')"
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          :options="[
                            {label: 'Percent %', value: '1'},
                            {label: 'Fixed', value: '2'}
                          ]"
                        ></v-select>
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Discount Rate -->
                  <b-col md="6" class="mb-3">
                    <validation-provider
                      name="Discount Rate"
                      :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Discount')">
                        <b-form-input
                          v-model.number="product.discount"
                          :state="getValidationState(validationContext)"
                          aria-describedby="Discount-feedback"
                          type="text"
                          placeholder="0.00"
                        ></b-form-input>
                        <b-form-invalid-feedback id="Discount-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Points -->
                  <b-col md="6" class="mb-3">
                    <validation-provider
                      name="Points"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Points')">
                        <b-form-input
                          v-model.number="product.points"
                          :state="getValidationState(validationContext)"
                          aria-describedby="Points-feedback"
                          type="text"
                          placeholder="0"
                        ></b-form-input>
                        <b-form-invalid-feedback id="Points-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>
                </b-row>
              </b-card>
            </div>

            <!-- ========== SECTION 5: COMBO PRODUCTS ========== -->
            <div class="form-section" id="section-combo" v-if="product.type == 'is_combo'">
              <div class="section-header">
                <lucide-icon class="section-icon" name="shopping-bag" />
                <h4 class="section-title">{{ $t('ComboProducts') }}</h4>
              </div>
              <b-card class="section-card">
                <div class="combo-search mb-3">
                  <b-form-group :label="$t('SearchProduct')">
                    <div class="autocomplete">
                      <input  
                        :placeholder="$t('Scan_Search_Product_by_Code_Name')"
                        @input='e => search_input = e.target.value'
                        @keyup="search(search_input)"
                        @focus="handleFocus"
                        @blur="handleBlur"
                        ref="product_autocomplete"
                        class="autocomplete-input form-control"
                      />
                      <ul class="autocomplete-result-list" v-show="focused">
                        <li class="autocomplete-result" v-for="product_fil in product_filter" :key="product_fil.id"
                            @mousedown="SearchProduct(product_fil)">{{ getResultValue(product_fil) }}</li>
                      </ul>
                    </div>
                  </b-form-group>
                </div>

                <div class="table-responsive">
                  <table class="table table-hover table-modern">
                    <thead>
                      <tr>
                        <th>{{ $t('ProductName') }}</th>
                        <th>{{ $t('Quantity') }}</th>
                        <th class="text-right">{{ $t('Cost') }}</th>
                        <th class="text-right">{{ $t('SubTotal') }}</th>
                        <th class="text-center" style="width: 50px;"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="materiels.length <= 0">
                        <td colspan="5" class="text-center text-muted">{{ $t('NodataAvailable') }}</td>
                      </tr>
                      <tr v-for="materiel in materiels" :key="materiel.product_id">
                        <td>
                          <div class="badge-wrapper">
                            <span class="badge badge-primary-light">{{ materiel.name }}</span>
                            <br>
                            <small class="text-muted">{{ materiel.code }}</small>
                          </div>
                        </td>
                        <td>
                          <b-input-group :append="materiel.unit_name">
                            <b-form-input 
                              v-model.number="materiel.quantity"
                              type="text"
                              min="1"
                              size="sm"
                              style="width: 60px;"
                            ></b-form-input>
                          </b-input-group>
                        </td>
                        <td class="text-right">{{ currentUser.currency }} {{ materiel.cost }}</td>
                        <td class="text-right font-weight-bold">{{ currentUser.currency }} {{ formatNumber(materiel.cost * materiel.quantity, 2) }}</td>
                        <td class="text-center">
                          <b-button
                            variant="danger"
                            size="sm"
                            @click="delete_materiel(materiel.product_id)"
                            title="Delete"
                          >
                            <lucide-icon name="x" />
                          </b-button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="combo-total mt-3" v-if="materiels.length > 0">
                  <div class="total-row">
                    <span class="total-label">{{ $t('TotalCost') }}</span>
                    <span class="total-value">{{ currentUser.currency }} {{ formatNumber(totalCost, 2) }}</span>
                  </div>
                </div>
              </b-card>
            </div>

            <!-- ========== SECTION 6: WARRANTY ========== -->
            <div class="form-section" id="section-warranty">
              <div class="section-header">
                <lucide-icon class="section-icon" name="shield" />
                <h4 class="section-title">{{ $t('Warranty_Guarantee_Tracking') }}</h4>
              </div>
              <b-card class="section-card">
                <b-row>
                  <!-- Warranty Period -->
                  <b-col md="6" class="mb-3">
                    <b-form-group :label="$t('Warranty_Period')">
                      <b-input-group>
                        <b-form-input
                          type="text"
                          placeholder="0"
                          v-model="product.warranty_period"
                        ></b-form-input>
                        <b-form-select
                          v-model="product.warranty_unit"
                          :options="[
                            { value: 'days', text: $t('Days') },
                            { value: 'months', text: $t('Months') },
                            { value: 'years', text: $t('Years') }
                          ]"
                        ></b-form-select>
                      </b-input-group>
                    </b-form-group>
                  </b-col>

                  <!-- Guarantee Toggle -->
                  <b-col md="6" class="mb-3">
                    <b-form-group>
                      <b-form-checkbox
                        v-model="product.has_guarantee"
                        :unchecked-value="false"
                        :checked-value="true"
                        switch
                      >
                        {{ $t('HasGuarantee') }}
                      </b-form-checkbox>
                    </b-form-group>
                  </b-col>

                  <!-- Warranty Terms -->
                  <b-col md="12" class="mb-3">
                    <b-form-group :label="$t('WarrantyTerms')">
                      <b-form-textarea
                        :placeholder="$t('Enter_warranty_terms')"
                        rows="3"
                        v-model="product.warranty_terms"
                      ></b-form-textarea>
                    </b-form-group>
                  </b-col>

                  <!-- Guarantee Period -->
                  <b-col md="6" class="mb-3" v-if="product.has_guarantee">
                    <b-form-group :label="$t('Guarantee_Period')">
                      <b-input-group>
                        <b-form-input
                          type="text"
                          placeholder="0"
                          v-model="product.guarantee_period"
                        ></b-form-input>
                        <b-form-select
                          v-model="product.guarantee_unit"
                          :options="[
                            { value: 'days', text: $t('Days') },
                            { value: 'months', text: $t('Months') },
                            { value: 'years', text: $t('Years') }
                          ]"
                        ></b-form-select>
                      </b-input-group>
                    </b-form-group>
                  </b-col>
                </b-row>
              </b-card>
            </div>

            <!-- ========== SECTION 7: WAREHOUSE LOCATION (DEFAULT HINT) ========== -->
            <div class="form-section" id="section-location">
              <div class="section-header">
                <lucide-icon class="section-icon" name="map-pin" />
                <h4 class="section-title">{{ $t('Internal_Location_Rack_Shelf') }}</h4>
              </div>
              <b-card class="section-card">
                <p class="text-muted mb-3">
                  {{ $t('Warehouse_Location_Optional_Hint') }}
                </p>
                <b-row>
                  <b-col md="6" class="mb-3" v-for="wh in warehouses" :key="wh.id">
                    <b-form-group :label="wh.name">
                      <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                          <v-select
                            :options="locationsByWarehouse[wh.id] || []"
                            label="label"
                            :reduce="o => o.id"
                            :placeholder="$t('Choose')"
                            v-model="warehouse_location_map[wh.id]"
                          />
                        </div>
                        <b-button
                          variant="outline-primary"
                          class="ml-2"
                          size="sm"
                          @click="openQuickWarehouseLocationModal(wh.id)"
                          v-b-tooltip.hover
                          :title="$t('Add') + ' ' + $t('Warehouse_Location')"
                        >
                          <lucide-icon name="plus" />
                        </b-button>
                      </div>
                    </b-form-group>
                  </b-col>
                </b-row>
              </b-card>
            </div>

            <!-- ========== SECTION 7: OPTIONS ========== -->
            <div class="form-section" id="section-options">
              <div class="section-header">
                <lucide-icon class="section-icon" name="database-zap" />
                <h4 class="section-title">{{ $t('Options') }}</h4>
              </div>
              <b-card class="section-card">
                <div class="options-grid">
                  <b-form-group>
                    <b-form-checkbox
                      v-model="product.is_imei"
                      :unchecked-value="false"
                      :checked-value="true"
                      switch
                    >
                      {{ $t('Product_Has_Imei_Serial_number') }}
                    </b-form-checkbox>
                  </b-form-group>

                  <b-form-group>
                    <b-form-checkbox
                      v-model="product.not_selling"
                      :unchecked-value="false"
                      :checked-value="true"
                      switch
                    >
                      {{ $t('This_Product_Not_For_Selling') }}
                    </b-form-checkbox>
                  </b-form-group>

                  <b-form-group>
                    <b-form-checkbox
                      v-model="product.is_active"
                      :unchecked-value="false"
                      :checked-value="true"
                      switch
                    >
                      {{ $t('Active') }}
                    </b-form-checkbox>
                  </b-form-group>

                  <b-form-group>
                    <b-form-checkbox
                      v-model="product.is_featured"
                      :unchecked-value="false"
                      :checked-value="true"
                      switch
                    >
                      {{ $t('Featured_Product') }}
                    </b-form-checkbox>
                  </b-form-group>

                  <b-form-group>
                    <b-form-checkbox
                      v-model="product.hide_from_online_store"
                      :unchecked-value="false"
                      :checked-value="true"
                      switch
                    >
                      {{ $t('Hide_From_Online_Store') }}
                    </b-form-checkbox>
                  </b-form-group>

                  <b-form-group>
                    <b-form-checkbox
                      v-model="product.is_preorder"
                      :unchecked-value="false"
                      :checked-value="true"
                      switch
                    >
                      {{ $t('Enable_Preorder') }}
                    </b-form-checkbox>
                  </b-form-group>

                  <template v-if="product.is_preorder">
                    <b-form-group :label="$t('Preorder_Available_Date')">
                      <b-form-input
                        type="date"
                        v-model="product.preorder_available_date"
                      />
                    </b-form-group>

                    <b-form-group :label="$t('Preorder_Limit')">
                      <b-form-input
                        type="number"
                        v-model="product.preorder_limit"
                        :placeholder="$t('Preorder_Limit')"
                        min="0"
                      />
                    </b-form-group>

                    <b-form-group :label="$t('Preorder_Note')">
                      <b-form-input
                        v-model="product.preorder_note"
                        :placeholder="$t('Preorder_Note')"
                      />
                    </b-form-group>
                  </template>

                </div>
              </b-card>
            </div>

            <!-- ========== SECTION: PHARMACY (BATCH & EXPIRY) ========== -->
            <div class="form-section" id="section-pharmacy">
              <div class="section-header">
                <lucide-icon class="section-icon" name="heart-pulse" />
                <h4 class="section-title">{{ $t('Pharmacy_Settings') || 'Pharmacy' }}</h4>
              </div>
              <b-card class="section-card">
                <b-form-group>
                  <b-form-checkbox
                    v-model="product.is_batch_tracked"
                    :unchecked-value="false"
                    :checked-value="true"
                    switch
                  >
                    {{ $t('Track_Batches_Expiry') }}
                  </b-form-checkbox>
                  <small class="text-muted d-block">{{ $t('Track_Batches_Expiry_Help') }}</small>
                </b-form-group>

                <template v-if="product.is_batch_tracked">
                  <b-form-group :label="$t('Shelf_Life_Days')">
                    <b-form-input
                      type="number"
                      v-model="product.shelf_life_days"
                      :placeholder="$t('Shelf_Life_Days')"
                      min="0"
                    />
                  </b-form-group>
                </template>

                <b-form-group>
                  <b-form-checkbox
                    v-model="product.prescription_required"
                    :unchecked-value="false"
                    :checked-value="true"
                    switch
                  >
                    {{ $t('Prescription_Required') }}
                  </b-form-checkbox>
                </b-form-group>

                <b-row>
                  <b-col md="6">
                    <b-form-group :label="$t('Generic_Name')">
                      <b-form-input
                        v-model="product.generic_name"
                        :placeholder="$t('Generic_Name')"
                      />
                    </b-form-group>
                  </b-col>

                  <b-col md="6">
                    <b-form-group :label="$t('Strength')">
                      <b-form-input
                        v-model="product.strength"
                        :placeholder="$t('Strength')"
                      />
                    </b-form-group>
                  </b-col>

                  <b-col md="6">
                    <b-form-group :label="$t('Dosage_Form')">
                      <b-form-input
                        v-model="product.dosage_form"
                        :placeholder="$t('Dosage_Form')"
                      />
                    </b-form-group>
                  </b-col>

                  <b-col md="6">
                    <b-form-group :label="$t('Pack_Size')">
                      <b-form-input
                        v-model="product.pack_size"
                        :placeholder="$t('Pack_Size')"
                      />
                    </b-form-group>
                  </b-col>

                  <b-col md="6">
                    <b-form-group :label="$t('Manufacturer')">
                      <b-form-input
                        v-model="product.manufacturer"
                        :placeholder="$t('Manufacturer')"
                      />
                    </b-form-group>
                  </b-col>

                  <b-col md="6">
                    <b-form-group :label="$t('Drug_Schedule')">
                      <b-form-input
                        v-model="product.drug_schedule"
                        :placeholder="$t('Drug_Schedule')"
                      />
                    </b-form-group>
                  </b-col>
                </b-row>
              </b-card>
            </div>

            <!-- bottom spacer so the floating action bar never covers the last field -->
            <div class="form-actions-spacer" aria-hidden="true"></div>
          </b-col>

          <!-- Sidebar — TOC + Live Summary -->
          <b-col lg="4" class="product-create-aside d-none d-lg-block">
            <div class="sticky-sidebar">

              <!-- Section navigation -->
              <nav class="side-card side-toc">
                <div class="side-card__title">
                  <lucide-icon name="list" />
                  <span>{{ $t('OnThisPage') || 'On this page' }}</span>
                </div>
                <ul class="side-toc__list">
                  <li><a href="#section-basic"><lucide-icon name="file" /><span>{{ $t('BasicInformation') }}</span></a></li>
                  <li><a href="#section-gallery"><lucide-icon name="upload" /><span>{{ $t('ProductImagesGallery') }}</span></a></li>
                  <li><a href="#section-inventory"><lucide-icon name="package" /><span>{{ $t('Inventory') }}</span></a></li>
                  <li v-if="product.type == 'is_variant'"><a href="#section-variants"><lucide-icon name="settings" /><span>{{ $t('Variants') }}</span></a></li>
                  <li><a href="#section-pricing"><lucide-icon name="tag" /><span>{{ $t('PricingAndTax') }}</span></a></li>
                  <li v-if="product.type == 'is_combo'"><a href="#section-combo"><lucide-icon name="shopping-bag" /><span>{{ $t('ComboProducts') }}</span></a></li>
                  <li><a href="#section-warranty"><lucide-icon name="shield" /><span>{{ $t('Warranty_Guarantee_Tracking') }}</span></a></li>
                  <li><a href="#section-location"><lucide-icon name="map-pin" /><span>{{ $t('Internal_Location_Rack_Shelf') }}</span></a></li>
                  <li><a href="#section-options"><lucide-icon name="database-zap" /><span>{{ $t('Options') }}</span></a></li>
                  <li><a href="#section-pharmacy"><lucide-icon name="heart-pulse" /><span>{{ $t('Pharmacy_Settings') || 'Pharmacy' }}</span></a></li>
                </ul>
              </nav>

              <!-- Live preview / summary -->
              <div class="side-card side-summary">
                <div class="side-card__title">
                  <lucide-icon name="eye" />
                  <span>{{ $t('LiveSummary') || 'Live summary' }}</span>
                </div>

                <div class="summary-pills">
                  <span class="pill pill--type">
                    <lucide-icon name="layers" />
                    {{
                      product.type === 'is_variant' ? ($t('Variable') || 'Variable')
                      : product.type === 'is_service' ? ($t('Service') || 'Service')
                      : product.type === 'is_combo' ? ($t('Combo') || 'Combo')
                      : ($t('Standard') || 'Standard')
                    }}
                  </span>
                  <span class="pill" :class="product.is_active ? 'pill--success' : 'pill--muted'">
                    <span class="pill-dot"></span>
                    {{ product.is_active ? ($t('Active') || 'Active') : ($t('Inactive') || 'Inactive') }}
                  </span>
                  <span v-if="product.is_featured" class="pill pill--accent">
                    <lucide-icon name="star" /> {{ $t('Featured_Product') || 'Featured' }}
                  </span>
                </div>

                <div class="summary-row">
                  <span class="summary-row__label">{{ $t('Name') }}</span>
                  <span class="summary-row__value">{{ product.name || '—' }}</span>
                </div>
                <div class="summary-row">
                  <span class="summary-row__label">{{ $t('Code') }}</span>
                  <span class="summary-row__value mono">{{ product.code || '—' }}</span>
                </div>
                <div class="summary-row" v-if="product.type == 'is_single' || product.type == 'is_combo'">
                  <span class="summary-row__label">{{ $t('Cost') }}</span>
                  <span class="summary-row__value">{{ currentUser.currency }} {{ product.cost || '0.00' }}</span>
                </div>
                <div class="summary-row" v-if="product.type != 'is_variant'">
                  <span class="summary-row__label">{{ $t('Retail Price') }}</span>
                  <span class="summary-row__value summary-row__value--strong">{{ currentUser.currency }} {{ product.price || '0.00' }}</span>
                </div>
                <div class="summary-row">
                  <span class="summary-row__label">{{ $t('OrderTax') }}</span>
                  <span class="summary-row__value">{{ product.TaxNet || 0 }}%</span>
                </div>
              </div>

              <!-- Help / tip card -->
              <div class="side-card side-help">
                <div class="side-card__title">
                  <lucide-icon name="lightbulb" />
                  <span>{{ $t('Tips') || 'Tips' }}</span>
                </div>
                <p class="side-help__text">
                  {{ $t('EditProductTipText') || 'Take care when changing the code or unit — historical transactions still reference the original values.' }}
                </p>
              </div>

            </div>
          </b-col>
        </b-row>

        <!-- ========== STICKY FLOATING ACTION BAR ========== -->
        <div class="form-action-bar">
          <div class="form-action-bar__inner">
            <div class="form-action-bar__hint">
              <lucide-icon name="info" />
              <span>{{ $t('EditUnsavedChangesHint') || 'Review your changes, then save them.' }}</span>
            </div>
            <div class="form-action-bar__buttons">
              <b-button variant="outline-secondary" type="button" @click="$router.back()">
                {{ $t('Cancel') || 'Cancel' }}
              </b-button>
              <b-button variant="primary" type="submit" :disabled="SubmitProcessing">
                <lucide-icon name="check" />
                <span>{{ SubmitProcessing ? ($t('Saving') || 'Saving…') : ($t('Save_Changes') || $t('submit')) }}</span>
              </b-button>
            </div>
          </div>
        </div>
      </b-form>
    </validation-observer>
  </div>
</template>

<script>
import VueTagsInput from "@johmun/vue-tags-input";
import draggable from "vuedraggable";
import NProgress from "nprogress";
import { mapActions, mapGetters } from "vuex";

export default {
  metaInfo: {
    title: "Edit Product"
  },
  data() {
    return {
      focused: false,
      timer:null,
      search_input:'',
      product_filter:[],
      materiels: [],
      products_ing: [],
      warehouses: [],
      warehouse_locations: [],
      locationsByWarehouse: {},
      warehouse_location_map: {},
      quickWarehouseLocation: {
        warehouse_id: "",
        code: "",
        name: "",
        is_active: true
      },
      quickWarehouseLocationWarehouseLocked: false,
      quickWarehouseLocationSubmitting: false,
      tag: "",
      len: 8,
      change: false,
      isLoading: true,
      SubmitProcessing:false,
      data: new FormData(),
      categories: [],
      allSubcategories: [],
      units: [],
      units_sub: [],
      brands: [],
      roles: {},
      variants: [],
      product: {
        type: "",
        name: "",
        points: "",
        code: "",
        Type_barcode: "",
        cost: "",
        price: "",
        brand_id: "",
        category_id: "",
        sub_category_id: "",
        assigned_category_ids: [],
        assigned_subcategory_ids: [],
        TaxNet: "",
        tax_method: "1",
        unit_id: "",
        unit_sale_id: "",
        unit_purchase_id: "",
        stock_alert: "",
        weight: "",
        length: "",
        width: "",
        height: "",
        image: "",
        note: "",
        is_variant: false,
        is_imei: false,
        not_selling: false,
        is_active: true,
        is_featured: false,
        hide_from_online_store: false,
        is_preorder: false,
        preorder_available_date: "",
        preorder_limit: "",
        preorder_note: "",
        is_batch_tracked: false,
        shelf_life_days: "",
        generic_name: "",
        strength: "",
        dosage_form: "",
        pack_size: "",
        manufacturer: "",
        prescription_required: false,
        drug_schedule: "",
      },
      code_exist: "",
      product_images: [],
      galleryRemoveIds: [],
      galleryUidSeed: 0
    };
  },

  components: {
    VueTagsInput,
    draggable
  },

  computed: {
    ...mapGetters(["currentUserPermissions","currentUser"]),
    totalCost() {
      return this.materiels.reduce((total, materiel) => {
        return total + (materiel.cost * materiel.quantity);
      }, 0);
    },
    hasPendingGalleryUploads() {
      return (this.product_images || []).some(r => r && r._file);
    },
    subcategoryOptionsFiltered() {
      const subs = this.allSubcategories || [];
      const cats = this.categories || [];
      const catName = id => {
        const c = cats.find(x => String(x.id) === String(id));
        return c ? c.name : "";
      };
      const selectedCats = this.product.assigned_category_ids || [];
      const set = new Set(selectedCats.map(id => String(id)));
      const filtered =
        !selectedCats.length
          ? []
          : subs.filter(sc => set.has(String(sc.category_id)));
      return filtered.map(sc => {
        const cn = catName(sc.category_id);
        return {
          label: cn ? `${sc.name} (${cn})` : sc.name,
          value: sc.id
        };
      });
    }
  },

  watch: {
    "product.assigned_category_ids": {
      handler() {
        this.pruneInvalidSubcategories();
        this.syncLegacyCategoryFields();
      },
      deep: true
    },
    "product.assigned_subcategory_ids": {
      handler() {
        this.syncLegacyCategoryFields();
      },
      deep: true
    }
  },

  methods: {

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

    touchGalleryOrder() {
      (this.product_images || []).forEach((r, i) => {
        r.sort_order = i;
      });
    },

    setGalleryMain(row) {
      (this.product_images || []).forEach(r => {
        r.is_main = r === row;
      });
    },

    removeGalleryRow(index) {
      const row = this.product_images[index];
      if (row && row.id) {
        this.galleryRemoveIds.push(row.id);
      }
      if (row && row._file && row.url && row.url.indexOf("blob:") === 0) {
        try {
          URL.revokeObjectURL(row.url);
        } catch (e) { /* ignore */ }
      }
      this.product_images.splice(index, 1);
      this.touchGalleryOrder();
      if (!this.product_images.some(r => r && r.is_main) && this.product_images.length) {
        this.$set(this.product_images[0], "is_main", true);
      }
    },

    onGalleryExtraSelected(e) {
      const files = Array.from(e.target.files || []).filter(f => f.type && f.type.indexOf("image/") === 0);
      files.forEach(f => {
        this.galleryUidSeed += 1;
        this.product_images.push({
          id: null,
          _uid: "n-" + this.galleryUidSeed,
          url: URL.createObjectURL(f),
          image_path: f.name,
          is_main: false,
          sort_order: this.product_images.length,
          _file: f
        });
      });
      this.touchGalleryOrder();
      if (!this.product_images.some(r => r && r.is_main) && this.product_images.length) {
        this.$set(this.product_images[0], "is_main", true);
      }
      e.target.value = "";
    },



    //---------------------- get_products_materiels------------------------------\\
    get_products_materiels(value) {
      axios
      .get("get_products_materiels")
      .then(({ data }) => (this.products_ing = data));
    },


    handleFocus() {
      this.focused = true
    },


    handleBlur() {
      this.focused = false
    },


    // Search Products
    search(){
      if (this.timer) {
          clearTimeout(this.timer);
          this.timer = null;
      }
      if (this.search_input.length < 1) {
      return this.product_filter= [];
      }
      this.timer = setTimeout(() => {
      const product_filter = this.products_ing.filter(ingredient => ingredient.code === this.search_input);
          if(product_filter.length === 1){
              this.SearchProduct(product_filter[0])
          }else{
              this.product_filter=  this.products_ing.filter(ingredient => {
              return (
                  ingredient.name.toLowerCase().includes(this.search_input.toLowerCase()) ||
                  ingredient.code.toLowerCase().includes(this.search_input.toLowerCase())
                  );
              });
          }
      }, 800);

    },

    // get Result Value Search Products
    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },


      // Submit Search Products
      SearchProduct(result) {
        this.ingredient = {};
        if (
            this.materiels.length > 0 &&
            this.materiels.some(detail => detail.code === result.code)
        ) {
            toastr.error('Product_Already_added');
            
        } else {

            var materiel_tag = {
                product_id:result.product_id,
                name:result.name,
                code:result.code,
                unit_name:result.unit_name,
                cost:result.cost,
                quantity:1,
            }
            this.materiels.push(materiel_tag);
            
        }
        this.search_input= '';
        this.$refs.product_autocomplete.value = "";
        this.product_filter = [];
      },


    //-----------------------------------Delete variant------------------------------\\
    delete_materiel(product_id) {

      for (var i = 0; i < this.materiels.length; i++) {
          if (product_id === this.materiels[i].product_id) {
          this.materiels.splice(i, 1);
          }
      }
    },


    showModal() {
      this.$bvModal.show('open_scan');
      
    },

    onScan (decodedText, decodedResult) {
      const code = decodedText;
      this.product.code = code;
      this.$bvModal.hide('open_scan');
    },


     //------ Generate code
     generateNumber() {
      this.code_exist = "";
      this.product.code = Math.floor(
        Math.pow(10, 7) +
          Math.random() *
            (Math.pow(10, 8) - Math.pow(10, 7) - 1)
      );
    },
    
    syncLegacyCategoryFields() {
      const c = Array.isArray(this.product.assigned_category_ids)
        ? this.product.assigned_category_ids
        : [];
      const s = Array.isArray(this.product.assigned_subcategory_ids)
        ? this.product.assigned_subcategory_ids
        : [];
      const firstCat = c.length ? c[0] : "";
      const firstSub = s.length ? s[0] : "";
      this.$set(this.product, "category_id", firstCat === "" || firstCat == null ? "" : firstCat);
      this.$set(this.product, "sub_category_id", firstSub === "" || firstSub == null ? "" : firstSub);
    },

    pruneInvalidSubcategories() {
      const catSet = new Set(
        (this.product.assigned_category_ids || []).map(id => String(id))
      );
      const subs = this.product.assigned_subcategory_ids || [];
      const all = this.allSubcategories || [];
      const filtered = subs.filter(sid => {
        const sc = all.find(x => String(x.id) === String(sid));
        return sc && catSet.has(String(sc.category_id));
      });
      if (filtered.length !== subs.length) {
        this.$set(this.product, "assigned_subcategory_ids", filtered);
      }
    },

    //------------- Submit Validation Update Product
    Submit_Product() {
      this.syncLegacyCategoryFields();
      this.$refs.Edit_Product.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {

            if (this.product.type == 'is_variant' && this.variants.length <= 0) {
              this.makeToast("danger", "The variants array is required.", this.$t("Failed"));
            }else{
              this.Update_Product();
            }
        }
      });
    },

    //------ Validation state fields
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //-------------------------- Quick Add Warehouse Location (modal) --------------------------\\
    openQuickWarehouseLocationModal(warehouseId) {
      this.quickWarehouseLocation = {
        warehouse_id: warehouseId || (this.warehouses[0] ? this.warehouses[0].id : ""),
        code: "",
        name: "",
        is_active: true
      };
      this.quickWarehouseLocationWarehouseLocked = true;
      this.$bvModal.show("Quick_Add_Warehouse_Location");
    },

    submitQuickWarehouseLocation() {
      this.$refs.QuickWarehouseLocation.validate().then(async success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
          return;
        }

        this.quickWarehouseLocationSubmitting = true;
        try {
          const payload = {
            warehouse_id: this.quickWarehouseLocation.warehouse_id,
            code: this.quickWarehouseLocation.code,
            name: this.quickWarehouseLocation.name || "",
            is_active: true
          };

          const { data } = await axios.post("products/warehouse_locations", payload);
          const newLoc = data && data.location ? data.location : null;
          if (newLoc && newLoc.id) {
            const wid = newLoc.warehouse_id;
            const label = newLoc.name ? `${newLoc.code} - ${newLoc.name}` : newLoc.code;

            if (!this.locationsByWarehouse[wid]) {
              this.$set(this.locationsByWarehouse, wid, []);
            }
            this.locationsByWarehouse[wid].push({ id: newLoc.id, label, is_active: true });

            // auto-select for that warehouse
            this.$set(this.warehouse_location_map, wid, newLoc.id);
          }

          this.$bvModal.hide("Quick_Add_Warehouse_Location");
          this.makeToast(
            "success",
            this.$t("Successfully_Created"),
            this.$t("Success")
          );
        } catch (e) {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        } finally {
          this.quickWarehouseLocationSubmitting = false;
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

    add_variant(tag) {
      if (
        this.variants.length > 0 &&
        this.variants.some(variant => variant.text === tag)
      ) {
         this.makeToast(
            "warning",
            this.$t("VariantDuplicate"),
            this.$t("Warning")
          );
      } else {
          if(this.tag != ''){
            var variant_tag = {
              var_id: this.variants.length + 1, // generate unique ID
              text: tag
            };
            this.variants.push(variant_tag);
            this.tag = "";
          }else{

            this.makeToast(
              "warning",
              "Please Enter the Variant",
              this.$t("Warning")
            );
            
          }
      }
    },
    //-----------------------------------Delete variant------------------------------\\
    delete_variant(var_id) {
      for (var i = 0; i < this.variants.length; i++) {
        if (var_id === this.variants[i].var_id) {
          this.variants.splice(i, 1);
        }
      }
    },



    //---------------------------------------Get Product Elements ------------------------------\\
    GetElements() {
      let id = this.$route.params.id;
      axios
        .get(`products/${id}/edit`)
        .then(response => {
          this.product = response.data.product;
          this.variants = response.data.product.ProductVariant;
          this.warehouses = response.data.warehouses || [];
          this.warehouse_locations = response.data.warehouse_locations || [];

          const byWh = {};
          (this.warehouse_locations || []).forEach(loc => {
            const wid = loc.warehouse_id;
            if (!byWh[wid]) byWh[wid] = [];
            const label = loc.name ? `${loc.code} - ${loc.name}` : loc.code;
            byWh[wid].push({ id: loc.id, label, is_active: !!loc.is_active });
          });
          this.locationsByWarehouse = byWh;

          // init map (reactive)
          const existing = {};
          (response.data.product_warehouse_locations || []).forEach(r => {
            existing[r.warehouse_id] = r.warehouse_location_id || null;
          });
          this.warehouses.forEach(wh => {
            this.$set(this.warehouse_location_map, wh.id, existing[wh.id] || null);
          });
          this.categories = response.data.categories;
          this.allSubcategories = response.data.all_subcategories || [];
          if (!Array.isArray(this.product.assigned_category_ids)) {
            this.$set(this.product, "assigned_category_ids", []);
          }
          if (!Array.isArray(this.product.assigned_subcategory_ids)) {
            this.$set(this.product, "assigned_subcategory_ids", []);
          }
          if (
            (!this.product.assigned_category_ids || !this.product.assigned_category_ids.length) &&
            this.product.category_id
          ) {
            this.$set(this.product, "assigned_category_ids", [this.product.category_id]);
          }
          if (
            (!this.product.assigned_subcategory_ids || !this.product.assigned_subcategory_ids.length) &&
            this.product.sub_category_id
          ) {
            this.$set(this.product, "assigned_subcategory_ids", [this.product.sub_category_id]);
          }
          this.$nextTick(() => {
            this.pruneInvalidSubcategories();
            this.syncLegacyCategoryFields();
          });
          this.brands = response.data.brands;
          this.units = response.data.units;
          this.units_sub = response.data.units_sub;
          this.galleryRemoveIds = [];
          this.galleryUidSeed = 0;
          const imgs = response.data.product.product_images || [];
          this.product_images = imgs.map(r => ({
            ...r,
            _uid: "e-" + r.id
          }));
          if(this.product.type == 'is_combo'){
              this.get_products_materiels();
              this.materiels = response.data.materiels;
          }

          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //---------------------- Get Sub Units with Unit id ------------------------------\\
    Get_Units_SubBase(value) {
      axios
        .get("get_sub_units_by_base?id=" + value)
        .then(({ data }) => (this.units_sub = data));
    },

    //---------------------- Event Select Unit Product ------------------------------\\
    Selected_Unit(value) {
      this.units_sub = [];
      this.product.unit_sale_id = "";
      this.product.unit_purchase_id = "";
      this.Get_Units_SubBase(value);
    },

    //------------------------------ Update Product ------------------------------\\
    Update_Product() {
      
      NProgress.start();
      NProgress.set(0.1);
      var self = this;
      self.data = new FormData();
      self.SubmitProcessing = true;

      self.syncLegacyCategoryFields();

      if (self.product.type == 'is_variant' && self.variants.length > 0) {
        self.product.is_variant = true;
      }else{
        self.product.is_variant = false;
      }

      const {
        assigned_category_ids,
        assigned_subcategory_ids,
        ...prodRest
      } = self.product;
      Object.entries(prodRest).forEach(([key, value]) => {
        self.data.append(key, value);
      });
      self.data.append("multi_category_ids", JSON.stringify(assigned_category_ids || []));
      self.data.append("multi_subcategory_ids", JSON.stringify(assigned_subcategory_ids || []));

       // append array variants
       if (self.materiels.length && self.product.type == 'is_combo') {
        self.data.append("materiels", JSON.stringify(self.materiels));
      }
                
      //append array variants
      if (self.variants.length) {
          for (var i = 0; i < self.variants.length; i++) {
          Object.entries(self.variants[i]).forEach(([key, value]) => {
              self.data.append("variants[" + i + "][" + key + "]", value);
          });
          }
      }

      // append warehouse default locations
      const wlPayload = {};
      if (self.warehouses && self.warehouses.length) {
        self.warehouses.forEach(wh => {
          wlPayload[wh.id] = { warehouse_location_id: self.warehouse_location_map[wh.id] || null };
        });
      }
      self.data.append("warehouse_locations", JSON.stringify(wlPayload));

      const orderPayload = [];
      (self.product_images || []).forEach((r, i) => {
        if (r && r.id) {
          orderPayload.push({ id: r.id, sort_order: i });
        }
      });
      const mainRow = (self.product_images || []).find(r => r && r.is_main);
      let main_id = null;
      let main_pending_index = null;
      if (mainRow) {
        if (mainRow.id) {
          main_id = mainRow.id;
        } else {
          const pending = (self.product_images || []).filter(r => r && r._file);
          const pi = pending.findIndex(r => r === mainRow);
          main_pending_index = pi >= 0 ? pi : null;
        }
      }
      const hasPersistedGallery = orderPayload.length > 0;
      const hasGalleryChanges =
        self.galleryRemoveIds.length > 0 ||
        hasPersistedGallery ||
        (self.product_images || []).some(r => r && r._file);
      if (hasGalleryChanges) {
        self.data.append(
          "product_gallery_json",
          JSON.stringify({
            remove: self.galleryRemoveIds,
            order: orderPayload,
            main_id: main_id,
            main_pending_index: main_pending_index
          })
        );
      }
      (self.product_images || []).forEach(r => {
        if (r && r._file) {
          self.data.append("gallery_images[]", r._file);
        }
      });

      self.data.append("_method", "put");

      //send Data with axios
      axios
        .post("products/" + this.product.id, self.data)
        .then(response => {
          NProgress.done();
          self.SubmitProcessing = false;
          this.$router.push({ name: "index_products" });
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
        })
        .catch(error => {
            NProgress.done();
            self.SubmitProcessing = false;
            if (error.errors.code && error.errors.code.length > 0) {
              self.code_exist = error.errors.code[0];
              this.makeToast("danger", error.errors.code[0], this.$t("Failed"));
            }else if(error.errors.variants && error.errors.variants.length > 0){
              this.makeToast("danger", error.errors.variants[0], this.$t("Failed"));
            }else{
              this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
            }
        });
    }
  }, //end Methods

  //-----------------------------Created function-------------------

  created: function() {
    this.GetElements();
  }
};
</script>

<style>
  /* ===========================================================
     Modern Edit Product page (shares the design language of the
     Add Product page; both use the .product-create-page namespace).
     ----------------------------------------------------------- */
  .product-create-page {
    --pc-primary: #6366f1;
    --pc-primary-strong: #4f46e5;
    --pc-primary-soft: #eef2ff;
    --pc-accent: #8b5cf6;
    --pc-success: #10b981;
    --pc-warn: #f59e0b;
    --pc-danger: #ef4444;
    --pc-text: #0f172a;
    --pc-text-soft: #475569;
    --pc-text-muted: #64748b;
    --pc-border: #e5e7eb;
    --pc-border-soft: #eef0f4;
    --pc-bg: #f8fafc;
    --pc-bg-soft: #fafbff;
    --pc-card: #ffffff;
    --pc-radius: 14px;
    --pc-radius-lg: 18px;
    --pc-shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.04);
    --pc-shadow: 0 1px 3px rgba(15, 23, 42, 0.05), 0 6px 20px rgba(15, 23, 42, 0.04);
    --pc-shadow-lg: 0 10px 30px rgba(15, 23, 42, 0.08);
  }

  /* ===== Page hero ===== */
  .product-create-page .page-hero {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1.25rem;
    padding: 1.5rem 1.75rem;
    margin-bottom: 1.75rem;
    border-radius: var(--pc-radius-lg);
    background:
      radial-gradient(circle at 0% 0%, rgba(139, 92, 246, 0.10), transparent 55%),
      radial-gradient(circle at 100% 100%, rgba(99, 102, 241, 0.12), transparent 55%),
      linear-gradient(135deg, #ffffff 0%, #f8faff 100%);
    border: 1px solid rgba(99, 102, 241, 0.12);
    box-shadow: var(--pc-shadow);
    overflow: hidden;
  }

  .product-create-page .page-hero::after {
    content: "";
    position: absolute;
    inset: auto -40px -60px auto;
    width: 240px;
    height: 240px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.18), transparent 70%);
    pointer-events: none;
  }

  .product-create-page .page-hero__main {
    flex: 1 1 320px;
    min-width: 0;
    position: relative;
    z-index: 1;
  }

  .product-create-page .page-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--pc-primary-strong);
    background: var(--pc-primary-soft);
    padding: 0.32rem 0.7rem;
    border-radius: 999px;
    margin-bottom: 0.65rem;
  }

  .product-create-page .page-hero__eyebrow svg {
    width: 14px;
    height: 14px;
  }

  .product-create-page .page-hero__title {
    margin: 0 0 0.35rem;
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--pc-text);
    line-height: 1.15;
  }

  .product-create-page .page-hero__subtitle {
    margin: 0;
    font-size: 0.95rem;
    color: var(--pc-text-soft);
    max-width: 56ch;
    line-height: 1.5;
  }

  .product-create-page .page-hero__actions {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    position: relative;
    z-index: 1;
  }

  .product-create-page .hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.6rem 1.05rem;
    border-radius: 10px;
    font-weight: 600;
    letter-spacing: -0.005em;
  }

  .product-create-page .hero-btn--primary {
    background: linear-gradient(135deg, var(--pc-primary) 0%, var(--pc-primary-strong) 100%);
    border-color: transparent;
    box-shadow: 0 6px 18px rgba(99, 102, 241, 0.28);
  }

  .product-create-page .hero-btn--primary:hover,
  .product-create-page .hero-btn--primary:focus {
    background: linear-gradient(135deg, var(--pc-primary-strong) 0%, #4338ca 100%);
    box-shadow: 0 8px 22px rgba(99, 102, 241, 0.36);
  }

  .product-create-page .hero-btn svg {
    width: 16px;
    height: 16px;
  }

  /* ===== Two-column layout ===== */
  .product-create-page .product-create-grid {
    align-items: flex-start;
  }

  .product-create-page .product-create-aside {
    align-self: stretch;
  }

  /* ===== Sticky sidebar / TOC ===== */
  .product-create-page .sticky-sidebar {
    position: sticky;
    top: 88px;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .product-create-page .side-card {
    background: var(--pc-card);
    border: 1px solid var(--pc-border-soft);
    border-radius: var(--pc-radius);
    padding: 1.1rem 1.15rem;
    box-shadow: var(--pc-shadow-sm);
  }

  .product-create-page .side-card__title {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--pc-text-muted);
    margin-bottom: 0.85rem;
    padding-bottom: 0.7rem;
    border-bottom: 1px dashed var(--pc-border);
  }

  .product-create-page .side-card__title svg {
    width: 15px;
    height: 15px;
    color: var(--pc-primary);
  }

  /* TOC list */
  .product-create-page .side-toc__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
  }

  .product-create-page .side-toc__list a {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.65rem;
    border-radius: 9px;
    font-size: 0.875rem;
    color: var(--pc-text-soft);
    text-decoration: none;
    transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
  }

  .product-create-page .side-toc__list a svg {
    width: 15px;
    height: 15px;
    color: var(--pc-text-muted);
    flex-shrink: 0;
  }

  .product-create-page .side-toc__list a:hover {
    background: var(--pc-primary-soft);
    color: var(--pc-primary-strong);
    transform: translateX(2px);
  }

  .product-create-page .side-toc__list a:hover svg {
    color: var(--pc-primary);
  }

  /* Summary card */
  .product-create-page .summary-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 0.9rem;
  }

  .product-create-page .pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.6rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    background: #f1f5f9;
    color: var(--pc-text-soft);
    border: 1px solid var(--pc-border);
  }

  .product-create-page .pill svg {
    width: 12px;
    height: 12px;
  }

  .product-create-page .pill--type {
    background: var(--pc-primary-soft);
    color: var(--pc-primary-strong);
    border-color: rgba(99, 102, 241, 0.25);
  }

  .product-create-page .pill--success {
    background: #ecfdf5;
    color: #047857;
    border-color: #a7f3d0;
  }

  .product-create-page .pill--success .pill-dot {
    background: var(--pc-success);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
  }

  .product-create-page .pill--muted .pill-dot {
    background: #94a3b8;
  }

  .product-create-page .pill-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
  }

  .product-create-page .pill--accent {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border-color: #fcd34d;
  }

  .product-create-page .summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.6rem 0;
    border-bottom: 1px dashed var(--pc-border-soft);
  }

  .product-create-page .summary-row:last-child {
    border-bottom: none;
  }

  .product-create-page .summary-row__label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--pc-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    flex-shrink: 0;
  }

  .product-create-page .summary-row__value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--pc-text);
    text-align: right;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 60%;
  }

  .product-create-page .summary-row__value.mono {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
    font-size: 0.82rem;
    color: var(--pc-text-soft);
    background: #f1f5f9;
    padding: 0.18rem 0.5rem;
    border-radius: 6px;
  }

  .product-create-page .summary-row__value--strong {
    color: var(--pc-primary-strong);
    font-weight: 700;
  }

  /* Help card */
  .product-create-page .side-help__text {
    margin: 0;
    font-size: 0.85rem;
    color: var(--pc-text-soft);
    line-height: 1.55;
  }

  /* ===== Floating action bar ===== */
  .product-create-page .form-actions-spacer {
    height: 82px;
  }

  .product-create-page .form-action-bar {
    position: sticky;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 30;
    margin: 0 -1px 0;
    padding: 0.85rem 0 1rem;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0) 0%, rgba(248, 250, 252, 0.85) 35%, #f8fafc 70%);
    pointer-events: none;
  }

  .product-create-page .form-action-bar__inner {
    pointer-events: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem;
    background: var(--pc-card);
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius);
    box-shadow: var(--pc-shadow-lg);
  }

  .product-create-page .form-action-bar__hint {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--pc-text-muted);
    font-size: 0.85rem;
    min-width: 0;
  }

  .product-create-page .form-action-bar__hint svg {
    width: 16px;
    height: 16px;
    color: var(--pc-primary);
    flex-shrink: 0;
  }

  .product-create-page .form-action-bar__hint span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .product-create-page .form-action-bar__buttons {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    flex-shrink: 0;
  }

  .product-create-page .form-action-bar__buttons .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 1.1rem;
    border-radius: 10px;
    font-weight: 600;
  }

  .product-create-page .form-action-bar__buttons .btn-primary {
    background: linear-gradient(135deg, var(--pc-primary) 0%, var(--pc-primary-strong) 100%);
    border-color: transparent;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.28);
  }

  .product-create-page .form-action-bar__buttons .btn-primary:hover,
  .product-create-page .form-action-bar__buttons .btn-primary:focus {
    background: linear-gradient(135deg, var(--pc-primary-strong) 0%, #4338ca 100%);
  }

  .product-create-page .form-action-bar__buttons .btn-primary svg {
    width: 15px;
    height: 15px;
  }

  /* ===== Smooth scroll anchors ===== */
  .product-create-page .form-section[id] {
    scroll-margin-top: 96px;
  }

  /* ===== Existing element refinements ===== */
  .scan-icon {
    width: auto;
    height: 18px;
    margin: 0;
    cursor: pointer;
    display: block;
  }

  .gallery-thumb-select {
    cursor: pointer;
    border: 2px solid transparent;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }

  .gallery-thumb-main {
    border-color: #667eea !important;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.35);
  }

  .product-gallery-section-card .card-body {
    padding: 1.35rem 1.5rem;
  }

  .product-gallery-intro {
    line-height: 1.55;
    max-width: 52rem;
  }

  .product-gallery-dropzone {
    position: relative;
    display: block;
    margin-bottom: 0;
    cursor: pointer;
    border-radius: 10px;
    border: 2px dashed #dde1ea;
    background: linear-gradient(180deg, #fafbff 0%, #f4f6fb 100%);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
  }

  .product-gallery-dropzone:hover:not(.product-gallery-dropzone-disabled) {
    border-color: #667eea;
    background: #f5f7ff;
    box-shadow: 0 2px 12px rgba(102, 126, 234, 0.12);
  }

  .product-gallery-dropzone-disabled {
    cursor: not-allowed;
    opacity: 0.55;
    pointer-events: none;
  }

  .product-gallery-dropzone-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.35rem 1rem;
    text-align: center;
  }

  .product-gallery-dropzone-icon {
    font-size: 1.85rem;
    color: #667eea;
    margin-bottom: 0.5rem;
    opacity: 0.95;
  }

  .product-gallery-dropzone-title {
    font-weight: 600;
    font-size: 0.95rem;
    color: #1a1a1a;
    letter-spacing: -0.02em;
  }

  .product-gallery-dropzone-sub {
    margin-top: 0.35rem;
    max-width: 22rem;
    line-height: 1.4;
  }

  .product-gallery-file-native {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
  }

  .product-gallery-empty {
    background: #f9fafc;
    border: 1px solid #eef0f4;
  }

  .product-gallery-draggable {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
  }

  .gallery-item-card {
    position: relative;
    padding: 0.75rem 1rem 0.75rem 12px;
    gap: 0.65rem;
    background: linear-gradient(145deg, #ffffff 0%, #fafbfd 100%);
    border: 1px solid rgba(232, 234, 239, 0.95);
    border-radius: 14px;
    box-shadow:
      0 1px 2px rgba(15, 23, 42, 0.04),
      0 4px 12px rgba(15, 23, 42, 0.035);
    transition:
      transform 0.22s ease,
      box-shadow 0.22s ease,
      border-color 0.22s ease,
      background 0.22s ease;
    overflow: hidden;
  }

  .gallery-item-card::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    border-radius: 14px 0 0 14px;
    background: linear-gradient(180deg, #e2e6ef 0%, #d4d9e4 100%);
    opacity: 0.85;
    transition: opacity 0.22s ease, background 0.22s ease;
  }

  .gallery-item-card--main {
    background: linear-gradient(145deg, #f8f9ff 0%, #f0f3ff 55%, #fafbff 100%);
    border-color: rgba(102, 126, 234, 0.35);
    box-shadow:
      0 1px 2px rgba(102, 126, 234, 0.08),
      0 6px 20px rgba(102, 126, 234, 0.12);
  }

  .gallery-item-card--main::before {
    background: linear-gradient(180deg, #7c8ef0 0%, #667eea 50%, #5a6fd6 100%);
    opacity: 1;
    box-shadow: 0 0 12px rgba(102, 126, 234, 0.35);
  }

  .gallery-item-card:hover {
    border-color: rgba(200, 206, 220, 0.95);
    box-shadow:
      0 2px 6px rgba(15, 23, 42, 0.06),
      0 10px 28px rgba(15, 23, 42, 0.07);
    transform: translateY(-1px);
  }

  .gallery-item-card--main:hover {
    border-color: rgba(102, 126, 234, 0.45);
    box-shadow:
      0 2px 8px rgba(102, 126, 234, 0.12),
      0 12px 32px rgba(102, 126, 234, 0.16);
  }

  .gallery-drag-handle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    min-height: 3.25rem;
    margin-right: 0.15rem;
    margin-left: 0;
    color: #9aa3b5;
    cursor: grab;
    border-radius: 10px;
    background: rgba(241, 243, 247, 0.9);
    transition: color 0.2s ease, background 0.2s ease;
  }

  .gallery-drag-handle:active {
    cursor: grabbing;
    color: #667eea;
    background: rgba(102, 126, 234, 0.12);
  }

  .gallery-item-card:hover .gallery-drag-handle {
    color: #7a8499;
    background: rgba(235, 238, 245, 0.95);
  }

  .gallery-item-thumb {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 12px;
    box-shadow:
      0 2px 8px rgba(15, 23, 42, 0.08),
      inset 0 1px 0 rgba(255, 255, 255, 0.6);
  }

  .gallery-item-card .gallery-thumb-select {
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.07);
  }

  .gallery-item-meta {
    min-width: 0;
  }

  .gallery-item-meta .text-truncate {
    color: #1e293b;
    letter-spacing: -0.01em;
    font-size: 0.875rem;
  }

  .gallery-main-badge {
    font-weight: 600;
    font-size: 0.65rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.28em 0.55em;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(34, 197, 94, 0.25);
  }

  .gallery-remove-btn {
    border-radius: 10px;
    width: 2.25rem;
    height: 2.25rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-width: 1.5px;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }

  .gallery-remove-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);
  }

  /* ===== Form Sections ===== */
  .product-create-page .form-section {
    margin-bottom: 1.75rem;
  }

  .product-create-page .section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
    padding: 0;
    border-bottom: none;
  }

  .product-create-page .section-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    color: var(--pc-primary);
    background: var(--pc-primary-soft);
    border-radius: 10px;
    margin-right: 0;
    font-size: 1rem;
  }

  .product-create-page .section-icon svg {
    width: 18px;
    height: 18px;
  }

  .product-create-page .section-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--pc-text);
    letter-spacing: -0.01em;
  }

  .product-create-page .section-card {
    position: relative;
    border: 1px solid var(--pc-border-soft);
    border-radius: var(--pc-radius);
    box-shadow: var(--pc-shadow-sm);
    background: var(--pc-card);
    transition: box-shadow 0.25s ease, border-color 0.25s ease, transform 0.25s ease;
    overflow: hidden;
  }

  .product-create-page .section-card::before {
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 3px;
    background: linear-gradient(90deg, var(--pc-primary), var(--pc-accent));
    opacity: 0;
    transition: opacity 0.25s ease;
  }

  .product-create-page .section-card:hover {
    box-shadow: var(--pc-shadow);
    border-color: rgba(99, 102, 241, 0.18);
  }

  .product-create-page .section-card:hover::before,
  .product-create-page .section-card:focus-within::before {
    opacity: 1;
  }

  .product-create-page .section-card .card-body {
    padding: 1.4rem 1.5rem;
  }

  /* ===== Form Controls ===== */
  .product-create-page .form-control,
  .product-create-page .custom-select,
  .product-create-page textarea.form-control {
    border-radius: 9px;
    border: 1.5px solid var(--pc-border);
    transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
  }

  .product-create-page .form-control:focus,
  .product-create-page .custom-select:focus,
  .product-create-page textarea.form-control:focus {
    border-color: var(--pc-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
  }

  .product-create-page .form-control-modern {
    border-radius: 9px;
    border: 1.5px solid var(--pc-border);
    padding: 0.55rem 0.85rem;
    font-size: 0.92rem;
    transition: all 0.2s ease;
  }

  .product-create-page .form-control-modern:focus {
    border-color: var(--pc-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
  }

  .product-create-page .form-group label {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--pc-text-soft);
    margin-bottom: 0.35rem;
    letter-spacing: 0.005em;
  }

  /* Inline help-icon next to a field label */
  .product-create-page .label-with-help {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
  }

  .product-create-page .label-help-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    color: var(--pc-text-muted);
    background: #f1f5f9;
    border: 1px solid var(--pc-border);
    cursor: help;
    transition: color 0.15s ease, background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
  }

  .product-create-page .label-help-icon svg {
    width: 11px;
    height: 11px;
  }

  .product-create-page .label-help-icon:hover,
  .product-create-page .label-help-icon:focus {
    color: var(--pc-primary-strong);
    background: var(--pc-primary-soft);
    border-color: rgba(99, 102, 241, 0.35);
    outline: none;
    transform: translateY(-1px);
  }

  /* v-select polish */
  .product-create-page .v-select .vs__dropdown-toggle {
    border-radius: 9px;
    border: 1.5px solid var(--pc-border);
    padding: 1px 4px 3px;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
  }

  .product-create-page .v-select.vs--open .vs__dropdown-toggle,
  .product-create-page .v-select:focus-within .vs__dropdown-toggle {
    border-color: var(--pc-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
  }

  .form-control-file {
    display: block;
    padding: 0.5rem 0;
    border-radius: 8px;
    cursor: pointer;
  }

  .image-upload-wrapper {
    background: #fafafa;
    padding: 1rem;
    border-radius: 8px;
    border: 2px dashed #d0d0d0;
    transition: all 0.2s ease;
  }

  .image-upload-wrapper:hover {
    border-color: #667eea;
    background: #f5f7ff;
  }

  /* ===== Input Groups ===== */
  /* Match the v-select height (calc(1.5em + 0.75rem + 3px)) so the
     Code Product field aligns with the Categories / Brand v-selects beside it. */
  .modern-input-group {
    display: flex;
    align-items: stretch;
    border-radius: 9px;
    overflow: hidden;
    border: 1.5px solid var(--pc-border);
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
    min-height: calc(1.5em + 0.75rem + 3px);
  }

  .modern-input-group:focus-within {
    border-color: var(--pc-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
  }

  .modern-input-group .input-group-prepend,
  .modern-input-group .input-group-append {
    display: flex;
    align-items: stretch;
  }

  .modern-input-group .form-control {
    border: none !important;
    box-shadow: none !important;
    height: auto;
    align-self: stretch;
  }

  .modern-input-group .btn-icon-scan,
  .modern-input-group .btn-icon-gen {
    background: #f5f7ff;
    border: none;
    padding: 0 0.75rem;
    color: var(--pc-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: stretch;
    min-width: 38px;
    line-height: 1;
  }

  .modern-input-group .btn-icon-scan:hover,
  .modern-input-group .btn-icon-gen:hover {
    background: var(--pc-primary);
    color: white;
  }

  .modern-input-group .btn-icon-scan svg,
  .modern-input-group .btn-icon-gen svg {
    width: 18px;
    height: 18px;
  }

  /* ===== Tables ===== */
  .product-create-page .table-modern {
    margin-bottom: 0;
    border-radius: 10px;
    overflow: hidden;
  }

  .product-create-page .table-modern thead {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid var(--pc-border);
  }

  .product-create-page .table-modern thead th {
    font-weight: 600;
    color: var(--pc-text-soft);
    padding: 0.85rem 0.875rem;
    text-transform: uppercase;
    font-size: 0.72rem;
    letter-spacing: 0.06em;
    border: none;
  }

  .product-create-page .table-modern tbody tr {
    border-bottom: 1px solid var(--pc-border-soft);
    transition: background-color 0.18s ease;
  }

  .product-create-page .table-modern tbody tr:hover {
    background-color: var(--pc-bg-soft);
  }

  .product-create-page .table-modern tbody tr:last-child {
    border-bottom: none;
  }

  .product-create-page .table-modern td {
    padding: 0.85rem 0.875rem;
    vertical-align: middle;
    color: var(--pc-text);
  }

  /* ===== Variant Input ===== */
  .product-create-page .variant-input-group {
    background: linear-gradient(135deg, #f5f7ff 0%, #eef2ff 100%);
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid rgba(99, 102, 241, 0.15);
  }

  /* ===== Combo Section ===== */
  .product-create-page .combo-search {
    background: var(--pc-bg-soft);
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid var(--pc-border-soft);
  }

  .autocomplete {
    position: relative;
  }

  .product-create-page .autocomplete-input {
    width: 100%;
    padding: 0.55rem 1rem;
    border-radius: 9px;
    border: 1.5px solid var(--pc-border);
    font-size: 0.92rem;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
  }

  .product-create-page .autocomplete-input:focus {
    outline: none;
    border-color: var(--pc-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
  }

  .autocomplete-result-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e0e0e0;
    border-top: none;
    border-radius: 0 0 8px 8px;
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 250px;
    overflow-y: auto;
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }

  .autocomplete-result {
    padding: 0.75rem 3rem;
    cursor: pointer;
    transition: background-color 0.15s ease;
  }

  .autocomplete-result:hover {
    background-color: #f5f7ff;
    color: #667eea;
  }

  .badge-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  .product-create-page .badge-primary-light {
    background-color: var(--pc-primary-soft);
    color: var(--pc-primary-strong);
    padding: 0.32rem 0.7rem;
    border-radius: 999px;
    font-weight: 600;
    display: inline-block;
    width: fit-content;
    font-size: 0.78rem;
  }

  .product-create-page .combo-total {
    background: linear-gradient(135deg, #f5f7ff 0%, #eef2ff 100%);
    padding: 1rem 1.25rem;
    border-radius: 12px;
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-left: 4px solid var(--pc-primary);
  }

  .product-create-page .total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .product-create-page .total-label {
    font-weight: 600;
    color: var(--pc-text-soft);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .product-create-page .total-value {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--pc-primary-strong);
    letter-spacing: -0.02em;
  }

  /* ===== Options Grid ===== */
  .product-create-page .options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 0.85rem 1.25rem;
  }

  .product-create-page .options-grid .form-group {
    background: var(--pc-bg-soft);
    border: 1px solid var(--pc-border-soft);
    border-radius: 10px;
    padding: 0.7rem 0.85rem;
    margin-bottom: 0;
    transition: border-color 0.18s ease, background 0.18s ease;
  }

  .product-create-page .options-grid .form-group:hover {
    border-color: rgba(99, 102, 241, 0.3);
    background: #f5f7ff;
  }

  .product-create-page .options-grid .custom-control-label {
    font-weight: 500;
    color: var(--pc-text);
    font-size: 0.88rem;
  }

  /* ===== Form Actions ===== */
  .product-create-page .form-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .product-create-page .spinner-inline {
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  /* ===== Generic input-group border / radius fix =====
     The 1.5px border + 9px radius I put on .form-control/.custom-select
     above doesn't match Bootstrap's default 1px / 0.25rem on
     .input-group-text and .btn inside append/prepend, which produces a
     mismatched seam where they meet. Unify border thickness, color and
     corner-rounding here. */
  .product-create-page .input-group {
    border-radius: 9px;
  }

  .product-create-page .input-group > .form-control,
  .product-create-page .input-group > .custom-select,
  .product-create-page .input-group > .input-group-prepend > .input-group-text,
  .product-create-page .input-group > .input-group-append > .input-group-text,
  .product-create-page .input-group > .input-group-prepend > .btn,
  .product-create-page .input-group > .input-group-append > .btn {
    border: 1.5px solid var(--pc-border);
  }

  /* Inner edges flat – outer edges keep the 9px radius. The
     :not(.modern-input-group) guard avoids fighting the prepend+append
     barcode group which has its own wrapper border. */
  .product-create-page .input-group:not(.modern-input-group) > .form-control:not(:first-child),
  .product-create-page .input-group:not(.modern-input-group) > .custom-select:not(:first-child) {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
  }

  .product-create-page .input-group:not(.modern-input-group) > .form-control:not(:last-child),
  .product-create-page .input-group:not(.modern-input-group) > .custom-select:not(:last-child) {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
  }

  .product-create-page .input-group:not(.modern-input-group) > .input-group-prepend > .input-group-text,
  .product-create-page .input-group:not(.modern-input-group) > .input-group-prepend > .btn {
    border-top-left-radius: 9px;
    border-bottom-left-radius: 9px;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right-width: 0;
  }

  .product-create-page .input-group:not(.modern-input-group) > .input-group-append > .input-group-text,
  .product-create-page .input-group:not(.modern-input-group) > .input-group-append > .btn {
    border-top-right-radius: 9px;
    border-bottom-right-radius: 9px;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    border-left-width: 0;
  }

  /* Append/prepend appearance */
  .product-create-page .input-group > .input-group-append > .input-group-text,
  .product-create-page .input-group > .input-group-prepend > .input-group-text {
    background: #f8fafc;
    color: var(--pc-text-soft);
    font-weight: 600;
  }

  /* Unified focus ring across the whole group */
  .product-create-page .input-group:focus-within > .form-control,
  .product-create-page .input-group:focus-within > .custom-select,
  .product-create-page .input-group:focus-within > .input-group-prepend > .input-group-text,
  .product-create-page .input-group:focus-within > .input-group-append > .input-group-text,
  .product-create-page .input-group:focus-within > .input-group-prepend > .btn,
  .product-create-page .input-group:focus-within > .input-group-append > .btn {
    border-color: var(--pc-primary);
  }

  .product-create-page .input-group:focus-within {
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
  }

  /* Don't double-up the focus ring inside the group. */
  .product-create-page .input-group > .form-control:focus,
  .product-create-page .input-group > .custom-select:focus {
    box-shadow: none;
  }

  /* ===== Responsive ===== */
  @media (max-width: 991.98px) {
    .product-create-page .sticky-sidebar {
      position: relative;
      top: 0;
    }
  }

  @media (max-width: 768px) {
    .product-create-page .page-hero {
      padding: 1.25rem 1.25rem;
    }
    .product-create-page .page-hero__title {
      font-size: 1.4rem;
    }
    .product-create-page .page-hero__actions {
      width: 100%;
      justify-content: flex-end;
    }
    .product-create-page .options-grid {
      grid-template-columns: 1fr;
    }
    .product-create-page .form-action-bar__inner {
      flex-direction: column-reverse;
      align-items: stretch;
      gap: 0.6rem;
      padding: 0.75rem;
    }
    .product-create-page .form-action-bar__buttons {
      width: 100%;
      justify-content: space-between;
    }
    .product-create-page .form-action-bar__buttons .btn {
      flex: 1 1 0;
      justify-content: center;
    }
    .product-create-page .form-action-bar__hint {
      font-size: 0.78rem;
    }
    .product-create-page .form-actions-spacer {
      height: 130px;
    }
    .product-create-page .section-card .card-body {
      padding: 1.1rem 1.15rem;
    }
  }

  /* ===========================================================
     Dark mode — repaint the page by overriding the CSS custom
     properties on .product-create-page, then patching the rules
     that hard-code hex values instead of using the vars.
     ----------------------------------------------------------- */
  .dark-theme .product-create-page {
    --pc-primary:        #818cf8;
    --pc-primary-strong: #a78bfa;
    --pc-primary-soft:   rgba(129, 140, 248, 0.16);
    --pc-accent:         #c4b5fd;
    --pc-success:        #34d399;
    --pc-warn:           #fbbf24;
    --pc-danger:         #f87171;
    --pc-text:           #d8d8d8;
    --pc-text-soft:      rgba(216, 216, 216, 0.82);
    --pc-text-muted:     rgba(216, 216, 216, 0.6);
    --pc-border:         #2a2a2a;
    --pc-border-soft:    #232323;
    --pc-bg:             #1a1a1a;
    --pc-bg-soft:        #232323;
    --pc-card:           #202020;
    --pc-shadow-sm:      0 1px 2px rgba(0, 0, 0, 0.3);
    --pc-shadow:         0 1px 3px rgba(0, 0, 0, 0.4), 0 6px 20px rgba(0, 0, 0, 0.3);
    --pc-shadow-lg:      0 10px 30px rgba(0, 0, 0, 0.5);
  }

  /* Hero — replace the white gradient + faint indigo glow */
  .dark-theme .product-create-page .page-hero {
    background:
      radial-gradient(circle at 0% 0%, rgba(139, 92, 246, 0.18), transparent 55%),
      radial-gradient(circle at 100% 100%, rgba(99, 102, 241, 0.18), transparent 55%),
      linear-gradient(135deg, #202020 0%, #1a1a1a 100%);
    border-color: rgba(129, 140, 248, 0.25);
  }

  /* Pills + chips that bypass the variables */
  .dark-theme .product-create-page .pill {
    background: #292929;
    color: var(--pc-text-soft);
    border-color: var(--pc-border);
  }
  .dark-theme .product-create-page .summary-row__value.mono {
    background: #292929;
    color: var(--pc-text-soft);
  }
  .dark-theme .product-create-page .label-help-icon {
    background: #292929;
    border-color: var(--pc-border);
  }

  /* Input group append/prepend */
  .dark-theme .product-create-page .input-group > .input-group-append > .input-group-text,
  .dark-theme .product-create-page .input-group > .input-group-prepend > .input-group-text {
    background: #292929;
    color: var(--pc-text-soft);
  }

  /* Form fields */
  .dark-theme .product-create-page .form-control,
  .dark-theme .product-create-page .custom-select,
  .dark-theme .product-create-page textarea.form-control,
  .dark-theme .product-create-page .form-control-modern,
  .dark-theme .product-create-page .autocomplete-input {
    background: #1a1a1a;
    color: var(--pc-text);
    border-color: var(--pc-border);
  }
  .dark-theme .product-create-page .form-control::placeholder,
  .dark-theme .product-create-page textarea.form-control::placeholder,
  .dark-theme .product-create-page .autocomplete-input::placeholder {
    color: rgba(216, 216, 216, 0.45);
  }

  /* TOC sidebar — overpower the global `a { color: !important }` rule */
  .dark-theme .product-create-page .side-toc__list a {
    color: var(--pc-text-soft) !important;
  }
  .dark-theme .product-create-page .side-toc__list a:hover {
    background: var(--pc-primary-soft);
    color: var(--pc-primary-strong) !important;
  }
  .dark-theme .product-create-page .side-toc__list a:hover svg {
    color: var(--pc-primary);
  }

  /* Tables */
  .dark-theme .product-create-page .table-modern thead {
    background: linear-gradient(180deg, #292929 0%, #202020 100%);
    border-bottom-color: var(--pc-border);
  }

  /* Modern input group icon buttons (scan / generate) */
  .dark-theme .product-create-page .modern-input-group .btn-icon-scan,
  .dark-theme .product-create-page .modern-input-group .btn-icon-gen {
    background: #292929;
    color: var(--pc-primary-strong);
  }
  .dark-theme .product-create-page .modern-input-group .btn-icon-scan:hover,
  .dark-theme .product-create-page .modern-input-group .btn-icon-gen:hover {
    background: var(--pc-primary);
    color: #fff;
  }

  /* Options grid hover */
  .dark-theme .product-create-page .options-grid .form-group:hover {
    background: var(--pc-primary-soft);
  }

  /* Variant + combo blocks (light indigo gradient) */
  .dark-theme .product-create-page .variant-input-group,
  .dark-theme .product-create-page .combo-total {
    background: linear-gradient(135deg, rgba(129, 140, 248, 0.12) 0%, rgba(129, 140, 248, 0.06) 100%);
    border-color: rgba(129, 140, 248, 0.25);
  }

  /* Floating save bar — fade-in mask must match dark page bg, not #f8fafc */
  .dark-theme .product-create-page .form-action-bar {
    background: linear-gradient(180deg, rgba(26, 26, 26, 0) 0%, rgba(26, 26, 26, 0.85) 35%, #1a1a1a 70%);
  }

  /* Gallery card (uses literal hex, not variables) */
  .dark-theme .gallery-item-card {
    background: linear-gradient(145deg, #202020 0%, #232323 100%);
    border-color: #2a2a2a;
    box-shadow:
      0 1px 2px rgba(0, 0, 0, 0.3),
      0 4px 12px rgba(0, 0, 0, 0.25);
  }
  .dark-theme .gallery-item-card::before {
    background: linear-gradient(180deg, #2a2a2a 0%, #232323 100%);
  }
  .dark-theme .gallery-item-card--main {
    background: linear-gradient(145deg, rgba(102, 126, 234, 0.15) 0%, rgba(102, 126, 234, 0.08) 55%, #202020 100%);
    border-color: rgba(102, 126, 234, 0.45);
  }
  .dark-theme .gallery-item-card:hover {
    border-color: rgba(129, 140, 248, 0.35);
    box-shadow:
      0 2px 6px rgba(0, 0, 0, 0.4),
      0 10px 28px rgba(0, 0, 0, 0.4);
  }
  .dark-theme .gallery-item-meta .text-truncate {
    color: #d8d8d8;
  }
  .dark-theme .gallery-drag-handle {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(216, 216, 216, 0.6);
  }
  .dark-theme .gallery-item-card:hover .gallery-drag-handle {
    background: rgba(255, 255, 255, 0.08);
    color: #d8d8d8;
  }

  /* Gallery dropzone */
  .dark-theme .product-gallery-dropzone {
    background: linear-gradient(180deg, #232323 0%, #1f1f1f 100%);
    border-color: #2f2f2f;
  }
  .dark-theme .product-gallery-dropzone:hover:not(.product-gallery-dropzone-disabled) {
    background: rgba(102, 126, 234, 0.08);
    border-color: #818cf8;
  }
  .dark-theme .product-gallery-dropzone-title {
    color: #d8d8d8;
  }
  .dark-theme .product-gallery-empty {
    background: #1f1f1f;
    border-color: #2a2a2a;
  }

  /* Legacy image-upload wrapper */
  .dark-theme .image-upload-wrapper {
    background: #1f1f1f;
    border-color: #2a2a2a;
  }
  .dark-theme .image-upload-wrapper:hover {
    background: rgba(102, 126, 234, 0.08);
    border-color: #818cf8;
  }

  /* Autocomplete results dropdown (combo product search) */
  .dark-theme .autocomplete-result-list {
    background: #202020;
    border-color: #2a2a2a;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
  }
  .dark-theme .autocomplete-result {
    color: #d8d8d8;
  }
  .dark-theme .autocomplete-result:hover {
    background: rgba(129, 140, 248, 0.15);
    color: #a78bfa;
  }
</style>
