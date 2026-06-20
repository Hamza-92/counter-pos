<template>
  <div class="main-content">
    <breadcumb :page="$t('ProductDetails')" :folder="$t('Products')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div
      v-if="!isLoading"
      class="pd-root"
      :style="rootStyle"
    >
      <!-- Top action bar -->
      <div
        class="pd-actions"
        :style="{
          display: 'flex',
          justifyContent: 'flex-end',
          alignItems: 'center',
          marginBottom: '16px',
          gap: '10px'
        }"
      >
        <button
          @click="goBack"
          :style="backBtnStyle"
          @mouseover="onBackBtnHover($event, true)"
          @mouseleave="onBackBtnHover($event, false)"
        >
          <lucide-icon name="arrow-left" :style="{ marginRight: '6px' }" />
          {{ $t('back') || 'Back' }}
        </button>
        <button
          @click="print_product()"
          :style="printBtnStyle"
          onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 16px rgba(79,70,229,0.35)'"
          onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 10px rgba(79,70,229,0.25)'"
        >
          <lucide-icon name="receipt" :style="{ marginRight: '6px' }" />
          {{ $t('print') }}
        </button>
      </div>

      <div id="print_product">
        <!-- Hero / Header -->
        <div
          class="pd-hero"
          :style="{
            background: 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)',
            borderRadius: '16px',
            padding: '28px',
            color: '#fff',
            marginBottom: '24px',
            boxShadow: '0 10px 30px rgba(79,70,229,0.25)',
            position: 'relative',
            overflow: 'hidden'
          }"
        >
          <div
            :style="{
              position: 'absolute',
              right: '-60px',
              top: '-60px',
              width: '220px',
              height: '220px',
              borderRadius: '50%',
              background: 'rgba(255,255,255,0.08)'
            }"
          ></div>
          <div
            :style="{
              position: 'absolute',
              right: '40px',
              bottom: '-80px',
              width: '180px',
              height: '180px',
              borderRadius: '50%',
              background: 'rgba(255,255,255,0.06)'
            }"
          ></div>

          <div class="pd-hero-row" :style="{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '24px', position: 'relative', zIndex: 1 }">
            <div
              class="pd-hero-img"
              :style="{
                width: '140px',
                height: '140px',
                borderRadius: '14px',
                background: '#fff',
                flexShrink: 0,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                overflow: 'hidden',
                boxShadow: '0 6px 20px rgba(0,0,0,0.15)'
              }"
            >
              <img
                :src="'/images/products/' + (productImages[0] || product.image || 'no-image.png')"
                :alt="product.name"
                :style="{ width: '100%', height: '100%', objectFit: 'contain' }"
                @error="onImgError"
              />
            </div>

            <div :style="{ flex: '1', minWidth: '240px' }">
              <div :style="{ display: 'flex', gap: '8px', flexWrap: 'wrap', marginBottom: '10px' }">
                <span :style="heroBadge('rgba(255,255,255,0.2)')">{{ product.type_name }}</span>
                <span :style="heroBadge('rgba(16,185,129,0.85)')" v-if="product.code">
                  <lucide-icon name="code" :style="{ marginRight: '4px' }" />{{ product.code }}
                </span>
                <span :style="heroBadge('rgba(245,158,11,0.9)')" v-if="product.brand && product.brand !== 'N/D'">
                  {{ product.brand }}
                </span>
              </div>
              <h1 class="pd-hero-title" :style="{ margin: '0 0 10px 0', fontSize: '28px', fontWeight: '700', lineHeight: '1.2' }">
                {{ product.name }}
              </h1>
              <div :style="{ display: 'flex', flexWrap: 'wrap', gap: '16px', fontSize: '14px', opacity: '0.95' }">
                <span v-if="categoriesLine">
                  <lucide-icon name="folder" :style="{ marginRight: '4px' }" />{{ categoriesLine }}
                </span>
                <span v-if="subcategoriesLine">
                  <lucide-icon name="rows-2" :style="{ marginRight: '4px' }" />{{ subcategoriesLine }}
                </span>
                <span v-if="product.unit && product.unit !== '----'">
                  <lucide-icon name="ruler" :style="{ marginRight: '4px' }" />{{ product.unit }}
                </span>
              </div>
            </div>

            <div
              class="pd-hero-price"
              :style="{
                background: 'rgba(255,255,255,0.15)',
                padding: '16px 22px',
                borderRadius: '12px',
                textAlign: 'center',
                backdropFilter: 'blur(6px)',
                minWidth: '160px'
              }"
              v-if="product.type != 'is_variant'"
            >
              <div :style="{ fontSize: '12px', opacity: '0.8', textTransform: 'uppercase', letterSpacing: '1px' }">
                {{ $t('Price') }}
              </div>
              <div :style="{ fontSize: '24px', fontWeight: '700', marginTop: '4px' }">
                {{ formatPriceWithSymbol(currentUser && currentUser.currency, product.price, 2) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Quick stats -->
        <div
          :style="{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
            gap: '16px',
            marginBottom: '24px'
          }"
          v-if="product.type != 'is_variant'"
        >
          <div :style="statCardStyle('#4f46e5', '#eef2ff')" v-if="product.type == 'is_single' || product.type == 'is_combo'">
            <div :style="statIconStyle('#4f46e5')"><lucide-icon name="wallet" /></div>
            <div>
              <div :style="statLabelStyle">{{ $t('Cost') }}</div>
              <div :style="statValueStyle">{{ formatPriceWithSymbol(currentUser && currentUser.currency, product.cost, 2) }}</div>
            </div>
          </div>

          <div :style="statCardStyle('#10b981', '#ecfdf5')">
            <div :style="statIconStyle('#10b981')"><lucide-icon name="tag" /></div>
            <div>
              <div :style="statLabelStyle">{{ $t('Price') }}</div>
              <div :style="statValueStyle">{{ formatPriceWithSymbol(currentUser && currentUser.currency, product.price, 2) }}</div>
            </div>
          </div>

          <div :style="statCardStyle('#f59e0b', '#fffbeb')">
            <div :style="statIconStyle('#f59e0b')"><lucide-icon name="store" /></div>
            <div>
              <div :style="statLabelStyle">{{ $t('Wholesale_Price') }}</div>
              <div :style="statValueStyle">{{ formatPriceWithSymbol(currentUser && currentUser.currency, product.wholesale_price, 2) }}</div>
            </div>
          </div>

          <div :style="statCardStyle('#ef4444', '#fef2f2')">
            <div :style="statIconStyle('#ef4444')"><lucide-icon name="chevron-down" /></div>
            <div>
              <div :style="statLabelStyle">{{ $t('MinPrice') }}</div>
              <div :style="statValueStyle">{{ formatPriceWithSymbol(currentUser && currentUser.currency, product.min_price, 2) }}</div>
            </div>
          </div>

          <div :style="statCardStyle('#0ea5e9', '#f0f9ff')" v-if="product.type != 'is_service'">
            <div :style="statIconStyle('#0ea5e9')"><lucide-icon name="bell" /></div>
            <div>
              <div :style="statLabelStyle">{{ $t('StockAlert') }}</div>
              <div :style="statValueStyle">{{ formatNumber(product.stock_alert, 2) }}</div>
            </div>
          </div>

          <div :style="statCardStyle('#8b5cf6', '#f5f3ff')" v-if="product.points">
            <div :style="statIconStyle('#8b5cf6')"><lucide-icon name="medal" /></div>
            <div>
              <div :style="statLabelStyle">{{ $t('Points') || 'Points' }}</div>
              <div :style="statValueStyle">{{ product.points }}</div>
            </div>
          </div>
        </div>

        <!-- Main two-column grid -->
        <div
          class="pd-main-grid"
          :style="{
            display: 'grid',
            gridTemplateColumns: 'minmax(0, 2fr) minmax(0, 1fr)',
            gap: '24px',
            marginBottom: '24px'
          }"
        >
          <!-- LEFT: details + barcode -->
          <div>
            <!-- Barcode card -->
            <div :style="cardStyle" v-if="product.type != 'is_variant' && product.code">
              <div :style="cardHeaderStyle">
                <lucide-icon name="barcode" :style="{ marginRight: '8px', color: '#4f46e5' }" />
                {{ $t('Barcode') || 'Barcode' }}
              </div>
              <div :style="{ padding: '20px', textAlign: 'center' }">
                <barcode
                  class="barcode"
                  :format="product.Type_barcode"
                  :value="product.code"
                  textmargin="0"
                  fontoptions="bold"
                ></barcode>
              </div>
            </div>

            <!-- Information card -->
            <div :style="cardStyle">
              <div :style="cardHeaderStyle">
                <lucide-icon name="info" :style="{ marginRight: '8px', color: '#4f46e5' }" />
                {{ $t('ProductDetails') }}
              </div>
              <div :style="{ padding: '8px 20px 20px 20px' }">
                <div class="pd-info-grid" :style="infoGrid">
                  <div :style="infoRow"><span :style="infoKey">{{ $t('type') }}</span><span :style="infoVal">{{ product.type_name }}</span></div>
                  <div :style="infoRow"><span :style="infoKey">{{ $t('CodeProduct') }}</span><span :style="infoVal">{{ product.code }}</span></div>
                  <div :style="infoRow"><span :style="infoKey">{{ $t('ProductName') }}</span><span :style="infoVal">{{ product.name }}</span></div>
                  <div :style="infoRow"><span :style="infoKey">{{ $t('Categorie') }}</span><span :style="infoVal">{{ categoriesLine || '—' }}</span></div>
                  <div :style="infoRow" v-if="subcategoriesLine"><span :style="infoKey">{{ $t('SubCategory') }}</span><span :style="infoVal">{{ subcategoriesLine }}</span></div>
                  <div :style="infoRow"><span :style="infoKey">{{ $t('Brand') }}</span><span :style="infoVal">{{ product.brand }}</span></div>

                  <div :style="infoRow" v-if="product.type == 'is_single' || product.type == 'is_combo'">
                    <span :style="infoKey">{{ $t('Cost') }}</span>
                    <span :style="infoValAccent('#4f46e5')">{{ formatPriceWithSymbol(currentUser && currentUser.currency, product.cost, 2) }}</span>
                  </div>
                  <div :style="infoRow" v-if="product.type != 'is_variant'">
                    <span :style="infoKey">{{ $t('Price') }}</span>
                    <span :style="infoValAccent('#10b981')">{{ formatPriceWithSymbol(currentUser && currentUser.currency, product.price, 2) }}</span>
                  </div>
                  <div :style="infoRow" v-if="product.type != 'is_variant'">
                    <span :style="infoKey">{{ $t('Wholesale_Price') }}</span>
                    <span :style="infoValAccent('#f59e0b')">{{ formatPriceWithSymbol(currentUser && currentUser.currency, product.wholesale_price, 2) }}</span>
                  </div>
                  <div :style="infoRow" v-if="product.type != 'is_variant'">
                    <span :style="infoKey">{{ $t('MinPrice') }}</span>
                    <span :style="infoValAccent('#ef4444')">{{ formatPriceWithSymbol(currentUser && currentUser.currency, product.min_price, 2) }}</span>
                  </div>

                  <div :style="infoRow" v-if="product.type != 'is_service'">
                    <span :style="infoKey">{{ $t('Unit') }}</span>
                    <span :style="infoVal">{{ product.unit }}</span>
                  </div>

                  <div :style="infoRow">
                    <span :style="infoKey">{{ $t('Tax') }}</span>
                    <span :style="infoVal">{{ formatNumber(product.taxe, 2) }} %</span>
                  </div>
                  <div :style="infoRow" v-if="product.taxe != '0.00'">
                    <span :style="infoKey">{{ $t('TaxMethod') }}</span>
                    <span :style="{ ...infoVal, ...pillStyle(product.tax_method === 'Exclusive' ? '#0ea5e9' : '#8b5cf6') }">{{ product.tax_method }}</span>
                  </div>

                  <div :style="infoRow">
                    <span :style="infoKey">{{ $t('Discount') }}</span>
                    <span :style="{ ...infoVal, ...pillStyle('#ef4444') }">{{ product.discount }}</span>
                  </div>

                  <div :style="infoRow" v-if="product.type != 'is_service'">
                    <span :style="infoKey">{{ $t('StockAlert') }}</span>
                    <span :style="{ ...infoVal, ...pillStyle('#f59e0b') }">{{ formatNumber(product.stock_alert, 2) }}</span>
                  </div>

                  <div :style="infoRow" v-if="product.type != 'is_service' && product.weight">
                    <span :style="infoKey">{{ $t('Weight') }}</span>
                    <span :style="infoVal">{{ formatNumber(product.weight, 2) }}</span>
                  </div>

                  <div :style="infoRow" v-if="product.points">
                    <span :style="infoKey">{{ $t('Points') || 'Points' }}</span>
                    <span :style="{ ...infoVal, ...pillStyle('#8b5cf6') }">{{ product.points }}</span>
                  </div>

                  <div :style="infoRow" v-if="product.Type_barcode">
                    <span :style="infoKey">{{ $t('BarcodeSymbology') || 'Barcode Type' }}</span>
                    <span :style="infoVal">{{ product.Type_barcode }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Warranty & Guarantee card -->
            <div :style="cardStyle" v-if="product.warranty_period || product.warranty_terms || product.has_guarantee">
              <div :style="cardHeaderStyle">
                <lucide-icon name="shield" :style="{ marginRight: '8px', color: '#0ea5e9' }" />
                {{ $t('Warranty') || 'Warranty & Guarantee' }}
              </div>
              <div :style="{ padding: '8px 20px 20px 20px' }">
                <div class="pd-info-grid" :style="infoGrid">
                  <div :style="infoRow" v-if="product.warranty_period !== null && product.warranty_period">
                    <span :style="infoKey">{{ $t('Warranty_Period') }}</span>
                    <span :style="{ ...infoVal, ...pillStyle('#0ea5e9') }">
                      {{ product.warranty_period }} {{ $t(product.warranty_unit) }}
                    </span>
                  </div>
                  <div :style="infoRow" v-if="product.warranty_terms">
                    <span :style="infoKey">{{ $t('WarrantyTerms') }}</span>
                    <span :style="infoVal">{{ product.warranty_terms }}</span>
                  </div>
                  <div :style="infoRow" v-if="product.has_guarantee">
                    <span :style="infoKey">{{ $t('Guarantee_Period') }}</span>
                    <span :style="{ ...infoVal, ...pillStyle('#10b981') }">
                      {{ product.guarantee_period }} {{ $t(product.guarantee_unit) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Note card -->
            <div :style="cardStyle" v-if="product.note">
              <div :style="cardHeaderStyle">
                <lucide-icon name="file-pen" :style="{ marginRight: '8px', color: '#8b5cf6' }" />
                {{ $t('Note') || 'Note' }}
              </div>
              <div
                :style="{
                  padding: '20px',
                  color: pdTheme.noteText,
                  lineHeight: '1.6',
                  whiteSpace: 'pre-wrap',
                  background: pdTheme.noteBg,
                  margin: '0 20px 20px 20px',
                  borderRadius: '10px',
                  borderLeft: '4px solid #8b5cf6'
                }"
              >
                {{ product.note }}
              </div>
            </div>
          </div>

          <!-- RIGHT: gallery -->
          <div>
            <div :style="cardStyle">
              <div :style="cardHeaderStyle">
                <lucide-icon name="image" :style="{ marginRight: '8px', color: '#10b981' }" />
                {{ $t('Images') || 'Gallery' }}
                <span
                  :style="{
                    marginLeft: '8px',
                    background: '#10b981',
                    color: '#fff',
                    fontSize: '11px',
                    padding: '2px 8px',
                    borderRadius: '999px'
                  }"
                  v-if="productImages.length"
                >{{ productImages.length }}</span>
              </div>
              <div :style="{ padding: '20px' }">
                <div
                  :style="{
                    width: '100%',
                    height: '320px',
                    borderRadius: '12px',
                    overflow: 'hidden',
                    background: pdTheme.galleryFrameBg,
                    border: `1px solid ${pdTheme.galleryFrameBorder}`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginBottom: '12px'
                  }"
                >
                  <img
                    :src="'/images/products/' + activeImage"
                    :alt="product.name"
                    :style="{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }"
                    @error="onImgError"
                  />
                </div>

                <div
                  :style="{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fill, minmax(64px, 1fr))',
                    gap: '8px'
                  }"
                  v-if="productImages.length > 1"
                >
                  <div
                    v-for="(img, idx) in productImages"
                    :key="idx"
                    @click="activeImageIndex = idx"
                    :style="{
                      width: '100%',
                      paddingTop: '100%',
                      position: 'relative',
                      borderRadius: '8px',
                      overflow: 'hidden',
                      cursor: 'pointer',
                      border: activeImageIndex === idx ? '2px solid #4f46e5' : '2px solid transparent',
                      boxShadow: activeImageIndex === idx ? '0 2px 8px rgba(79,70,229,0.25)' : '0 1px 3px rgba(0,0,0,0.05)',
                      transition: 'all 0.2s'
                    }"
                  >
                    <img
                      :src="'/images/products/' + img"
                      :style="{
                        position: 'absolute',
                        top: 0, left: 0,
                        width: '100%', height: '100%',
                        objectFit: 'cover',
                        background: pdTheme.thumbInnerBg
                      }"
                      @error="onImgError"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Combo products -->
        <div :style="cardStyle" v-if="product.type == 'is_combo' && product.products_combo_data && product.products_combo_data.length">
          <div :style="cardHeaderStyle">
            <lucide-icon name="receipt-text" :style="{ marginRight: '8px', color: '#f59e0b' }" />
            {{ $t('Combined_Products') || 'Combined Products' }}
          </div>
          <div :style="{ padding: '8px 20px 20px 20px', overflowX: 'auto' }">
            <table :style="tableStyle">
              <thead>
                <tr>
                  <th :style="thStyle">{{ $t('Product_Code') || 'Product Code' }}</th>
                  <th :style="thStyle">{{ $t('Product_Name') || 'Product Name' }}</th>
                  <th :style="{ ...thStyle, textAlign: 'right' }">{{ $t('Quantity') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(pc, i) in product.products_combo_data" :key="i" :style="trHover">
                  <td :style="tdStyle"><code :style="codeStyle">{{ pc.code }}</code></td>
                  <td :style="tdStyle">{{ pc.name }}</td>
                  <td :style="{ ...tdStyle, textAlign: 'right', fontWeight: '600' }">{{ pc.quantity }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Variants -->
        <div :style="cardStyle" v-if="product.type == 'is_variant' && product.products_variants_data && product.products_variants_data.length">
          <div :style="cardHeaderStyle">
            <lucide-icon name="columns-2" :style="{ marginRight: '8px', color: '#7c3aed' }" />
            {{ $t('Variants') || 'Variants' }}
          </div>
          <div :style="{ padding: '8px 20px 20px 20px', overflowX: 'auto' }">
            <table :style="tableStyle">
              <thead>
                <tr>
                  <th :style="thStyle">{{ $t('Variant_code') }}</th>
                  <th :style="thStyle">{{ $t('Variant_Name') }}</th>
                  <th :style="{ ...thStyle, textAlign: 'right' }">{{ $t('Variant_cost') }}</th>
                  <th :style="{ ...thStyle, textAlign: 'right' }">{{ $t('Variant_price') }}</th>
                  <th :style="{ ...thStyle, textAlign: 'right' }">{{ $t('Wholesale_Price') }}</th>
                  <th :style="{ ...thStyle, textAlign: 'right' }">{{ $t('Min_Selling_Price') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(v, i) in product.products_variants_data" :key="i" :style="trHover">
                  <td :style="tdStyle"><code :style="codeStyle">{{ v.code }}</code></td>
                  <td :style="tdStyle">{{ v.name }}</td>
                  <td :style="{ ...tdStyle, textAlign: 'right', color: '#4f46e5' }">{{ formatPriceWithSymbol(currentUser && currentUser.currency, v.cost, 2) }}</td>
                  <td :style="{ ...tdStyle, textAlign: 'right', color: '#10b981', fontWeight: '600' }">{{ formatPriceWithSymbol(currentUser && currentUser.currency, v.price, 2) }}</td>
                  <td :style="{ ...tdStyle, textAlign: 'right', color: '#f59e0b' }">{{ formatPriceWithSymbol(currentUser && currentUser.currency, v.wholesale, 2) }}</td>
                  <td :style="{ ...tdStyle, textAlign: 'right', color: '#ef4444' }">{{ formatPriceWithSymbol(currentUser && currentUser.currency, v.min_price, 2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Warehouse Quantity (single) -->
        <div :style="cardStyle" v-if="product.type == 'is_single' && product.CountQTY && product.CountQTY.length">
          <div class="pd-card-header" :style="cardHeaderStyle">
            <lucide-icon name="database" :style="{ marginRight: '8px', color: '#0ea5e9' }" />
            {{ $t('Warehouse_Stock') || 'Warehouse Stock' }}
            <span
              :style="{
                marginLeft: 'auto',
                background: '#0ea5e9',
                color: '#fff',
                fontSize: '12px',
                padding: '2px 10px',
                borderRadius: '999px',
                fontWeight: '600'
              }"
            >
              {{ $t('Total') || 'Total' }}: {{ formatNumber(totalStock, 2) }} {{ product.unit }}
            </span>
          </div>
          <div
            :style="{
              padding: '20px',
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
              gap: '12px'
            }"
          >
            <div
              v-for="(w, i) in product.CountQTY"
              :key="i"
              :style="{
                background: pdTheme.warehouseCardBg,
                border: `1px solid ${pdTheme.warehouseCardBorder}`,
                borderRadius: '12px',
                padding: '14px 16px',
                display: 'flex',
                alignItems: 'center',
                gap: '12px'
              }"
            >
              <div
                :style="{
                  width: '40px', height: '40px',
                  borderRadius: '10px',
                  background: '#0ea5e9',
                  color: '#fff',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontSize: '18px',
                  flexShrink: 0
                }"
              ><lucide-icon name="store" /></div>
              <div :style="{ flex: 1 }">
                <div :style="{ fontSize: '12px', color: pdTheme.warehouseLabel, fontWeight: '600', textTransform: 'uppercase' }">{{ w.mag }}</div>
                <div :style="{ fontSize: '18px', fontWeight: '700', color: pdTheme.warehouseValue }">
                  {{ formatNumber(w.qte || 0, 2) }}
                  <span :style="{ fontSize: '12px', color: pdTheme.warehouseUnit, fontWeight: '500' }">{{ product.unit }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Warehouse Variants Quantity -->
        <div :style="cardStyle" v-if="product.type == 'is_variant' && product.CountQTY_variants && product.CountQTY_variants.length">
          <div :style="cardHeaderStyle">
            <lucide-icon name="database" :style="{ marginRight: '8px', color: '#0ea5e9' }" />
            {{ $t('Warehouse_Variants_Stock') || 'Warehouse Variants Stock' }}
          </div>
          <div :style="{ padding: '8px 20px 20px 20px', overflowX: 'auto' }">
            <table :style="tableStyle">
              <thead>
                <tr>
                  <th :style="thStyle">{{ $t('warehouse') }}</th>
                  <th :style="thStyle">{{ $t('Variant') }}</th>
                  <th :style="{ ...thStyle, textAlign: 'right' }">{{ $t('Quantity') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(w, i) in product.CountQTY_variants" :key="i" :style="trHover">
                  <td :style="tdStyle">
                    <lucide-icon name="store" :style="{ color: '#0ea5e9', marginRight: '6px' }" />{{ w.mag }}
                  </td>
                  <td :style="tdStyle">
                    <span :style="pillStyle('#7c3aed')">{{ w.variant }}</span>
                  </td>
                  <td :style="{ ...tdStyle, textAlign: 'right', fontWeight: '700', color: pdTheme.warehouseValue }">
                    {{ formatNumber(w.qte || 0, 2) }} {{ product.unit }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Batches (Pharmacy) -->
        <div :style="cardStyle" v-if="product.is_batch_tracked">
          <div class="pd-card-header" :style="cardHeaderStyle">
            <lucide-icon name="package" :style="{ marginRight: '8px', color: '#4f46e5' }" />
            {{ $t('Batches') || 'Batches' }}
            <div :style="{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }">
              <span
                :style="{
                  background: '#4f46e5',
                  color: '#fff',
                  fontSize: '12px',
                  padding: '2px 10px',
                  borderRadius: '999px',
                  fontWeight: '600'
                }"
              >
                {{ batches.length }} {{ $t('items') || 'items' }}
              </span>
              <span
                v-if="batches.length"
                :style="{
                  background: '#0ea5e9',
                  color: '#fff',
                  fontSize: '12px',
                  padding: '2px 10px',
                  borderRadius: '999px',
                  fontWeight: '600'
                }"
              >
                {{ $t('Total') || 'Total' }}: {{ formatNumber(batchesTotalQty, 2) }} {{ product.unit }}
              </span>
              <span
                v-if="batchesExpiredCount > 0"
                :style="{
                  background: '#ef4444',
                  color: '#fff',
                  fontSize: '12px',
                  padding: '2px 10px',
                  borderRadius: '999px',
                  fontWeight: '600'
                }"
              >
                {{ batchesExpiredCount }} {{ $t('Expired') || 'Expired' }}
              </span>
              <span
                v-if="batchesNearExpiryCount > 0"
                :style="{
                  background: '#f59e0b',
                  color: '#fff',
                  fontSize: '12px',
                  padding: '2px 10px',
                  borderRadius: '999px',
                  fontWeight: '600'
                }"
              >
                {{ batchesNearExpiryCount }} {{ $t('Near_Expiry') || 'Near Expiry' }}
              </span>
            </div>
          </div>

          <div v-if="batchesLoading" :style="{ padding: '24px', textAlign: 'center', color: pdTheme.keyColor }">
            <div class="spinner spinner-primary" :style="{ display: 'inline-block', marginRight: '10px' }"></div>
            {{ $t('Loading') || 'Loading...' }}
          </div>

          <div v-else-if="!batches.length" :style="{ padding: '24px', textAlign: 'center', color: pdTheme.keyColor, fontStyle: 'italic' }">
            <lucide-icon name="info" :style="{ marginRight: '6px' }" />
            {{ $t('No_Batches_Available') || 'No batches recorded for this product yet.' }}
          </div>

          <div v-else :style="{ padding: '8px 20px 20px 20px', overflowX: 'auto' }">
            <table :style="tableStyle">
              <thead>
                <tr>
                  <th :style="thStyle">{{ $t('Batch_No') || 'Batch No' }}</th>
                  <th :style="thStyle" v-if="hasAnyVariant">{{ $t('Variant') || 'Variant' }}</th>
                  <th :style="thStyle">{{ $t('warehouse') || 'Warehouse' }}</th>
                  <th :style="thStyle">{{ $t('Mfg_Date') || 'Mfg Date' }}</th>
                  <th :style="thStyle">{{ $t('Expiry_Date') || 'Expiry Date' }}</th>
                  <th :style="{ ...thStyle, textAlign: 'right' }">{{ $t('Quantity') || 'Quantity' }}</th>
                  <th :style="{ ...thStyle, textAlign: 'right' }">{{ $t('Cost') || 'Cost' }}</th>
                  <th :style="{ ...thStyle, textAlign: 'center' }">{{ $t('Status') || 'Status' }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="b in batches" :key="b.id" :style="trHover">
                  <td :style="{ ...tdStyle, fontWeight: '700', color: pdTheme.tableCellText }">
                    {{ b.batch_no || '—' }}
                  </td>
                  <td :style="tdStyle" v-if="hasAnyVariant">
                    <span v-if="b.variant_name" :style="pillStyle('#7c3aed')">{{ b.variant_name }}</span>
                    <span v-else :style="{ color: pdTheme.mutedColor }">—</span>
                  </td>
                  <td :style="tdStyle">
                    <lucide-icon name="store" :style="{ color: '#0ea5e9', marginRight: '6px' }" />{{ b.warehouse_name || '—' }}
                  </td>
                  <td :style="tdStyle">
                    <span v-if="b.mfg_date">{{ b.mfg_date }}</span>
                    <span v-else :style="{ color: pdTheme.mutedColor }">—</span>
                  </td>
                  <td :style="tdStyle">
                    <span v-if="b.expiry_date" :style="batchExpiryStyle(b.expiry_bucket)">{{ b.expiry_date }}</span>
                    <span v-else :style="{ color: pdTheme.mutedColor }">—</span>
                    <div v-if="b.expiry_date && Number.isFinite(b.days_to_expiry)" :style="{ fontSize: '11px', color: pdTheme.keyColor, marginTop: '2px' }">
                      <template v-if="b.days_to_expiry < 0">
                        {{ $t('Expired') || 'Expired' }} {{ Math.abs(b.days_to_expiry) }} {{ $t('Days') || 'days' }}
                      </template>
                      <template v-else>
                        {{ $t('Expires_in') || 'Expires in' }} {{ b.days_to_expiry }} {{ $t('Days') || 'days' }}
                      </template>
                    </div>
                  </td>
                  <td :style="{ ...tdStyle, textAlign: 'right', fontWeight: '700', color: pdTheme.warehouseValue }">
                    {{ formatNumber(b.qty || 0, 2) }} {{ product.unit }}
                  </td>
                  <td :style="{ ...tdStyle, textAlign: 'right', color: isDarkMode ? '#a78bfa' : '#4f46e5', fontWeight: '600' }">
                    <span v-if="b.unit_cost != null">{{ currentUser.currency }} {{ formatPriceDisplay(b.unit_cost, 2) }}</span>
                    <span v-else :style="{ color: pdTheme.mutedColor }">—</span>
                  </td>
                  <td :style="{ ...tdStyle, textAlign: 'center' }">
                    <span :style="batchStatusStyle(b.status)">{{ b.status || 'active' }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>


<script>
import VueBarcode from "vue-barcode";
import { mapActions, mapGetters } from "vuex";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: {
    title: "Detail Product"
  },
  components: {
    barcode: VueBarcode
  },

  data() {
    return {
      len: 8,
      isLoading: true,
      product: {},
      roles: {},
      variants: [],
      activeImageIndex: 0,
      price_format_key: null,

      // Pharmacy: per-product batch list (loaded on demand for batch-tracked products).
      batches: [],
      batchesLoading: false,
      expiryWarningDays: 90
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    ...mapGetters("config", ["getThemeMode"]),

    isDarkMode() {
      return !!(this.getThemeMode && this.getThemeMode.dark);
    },

    // Single source of color truth for the page. Every style object below
    // pulls from here, so flipping dark mode flips the whole page.
    pdTheme() {
      return this.isDarkMode ? {
        pageBg:           'linear-gradient(135deg, #1a1a1a 0%, #202020 100%)',
        cardBg:           '#202020',
        cardBorder:       '#292929',
        cardShadow:       '0 2px 12px rgba(0,0,0,0.4)',
        cardHeaderBg:     'linear-gradient(180deg, #292929 0%, #202020 100%)',
        cardHeaderText:   '#d8d8d8',
        keyColor:         'rgba(216,216,216,0.7)',
        valueColor:       '#d8d8d8',
        labelColor:       'rgba(216,216,216,0.7)',
        mutedColor:       'rgba(216,216,216,0.5)',
        dashedBorder:     '#292929',
        tableHeaderBg:    '#292929',
        tableHeaderText:  'rgba(216,216,216,0.8)',
        tableHeaderRule:  '#292929',
        tableRowRule:     '#292929',
        tableCellText:    '#d8d8d8',
        codeBg:           '#292929',
        codeText:         '#a78bfa',
        noteBg:           '#292929',
        noteText:         'rgba(216,216,216,0.85)',
        warehouseCardBg:  'linear-gradient(135deg, rgba(14,165,233,0.12) 0%, rgba(14,165,233,0.05) 100%)',
        warehouseCardBorder: 'rgba(14,165,233,0.35)',
        warehouseLabel:   'rgba(216,216,216,0.7)',
        warehouseValue:   '#bae6fd',
        warehouseUnit:    'rgba(216,216,216,0.6)',
        galleryFrameBg:   '#292929',
        galleryFrameBorder: '#292929',
        thumbInnerBg:     '#292929',
        backBtnBg:        'transparent',
        backBtnColor:     '#d8d8d8',
        backBtnBorder:    'rgba(216,216,216,0.25)',
        backBtnHoverBg:   'rgba(216,216,216,0.08)',
        backBtnHoverFg:   '#a78bfa'
      } : {
        pageBg:           'linear-gradient(135deg, #f8f9fc 0%, #eef2f7 100%)',
        cardBg:           '#ffffff',
        cardBorder:       '#eef2f7',
        cardShadow:       '0 2px 12px rgba(15,23,42,0.06)',
        cardHeaderBg:     'linear-gradient(180deg, #fafbff 0%, #ffffff 100%)',
        cardHeaderText:   '#1e293b',
        keyColor:         '#64748b',
        valueColor:       '#0f172a',
        labelColor:       '#64748b',
        mutedColor:       '#94a3b8',
        dashedBorder:     '#e5e7eb',
        tableHeaderBg:    '#f8fafc',
        tableHeaderText:  '#64748b',
        tableHeaderRule:  '#e5e7eb',
        tableRowRule:     '#f1f5f9',
        tableCellText:    '#1e293b',
        codeBg:           '#f1f5f9',
        codeText:         '#4f46e5',
        noteBg:           '#f8fafc',
        noteText:         '#475569',
        warehouseCardBg:  'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)',
        warehouseCardBorder: '#bae6fd',
        warehouseLabel:   '#64748b',
        warehouseValue:   '#0c4a6e',
        warehouseUnit:    '#64748b',
        galleryFrameBg:   '#f8fafc',
        galleryFrameBorder: '#e5e7eb',
        thumbInnerBg:     '#fff',
        backBtnBg:        'transparent',
        backBtnColor:     '#475569',
        backBtnBorder:    '#cbd5e1',
        backBtnHoverBg:   '#fff',
        backBtnHoverFg:   '#4f46e5'
      };
    },

    rootStyle() {
      return {
        background: this.pdTheme.pageBg,
        padding: '24px',
        borderRadius: '12px',
        minHeight: '100vh'
      };
    },
    cardStyle() {
      return {
        background: this.pdTheme.cardBg,
        borderRadius: '14px',
        boxShadow: this.pdTheme.cardShadow,
        border: `1px solid ${this.pdTheme.cardBorder}`,
        marginBottom: '20px',
        overflow: 'hidden'
      };
    },
    cardHeaderStyle() {
      return {
        padding: '16px 20px',
        borderBottom: `1px solid ${this.pdTheme.cardBorder}`,
        fontSize: '15px',
        fontWeight: '700',
        color: this.pdTheme.cardHeaderText,
        display: 'flex',
        alignItems: 'center',
        background: this.pdTheme.cardHeaderBg
      };
    },
    infoGrid() {
      return { display: 'grid', gridTemplateColumns: '1fr', rowGap: '0' };
    },
    infoRow() {
      return {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '12px 4px',
        borderBottom: `1px dashed ${this.pdTheme.dashedBorder}`,
        flexWrap: 'wrap',
        gap: '8px'
      };
    },
    infoKey() {
      return { fontSize: '13px', color: this.pdTheme.keyColor, fontWeight: '500' };
    },
    infoVal() {
      return { fontSize: '14px', color: this.pdTheme.valueColor, fontWeight: '600', textAlign: 'right' };
    },
    tableStyle() {
      return { width: '100%', borderCollapse: 'separate', borderSpacing: '0', fontSize: '14px' };
    },
    thStyle() {
      return {
        padding: '10px 12px',
        textAlign: 'left',
        color: this.pdTheme.tableHeaderText,
        fontWeight: '700',
        fontSize: '12px',
        textTransform: 'uppercase',
        letterSpacing: '0.5px',
        borderBottom: `2px solid ${this.pdTheme.tableHeaderRule}`,
        background: this.pdTheme.tableHeaderBg
      };
    },
    tdStyle() {
      return {
        padding: '12px',
        borderBottom: `1px solid ${this.pdTheme.tableRowRule}`,
        color: this.pdTheme.tableCellText
      };
    },
    trHover() { return { transition: 'background 0.2s' }; },
    codeStyle() {
      return {
        background: this.pdTheme.codeBg,
        color: this.pdTheme.codeText,
        padding: '2px 8px',
        borderRadius: '6px',
        fontSize: '12px',
        fontFamily: "'Courier New', monospace",
        fontWeight: '600'
      };
    },
    statLabelStyle() {
      return {
        fontSize: '11px',
        color: this.pdTheme.labelColor,
        textTransform: 'uppercase',
        letterSpacing: '0.5px',
        fontWeight: '600'
      };
    },
    statValueStyle() {
      return {
        fontSize: '16px',
        color: this.pdTheme.valueColor,
        fontWeight: '700',
        marginTop: '2px'
      };
    },
    printBtnStyle() {
      return {
        background: 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)',
        color: '#fff',
        border: 'none',
        padding: '10px 20px',
        borderRadius: '10px',
        fontWeight: '600',
        fontSize: '14px',
        cursor: 'pointer',
        boxShadow: '0 4px 10px rgba(79,70,229,0.25)',
        transition: 'all 0.2s',
        display: 'inline-flex',
        alignItems: 'center'
      };
    },
    backBtnStyle() {
      return {
        background: this.pdTheme.backBtnBg,
        color: this.pdTheme.backBtnColor,
        border: `1px solid ${this.pdTheme.backBtnBorder}`,
        padding: '10px 18px',
        borderRadius: '10px',
        fontWeight: '600',
        fontSize: '14px',
        cursor: 'pointer',
        transition: 'all 0.2s',
        display: 'inline-flex',
        alignItems: 'center'
      };
    },

    categoriesLine() {
      const p = this.product;
      if (!p || typeof p !== "object") return "";
      if (Array.isArray(p.categories) && p.categories.length) {
        return p.categories.map(c => c && c.name).filter(Boolean).join(", ");
      }
      return p.category || "";
    },
    subcategoriesLine() {
      const p = this.product;
      if (!p || typeof p !== "object") return "";
      if (Array.isArray(p.subcategories) && p.subcategories.length) {
        return p.subcategories.map(s => s && s.name).filter(Boolean).join(", ");
      }
      return p.sub_category || "";
    },
    productImages() {
      const p = this.product;
      if (!p || typeof p !== "object") return [];
      if (Array.isArray(p.images) && p.images.length) {
        return p.images.filter(Boolean);
      }
      if (typeof p.image === "string" && p.image.trim() !== "") {
        return p.image.split(",").map(s => s.trim()).filter(Boolean);
      }
      return [];
    },
    activeImage() {
      const imgs = this.productImages;
      if (!imgs.length) return this.product.image || 'no-image.png';
      return imgs[Math.min(this.activeImageIndex, imgs.length - 1)];
    },
    totalStock() {
      if (!this.product || !Array.isArray(this.product.CountQTY)) return 0;
      return this.product.CountQTY.reduce((sum, w) => sum + (parseFloat(w.qte) || 0), 0);
    },
    batchesTotalQty() {
      if (!Array.isArray(this.batches)) return 0;
      return this.batches.reduce((sum, b) => sum + (Number(b.qty) || 0), 0);
    },
    batchesExpiredCount() {
      return (this.batches || []).filter(b => b.expiry_bucket === 'expired').length;
    },
    batchesNearExpiryCount() {
      return (this.batches || []).filter(b => b.expiry_bucket === 'near').length;
    },
    hasAnyVariant() {
      return (this.batches || []).some(b => !!b.variant_name);
    },
  },

  methods: {
    goBack() {
      this.$router.go(-1);
    },

    onImgError(e) {
      e.target.src = '/images/products/no-image.png';
    },

    heroBadge(bg) {
      return {
        background: bg,
        color: '#fff',
        padding: '4px 12px',
        borderRadius: '999px',
        fontSize: '12px',
        fontWeight: '600',
        display: 'inline-flex',
        alignItems: 'center'
      };
    },

    statCardStyle(accent, bg) {
      return {
        background: this.pdTheme.cardBg,
        border: `1px solid ${this.pdTheme.cardBorder}`,
        borderLeft: `4px solid ${accent}`,
        borderRadius: '12px',
        padding: '14px 16px',
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        boxShadow: this.isDarkMode ? '0 2px 8px rgba(0,0,0,0.3)' : '0 2px 8px rgba(15,23,42,0.04)'
      };
    },
    statIconStyle(color) {
      return {
        width: '44px',
        height: '44px',
        borderRadius: '12px',
        background: color + '1a',
        color: color,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: '20px',
        flexShrink: 0
      };
    },
    infoValAccent(color) {
      return {
        fontSize: '14px',
        color: color,
        fontWeight: '700',
        textAlign: 'right'
      };
    },

    onBackBtnHover(e, isHover) {
      const t = this.pdTheme;
      e.currentTarget.style.background = isHover ? t.backBtnHoverBg : t.backBtnBg;
      e.currentTarget.style.color      = isHover ? t.backBtnHoverFg : t.backBtnColor;
    },

    pillStyle(color) {
      return {
        background: color + '15',
        color: color,
        padding: '4px 10px',
        borderRadius: '999px',
        fontSize: '12px',
        fontWeight: '700',
        display: 'inline-block'
      };
    },

    formatNumber(number, dec) {
      if (number === null || number === undefined) number = 0;
      const value = (typeof number === "string" ? number : number.toString()).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec) return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 2;
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) this.price_format_key = key;
        return formatPriceDisplayHelper(number, decimals, key);
      } catch (e) {
        return this.formatNumber(number, dec);
      }
    },

    formatPriceWithSymbol(symbol, number, dec) {
      const safeSymbol = symbol || "";
      const value = this.formatPriceDisplay(number, dec);
      return safeSymbol ? `${safeSymbol} ${value}` : value;
    },

    print_product() {
      const el = document.getElementById('print_product');
      if (!el) return;

      const win = window.open('', '_blank', 'fullscreen=yes,titlebar=yes,scrollbars=yes');
      if (!win) {
        alert('Please allow pop-ups to print this page.');
        return;
      }

      const title = (this.product && this.product.name)
        ? `${this.product.name} — ${this.product.code || ''}`
        : document.title;

      const html = `<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>${title}</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" />
    <style>
      html, body { margin: 0; padding: 0; background: #fff; color: #0f172a; font-family: 'Segoe UI', Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      body { padding: 20px; }
      table { page-break-inside: auto; }
      tr    { page-break-inside: avoid; page-break-after: auto; }
      img   { max-width: 100%; height: auto; }
      @page { margin: 10mm; }
      @media print {
        body { padding: 0; }
      }
    </style>
  </head>
  <body>${el.innerHTML}</body>
</html>`;

      win.document.open();
      win.document.write(html);
      win.document.close();

      const doPrint = () => {
        try {
          win.focus();
          // Close after the print dialog returns (or user cancels)
          const closeAfter = () => { try { win.close(); } catch (e) {} };
          if (typeof win.onafterprint !== 'undefined') {
            win.onafterprint = closeAfter;
          }
          win.print();
          // Safety fallback in browsers that don't fire afterprint reliably
          setTimeout(closeAfter, 1500);
        } catch (e) {
          try { win.close(); } catch (_) {}
        }
      };

      // Wait for images and stylesheet to load so content is visible before printing
      const waitForImages = () => {
        const imgs = Array.from(win.document.images || []);
        if (!imgs.length) return Promise.resolve();
        return Promise.all(
          imgs.map(img => {
            if (img.complete) return Promise.resolve();
            return new Promise(res => {
              img.addEventListener('load', res, { once: true });
              img.addEventListener('error', res, { once: true });
            });
          })
        );
      };

      if (win.document.readyState === 'complete') {
        waitForImages().then(doPrint);
      } else {
        win.addEventListener('load', () => waitForImages().then(doPrint));
      }
    },

    showDetails() {
      let id = this.$route.params.id;
      axios
        .get(`get_product_detail_api/${id}`)
        .then(response => {
          this.product = response.data;
          this.isLoading = false;
          if (this.product && this.product.is_batch_tracked) {
            this.loadBatches();
          }
        })
        .catch(() => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    loadBatches() {
      const id = this.$route.params.id;
      if (!id) return;
      this.batchesLoading = true;
      axios
        .get('product_batches', {
          params: {
            product_id: id,
            limit: 200,
            SortField: 'expiry_date',
            SortType: 'asc',
          },
        })
        .then(response => {
          const data = response && response.data ? response.data : {};
          this.batches = Array.isArray(data.batches) ? data.batches : [];
          if (Number.isFinite(Number(data.expiry_warning_days))) {
            this.expiryWarningDays = Number(data.expiry_warning_days);
          }
        })
        .catch(() => {
          this.batches = [];
        })
        .then(() => {
          this.batchesLoading = false;
        });
    },

    batchExpiryStyle(bucket) {
      const styles = {
        expired: { background: '#fee2e2', color: '#991b1b' },
        near: { background: '#fef3c7', color: '#92400e' },
        valid: { background: '#dcfce7', color: '#166534' },
      };
      const palette = styles[bucket] || { background: '#f3f4f6', color: '#6b7280' };
      return {
        display: 'inline-block',
        padding: '3px 10px',
        borderRadius: '999px',
        fontSize: '12px',
        fontWeight: '700',
        ...palette,
      };
    },

    batchStatusStyle(status) {
      const palette = {
        active: { background: '#dcfce7', color: '#166534' },
        quarantined: { background: '#fef3c7', color: '#92400e' },
        expired: { background: '#fee2e2', color: '#991b1b' },
        written_off: { background: '#fecaca', color: '#7f1d1d' },
      };
      const c = palette[String(status || '').toLowerCase()] || { background: '#e5e7eb', color: '#374151' };
      return {
        display: 'inline-block',
        padding: '3px 10px',
        borderRadius: '6px',
        fontSize: '11px',
        fontWeight: '700',
        textTransform: 'uppercase',
        letterSpacing: '0.3px',
        ...c,
      };
    },
  },

  created: function() {
    this.showDetails();
  }
};
</script>

<style scoped>
/* Stack gallery under details on tablet and below */
@media (max-width: 992px) {
  .pd-main-grid {
    grid-template-columns: 1fr !important;
    gap: 16px !important;
  }
}

/* Mobile / phone */
@media (max-width: 768px) {
  .pd-root {
    padding: 12px !important;
    border-radius: 8px !important;
  }

  .pd-actions {
    flex-wrap: wrap !important;
    justify-content: stretch !important;
    gap: 8px !important;
  }
  .pd-actions > button {
    flex: 1 1 auto !important;
    justify-content: center !important;
    padding: 10px 14px !important;
    font-size: 13px !important;
  }

  .pd-hero {
    padding: 18px !important;
    border-radius: 12px !important;
  }
  .pd-hero-row {
    gap: 14px !important;
  }
  .pd-hero-img {
    width: 96px !important;
    height: 96px !important;
    border-radius: 10px !important;
  }
  .pd-hero-title {
    font-size: 20px !important;
  }
  .pd-hero-price {
    width: 100% !important;
    min-width: 0 !important;
    padding: 12px 16px !important;
  }

  /* Stack info rows label-above-value on phones */
  .pd-info-grid > div {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 4px !important;
    padding: 10px 4px !important;
  }
  .pd-info-grid > div > span:last-child {
    text-align: left !important;
  }

  /* Card headers with badges should wrap */
  .pd-card-header {
    flex-wrap: wrap !important;
    gap: 6px !important;
    padding: 12px 14px !important;
    font-size: 14px !important;
  }
  .pd-card-header > div {
    margin-left: 0 !important;
    width: 100% !important;
    justify-content: flex-start !important;
  }
}

/* Extra-small phones */
@media (max-width: 480px) {
  .pd-root {
    padding: 8px !important;
  }
  .pd-hero {
    padding: 14px !important;
  }
  .pd-hero-img {
    width: 80px !important;
    height: 80px !important;
  }
  .pd-hero-title {
    font-size: 18px !important;
  }
}
</style>
