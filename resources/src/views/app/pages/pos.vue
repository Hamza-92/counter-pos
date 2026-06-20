<template>
  <div class="pos-codecanyon" style="display: flex; flex-direction: column; height: 100vh; background: #f7f7fb; color: #1f1f2c; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; overflow: hidden;">

    <!-- ============================================================
         TOP TOOLBAR (desktop / tablet)
         ============================================================ -->
    <nav v-if="productsReady" class="pos-shell-header" style="display: flex; align-items: center; gap: 8px; padding: 0 16px; flex-shrink: 0; flex-grow: 0; border-bottom: 1px solid #e6e6ec; background: #ffffff;">

      <!-- Brand block (28x28 logo only on mobile; register info follows as a sibling) -->
      <div class="pos-shell-brand-block" style="display: flex; align-items: center; gap: 10px; padding-right: 12px; border-right: 1px solid #e6e6ec; height: 32px;">
        <div style="width: 28px; height: 28px; border-radius: 8px; background: transparent; display: grid; place-items: center; color: #1f1f2c; font-weight: 700; font-size: 13px; font-family: 'JetBrains Mono', monospace; overflow: hidden; flex-shrink: 0;">
          <img v-if="currentUser && currentUser.logo" :src="'/images/'+currentUser.logo" alt="logo" style="width: 100%; height: 100%; object-fit: cover;" />
          <span v-else>{{ (currentUser && currentUser.company) ? (currentUser.company[0] || 'P') : 'P' }}</span>
        </div>
      </div>

      <!-- Register status (text + OPEN/CLOSED pill) — sibling so it can wrap on its own row on mobile -->
      <div v-if="registerEnabled" class="pos-shell-register-status" style="display: inline-flex; align-items: center; gap: 10px;">
        <div style="font-size: 13px; font-weight: 600; color: #1f1f2c; white-space: nowrap;">
          {{ $t('Register') || 'Register' }}<span v-if="currentRegister && currentRegister.id"> #{{ currentRegister.id }}</span>
        </div>
        <button
          v-if="isOnline"
          type="button"
          @click="(currentRegister && currentRegister.status === 'open') ? $bvModal.show('CloseRegisterModal') : $bvModal.show('OpenRegisterModal')"
          :title="(currentRegister && currentRegister.status === 'open') ? $t('Close Register') : $t('Open Register')"
          style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; font-family: inherit; text-transform: uppercase; cursor: pointer; border: 0;"
          :style="{ background: (currentRegister && currentRegister.status === 'open') ? '#eaf7ef' : '#fdecec', color: (currentRegister && currentRegister.status === 'open') ? '#1e7a44' : '#a83232' }">
          <span style="width: 6px; height: 6px; border-radius: 99px; background: currentColor;"></span>
          <span v-if="currentRegister && currentRegister.status === 'open'">OPEN</span>
          <span v-else>CLOSED</span>
        </button>
      </div>

      <!-- Selectors -->
      <div
        role="button"
        tabindex="0"
        class="pos-wh-trigger"
        :class="{ 'is-active': sale.warehouse_id }"
        :title="$t('Select_Warehouse')"
        @click="whDrawerOpen = true"
        @keydown.enter.prevent="whDrawerOpen = true"
        @keydown.space.prevent="whDrawerOpen = true"
      >
        <span class="pos-wh-trigger-icon">
          <lucide-icon name="warehouse" />
          <span class="pos-wh-trigger-icon-pulse" v-if="sale.warehouse_id"></span>
        </span>
        <span class="pos-wh-trigger-body">
          <span class="pos-wh-trigger-eyebrow">{{ $t('Warehouse') || 'Warehouse' }}</span>
          <span class="pos-wh-trigger-label">{{ selectedWarehouseLabel }}</span>
        </span>
        <lucide-icon name="chevron-down" class="pos-wh-trigger-caret" />
      </div>
      <div
        v-if="pos_settings.show_categories && isOnline"
        role="button"
        tabindex="0"
        class="pos-cat-trigger"
        :class="{ 'is-active': category_id }"
        :title="$t('pos.All_Categories')"
        @click="catDrawerOpen = true"
        @keydown.enter.prevent="catDrawerOpen = true"
        @keydown.space.prevent="catDrawerOpen = true"
      >
        <span class="pos-cat-trigger-icon">
          <lucide-icon name="folder" />
        </span>
        <span class="pos-cat-trigger-label">{{ selectedCategoryLabel }}</span>
        <span
          v-if="category_id"
          class="pos-cat-trigger-clear"
          role="button"
          tabindex="0"
          :title="$t('Clear')"
          @click.stop="selectCategory(null)"
          @keydown.enter.stop.prevent="selectCategory(null)"
          @keydown.space.stop.prevent="selectCategory(null)"
        >
          <lucide-icon name="x" />
        </span>
        <lucide-icon v-else name="chevron-down" class="pos-cat-trigger-caret" />
      </div>
      <div
        v-if="pos_settings.show_brands && isOnline"
        role="button"
        tabindex="0"
        class="pos-cat-trigger pos-brand-trigger"
        :class="{ 'is-active': brand_id }"
        :title="$t('pos.All_Brands')"
        @click="brandDrawerOpen = true"
        @keydown.enter.prevent="brandDrawerOpen = true"
        @keydown.space.prevent="brandDrawerOpen = true"
      >
        <span class="pos-cat-trigger-icon">
          <lucide-icon name="tag" />
        </span>
        <span class="pos-cat-trigger-label">{{ selectedBrandLabel }}</span>
        <span
          v-if="brand_id"
          class="pos-cat-trigger-clear"
          role="button"
          tabindex="0"
          :title="$t('Clear')"
          @click.stop="selectBrand(null)"
          @keydown.enter.stop.prevent="selectBrand(null)"
          @keydown.space.stop.prevent="selectBrand(null)"
        >
          <lucide-icon name="x" />
        </span>
        <lucide-icon v-else name="chevron-down" class="pos-cat-trigger-caret" />
      </div>
      <!-- Spacer pushes everything that follows to the right side (matches POS.html) -->
      <div style="flex: 1;"></div>

      <!-- Customer select (right side, like POS.html "Walk-in customer") -->
      <div
        role="button"
        tabindex="0"
        class="pos-cust-trigger"
        :class="{ 'is-active': selectedClientId }"
        :title="$t('Select_Customer')"
        @click="custDrawerOpen = true"
        @keydown.enter.prevent="custDrawerOpen = true"
        @keydown.space.prevent="custDrawerOpen = true"
      >
        <span class="pos-cust-trigger-avatar" :style="selectedClientId ? { background: catColor(selectedClientId) } : {}">
          <span v-if="selectedClientId">{{ catInitial(selectedCustomerLabel) }}</span>
          <lucide-icon v-else name="user" />
        </span>
        <span class="pos-cust-trigger-body">
          <span class="pos-cust-trigger-eyebrow">{{ $t('Customer') || 'Customer' }}</span>
          <span class="pos-cust-trigger-label">{{ selectedCustomerLabel }}</span>
        </span>
        <lucide-icon name="chevron-down" class="pos-cust-trigger-caret" />
      </div>

      <!-- Quick add customer -->
      <button
        v-if="isQuickAddCustomerEnabled && isOnline"
        @click="Quick_Add_Client"
        :title="$t('Quick_Add_Customer')"
        class="pos-shell-icon-btn"
        style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #ece9fb; background: #f5f3fd; color: #6f53d9; display: inline-flex; align-items: center; justify-content: center; padding: 0; cursor: pointer; transition: all 120ms ease;">
        <lucide-icon name="user-plus" style="font-size: 15px;" />
      </button>

      <!-- Vertical divider (matches POS.html separator before icon cluster) -->
      <div style="width: 1px; height: 24px; background: #e6e6ec; margin: 0 4px;"></div>

      <!-- Today's sales -->
      <button
        v-if="isOnline"
        @click="get_today_sales"
        :title="$t('Today_Sales')"
        class="pos-shell-icon-btn"
        style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e6e6ec; background: #ffffff; color: #54546a; display: inline-flex; align-items: center; justify-content: center; padding: 0; cursor: pointer; transition: all 120ms ease;">
        <lucide-icon name="receipt" style="font-size: 15px;" />
      </button>

      <!-- Offline / sync -->
      <button
        v-if="!isOnline || offlineSalesCount > 0"
        @click="syncOfflineSales"
        :title="offlineStatusTitle"
        class="pos-shell-icon-btn"
        :style="{ width: '32px', height: '32px', borderRadius: '8px', border: '1px solid ' + (!isOnline ? '#fecaca' : '#e6e6ec'), background: !isOnline ? '#fef2f2' : '#ffffff', color: !isOnline ? '#a83232' : '#54546a', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', padding: 0, cursor: 'pointer', position: 'relative' }">
        <lucide-icon name="cloud" style="font-size: 15px;" />
        <span v-if="offlineSalesCount > 0" style="position: absolute; top: -4px; right: -4px; min-width: 16px; height: 16px; padding: 0 4px; background: #d64545; color: #fff; font-size: 10px; font-weight: 700; border-radius: 99px; display: inline-flex; align-items: center; justify-content: center; font-family: 'JetBrains Mono', monospace;">{{ offlineSalesCount }}</span>
      </button>

      <!-- POS settings -->
      <button
        v-if="currentUserPermissions && currentUserPermissions.includes('pos_settings') && isOnline"
        type="button"
        :title="$t('Pos_Settings')"
        class="pos-shell-icon-btn pos-shell-settings-btn"
        style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e6e6ec; background: #ffffff; color: #54546a; display: inline-flex; align-items: center; justify-content: center; padding: 0;"
        @click="open_pos_settings_modal">
        <lucide-icon name="settings" style="font-size: 15px;" />
      </button>

      <!-- Languages -->
      <b-dropdown
        v-if="show_language && isOnline"
        id="lang-dd"
        right offset="8" boundary="window"
        toggle-class="dropdown-toggle-no-caret p-0 bg-transparent border-0"
        menu-class="pos-shell-dropdown-menu"
        no-caret variant="link">
        <template #button-content>
          <span :title="$t('Languages') || 'Languages'" style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e6e6ec; background: #ffffff; color: #54546a; display: inline-flex; align-items: center; justify-content: center;">
            <lucide-icon name="globe" style="font-size: 15px;" />
          </span>
        </template>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px; padding: 8px; min-width: 220px;">
          <button v-for="lang in languages_available" :key="lang.locale" @click="SetLocal(lang.locale)" style="display: flex; align-items: center; gap: 8px; padding: 6px 8px; background: transparent; border: 0; border-radius: 6px; cursor: pointer; color: #1f1f2c; font-size: 12px; text-align: left;">
            <img :src="`/flags/${lang.flag}`" :alt="lang.name" style="width: 18px; height: 18px; border-radius: 99px; object-fit: cover;" />
            <span>{{ lang.name }}</span>
          </button>
        </div>
      </b-dropdown>

      <!-- Display screen dropdown -->
      <b-dropdown
        id="display-screen-dd"
        right offset="8" boundary="window"
        toggle-class="dropdown-toggle-no-caret p-0 bg-transparent border-0"
        menu-class="pos-shell-dropdown-menu"
        no-caret variant="link">
        <template #button-content>
          <span :title="$t('Display')" style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e6e6ec; background: #ffffff; color: #54546a; display: inline-flex; align-items: center; justify-content: center;">
            <lucide-icon name="monitor" style="font-size: 15px;" />
          </span>
        </template>
        <div style="padding: 6px; min-width: 160px;">
          <button
            v-for="opt in customerDisplayScreenOptions"
            :key="opt.value"
            type="button"
            @click="onCustomerDisplayScreenChange(opt.value)"
            :style="{ display: 'block', width: '100%', textAlign: 'left', padding: '6px 10px', fontSize: '12px', borderRadius: '6px', background: customer_display_screen_id === opt.value ? '#f5f3fd' : 'transparent', color: customer_display_screen_id === opt.value ? '#6f53d9' : '#1f1f2c', border: 0, cursor: 'pointer' }">
            {{ opt.label }}
          </button>
        </div>
      </b-dropdown>

      <!-- Fullscreen -->
      <button
        @click="handleFullScreen"
        :title="$t('Fullscreen')"
        class="pos-shell-icon-btn"
        style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e6e6ec; background: #ffffff; color: #54546a; display: inline-flex; align-items: center; justify-content: center; padding: 0; cursor: pointer;">
        <lucide-icon name="maximize" style="font-size: 15px;" />
      </button>

      <!-- Vertical divider -->
      <div style="width: 1px; height: 24px; background: #e6e6ec; margin: 0 4px;"></div>

      <!-- User dropdown -->
      <div class="pos-shell-user-dd-wrapper" style="display: inline-flex;">
        <b-dropdown id="user-dd" right toggle-class="dropdown-toggle-no-caret p-0 bg-transparent border-0" no-caret variant="link">
          <template #button-content>
            <img v-if="currentUser && currentUser.avatar" :src="'/images/avatar/'+currentUser.avatar" alt="avatar" style="width: 32px; height: 32px; border-radius: 99px; object-fit: cover; display: block;" />
            <div v-else style="width: 32px; height: 32px; border-radius: 99px; background: linear-gradient(135deg, #6f53d9, #b65cd6); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600;">{{ currentUser && currentUser.name ? currentUser.name.charAt(0).toUpperCase() : 'U' }}</div>
          </template>
          <div style="padding: 6px; min-width: 200px;">
            <div style="padding: 8px 10px; font-size: 12px; color: #8d8da0; display: flex; align-items: center; gap: 6px;">
              <lucide-icon name="lock" />
              <span>{{ currentUser && (currentUser.username || currentUser.name) }}</span>
            </div>
            <router-link to="/app/profile" style="display: block; padding: 8px 10px; font-size: 13px; color: #1f1f2c; border-radius: 6px; text-decoration: none;">{{$t('profil')}}</router-link>
            <router-link
              v-if="currentUserPermissions && currentUserPermissions.includes('setting_system')"
              to="/app/settings/System_settings"
              style="display: block; padding: 8px 10px; font-size: 13px; color: #1f1f2c; border-radius: 6px; text-decoration: none;">{{$t('Settings')}}</router-link>
            <a href="#" @click.prevent="logoutUser" style="display: block; padding: 8px 10px; font-size: 13px; color: #d64545; border-radius: 6px; text-decoration: none;">{{$t('logout')}}</a>
          </div>
        </b-dropdown>
      </div>
    </nav>

    <!-- ============================================================
         GATE LOADER
         ============================================================ -->
    <div v-else style="flex: 1; display: grid; place-items: center; background: #f7f7fb;">
      <div style="text-align: center;">
        <div class="spinner lg spinner-primary"></div>
        <div style="margin-top: 8px; color: #54546a; font-size: 13px;">{{ $t('Loading') }}...</div>
      </div>
    </div>

    <!-- ============================================================
         MAIN GRID  (cart left | products right)
         ============================================================ -->
    <div v-if="productsReady" class="pos-shell-main" style="flex: 1 1 auto; display: flex; flex-direction: row; align-items: stretch; min-height: 0; overflow: hidden;">

      <!-- ============ CART (LEFT) ============ -->
      <aside class="pos-shell-cart-aside" style="display: grid; grid-template-rows: 1fr auto; border-right: 1px solid #e6e6ec; background: #ffffff; min-height: 0; overflow: hidden;">

        <!-- Mobile-only cart header (visual label, matches mockup) -->
        <div class="pos-shell-mobile-cart-header">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px; color: #6f53d9;">
            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>
          </svg>
          <span class="pos-shell-mobile-cart-title">{{ $t('pos.Cart') || 'Cart' }} ({{ details.length }} {{ $t('pos.items') || 'items' }})</span>
        </div>

        <!-- Cart items (scrolls when content exceeds available space) -->
        <div class="pos-shell-cart-scroll" style="overflow-y: auto; overflow-x: hidden; padding: 8px; min-height: 0; height: 100%;">

          <!-- Sticky header: title + count on the left, total + clear on the right -->
          <div class="pos-cart-header">
            <div class="pos-cart-header-left">
              <span class="pos-cart-header-icon">
                <lucide-icon name="shopping-cart" />
              </span>
              <div class="pos-cart-header-text">
                <div class="pos-cart-header-title">{{ $t('Current_Cart') || 'Current Cart' }}</div>
                <div class="pos-cart-header-sub">
                  {{ details.length }} {{ details.length === 1 ? ($t('item') || 'item') : ($t('items') || 'items') }}
                </div>
              </div>
            </div>
            <div class="pos-cart-header-right">
              <div class="pos-cart-header-total">
                <span class="pos-cart-header-total-label">{{ $t('Total') || 'Total' }}</span>
                <span class="pos-cart-header-total-value">
                  {{ formatPriceWithCurrentCurrency(GrandTotal || 0, 2) }}
                </span>
              </div>
              <button
                type="button"
                class="pos-cart-header-clear-btn"
                :class="{ 'is-disabled': !details.length }"
                :disabled="!details.length"
                :title="$t('pos.Clear_all_items') || 'Clear cart'"
                @click="Reset_Pos"
              >
                <lucide-icon name="trash-2" />
              </button>
            </div>
          </div>

          <!-- Empty state — cart icon + 2-line copy (matches POS.html) -->
          <div v-if="details.length === 0" style="padding: 32px 16px; text-align: center; color: #8d8da0;">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width: 32px; height: 32px; opacity: 0.3; margin: 0 auto 8px;">
              <path d="M3 4h2l2 9h9l1.5-6H6"/><circle cx="8" cy="16.5" r="1"/><circle cx="15" cy="16.5" r="1"/>
            </svg>
            <div style="font-size: 13px; font-weight: 500; margin-bottom: 4px; color: #54546a;">{{ $t('pos.No_items_added') || 'Cart is empty' }}</div>
            <div style="font-size: 11px; color: #8d8da0;">{{ $t('pos.Select_products_from_right_panel') || 'Scan or click a product to begin' }}</div>
          </div>

          <!-- Cart items list — 3-col grid: thumb | details | actions/total -->
          <div v-else style="display: flex; flex-direction: column;">
            <div
              v-for="(item, index) in details"
              :key="index"
              class="pos-shell-cart-row"
              style="display: grid; grid-template-columns: 32px 1fr auto; gap: 8px; padding: 6px 8px; border-radius: 8px; margin-bottom: 2px; transition: background 120ms ease;">

              <!-- Thumb: product image when available + setting on, else initials swatch.
                   Mirrors the product-card behaviour at the grid above so the
                   cart row reuses the same image source / fallback rules. -->
              <div
                style="width: 32px; height: 32px; border-radius: 6px; align-self: center; display: grid; place-items: center; font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 10px; color: rgba(31,31,44,0.55); letter-spacing: 0.02em; text-transform: uppercase;"
                :style="{
                  background: (pos_settings.show_product_images && item.image)
                    ? '#ffffff'
                    : 'repeating-linear-gradient(135deg, #ece9fb, #ece9fb 6px, #f5f3fd 6px, #f5f3fd 12px)',
                  backgroundImage: (pos_settings.show_product_images && item.image)
                    ? 'url(' + resolveProductImage(item.image) + ')'
                    : null,
                  backgroundRepeat: 'no-repeat',
                  backgroundPosition: 'center',
                  backgroundSize: 'contain',
                  border: (pos_settings.show_product_images && item.image) ? '1px solid #e6e6ec' : '0'
                }">
                <span v-if="!(pos_settings.show_product_images && item.image)">
                  {{ (item.name || 'P').split(/[ ·]/).filter(Boolean).slice(0,2).map(w => w[0]).join('') }}
                </span>
              </div>

              <!-- Details: name (title) → SKU (subtitle) → qty + unit price + price-type.
                   SKU was previously inline with name (flex-end), which left it
                   floating awkwardly next to the right column's subtotal and
                   created blank space between name and SKU when names were
                   short. Promoted to its own subtitle line so the middle
                   column reads top-to-bottom as title / subtitle / controls. -->
              <div style="min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 2px;">
                <!-- Name -->
                <div style="font-size: 13px; font-weight: 600; color: #1f1f2c; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ item.name }}</div>

                <!-- SKU subtitle -->
                <div v-if="item.code" style="font-size: 10px; color: #8d8da0; font-family: 'JetBrains Mono', monospace; letter-spacing: 0.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">SKU · {{ item.code }}</div>

                <!-- Controls row: qty stepper + unit price + price-type -->
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                  <!-- qty stepper -->
                  <div style="display: inline-flex; align-items: center; border: 1px solid #e6e6ec; border-radius: 5px; height: 24px; background: #ffffff; flex-shrink: 0;">
                    <button @click="decrement(item, item.detail_id)" :title="$t('pos.Decrease')" style="width: 22px; height: 22px; background: transparent; border: 0; color: #54546a; font-size: 14px; cursor: pointer; padding: 0;">−</button>
                    <input v-model.number="item.quantity" type="text" @change="Verified_Qty(item, item.detail_id)" style="width: 32px; height: 100%; border: 0; text-align: center; font-size: 12px; font-family: 'JetBrains Mono', monospace; background: transparent; color: #1f1f2c; outline: none; padding: 0;" />
                    <button @click="increment(item.detail_id)" :title="$t('pos.Increase')" style="width: 22px; height: 22px; background: transparent; border: 0; color: #54546a; font-size: 14px; cursor: pointer; padding: 0;">+</button>
                  </div>
                  <!-- × unit price (darker + larger so the per-unit math reads at a glance) -->
                  <span style="font-size: 12px; color: #54546a; font-weight: 500; font-family: 'JetBrains Mono', monospace; white-space: nowrap;">× {{ formatPriceWithCurrentCurrency(item.Total_price, 2) }}</span>
                  <!-- Price type (sized to match qty stepper, dark text instead of muted) -->
                  <select v-model="item.price_type" @change="onChangePriceType(item)" style="height: 24px; padding: 0 6px; font-size: 11px; font-weight: 500; border: 1px solid #d8d8e0; border-radius: 5px; background: #ffffff; color: #1f1f2c; outline: none; cursor: pointer; flex-shrink: 0;">
                    <option value="retail">{{ $t('Retail Price') }}</option>
                    <option value="wholesale">{{ $t('Wholesale Price') }}</option>
                  </select>
                </div>
              </div>

              <!-- Right column: edit/remove top, line total bottom -->
              <div style="display: flex; flex-direction: column; align-items: flex-end; justify-content: space-between; gap: 4px;">
                <div style="display: inline-flex; gap: 2px;">
                  <button
                    v-if="currentUserPermissions && currentUserPermissions.includes('edit_product_sale')"
                    type="button"
                    @mousedown.prevent
                    @click="Modal_Updat_Detail(item)"
                    :title="$t('pos.Edit')"
                    style="background: transparent; border: 0; padding: 4px; border-radius: 4px; cursor: pointer; color: #8d8da0; display: inline-flex; align-items: center; justify-content: center;">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width: 13px; height: 13px;">
                      <path d="M14 3l3 3-9 9H5v-3z"/>
                    </svg>
                  </button>
                  <button
                    type="button"
                    @mousedown.prevent
                    @click="delete_Product_Detail(item.detail_id)"
                    :title="$t('pos.Remove')"
                    style="background: transparent; border: 0; padding: 4px; border-radius: 4px; cursor: pointer; color: #8d8da0; display: inline-flex; align-items: center; justify-content: center;">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" style="width: 14px; height: 14px;">
                      <path d="m5 5 10 10M15 5 5 15"/>
                    </svg>
                  </button>
                </div>
                <div style="font-size: 13px; font-weight: 600; font-family: 'JetBrains Mono', monospace; color: #1f1f2c;">{{ formatPriceWithCurrentCurrency(item.subtotal, 2) }}</div>
              </div>

              <!-- Batches panel (full-width, only for tracked items) -->
              <div v-if="item.is_batch_tracked" style="grid-column: 1 / -1; margin-top: 6px; padding: 8px; background: #f3f3f7; border-radius: 8px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                  <div style="font-size: 11px; font-weight: 600; color: #54546a; display: inline-flex; align-items: center; gap: 4px;">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor">
                      <path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.24L19.53 8 12 11.76 4.47 8 12 4.24zM4 9.51l7 3.5V20l-7-3.5V9.51zm16 0V16.5L13 20v-7l7-3.49z"/>
                    </svg>
                    <span>{{ $t('Batches') || 'Batches' }}</span>
                    <span style="font-size: 10px; color: #8d8da0; font-family: 'JetBrains Mono', monospace; font-weight: 400; margin-left: 4px;">
                      {{ (item.batches || []).length }} {{ $t('items') || 'items' }}
                      <template v-if="(item.batches || []).length">· {{ formatNumber(batch_total_qty(item), 2) }} / {{ formatNumber(Number(item.quantity) || 0, 2) }}</template>
                    </span>
                  </div>
                  <button type="button" @click="add_batch_to_detail(item)" style="height: 24px; padding: 0 8px; background: #f5f3fd; color: #6f53d9; border: 0; border-radius: 5px; font-size: 11px; font-weight: 600; cursor: pointer;">+ {{ $t('Add') || 'Add' }}</button>
                </div>
                <div v-if="item.batches_loading" style="font-size: 11px; color: #8d8da0; padding: 4px 0;">{{ $t('Loading') || 'Loading...' }}</div>
                <div v-else-if="!(item.available_batches && item.available_batches.length)" style="font-size: 11px; color: #d64545; padding: 4px 0;">{{ $t('No_Batches_Available') || 'No available batches for this product in the selected warehouse' }}</div>
                <div v-else-if="!item.batches || item.batches.length === 0" style="font-size: 11px; color: #d64545; padding: 4px 0;">{{ $t('Choose_Batch') || 'Choose a batch' }}</div>
                <div v-else style="display: flex; flex-direction: column; gap: 6px;">
                  <div v-for="(b, bIdx) in item.batches" :key="'pb-' + item.detail_id + '-' + bIdx" style="display: grid; grid-template-columns: 1fr 80px 24px; gap: 6px; align-items: center;">
                    <v-select
                      :value="b.product_batch_id"
                      :options="item.available_batches.map(ab => ({ label: ab.batch_no + (ab.expiry_date ? ' · ' + ($t('Exp') || 'Exp') + ' ' + ab.expiry_date : '') + ' · ' + ab.qty_available, value: ab.id }))"
                      :reduce="opt => opt.value"
                      :placeholder="$t('Choose_Batch') || 'Choose batch'"
                      :append-to-body="true"
                      @input="val => on_batch_select(item, bIdx, val)"
                    />
                    <input
                      type="text"
                      inputmode="decimal"
                      :value="b.qty"
                      @input="evt => on_batch_qty_input(b, evt.target.value)"
                      :placeholder="$t('Quantity')"
                      style="height: 32px; padding: 0 8px; border-radius: 6px; background: #ffffff; font-size: 12px; font-family: 'JetBrains Mono', monospace; color: #1f1f2c; outline: none; width: 100%;"
                      :style="{ border: '1px solid ' + ((Number(b.qty) > (Number(b.qty_available) || 0)) ? '#fca5a5' : '#e6e6ec') }"
                    />
                    <button type="button" @click="remove_batch_from_detail(item, bIdx)" :title="$t('pos.Remove')" style="width: 24px; height: 24px; background: transparent; border: 0; color: #8d8da0; cursor: pointer; padding: 0; border-radius: 5px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">×</button>
                  </div>
                </div>
                <div v-if="!item.batches_loading && batch_line_error(item)" style="margin-top: 6px; font-size: 11px; color: #d64545;">{{ batch_line_error(item) }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Totals / charges -->
        <div class="pos-shell-totals" style="border-top: 1px solid #e6e6ec; padding: 8px 12px; background: #f7f7fb;">

          <!-- Charges row — matches POS.html FieldNum (no uppercase, prefix/suffix inside box without borders) -->
          <div class="pos-shell-charges-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-bottom: 8px;">
            <label style="display: block;">
              <div style="font-size: 9px; font-weight: 700; letter-spacing: 0.08em; color: #8d8da0; margin-bottom: 4px;">{{ $t('pos.Tax') }} %</div>
              <div style="display: flex; align-items: center; height: 28px; border: 1px solid #e6e6ec; border-radius: 6px; background: #ffffff;">
                <input v-model.number="sale.tax_rate" type="text" placeholder="0" @keyup="keyup_OrderTax" style="width: 100%; border: 0; padding: 0 8px; background: transparent; font-size: 12px; font-family: 'JetBrains Mono', monospace; color: #1f1f2c; outline: none; min-width: 0;" />
                <span style="padding-right: 8px; color: #8d8da0; font-size: 12px; font-family: 'JetBrains Mono', monospace;">%</span>
              </div>
            </label>
            <label style="display: block;">
              <div style="font-size: 9px; font-weight: 700; letter-spacing: 0.08em; color: #8d8da0; margin-bottom: 4px;">{{ $t('Discount') }}</div>
              <div style="display: flex; align-items: center; height: 28px; border: 1px solid #e6e6ec; border-radius: 6px; background: #ffffff;">
                <button type="button" @click="toggleDiscountType" :title="sale.discount_Method === '1' ? $t('Switch_to_Fixed') : $t('Switch_to_Percentage')" style="padding-left: 8px; padding-right: 0; background: transparent; border: 0; color: #8d8da0; font-size: 12px; cursor: pointer; font-family: 'JetBrains Mono', monospace;">{{ sale.discount_Method === '1' ? '%' : currentUser.currency }}</button>
                <input v-model.number="sale.discount" type="text" placeholder="0" @keyup="keyup_Discount" style="width: 100%; border: 0; padding: 0 8px; background: transparent; font-size: 12px; font-family: 'JetBrains Mono', monospace; color: #1f1f2c; outline: none; min-width: 0;" />
              </div>
            </label>
            <label style="display: block;">
              <div style="font-size: 9px; font-weight: 700; letter-spacing: 0.08em; color: #8d8da0; margin-bottom: 4px;">{{ $t('Shipping') }}</div>
              <div style="display: flex; align-items: center; height: 28px; border: 1px solid #e6e6ec; border-radius: 6px; background: #ffffff;">
                <span style="padding-left: 8px; color: #8d8da0; font-size: 12px; font-family: 'JetBrains Mono', monospace;">{{ currentUser.currency }}</span>
                <input v-model.number="sale.shipping" type="text" placeholder="0" @keyup="keyup_Shipping" style="width: 100%; border: 0; padding: 0 8px; background: transparent; font-size: 12px; font-family: 'JetBrains Mono', monospace; color: #1f1f2c; outline: none; min-width: 0;" />
              </div>
            </label>
          </div>

          <!-- Points convert row -->
          <div
            v-if="isOnline && pos_settings.enable_customer_points && clientIsEligible && currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_sale')"
            :style="{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '10px', padding: '10px', background: pointsConverted ? '#eaf7ef' : '#f5f3fd', borderRadius: '8px', marginBottom: '10px' }">
            <div style="min-width: 0;">
              <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 11px; color: #54546a;">
                <lucide-icon name="check" v-if="pointsConverted" />
                <span>{{ $t('Available_Points') }}</span>
                <span style="font-size: 14px; font-weight: 700; color: #6f53d9; font-family: 'JetBrains Mono', monospace; margin-left: 4px;">{{ selectedClientPoints }}</span>
              </div>
              <div v-if="discount_from_points > 0" style="font-size: 10px; color: #1e7a44; margin-top: 2px;">✅ {{ $t('Discount') }} {{ discount_from_points }} {{ currentUser.currency }} {{ $t('pos.will_be_applied') }}</div>
            </div>
            <div style="display: inline-flex; align-items: center; gap: 6px;">
              <input
                v-model.number="points_to_convert"
                @input="onPointsToConvertInput"
                type="text"
                min="0"
                :max="selectedClientPoints"
                step="1"
                :disabled="selectedClientPoints === 0 || pointsConverted"
                inputmode="numeric"
                pattern="[0-9]*"
                placeholder="0"
                style="width: 60px; height: 28px; padding: 0 8px; border: 1px solid #e6e6ec; border-radius: 6px; background: #ffffff; font-size: 12px; font-family: 'JetBrains Mono', monospace; color: #1f1f2c; outline: none; text-align: center;"
              />
              <button
                :disabled="selectedClientPoints === 0"
                @click="convertPointsToDiscount"
                :style="{ height: '28px', padding: '0 10px', background: pointsConverted ? '#2fae5e' : '#6f53d9', color: '#fff', border: 0, borderRadius: '6px', fontSize: '11px', fontWeight: 600, cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '4px' }">
                <template v-if="!pointsConverted"><lucide-icon name="banknote" /> {{ $t('Convert') }}</template>
                <template v-else><lucide-icon name="check" /> {{ $t('Unconverted') }}</template>
              </button>
            </div>
          </div>

          <!-- Totals — Subtotal + Tax always, Discount + Shipping only when > 0 (matches POS.html) -->
          <div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; font-size: 12px;">
              <span style="color: #54546a;">{{ $t('pos.Subtotal') }}</span>
              <span style="color: #1f1f2c; font-family: 'JetBrains Mono', monospace; font-weight: 500;">{{ formatPriceWithCurrentCurrency(total, 2) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 4px 0; font-size: 12px;">
              <span style="color: #8d8da0;">{{ $t('pos.Tax') }}</span>
              <span style="color: #54546a; font-family: 'JetBrains Mono', monospace; font-weight: 500;">{{ formatPriceWithCurrentCurrency(sale.TaxNet, 2) }}</span>
            </div>
            <div v-if="getCurrentSaleDiscountAmount() > 0" style="display: flex; justify-content: space-between; padding: 4px 0; font-size: 12px;">
              <span style="color: #54546a;">{{ $t('pos.Discount') }}</span>
              <span style="color: #d64545; font-family: 'JetBrains Mono', monospace; font-weight: 500;">−{{ formatPriceWithCurrentCurrency(getCurrentSaleDiscountAmount(), 2) }}</span>
            </div>
            <div v-if="Number(sale.shipping) > 0" style="display: flex; justify-content: space-between; padding: 4px 0; font-size: 12px;">
              <span style="color: #8d8da0;">{{ $t('pos.Shipping') }}</span>
              <span style="color: #54546a; font-family: 'JetBrains Mono', monospace; font-weight: 500;">{{ formatPriceWithCurrentCurrency(sale.shipping, 2) }}</span>
            </div>
          </div>

          <!-- Mobile-only Pay Now (matches mockup; desktop has its own pay bar at the bottom of the page) -->
          <button
            class="pos-shell-mobile-pay-btn"
            @click="openModernPaymentModal"
            :disabled="paymentProcessing || details.length === 0 || payNowBatchGate.blocked"
            :title="payNowBatchGate.blocked ? payNowBatchGate.reason : $t('pos.Complete_and_process_payment')">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width: 18px; height: 18px;">
              <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
            </svg>
            <span class="pos-shell-mobile-pay-btn-label">{{ paymentProcessing ? $t('pos.Processing') : $t('pos.Pay_Now') }}</span>
            <span class="pos-shell-mobile-pay-btn-amount">{{ formatPriceWithCurrentCurrency(GrandTotal, 2) }}</span>
          </button>
        </div>
      </aside>

      <!-- ============ PRODUCTS (RIGHT) ============ -->
      <section class="pos-shell-section" style="flex: 1 1 auto; min-width: 0; display: grid; grid-template-rows: auto 1fr auto; min-height: 0; background: #ffffff; overflow: hidden;">

        <!-- Search row (search input + Scanner button) -->
        <div class="pos-shell-search-row" style="padding: 4px 6px; display: flex; gap: 8px; align-items: center;">
          <div class="pos-shell-search-wrap" style="flex: 1; position: relative;">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: #8d8da0; pointer-events: none;">
              <path d="M3 7V4h3M14 4h3v3M17 13v3h-3M6 16H3v-3"/><path d="M5 10h10"/>
            </svg>
            <input
              type="text"
              :placeholder="$t('Scan_Search_Product_by_Code_Name')"
              v-model="search_input"
              @keyup="search"
              class="pos-shell-search-input"
              style="width: 100%; height: 36px; padding: 0 12px 0 38px; background: #ffffff; border: 1px solid #e6e6ec; border-radius: 8px; font-size: 13px; color: #1f1f2c; outline: none; font-family: inherit; transition: border-color 120ms ease, box-shadow 120ms ease;"
            />
            <ul v-if="product_filter && product_filter.length" style="position: absolute; top: calc(100% + 4px); left: 0; right: 0; margin: 0; padding: 4px; list-style: none; background: #ffffff; border: 1px solid #e6e6ec; border-radius: 10px; box-shadow: 0 8px 24px rgba(20,20,40,0.08); max-height: 280px; overflow: auto; z-index: 10;">
              <li
                v-for="product_fil in product_filter"
                :key="product_fil.id"
                @mousedown="SearchProduct(product_fil)"
                class="pos-shell-autocomplete-item"
                style="padding: 8px 12px; font-size: 13px; color: #1f1f2c; cursor: pointer; border-radius: 6px;">
                {{ getResultValue(product_fil) }}
              </li>
            </ul>
          </div>
          <!-- Scanner button (matches POS.html ghost lg button) -->
          <button @click="showModal" :title="$t('Scan')" class="pos-shell-action-btn" style="height: 36px; padding: 0 12px; background: transparent; color: #1f1f2c; border: 1px solid #e6e6ec; border-radius: 8px; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; transition: all 120ms ease;">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" style="width: 16px; height: 16px;">
              <path d="M3 7V4h3M14 4h3v3M17 13v3h-3M6 16H3v-3"/><path d="M5 10h10"/>
            </svg>
            <span>{{ $t('Scan') }}</span>
          </button>
        </div>



        <!-- Products grid -->
        <div class="pos-shell-products-scroll" :style="{ overflowY: 'auto', overflowX: 'hidden', padding: '0px 6px', minHeight: 0, height: '100%', position: 'relative', opacity: productsLoading ? 0.6 : 1 }">
          <!-- Empty state -->
          <div v-if="paginated_Products.length === 0" style="padding: 48px 16px; text-align: center; color: #8d8da0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 40px; height: 40px; opacity: 0.3; margin: 0 auto 8px;">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.35-4.35"></path>
            </svg>
            <div style="font-size: 13px; color: #54546a;">{{ $t('pos.No_products_found') }}</div>
          </div>

          <div v-else class="pos-shell-products-grid">
            <button
              v-for="product in paginated_Products"
              :key="product.product_variant_id ? (product.id + '-' + product.product_variant_id) : product.id"
              type="button"
              class="pos-shell-product-card"
              @click="handleProductClick(product)"
              style="background: #ffffff; border: 1px solid #e6e6ec; border-radius: 12px; padding: 10px; text-align: left; cursor: pointer; position: relative; transition: all 120ms ease; font-family: inherit;">

              <div v-if="uiLoadingProductId === (product.product_variant_id ? (product.id + '-' + product.product_variant_id) : product.id)" style="position: absolute; inset: 0; background: rgba(255,255,255,0.7); border-radius: 12px; display: grid; place-items: center; z-index: 2;">
                <div class="spinner sm spinner-primary"></div>
              </div>

              <!-- Image / swatch area (matches POS.html ProductSwatch) -->
              <div style="margin: -10px -10px 8px; position: relative;">
                <div
                  :style="{
                    width: '100%',
                    height: '58px',
                    borderRadius: '12px 12px 0 0',
                    overflow: 'hidden',
                    position: 'relative',
                    background: (pos_settings.show_product_images && product.image)
                      ? '#ffffff'
                      : 'repeating-linear-gradient(135deg, #ece9fb, #ece9fb 8px, #f5f3fd 8px, #f5f3fd 16px)',
                    backgroundImage: (pos_settings.show_product_images && product.image)
                      ? 'url(' + resolveProductImage(product.image) + ')'
                      : null,
                    backgroundRepeat: 'no-repeat',
                    backgroundPosition: 'center',
                    backgroundSize: 'contain'
                  }">
                  <span v-if="!(pos_settings.show_product_images && product.image)" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 16px; color: rgba(31,31,44,0.6); letter-spacing: 0.02em; text-transform: uppercase;">
                    {{ (product.name || 'P').split(/[ ·]/).filter(Boolean).slice(0,2).map(w => w[0]).join('') }}
                  </span>
                </div>
                <!-- Discount % badge (top-left) -->
                <div v-if="product.discount" style="position: absolute; top: 6px; left: 6px; background: rgba(255,255,255,0.9); border-radius: 6px; padding: 2px 6px; color: #d64545; font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 700;">-{{ product.discount }}%</div>
              </div>

              <!-- Name (single 13px line) -->
              <div style="font-size: 13px; font-weight: 500; margin-bottom: 2px; line-height: 1.2; color: #1f1f2c; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ product.name }}</div>

              <!-- SKU · X in stock -->
              <div style="font-size: 10px; color: #8d8da0; font-family: 'JetBrains Mono', monospace; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <span v-if="product.code">SKU {{ product.code }}</span>
                <span
                  v-if="product.product_type !== 'is_service' && pos_settings.show_stock_quantity"
                  class="pos-shell-card-stock">
                  <span v-if="product.code"> · </span>{{ formatNumber(product.qte_sale, 2) }} {{ product.unitSale }}
                </span>
              </div>

              <!-- Price + plus button row -->
              <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="font-size: 14px; font-weight: 700; font-family: 'JetBrains Mono', monospace; color: #6f53d9; letter-spacing: -0.01em;">{{ formatPriceWithCurrentCurrency(product.Net_price, 2) }}</div>
                <span
                  class="pos-shell-add-btn"
                  :style="{ width: '24px', height: '24px', background: '#f0ecfb', color: '#6f53d9', borderRadius: '6px', display: 'grid', placeItems: 'center', opacity: (!isOversellingAllowed && product.product_type !== 'is_service' && product.qte_sale <= 0) ? 0.4 : 1 }">
                  <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" style="width: 14px; height: 14px;">
                    <path d="M10 4v12M4 10h12"/>
                  </svg>
                </span>
              </div>
            </button>
          </div>
        </div>

        <!-- Mobile-only pagination dots (matches mockup) -->
        <div v-if="paginated_Products.length > 0 && product_lastPage > 1" class="pos-shell-mobile-pagination-dots">
          <button
            v-for="page in product_lastPage"
            :key="'pdot-' + page"
            type="button"
            class="pos-shell-mobile-page-dot"
            :class="{ 'pos-shell-mobile-page-dot-active': page === product_currentPage }"
            :aria-label="($t('pos.Page') || 'Page') + ' ' + page"
            @click="Product_onPageChanged(page)"></button>
        </div>

        <!-- Pagination -->
        <div v-if="paginated_Products.length > 0" class="pos-shell-pagination" style="padding: 0px 16px; border-top: 1px solid rgb(230, 230, 236); background: rgb(255, 255, 255); display: flex; align-items: center; justify-content: space-between; gap: 7px; font-size: 11px; color: rgb(141, 141, 160); min-height: 30px;">
          <button
            @click="Product_onPageChanged(product_currentPage - 1)"
            :disabled="product_currentPage === 1"
            :title="$t('pos.Previous_Page')"
            style="width: 28px; height: 28px; border-radius: 6px; border: 1px solid #e6e6ec; background: #ffffff; color: #54546a; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;"><polyline points="15 18 9 12 15 6"></polyline></svg>
          </button>
          <div style="display: inline-flex; flex-direction: column; align-items: center; font-size: 11px; color: #8d8da0;">
            <span style="font-family: 'JetBrains Mono', monospace; color: #54546a;">{{ $t('pos.Page') }} {{ product_currentPage }}</span>
            <span>{{ product_totalRows }} {{ $t('pos.products') }}</span>
          </div>
          <div style="display: inline-flex; gap: 4px;">
            <button
              v-for="(item, idx) in product_visiblePageItems"
              :key="`pp-${idx}-${item}`"
              :disabled="item === '…'"
              @click="onProductPageItemClick(item)"
              :title="item === '…' ? '' : `${$t('pos.Go_to_page')} ${item}`"
              :style="{ width: '28px', height: '28px', borderRadius: '6px', border: item === '…' ? 0 : '1px solid #e6e6ec', background: item === product_currentPage ? '#6f53d9' : (item === '…' ? 'transparent' : '#ffffff'), color: item === product_currentPage ? '#fff' : '#54546a', fontSize: '12px', cursor: item === '…' ? 'default' : 'pointer', fontWeight: item === product_currentPage ? 600 : 400, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', padding: 0 }">
              {{ item }}
            </button>
          </div>
          <button
            @click="Product_onPageChanged(product_currentPage + 1)"
            :disabled="product_currentPage === product_lastPage"
            :title="$t('pos.Next_Page')"
            style="width: 28px; height: 28px; border-radius: 6px; border: 1px solid #e6e6ec; background: #ffffff; color: #54546a; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
          </button>
        </div>
      </section>
    </div>

    <!-- ============================================================
         BOTTOM PAY BAR
         ============================================================ -->
    <div v-if="productsReady" class="pos-shell-pay-bar" style="display: flex; background: rgb(255, 255, 255); align-items: center; gap: 8px; padding: 8px 16px 8px 20px; flex-shrink: 0; flex-grow: 0; border-top: 1px solid rgb(230, 230, 236);">

      <!-- Online indicator -->
      <div :style="{ display: 'inline-flex', alignItems: 'center', gap: '6px', fontSize: '12px', fontWeight: 500, color: isOnline ? '#1e7a44' : '#a83232' }" :title="offlineStatusTitle">
        <span :style="{ width: '6px', height: '6px', borderRadius: '99px', background: isOnline ? '#2fae5e' : '#d64545' }"></span>
        <span v-if="isOnline">{{ $t('Online') || 'Online' }} <span v-if="lastProductsSyncAt" style="opacity: 0.7;">· {{ $t('pos.Synced') || 'synced' }}</span></span>
        <span v-else>{{ $t('pos.Offline_Mode') }}<span v-if="offlineSalesCount > 0" style="opacity: 0.7;"> · {{ offlineSalesCount }} {{ $t('pos.Pending') || 'pending' }}</span></span>
      </div>

      <div style="width: 1px; height: 24px; background: #e6e6ec; margin: 0 8px;"></div>

      <!-- Action buttons -->
      <router-link v-if="isOnline" to="/app/dashboard" :title="$t('pos.Home')" class="pos-shell-action-btn" style="height: 36px; padding: 0 14px; background: transparent; color: #1f1f2c; border: 1px solid #e6e6ec; border-radius: 8px; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; text-decoration: none; transition: all 120ms ease;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
          <path d="M3 11l9-8 9 8"></path>
          <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"></path>
        </svg>
        <span>{{ $t('pos.Home') }}</span>
      </router-link>

      <button v-if="isOnline" @click="Reset_Pos" :title="$t('pos.Clear_all_items')" class="pos-shell-action-btn" style="height: 36px; padding: 0 14px; background: transparent; color: #1f1f2c; border: 1px solid #e6e6ec; border-radius: 8px; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 120ms ease;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
          <path d="M1 4v6h6"></path>
          <path d="M23 20v-6h-6"></path>
          <path d="M20.49 9A9 9 0 0 0 5.64 5.64"></path>
          <path d="M3.51 15A9 9 0 0 0 18.36 18.36"></path>
        </svg>
        <span>{{ $t('pos.Reset') }}</span>
      </button>

      <button v-if="isOnline" @click="Show_Draft_Sales" :title="$t('pos.Drafts_list')" class="pos-shell-action-btn" style="height: 36px; padding: 0 14px; background: transparent; color: #1f1f2c; border: 1px solid #e6e6ec; border-radius: 8px; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 120ms ease;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
          <rect x="3" y="4" width="18" height="14" rx="2" ry="2"></rect>
          <path d="M7 8h10M7 12h8"></path>
        </svg>
        <span>{{ $t('pos.Recent_Drafts') }}</span>
        <span v-if="totalRows_draft_sales > 0" style="background: #f3f3f7; color: #54546a; padding: 1px 7px; border-radius: 99px; font-size: 11px; font-family: 'JetBrains Mono', monospace; font-weight: 600; margin-left: 2px;">{{ totalRows_draft_sales }}</span>
      </button>

      <button
        v-if="pos_settings.enable_hold_sales && isOnline"
        @click="Submit_Draft"
        :disabled="DraftProcessing"
        :title="$t('pos.Hold_this_sale')"
        class="pos-shell-action-btn"
        style="height: 36px; padding: 0 14px; background: transparent; color: #1f1f2c; border: 1px solid #e6e6ec; border-radius: 8px; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 120ms ease;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
          <circle cx="12" cy="12" r="10"></circle>
          <path d="M10 9v6"></path>
          <path d="M14 9v6"></path>
        </svg>
        <span>{{ DraftProcessing ? $t('pos.Saving') : $t('pos.Hold') }}</span>
      </button>

      <div style="flex: 1;"></div>

      <!-- Total payable -->
      <div style="display: flex; flex-direction: column; align-items: flex-end; text-align: right; margin-right: 12px;">
        <span style="font-size: 10px; color: #8d8da0; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">{{ $t('pos.Total_Payable') }}</span>
        <span style="font-size: 20px; font-weight: 700; font-family: 'JetBrains Mono', monospace; color: #1f1f2c; letter-spacing: -0.01em;">{{ formatPriceWithCurrentCurrency(GrandTotal, 2) }}</span>
      </div>

      <!-- Pay now -->
      <button
        @click="openModernPaymentModal"
        :disabled="paymentProcessing || details.length === 0 || payNowBatchGate.blocked"
        :title="payNowBatchGate.blocked ? payNowBatchGate.reason : $t('pos.Complete_and_process_payment')"
        class="pos-shell-pay-btn"
        :style="{ height: '48px', padding: '0 28px', background: '#6f53d9', color: '#fff', border: 0, borderRadius: '10px', fontSize: '15px', fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: '10px', cursor: (paymentProcessing || details.length === 0 || payNowBatchGate.blocked) ? 'not-allowed' : 'pointer', opacity: (paymentProcessing || details.length === 0 || payNowBatchGate.blocked) ? 0.5 : 1, boxShadow: '0 4px 14px rgba(111,83,217,0.32)', transition: 'all 120ms ease' }">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width: 18px; height: 18px;">
          <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"></path>
        </svg>
        <span>{{ paymentProcessing ? $t('pos.Processing') : $t('pos.Pay_Now') }}</span>
      </button>
    </div>

    <!-- ============================================================
         MOBILE BOTTOM TAB BAR (matches mockup)
         ============================================================ -->
    <nav v-if="productsReady" class="pos-shell-mobile-tabbar">
      <button
        type="button"
        class="pos-shell-mobile-tab"
        :class="{ 'pos-shell-mobile-tab-active': mobileActiveTab === 'home' }"
        @click="goToMobileTab('home')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 22px; height: 22px;">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        <span>{{ $t('pos.Home') || 'Home' }}</span>
      </button>
      <button
        type="button"
        class="pos-shell-mobile-tab"
        :class="{ 'pos-shell-mobile-tab-active': mobileActiveTab === 'cart' }"
        @click="goToMobileTab('cart')">
        <span class="pos-shell-mobile-tab-icon-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 22px; height: 22px;">
            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>
          </svg>
          <span v-if="details.length > 0" class="pos-shell-mobile-tab-badge">{{ details.length }}</span>
        </span>
        <span>{{ $t('pos.Cart') || 'Cart' }}</span>
      </button>
      <button
        v-if="pos_settings.enable_hold_sales && isOnline"
        type="button"
        class="pos-shell-mobile-tab"
        :class="{ 'pos-shell-mobile-tab-active': mobileActiveTab === 'hold' }"
        :disabled="DraftProcessing || details.length === 0"
        @click="mobileActiveTab = 'hold'; Submit_Draft();">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 22px; height: 22px;">
          <circle cx="12" cy="12" r="10"/><path d="M10 9v6"/><path d="M14 9v6"/>
        </svg>
        <span>{{ $t('pos.Hold') || 'Hold' }}</span>
      </button>
      <button
        v-if="isOnline"
        type="button"
        class="pos-shell-mobile-tab"
        :class="{ 'pos-shell-mobile-tab-active': mobileActiveTab === 'recent' }"
        @click="mobileActiveTab = 'recent'; Show_Draft_Sales();">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 22px; height: 22px;">
          <rect x="3" y="4" width="18" height="14" rx="2" ry="2"/><path d="M7 8h10M7 12h8"/>
        </svg>
        <span>{{ $t('pos.Recent') || 'Recent' }}</span>
      </button>
      <b-dropdown
        dropup right no-caret variant="link"
        toggle-class="pos-shell-mobile-tab-toggle"
        menu-class="pos-shell-mobile-tab-menu">
        <template #button-content>
          <span class="pos-shell-mobile-tab" :class="{ 'pos-shell-mobile-tab-active': mobileActiveTab === 'more' }">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 22px; height: 22px;">
              <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
            <span>{{ $t('pos.More') || 'More' }}</span>
          </span>
        </template>
        <router-link to="/app/dashboard" class="dropdown-item">{{ $t('pos.Home') || 'Home' }}</router-link>
        <button type="button" class="dropdown-item" @click="Reset_Pos">{{ $t('pos.Reset') || 'Reset' }}</button>
        <button type="button" class="dropdown-item" @click="handleFullScreen">{{ $t('Fullscreen') || 'Fullscreen' }}</button>
        <a href="#" class="dropdown-item" @click.prevent="logoutUser">{{ $t('logout') || 'Logout' }}</a>
      </b-dropdown>
    </nav>

    <!-- ============================================================
         MODALS (preserved verbatim from original; teleport to body)
         ============================================================ -->
  <b-modal id="open_scan" hide-footer :title="$t('Scan')">
    <qrcode-scanner
      :qrbox="250"
      :fps="10"
      style="width: 100%; height: calc(100vh - 56px);"
      @result="onScan"
    />
    <div class="text-center mt-2">
      <b-button variant="primary" @click="$bvModal.hide('open_scan')">{{ $t('Close') }}</b-button>
    </div>
  </b-modal>



  <!-- Modern Payment Modal Alternative -->
  <modern-payment-modal 
    ref="modernPaymentModal"
    :payment-methods="payment_methods"
    :accounts="accounts"
    :default-account-id="default_account_id"
    :default-payment-method-id="default_payment_method_id"
    :currency="currentUser.currency"
    :client-id="selectedClientId"
    :warehouse-id="sale.warehouse_id"
    :sale="sale"
    :details="details"
    :grand-total="GrandTotal"
    :stripe-key="stripe_key"
    :discount-from-points="discount_from_points"
    :used-points="used_points"
    :draft-sale-id="draft_sale_id"
    :client-credit-limit="selectedClientCreditLimit"
    :client-net-balance="selectedClientNetBalance"
    :is-online="isOnline"
    @payment-success="onModernPaymentSuccess"
  />

  <b-modal hide-footer size="sm" scrollable id="Show_invoice" :title="$t('Invoice_POS')" @shown="onInvoiceModalShown">
        <div id="invoice-POS">
          <div style="max-width:400px;margin:0px auto">
            <!-- Layout 1 - Standard (existing layout) -->
            <div v-if="currentReceiptLayout === 1">
              <div class="info">
                <div class="invoice_logo text-center mb-2">
                  <img v-show="pos_settings.show_logo !== 0" :src="'/images/'+invoice_pos.setting.logo" alt :width="pos_settings.logo_size || 60" :height="pos_settings.logo_size || 60">
                </div>
                <p>
                  <span v-show="pos_settings.show_store_name !== 0"><strong>{{invoice_pos.setting.CompanyName}}</strong><br></span>
                  <span v-if="invoice_pos.sale && invoice_pos.sale.Ref && pos_settings.show_reference !== 0">{{$t('Reference')}} : {{invoice_pos.sale.Ref}} <br></span>
                  <span v-show="pos_settings.show_date !== 0">{{$t('date')}} : {{invoice_pos.sale.date}} <br></span>
                  <span v-show="pos_settings.show_seller !== 0">{{$t('Seller')}} : {{invoice_pos.sale.seller_name}} <br></span>
                  <span v-show="pos_settings.show_address">{{$t('Adress')}} : {{invoice_pos.setting.CompanyAdress}} <br></span>
                  <span v-show="pos_settings.show_email">{{$t('Email')}} : {{invoice_pos.setting.email}} <br></span>
                  <span v-show="pos_settings.show_phone">{{$t('Phone')}} : {{invoice_pos.setting.CompanyPhone}} <br></span>
                  <span v-show="pos_settings.show_customer">{{$t('Customer')}} : {{invoice_pos.sale.client_name}} <br></span>
                  <span v-show="pos_settings.show_Warehouse">{{$t('warehouse')}} : {{invoice_pos.sale.warehouse_name}} <br></span>
                </p>
              </div>

              <table class="table_data" style=" width: 100%; ">
                <tbody>
                  <tr v-for="detail_invoice in invoice_pos.details">
                    <td colspan="3">
                      {{detail_invoice.name}}
                      <br v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null">
                      <span v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null ">{{$t('IMEI_SN')}} : {{detail_invoice.imei_number}}</span>
                      <br>
                      <span>{{formatNumber(detail_invoice.quantity,2)}} {{detail_invoice.unit_sale}} x {{ formatPriceDisplay(detail_invoice.total/detail_invoice.quantity,2) }}</span>
                    </td>
                    <td style="text-align:right;vertical-align:bottom">
                      {{ formatPriceDisplay(detail_invoice.total,2) }}
                    </td>
                  </tr>

                  <!-- Subtotal (before tax/discount/shipping) -->
                  <tr style="margin-top:10px">
                    <td colspan="3" class="total">{{$t('pos.Subtotal')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoiceSubtotal, 2) }}
                    </td>
                  </tr>

                  <tr style="margin-top:10px" v-show="pos_settings.show_tax">
                    <td colspan="3" class="total">{{$t('OrderTax')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.taxe ,2) }} ({{formatNumber(invoice_pos.sale.tax_rate,2)}} %)
                    </td>
                  </tr>

                  <tr style="margin-top:10px" v-show="pos_settings.show_discount">
                    <td colspan="3" class="total">{{$t('Discount')}}</td>
                    <td style="text-align:right;" class="total">
                      <!-- If percentage: show percent value AND discount amount; else amount only -->
                      <template v-if="String(invoice_pos.sale.discount_Method || '2') === '1'">
                        {{ formatNumber(invoice_pos.sale.discount, 2) }}% ({{ formatPriceWithSymbol(invoice_pos.symbol, calculatedManualDiscountAmount ,2) }})
                      </template>
                      <template v-else>
                        {{ formatPriceWithSymbol(invoice_pos.symbol, calculatedManualDiscountAmount ,2) }}
                      </template>
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_discount && invoice_pos.sale.discount_from_points && Number(invoice_pos.sale.discount_from_points) > 0">
                    <td colspan="3" class="total">{{$t('Discount_from_Points')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.discount_from_points ,2) }}
                    </td>
                  </tr>

                  <tr style="margin-top:10px" v-show="pos_settings.show_shipping">
                    <td colspan="3" class="total">{{$t('Shipping')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.shipping ,2) }}
                    </td>
                  </tr>

                  <tr style="margin-top:10px">
                    <td colspan="3" class="total">{{$t('Total')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.GrandTotal ,2) }}
                    </td>
                  </tr>

                  <tr v-show="pos_settings.show_paid !== 0">
                    <td colspan="3" class="total">{{$t('Paid')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.paid_amount ,2) }}
                    </td>
                  </tr>

                  <tr v-show="pos_settings.show_due !== 0">
                    <td colspan="3" class="total">{{$t('Due')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, (invoice_pos.sale.GrandTotal - invoice_pos.sale.paid_amount), 2) }}
                    </td>
                  </tr>
                </tbody>
              </table>

              <table
                class="change mt-3"
                style=" font-size: 10px;width: 100%;"
                v-show="pos_settings.show_payments !== 0 && invoice_pos.sale.paid_amount > 0"
              >
                <thead>
                  <tr style="background: #eee; ">
                    <th style="text-align: left;" colspan="1">{{$t('PayeBy')}}:</th>
                    <th style="text-align: center;" colspan="2">{{$t('Amount')}}:</th>
                    <th style="text-align: right;" colspan="1">{{$t('Change')}}:</th>
                  </tr>
                </thead>

                <tbody>
                  <template v-for="payment_pos in payments">
                    <tr :key="'pay-' + payment_pos.id">
                      <td style="text-align: left;" colspan="1">{{payment_pos.payment_method?payment_pos.payment_method.name:'---'}}</td>
                      <td style="text-align: center;" colspan="2">
                        {{ formatPriceDisplay(payment_pos.montant ,2) }}
                      </td>
                      <td style="text-align: right;" colspan="1">
                        {{ formatPriceDisplay(payment_pos.change ,2) }}
                      </td>
                    </tr>
                    <tr v-if="payment_pos.notes" :key="'pay-note-' + payment_pos.id">
                      <td colspan="4" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;">
                        {{$t('Payment_note')}}: {{payment_pos.notes}}
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>

              <div id="legalcopy" class="ml-2">
                <p v-if="invoice_pos.sale && invoice_pos.sale.notes" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;margin:0;">
                  {{$t('sale_note')}}: {{invoice_pos.sale.notes}}
                </p>
                <p class="legal" v-show="pos_settings.show_note" style="white-space:pre-line;">
                  <strong>{{pos_settings.note_customer}}</strong>
                </p>
                <!-- Receipt QR codes (ZATCA + Invoice URL) -->
                <div v-if="(invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0) || (pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref)" class="receipt-qr-row mt-2">
                  <div v-if="invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0" class="receipt-qr-block">
                    <div class="receipt-qr-title">ZATCA QR</div>
                    <div class="receipt-qr-canvas" ref="zatcaQrcodePos"></div>
                  </div>
                  <div v-if="pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref" class="receipt-qr-block">
                    <div class="receipt-qr-title">Invoice QR</div>
                    <div class="receipt-qr-canvas" ref="invoiceUrlQr"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Layout 2 - Compact -->
            <div v-else-if="currentReceiptLayout === 2">
              <div class="info text-center">
                <div class="invoice_logo mb-1">
                  <img v-show="pos_settings.show_logo !== 0" :src="'/images/'+invoice_pos.setting.logo" alt :width="pos_settings.logo_size || 60" :height="pos_settings.logo_size || 60">
                </div>
                <div>
                  <div v-show="pos_settings.show_store_name !== 0">{{invoice_pos.setting.CompanyName}}</div>
                  <div v-show="pos_settings.show_address">{{invoice_pos.setting.CompanyAdress}}</div>
                  <div v-show="pos_settings.show_phone">{{invoice_pos.setting.CompanyPhone}}</div>
                  <div v-show="pos_settings.show_email">{{invoice_pos.setting.email}}</div>
                </div>
                <div class="mt-1">
                  <small
                    v-if="invoice_pos.sale && invoice_pos.sale.Ref && pos_settings.show_reference !== 0"
                  >
                    {{$t('Reference')}} : {{invoice_pos.sale.Ref}}
                  </small>
                  <br v-if="invoice_pos.sale && invoice_pos.sale.Ref && pos_settings.show_reference !== 0">
                  <small v-show="pos_settings.show_date !== 0">
                    {{$t('date')}} : {{invoice_pos.sale.date}}
                  </small><br>
                  <small v-show="pos_settings.show_seller !== 0">
                    {{$t('Seller')}} : {{invoice_pos.sale.seller_name}}
                  </small><br>
                  <small v-show="pos_settings.show_customer">
                    {{$t('Customer')}} : {{invoice_pos.sale.client_name}}
                  </small><br>
                  <small v-show="pos_settings.show_Warehouse">
                    {{$t('warehouse')}} : {{invoice_pos.sale.warehouse_name}}
                  </small>
                </div>
              </div>

              <table class="table_data mt-2" style="width:100%; font-size:11px;">
                <thead>
                  <tr>
                    <th style="text-align:left">{{$t('ProductName')}}</th>
                    <th style="text-align:center">{{$t('Quantity')}}</th>
                    <th style="text-align:right">{{$t('Price')}}</th>
                    <th style="text-align:right">{{$t('Total')}}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="detail_invoice in invoice_pos.details">
                    <td>
                      {{detail_invoice.name}}
                      <br v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null">
                      <small v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null ">{{$t('IMEI_SN')}} : {{detail_invoice.imei_number}}</small>
                    </td>
                    <td style="text-align:center">
                      {{formatNumber(detail_invoice.quantity,2)}} {{detail_invoice.unit_sale}}
                    </td>
                    <td style="text-align:right">
                      {{formatNumber(detail_invoice.total/detail_invoice.quantity,2)}}
                    </td>
                    <td style="text-align:right">
                      {{formatNumber(detail_invoice.total,2)}}
                    </td>
                  </tr>
                </tbody>
              </table>

              <table class="table_data mt-2" style="width:100%; font-size:11px;">
                <tbody>
                  <tr>
                    <td class="total">{{$t('pos.Subtotal')}}</td>
                    <td style="text-align:right" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoiceSubtotal, 2) }}
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_tax">
                    <td class="total">{{$t('OrderTax')}}</td>
                    <td style="text-align:right" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.taxe ,2) }} ({{formatNumber(invoice_pos.sale.tax_rate,2)}} %)
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_discount">
                    <td class="total">{{$t('Discount')}}</td>
                    <td style="text-align:right" class="total">
                      <!-- If percentage: show percent value AND discount amount; else amount only -->
                      <template v-if="String(invoice_pos.sale.discount_Method || '2') === '1'">
                        {{ formatNumber(invoice_pos.sale.discount, 2) }}% ({{ formatPriceWithSymbol(invoice_pos.symbol, calculatedManualDiscountAmount ,2) }})
                      </template>
                      <template v-else>
                        {{ formatPriceWithSymbol(invoice_pos.symbol, calculatedManualDiscountAmount ,2) }}
                      </template>
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_discount && invoice_pos.sale.discount_from_points && Number(invoice_pos.sale.discount_from_points) > 0">
                    <td class="total">{{$t('Discount_from_Points')}}</td>
                    <td style="text-align:right" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.discount_from_points ,2) }}
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_shipping">
                    <td class="total">{{$t('Shipping')}}</td>
                    <td style="text-align:right" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.shipping ,2) }}
                    </td>
                  </tr>
                  <tr>
                    <td class="total">{{$t('Total')}}</td>
                    <td style="text-align:right" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.GrandTotal ,2) }}
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_paid !== 0">
                    <td class="total">{{$t('Paid')}}</td>
                    <td style="text-align:right" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.paid_amount ,2) }}
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_due !== 0">
                    <td class="total">{{$t('Due')}}</td>
                    <td style="text-align:right" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, (invoice_pos.sale.GrandTotal - invoice_pos.sale.paid_amount), 2) }}
                    </td>
                  </tr>
                </tbody>
              </table>

              <table
                class="change mt-2"
                style=" font-size: 10px;width: 100%;"
                v-show="pos_settings.show_payments !== 0 && invoice_pos.sale.paid_amount > 0"
              >
                <thead>
                  <tr style="background: #eee; ">
                    <th style="text-align: left;" colspan="1">{{$t('PayeBy')}}:</th>
                    <th style="text-align: center;" colspan="2">{{$t('Amount')}}:</th>
                    <th style="text-align: right;" colspan="1">{{$t('Change')}}:</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="payment_pos in payments">
                    <tr :key="'pay2-' + payment_pos.id">
                      <td style="text-align: left;" colspan="1">{{payment_pos.payment_method?payment_pos.payment_method.name:'---'}}</td>
                      <td style="text-align: center;" colspan="2">
                        {{formatNumber(payment_pos.montant ,2)}}
                      </td>
                      <td style="text-align: right;" colspan="1">
                        {{formatNumber(payment_pos.change ,2)}}
                      </td>
                    </tr>
                    <tr v-if="payment_pos.notes" :key="'pay2-note-' + payment_pos.id">
                      <td colspan="4" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;">
                        {{$t('Payment_note')}}: {{payment_pos.notes}}
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>

              <div id="legalcopy" class="ml-2">
                <p v-if="invoice_pos.sale && invoice_pos.sale.notes" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;margin:0;">
                  {{$t('sale_note')}}: {{invoice_pos.sale.notes}}
                </p>
                <p class="legal" v-show="pos_settings.show_note" style="white-space:pre-line;">
                  <strong>{{pos_settings.note_customer}}</strong>
                </p>
                <!-- Receipt QR codes (ZATCA + Invoice URL) -->
                <div v-if="(invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0) || (pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref)" class="receipt-qr-row mt-2">
                  <div v-if="invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0" class="receipt-qr-block">
                    <div class="receipt-qr-title">ZATCA QR</div>
                    <div class="receipt-qr-canvas" ref="zatcaQrcodePos"></div>
                  </div>
                  <div v-if="pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref" class="receipt-qr-block">
                    <div class="receipt-qr-title">Invoice QR</div>
                    <div class="receipt-qr-canvas" ref="invoiceUrlQr"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Layout 3 - Detailed -->
            <div v-else-if="currentReceiptLayout === 3">
              <div class="info mb-2">
                <div class="d-flex justify-content-between">
                  <div>
                    <strong v-show="pos_settings.show_store_name !== 0">{{invoice_pos.setting.CompanyName}}</strong><br>
                    <span v-show="pos_settings.show_address">{{invoice_pos.setting.CompanyAdress}}</span><br v-show="pos_settings.show_address">
                    <span v-show="pos_settings.show_phone">{{invoice_pos.setting.CompanyPhone}}</span><br v-show="pos_settings.show_phone">
                    <span v-show="pos_settings.show_email">{{invoice_pos.setting.email}}</span>
                  </div>
                  <div class="invoice_logo text-center mb-2" v-show="pos_settings.show_logo !== 0">
                    <img :src="'/images/'+invoice_pos.setting.logo" alt :width="pos_settings.logo_size || 60" :height="pos_settings.logo_size || 60">
                  </div>
                </div>
                <div class="mt-2" style="font-size:11px;">
                  <div
                    v-if="invoice_pos.sale && invoice_pos.sale.Ref && pos_settings.show_reference !== 0"
                  >
                    {{$t('Reference')}} : {{invoice_pos.sale.Ref}}
                  </div>
                  <div v-show="pos_settings.show_date !== 0">
                    {{$t('date')}} : {{invoice_pos.sale.date}}
                  </div>
                  <div v-show="pos_settings.show_seller !== 0">
                    {{$t('Seller')}} : {{invoice_pos.sale.seller_name}}
                  </div>
                  <div v-show="pos_settings.show_customer">
                    {{$t('Customer')}} : {{invoice_pos.sale.client_name}}
                  </div>
                  <div v-show="pos_settings.show_Warehouse">
                    {{$t('warehouse')}} : {{invoice_pos.sale.warehouse_name}}
                  </div>
                </div>
              </div>

              <table class="table_data w-100 mb-2" style="font-size:11px;">
                <tbody>
                  <tr v-for="detail_invoice in invoice_pos.details">
                    <td colspan="2">
                      <strong>{{detail_invoice.name}}</strong>
                      <br v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null">
                      <span v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null ">{{$t('IMEI_SN')}} : {{detail_invoice.imei_number}}</span>
                      <br>
                      <small>{{formatNumber(detail_invoice.quantity,2)}} {{detail_invoice.unit_sale}} x {{ formatPriceDisplay(detail_invoice.total/detail_invoice.quantity,2) }}</small>
                    </td>
                    <td style="text-align:right;vertical-align:bottom">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, detail_invoice.total, 2) }}
                    </td>
                  </tr>
                </tbody>
              </table>

              <table class="table_data w-100 mt-2" style="font-size:11px;">
                <tbody>
                  <tr>
                    <td class="total">{{$t('pos.Subtotal')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoiceSubtotal, 2) }}
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_tax">
                    <td class="total">{{$t('OrderTax')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.taxe ,2) }} ({{formatNumber(invoice_pos.sale.tax_rate,2)}} %)
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_discount">
                    <td class="total">{{$t('Discount')}}</td>
                    <td style="text-align:right;" class="total">
                      <!-- If percentage: show percent value AND discount amount; else amount only -->
                      <template v-if="String(invoice_pos.sale.discount_Method || '2') === '1'">
                        {{ formatNumber(invoice_pos.sale.discount, 2) }}% ({{ formatPriceWithSymbol(invoice_pos.symbol, calculatedManualDiscountAmount ,2) }})
                      </template>
                      <template v-else>
                        {{ formatPriceWithSymbol(invoice_pos.symbol, calculatedManualDiscountAmount ,2) }}
                      </template>
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_discount && invoice_pos.sale.discount_from_points && Number(invoice_pos.sale.discount_from_points) > 0">
                    <td class="total">{{$t('Discount_from_Points')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.discount_from_points ,2) }}
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_shipping">
                    <td class="total">{{$t('Shipping')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.shipping ,2) }}
                    </td>
                  </tr>
                  <tr>
                    <td class="total">{{$t('Total')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.GrandTotal ,2) }}
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_paid !== 0">
                    <td class="total">{{$t('Paid')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.paid_amount ,2) }}
                    </td>
                  </tr>
                  <tr v-show="pos_settings.show_due !== 0">
                    <td class="total">{{$t('Due')}}</td>
                    <td style="text-align:right;" class="total">
                      {{ formatPriceWithSymbol(invoice_pos.symbol, (invoice_pos.sale.GrandTotal - invoice_pos.sale.paid_amount), 2) }}
                    </td>
                  </tr>
                </tbody>
              </table>

              <table
                class="change mt-3"
                style=" font-size: 10px;width: 100%;"
                v-show="pos_settings.show_payments !== 0 && invoice_pos.sale.paid_amount > 0"
              >
                <thead>
                  <tr style="background: #eee; ">
                    <th style="text-align: left;" colspan="1">{{$t('PayeBy')}}:</th>
                    <th style="text-align: center;" colspan="2">{{$t('Amount')}}:</th>
                    <th style="text-align: right;" colspan="1">{{$t('Change')}}:</th>
                  </tr>
                </thead>

                <tbody>
                  <template v-for="payment_pos in payments">
                    <tr :key="'pay3-' + payment_pos.id">
                      <td style="text-align: left;" colspan="1">{{payment_pos.payment_method?payment_pos.payment_method.name:'---'}}</td>
                      <td style="text-align: center;" colspan="2">
                        {{formatNumber(payment_pos.montant ,2)}}
                      </td>
                      <td style="text-align: right;" colspan="1">
                        {{formatNumber(payment_pos.change ,2)}}
                      </td>
                    </tr>
                    <tr v-if="payment_pos.notes" :key="'pay3-note-' + payment_pos.id">
                      <td colspan="4" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;">
                        {{$t('Payment_note')}}: {{payment_pos.notes}}
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>

              <div id="legalcopy" class="ml-2">
                <p v-if="invoice_pos.sale && invoice_pos.sale.notes" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;margin:0;">
                  {{$t('sale_note')}}: {{invoice_pos.sale.notes}}
                </p>
                <p class="legal" v-show="pos_settings.show_note" style="white-space:pre-line;">
                  <strong>{{pos_settings.note_customer}}</strong>
                </p>
                <!-- Receipt QR codes (ZATCA + Invoice URL) -->
                <div v-if="(invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0) || (pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref)" class="receipt-qr-row mt-2">
                  <div v-if="invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0" class="receipt-qr-block">
                    <div class="receipt-qr-title">ZATCA QR</div>
                    <div class="receipt-qr-canvas" ref="zatcaQrcodePos"></div>
                  </div>
                  <div v-if="pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref" class="receipt-qr-block">
                    <div class="receipt-qr-title">Invoice QR</div>
                    <div class="receipt-qr-canvas" ref="invoiceUrlQr"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Layout 4 - Bilingual (Arabic + English) -->
            <div v-else-if="currentReceiptLayout === 4" class="receipt-layout-4">
              <div class="info text-center">
                <div class="invoice_logo mb-2" v-show="pos_settings.show_logo !== 0">
                  <img :src="'/images/'+invoice_pos.setting.logo" alt :width="pos_settings.logo_size || 60" :height="pos_settings.logo_size || 60">
                </div>
                <div>
                  <strong style="font-size:13px;">{{invoice_pos.setting.company_name_ar}}</strong><br>
                  <strong style="font-size:12px;">{{invoice_pos.setting.CompanyName}}</strong>
                </div>
                <div v-if="invoice_pos.setting.CompanyAdress" style="font-size:10px;margin-top:2px;">{{invoice_pos.setting.CompanyAdress}}</div>
                <div v-if="invoice_pos.setting.CompanyPhone" style="font-size:10px;">{{invoice_pos.setting.CompanyPhone}}</div>
                <div v-if="invoice_pos.setting.email" v-show="pos_settings.show_email" style="font-size:10px;">{{invoice_pos.setting.email}}</div>
                <div v-if="invoice_pos.setting.vat_number" style="font-size:11px;font-weight:bold;margin-top:4px;">
                  الرقم الضريبي / TRN : {{invoice_pos.setting.vat_number}}
                </div>
                <div class="mt-2 mb-2" style="border-top:1px dashed #000;border-bottom:1px dashed #000;padding:4px 0;">
                  <strong>فاتورة ضريبية مبسطة</strong><br>
                  <strong>Simplified Tax Invoice</strong>
                </div>
              </div>

              <div style="font-size:10px;">
                <div v-if="invoice_pos.sale && invoice_pos.sale.Ref && pos_settings.show_reference !== 0" style="display:flex;justify-content:space-between;">
                  <span>Invoice No</span>
                  <span>{{invoice_pos.sale.Ref}}</span>
                  <span>رقم الفاتورة</span>
                </div>
                <div v-show="pos_settings.show_date !== 0" style="display:flex;justify-content:space-between;">
                  <span>Date</span>
                  <span>{{invoice_pos.sale.date}}</span>
                  <span>تاريخ</span>
                </div>
                <div v-show="pos_settings.show_seller !== 0" style="display:flex;justify-content:space-between;">
                  <span>Seller</span>
                  <span>{{invoice_pos.sale.seller_name}}</span>
                  <span>البائع</span>
                </div>
                <div v-show="pos_settings.show_customer" style="display:flex;justify-content:space-between;">
                  <span>Customer</span>
                  <span>{{invoice_pos.sale.client_name}}</span>
                  <span>العميل</span>
                </div>
                <div v-show="pos_settings.show_Warehouse" style="display:flex;justify-content:space-between;">
                  <span>Warehouse</span>
                  <span>{{invoice_pos.sale.warehouse_name}}</span>
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
                  <tr v-for="detail_invoice in invoice_pos.details" style="border-bottom:1px dashed #eee;">
                    <td>
                      {{detail_invoice.name}}
                      <br v-if="Number(detail_invoice.tax_percent || detail_invoice.tax_rate || 0) > 0">
                      <small v-if="Number(detail_invoice.tax_percent || detail_invoice.tax_rate || 0) > 0">VAT @ {{ formatNumber(Number(detail_invoice.tax_percent || detail_invoice.tax_rate || 0),2) }}% ({{ formatPriceDisplay(detail_invoice.total * Number(detail_invoice.tax_percent || detail_invoice.tax_rate || 0) / 100, 2) }})</small>
                      <br v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null">
                      <span v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null ">IMEI/SN الرقم التسلسلي : {{detail_invoice.imei_number}}</span>
                    </td>
                    <td style="text-align:center">{{formatNumber(detail_invoice.quantity,2)}} {{detail_invoice.unit_sale}}</td>
                    <td style="text-align:center">{{ formatPriceDisplay(detail_invoice.total/detail_invoice.quantity,2) }}</td>
                    <td style="text-align:right">{{ formatPriceDisplay(detail_invoice.total,2) }}</td>
                  </tr>
                </tbody>
              </table>

              <table style="width:100%;font-size:10px;border-top:1px dashed #000;margin-top:4px;">
                <colgroup><col style="width:35%"><col style="width:5%"><col style="width:25%"><col style="width:35%"></colgroup>
                <tbody>
                  <tr>
                    <td style="text-align:left" class="total">Sub Total</td>
                    <td class="total">:</td>
                    <td style="text-align:center" class="total">{{ formatPriceWithSymbol(invoice_pos.symbol, invoiceSubtotal - invoiceDetailsTaxTotal, 2) }}</td>
                    <td style="text-align:right" class="total">المجموع الفرعي</td>
                  </tr>
                  <tr v-show="pos_settings.show_discount">
                    <td style="text-align:left" class="total">Discount</td>
                    <td class="total">:</td>
                    <td style="text-align:center" class="total">
                      <template v-if="String(invoice_pos.sale.discount_Method || '2') === '1'">
                        {{ formatNumber(invoice_pos.sale.discount, 2) }}% ({{ formatPriceWithSymbol(invoice_pos.symbol, calculatedManualDiscountAmount ,2) }})
                      </template>
                      <template v-else>
                        {{ formatPriceWithSymbol(invoice_pos.symbol, calculatedManualDiscountAmount ,2) }}
                      </template>
                    </td>
                    <td style="text-align:right" class="total">تخفيض</td>
                  </tr>
                  <tr v-show="pos_settings.show_discount && invoice_pos.sale.discount_from_points && Number(invoice_pos.sale.discount_from_points) > 0">
                    <td style="text-align:left" class="total">Discount from Points</td>
                    <td class="total">:</td>
                    <td style="text-align:center" class="total">{{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.discount_from_points ,2) }}</td>
                    <td style="text-align:right" class="total">خصم من النقاط</td>
                  </tr>
                  <tr v-show="pos_settings.show_tax && Number(invoice_pos.sale.taxe || 0) > 0">
                    <td style="text-align:left" class="total">VAT @ Total</td>
                    <td class="total">:</td>
                    <td style="text-align:center" class="total">{{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.taxe ,2) }}</td>
                    <td style="text-align:right" class="total">قيمة الضريبة</td>
                  </tr>
                  <tr v-show="pos_settings.show_shipping">
                    <td style="text-align:left" class="total">Shipping</td>
                    <td class="total">:</td>
                    <td style="text-align:center" class="total">{{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.shipping ,2) }}</td>
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
                    <td style="text-align:center">{{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.GrandTotal ,2) }}</td>
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
                    <td style="text-align:center">{{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.paid_amount ,2) }}</td>
                    <td style="text-align:right"><strong>المبلغ المدفوع</strong></td>
                  </tr>
                  <tr v-show="pos_settings.show_due !== 0">
                    <td style="text-align:left"><strong>Balance</strong></td>
                    <td><strong>:</strong></td>
                    <td style="text-align:center">{{ formatPriceWithSymbol(invoice_pos.symbol, (invoice_pos.sale.GrandTotal - invoice_pos.sale.paid_amount), 2) }}</td>
                    <td style="text-align:right"><strong>الرصيد</strong></td>
                  </tr>
                </tbody>
              </table>

              <table class="change mt-3" style="font-size:10px;width:100%;" v-show="pos_settings.show_payments !== 0 && invoice_pos.sale.paid_amount > 0">
                <thead>
                  <tr style="background:#eee;">
                    <th style="text-align:left;" colspan="1">Paid By / طريقة الدفع:</th>
                    <th style="text-align:center;" colspan="2">Amount / المبلغ:</th>
                    <th style="text-align:right;" colspan="1">Change / الباقي:</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="payment_pos in payments">
                    <tr :key="'pay4-' + payment_pos.id">
                      <td style="text-align:left;" colspan="1">{{payment_pos.payment_method?payment_pos.payment_method.name:'---'}}</td>
                      <td style="text-align:center;" colspan="2">{{ formatPriceDisplay(payment_pos.montant ,2) }}</td>
                      <td style="text-align:right;" colspan="1">{{ formatPriceDisplay(payment_pos.change ,2) }}</td>
                    </tr>
                    <tr v-if="payment_pos.notes" :key="'pay4-note-' + payment_pos.id">
                      <td colspan="4" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;">
                        {{$t('Payment_note')}} / ملاحظة الدفع: {{payment_pos.notes}}
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>

              <div id="legalcopy" class="ml-2">
                <div v-if="invoice_pos.sale && invoice_pos.sale.notes" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;">
                  {{$t('sale_note')}} / ملاحظة البيع: {{invoice_pos.sale.notes}}
                </div>
                <div v-show="pos_settings.show_note && pos_settings.note_customer" class="mt-3" style="border-top:1px dashed #000;padding-top:6px;font-size:9px;line-height:1.5;text-align:center;white-space:pre-line;">
                  <strong>{{pos_settings.note_customer}}</strong>
                </div>
                <!-- Receipt QR codes (ZATCA + Invoice URL) -->
                <div v-if="(invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0) || (pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref)" class="receipt-qr-row mt-2">
                  <div v-if="invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0" class="receipt-qr-block">
                    <div class="receipt-qr-title">ZATCA QR</div>
                    <div class="receipt-qr-canvas" ref="zatcaQrcodePos"></div>
                  </div>
                  <div v-if="pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref" class="receipt-qr-block">
                    <div class="receipt-qr-title">Invoice QR</div>
                    <div class="receipt-qr-canvas" ref="invoiceUrlQr"></div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
          <button @click="print_pos()" class="btn btn-outline-primary">
            <lucide-icon name="receipt" />
            {{$t('print')}}
          </button>
        </b-modal>

  <b-modal id="show_draft_sales" size="lg" hide-footer :title="$t('Recent_Drafts')">
    <div>
      <table class="table table-sm">
        <thead>
          <tr>
            <th>{{ $t('date') }}</th>
            <th>{{ $t('Reference') }}</th>
            <th>{{ $t('Customer') }}</th>
            <th class="text-right">{{ $t('Total') }}</th>
            <th class="text-right">{{ $t('Action') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="draft_sales.length === 0">
            <td colspan="5" class="text-center text-muted py-3">{{ $t('NodataAvailable') }}</td>
          </tr>
          <tr v-for="d in draft_sales" :key="d.id">
            <td>{{ d.date }}</td>
            <td>{{ d.Ref }}</td>
            <td>{{ d.client_name }}</td>
            <td class="text-right">{{ formatNumber(d.GrandTotal, 2) }}</td>
            <td class="text-right">
              <b-button size="sm" variant="outline-success" class="mr-2" @click="loadDraftSale(d.id)" :disabled="openingDraftId === d.id" :title="openingDraftId === d.id ? $t('Loading') : $t('Open')">
                <template v-if="openingDraftId === d.id">
                  <span class="spinner sm spinner-primary"></span>
                </template>
                <template v-else>
                  <lucide-icon name="arrow-right" />
                </template>
              </b-button>
              <b-button size="sm" variant="outline-danger" @click="Remove_Draft_Sale(d.id)" :title="$t('Delete')">
                <lucide-icon name="trash-2" />
              </b-button>
            </td>
          </tr>
        </tbody>
      </table>
      
      <!-- Pagination -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="totalRows_draft_sales > limit">
        <div class="text-muted">
          {{ $t('Showing') }} {{ draft_sales.length }} {{ $t('of') }} {{ totalRows_draft_sales }} {{ $t('entries') }}
        </div>
        <b-pagination
          v-model="draft_sales_page"
          :total-rows="totalRows_draft_sales"
          :per-page="parseInt(limit)"
          @change="onPageChangeDraftSales"
          align="right"
          size="sm"
        ></b-pagination>
      </div>
    </div>
  </b-modal>

  <validation-observer ref="Update_Detail">
    <b-modal hide-footer size="lg" id="form_Update_Detail" :title="detail.name">
    <b-form @submit.prevent="submit_Update_Detail">
        <b-row>
          <!-- Unit Price + Price Type -->
          <b-col lg="12" class="mb-2" v-if="detailLoading">
            <div class="text-center py-3">
              <div class="spinner sm spinner-primary"></div>
            </div>
          </b-col>
          <b-col lg="6" md="6" sm="12" v-show="!detailLoading">
            <validation-provider
              name="Product Price"
              :rules="{ required: true , regex: /^\d*\.?\d*$/}"
              v-slot="validationContext"
            >
              <b-form-group :label="$t('ProductPrice') + ' ' + '*'" id="Price-input">
                <div class="d-flex align-items-center">
                  <b-form-input
                    label="Product Price"
                    v-model="detail.Unit_price"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Price-feedback"
                    class="mr-2"
                  ></b-form-input>
                  <select
                    class="form-control pos-price-select"
                    v-model="detail.price_type"
                    @change="onChangePriceType(detail)"
                  >
                    <option :value="'retail'">{{$t('Retail Price')}}</option>
                    <option :value="'wholesale'">{{$t('Wholesale Price')}}</option>
                  </select>
                </div>
                <b-form-invalid-feedback id="Price-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
              </b-form-group>
            </validation-provider>
          </b-col>

           <!-- Unit Sale -->
           <b-col lg="6" md="6" sm="12" v-if="detail.product_type != 'is_service'" v-show="!detailLoading">
            <validation-provider name="Unit Sale" :rules="{ required: true}">
              <b-form-group slot-scope="{ valid, errors }" :label="$t('UnitSale') + ' ' + '*'"><v-select
                  :class="{'is-invalid': !!errors.length}"
                  :state="errors[0] ? false : (valid ? true : null)"
                  v-model="detail.sale_unit_id"
                  :placeholder="$t('Choose_Unit_Sale')"
                  :reduce="label => label.value"
                  :options="units.map(units => ({label: units.name, value: units.id}))"
                />
                <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
              </b-form-group>
            </validation-provider>
          </b-col>

           <!-- Tax -->
           <b-col lg="6" md="6" sm="12" v-show="!detailLoading">
            <validation-provider
              name="Tax"
              :rules="{ required: true , regex: /^\d*\.?\d*$/}"
              v-slot="validationContext"
            >
              <b-form-group :label="$t('Tax') + ' ' + '*'"><b-input-group append="%">
                  <b-form-input
                    label="Tax"
                    v-model="detail.tax_percent"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Tax-feedback"
                  ></b-form-input>
                </b-input-group>
                <b-form-invalid-feedback
                  id="Tax-feedback"
                >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
      </b-form-group>
            </validation-provider>
          </b-col>

          <!-- Tax Method -->
          <b-col lg="6" md="6" sm="12" v-show="!detailLoading">
            <validation-provider name="Tax Method" :rules="{ required: true}">
              <b-form-group slot-scope="{ valid, errors }" :label="$t('TaxMethod') + ' ' + '*'"><v-select
                  :class="{'is-invalid': !!errors.length}"
                  :state="errors[0] ? false : (valid ? true : null)"
                  v-model="detail.tax_method"
                  :reduce="label => label.value"
                  :placeholder="$t('Choose_Method')"
                  :options="
                 [
                  {label: 'Exclusive', value: 1},
                  {label: 'Inclusive', value: 2}
                 ]"
                ></v-select>
                <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
      </b-form-group>
            </validation-provider>
          </b-col>

           <!-- Discount Rate -->
           <b-col lg="6" md="6" sm="12" v-show="!detailLoading">
            <validation-provider
              name="Discount Rate"
              :rules="{ required: true , regex: /^\d*\.?\d*$/}"
              v-slot="validationContext"
            >
              <b-form-group :label="$t('Discount') + ' ' + '*'"><b-form-input
                  label="Discount"
                  v-model="detail.discount"
                  :state="getValidationState(validationContext)"
                  aria-describedby="Discount-feedback"
                ></b-form-input>
                <b-form-invalid-feedback
                  id="Discount-feedback"
                >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
      </b-form-group>
            </validation-provider>
          </b-col>

         

          <!-- Discount Method -->
          <b-col lg="6" md="6" sm="12" v-show="!detailLoading">
            <validation-provider name="Discount Method" :rules="{ required: true}">
              <b-form-group slot-scope="{ valid, errors }" :label="$t('Discount_Method') + ' ' + '*'"><v-select
                  v-model="detail.discount_Method"
                  :reduce="label => label.value"
                  :placeholder="$t('Choose_Method')"
                  :class="{'is-invalid': !!errors.length}"
                  :state="errors[0] ? false : (valid ? true : null)"
                  :options="
                    [
                      {label: 'Percent %', value: '1'},
                      {label: 'Fixed', value: '2'}
                    ]"
                ></v-select>
                <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
      </b-form-group>
            </validation-provider>
          </b-col>

         

         

          <!-- Imei or serial numbers -->
          <b-col lg="12" md="12" sm="12" v-show="detail.is_imei && !detailLoading">
            <b-form-group :label="$t('Add_product_IMEI_Serial_number')">
              <b-form-input
                label="Add_product_IMEI_Serial_number"
                v-model="detail.imei_number"
                :placeholder="$t('Add_product_IMEI_Serial_number')"
              ></b-form-input>
            </b-form-group>
          </b-col>

          <b-col md="12">
            <b-form-group>
              <b-button variant="primary" type="submit">{{$t('submit')}}</b-button>
            </b-form-group>
          </b-col>
        </b-row>
    </b-form>
  </b-modal>
  </validation-observer>

  <validation-observer ref="Create_Customer">
  <b-modal id="New_Customer" hide-footer size="lg" :title="$t('New_Customer')">
    <b-form @submit.prevent="Submit_Customer" class="new-customer-form">
      <b-row>
        <b-col md="6" sm="12">
          <b-form-group :label="$t('Name')">
            <b-form-input v-model="client.name" :placeholder="$t('Name')" required />
          </b-form-group>
        </b-col>
        <b-col md="6" sm="12">
          <b-form-group :label="$t('Email')">
            <b-form-input type="email" v-model="client.email" :placeholder="$t('Email')" />
          </b-form-group>
        </b-col>

        <b-col md="6" sm="12">
          <b-form-group :label="$t('Phone')">
            <b-form-input v-model="client.phone" :placeholder="$t('Phone')" />
          </b-form-group>
        </b-col>
        <b-col md="6" sm="12">
          <b-form-group :label="$t('Tax_Number')">
            <b-form-input v-model="client.tax_number" :placeholder="$t('Tax_Number')" />
          </b-form-group>
        </b-col>

        <b-col md="6" sm="12">
          <b-form-group :label="$t('Country')">
            <b-form-input v-model="client.country" :placeholder="$t('Country')" />
          </b-form-group>
        </b-col>
        <b-col md="6" sm="12">
          <b-form-group :label="$t('City')">
            <b-form-input v-model="client.city" :placeholder="$t('City')" />
          </b-form-group>
        </b-col>

        <b-col md="12">
          <b-form-group :label="$t('Adress')">
            <b-form-input v-model="client.adresse" :placeholder="$t('Adress')" />
          </b-form-group>
        </b-col>

        <b-col md="12">
          <b-form-group>
            <div class="loyalty-eligible-row">
              <b-form-checkbox v-model="client.is_royalty_eligible" switch class="mb-0 loyalty-switch">
                {{ $t('Loyalty_Eligible') }}
              </b-form-checkbox>
              <small class="loyalty-help text-muted">{{ $t('Loyalty_Points_Help') }}</small>
            </div>
          </b-form-group>
        </b-col>

        <b-col cols="12" class="d-flex justify-content-end">
        <b-button variant="secondary" class="mr-2" @click="$bvModal.hide('New_Customer')">{{ $t('Close') }}</b-button>
        <b-button variant="primary" type="submit">{{ $t('Save') }}</b-button>
        </b-col>
      </b-row>
    </b-form>
  </b-modal>
  </validation-observer>

  <!-- Quick Add Customer Modal -->
  <validation-observer ref="Quick_Add_Customer_Form">
    <b-modal hide-footer size="lg" id="Quick_Add_Customer" :title="$t('Quick_Add_Customer')">
      <b-form @submit.prevent="Submit_Quick_Add_Customer" class="quick-add-customer-form">
        <b-row>
          <!-- Customer Name -->
          <b-col md="6" sm="12">
            <validation-provider
              name="Name Customer"
              :rules="{ required: true}"
              v-slot="validationContext"
            >
              <b-form-group :label="$t('CustomerName') + ' ' + '*'">
                <b-form-input
                  :state="getValidationState(validationContext)"
                  aria-describedby="name-feedback"
                  label="name"
                  :placeholder="$t('CustomerName')"
                  v-model="client.name"
                ></b-form-input>
                <b-form-invalid-feedback id="name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
              </b-form-group>
            </validation-provider>
          </b-col>
          
          <!-- Customer Email -->
          <b-col md="6" sm="12">
            <b-form-group :label="$t('Email')">
              <b-form-input
                label="email"
                v-model="client.email"
                :placeholder="$t('Email')"
              ></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Customer Phone -->
          <b-col md="6" sm="12">
            <b-form-group :label="$t('Phone')">
              <b-form-input
                label="Phone"
                v-model="client.phone"
                :placeholder="$t('Phone')"
              ></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Customer Country -->
          <b-col md="6" sm="12">
            <b-form-group :label="$t('Country')">
              <b-form-input
                label="Country"
                v-model="client.country"
                :placeholder="$t('Country')"
              ></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Customer City -->
          <b-col md="6" sm="12">
            <b-form-group :label="$t('City')">
              <b-form-input
                label="City"
                v-model="client.city"
                :placeholder="$t('City')"
              ></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Customer Tax Number -->
          <b-col md="6" sm="12">
            <b-form-group :label="$t('Tax_Number')">
              <b-form-input
                label="Tax Number"
                v-model="client.tax_number"
                :placeholder="$t('Tax_Number')"
              ></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Customer Address -->
          <b-col md="12" sm="12">
            <b-form-group :label="$t('Adress')">
              <textarea
                label="Adress"
                class="form-control"
                rows="4"
                v-model="client.adresse"
                :placeholder="$t('Adress')"
              ></textarea>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12" class="mt-4 mb-4">
            <div class="psx-form-check">
              <input type="checkbox" v-model="client.is_royalty_eligible" class="psx-checkbox psx-form-check-input" id="is_royalty_eligible">
              <label class="psx-form-check-label" for="is_royalty_eligible">
                <h5>{{ $t('Is_Royalty_Eligible') }}</h5>
              </label>
            </div>
          </b-col>

          <!-- Custom Fields (same as CreateCustomer.vue, but for quick add) -->
          <b-col md="12" sm="12" class="mt-3">
            <CustomFieldsForm
              entity-type="client"
              v-model="quickAddCustomFieldValues"
            />
          </b-col>

          <b-col md="12" class="mt-3">
            <b-button variant="secondary" class="mr-2" @click="$bvModal.hide('Quick_Add_Customer')">{{ $t('Cancel') }}</b-button>
            <b-button variant="primary" type="submit" :disabled="SubmitProcessing">{{$t('submit')}}</b-button>
            <div v-once class="typo__p" v-if="SubmitProcessing">
              <div class="spinner sm spinner-primary mt-3"></div>
            </div>
          </b-col>

        </b-row>
      </b-form>
    </b-modal>
  </validation-observer>

  <b-modal
    id="modal_today_sales"
    hide-footer
    hide-header
    size="lg"
    modal-class="ts-modal-modern"
    body-class="ts-modal-body-wrap"
    :title="$t('Today_Sales')"
  >
    <div class="ts-modal-body">
      <!-- Hero / summary -->
      <div class="ts-hero">
        <button
          type="button"
          class="ts-close"
          :aria-label="$t('Close')"
          @click="$bvModal.hide('modal_today_sales')"
        >
          <lucide-icon name="x" />
        </button>

        <div class="ts-hero-top">
          <div class="ts-hero-title">
            <div class="ts-hero-eyebrow">
              <lucide-icon name="calendar" />
              <span>{{ today_sales.today || $t('Today') }}</span>
            </div>
            <div class="ts-hero-heading">{{ $t('Today_Sales') }}</div>
          </div>
        </div>

        <div class="ts-hero-amount-wrap">
          <div class="ts-hero-label">{{ $t('Total_Sales') }}</div>
          <div class="ts-hero-amount">
            {{ formatPriceWithCurrentCurrency(today_sales.total_sales_amount || 0, 2) }}
          </div>
        </div>

        <div class="ts-hero-stats">
          <div class="ts-hero-stat">
            <div class="ts-hero-stat-icon paid"><lucide-icon name="check-circle" /></div>
            <div class="ts-hero-stat-info">
              <div class="ts-hero-stat-label">{{ $t('Total_Amount_Paid') }}</div>
              <div class="ts-hero-stat-val">
                {{ formatPriceWithCurrentCurrency(today_sales.total_amount_paid || 0, 2) }}
              </div>
            </div>
          </div>
          <div class="ts-hero-divider"></div>
          <div class="ts-hero-stat">
            <div class="ts-hero-stat-icon due"><lucide-icon name="clock" /></div>
            <div class="ts-hero-stat-info">
              <div class="ts-hero-stat-label">{{ $t('Due') || 'Due' }}</div>
              <div class="ts-hero-stat-val">
                {{ formatPriceWithCurrentCurrency(Math.max(0, (Number(today_sales.total_sales_amount) || 0) - (Number(today_sales.total_amount_paid) || 0)), 2) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment methods breakdown -->
      <div class="ts-section" v-if="(today_sales.payment_methods || []).length">
        <div class="ts-section-head">
          <div class="ts-section-title">
            <lucide-icon name="credit-card" />
            <span>{{ $t('Payment_Methods') || 'Payment Methods' }}</span>
          </div>
          <div class="ts-section-sub">
            {{ formatPriceWithCurrentCurrency(today_sales.total_amount_paid || 0, 2) }}
          </div>
        </div>

        <div class="ts-methods">
          <div
            class="ts-method"
            v-for="method in (today_sales.payment_methods || [])"
            :key="method.id"
          >
            <div class="ts-method-icon"><lucide-icon name="credit-card" /></div>
            <div class="ts-method-body">
              <div class="ts-method-row">
                <div class="ts-method-name">{{ method.name }}</div>
                <div class="ts-method-amount">
                  {{ formatPriceWithCurrentCurrency(method.total || 0, 2) }}
                </div>
              </div>
              <div class="ts-method-bar-wrap">
                <div
                  class="ts-method-bar"
                  :style="{ width: ((Number(method.total) || 0) / Math.max(1, Number(today_sales.total_amount_paid) || 0) * 100) + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="ts-empty" v-else>
        <div class="ts-empty-icon"><lucide-icon name="inbox" /></div>
        <div class="ts-empty-text">{{ $t('No_Payment_Methods') || 'No payments recorded yet today' }}</div>
      </div>
    </div>
  </b-modal>

  <!-- ============ POS Settings modal ============ -->
  <b-modal
    id="modal_pos_settings"
    hide-footer
    hide-header
    size="lg"
    modal-class="ps-modal-modern"
    body-class="ps-modal-body-wrap"
    :title="$t('POS_Settings')"
  >
    <div class="ps-modal-body">
      <!-- Hero -->
      <div class="ps-hero">
        <button
          type="button"
          class="ps-close"
          :aria-label="$t('Close')"
          @click="$bvModal.hide('modal_pos_settings')"
        >
          <lucide-icon name="x" />
        </button>
        <div class="ps-hero-eyebrow">
          <lucide-icon name="settings" />
          <span>{{ $t('Settings') || 'Settings' }}</span>
        </div>
        <div class="ps-hero-heading">{{ $t('POS_Settings') }}</div>
        <div class="ps-hero-sub">
          {{ $t('Pos_Settings_Modal_Sub') || 'Configure POS behavior, display, and printing — changes apply immediately.' }}
        </div>
      </div>

      <div class="ps-loading" v-if="ps_loading">
        <div class="spinner spinner-primary"></div>
      </div>

      <validation-observer ref="Submit_Pos_Settings_Modal" v-else>
        <b-form @submit.prevent="Submit_Pos_Settings_Modal">

          <!-- Behavior section -->
          <div class="ps-section">
            <div class="ps-section-title">
              <lucide-icon name="zap" />
              <span>{{ $t('Behavior') || 'Behavior' }}</span>
            </div>
            <div class="ps-grid">
              <label class="ps-toggle">
                <div class="ps-toggle-info">
                  <div class="ps-toggle-label">{{$t('Quick_Add_Customer')}}</div>
                  <div class="ps-toggle-help">{{$t('Enable_Quick_Add_Customer_popup_in_POS')}}</div>
                </div>
                <span class="ps-switch">
                  <input type="checkbox" v-model="pos_settings.quick_add_customer">
                  <span class="ps-switch-slider"></span>
                </span>
              </label>

              <label class="ps-toggle">
                <div class="ps-toggle-info">
                  <div class="ps-toggle-label">{{$t('Barcode_Scanning_Sound')}}</div>
                  <div class="ps-toggle-help">{{$t('Enable_sound_when_scanning_barcodes_in_POS')}}</div>
                </div>
                <span class="ps-switch">
                  <input type="checkbox" v-model="pos_settings.barcode_scanning_sound">
                  <span class="ps-switch-slider"></span>
                </span>
              </label>

              <label class="ps-toggle">
                <div class="ps-toggle-info">
                  <div class="ps-toggle-label">{{$t('Enable_Hold_Sales')}}</div>
                  <div class="ps-toggle-help">{{$t('Enable_disable_Hold_Sales_feature_in_POS')}}</div>
                </div>
                <span class="ps-switch">
                  <input type="checkbox" v-model="pos_settings.enable_hold_sales">
                  <span class="ps-switch-slider"></span>
                </span>
              </label>

              <label class="ps-toggle">
                <div class="ps-toggle-info">
                  <div class="ps-toggle-label">{{$t('Enable_Customer_Points_in_POS')}}</div>
                  <div class="ps-toggle-help">{{$t('Enable_disable_customer_points_system_in_POS')}}</div>
                </div>
                <span class="ps-switch">
                  <input type="checkbox" v-model="pos_settings.enable_customer_points">
                  <span class="ps-switch-slider"></span>
                </span>
              </label>

              <label class="ps-toggle">
                <div class="ps-toggle-info">
                  <div class="ps-toggle-label">{{$t('Allow_overselling') || 'Allow Overselling'}}</div>
                  <div class="ps-toggle-help">{{$t('Allow_overselling_help') || 'Allow selling products even when stock is zero or negative.'}}</div>
                </div>
                <span class="ps-switch">
                  <input type="checkbox" v-model="pos_settings.allow_overselling">
                  <span class="ps-switch-slider"></span>
                </span>
              </label>

              <label class="ps-toggle">
                <div class="ps-toggle-info">
                  <div class="ps-toggle-label">{{$t('Enable_Print_Invoice')}}</div>
                  <div class="ps-toggle-help">{{ $t('Enable_Print_Invoice_Help') || 'Auto-print receipt after each sale.' }}</div>
                </div>
                <span class="ps-switch">
                  <input type="checkbox" v-model="pos_settings.is_printable" :true-value="1" :false-value="0">
                  <span class="ps-switch-slider"></span>
                </span>
              </label>
            </div>
          </div>

          <!-- Display section -->
          <div class="ps-section">
            <div class="ps-section-title">
              <lucide-icon name="eye" />
              <span>{{ $t('Display') || 'Display' }}</span>
            </div>
            <div class="ps-grid">
              <label class="ps-toggle">
                <div class="ps-toggle-info">
                  <div class="ps-toggle-label">{{$t('Show_Product_Images_in_POS')}}</div>
                  <div class="ps-toggle-help">{{$t('Show_hide_product_images_in_POS_product_listing')}}</div>
                </div>
                <span class="ps-switch">
                  <input type="checkbox" v-model="pos_settings.show_product_images">
                  <span class="ps-switch-slider"></span>
                </span>
              </label>

              <label class="ps-toggle">
                <div class="ps-toggle-info">
                  <div class="ps-toggle-label">{{$t('Show_Stock_Quantity_in_POS')}}</div>
                  <div class="ps-toggle-help">{{$t('Show_hide_stock_quantity_in_POS')}}</div>
                </div>
                <span class="ps-switch">
                  <input type="checkbox" v-model="pos_settings.show_stock_quantity">
                  <span class="ps-switch-slider"></span>
                </span>
              </label>

              <label class="ps-toggle">
                <div class="ps-toggle-info">
                  <div class="ps-toggle-label">{{$t('Show_Categories_in_POS')}}</div>
                  <div class="ps-toggle-help">{{$t('Show_hide_categories_in_POS')}}</div>
                </div>
                <span class="ps-switch">
                  <input type="checkbox" v-model="pos_settings.show_categories">
                  <span class="ps-switch-slider"></span>
                </span>
              </label>

              <label class="ps-toggle">
                <div class="ps-toggle-info">
                  <div class="ps-toggle-label">{{$t('Show_Brands_in_POS')}}</div>
                  <div class="ps-toggle-help">{{$t('Show_hide_brands_in_POS')}}</div>
                </div>
                <span class="ps-switch">
                  <input type="checkbox" v-model="pos_settings.show_brands">
                  <span class="ps-switch-slider"></span>
                </span>
              </label>
            </div>

            <div class="ps-field">
              <validation-provider
                name="products_per_page"
                :rules="{ required: true }"
                v-slot="validationContext"
              >
                <label class="ps-field-label">{{ $t('How_many_items_to_display_in_POS') || 'How many items do you want to display in POS' }} *</label>
                <b-form-input
                  :state="getValidationState(validationContext)"
                  v-model="pos_settings.products_per_page"
                  type="text"
                  class="ps-input"
                />
                <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
              </validation-provider>
            </div>
          </div>

          <!-- Cash drawer section -->
          <div class="ps-section">
            <div class="ps-section-title">
              <lucide-icon name="banknote" />
              <span>{{ $t('Cash_Drawer_Settings') }}</span>
            </div>
            <div class="ps-help-banner">{{ $t('Cash_Drawer_Auto_Open_Help') }}</div>
            <div class="ps-grid">
              <label class="ps-toggle">
                <div class="ps-toggle-info">
                  <div class="ps-toggle-label">{{ $t('Cash_Drawer_Auto_Open') }}</div>
                </div>
                <span class="ps-switch">
                  <input type="checkbox" v-model="pos_settings.cash_drawer_auto_open" :true-value="true" :false-value="false">
                  <span class="ps-switch-slider"></span>
                </span>
              </label>
            </div>

            <div class="ps-field">
              <label class="ps-field-label">{{ $t('Cash_Drawer_Printer_Name') }}</label>
              <b-form-input
                v-model="pos_settings.cash_drawer_printer_name"
                :placeholder="$t('Leave_blank_for_default_receipt_printer')"
                maxlength="192"
                class="ps-input"
              />
              <small class="ps-field-help">{{ $t('Cash_Drawer_Printer_Name_Help') }}</small>
            </div>
          </div>

          <!-- Receipt section -->
          <div class="ps-section">
            <div class="ps-section-title">
              <lucide-icon name="receipt" />
              <span>{{ $t('Receipt') || 'Receipt' }}</span>
            </div>
            <div class="ps-field">
              <label class="ps-field-label">{{ $t('Invoice_Format') }}</label>
              <b-form-radio-group
                v-model="invoice_format"
                :options="ps_invoiceFormatOptions.map(opt => ({ value: opt.value, text: $t(opt.textKey) }))"
                buttons
                button-variant="outline-primary"
                size="sm"
              />
              <small class="ps-field-help d-block mt-1">{{ $t('Invoice_Format_help') }}</small>
            </div>
          </div>

          <!-- Footer -->
          <div class="ps-footer">
            <button
              type="button"
              class="ps-btn ps-btn-secondary"
              @click="$bvModal.hide('modal_pos_settings')"
            >
              {{ $t('Cancel') || 'Cancel' }}
            </button>
            <button
              type="submit"
              class="ps-btn ps-btn-primary"
              :disabled="ps_saving"
            >
              <lucide-icon name="check" v-if="!ps_saving" />
              <span>{{ ps_saving ? ($t('Saving') || 'Saving…') : ($t('submit') || 'Save') }}</span>
            </button>
          </div>
        </b-form>
      </validation-observer>
    </div>
  </b-modal>

    <b-modal id="OpenRegisterModal" :title="$t('Open Register')" hide-footer>
      <div class="form-group">
        <label>{{$t('warehouse')}}</label>
        <b-form-select v-model="registerForm.warehouse_id" :options="warehouseOptions"></b-form-select>
      </div>
      <div class="form-group">
        <label>{{$t('Opening_Balance')}}</label>
        <input type="text" min="0" step="0.01" class="form-control" v-model.number="registerForm.opening_balance" />
      </div>
      <div class="form-group">
        <label>{{$t('notes')}}</label>
        <textarea class="form-control" v-model="registerForm.notes"></textarea>
      </div>
      <div class="text-right">
        <b-button variant="secondary" class="mr-2" @click="$bvModal.hide('OpenRegisterModal')">{{$t('Cancel')}}</b-button>
        <b-button variant="success" @click="submitOpenRegister" :disabled="registerBusy">{{$t('Open Register')}}</b-button>
      </div>
    </b-modal>

    <!-- Close Register Modal -->
    <b-modal id="CloseRegisterModal" :title="$t('Close Register')" hide-footer>
      <div class="form-group">
        <label>{{$t('Counted_Cash')}}</label>
        <input type="text" min="0" step="0.01" class="form-control" v-model.number="closeForm.counted_cash" />
      </div>
      <div class="form-group">
        <label>{{$t('Closing_Notes')}}</label>
        <textarea class="form-control" v-model="closeForm.notes"></textarea>
      </div>
      <div class="text-right">
        <b-button variant="secondary" class="mr-2" @click="$bvModal.hide('CloseRegisterModal')">{{$t('Cancel')}}</b-button>
        <b-button variant="danger" @click="submitCloseRegister" :disabled="registerBusy">{{$t('Close Register')}}</b-button>
      </div>
    </b-modal>

    <!-- Online reload confirmation modal (non-blocking) -->
    <b-modal
      id="OnlineReloadModal"
      v-model="onlineReloadModalVisible"
      :hide-footer="true"
      :hide-header-close="true"
      :no-close-on-backdrop="true"
      :no-close-on-esc="true"
      size="md"
      centered
      :title="$t('pos.InternetRestored') || 'Internet connection restored'"
    >
      <p class="mb-3">
        {{ $t('pos.ActiveCheckoutReloadQuestion') || 'You have an active checkout. Would you like to reload the page now or after completing the sale?' }}
      </p>
      <div class="d-flex justify-content-end">
        <b-button variant="outline-primary" class="mr-2" @click="onOnlineReloadAfterSale">
          {{ $t('pos.ReloadAfterSale') || 'After this sale' }}
        </b-button>
        <b-button variant="primary" @click="onOnlineReloadNow">
          {{ $t('pos.ReloadNow') || 'Reload now' }}
        </b-button>
      </div>
    </b-modal>
    <!-- Keyboard Shortcuts Help Modal (opt-in feature, opened with Shift + ?) -->
    <b-modal
      id="pos-keyboard-shortcuts-help"
      size="lg"
      scrollable
      hide-footer
      :title="$t('Keyboard_Shortcuts') || 'Keyboard Shortcuts'"
    >
      <div class="pos-shortcuts-help">
        <p class="text-muted small mb-3">
          {{ $t('Keyboard_Shortcuts_Help_Intro') || 'Speed up checkout with these shortcuts. Enable or disable them in POS Settings.' }}
        </p>
        <table class="table table-sm table-hover">
          <thead>
            <tr>
              <th style="width: 40%">{{ $t('Shortcut') || 'Shortcut' }}</th>
              <th>{{ $t('Action') || 'Action' }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in posShortcutsList" :key="s.id">
              <td>
                <kbd class="pos-shortcut-key">{{ s.keys }}</kbd>
              </td>
              <td>{{ $t(s.descriptionKey) !== s.descriptionKey ? $t(s.descriptionKey) : s.descriptionFallback }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </b-modal>

    <!-- Clear-cart confirmation (opened by ESC shortcut). Two outcomes:
         "Yes, Clear It" wipes the cart, "Draft / Hold" stashes it as a
         draft instead. ESC / backdrop click cancels. -->
    <b-modal
      id="pos-confirm-clear-cart"
      hide-footer
      hide-header
      centered
      size="sm"
      modal-class="pos-confirm-clear-modal"
      body-class="pos-confirm-clear-body"
    >
      <div class="pos-confirm-clear">
        <div class="pos-confirm-clear-icon" aria-hidden="true">
          <lucide-icon name="alert-triangle" />
        </div>
        <h3 class="pos-confirm-clear-title">
          {{ $t('Confirm_Clear_Cart_Title') || 'Are you sure you want to remove all items?' }}
        </h3>
        <p class="pos-confirm-clear-msg">
          {{ $t('Confirm_Clear_Cart_Subtitle') || 'You can clear the cart, or save it as a draft to recall later.' }}
        </p>
        <div class="pos-confirm-clear-actions">
          <button
            type="button"
            class="pos-confirm-btn pos-confirm-btn-secondary"
            @click="$bvModal.hide('pos-confirm-clear-cart'); Submit_Draft();">
            <lucide-icon name="bookmark" />
            <span>{{ $t('Draft_Hold') || 'Draft / Hold' }}</span>
          </button>
          <button
            type="button"
            class="pos-confirm-btn pos-confirm-btn-danger"
            @click="$bvModal.hide('pos-confirm-clear-cart'); Reset_Pos();">
            <lucide-icon name="trash-2" />
            <span>{{ $t('Yes_Clear_It') || 'Yes, Clear It' }}</span>
          </button>
        </div>
      </div>
    </b-modal>

    <!-- ============ Categories drawer ============ -->
    <transition name="cat-drawer">
      <div
        v-if="catDrawerOpen"
        class="cat-drawer-backdrop"
        @click.self="catDrawerOpen = false"
        @keydown.esc="catDrawerOpen = false"
      >
        <aside class="cat-drawer" role="dialog" :aria-label="$t('Categories') || 'Categories'">
          <header class="cat-drawer-header">
            <div class="cat-drawer-title">
              <span class="cat-drawer-title-icon"><lucide-icon name="folder" /></span>
              <div>
                <div class="cat-drawer-title-text">{{ $t('Categories') || 'Categories' }}</div>
                <div class="cat-drawer-title-sub">{{ categories.length }} {{ ((categories.length === 1) ? ($t('category') || 'category') : ($t('categories') || 'categories')) }}</div>
              </div>
            </div>
            <button
              type="button"
              class="cat-drawer-close"
              :aria-label="$t('Close')"
              @click="catDrawerOpen = false"
            >
              <lucide-icon name="x" />
            </button>
          </header>

          <div class="cat-drawer-search">
            <lucide-icon name="search" />
            <input
              v-model="catDrawerSearch"
              type="text"
              :placeholder="$t('Search_Categories') || 'Search categories…'"
            />
            <button
              v-if="catDrawerSearch"
              type="button"
              class="cat-drawer-search-clear"
              @click="catDrawerSearch = ''"
              :aria-label="$t('Clear')"
            >
              <lucide-icon name="x" />
            </button>
          </div>

          <div class="cat-drawer-list">
            <button
              type="button"
              class="cat-drawer-item all"
              :class="{ active: !category_id }"
              @click="selectCategory(null)"
            >
              <span class="cat-drawer-item-icon all"><lucide-icon name="list" /></span>
              <span class="cat-drawer-item-name">{{ $t('pos.All_Categories') || 'All Categories' }}</span>
              <lucide-icon v-if="!category_id" name="check-circle" class="cat-drawer-item-check" />
            </button>

            <button
              type="button"
              class="cat-drawer-item"
              :class="{ active: category_id === c.id }"
              v-for="c in filteredCategories"
              :key="c.id"
              @click="selectCategory(c.id)"
            >
              <span class="cat-drawer-item-icon" :style="{ background: catColor(c.id), color: '#fff' }">{{ catInitial(c.name) }}</span>
              <span class="cat-drawer-item-name">{{ c.name }}</span>
              <lucide-icon v-if="category_id === c.id" name="check-circle" class="cat-drawer-item-check" />
            </button>

            <div class="cat-drawer-empty" v-if="!filteredCategories.length && catDrawerSearch">
              <div class="cat-drawer-empty-icon"><lucide-icon name="search" /></div>
              <div class="cat-drawer-empty-text">{{ $t('No_results') || 'No results' }}</div>
            </div>
          </div>
        </aside>
      </div>
    </transition>

    <!-- ============ Brands drawer ============ -->
    <transition name="cat-drawer">
      <div
        v-if="brandDrawerOpen"
        class="cat-drawer-backdrop"
        @click.self="brandDrawerOpen = false"
        @keydown.esc="brandDrawerOpen = false"
      >
        <aside class="cat-drawer" role="dialog" :aria-label="$t('Brands') || 'Brands'">
          <header class="cat-drawer-header">
            <div class="cat-drawer-title">
              <span class="cat-drawer-title-icon"><lucide-icon name="tag" /></span>
              <div>
                <div class="cat-drawer-title-text">{{ $t('Brands') || 'Brands' }}</div>
                <div class="cat-drawer-title-sub">{{ brands.length }} {{ ((brands.length === 1) ? ($t('brand') || 'brand') : ($t('brands') || 'brands')) }}</div>
              </div>
            </div>
            <button
              type="button"
              class="cat-drawer-close"
              :aria-label="$t('Close')"
              @click="brandDrawerOpen = false"
            >
              <lucide-icon name="x" />
            </button>
          </header>

          <div class="cat-drawer-search">
            <lucide-icon name="search" />
            <input
              v-model="brandDrawerSearch"
              type="text"
              :placeholder="$t('Search_Brands') || 'Search brands…'"
            />
            <button
              v-if="brandDrawerSearch"
              type="button"
              class="cat-drawer-search-clear"
              @click="brandDrawerSearch = ''"
              :aria-label="$t('Clear')"
            >
              <lucide-icon name="x" />
            </button>
          </div>

          <div class="cat-drawer-list">
            <button
              type="button"
              class="cat-drawer-item all"
              :class="{ active: !brand_id }"
              @click="selectBrand(null)"
            >
              <span class="cat-drawer-item-icon all"><lucide-icon name="list" /></span>
              <span class="cat-drawer-item-name">{{ $t('pos.All_Brands') || 'All Brands' }}</span>
              <lucide-icon v-if="!brand_id" name="check-circle" class="cat-drawer-item-check" />
            </button>

            <button
              type="button"
              class="cat-drawer-item"
              :class="{ active: brand_id === b.id }"
              v-for="b in filteredBrands"
              :key="b.id"
              @click="selectBrand(b.id)"
            >
              <span class="cat-drawer-item-icon" :style="{ background: catColor(b.id), color: '#fff' }">{{ catInitial(b.name) }}</span>
              <span class="cat-drawer-item-name">{{ b.name }}</span>
              <lucide-icon v-if="brand_id === b.id" name="check-circle" class="cat-drawer-item-check" />
            </button>

            <div class="cat-drawer-empty" v-if="!filteredBrands.length && brandDrawerSearch">
              <div class="cat-drawer-empty-icon"><lucide-icon name="search" /></div>
              <div class="cat-drawer-empty-text">{{ $t('No_results') || 'No results' }}</div>
            </div>
          </div>
        </aside>
      </div>
    </transition>

    <!-- ============ Warehouses drawer (right side, distinct theme) ============ -->
    <transition name="wh-drawer">
      <div
        v-if="whDrawerOpen"
        class="wh-drawer-backdrop"
        @click.self="whDrawerOpen = false"
        @keydown.esc="whDrawerOpen = false"
      >
        <aside class="wh-drawer" role="dialog" :aria-label="$t('Warehouses') || 'Warehouses'">
          <header class="wh-drawer-header">
            <button
              type="button"
              class="wh-drawer-close"
              :aria-label="$t('Close')"
              @click="whDrawerOpen = false"
            >
              <lucide-icon name="x" />
            </button>
            <div class="wh-drawer-hero-icon">
              <lucide-icon name="warehouse" />
            </div>
            <div class="wh-drawer-hero-text">
              <div class="wh-drawer-hero-eyebrow">{{ $t('Locations') || 'Locations' }}</div>
              <div class="wh-drawer-hero-title">{{ $t('Warehouses') || 'Warehouses' }}</div>
              <div class="wh-drawer-hero-sub">
                {{ warehouses.length }} {{ ((warehouses.length === 1) ? ($t('warehouse') || 'warehouse') : ($t('warehouses') || 'warehouses')) }}<span v-if="sale.warehouse_id"> · {{ $t('Currently_Active') || '1 active' }}</span>
              </div>
            </div>
          </header>

          <div class="wh-drawer-search">
            <lucide-icon name="search" />
            <input
              v-model="whDrawerSearch"
              type="text"
              :placeholder="$t('Search_Warehouses') || 'Search warehouses…'"
            />
            <button
              v-if="whDrawerSearch"
              type="button"
              class="wh-drawer-search-clear"
              @click="whDrawerSearch = ''"
              :aria-label="$t('Clear')"
            >
              <lucide-icon name="x" />
            </button>
          </div>

          <div class="wh-drawer-list">
            <button
              type="button"
              class="wh-drawer-card wh-drawer-card-clear"
              :class="{ active: !sale.warehouse_id }"
              @click="selectWarehouse(null)"
            >
              <div class="wh-drawer-card-icon clear">
                <lucide-icon name="x-circle" />
              </div>
              <div class="wh-drawer-card-body">
                <div class="wh-drawer-card-name">{{ $t('No_Warehouse') || 'No warehouse' }}</div>
                <div class="wh-drawer-card-meta">
                  <span class="wh-drawer-card-hint">{{ $t('Clear_Selection') || 'Clear selection' }}</span>
                </div>
              </div>
              <div class="wh-drawer-card-arrow">
                <lucide-icon v-if="!sale.warehouse_id" name="check-circle" />
                <lucide-icon v-else name="chevron-right" />
              </div>
            </button>

            <button
              type="button"
              class="wh-drawer-card"
              :class="{ active: sale.warehouse_id === wh.id }"
              v-for="wh in filteredWarehouses"
              :key="wh.id"
              @click="selectWarehouse(wh.id)"
            >
              <div class="wh-drawer-card-icon">
                <lucide-icon name="warehouse" />
              </div>
              <div class="wh-drawer-card-body">
                <div class="wh-drawer-card-name">{{ wh.name }}</div>
                <div class="wh-drawer-card-meta">
                  <span class="wh-drawer-card-id">#{{ wh.id }}</span>
                  <span v-if="sale.warehouse_id === wh.id" class="wh-drawer-card-active-pill">
                    <span class="wh-drawer-card-active-dot"></span>
                    {{ $t('Active') || 'Active' }}
                  </span>
                </div>
              </div>
              <div class="wh-drawer-card-arrow">
                <lucide-icon v-if="sale.warehouse_id === wh.id" name="check-circle" />
                <lucide-icon v-else name="chevron-right" />
              </div>
            </button>

            <div class="wh-drawer-empty" v-if="!filteredWarehouses.length && whDrawerSearch">
              <div class="wh-drawer-empty-icon"><lucide-icon name="search" /></div>
              <div class="wh-drawer-empty-text">{{ $t('No_results') || 'No results' }}</div>
            </div>
            <div class="wh-drawer-empty" v-else-if="!warehouses.length">
              <div class="wh-drawer-empty-icon"><lucide-icon name="warehouse" /></div>
              <div class="wh-drawer-empty-text">{{ $t('No_Warehouses') || 'No warehouses available' }}</div>
            </div>
          </div>
        </aside>
      </div>
    </transition>

    <!-- ============ Customers picker (center modal, distinct theme) ============ -->
    <transition name="cust-drawer">
      <div
        v-if="custDrawerOpen"
        class="cust-drawer-backdrop"
        @click.self="custDrawerOpen = false"
        @keydown.esc="custDrawerOpen = false"
      >
        <section class="cust-drawer" role="dialog" :aria-label="$t('Select_Customer') || 'Select customer'">
          <button
            type="button"
            class="cust-drawer-close"
            :aria-label="$t('Close')"
            @click="custDrawerOpen = false"
          >
            <lucide-icon name="x" />
          </button>

          <header class="cust-drawer-header">
            <div class="cust-drawer-hero-icon">
              <lucide-icon name="users" />
            </div>
            <div class="cust-drawer-hero-text">
              <div class="cust-drawer-hero-eyebrow">{{ $t('Customer') || 'Customer' }}</div>
              <div class="cust-drawer-hero-title">{{ $t('Select_Customer') || 'Select Customer' }}</div>
              <div class="cust-drawer-hero-sub">
                {{ filteredCustomers.length }} / {{ clients.length }}
              </div>
            </div>
            <button
              v-if="isQuickAddCustomerEnabled && isOnline"
              type="button"
              class="cust-drawer-quick-add"
              @click="custDrawerOpen = false; Quick_Add_Client()"
            >
              <lucide-icon name="user-plus" />
              <span>{{ $t('Quick_Add_Customer') || 'Quick add' }}</span>
            </button>
          </header>

          <div class="cust-drawer-search">
            <lucide-icon name="search" />
            <input
              v-model="custDrawerSearch"
              type="text"
              :placeholder="$t('Search_Customers') || 'Search by name or phone…'"
              ref="custDrawerSearchInput"
            />
            <button
              v-if="custDrawerSearch"
              type="button"
              class="cust-drawer-search-clear"
              @click="custDrawerSearch = ''"
              :aria-label="$t('Clear')"
            >
              <lucide-icon name="x" />
            </button>
          </div>

          <div class="cust-drawer-list">
            <button
              type="button"
              class="cust-drawer-card cust-drawer-card-clear"
              :class="{ active: !selectedClientId }"
              @click="selectCustomer(null)"
            >
              <span class="cust-drawer-card-avatar clear">
                <lucide-icon name="x-circle" />
              </span>
              <span class="cust-drawer-card-body">
                <span class="cust-drawer-card-name">{{ $t('No_Customer') || 'No customer' }}</span>
                <span class="cust-drawer-card-meta">
                  <span>{{ $t('Clear_Selection') || 'Clear selection' }}</span>
                </span>
              </span>
              <span v-if="!selectedClientId" class="cust-drawer-card-check">
                <lucide-icon name="check" />
              </span>
            </button>

            <button
              type="button"
              class="cust-drawer-card"
              :class="{ active: selectedClientId === c.value }"
              v-for="c in filteredCustomers"
              :key="c.value"
              @click="selectCustomer(c.value)"
            >
              <span class="cust-drawer-card-avatar" :style="{ background: catColor(c.value) }">
                {{ catInitial(c.name || c.label) }}
              </span>
              <span class="cust-drawer-card-body">
                <span class="cust-drawer-card-name">{{ c.name || c.label }}</span>
                <span class="cust-drawer-card-meta" v-if="c.phone">
                  <lucide-icon name="phone" />
                  <span>{{ c.phone }}</span>
                </span>
              </span>
              <span v-if="selectedClientId === c.value" class="cust-drawer-card-check">
                <lucide-icon name="check" />
              </span>
            </button>

            <div class="cust-drawer-empty" v-if="!filteredCustomers.length">
              <div class="cust-drawer-empty-icon"><lucide-icon name="users" /></div>
              <div class="cust-drawer-empty-text">
                {{ custDrawerSearch ? ($t('No_results') || 'No results') : ($t('No_Customers') || 'No customers') }}
              </div>
            </div>
          </div>
        </section>
      </div>
    </transition>

  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapActions, mapGetters } from "vuex";
import vueEasyPrint from "vue-easy-print";
import VueBarcode from "vue-barcode";
import Util from "../../../utils";
import { formatPriceDisplay, getPriceFormatSetting } from "../../../utils/priceFormat";
import { openCashDrawer } from "../../../utils/cashDrawerQz";
import { loadStripe } from "@stripe/stripe-js";
import ModernPaymentModal from "../components/ModernPaymentModal.vue";
import CustomFieldsForm from "../../../components/CustomFieldsForm.vue";
import posKeyboardShortcutsMixin, { POS_SHORTCUTS } from "../../../mixins/posKeyboardShortcuts";

export default {
  components: {
    vueEasyPrint,
    barcode: VueBarcode,
    ModernPaymentModal,
    CustomFieldsForm,
  },
  mixins: [posKeyboardShortcutsMixin],
  metaInfo: {
    title: "POS"
  },
  data() {
    return {
      // ===== Mobile UI state (drives the phone layout) =====
      mobileActiveTab: 'home', // home | cart | hold | recent | more

      sendEmail: false,
      sendSMS: false,
      stripe: {},
      stripe_key: "",
      cardElement: {},
      paymentProcessing: false,
      DraftProcessing: false,
      hasSavedPaymentMethod: false,
      useSavedPaymentMethod: false,
      selectedCard:null,
      card_id:'',
      is_new_credit_card: false,
      submit_showing_credit_card: false,

      totalRows_draft_sales: "",
      draft_sales:[],
      draft_sales_page: 1,
      limit: "10",
          draft_sale_id: '',
      openingDraftId: null,

      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },

      client_name:'',
      paymentLines: [
        { 
          // only the first line shows Received Amount
          amount: 0, 
          payment_method_id: '', 
        }
      ],
      globalPaymentNote: '', 
      selectedAccount: null, 
      payment_methods:[],
      SubmitProcessing: false,
      // --- Customer Display (broadcast, multi-screen) ---
      _cd_broadcast_timer: null,
      customer_display_screen_id: (() => {
        try {
          const s = localStorage.getItem('pos_customer_display_screen_id');
          if (s !== null && s !== '') return String(s);
        } catch (e) {}
        return '1';
      })(),
      search_category: '',
      search_brand: '',
      focused: false,
      timer:null,
      search_input:'',
      product_filter:[],
      isLoading: true,
      load_product: true,
      GrandTotal: 0,
      total: 0,
      Ref: "",
      clients: [],
      units: [],
      unitsByProductId: {},
      warehouses: [],
      payments: [],
      products: [],
      products_pos: [],
      details: [],
      detail: {},
      categories: [],
      brands: [],
      accounts: [],
      default_account_id: null,
      default_payment_method_id: null,
      pos_settings:{
        quick_add_customer: false,
        barcode_scanning_sound: true,
        show_product_images: true,
        show_stock_quantity: true,
        enable_hold_sales: true,
        enable_customer_points: true,
        show_categories: true,
        show_brands: true,
        allow_overselling: false,
        receipt_paper_size: 80,
        direct_network_printing: false,
        network_printer_ip: "",
        network_printer_port: 9100,
      },
      ps_loading: false,
      ps_saving: false,
      ps_invoiceFormatOptions: [
        { value: "thermal", textKey: "Invoice_Thermal" },
        { value: "a4", textKey: "Invoice_A4" },
      ],
      catDrawerOpen: false,
      catDrawerSearch: "",
      brandDrawerOpen: false,
      brandDrawerSearch: "",
      whDrawerOpen: false,
      whDrawerSearch: "",
      custDrawerOpen: false,
      custDrawerSearch: "",
      product_currentPage: 1,
      paginated_Products: [],
      product_perPage: 10,
      product_totalRows: 0,
      productsLoading: false,
      paginated_Brands: "",
      brand_currentPage: 1,
      brand_perPage: 3,
      paginated_Category: "",
      category_currentPage: 1,
      category_perPage: 3,
      barcodeFormat: "CODE128",
      today_sales:{
        total_sales_amount: "",
        total_amount_paid: "",
        today: "",
        total_cash: "",
        total_credit_card: "",
        total_cheque: "",
        payment_methods: [],
      },
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null,
      // Preferred invoice format for POS printing: 'thermal' (default) or 'a4'
      invoice_format: 'thermal',
      invoice_pos: {
        sale: {
          Ref: "",
          client_name: "",
          discount: "",
          taxe: "",
          date: "",
          tax_rate: "",
          shipping: "",
          GrandTotal: "",
          paid_amount: ""
        },
        details: [],
        setting: {
          logo: "",
          CompanyName: "",
          CompanyAdress: "",
          email: "",
          CompanyPhone: "",
          vat_number: "",
          company_name_ar: "",
          zatca_enabled: false,
          // Preferred invoice format for POS printing: 'thermal' (default) or 'a4'
          invoice_format: "thermal"
        },
        zatca_qr: ""
      },
      selectedClientPoints: 0,
      showPointsSection: false,
      points_to_convert: 0,
      // Most recent completed sale id — used by the F9 "print last
      // receipt" shortcut to re-open the receipt modal after the user
      // has dismissed it.
      last_sale_id: null,
      discount_from_points: 0,
      used_points: 0,
      clientIsEligible: false,
      pointsConverted: false,
      selectedClientCreditLimit: 0,
      selectedClientNetBalance: 0,
      point_to_amount_rate: 0,
      zatcaRenderedPos: false,
      public_invoice_url: '',
      sale: {
        warehouse_id: "",
        client_id: "",
        tax_rate: 0,
        shipping: 0,
        discount: 0,
        discount_Method: "2", // "1" for percentage, "2" for fixed (default)
        TaxNet: 0,
        notes:'',
      },
      client: {
        id: "",
        name: "",
        code: "",
        email: "",
        phone: "",
        country: "",
        tax_number: "",
        city: "",
        adresse: "",
        is_royalty_eligible: "",
      },
      quickAddCustomFieldValues: {},
      category_id: "",
      brand_id: "",
      default_tax: 0,
      languages_available:[],
      product: {
        id: "",
        code: "",
        product_type: "",
        current: "",
        quantity: "",
        check_qty: "",
        discount: "",
        DiscountNet: "",
        discount_Method: "",
        sale_unit_id: "",
        fix_stock: "",
        fix_price: "",
        name: "",
        unitSale: "",
        Net_price: "",
        Unit_price: "",
        Unit_price_wholesale: "",
        wholesale_Net_price: "",
        min_price: 0,
        price_type: 'retail',
        retail_unit_price: "",
        wholesale_unit_price: "",
        Total_price: "",
        subtotal: "",
        product_id: "",
        detail_id: "",
        taxe: "",
        tax_percent: "",
        tax_method: "",
        product_variant_id: "",
        is_imei: "",
        imei_number:"",
        is_batch_tracked: false,
      },
      sound: "/audio/Beep.wav",
      audio: new Audio("/audio/Beep.wav"),
      // Cash Register state (optional module)
      registerEnabled: true,
      currentRegister: null,
      registerBusy: false,
      registerForm: { warehouse_id: '', opening_balance: 0, notes: '' },
      closeForm: { counted_cash: 0, notes: '' },
      cashMove: { type: 'in', amount: 0, notes: '' },
      warehouseOptions: [],
      selectedClientId: "",
      productsReady: false,
      uiLoadingProductId: null,
      detailLoading: false,
      uiLoadingDetailId: null,
      detailLoading: false,
      // --- Offline & sync state ---
      isOnline: true,
      offlineSyncInProgress: false,
      offlineSalesCount: 0,
      offlineLastSyncError: null,
      // Latest backend reachability result (from /ping). This is separate from
      // browser online/offline signal and is used to gate sync/modal actions.
      backendReachable: null,
      // When we come back online with an active cart, we show a small
      // confirmation modal offering to reload now or after completing the sale.
      onlineReloadModalVisible: false,
      onlineReloadAfterSale: false,
      // When internet comes back and the cart is empty, we auto-sync offline
      // sales and then reload the page once sync succeeds.
      reloadAfterOfflineSync: false,
      // Server timestamp returned by the last full products fetch / delta
      // sync / sale. Used as the `since` value for incremental stock delta
      // polling so we avoid re-downloading the whole catalog after each sale
      // while still picking up changes made by other cashiers.
      lastProductsSyncAt: null
    };
  },
  computed: {
    ...mapGetters(["currentUser", "currentUserPermissions","show_language"]),

    // Static list of POS keyboard shortcuts used by the help modal.
    posShortcutsList() {
      return POS_SHORTCUTS;
    },

    // Overselling Control: when ON, all POS stock checks are bypassed and
    // sales are allowed even with zero/negative stock. Default OFF preserves
    // the historical strict stock-check behavior for existing installs.
    isOversellingAllowed() {
      return !!(this.pos_settings && this.pos_settings.allow_overselling);
    },

    // Batch validation: when any cart line has a batch problem, Pay Now is blocked.
    // Returns { blocked: bool, reason: string | null } so the button can show a tooltip.
    payNowBatchGate() {
      const details = Array.isArray(this.details) ? this.details : [];
      for (const d of details) {
        if (!d || !d.is_batch_tracked) continue;
        // Loading — defer; don't block, but don't pass either (button stays disabled).
        if (d.batches_loading) {
          return { blocked: true, reason: this.$t('Loading') || 'Loading...' };
        }
        const err = this.batch_line_error ? this.batch_line_error(d) : null;
        if (err) {
          const name = d.name ? d.name : '';
          return { blocked: true, reason: name ? `${name}: ${err}` : err };
        }
      }
      return { blocked: false, reason: null };
    },

    // Receipt subtotal (sum of invoice detail totals; before order tax/discount/shipping)
    invoiceSubtotal() {
      try {
        const details = (this.invoice_pos && Array.isArray(this.invoice_pos.details)) ? this.invoice_pos.details : [];
        return details.reduce((sum, d) => {
          const n = Number(d && d.total != null ? d.total : 0);
          return sum + (Number.isFinite(n) ? n : 0);
        }, 0);
      } catch (e) {
        return 0;
      }
    },

    // Signed public URL for barcode so scanning opens the invoice (no login required)
    invoiceBarcodeUrl() {
      return this.public_invoice_url || '';
    },

    // Sum of per-product VAT (total * tax_percent / 100)
    invoiceDetailsTaxTotal() {
      const details = (this.invoice_pos && Array.isArray(this.invoice_pos.details)) ? this.invoice_pos.details : [];
      return details.reduce((sum, d) => {
        const total = Number(d.total || 0);
        const rate = Number(d.tax_percent || d.tax_rate || 0);
        return sum + (total * rate / 100);
      }, 0);
    },

    // Normalize POS receipt layout selection (1, 2, 3, or 4)
    currentReceiptLayout() {
      const raw = this.pos_settings && this.pos_settings.receipt_layout != null
        ? this.pos_settings.receipt_layout
        : 1;
      const n = Number(raw) || 1;
      return [1, 2, 3, 4].includes(n) ? n : 1;
    },

    // Normalize receipt paper size (58mm, 80mm, 88mm)
    currentReceiptPaperSize() {
      const raw = this.pos_settings && this.pos_settings.receipt_paper_size != null
        ? this.pos_settings.receipt_paper_size
        : 80;
      const n = Number(raw) || 80;
      return [58, 80, 88].includes(n) ? n : 80;
    },

    currentReceiptPaperSizeClass() {
      const size = this.currentReceiptPaperSize;
      if (size === 58) return 'receipt-58';
      if (size === 88) return 'receipt-88';
      return 'receipt-80';
    },

    // Customer options for v-select with phone search capability
    customerOptions() {
      return this.clients.map(client => ({
        label: client.name,
        value: client.id,
        phone: client.phone || '',
        name: client.name || ''
      }));
    },

    // Customer display screen options (multi-screen support)
    customerDisplayScreenOptions() {
      return [1, 2, 3, 4, 5].map(n => ({
        label: this.$t('Screen') + ' ' + n,
        value: String(n)
      }));
    },

    // Check if Quick Add Customer is enabled (handles both boolean and integer values)
    isQuickAddCustomerEnabled() {
      const value = this.pos_settings.quick_add_customer;
      // Handle both boolean and integer (0/1) values
      if (typeof value === 'number') {
        return value === 1;
      }
      return value === true || value === 'true' || value === '1';
    },

    // Label shown on the categories trigger button — falls back to "All Categories"
    selectedCategoryLabel() {
      if (!this.category_id) return this.$t('pos.All_Categories') || 'All Categories';
      const c = (this.categories || []).find(x => x.id === this.category_id);
      return c ? c.name : (this.$t('pos.All_Categories') || 'All Categories');
    },

    // Categories filtered by the drawer's search box
    filteredCategories() {
      const q = (this.catDrawerSearch || '').trim().toLowerCase();
      const list = this.categories || [];
      if (!q) return list;
      return list.filter(c => (c.name || '').toLowerCase().includes(q));
    },

    // Label shown on the brands trigger button — falls back to "All Brands"
    selectedBrandLabel() {
      if (!this.brand_id) return this.$t('pos.All_Brands') || 'All Brands';
      const b = (this.brands || []).find(x => x.id === this.brand_id);
      return b ? b.name : (this.$t('pos.All_Brands') || 'All Brands');
    },

    // Brands filtered by the drawer's search box
    filteredBrands() {
      const q = (this.brandDrawerSearch || '').trim().toLowerCase();
      const list = this.brands || [];
      if (!q) return list;
      return list.filter(b => (b.name || '').toLowerCase().includes(q));
    },

    // Label shown on the warehouse trigger
    selectedWarehouseLabel() {
      if (!this.sale.warehouse_id) return this.$t('Select_Warehouse') || 'Select warehouse';
      const wh = (this.warehouses || []).find(x => x.id === this.sale.warehouse_id);
      return wh ? wh.name : (this.$t('Select_Warehouse') || 'Select warehouse');
    },

    // Warehouses filtered by the drawer's search box
    filteredWarehouses() {
      const q = (this.whDrawerSearch || '').trim().toLowerCase();
      const list = this.warehouses || [];
      if (!q) return list;
      return list.filter(wh => (wh.name || '').toLowerCase().includes(q));
    },

    // Label shown on the customer trigger button
    selectedCustomerLabel() {
      if (!this.selectedClientId) return this.$t('Select_Customer') || 'Select customer';
      const c = (this.clients || []).find(x => x.id === this.selectedClientId);
      return c ? c.name : (this.$t('Select_Customer') || 'Select customer');
    },

    // Customers filtered by the drawer's search box (matches name OR phone)
    filteredCustomers() {
      const q = (this.custDrawerSearch || '').trim().toLowerCase();
      const list = this.customerOptions || [];
      if (!q) return list;
      return list.filter(c => {
        const name = (c.name || c.label || '').toLowerCase();
        const phone = (c.phone || '').toLowerCase();
        return name.includes(q) || phone.includes(q);
      });
    },

    // Total pages for product list
    product_lastPage() {
      const total = Number(this.product_totalRows || 0);
      const per = Number(this.product_perPage || 1);
      const pages = Math.ceil(total / per);
      return pages > 0 ? pages : 1;
    },

    // Windowed list of page items with ellipses, e.g. [1, '…', 4, 5, 6, '…', 20]
    product_visiblePageItems() {
      const current = Number(this.product_currentPage || 1);
      const last = this.product_lastPage;
      const windowSize = 1; // pages to show on each side of current
      const pages = new Set([1, last]);
      for (let p = current - windowSize; p <= current + windowSize; p++) {
        if (p >= 1 && p <= last) pages.add(p);
      }
      const sorted = Array.from(pages).sort((a, b) => a - b);
      const out = [];
      let prev = null;
      for (const p of sorted) {
        if (prev !== null && p - prev > 1) out.push('…');
        out.push(p);
        prev = p;
      }
      return out;
    },


    anyCreditCardUsed() {
      return this.paymentLines.some(p => p.payment_method_id === '1' || p.payment_method_id === 1);
    },

     // Sum of all entered payment lines
    totalPaid() {
      return this.paymentLines.reduce((sum, p) => sum + Number(p.amount || 0), 0).toFixed(2);
    },
    
    // Calculate manual discount amount only (excluding points) for receipt display
    calculatedManualDiscountAmount() {
      try {
        // For invoice_pos (receipt display), use invoice_pos data
        const saleData = this.invoice_pos && this.invoice_pos.sale ? this.invoice_pos.sale : this.sale;
        const discountMethod = String(saleData.discount_Method || '2'); // Default to fixed for backward compatibility
        const discountValue = Number(saleData.discount || 0);
        const subtotal = this.invoiceSubtotal || this.total || 0;
        
        if (discountMethod === '1') {
          // Percentage discount on subtotal (manual discount only, no points)
          return parseFloat((subtotal * (discountValue / 100)).toFixed(2));
        } else {
          // Fixed discount (manual discount only, no points)
          return parseFloat(Math.min(discountValue, subtotal).toFixed(2));
        }
      } catch (e) {
        return 0;
      }
    },
    
    // Calculate actual discount amount for display (handles both percentage and fixed, includes points)
    calculatedDiscountAmount() {
      try {
        // For invoice_pos (receipt display), use invoice_pos data
        const saleData = this.invoice_pos && this.invoice_pos.sale ? this.invoice_pos.sale : this.sale;
        const discountMethod = String(saleData.discount_Method || '2'); // Default to fixed for backward compatibility
        const discountValue = Number(saleData.discount || 0);
        const subtotal = this.invoiceSubtotal || this.total || 0;
        
        // Get discount_from_points from invoice_pos.sale if available, otherwise use current sale's discount_from_points
        const pointsDiscount = Number(
          (saleData.discount_from_points !== undefined && saleData.discount_from_points !== null)
            ? saleData.discount_from_points
            : (this.discount_from_points || 0)
        );
        
        if (discountMethod === '1') {
          // Percentage discount on subtotal
          const percentAmount = parseFloat((subtotal * (discountValue / 100)).toFixed(2));
          // Points-based discount is always a fixed amount; apply it in addition, but never exceed remaining subtotal
          const remainingAfterPercent = Math.max(subtotal - percentAmount, 0);
          const pointsAmount = parseFloat(
            Math.min(pointsDiscount, remainingAfterPercent).toFixed(2)
          );
          return percentAmount + pointsAmount;
        } else {
          // Fixed discount: apply both manual discount and points discount separately
          const manualDiscount = parseFloat(Math.min(discountValue, subtotal).toFixed(2));
          const remainingAfterManual = Math.max(subtotal - manualDiscount, 0);
          const pointsAmount = parseFloat(
            Math.min(pointsDiscount, remainingAfterManual).toFixed(2)
          );
          return manualDiscount + pointsAmount;
        }
      } catch (e) {
        return 0;
      }
    },
    // What's still due (never negative)
    balance() {
      const b = this.GrandTotal - this.totalPaid;
      return (b > 0 ? b : 0).toFixed(2);
    },
    // How much to return if over-paid
    changeReturn() {
      const c = this.totalPaid - this.GrandTotal;
      return (c > 0 ? c : 0).toFixed(2);
    },

    brand_totalRows() {
      return this.brands.length;
    },

    category_totalRows() {
      return this.categories.length;
    },

    offlineStatusTitle() {
      try {
        if (!this.isOnline) {
          return this.$t ? this.$t('pos.Offline_Mode') : 'Offline mode';
        }
        if (this.offlineSalesCount > 0) {
          return this.$t ? this.$t('pos.Sync_Offline_Sales') : 'Sync offline sales';
        }
        return this.$t ? this.$t('Online') : 'Online';
      } catch (e) {
        return 'POS';
      }
    },

    filteredCategories() {
      if (this.search_category.trim() === '') {
        return this.paginated_Category;
      }
      return this.paginated_Category.filter(category =>
        category.name.toLowerCase().includes(this.search_category.toLowerCase())
      );
    },

    filteredBrands() {
      if (this.search_brand.trim() === '') {
        return this.paginated_Brands;
      }
      return this.paginated_Brands.filter(brand =>
        brand.name.toLowerCase().includes(this.search_brand.toLowerCase())
      );
    },

    displaySavedPaymentMethods() {
      if(this.hasSavedPaymentMethod){
        return true;
      }else{
        return false;
      }
    },

    displayFormNewCard() {
      if(this.useSavedPaymentMethod){
        return false;
      }else{
        return true;
      }
    },

    isSelectedCard() {
      return card => this.selectedCard === card;
    },

    columns_draft_sales() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
       
        {
          label: this.$t("Total"),
          field: "GrandTotal",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },

        {
          label: this.$t("Action"),
          field: "actions",
          html: true,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
     
      ];
    }

    

  },

  watch: {
    // When the warehouse changes (including being cleared), clear the current
    // checkout so we never mix products/stock from different warehouses.
    'sale.warehouse_id'(newVal, oldVal) {
      // Only react when there was a previously selected warehouse and it
      // actually changed.
      if (!oldVal || oldVal === newVal) {
        return;
      }

      // Clear cart lines and totals but keep current client and general UI state.
      this.details = [];
      this.product = {};
      this.GrandTotal = 0;
      this.total = 0;

      // Notify any external listeners (dashboard widgets, etc.) that the
      // checkout has been cleared.
      try {
        this._cd_emit && this._cd_emit({
          currency: (this.currentUser && this.currentUser.currency) || '',
          details: [],
          discount: 0,
          TaxNet: 0,
          GrandTotal: 0,
        }, true);
      } catch (e) {}
    },
  },
  watch: {
    'invoice_pos.zatca_qr'(val){
      if(val){
        this.$nextTick(() => { this.renderZatcaQrPos(); this.renderInvoiceUrlQr(); });
      }
    }
  },
  mounted() {
    this.changeSidebarProperties();
    this.paginate_products(this.product_perPage, 0);
    // Marker class so the global :fullscreen rules at the bottom of this
    // file only fire while POS is the active page. Without it, clicking
    // the topnav fullscreen button on a non-POS page applies POS-only
    // overrides (hiding .main-header / .side-content-wrap and zeroing
    // .main-content-wrap padding) and the layout — including the
    // footer — shifts.
    try {
      if (typeof document !== 'undefined' && document.documentElement) {
        document.documentElement.classList.add('pos-active');
      }
    } catch (e) {}
  },
  methods: {
    goToMobileTab(tab) {
      if (tab === 'home') {
        if (this.$route && this.$route.path !== '/') {
          this.$router.push('/').catch(() => {});
        }
        return;
      }
      this.mobileActiveTab = tab;
      this.$nextTick(() => {
        try {
          if (tab === 'cart') {
            const el = document.querySelector('.pos-shell-cart-aside');
            if (el && typeof el.scrollIntoView === 'function') {
              el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }
        } catch (e) { /* ignore scroll errors */ }
      });
    },
    async pingBackend() {
      // De-dupe concurrent pings.
      if (this._posPingInFlight) return this._posPingInFlight;
      this._posPingInFlight = (async () => {
        try {
          const res = await axios.get('/ping', { timeout: 2000 });
          const ok = !!(res && res.status === 200 && res.data && res.data.ok === true);
          this.backendReachable = ok;
          return ok;
        } catch (e) {
          this.backendReachable = false;
          return false;
        } finally {
          this._posPingInFlight = null;
        }
      })();
      return this._posPingInFlight;
    },
    // Custom filter function for customer v-select to search by name and phone
    filterCustomerByPhone(option, label, search) {
      if (!search) return true;
      const searchLower = search.toLowerCase();
      const name = (option.name || '').toLowerCase();
      const phone = (option.phone || '').toLowerCase();
      return name.includes(searchLower) || phone.includes(searchLower);
    },

    async refreshCurrentRegister() {
      try {
        if (!this.currentUser) return;
        const params = {};
        if (this.sale && this.sale.warehouse_id) params.warehouse_id = this.sale.warehouse_id;
        const { data } = await axios.get(`cash-registers/current/${this.currentUser.id}`, { params });
        this.currentRegister = data.register || null;
      } catch (e) {
        this.currentRegister = null;
      }
    },
    // ---------- Customer Display helpers ----------
    _cd_emit(payload, completed = false) {
      const screenId = this.customer_display_screen_id || '1';
      try {
        if (window.Echo && window.Echo.channel) {
          const ch = 'pos-cart.' + screenId;
          window.Echo.channel(ch).whisper('cart-updated', payload);
          if (completed) window.Echo.channel(ch).whisper('sale-completed', true);
          return;
        }
      } catch (e) { /* ignore */ }
      try {
        // If the browser is offline (or POS is in offline mode), skip broadcast
        // to avoid noisy ERR_INTERNET_DISCONNECTED spam.
        const browserOnline = (() => {
          try { return !window.navigator || window.navigator.onLine !== false; } catch (e) { return true; }
        })();
        if (!browserOnline || this.isOnline === false) return;
        window.axios && window.axios.post('pos/customer-display/broadcast', {
          cart: payload,
          completed,
          screen_id: screenId
        });
      } catch (e) { /* ignore */ }
    },
    _cd_queue_broadcast() {
      if (this._cd_broadcast_timer) clearTimeout(this._cd_broadcast_timer);
      this._cd_broadcast_timer = setTimeout(() => {
        const payload = {
          currency: (this.currentUser && this.currentUser.currency) || '',
          discount: this.sale && this.sale.discount ? this.sale.discount : 0,
          TaxNet: this.sale && this.sale.TaxNet ? this.sale.TaxNet : 0,
            shipping: this.sale && this.sale.shipping ? this.sale.shipping : 0,
          GrandTotal: this.GrandTotal || 0,
          details: (this.details || []).map(d => ({
            name: d.name,
            quantity: d.quantity,
              // Back-compat: keep total, but also send unit_price and line_total explicitly
              total: (d.total != null ? d.total : (d.Net_price || 0)),
              unit_price: (d.Net_price != null ? d.Net_price : (d.Unit_price != null ? d.Unit_price : (d.price != null ? d.price : 0))),
              line_total: (d.total != null ? d.total : ((d.Net_price || 0) * (d.quantity || 0))),
          }))
        };
        this._cd_emit(payload);
      }, 200); // small debounce
    },
    async submitOpenRegister() {
      if (!this.registerForm.warehouse_id) {
        this.makeToast('warning', this.$t('Please_select_warehouse'), this.$t('Warning'));
        return;
      }
      this.registerBusy = true;
      try {
        const { data } = await axios.post('cash-registers/open', {
          user_id: this.currentUser.id,
          warehouse_id: this.registerForm.warehouse_id,
          opening_balance: this.registerForm.opening_balance || 0,
          notes: this.registerForm.notes || ''
        });
        this.$bvModal.hide('OpenRegisterModal');
        this.makeToast('success', this.$t('RegisterOpened'), this.$t('Success'));
        // Immediately reflect UI without waiting for fetch
        this.currentRegister = data && data.register ? data.register : this.currentRegister;
        // Fallback refresh to ensure latest from server
        this.refreshCurrentRegister();
      } catch (e) {
        const msg = e.response?.data?.message || this.$t('OperationFailed');
        this.makeToast('danger', msg, this.$t('Failed'));
      } finally {
        this.registerBusy = false;
      }
    },
    async submitCloseRegister() {
      if (!this.currentRegister) return;
      this.registerBusy = true;
      try {
        await axios.post('cash-registers/close', {
          register_id: this.currentRegister.id,
          counted_cash: this.closeForm.counted_cash || 0,
          notes: this.closeForm.notes || ''
        });
        this.$bvModal.hide('CloseRegisterModal');
        this.makeToast('success', this.$t('RegisterClosed'), this.$t('Success'));
        this.refreshCurrentRegister();
      } catch (e) {
        const msg = e.response?.data?.message || this.$t('OperationFailed');
        this.makeToast('danger', msg, this.$t('Failed'));
      } finally {
        this.registerBusy = false;
      }
    },
    resolveProductImage(imagePath) {
      if (!imagePath) return '';
      if (/^https?:\/\//i.test(imagePath)) return imagePath;
      const clean = String(imagePath).replace(/^\/+/, '');
      const encoded = clean.split('/').map(encodeURIComponent).join('/');
      return `/images/products/${encoded}`;
    },
    getResultValue(result) {
      return result.code + " (" + result.name + ")";
    },

    SearchProduct(result) {
      if (this.load_product) {
        this.load_product = false;
        this.product = {};

        if (result.product_type == 'is_service') {
          this.product.quantity = 1;
          this.product.code = result.code;
        } else {
          this.product.code = result.code;
          this.product.current = result.qte_sale;
          this.product.fix_stock = result.qte;
          this.product.quantity = result.qte_sale < 1 ? result.qte_sale : 1;
        }

        this.product.product_variant_id = result.product_variant_id;
        this.Get_Product_Details(result.id, result.product_variant_id, result);

        this.search_input = '';
        this.product_filter = [];
      } else {
        this.makeToast(
          "warning",
          this.$t("Please_wait_until_the_product_is_loaded"),
          this.$t("Warning")
        );
      }
    },
    ...mapActions(["changeSidebarProperties", "changeThemeMode", "logout"]),
    // ... All methods from old_pos will be injected here
    logoutUser() {
      this.$store.dispatch("logout");
    },
    
     handleFocus() {
      this.focused = true
    },
    handleBlur() {
      this.focused = false
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

    addPaymentLine() {
      this.paymentLines.push({
        amount: 0,
        payment_method_id: '',
      })
    },
    removePaymentLine(idx) {
      if (this.paymentLines.length > 1) {
        this.paymentLines.splice(idx, 1)
      }
    },

    setQuickAmount(val) {
      // assign to current active line (e.g. last)
      const line = this.paymentLines[this.paymentLines.length - 1];
      line.amount = val;
    },
    appendDigit(d) {
      // append to the last line's amount
      let line = this.paymentLines[this.paymentLines.length - 1];
      let s = String(line.amount || '');
      if (s === '0') s = d;
      else s += d;
      line.amount = parseFloat(s);
    },
    clearInput() {
      this.paymentLines[this.paymentLines.length - 1].amount = 0;
    },
    backspace() {
      let line = this.paymentLines[this.paymentLines.length - 1];
      let s = String(line.amount || '');
      s = s.slice(0, -1) || '0';
      line.amount = parseFloat(s);
    },

     async Selected_PaymentMethod(value) {
      if (value == '1' || value == 1) {
        this.savedPaymentMethods = [];
        this.submit_showing_credit_card = true;
        this.selectedCard = null
        this.card_id = '';
        // Check if the customer has saved payment methods
        await axios.get(`/retrieve-customer?customerId=${this.selectedClientId}`)
            .then(response => {
                // If the customer has saved payment methods, display them
                this.savedPaymentMethods = response.data.data;
                this.card_id = response.data.customer_default_source;
                this.hasSavedPaymentMethod = true;
                this.useSavedPaymentMethod = true;
                this.is_new_credit_card = false;
                this.submit_showing_credit_card = false;
            })
            .catch(error => {
                // If the customer does not have saved payment methods, show the card element for them to enter their payment information
                this.hasSavedPaymentMethod = false;
                this.useSavedPaymentMethod = false;
                this.is_new_credit_card = true;
                this.card_id = '';

                setTimeout(() => {
                    this.loadStripe_payment();
                }, 1000);
                this.submit_showing_credit_card = false;
            });

         
        }else{
          this.hasSavedPaymentMethod = false;
          this.useSavedPaymentMethod = false;
          this.is_new_credit_card = false;
        }

    },

    show_saved_credit_card() {
      this.hasSavedPaymentMethod = true;
      this.useSavedPaymentMethod = true;
      this.is_new_credit_card = false;
      this.Selected_PaymentMethod(1);
    },

    show_new_credit_card() {
      this.selectedCard = null;
      this.card_id = '';
      this.useSavedPaymentMethod = false;
      this.hasSavedPaymentMethod = false;
      this.is_new_credit_card = true;

      setTimeout(() => {
        this.loadStripe_payment();
      }, 500);
    },

    selectCard(card) {
      this.selectedCard = card;
      this.card_id = card.card_id;
    },

    async loadStripe_payment() {
      this.stripe = await loadStripe(`${this.stripe_key}`);
      const elements = this.stripe.elements();
      this.cardElement = elements.create("card", {
        classes: {
          base:
            "bg-gray-100 rounded border border-gray-300 focus:border-indigo-500 text-base outline-none text-gray-700 p-3 leading-8 transition-colors duration-200 ease-in-out"
        }
      });
      this.cardElement.mount("#card-element");
    },

    SetLocal(locale) {
      this.$i18n.locale = locale;
      this.$store.dispatch("setLanguage", locale);
      Fire.$emit("ChangeLanguage");
      window.location.reload();
    },


    handleFullScreen() {
      Util.toggleFullScreen();
    },
    logoutUser() {
      this.logout();
    },
    // ==================== PAGINATION METHODS (frontend only) ====================
    Product_paginatePerPage() {
      // Always paginate from the full in‑memory list; backend now returns
      // the entire filtered collection and we handle pagination here.
      this.paginate_products(this.product_perPage, 0);
    },
    onProductPageItemClick(item) {
      if (typeof item === 'number' && item >= 1 && item <= this.product_lastPage && item !== this.product_currentPage) {
        this.Product_onPageChanged(item);
      }
    },
    paginate_products(pageSize, pageNumber) {
      const itemsToParse = Array.isArray(this.products) ? this.products : [];
      this.paginated_Products = itemsToParse.slice(
        pageNumber * pageSize,
        (pageNumber + 1) * pageSize
      );
    },
    Product_onPageChanged(page) {
      // Pure frontend pagination: just change the visible slice,
      // do not refetch since we already hold the full filtered list.
      this.product_currentPage = page;
      this.paginate_products(this.product_perPage, page - 1);
    },
    BrandpaginatePerPage() {
      this.paginate_Brands(this.brand_perPage, 0);
    },
    paginate_Brands(pageSize, pageNumber) {
      let itemsToParse = this.brands;
      this.paginated_Brands = itemsToParse.slice(
        pageNumber * pageSize,
        (pageNumber + 1) * pageSize
      );
    },
    BrandonPageChanged(page) {
      this.paginate_Brands(this.brand_perPage, page - 1);
    },
    Category_paginatePerPage() {
      this.paginate_Category(this.category_perPage, 0);
    },
    paginate_Category(pageSize, pageNumber) {
      let itemsToParse = this.categories;
      this.paginated_Category = itemsToParse.slice(
        pageNumber * pageSize,
        (pageNumber + 1) * pageSize
      );
    },
    Category_onPageChanged(page) {
      this.paginate_Category(this.category_perPage, page - 1);
    },

    // ==================== SUBMIT & VALIDATION METHODS ====================
    Submit_Pos() {
      NProgress.start();
      NProgress.set(0.1);
      if (this.verifiedForm()) {
        Fire.$emit("pay_now");
      } else {
        NProgress.done();
      }
    },

    Submit_Draft() {
      NProgress.start();
      NProgress.set(0.1);
      if (this.verifiedForm()) {
        this.Create_Draft();
      } else {
        NProgress.done();
      }
    },

    verifiedForm() {
      if (this.selectedClientId == "" || this.selectedClientId === null) {
        this.makeToast(
          "danger",
          this.$t("Choose_Customer"),
          this.$t("Failed")
        );
        return false;
      } else if (
        this.sale.warehouse_id == "" ||
        this.sale.warehouse_id === null
      ) {
        this.makeToast(
          "danger",
          this.$t("Choose_Warehouse"),
          this.$t("Failed")
        );
        return false;
      } else if (this.details.length === 0) {
        this.makeToast(
          "danger",
          this.$t("PleaseAddProducts"),
          this.$t("Failed")
        );
        return false;
      } else if (this.has_batch_validation_errors()) {
        this.makeToast(
          "danger",
          this.$t("Total_batch_qty_mismatch") || "Batch quantities are invalid",
          this.$t("Failed")
        );
        return false;
      }
      return true;
    },

    Create_Draft(){
      NProgress.start();
      NProgress.set(0.1);
      this.DraftProcessing = true;
      axios
        .post("pos/create_draft", {
          draft_sale_id: this.draft_sale_id || undefined,
          client_id: this.selectedClientId,
          warehouse_id: this.sale.warehouse_id,
          tax_rate: this.sale.tax_rate?this.sale.tax_rate:0,
          TaxNet: this.sale.TaxNet?this.sale.TaxNet:0,
          discount: this.sale.discount?this.sale.discount:0,
          discount_Method: String(this.sale.discount_Method || '2'), // Ensure it's always a string: '1' for percentage, '2' for fixed
          shipping: this.sale.shipping?this.sale.shipping:0,
          notes: this.sale.notes,
          details: this.buildSubmitDetails(),
          GrandTotal: this.GrandTotal,
        })
        .then(response => {
          if (response.data.success === true) {
            this.makeToast(
                "success",
                this.$t("Draft_Created_successfully"),
                this.$t("Success")
              );
            NProgress.done();
            this.DraftProcessing = false;
            this.Reset_Pos();
          }
        })
        .catch(error => {
          NProgress.done();
          this.DraftProcessing = false;
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    // ==================== PAYMENT METHODS ====================
    Submit_Payment() {
      NProgress.start();
      NProgress.set(0.1);

      const total    = parseFloat(this.totalPaid);
      const due      = parseFloat(this.GrandTotal.toFixed(2));
      const multi    = this.paymentLines.length > 1;

      if (multi && total > due) {
        NProgress.done();
        this.makeToast(
          "warning",
          this.$t("TotalPaidExceedsGrandTotalForMultiPayment"),
          this.$t("Warning")
        );
        return;
      }

      this.CreatePOS();
    },

    CreatePOS() {
      NProgress.start();
      NProgress.set(0.1);
      if (this.paymentLines.length > 1 && this.totalPaid > this.GrandTotal) {
        this.makeToast(
          "warning",
          this.$t("TotalPaidExceedsGrandTotalForMultiPayment"),
          this.$t("Warning")
        );
        NProgress.done();
        return;
      }

      // Credit Limit Validation (0 means no limit)
      // Only applies when this sale is adding new credit (paid amount < sale total)
      if (this.selectedClientId && this.selectedClientCreditLimit > 0) {
        const total = parseFloat(this.totalPaid);
        const due = parseFloat(this.GrandTotal.toFixed(2));

        if (total < due) {
          // Calculate the new due amount after this sale
          const currentDue = parseFloat(this.selectedClientNetBalance || 0);
          const newSaleDue = due - total; // Remaining due from this sale
          const newTotalDue = currentDue + newSaleDue;

          if (newTotalDue > this.selectedClientCreditLimit) {
            NProgress.done();
            const exceededAmount = newTotalDue - this.selectedClientCreditLimit;
            this.makeToast(
              "danger",
              this.$t("Credit_Limit_Exceeded") + ": " + 
              this.formatPriceWithCurrentCurrency(exceededAmount, 2) + " " + 
              this.$t("exceeds_credit_limit_of") + " " + 
              this.formatPriceWithCurrentCurrency(this.selectedClientCreditLimit, 2),
              this.$t("Warning")
            );
            return;
          }
        }
      }

      const anyNewCard = this.paymentLines.some(
        p => (p.payment_method_id === '1' || p.payment_method_id === 1) && this.is_new_credit_card
      );

      if (anyNewCard) {
        if (this.stripe_key !== '') {
          this.processPayment();
        } else {
          this.makeToast(
            'danger',
            this.$t('credit_card_account_not_available'),
            this.$t('Failed')
          );
          NProgress.done();
        }
      } else {
        this.paymentProcessing = true;
        axios
          .post("pos/create_pos", {
            client_id: this.selectedClientId,
            warehouse_id: this.sale.warehouse_id,
            tax_rate: this.sale.tax_rate?this.sale.tax_rate:0,
            TaxNet: this.sale.TaxNet?this.sale.TaxNet:0,
            discount: this.sale.discount?this.sale.discount:0,
            discount_Method: String(this.sale.discount_Method || '2'), // Ensure it's always a string: '1' for percentage, '2' for fixed
            shipping: this.sale.shipping?this.sale.shipping:0,
            notes: this.sale.notes,
            details: this.buildSubmitDetails(),
            GrandTotal: this.GrandTotal,
            payments: this.paymentLines,
            send_email: this.sendEmail,
            send_sms: this.sendSMS,
            account_id: this.selectedAccount,
            payment_note: this.globalPaymentNote || '',
            is_new_credit_card: this.is_new_credit_card,
            selectedCard: this.selectedCard,
            card_id: this.card_id,
            discount_from_points: this.discount_from_points,
            used_points: this.used_points,
            draft_sale_id: this.draft_sale_id || undefined,
          })
          .then(response => {
            if (response.data.success === true) {
              NProgress.done();
              this.paymentProcessing = false;
              const saleId = response.data.id;
              const draftId = this.draft_sale_id;
              // Patch in-memory stock with the server-authoritative values
              // for the items we just sold so the grid updates without a
              // full product reload.
              try {
                this.applyStockUpdate(response.data.updated_stock, response.data.server_time);
              } catch (e) {}
              const afterCleanup = () => {
                this.last_sale_id = saleId;
                this.Invoice_POS(saleId);
                this.$bvModal.hide("Add_Payment");
                this.Reset_Pos();
              };
              if (draftId) {
                axios.delete("remove_draft_sale/" + draftId)
                  .then(() => { try { Fire.$emit("event_delete_draft_sale"); } catch(e) {} })
                  .catch(() => {})
                  .finally(() => { this.draft_sale_id = ''; afterCleanup(); });
              } else {
                afterCleanup();
              }
            }
          })
          .catch(error => {
            NProgress.done();
            this.paymentProcessing = false;
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          });
      }
    },

    async processPayment() {
      this.paymentProcessing = true;

      const { paymentMethod, error } = await this.stripe.createPaymentMethod({
        type: "card",
        card: this.cardElement,
        billing_details: {
          name: this.client_name || "",
        },
      });

      if (error) {
        this.paymentProcessing = false;
        NProgress.done();
        this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
      } else {
        // Attach the Stripe Payment Method ID (pm_...) to all credit‑card lines
        const paymentsWithMethod = this.paymentLines.map(p => {
          if (p.payment_method_id === "1" || p.payment_method_id === 1) {
            return {
              ...p,
              payment_method_id_stripe: paymentMethod.id,
            };
          }
          return p;
        });

        axios
          .post("pos/create_pos", {
            client_id: this.selectedClientId,
            warehouse_id: this.sale.warehouse_id,
            tax_rate: this.sale.tax_rate ? this.sale.tax_rate : 0,
            TaxNet: this.sale.TaxNet ? this.sale.TaxNet : 0,
            discount: this.sale.discount ? this.sale.discount : 0,
            shipping: this.sale.shipping ? this.sale.shipping : 0,
            details: this.buildSubmitDetails(),
            GrandTotal: this.GrandTotal,
            notes: this.sale.notes,
            payments: paymentsWithMethod,
            send_email: this.sendEmail,
            send_sms: this.sendSMS,
            account_id: this.selectedAccount,
            payment_note: this.globalPaymentNote || "",
            is_new_credit_card: this.is_new_credit_card,
            selectedCard: this.selectedCard,
            card_id: this.card_id,
            discount_from_points: this.discount_from_points,
            used_points: this.used_points,
            draft_sale_id: this.draft_sale_id || undefined,
          })
          .then(response => {
            this.paymentProcessing = false;
            if (response.data.success === true) {
              NProgress.done();
              const saleId = response.data.id;
              const draftId = this.draft_sale_id;
              try {
                this.applyStockUpdate(response.data.updated_stock, response.data.server_time);
              } catch (e) {}
              const afterCleanup = () => {
                this.last_sale_id = saleId;
                this.Invoice_POS(saleId);
                this.$bvModal.hide("Add_Payment");
                this.Reset_Pos();
              };
              if (draftId) {
                axios
                  .delete("remove_draft_sale/" + draftId)
                  .then(() => {
                    try {
                      Fire.$emit("event_delete_draft_sale");
                    } catch (e) {}
                  })
                  .catch(() => {})
                  .finally(() => {
                    this.draft_sale_id = "";
                    afterCleanup();
                  });
              } else {
                afterCleanup();
              }
            }
          })
          .catch(error => {
            this.paymentProcessing = false;
            NProgress.done();
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          });
      }
    },

    // ==================== UTILITY & CALCULATION METHODS ====================
    formatNumber(number, dec) {
      const decimals = Number.isInteger(dec) ? dec : 0;
      const n = Number(number);
      const safe = Number.isFinite(n) ? n : 0;
      try {
        return safe.toLocaleString('en-US', {
          minimumFractionDigits: decimals,
          maximumFractionDigits: decimals,
        });
      } catch (e) {
        // Fallback for environments without Intl
        const fixed = safe.toFixed(decimals);
        const [intPart, fracPart] = fixed.split('.');
        const withCommas = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return fracPart ? `${withCommas}.${fracPart}` : withCommas;
      }
    },

    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing formatNumber helper to preserve current behavior.
    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 0;
        // Prefer cached key, otherwise read from helpers/localStorage
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplay(number, decimals, effectiveKey);
      } catch (e) {
        // Fallback: keep legacy behavior
        return this.formatNumber(number, dec);
      }
    },

    formatPriceWithCurrentCurrency(number, dec) {
      const symbol = (this.currentUser && this.currentUser.currency) ? this.currentUser.currency : '';
      const value = this.formatPriceDisplay(number, dec);
      return symbol ? `${symbol} ${value}` : value;
    },

    formatPriceWithSymbol(symbol, number, dec) {
      const safeSymbol = symbol || '';
      const value = this.formatPriceDisplay(number, dec);
      return safeSymbol ? `${safeSymbol} ${value}` : value;
    },

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    CalculTotal() {
      this.total = 0;
      for (var i = 0; i < this.details.length; i++) {
        var tax = this.details[i].taxe * this.details[i].quantity;
        this.details[i].subtotal = parseFloat(
          this.details[i].quantity * this.details[i].Net_price + tax
        );
        this.total = parseFloat(this.total + this.details[i].subtotal);
      }
      // Calculate discount based on type (backward compatible: default to fixed if not set)
      const discountMethod = String(this.sale.discount_Method || '2');
      const discountValue = Number(this.sale.discount || 0);
      let discountAmount = 0;
      
      if (discountMethod === '1') {
        // Percentage discount on subtotal
        const percentAmount = parseFloat((this.total * (discountValue / 100)).toFixed(2));
        // Points-based discount is always a fixed amount; apply it in addition, but never exceed remaining subtotal
        const remainingAfterPercent = Math.max(this.total - percentAmount, 0);
        const pointsAmount = parseFloat(
          Math.min(Number(this.discount_from_points || 0), remainingAfterPercent).toFixed(2)
        );
        discountAmount = percentAmount + pointsAmount;
      } else {
        // Fixed discount: apply both manual discount and points discount separately
        const manualDiscount = parseFloat(Math.min(discountValue, this.total).toFixed(2));
        const remainingAfterManual = Math.max(this.total - manualDiscount, 0);
        const pointsDiscount = parseFloat(
          Math.min(Number(this.discount_from_points || 0), remainingAfterManual).toFixed(2)
        );
        discountAmount = manualDiscount + pointsDiscount;
      }
      
      const total_without_discount = parseFloat(
        (this.total - discountAmount).toFixed(2)
      );
      this.sale.TaxNet = parseFloat(
        (total_without_discount * this.sale.tax_rate) / 100
      );
      this.GrandTotal = parseFloat(
        total_without_discount + this.sale.TaxNet + this.sale.shipping
      );
    var grand_total =  this.GrandTotal.toFixed(2);
    this.GrandTotal = parseFloat(grand_total);
    try { this._cd_queue_broadcast && this._cd_queue_broadcast(); } catch(e) {}
    },

    keyup_OrderTax() {
      if (isNaN(this.sale.tax_rate)) {
        this.sale.tax_rate = 0;
      } else if(this.sale.tax_rate == ''){
         this.sale.tax_rate = 0;
        this.CalculTotal();
      }else {
        this.CalculTotal();
      }
    },

    keyup_Discount() {
      if (isNaN(this.sale.discount)) {
        this.sale.discount = 0;
      } else if(this.sale.discount == ''){
         this.sale.discount = 0;
        this.CalculTotal();
      }else {
        this.CalculTotal();
      }
    },
    
    toggleDiscountType() {
      // Toggle between '1' (percentage) and '2' (fixed)
      this.sale.discount_Method = this.sale.discount_Method === '1' ? '2' : '1';
      this.CalculTotal();
    },
    
    // Calculate discount amount for current sale (for display purposes)
    getCurrentSaleDiscountAmount() {
      try {
        const discountMethod = String(this.sale.discount_Method || '2'); // Default to fixed for backward compatibility
        const discountValue = Number(this.sale.discount || 0);
        const subtotal = this.total || 0;
        
        if (discountMethod === '1') {
          // Percentage discount on subtotal
          const percentAmount = parseFloat((subtotal * (discountValue / 100)).toFixed(2));
          // Points-based discount is always a fixed amount; add it for display
          const remainingAfterPercent = Math.max(subtotal - percentAmount, 0);
          const pointsAmount = parseFloat(
            Math.min(Number(this.discount_from_points || 0), remainingAfterPercent).toFixed(2)
          );
          return percentAmount + pointsAmount;
        } else {
          // Fixed discount: apply both manual discount and points discount separately
          const manualDiscount = parseFloat(Math.min(discountValue, subtotal).toFixed(2));
          const remainingAfterManual = Math.max(subtotal - manualDiscount, 0);
          const pointsDiscount = parseFloat(
            Math.min(Number(this.discount_from_points || 0), remainingAfterManual).toFixed(2)
          );
          return manualDiscount + pointsDiscount;
        }
      } catch (e) {
        return Number(this.sale.discount || 0);
      }
    },

    keyup_Shipping() {
      if (isNaN(this.sale.shipping)) {
        this.sale.shipping = 0;
      } else if(this.sale.shipping == ''){
         this.sale.shipping = 0;
        this.CalculTotal();
      }else {
        this.CalculTotal();
      }
    },

    //---------------------------------Get Product Details ------------------------\\
    // Optional third argument (sourceProduct) is used primarily to avoid
    // per-click `/show_product_data` calls in ONLINE mode by reusing the
    // already-fetched backend rows from `pos/get_products_pos`. In offline
    // mode the existing cache behaviour is preserved.
    async Get_Product_Details(product_id, variant_id, sourceProduct = null) {
      const warehouseId = this.sale && this.sale.warehouse_id ? this.sale.warehouse_id : null;
      const applyDetail = (data) => {
        if (!data) return;
        this.product.discount           = data.discount;
        this.product.DiscountNet        = data.DiscountNet;
        this.product.discount_Method    = data.discount_method;
        this.product.product_id         = data.id;
        this.product.product_type       = data.product_type;
        this.product.name               = data.name;
        this.product.Net_price          = data.Net_price;
        this.product.Total_price        = data.Total_price;
        this.product.Unit_price         = data.Unit_price;
        this.product.Unit_price_wholesale = data.Unit_price_wholesale;
        this.product.wholesale_Net_price   = data.wholesale_Net_price;
        this.product.min_price             = data.min_price || 0;
        this.product.retail_unit_price     = data.Unit_price;
        this.product.wholesale_unit_price  = data.Unit_price_wholesale;
        this.product.price_type            = 'retail';
        this.product.taxe               = data.tax_price;
        this.product.tax_method         = data.tax_method;
        this.product.tax_percent        = data.tax_percent;
        this.product.unitSale           = data.unitSale;
        this.product.product_variant_id = variant_id;
        this.product.code               = data.code;
        this.product.fix_price          = data.fix_price;
        this.product.sale_unit_id       = data.sale_unit_id;
        this.product.is_imei            = data.is_imei;
        this.product.imei_number        = '';
        this.product.is_batch_tracked   = !!data.is_batch_tracked;
        this.product.image              = data.image;

        // Set current stock quantity from warehouse data (already adjusted for shadow stock if applied below)
        this.product.current = data.qte_sale || 0;
        this.product.fix_stock = data.qte || 0;
        
        // Ensure a valid default quantity when adding directly from the grid
        if (this.product.product_type === 'is_service') {
          this.product.quantity = 1;
        } else if (this.product.quantity === undefined || this.product.quantity === null || this.product.quantity <= 0) {
          this.product.quantity = this.product.current < 1 ? this.product.current : 1;
        }

        this.add_product(data.code);
        this.CalculTotal();
      };

      const reallyOffline = (!this.isOnline) || (typeof window !== 'undefined' && window.navigator && window.navigator.onLine === false);
      // Helper: build a detail object from a sourceProduct row that came
      // directly from the backend (get_products_pos). This is used in BOTH
      // online and offline modes, but in online mode we *only* use this and
      // do not read from any offline cache.
      const buildDetailFromSource = () => {
        const p = sourceProduct;
        if (!p || typeof p !== 'object') return null;

        const detail = {
          id: p.id,
          product_type: p.product_type,
          name: p.name,
          // Pricing fields – prefer Net_price, then Unit_price/price
          Net_price: p.Net_price != null ? p.Net_price : (p.Unit_price != null ? p.Unit_price : (p.price != null ? p.price : 0)),
          Unit_price: p.Unit_price != null ? p.Unit_price : (p.Net_price != null ? p.Net_price : (p.price != null ? p.price : 0)),
          Unit_price_wholesale: p.Unit_price_wholesale != null ? p.Unit_price_wholesale : (p.Unit_price != null ? p.Unit_price : (p.Net_price != null ? p.Net_price : 0)),
          wholesale_Net_price: p.wholesale_Net_price != null ? p.wholesale_Net_price : (p.Net_price != null ? p.Net_price : 0),
          min_price: p.min_price != null ? p.min_price : 0,
          // Discount & tax
          discount: p.discount != null ? p.discount : 0,
          DiscountNet: p.DiscountNet != null ? p.DiscountNet : 0,
          discount_method: p.discount_Method != null ? p.discount_Method : '2',
          tax_price: p.tax_price != null ? p.tax_price : 0,
          tax_method: p.tax_method != null ? p.tax_method : 1,
          tax_percent: p.tax_percent != null ? p.tax_percent : 0,
          // Units & codes
          unitSale: p.unitSale,
          code: p.code,
          sale_unit_id: p.sale_unit_id,
          // Stock
          qte_sale: p.qte_sale != null ? p.qte_sale : 0,
          qte: p.qte != null ? p.qte : 0,
          // Other flags
          fix_price: p.fix_price,
          is_imei: p.is_imei,
          is_batch_tracked: !!p.is_batch_tracked,
          // Image: needed by the cart-row thumb so it can render the same
          // visual as the product card (with initials fallback when missing).
          image: p.image,
        };

        // Ensure price fields (Net_price, taxe, Total_price) are consistent,
        // using the same logic as draft loading.
        try {
          const unitPrice = Number(detail.Unit_price || 0);
          const discountVal = Number(detail.discount || 0);
          const discountMethod = String(detail.discount_method || '2'); // 1: %, 2: fixed
          const taxPercent = Number(detail.tax_percent || 0);
          const taxMethod = String(detail.tax_method || '1'); // 1: Exclusive, 2: Inclusive

          if (!detail.DiscountNet && discountVal) {
            detail.DiscountNet =
              discountMethod === '2'
                ? discountVal
                : (unitPrice * (discountVal / 100));
          } else if (detail.DiscountNet == null) {
            detail.DiscountNet = 0;
          }

          if (!detail.Net_price || !detail.Total_price) {
            if (taxMethod === '1') {
              // Tax exclusive
              const net = unitPrice - detail.DiscountNet;
              const taxe = (unitPrice - detail.DiscountNet) * (taxPercent / 100);
              detail.Net_price = parseFloat(net.toFixed(2));
              detail.taxe = parseFloat(taxe.toFixed(2));
              detail.Total_price = parseFloat((net + taxe).toFixed(2));
            } else {
              // Tax inclusive
              const taxe = (unitPrice - detail.DiscountNet) * (taxPercent / 100);
              const net = unitPrice - taxe - detail.DiscountNet;
              detail.taxe = parseFloat(taxe.toFixed(2));
              detail.Net_price = parseFloat(net.toFixed(2));
              detail.Total_price = parseFloat((net + taxe).toFixed(2));
            }
          }
        } catch (e2) {}

        return detail;
      };

      // Helper function to load from OFFLINE cache first, then fall back to
      // sourceProduct. This is used only when reallyOffline is true so that
      // existing offline behaviour remains unchanged.
      const loadFromOfflineCacheOrSource = async () => {
        try {
          if (Util && Util.offlinePos && Util.offlinePos.getProductDetail) {
            const cached = Util.offlinePos.getProductDetail(warehouseId, product_id, variant_id);
            if (cached) {
              const detail = { ...cached };
              if (reallyOffline) {
                try {
                  if (Util && Util.shadowStock && Util.shadowStock.getAvailableQuantity && warehouseId && product_id) {
                    const baseQte = detail.qte_sale || 0;
                    detail.qte_sale = await Util.shadowStock.getAvailableQuantity(warehouseId, product_id, variant_id, baseQte);
                  }
                } catch (e2) {}
              }
              applyDetail(detail);
              NProgress.done();
              return true;
            }
          }
        } catch (e) {}

        const detailFromSource = buildDetailFromSource();
        if (detailFromSource) {
          applyDetail(detailFromSource);
          NProgress.done();
          return true;
        }

        return false;
      };

      // OFFLINE MODE: keep existing behaviour – use offline cache and
      // sourceProduct, never call the backend.
      if (reallyOffline) {
        const loaded = await loadFromOfflineCacheOrSource();
        if (!loaded) {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          NProgress.done();
        }
        return Promise.resolve();
      }

      // ONLINE MODE: product data should come directly from the backend and
      // not from any offline cache. We therefore ignore `offlinePos` here and
      // use only:
      //   1) The preloaded backend rows (sourceProduct from get_products_pos)
      //   2) As a fallback, a direct `/show_product_data` request for this
      //      specific product.
      const fromSourceOnline = buildDetailFromSource();
      if (fromSourceOnline) {
        applyDetail(fromSourceOnline);
        NProgress.done();
        return Promise.resolve();
      }

      // Fallback: direct backend call for this product when, for some reason,
      // we did not receive a usable sourceProduct row.
      return axios
        .get("/show_product_data/" + product_id + "/" + variant_id + "/" + warehouseId)
        .then(async response => {
          const detail = response.data;
          applyDetail(detail);
          // Cache for offline usage (writing cache is fine; online paths do
          // not read from it).
          try {
            if (Util && Util.offlinePos && Util.offlinePos.cacheProductDetail) {
              Util.offlinePos.cacheProductDetail(warehouseId, product_id, variant_id, detail);
            }
          } catch (e) {}
          NProgress.done();
        })
        .catch(() => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          NProgress.done();
        });
    },

    add_product(code) {
      // Play sound only if barcode scanning sound is enabled
      if (this.pos_settings.barcode_scanning_sound) {
        this.audio.play();
      }
      // 1) If product already exists in the list (ignore price_type), merge and just increase quantity
      const hasProductIds = this.product.product_id !== undefined && this.product.product_id !== null;
      const targetVariantId = (this.product.product_variant_id === undefined || this.product.product_variant_id === null)
        ? null
        : this.product.product_variant_id;
      const existingIndex = this.details.findIndex(d => {
        const dVariant = (d.product_variant_id === undefined || d.product_variant_id === null) ? null : d.product_variant_id;
        const rowHasId = d.product_id !== undefined && d.product_id !== null;
        // Prefer strict match by ids when both sides have ids
        if (hasProductIds && rowHasId) {
          return (d.product_id === this.product.product_id) && (dVariant === targetVariantId) && (d.sale_unit_id === this.product.sale_unit_id);
        }
        // Fallback to matching by code + unit when ids are not available
        return (d.code === this.product.code) && (d.sale_unit_id === this.product.sale_unit_id);
      });

      if (existingIndex !== -1) {
        const row = this.details[existingIndex];
        const addQty = (typeof this.product.quantity === 'number' && this.product.quantity > 0) ? this.product.quantity : 1;
        if (row.product_type !== 'is_service') {
          const desiredQty = row.quantity + addQty;
          // Stock guard skipped when overselling is allowed: cart can grow past available stock.
          if (!this.isOversellingAllowed && desiredQty > row.current) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
            row.quantity = row.current;
          } else {
            row.quantity = desiredQty;
          }
        } else {
          row.quantity = row.quantity + addQty;
        }
        this.CalculTotal();
        this.$forceUpdate();
        setTimeout(() => { this.load_product = true; }, 300);
        if (row.is_imei) {
          this.Modal_Updat_Detail(row);
        }
        return;
      }

      // 2) No existing row → create a new one
      if (this.details.length > 0) {
        this.order_detail_id();
      } else if (this.details.length === 0) {
        this.product.detail_id = 1;
      }
      // initialize price type fields before pushing
      if (!this.product.price_type) {
        this.product.price_type = 'retail';
      }
      if (!this.product.retail_unit_price) {
        this.product.retail_unit_price = this.product.Unit_price;
      }
      if (!this.product.wholesale_unit_price) {
        this.product.wholesale_unit_price = this.product.Unit_price_wholesale;
      }

      // push a cloned object to avoid accidental reference sharing
      const newItem = JSON.parse(JSON.stringify(this.product));
      if (!newItem.price_type) newItem.price_type = 'retail';
      // ensure reactivity for newly-added prop on some browsers
      this.$set(newItem, 'price_type', newItem.price_type || 'retail');
      // Apply min_price on add: ensure Net_price >= min_price by adjusting Unit_price if required
      try {
        const min = Number(newItem.min_price || 0);
        const taxMethod = String(newItem.tax_method || '1');
        const discountMethod = String(newItem.discount_Method || '2');
        const discountVal = Number(newItem.discount || 0);
        const taxRate = Number(newItem.tax_percent || 0) / 100;
        const currentNet = Number(newItem.Net_price || 0);
        if (min > 0 && currentNet < min) {
          let unitPriceCandidate = Number(newItem.Unit_price || 0);
          if (taxMethod === '1') {
            if (discountMethod === '1') {
              const denom = 1 - (discountVal / 100);
              unitPriceCandidate = denom > 0 ? (min / denom) : min;
            } else {
              unitPriceCandidate = min + discountVal;
            }
            const discountNet = (discountMethod === '1') ? (unitPriceCandidate * (discountVal / 100)) : discountVal;
            const net = unitPriceCandidate - discountNet;
            const tax = (unitPriceCandidate - discountNet) * taxRate;
            newItem.Unit_price = parseFloat(unitPriceCandidate.toFixed(2));
            newItem.DiscountNet = parseFloat(discountNet.toFixed(2));
            newItem.Net_price = parseFloat(net.toFixed(2));
            newItem.taxe = parseFloat(tax.toFixed(2));
            newItem.Total_price = parseFloat((net + tax).toFixed(2));
          } else {
            if (discountMethod === '1') {
              const denom = (1 - (discountVal / 100)) * (1 - taxRate);
              unitPriceCandidate = denom > 0 ? (min / denom) : min;
            } else {
              const denom = (1 - taxRate);
              unitPriceCandidate = (denom > 0 ? (min / denom) : min) + discountVal;
            }
            const discountNet = (discountMethod === '1') ? (unitPriceCandidate * (discountVal / 100)) : discountVal;
            const taxBase = unitPriceCandidate - discountNet;
            const tax = taxBase * taxRate;
            const net = taxBase - tax;
            newItem.Unit_price = parseFloat(unitPriceCandidate.toFixed(2));
            newItem.DiscountNet = parseFloat(discountNet.toFixed(2));
            newItem.taxe = parseFloat(tax.toFixed(2));
            newItem.Net_price = parseFloat(net.toFixed(2));
            newItem.Total_price = parseFloat((net + tax).toFixed(2));
          }
          if (newItem.price_type === 'wholesale') {
            newItem.wholesale_unit_price = newItem.Unit_price;
          } else {
            newItem.retail_unit_price = newItem.Unit_price;
          }
        }
      } catch(e) {}
      this.details.unshift(newItem);
      setTimeout(() => {
        this.load_product = true;
      }, 300);
      if(newItem.is_imei){
        this.Modal_Updat_Detail(newItem);
      }
      // Pharmacy: fetch available batches for the just-added line if it's batch-tracked.
      if (newItem.is_batch_tracked) {
        const last = this.details[0];
        if (last) {
          this.fetch_batches_for_detail(last);
        }
      }
    },

    order_detail_id() {
      let id = 0;
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id > id) {
          id = this.details[i].detail_id;
        }
      }
      this.product.detail_id = id + 1;
    },

    increment_qty_scanner(code) {
      // Play sound only if barcode scanning sound is enabled
      if (this.pos_settings.barcode_scanning_sound) {
        this.audio.play();
      }
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].code === code) {
          if (!this.isOversellingAllowed && this.details[i].product_type !== 'is_service' && this.details[i].quantity + 1 > this.details[i].current) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
          } else {
            this.details[i].quantity++;
          }
        }
      }
      this.CalculTotal();
      this.$forceUpdate();

      NProgress.done();
      setTimeout(() => {
        this.load_product = true;
      }, 300);
    },

    increment(id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          if (!this.isOversellingAllowed && this.details[i].product_type !== 'is_service' && this.details[i].quantity + 1 > this.details[i].current) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
          } else {
            this.details[i].quantity++;
          }
        }
      }
      this.CalculTotal();
      this.$forceUpdate();
    },

    handleProductClick(product) {
      // Out-of-stock guard skipped when overselling is allowed.
      if (!product) return;
      if (!this.isOversellingAllowed && product.product_type !== 'is_service' && product.qte_sale <= 0) return;
      // Use composite key for variants to avoid overlay conflicting across variants
      const key = product.product_variant_id ? (product.id + '-' + product.product_variant_id) : product.id;
      this.uiLoadingProductId = key;
      this.Get_Product_Details(product.id, product.product_variant_id, product)
        .catch(() => {})
        .finally(() => {
          // Clear only if still the same key (guard against fast double clicks)
          if (this.uiLoadingProductId === key) this.uiLoadingProductId = null;
        });
    },

    decrement(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          // Prevent quantity from going negative, but allow zero and fractional quantities
          if (detail.quantity - 1 < 0) {
            this.makeToast("warning", this.$t("MinimumQuantity"), this.$t("Warning"));
          } else {
            this.details[i].quantity--;
          }
        }
      }
      this.CalculTotal();
      this.$forceUpdate();
    },

    Verified_Qty(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          const qty = parseFloat(detail.quantity);

          // If empty or not a number, fall back to 1 without warning
          if (isNaN(qty) || detail.quantity === null || detail.quantity === '') {
            this.details[i].quantity = 1;
          // Enforce only that quantity must not be negative (zero is allowed)
          } else if (qty < 0) {
            this.makeToast("warning", this.$t("MinimumQuantity"), this.$t("Warning"));
            this.details[i].quantity = 1;
          // Stock cap skipped when overselling is allowed.
          } else if (!this.isOversellingAllowed && this.details[i].product_type !== 'is_service' && qty > detail.current) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
            this.details[i].quantity = detail.current;
          } else {
            this.details[i].quantity = qty;
          }
        }
      }
      this.$forceUpdate();
      this.CalculTotal();
    },

    delete_Product_Detail(id) {
      for (var i = 0; i < this.details.length; i++) {
        if (id === this.details[i].detail_id) {
          this.details.splice(i, 1);
          this.CalculTotal();
          try { this._cd_queue_broadcast && this._cd_queue_broadcast(); } catch(e) {}
        }
      }
    },

    // ---------- Edit Detail Logic (ported from old_pos) ----------
    //------ Show Modal Update Detail Product
    Modal_Updat_Detail(detail) {
      this.detailLoading = true;
      this.detail = {};
      this.detail.name = detail.name;
      this.$bvModal.show("form_Update_Detail");
      this.get_units(detail.product_id)
        .catch(() => {})
        .finally(() => {
          this.detail.detail_id = detail.detail_id;
          this.detail.sale_unit_id = detail.sale_unit_id;
          this.detail.product_type = detail.product_type;
          this.detail.Unit_price = detail.Unit_price;
          this.detail.price_type = detail.price_type || 'retail';
          this.detail.retail_unit_price = detail.retail_unit_price !== undefined ? detail.retail_unit_price : detail.Unit_price;
          this.detail.wholesale_unit_price = detail.wholesale_unit_price !== undefined ? detail.wholesale_unit_price : detail.Unit_price_wholesale;
          this.detail.min_price = detail.min_price !== undefined ? detail.min_price : 0;
          this.detail.fix_price = detail.fix_price;
          this.detail.fix_stock = detail.fix_stock;
          this.detail.current = detail.current;
          this.detail.tax_method = detail.tax_method;
          this.detail.discount_Method = detail.discount_Method;
          this.detail.discount = detail.discount;
          this.detail.quantity = detail.quantity;
          this.detail.tax_percent = detail.tax_percent;
          this.detail.is_imei = detail.is_imei;
          this.detail.imei_number = detail.imei_number;
          this.detailLoading = false;
        });
        console.log(detail);

    },


    //------ Submit Update Detail Product
    submit_Update_Detail() {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === this.detail.detail_id) {
          // 1) Compute proposed pricing WITHOUT mutating the row yet
          let proposedUnitPrice = this.detail.Unit_price;
          const rawMinCandidate = (this.details[i].min_price !== undefined && this.details[i].min_price !== null)
            ? this.details[i].min_price
            : (this.detail.min_price || 0);
          const minPriceRow = parseFloat(String(rawMinCandidate).toString().replace(/,/g, '')) || 0;
          const unitPriceNum = parseFloat(String(proposedUnitPrice).toString().replace(/,/g, '')) || 0;
          // 1.a) Block if unit price is not strictly greater than min price
          if (minPriceRow > 0 && unitPriceNum <= minPriceRow) {
            this.makeToast('warning', this.$t('Price_below_min_not_allowed'), this.$t('Warning'));
            return;
          }

          let proposedDiscountNet = (this.detail.discount_Method == "2")
            ? this.detail.discount
            : parseFloat((proposedUnitPrice * this.detail.discount) / 100);
          let proposedNet, proposedTax;
          if (this.detail.tax_method == "1") {
            proposedNet = parseFloat(proposedUnitPrice - proposedDiscountNet);
            proposedTax = parseFloat((this.detail.tax_percent * (proposedUnitPrice - proposedDiscountNet)) / 100);
          } else {
            proposedTax = parseFloat((proposedUnitPrice - proposedDiscountNet) * (this.detail.tax_percent / 100));
            proposedNet = parseFloat(proposedUnitPrice - proposedTax - proposedDiscountNet);
          }

          // 2) Enforce min price by net as a fallback: if invalid, show toast and ABORT update (keep modal open)
          if (minPriceRow > 0 && proposedNet < minPriceRow) {
            this.makeToast('warning', this.$t('Price_below_min_not_allowed'), this.$t('Warning'));
            return;
          }

          // 3) Apply unit conversion now that price is valid (skip stock logic for services)
          if (this.details[i].product_type !== 'is_service') {
            for (var k = 0; k < this.units.length; k++) {
              if (this.units[k].id == this.detail.sale_unit_id) {
                if (this.units[k].operator == "/") {
                  this.details[i].current = this.detail.fix_stock * this.units[k].operator_value;
                  this.details[i].unitSale = this.units[k].ShortName;
                } else {
                  this.details[i].current = this.detail.fix_stock / this.units[k].operator_value;
                  this.details[i].unitSale = this.units[k].ShortName;
                }
              }
            }
          }

          // 4) Persist values to the row
          this.details[i].Unit_price = proposedUnitPrice;
          // update baseline for the NEWLY selected price type
          if (this.detail.price_type === 'wholesale') {
            this.details[i].wholesale_unit_price = proposedUnitPrice;
          } else {
            this.details[i].retail_unit_price = proposedUnitPrice;
          }
          this.details[i].price_type = this.detail.price_type;
          this.details[i].tax_percent = this.detail.tax_percent;
          this.details[i].tax_method = this.detail.tax_method;
          this.details[i].discount_Method = this.detail.discount_Method;
          this.details[i].discount = this.detail.discount;
          this.details[i].sale_unit_id = this.detail.sale_unit_id;
          this.details[i].imei_number = this.detail.imei_number;
          this.details[i].product_type = this.detail.product_type;

          // 5) Apply computed values
          this.details[i].DiscountNet = proposedDiscountNet;
          this.details[i].taxe = proposedTax;
          this.details[i].Net_price = proposedNet;
          this.details[i].Total_price = parseFloat(proposedNet + proposedTax);
          this.$forceUpdate();
        }
      }
      this.CalculTotal();
      setTimeout(() => {
        this.$bvModal.hide("form_Update_Detail");
      }, 1000);
    },

    // Toggle between retail and wholesale price baselines and recompute amounts
    onChangePriceType(detail){
      const isWholesale = detail.price_type === 'wholesale';
      const wholesaleBase = detail.wholesale_unit_price;
      const retailBase = detail.retail_unit_price;

      // 1) Apply selected baseline
      if (isWholesale) {
        detail.Unit_price = (wholesaleBase !== undefined && wholesaleBase !== null && wholesaleBase !== '') ? wholesaleBase : detail.Unit_price;
      } else {
        detail.Unit_price = (retailBase !== undefined && retailBase !== null && retailBase !== '') ? retailBase : detail.Unit_price;
      }

      // 2) Recompute derived values
      if (detail.discount_Method == "2") {
        detail.DiscountNet = detail.discount;
      } else {
        detail.DiscountNet = parseFloat((detail.Unit_price * detail.discount) / 100);
      }
      if (detail.tax_method == "1") {
        detail.Net_price = parseFloat(detail.Unit_price - detail.DiscountNet);
        detail.taxe = parseFloat((detail.tax_percent * (detail.Unit_price - detail.DiscountNet)) / 100);
        detail.Total_price = parseFloat(detail.Net_price + detail.taxe);
      } else {
        detail.taxe = parseFloat((detail.Unit_price - detail.DiscountNet) * (detail.tax_percent / 100));
        detail.Net_price = parseFloat(detail.Unit_price - detail.taxe - detail.DiscountNet);
        detail.Total_price = parseFloat(detail.Net_price + detail.taxe);
      }

      // 3) Enforce min price
      if ((detail.min_price || 0) > 0 && detail.Net_price < detail.min_price) {
        this.makeToast('warning', this.$t('Price_below_min_not_allowed'), this.$t('Warning'));
        // revert to retail
        detail.price_type = 'retail';
        detail.Unit_price = (retailBase !== undefined && retailBase !== null && retailBase !== '') ? retailBase : detail.Unit_price;
        // recompute again
        if (detail.discount_Method == "2") {
          detail.DiscountNet = detail.discount;
        } else {
          detail.DiscountNet = parseFloat((detail.Unit_price * detail.discount) / 100);
        }
        if (detail.tax_method == "1") {
          detail.Net_price = parseFloat(detail.Unit_price - detail.DiscountNet);
          detail.taxe = parseFloat((detail.tax_percent * (detail.Unit_price - detail.DiscountNet)) / 100);
          detail.Total_price = parseFloat(detail.Net_price + detail.taxe);
        } else {
          detail.taxe = parseFloat((detail.Unit_price - detail.DiscountNet) * (detail.tax_percent / 100));
          detail.Net_price = parseFloat(detail.Unit_price - detail.taxe - detail.DiscountNet);
          detail.Total_price = parseFloat(detail.Net_price + detail.taxe);
        }
      }

      // 4) Update baseline for the (final) selected type
      if (detail.price_type === 'wholesale') {
        detail.wholesale_unit_price = detail.Unit_price;
      } else {
        detail.retail_unit_price = detail.Unit_price;
      }

      this.$forceUpdate();
      this.CalculTotal();
    },

    // Ensure each rendered item always has a default price_type for binding
    ensurePriceType(detail){
      if (!detail) return;
      if (!detail.price_type) this.$set(detail, 'price_type', 'retail');
    },

    // ==================== RESET METHOD ====================
    async Reset_Pos() {
      // Track whether a category/brand filter was active BEFORE the reset so
      // we can decide whether a full product refetch is needed. The common
      // case (no filter) stays fast; only a filtered view requires a reload
      // so the cleared filter matches what is shown.
      const hadFilter = !!(this.category_id || this.brand_id);

      this.details = [];
      this.product = {};
      this.draft_sale_id = '';
      this.paymentLines = [
        {
          amount: 0,
          payment_method_id: '2',
        }
      ];

      this.selectedAccount = null;
      this.globalPaymentNote = '';

      this.savedPaymentMethods= [],
      this.hasSavedPaymentMethod= false,
      this.useSavedPaymentMethod= false,
      this.selectedCard=null,
      this.card_id='',
      this.is_new_credit_card= false,
      this.submit_showing_credit_card= false,

      this.sale.tax_rate = this.default_tax;
      this.sale.TaxNet = 0;
      this.sale.shipping = 0;
      this.sale.discount = 0;
      this.sale.discount_Method = '2'; // Reset to fixed (default)
      this.sale.notes = '';
      this.GrandTotal = 0;
      this.total = 0;
      this.category_id = "";
      this.brand_id = "";
      
      this.selectedClientPoints = 0;
      this.points_to_convert = 0;
      this.used_points = 0;
      this.discount_from_points = 0;
      this.clientIsEligible = false;
      this.pointsConverted = false;
      try { this._cd_emit && this._cd_emit({ currency: (this.currentUser && this.currentUser.currency) || '', details: [], discount: 0, TaxNet: 0, GrandTotal: 0 }, true); } catch(e) {}
      
      const client = this.clients.find(client => client.id === 1);
      if (client) {
        this.client_name = client.name;
        this.selectedClientId = 1;

        try {
          const response = await axios.get(`/get_points_client/${this.selectedClientId}`);
          const data = response.data;

          if (data.is_royalty_eligible) {
            this.selectedClientPoints = data.points;
            this.clientIsEligible = true;
          } else {
            this.selectedClientPoints = 0;
            this.clientIsEligible = false;
          }
        } catch (error) {
        }
      }

      // NOTE: We intentionally avoid the previous blanket re-fetch of the
      // full products list here. Stock for the items just sold has already
      // been patched via applyStockUpdate() using the authoritative values
      // returned by the sale response, and the periodic delta-sync keeps
      // the rest of the catalog coherent across multiple cashiers. The
      // only case where a full refetch is still required is when a
      // category/brand filter was active — in that case we just cleared
      // those filters above and the displayed list must be re-queried
      // without a filter to match.
      if (hadFilter) {
        try { await this.getProducts(); } catch (e) {}
      }
    },

    // ==================== SEARCH METHODS ====================
    search(){
      if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
      }
      if (this.search_input.length < 2) {
        return this.product_filter= [];
      }
      if (this.sale.warehouse_id != "" &&  this.sale.warehouse_id != null) {
        this.timer = setTimeout(() => {

          let barcode = this.search_input.trim();
          let weight = null;
          if (barcode.length === 13 && !isNaN(barcode)) {
            // Play sound only if barcode scanning sound is enabled
            if (this.pos_settings.barcode_scanning_sound) {
              this.audio.play();
            }
            // Play sound only if barcode scanning sound is enabled
            if (this.pos_settings.barcode_scanning_sound) {
              this.audio.play();
            }
            let product = this.products_pos.find(prod =>
              prod.code === barcode &&
              (prod.product_type === 'is_service' || this.isOversellingAllowed || Number(prod.qte_sale || 0) > 0)
            );
            if (product) {
              this.Check_Product_Exist(product, product.id, weight);
              return;
            }else{

              let productCode = barcode.substring(0, 7);
              let weight = parseFloat(barcode.substring(7, 12)) / 1000;
              let product = this.products_pos.find(prod =>
                prod.code === productCode &&
                (prod.product_type === 'is_service' || this.isOversellingAllowed || Number(prod.qte_sale || 0) > 0)
              );
              if (product) {
                // Play sound only if barcode scanning sound is enabled
                if (this.pos_settings.barcode_scanning_sound) {
                  this.audio.play();
                }
                // Ensure weight does not exceed available quantity (unless overselling is allowed)
                const available = Number(product.qte_sale || 0);
                if (!this.isOversellingAllowed && available > 0 && weight > available) {
                  this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
                  weight = available;
                }
                product.quantity = weight;
                this.Check_Product_Exist(product, product.id, weight);
                return;
              }
            }

            this.makeToast("danger", "Invalid product code scanned", this.$t("Error"));
            this.search_input= '';
            this.product_filter = [];
          }
          
          const product_filter = this.products_pos.filter(product =>
            (product.product_type === 'is_service' || this.isOversellingAllowed || Number(product.qte_sale || 0) > 0) &&
            (product.code === this.search_input || String(product.barcode || '').includes(this.search_input))
          );
          if(product_filter.length === 1){
            // Play sound only if barcode scanning sound is enabled
            if (this.pos_settings.barcode_scanning_sound) {
              this.audio.play();
            }
            this.Check_Product_Exist(product_filter[0], product_filter[0].id, weight = null);
          }else {
            this.product_filter = this.products_pos.filter(product => {
              // Hide out-of-stock products from search results unless overselling is allowed.
              if (!this.isOversellingAllowed && product.product_type !== 'is_service' && Number(product.qte_sale || 0) <= 0) return false;
              const name = String(product.name || '').toLowerCase();
              const code = String(product.code || '').toLowerCase();
              const barcodeStr = String(product.barcode || '').toLowerCase();
              const term = this.search_input.toLowerCase();
              return (
                name.includes(term) ||
                code.includes(term) ||
                barcodeStr.includes(term)
              );
            });
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

    Check_Product_Exist(product, id, weight = null) {
      if(this.load_product){
        this.load_product = false;
        NProgress.start();
        NProgress.set(0.1);
        this.product = {};

        if( product.product_type == 'is_service'){
          this.product.quantity = 1;

        }else{

          this.product.current = product.qte_sale;
          this.product.fix_stock = product.qte;

          if (weight !== null) {
            this.product.quantity = weight;
          } else {
            this.product.quantity = product.qte_sale < 1 ? product.qte_sale : 1;
          }

        }
        this.Get_Product_Details(id, product.product_variant_id, product);

        NProgress.done();
        this.search_input= '';
        this.product_filter = [];

      }else{
          this.makeToast(
            "warning",
            this.$t("Please_wait_until_the_product_is_loaded"),
            this.$t("Warning")
          );
      }
    },

    Products_by_Category(id) {
      this.category_id = id;
      this.getProducts();
    },

    Products_by_Brands(id) {
      this.brand_id = id;
      this.getProducts();
    },

    getAllCategory() {
      this.category_id = "";
      this.search_category = '';
      this.getProducts();
    },

    GetAllBrands() {
      this.brand_id = "";
      this.search_brand = '';
      this.getProducts(1);
    },

    // ==================== DRAFT SALES METHODS (from old_pos) ====================
    Show_Draft_Sales() {
      this.draft_sales_page = 1;
      this.get_Draft_Sales(1);
      setTimeout(() => {
        this.$bvModal.show("show_draft_sales");
      }, 1000);
    },

    onPageChangeDraftSales(page) {
      this.draft_sales_page = page;
      this.get_Draft_Sales(page);
    },

    get_Draft_Sales(page) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          "get_draft_sales?page=" +
            page +
            "&limit=" +
            this.limit
        )
        .then(response => {
          this.draft_sales = response.data.draft_sales;
          this.totalRows_draft_sales = response.data.totalRows;
          
          // If current page is empty but we have data and we're not on page 1, go to previous page
          if (this.draft_sales.length === 0 && this.totalRows_draft_sales > 0 && page > 1) {
            this.draft_sales_page = page - 1;
            this.get_Draft_Sales(this.draft_sales_page);
            return;
          }
          
          NProgress.done();
        })
        .catch(() => {
          NProgress.done();
        });
    },

    // Load a draft sale into the current POS view without navigating
    loadDraftSale(id) {
      this.openingDraftId = id;
      // If this draft is already loaded, do nothing (do not update on open)
      if (this.draft_sale_id && String(this.draft_sale_id) === String(id)) {
        this.openingDraftId = null;
        return;
      }

      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(`pos/data_draft_convert_sale/${id}`)
        .then(response => {
          this.draft_sale_id = id;
          const data = response.data || {};

          // Basic references (keep layout/logic unchanged; just inject data)
          if (Array.isArray(data.clients)) this.clients = data.clients;
          if (Array.isArray(data.accounts)) this.accounts = data.accounts;
          if (Array.isArray(data.warehouses)) this.warehouses = data.warehouses;
          if (Array.isArray(data.categories)) this.categories = data.categories;
          if (Array.isArray(data.brands)) this.brands = data.brands;
          if (Array.isArray(data.payment_methods)) this.payment_methods = data.payment_methods;
          if (data.default_account_id !== undefined) this.default_account_id = data.default_account_id ?? null;
          if (data.default_payment_method_id !== undefined) this.default_payment_method_id = data.default_payment_method_id ?? null;

          // Customer & loyalty
          this.selectedClientId = data.client_id || (data.sale && data.sale.client_id) || this.selectedClientId;
          this.client_name = data.client_name || this.client_name;
          this.clientIsEligible = data.default_client_eligible === true || data.default_client_eligible === 1;
          this.selectedClientPoints = this.clientIsEligible ? parseFloat(data.default_client_points || 0) : 0;
          if (typeof data.point_to_amount_rate !== 'undefined') {
            this.point_to_amount_rate = data.point_to_amount_rate;
          }

          // Sale-level fields
          const saleData = data.sale || {};
          this.sale.warehouse_id = (data.warehouse_id !== undefined && data.warehouse_id !== null)
            ? data.warehouse_id
            : (saleData.warehouse_id || this.sale.warehouse_id);
          this.sale.tax_rate = saleData.tax_rate || 0;
          this.sale.TaxNet = saleData.TaxNet || 0;
          this.sale.discount = saleData.discount || 0;
          // Backward compatibility: default to fixed ('2') if discount_Method is not present
          this.sale.discount_Method = saleData.discount_Method || '2';
          this.sale.shipping = saleData.shipping || 0;
          this.sale.notes = saleData.notes || '';

          // Map draft details to POS details shape (ensuring fields required by POS)
          const incoming = Array.isArray(data.details) ? data.details : [];
          const mapped = incoming.map((it, idx) => {
            const d = { ...it };
            if (d.detail_id === undefined || d.detail_id === null) d.detail_id = idx + 1;
            if (!d.price_type) d.price_type = 'retail';
            if (d.retail_unit_price === undefined) d.retail_unit_price = d.Unit_price;
            if (d.wholesale_unit_price === undefined) d.wholesale_unit_price = (d.Unit_price_wholesale !== undefined ? d.Unit_price_wholesale : d.Unit_price);
            if (d.min_price === undefined) d.min_price = 0;
            if (d.current === undefined || d.current === null) d.current = (d.fix_stock !== undefined ? d.fix_stock : d.quantity);
            if (d.fix_stock === undefined || d.fix_stock === null) d.fix_stock = d.current;

            const unitPrice = Number(d.Unit_price || 0);
            const discountVal = Number(d.discount || 0);
            const discountMethod = String(d.discount_Method || '2'); // 1: %, 2: fixed
            const taxPercent = Number(d.tax_percent || 0);
            const taxMethod = String(d.tax_method || '1'); // 1: Exclusive, 2: Inclusive

            if (typeof d.DiscountNet === 'undefined') {
              d.DiscountNet = discountMethod === '2' ? discountVal : (unitPrice * (discountVal / 100));
            }

            if (taxMethod === '1') {
              d.Net_price = parseFloat((unitPrice - d.DiscountNet).toFixed(2));
              d.taxe = parseFloat((((unitPrice - d.DiscountNet) * taxPercent) / 100).toFixed(2));
              d.Total_price = parseFloat((d.Net_price + d.taxe).toFixed(2));
            } else {
              d.taxe = parseFloat(((unitPrice - d.DiscountNet) * (taxPercent / 100)).toFixed(2));
              d.Net_price = parseFloat((unitPrice - d.taxe - d.DiscountNet).toFixed(2));
              d.Total_price = parseFloat((d.Net_price + d.taxe).toFixed(2));
            }
            return d;
          });
          this.details = mapped;

          // Totals
          this.GrandTotal = Number(data.GrandTotal || 0);
          this.CalculTotal();

          // Refresh product lists for the chosen warehouse (unified API)
          if (this.sale.warehouse_id) {
            this.getProducts();
          }

          // Close draft list modal
          try { this.$bvModal.hide('show_draft_sales'); } catch (e) {}

          NProgress.done();
        })
        .catch(() => {
          NProgress.done();
        })
        .finally(() => {
          this.openingDraftId = null;
        });
    },

    Remove_Draft_Sale(id) {
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
            .delete("remove_draft_sale/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              Fire.$emit("event_delete_draft_sale");
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

    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.get_Draft_Sales(currentPage);
      }
    },
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.get_Draft_Sales(1);
      }
    },

    getProducts() {
      this.productsLoading = true;
      NProgress.start();
      NProgress.set(0.1);
      return axios
        .get(
          "pos/get_products_pos" +
            "?category_id=" +
            this.category_id +
            "&brand_id=" +
            this.brand_id +
            "&warehouse_id=" +
            this.sale.warehouse_id +
            "&stock=" + 1 +
            "&product_service=" + 1 +
            "&product_combo=" + 1
        )
        .then(response => {
          const rawProducts = Array.isArray(response.data.products) ? response.data.products : [];
          // Always show real backend stock in online mode;
          // only apply shadow stock adjustments when we are offline.
          this.products = rawProducts.map(p => ({ ...p }));
          // Use the same unified list for barcode scanning / quick search.
          this.products_pos = rawProducts.map(p => ({ ...p }));
          this.product_totalRows = response.data.totalRows;
          // Capture backend server time as the baseline for incremental delta
          // sync. If absent (older backend), fall back to null so delta sync
          // will stay disabled until the next successful call provides one.
          this.lastProductsSyncAt = response.data && response.data.server_time
            ? response.data.server_time
            : null;
          this.Product_paginatePerPage();
          const reallyOffline = (!this.isOnline) || (typeof window !== 'undefined' && window.navigator && window.navigator.onLine === false);
          if (reallyOffline) {
            try {
              if (Util && Util.shadowStock && Util.shadowStock.applyToList && this.sale.warehouse_id) {
                Util.shadowStock.applyToList(this.sale.warehouse_id, this.products);
              }
            } catch (e) {}
          }
          this.productsReady = true;
          // Cache grid products for offline usage (per warehouse) using RAW server data
          try {
            if (Util && Util.offlinePos && Util.offlinePos.cacheWarehouseSnapshot && this.sale.warehouse_id) {
              Util.offlinePos.cacheWarehouseSnapshot(this.sale.warehouse_id, {
                products: rawProducts,
                products_pos: rawProducts,
                product_totalRows: this.product_totalRows,
                lastLoadedPage: 1,
                category_id: this.category_id,
                brand_id: this.brand_id,
                lastSyncAt: this.lastProductsSyncAt
              });
            }
          } catch (e) {}
          NProgress.done();
        })
        .catch(() => {
          // Offline/failed request: try to hydrate from cached snapshot
          try {
            if (Util && Util.offlinePos && Util.offlinePos.getWarehouseSnapshot && this.sale.warehouse_id) {
              const snap = Util.offlinePos.getWarehouseSnapshot(this.sale.warehouse_id);
              if (snap && Array.isArray(snap.products)) {
                const baseProducts = snap.products;
                this.products = baseProducts.map(p => ({ ...p }));
                // Hydrate scan/search list from cached unified products if available
                if (Array.isArray(snap.products_pos)) {
                  this.products_pos = snap.products_pos.map(p => ({ ...p }));
                } else {
                  this.products_pos = baseProducts.map(p => ({ ...p }));
                }
                this.product_totalRows = snap.product_totalRows || baseProducts.length;
                // Restore last delta-sync baseline so when we regain connectivity
                // we can resume incremental sync from where we left off instead
                // of re-fetching the whole catalog.
                this.lastProductsSyncAt = snap.lastSyncAt || null;
                this.Product_paginatePerPage();
                const reallyOffline = (!this.isOnline) || (typeof window !== 'undefined' && window.navigator && window.navigator.onLine === false);
                if (reallyOffline) {
                  // When truly offline, apply shadow stock on top of cached data
                  try {
                    if (Util && Util.shadowStock && Util.shadowStock.applyToList) {
                      Util.shadowStock.applyToList(this.sale.warehouse_id, this.products);
                    }
                  } catch (e2) {}
                }
              }
            }
          } catch (e) {}
          this.productsReady = true; // avoid blocking UI forever on error
          NProgress.done();
        })
        .finally(() => {
          this.productsLoading = false;
        });
    },

    // ==================== INCREMENTAL STOCK SYNC ====================
    // Patch in-memory product lists (and the offline snapshot) with stock
    // updates returned by the backend (either from a sale response or from
    // the delta-sync endpoint). Avoids re-downloading the full catalog after
    // every sale while still keeping stock coherent across multiple cashiers.
    applyStockUpdate(updates, serverTime) {
      const list = Array.isArray(updates) ? updates : [];
      let changed = 0;

      if (list.length) {
        const keyOf = (p) => {
          const v = p && p.product_variant_id != null ? p.product_variant_id : 'null';
          return (p && p.id != null ? p.id : '?') + ':' + v;
        };
        const indexBy = (arr) => {
          const m = new Map();
          if (Array.isArray(arr)) {
            for (let i = 0; i < arr.length; i++) {
              m.set(keyOf(arr[i]), i);
            }
          }
          return m;
        };

        const productsIdx = indexBy(this.products);
        const productsPosIdx = indexBy(this.products_pos);

        for (let i = 0; i < list.length; i++) {
          const u = list[i] || {};
          const key = (u.id != null ? u.id : '?') + ':' + (u.product_variant_id != null ? u.product_variant_id : 'null');
          const pi = productsIdx.get(key);
          if (pi != null && this.products[pi]) {
            this.$set(this.products[pi], 'qte', u.qte);
            this.$set(this.products[pi], 'qte_sale', u.qte_sale);
            changed++;
          }
          const ppi = productsPosIdx.get(key);
          if (ppi != null && this.products_pos[ppi]) {
            this.$set(this.products_pos[ppi], 'qte', u.qte);
            this.$set(this.products_pos[ppi], 'qte_sale', u.qte_sale);
          }
        }
      }

      if (serverTime) {
        this.lastProductsSyncAt = serverTime;
      }

      // Only rewrite the offline snapshot when something actually changed or
      // the baseline timestamp moved — the snapshot can be several MB on
      // larger catalogs so we avoid needless localStorage churn.
      if (changed > 0 || serverTime) {
        this.persistStockSnapshot();
      }
    },

    persistStockSnapshot() {
      try {
        if (Util && Util.offlinePos && Util.offlinePos.cacheWarehouseSnapshot && this.sale && this.sale.warehouse_id) {
          Util.offlinePos.cacheWarehouseSnapshot(this.sale.warehouse_id, {
            products: this.products,
            products_pos: this.products_pos,
            product_totalRows: this.product_totalRows,
            lastLoadedPage: this.product_currentPage || 1,
            category_id: this.category_id,
            brand_id: this.brand_id,
            lastSyncAt: this.lastProductsSyncAt
          });
        }
      } catch (e) {}
    },

    async syncProductsDelta() {
      // Lightweight polling: ask the backend for only the product_warehouse
      // rows that changed since our last sync, then patch them in-place.
      if (!this.isOnline) return;
      if (!this.sale || !this.sale.warehouse_id) return;
      if (!this.lastProductsSyncAt) return;
      if (this._deltaSyncing) return;
      this._deltaSyncing = true;
      try {
        const resp = await axios.get('pos/get_products_pos_changes', {
          params: {
            warehouse_id: this.sale.warehouse_id,
            since: this.lastProductsSyncAt
          }
        });
        const data = (resp && resp.data) || {};
        const updates = Array.isArray(data.products) ? data.products : [];
        const serverTime = data.server_time || null;
        this.applyStockUpdate(updates, serverTime);
      } catch (e) {
        // Transient network/server errors: keep current state and try again
        // on the next tick. Do not flip offline; handleOffline() owns that.
      } finally {
        this._deltaSyncing = false;
      }
    },

    // ==================== WAREHOUSE & CLIENT SELECTION ====================
    onCustomerDisplayScreenChange(value) {
      const screenId = value || '1';
      this.customer_display_screen_id = String(screenId);
      try {
        localStorage.setItem('pos_customer_display_screen_id', this.customer_display_screen_id);
      } catch (e) {}
      this._cd_queue_broadcast && this._cd_queue_broadcast();
    },
    Selected_Warehouse(value) {
      this.search_input = '';
      this.product_filter = [];

      // If warehouse is cleared, reset product lists and avoid calling API/cache.
      if (!value) {
        this.products = [];
        this.products_pos = [];
        this.product_totalRows = 0;
        this.paginated_Products = [];
        this.product_currentPage = 1;
        this.productsLoading = false;
        return;
      }

      // With unified API, a single call loads both grid and scan lists.
      this.getProducts();

      // Refresh batch availability on existing batch-tracked cart lines (warehouse-specific).
      if (Array.isArray(this.details)) {
        for (const d of this.details) {
          if (d && d.is_batch_tracked) {
            this.$set(d, "batches", []);
            this.fetch_batches_for_detail(d);
          }
        }
      }
    },

    //----------------------------------------- Batch handling -------------------------\\
    fetch_batches_for_detail(detail) {
      if (!detail) return;
      // Make sure all batch-related fields exist reactively on the cart line.
      if (!("batches_loading" in detail)) this.$set(detail, "batches_loading", false);
      if (!("available_batches" in detail)) this.$set(detail, "available_batches", []);
      if (!Array.isArray(detail.batches)) this.$set(detail, "batches", []);

      if (!detail.is_batch_tracked) {
        this.$set(detail, "batches_loading", false);
        return;
      }
      // Offline: skip live fetch — backend will auto-FEFO at submit time.
      if (this.isOnline === false) {
        this.$set(detail, "batches_loading", false);
        return;
      }
      const wid = this.sale && this.sale.warehouse_id;
      const productId = detail.product_id || detail.id;
      if (!wid || !productId) {
        this.$set(detail, "batches_loading", false);
        return;
      }
      const variantSeg = (detail.product_variant_id != null && detail.product_variant_id !== "")
        ? detail.product_variant_id
        : 0;
      this.$set(detail, "batches_loading", true);
      axios
        .get(`batches_for_sale/${productId}/${wid}/${variantSeg}`, { timeout: 15000 })
        .then(response => {
          const list = (response && response.data && Array.isArray(response.data.batches))
            ? response.data.batches
            : [];
          this.$set(detail, "available_batches", list);
          if (!Array.isArray(detail.batches)) this.$set(detail, "batches", []);
          // Auto-seed the first batch row with the full line qty so the cashier only has
          // to pick a batch — keeps the strict-validation flow fast on the happy path.
          if (detail.batches.length === 0 && list.length > 0) {
            this.add_batch_to_detail(detail);
          }
        })
        .catch(() => {
          this.$set(detail, "available_batches", []);
        })
        .then(() => {
          this.$set(detail, "batches_loading", false);
        });
    },

    add_batch_to_detail(detail) {
      if (!Array.isArray(detail.batches)) this.$set(detail, "batches", []);
      detail.batches.push({
        product_batch_id: null,
        batch_no: "",
        expiry_date: null,
        qty_available: 0,
        qty: detail.batches.length === 0 ? (Number(detail.quantity) || 0) : 0,
      });
    },

    remove_batch_from_detail(detail, idx) {
      if (!Array.isArray(detail.batches)) return;
      detail.batches.splice(idx, 1);
    },

    on_batch_select(detail, idx, batchId) {
      const list = Array.isArray(detail.available_batches) ? detail.available_batches : [];
      const row = detail.batches[idx];
      if (!row) return;
      const ab = list.find(x => x.id === batchId);
      this.$set(row, "product_batch_id", ab ? ab.id : null);
      this.$set(row, "batch_no", ab ? ab.batch_no : "");
      this.$set(row, "expiry_date", ab ? ab.expiry_date : null);
      this.$set(row, "qty_available", ab ? Number(ab.qty_available) || 0 : 0);
    },

    on_batch_qty_input(b, val) {
      const num = parseFloat(String(val).replace(",", "."));
      this.$set(b, "qty", Number.isFinite(num) ? num : 0);
    },

    batch_total_qty(detail) {
      if (!detail || !Array.isArray(detail.batches)) return 0;
      return detail.batches.reduce((sum, b) => sum + (Number(b.qty) || 0), 0);
    },

    batch_qty_mismatch(detail) {
      if (!detail || !detail.is_batch_tracked) return false;
      if (!Array.isArray(detail.batches) || detail.batches.length === 0) return false;
      const total = this.batch_total_qty(detail);
      const target = Number(detail.quantity) || 0;
      return Math.abs(total - target) > 0.0001;
    },

    batch_line_error(detail) {
      // Returns null if the line is OK, or a translated error string otherwise.
      if (!detail || !detail.is_batch_tracked) return null;
      // Still loading — skip validation; user can't act yet.
      if (detail.batches_loading) return null;
      const available = Array.isArray(detail.available_batches) ? detail.available_batches : [];
      // No availability in this warehouse — block the sale (cashier must change warehouse / restock).
      if (available.length === 0) {
        return this.$t('No_Batches_Available') || 'No available batches for this product in the selected warehouse';
      }
      const batches = Array.isArray(detail.batches) ? detail.batches : [];
      if (batches.length === 0) {
        return this.$t('Choose_Batch') || 'Choose a batch';
      }
      for (const b of batches) {
        if (!b.product_batch_id) {
          return this.$t('Choose_Batch') || 'Choose a batch';
        }
        const qty = Number(b.qty);
        if (!Number.isFinite(qty) || qty <= 0) {
          return this.$t('Quantity_Required') || 'Quantity is required';
        }
        if (qty > (Number(b.qty_available) || 0) + 0.0001) {
          return this.$t('Batch_Qty_Exceeds_Available') || 'Quantity exceeds available stock';
        }
      }
      const seen = new Set();
      for (const b of batches) {
        if (seen.has(b.product_batch_id)) {
          return this.$t('Duplicate_Batch_Selected') || 'The same batch is selected twice';
        }
        seen.add(b.product_batch_id);
      }
      if (this.batch_qty_mismatch(detail)) {
        return this.$t('Total_batch_qty_mismatch') || 'Total batch quantity does not match the line quantity';
      }
      return null;
    },

    has_batch_validation_errors() {
      if (!Array.isArray(this.details)) return false;
      for (const d of this.details) {
        if (this.batch_line_error(d)) return true;
      }
      return false;
    },

    first_batch_error_message() {
      if (!Array.isArray(this.details)) return null;
      for (const d of this.details) {
        const err = this.batch_line_error(d);
        if (err) {
          const name = d && d.name ? d.name : '';
          return name ? `${name}: ${err}` : err;
        }
      }
      return null;
    },

    expiry_pill_style(dateStr) {
      const base = {
        display: "inline-block",
        padding: "1px 6px",
        fontSize: "10px",
        fontWeight: "600",
        borderRadius: "8px",
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

    buildSubmitDetails() {
      // Strip helper UI fields and map batches to clean payload before sending to backend.
      return (this.details || []).map(d => {
        const out = { ...d };
        delete out.available_batches;
        delete out.batches_loading;
        if (d.is_batch_tracked && Array.isArray(d.batches)) {
          out.batches = d.batches
            .filter(b => b && b.product_batch_id && Number(b.qty) > 0)
            .map(b => ({
              product_batch_id: Number(b.product_batch_id),
              qty: Number(b.qty) || 0,
              unit_price: Number(d.Unit_price) || 0,
            }));
        } else {
          delete out.batches;
        }
        return out;
      });
    },

    onOnlineReloadNow() {
      this.onlineReloadModalVisible = false;
      this.onlineReloadAfterSale = false;
      if (typeof window !== 'undefined' && window.location && typeof window.location.reload === 'function') {
        try { window.location.reload(); } catch (e) {}
      }
    },

    onOnlineReloadAfterSale() {
      this.onlineReloadAfterSale = true;
      this.onlineReloadModalVisible = false;
    },

    onOnlineReloadDismiss() {
      this.onlineReloadModalVisible = false;
      this.onlineReloadAfterSale = false;
    },

    // ==================== DETAIL EDIT METHODS (from old_pos) ====================
    get_units(value) {
      const UNITS_CACHE_KEY = 'pos_units_by_product';

      const loadUnitsCache = () => {
        try {
          const raw = typeof window !== 'undefined' ? window.localStorage.getItem(UNITS_CACHE_KEY) : null;
          return raw ? JSON.parse(raw) : {};
        } catch (e) {
          return {};
        }
      };

      const saveUnitsCache = (map) => {
        try {
          if (typeof window !== 'undefined') {
            window.localStorage.setItem(UNITS_CACHE_KEY, JSON.stringify(map || {}));
          }
        } catch (e) {}
      };

      const reallyOffline = (!this.isOnline) || (typeof window !== 'undefined' && window.navigator && window.navigator.onLine === false);
      const pid = String(value);

      if (reallyOffline) {
        // 1) Try in-memory cache first
        if (this.unitsByProductId && this.unitsByProductId[pid]) {
          const units = this.unitsByProductId[pid];
          this.units = units;
          if (!this.detail.sale_unit_id && Array.isArray(units) && units[0] && units[0].id) {
            this.detail.sale_unit_id = units[0].id;
          }
          return Promise.resolve(units);
        }

        // 2) Try persisted localStorage cache
        const stored = loadUnitsCache();
        if (stored && stored[pid]) {
          const units = stored[pid];
          this.unitsByProductId = Object.assign({}, this.unitsByProductId || {}, { [pid]: units });
          this.units = units;
          if (!this.detail.sale_unit_id && Array.isArray(units) && units[0] && units[0].id) {
            this.detail.sale_unit_id = units[0].id;
          }
          return Promise.resolve(units);
        }

        // 3) Fallback: synthesize a minimal units array from the existing detail row
        try {
          const row = (this.details || []).find(d => d.product_id === value);
          if (row) {
            const syntheticId = row.sale_unit_id || row.product_id || 0;
            const name = row.unitSale || row.unitSaleName || '';
            const units = [
              {
                id: syntheticId,
                name: name || (this.$t ? this.$t('UnitSale') : 'Unit'),
                ShortName: name || '',
                operator: '*',
                operator_value: 1,
              }
            ];
            this.units = units;
            this.unitsByProductId = Object.assign({}, this.unitsByProductId || {}, { [pid]: units });
            if (!this.detail.sale_unit_id) {
              this.detail.sale_unit_id = syntheticId;
            }
            return Promise.resolve(units);
          }
        } catch (e) {}

        // 4) Last resort: keep whatever units we already have (may be empty),
        // but do not reject so the caller's .finally() still runs.
        return Promise.resolve(this.units || []);
      }

      // Online mode: fetch from API and cache by product_id for future offline use
      return axios
        .get("get_units?id=" + value)
        .then(({ data }) => {
          const units = Array.isArray(data) ? data : [];
          this.units = units;
          const map = Object.assign({}, this.unitsByProductId || {}, { [pid]: units });
          this.unitsByProductId = map;
          const stored = loadUnitsCache();
          stored[pid] = units;
          saveUnitsCache(stored);
          return units;
        });
    },
    Modal_Updat_Detail(detail) {
      this.detailLoading = true;
      this.detail = {};
      this.detail.name = detail.name;
      this.$bvModal.show("form_Update_Detail");
      this.get_units(detail.product_id)
        .catch(() => {})
        .finally(() => {
          this.detail.detail_id = detail.detail_id;
          this.detail.sale_unit_id = detail.sale_unit_id;
          this.detail.product_type = detail.product_type;
          this.detail.Unit_price = detail.Unit_price;
          this.detail.price_type = detail.price_type || 'retail';
          const baseRetail = (detail.retail_unit_price !== undefined && detail.retail_unit_price !== null)
            ? detail.retail_unit_price
            : detail.Unit_price;
          let baseWholesale = (detail.wholesale_unit_price !== undefined && detail.wholesale_unit_price !== null)
            ? detail.wholesale_unit_price
            : detail.Unit_price_wholesale;
          if (baseWholesale === undefined || baseWholesale === null || baseWholesale === 0) {
            baseWholesale = baseRetail;
          }
          this.detail.retail_unit_price = baseRetail;
          this.detail.wholesale_unit_price = baseWholesale;
          this.detail.min_price = detail.min_price !== undefined ? detail.min_price : 0;
          this.detail.fix_price = detail.fix_price;
          this.detail.fix_stock = detail.fix_stock;
          this.detail.current = detail.current;
          // Normalize tax_method so v-select shows the correct label in both
          // online and offline modes (1 => Exclusive, 2 => Inclusive).
          const rawTaxMethod = detail.tax_method;
          this.detail.tax_method = (rawTaxMethod === 2 || rawTaxMethod === '2') ? 2 : 1;
          this.detail.discount_Method = detail.discount_Method;
          this.detail.discount = detail.discount;
          this.detail.quantity = detail.quantity;
          this.detail.tax_percent = detail.tax_percent;
          this.detail.is_imei = detail.is_imei;
          this.detail.imei_number = detail.imei_number;
          this.detailLoading = false;
        });
    },
    submit_Update_Detail() {
      this.$refs.Update_Detail && this.$refs.Update_Detail.validate().then(success => {
        if (!success) {
          return;
        } else {
          this.Update_Detail();
        }
      }).catch(() => {
        // Fallback: proceed without form validation if ref is absent in new design
        this.Update_Detail();
      });
    },
    Update_Detail() {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === this.detail.detail_id) {
          // Min price validation (unit and net)
          const rawMinCandidate = (this.details[i].min_price !== undefined && this.details[i].min_price !== null)
            ? this.details[i].min_price
            : (this.detail.min_price || 0);
          const minPriceRow = parseFloat(String(rawMinCandidate).toString().replace(/,/g, '')) || 0;
          const unitPriceNum = parseFloat(String(this.detail.Unit_price).toString().replace(/,/g, '')) || 0;
          if (minPriceRow > 0 && unitPriceNum < minPriceRow) {
            this.makeToast('warning', this.$t('Price_below_min_not_allowed'), this.$t('Warning'));
            return;
          }
          // compute proposed net to check against min price
          let proposedDiscountNet = (this.detail.discount_Method == "2")
            ? this.detail.discount
            : parseFloat((unitPriceNum * this.detail.discount) / 100);
          let proposedNet, proposedTax;
          if (this.detail.tax_method == "1") {
            proposedNet = parseFloat(unitPriceNum - proposedDiscountNet);
            proposedTax = parseFloat((this.detail.tax_percent * (unitPriceNum - proposedDiscountNet)) / 100);
          } else {
            proposedTax = parseFloat((unitPriceNum - proposedDiscountNet) * (this.detail.tax_percent / 100));
            proposedNet = parseFloat(unitPriceNum - proposedTax - proposedDiscountNet);
          }
          if (minPriceRow > 0 && proposedNet < minPriceRow) {
            this.makeToast('warning', this.$t('Price_below_min_not_allowed'), this.$t('Warning'));
            return;
          }

          if (this.details[i].product_type !== 'is_service') {
            for (var k = 0; k < this.units.length; k++) {
              if (this.units[k].id == this.detail.sale_unit_id) {
                if (this.units[k].operator == "/") {
                  this.details[i].current =
                    this.detail.fix_stock * this.units[k].operator_value;
                  this.details[i].unitSale = this.units[k].ShortName;
                } else {
                  this.details[i].current =
                    this.detail.fix_stock / this.units[k].operator_value;
                  this.details[i].unitSale = this.units[k].ShortName;
                }
              }
            }
          if (this.details[i].current < this.details[i].quantity) {
            this.details[i].quantity = this.details[i].current;
          }
          }
        this.details[i].Unit_price = unitPriceNum;
        this.details[i].price_type = this.detail.price_type;
          this.details[i].tax_percent = this.detail.tax_percent;
          this.details[i].tax_method = this.detail.tax_method;
          this.details[i].discount_Method = this.detail.discount_Method;
          this.details[i].discount = this.detail.discount;
          this.details[i].sale_unit_id = this.detail.sale_unit_id;
          this.details[i].imei_number = this.detail.imei_number;
          this.details[i].product_type = this.detail.product_type;

          // reuse computed values
          this.details[i].DiscountNet = proposedDiscountNet;
          this.details[i].taxe = proposedTax;
          this.details[i].Net_price = proposedNet;
          this.details[i].Total_price = parseFloat(proposedNet + proposedTax);
          this.$forceUpdate();
        }
      }
      this.CalculTotal();
      setTimeout(() => {
        this.$bvModal.hide("form_Update_Detail");
      }, 1000);
    },

    async onClientSelected(selectedClientId) {
      this.client_name = '';
      this.selectedClientPoints = 0;
      this.points_to_convert = 0;
      this.discount_from_points = 0;
      this.used_points = 0;
      this.clientIsEligible = false;
      this.pointsConverted = false;
      this.sale.discount = 0;
      this.selectedClientCreditLimit = 0;
      this.selectedClientNetBalance = 0;

      if (!selectedClientId) {
        this.selectedClientId = "";
        this.CalculTotal();
        return;
      }

      const client = this.clients.find(c => c.id === selectedClientId);
      if (client) {
        this.client_name = client.name;
        this.selectedClientId = selectedClientId;

        try {
          const response = await axios.get(`/get_points_client/${selectedClientId}`);
          const data = response.data;

          if (data.is_royalty_eligible) {
            this.selectedClientPoints = data.points;
            this.clientIsEligible = true;
          } else {
            this.selectedClientPoints = 0;
            this.clientIsEligible = false;
          }
        } catch (error) {
          console.error('Error fetching client points:', error);
        }

        // Fetch client credit limit and current balance
        try {
          const briefResponse = await axios.get(`/clients/${selectedClientId}/brief`);
          const briefData = briefResponse.data;
          this.selectedClientCreditLimit = parseFloat(briefData.credit_limit || 0);
          this.selectedClientNetBalance = parseFloat(briefData.netBalance || 0);
        } catch (error) {
          console.error('Error fetching client credit limit:', error);
          this.selectedClientCreditLimit = 0;
          this.selectedClientNetBalance = 0;
        }

      } else {
        this.selectedClientId = "";
        this.selectedClientCreditLimit = 0;
        this.selectedClientNetBalance = 0;
      }

      this.CalculTotal();
    },

    convertPointsToDiscount() {
      if (this.pointsConverted) {
        const current = Number(this.sale.discount || 0);
        const toRemove = Number(this.discount_from_points || 0);
        // For fixed discounts, revert the combined fixed discount amount
        if (String(this.sale.discount_Method || '2') !== '1') {
          this.sale.discount = Math.max(0, parseFloat((current - toRemove).toFixed(2)));
        }
        this.discount_from_points = 0;
        this.used_points = 0;
        this.points_to_convert = 0;
        this.pointsConverted = false;
      } else {
        const maxPoints = Number(this.selectedClientPoints) || 0;
        let pts = Number(this.points_to_convert);
        if (!Number.isFinite(pts) || pts <= 0) {
          this.makeToast('warning', this.$t ? this.$t('Please_enter_points_to_convert') : 'Please enter points to convert', this.$t ? this.$t('Warning') : 'Warning');
          return;
        }
        if (pts > maxPoints) pts = maxPoints;
        const discount = parseFloat((pts * this.point_to_amount_rate).toFixed(2));
        this.discount_from_points = discount;
        // Don't merge points into sale.discount - keep them separate so input shows only manual discount
        // Points discount is stored in discount_from_points and applied separately in calculations
        this.used_points = pts;
        this.pointsConverted = true;
      }

      this.CalculTotal();
    },

    onPointsToConvertInput() {
      let max = Number(this.selectedClientPoints) || 0;
      let val = Number(this.points_to_convert);
      if (!Number.isFinite(val)) val = 0;
      if (val < 0) val = 0;
      // enforce integer points
      val = Math.floor(val);
      if (val > max) {
        val = max;
        this.makeToast('warning', this.$t ? this.$t('Entered_points_exceed_available') : 'Entered points exceed available', this.$t ? this.$t('Warning') : 'Warning');
      }
      this.points_to_convert = val;
    },

    // ==================== INVOICE & PRINT METHODS ====================
    Invoice_POS(id) {
      // Determine preferred invoice format from settings; default to thermal
      let format = 'thermal';
      try {
        // Prefer explicit invoice_format cached from POS bootstrap
        if (typeof this.invoice_format === 'string' && ['thermal', 'a4'].includes(this.invoice_format)) {
          format = this.invoice_format;
        } else {
          const s = this.invoice_pos && this.invoice_pos.setting ? this.invoice_pos.setting : null;
          if (s && typeof s.invoice_format === 'string' && ['thermal', 'a4'].includes(s.invoice_format)) {
            format = s.invoice_format;
          }
        }
      } catch (e) {}

      // If A4 is selected, print using the existing A4 PDF endpoint (`/api/sale_pdf/{id}`)
      // but keep the UX similar to the thermal POS invoice:
      // - open a print popup window
      // - show the PDF inside it
      // - trigger print
      // - reload after Print/Cancel when "After this sale" is selected
      if (format === 'a4') {
        if (typeof window !== 'undefined') {
          const vm = this;
          const reloadAfterSale = !!vm.onlineReloadAfterSale;

          // Create (or refresh) a same-origin hook so the popup can request a reload immediately
          // after Print/Cancel without relying on the POS window focus changing.
          try {
            window.__posReloadAfterA4Print = () => {
              try {
                if (vm.onlineReloadAfterSale && window.location && typeof window.location.reload === 'function') {
                  vm.onlineReloadAfterSale = false;
                  vm.onlineReloadModalVisible = false;
                  try { window.location.reload(); } catch (e) {}
                }
              } catch (e) {}
            };
          } catch (e) {}

          // Open the popup immediately (before async axios resolves) so it is not blocked,
          // and so the user always sees the print window (like the thermal POS invoice).
          let sw = 1200, sh = 800;
          try {
            sw = (window.screen && window.screen.availWidth) ? window.screen.availWidth : sw;
            sh = (window.screen && window.screen.availHeight) ? window.screen.availHeight : sh;
          } catch (e) {}
          const width = Math.max(700, Math.min(1200, Math.floor(sw * 0.9)));
          const height = Math.max(600, Math.min(900, Math.floor(sh * 0.9)));
          const left = Math.max(0, Math.floor((sw - width) / 2));
          const top = Math.max(0, Math.floor((sh - height) / 2));
          const features = `height=${height},width=${width},left=${left},top=${top},toolbar=0,location=0,menubar=0,status=0,scrollbars=1,resizable=1`;
          const win = window.open('', 'A4Invoice', features);
          if (!win) {
            return;
          }

          // Bootstrap the popup with a loading screen and print/reload wiring.
          try {
            win.document.open();
            win.document.write('<!doctype html><html><head><title>Print</title>');
            win.document.write('<meta name="viewport" content="width=device-width, initial-scale=1.0">');
            win.document.write('<style>html,body{margin:0;padding:0;height:100%;overflow:hidden;background:#fff;}');
            win.document.write('#loading{height:100%;display:flex;align-items:center;justify-content:center;font-family:Arial;color:#444;}');
            win.document.write('embed,object,iframe{width:100%;height:100%;border:0;display:none;}</style>');
            win.document.write('</head><body>');
            win.document.write('<div id="loading">Loading invoice…</div>');
            win.document.write('<object id="pdfObject" type="application/pdf"></object>');
            win.document.write('<embed id="pdfEmbed" type="application/pdf" />');
            win.document.write('<iframe id="pdfFrame"></iframe>');
            win.document.write('<script>(function(){');
            win.document.write('var reloadAfterSale=' + (reloadAfterSale ? 'true' : 'false') + ';');
            win.document.write('var printed=false;');
            win.document.write('function done(){try{if(window.opener){try{if(reloadAfterSale){try{window.opener.__posReloadAfterA4Print && window.opener.__posReloadAfterA4Print();}catch(e){}}}catch(e){}}}catch(e){} try{window.close();}catch(e){} }');
            win.document.write('window.onafterprint=function(){done();};');
            // Do NOT close on initial focus; only after printing was initiated.
            win.document.write('window.addEventListener("focus", function(){try{if(printed){setTimeout(done, 150);}}catch(e){}});');
            win.document.write('function doPrint(){ if(printed) return; printed=true; try{window.focus();}catch(e){} try{window.print();}catch(e){} }');
            win.document.write('window.__setPdf=function(src){');
            win.document.write('try{document.getElementById("loading").style.display="none";}catch(e){}');
            // Prefer object (native viewer). If it fails, embed/iframe can still show.
            win.document.write('try{var o=document.getElementById("pdfObject"); if(o){o.data=src; o.style.display="block";}}catch(e){}');
            win.document.write('try{var e=document.getElementById("pdfEmbed"); if(e){e.src=src;}}catch(e){}');
            win.document.write('try{var f=document.getElementById("pdfFrame"); if(f){f.src=src; f.onload=function(){setTimeout(doPrint,300);};}}catch(e){}');
            // Timed fallbacks: PDFs can take time to render before print dialog appears.
            win.document.write('setTimeout(doPrint, 900); setTimeout(doPrint, 1800); setTimeout(doPrint, 2800);');
            win.document.write('};');
            win.document.write('window.__setError=function(msg){try{document.getElementById("loading").innerText=msg||"Failed to load invoice";}catch(e){}};');
            win.document.write('})();<\/script>');
            win.document.write('</body></html>');
            win.document.close();
          } catch (e) {}

          // Fetch the existing A4 layout as HTML using the current Bearer token (axios default header),
          // then inject it into the already-open popup so we can call window.print() reliably
          // (same pattern as the POS thermal invoice).
          axios.get(`sale_print_html/${id}`, {
            responseType: 'text',
            headers: { 'Content-Type': 'application/json' },
          }).then(response => {
            try {
              const html = typeof response.data === 'string'
                ? response.data
                : (response.request && typeof response.request.responseText === 'string'
                    ? response.request.responseText
                    : '');
              if (!html) {
                try { win.__setError && win.__setError('Empty invoice HTML'); } catch (_) {}
                return;
              }
              // Replace popup content with the A4 HTML and trigger print (mirrors index_sale.print_it)
              try {
                win.document.open();
                win.document.write(html);
                win.document.close();
              } catch (e) {
                try { win.__setError && win.__setError('Failed to render invoice'); } catch (_) {}
                return;
              }
              // Ensure the popup preview (screen) and print dialog use the same A4 sizing.
              // We do NOT modify the template content; we only inject extra CSS rules.
              try {
                const style = win.document.createElement('style');
                style.type = 'text/css';
                style.innerHTML =
                  '@media screen {' +
                  '  body { width: 210mm; margin: 0 auto; }' +
                  '}' +
                  '@media print {' +
                  '  @page { size: A4; margin: 10mm 15mm; }' +
                  '  body { width: auto; margin: 0; }' +
                  '}';
                (win.document.head || win.document.getElementsByTagName("head")[0]).appendChild(style);
              } catch (e) {}

              // Close/hide the preview popup after the user clicks Print or Cancel
              // (mirroring the thermal invoice flow).
              let closed = false;
              const closePreview = () => {
                if (closed) return;
                closed = true;
                try { win.close(); } catch (_) {}
              };
              // Browsers do not expose a reliable "print job finished" event.
              // `afterprint` fires when the print dialog closes (Print OR Cancel).
              // Heuristic:
              // - If the dialog closes almost immediately (< 800ms) we treat it as Cancel and close at once.
              // - Otherwise we assume "Print" and keep the preview a bit longer (2s) before closing.
              const CANCEL_THRESHOLD_MS = 800;
              const PRINT_CLOSE_DELAY_MS = 2000;
              // Track whether we actually initiated printing, and whether the popup lost focus
              // (print dialog typically causes a blur). This prevents premature closing.
              let printInitiated = false;
              let blurredAfterPrint = false;
              let printStartAt = 0;
              try {
                win.onafterprint = () => {
                  const elapsed = printStartAt ? (Date.now() - printStartAt) : 0;
                  const delay = elapsed > CANCEL_THRESHOLD_MS ? PRINT_CLOSE_DELAY_MS : 0;
                  setTimeout(() => {
                    try {
                      if (reloadAfterSale && window.__posReloadAfterA4Print) {
                        window.__posReloadAfterA4Print();
                      }
                    } catch (e) {}
                    closePreview();
                  }, delay);
                };
              } catch (e) {}
              // Fallback: some browsers don't fire afterprint; close only after a blur->focus cycle
              // that happens AFTER print() was called (i.e. after Print/Cancel dialog closes).
              try {
                const onBlur = () => {
                  try {
                    if (printInitiated) blurredAfterPrint = true;
                  } catch (e) {}
                };
                const onFocus = () => {
                  try {
                    if (!printInitiated || !blurredAfterPrint) return;
                  } catch (e) { return; }
                  const elapsed = printStartAt ? (Date.now() - printStartAt) : 0;
                  const delay = elapsed > CANCEL_THRESHOLD_MS ? PRINT_CLOSE_DELAY_MS : 0;
                  setTimeout(() => {
                    try {
                      if (reloadAfterSale && window.__posReloadAfterA4Print) {
                        window.__posReloadAfterA4Print();
                      }
                    } catch (e) {}
                    closePreview();
                  }, delay);
                };
                win.addEventListener && win.addEventListener('blur', onBlur);
                win.addEventListener && win.addEventListener('focus', onFocus);
              } catch (e) {}

              // Give browser a moment to render, then show system print dialog
              setTimeout(() => {
                try { win.focus(); } catch (_) {}
                try {
                  printInitiated = true;
                  printStartAt = Date.now();
                } catch (e) {}
                try { win.print(); } catch (_) {}
              }, 700);
            } catch (e) {
              try { win.__setError && win.__setError('Failed to load invoice'); } catch (_) {}
            }
          }).catch(() => {
            try { win.__setError && win.__setError('Failed to load invoice'); } catch (_) {}
          });
        }
        return;
      }

      // Default: thermal POS invoice (existing behavior)
      axios.get(`sales_print_invoice/${id}`).then(response => {
        this.invoice_pos.sale = response.data.sale || {};
        // Backward compatibility: ensure discount_Method defaults to '2' (fixed) if not present
        if (this.invoice_pos.sale && !this.invoice_pos.sale.discount_Method) {
          this.invoice_pos.sale.discount_Method = '2';
        }
        this.invoice_pos.details = response.data.details;
        this.invoice_pos.setting = response.data.setting;
        this.invoice_pos.symbol = response.data.symbol;
        this.invoice_pos.zatca_qr = response.data.zatca_qr;
        this.public_invoice_url = response.data.public_invoice_url || '';
        this.payments = response.data.payments;
        if (response.data.pos_settings) {
          // Merge with existing pos_settings to preserve defaults
          this.pos_settings = {
            ...this.pos_settings,
            ...response.data.pos_settings
          };
          // Convert integer values to boolean for proper condition checking
          if (typeof this.pos_settings.quick_add_customer === 'number') {
            this.pos_settings.quick_add_customer = this.pos_settings.quick_add_customer === 1;
          }
        }
        // Mirror index_sale behavior: show modal first, then optionally auto-print.
        // Auto-print is gated on awaitQrReady() so the print iframe snapshot
        // is taken AFTER canvas → <img> conversion — otherwise on mobile the
        // canvas pixels are lost on innerHTML clone and the QR prints blank.
        let autoPrintable = false;
        try {
          const rawPrintable = (this.pos_settings && this.pos_settings.is_printable !== undefined)
            ? this.pos_settings.is_printable
            : (response.data.pos_settings && response.data.pos_settings.is_printable !== undefined
                ? response.data.pos_settings.is_printable
                : 1);
          autoPrintable = (rawPrintable === true || rawPrintable === 1 || rawPrintable === '1');
        } catch (e) {}

        setTimeout(() => {
          try { this.$bvModal.show('Show_invoice'); } catch(e) {}
          this.$nextTick(() => {
            const qrReady = this.awaitQrReady();
            if (autoPrintable) {
              qrReady.then(() => {
                // Always go through print_pos so Direct Network Printing
                // is honored on mobile too. print_pos itself falls back to
                // print_pos_mobile when DNP is disabled / not applicable.
                try { this.print_pos(); } catch(_) {}
              });
            }
          });
        }, 500);
      });
    },
    onInvoiceModalShown() {
      try { this.renderZatcaQrPos(); } catch (e) {}
      try { this.renderInvoiceUrlQr(); } catch (e) {}
    },

    // Loads the qrcodejs library exactly once across all callers.
    // Returns a Promise that resolves when window.QRCode is available
    // (or all sources have been exhausted). Cached on the instance so
    // overlapping render calls share one in-flight script load.
    // Sanity-check window.QRCode by encoding a non-trivial string and
    // verifying the canvas actually has dark pixels. Detects the broken
    // minimal build previously shipped at /vendor/qrcode/qrcode.min.js
    // (its mapData was missing bit-index increments → all-white canvas).
    _qrLibIsWorking() {
      try {
        if (!window.QRCode) return false;
        if (this._qrLibVerified) return true; // memoized per instance
        const probe = document.createElement('div');
        probe.style.cssText = 'position:absolute;left:-9999px;top:-9999px;';
        document.body.appendChild(probe);
        try {
          new window.QRCode(probe, {
            text: 'https://example.com/test/abcdefghij',
            width: 32, height: 32,
            correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.L : 1
          });
          const cv = probe.querySelector('canvas');
          if (!cv) return false;
          const data = cv.getContext('2d').getImageData(0, 0, cv.width, cv.height).data;
          let darkPixels = 0;
          for (let i = 0; i < data.length; i += 4) {
            if (data[i] < 128 && data[i + 1] < 128 && data[i + 2] < 128) {
              if (++darkPixels > 16) break;
            }
          }
          this._qrLibVerified = darkPixels > 16;
          return this._qrLibVerified;
        } finally {
          try { document.body.removeChild(probe); } catch (e) {}
        }
      } catch (e) { return false; }
    },

    loadQRCodeLib() {
      if (window.QRCode && this._qrLibIsWorking()) return Promise.resolve();
      // window.QRCode may be the old broken local lib cached from a
      // previous page visit — discard it so a known-good source replaces it.
      if (window.QRCode) {
        try { delete window.QRCode; } catch (e) { window.QRCode = undefined; }
        this._qrLibVerified = false;
      }
      if (this._qrLoaderPromise) return this._qrLoaderPromise;
      const tryLoad = (src, next) => {
        const s = document.createElement('script');
        s.src = src;
        s.onload = () => { if (window.QRCode) next(true); else next(false); };
        s.onerror = () => next(false);
        document.head.appendChild(s);
      };
      // The local /vendor/qrcode/qrcode.min.js previously shipped a broken
      // minimal build (missing bit-index increments in mapData → blank
      // canvas). We replaced it with the full davidshimjs/qrcodejs
      // library. The cache-buster forces mobile browsers to fetch the new
      // version instead of the cached broken one.
      const localQrUrl = '/vendor/qrcode/qrcode.min.js?v=full-3';
      // After each load attempt, validate the library actually produces QR
      // pixels — otherwise fall through to the next source. Catches a
      // corrupted CDN response or any future broken minimal build.
      const validateOrFalse = () => {
        if (!window.QRCode) return false;
        this._qrLibVerified = false;
        return this._qrLibIsWorking();
      };
      const discardBroken = () => {
        try { delete window.QRCode; } catch (e) { window.QRCode = undefined; }
        this._qrLibVerified = false;
      };
      this._qrLoaderPromise = new Promise((resolve) => {
        tryLoad('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', (ok) => {
          if (ok && validateOrFalse()) return resolve();
          if (window.QRCode) discardBroken();
          tryLoad(localQrUrl, (ok2) => {
            if (ok2 && validateOrFalse()) return resolve();
            if (window.QRCode) discardBroken();
            tryLoad('/assets_setup/js/qrcode.js', () => resolve());
          });
        });
      }).then(() => {
        // Allow a future retry if no working library was ever obtained.
        if (!window.QRCode) { this._qrLoaderPromise = null; return; }
        // Runtime-patch QRCode.prototype.makeImage to remove the legacy
        // Android version guard. The original davidshimjs/qrcodejs (same
        // copy ships on the cdnjs CDN) skips makeImage() when its UA regex
        // `Android (\d\.\d)` does not match — Android 7+ reports e.g.
        // `Android 13` (no decimal), so `_android` stays the boolean `true`,
        // `true >= 3` coerces to `1 >= 3` which is false, and the receipt
        // <img> is never given a data-URL src. Result: blank QR on every
        // modern Android browser. We always emit the <img>; data URIs are
        // universally supported on every browser this app runs on.
        try {
          if (window.QRCode && window.QRCode.prototype) {
            window.QRCode.prototype.makeImage = function () {
              if (this._oDrawing && typeof this._oDrawing.makeImage === 'function') {
                this._oDrawing.makeImage();
              }
            };
          }
        } catch (e) {}
      });
      return this._qrLoaderPromise;
    },

    // Returns a Promise that resolves when the invoice QR <img> is in the DOM
    // (or when there is nothing to render). Callers that intend to clone
    // #invoice-POS via innerHTML for printing must await this — canvas pixel
    // data is lost on innerHTML clone, only the <img> data URL survives.
    renderInvoiceUrlQr() {
      return new Promise((resolve) => {
        try {
          if (this.pos_settings && Number(this.pos_settings.show_barcode) === 0) return resolve();
          if (!this.invoice_pos || !this.invoice_pos.sale || !this.invoice_pos.sale.Ref) return resolve();
          if (!this.$refs.invoiceUrlQr) return resolve();
          const text = String(this.invoiceBarcodeUrl || this.invoice_pos.sale.Ref || '');
          if (!text) return resolve();

          // Per-mount render token: each call wins, older pending draws bail.
          this._invoiceQrToken = (this._invoiceQrToken || 0) + 1;
          const myToken = this._invoiceQrToken;

          this.loadQRCodeLib().then(() => {
            if (myToken !== this._invoiceQrToken) return resolve();
            if (!window.QRCode) return resolve();
            const m = this.$refs.invoiceUrlQr;
            if (!m) return resolve();
            m.innerHTML = '';
            try { m.setAttribute('title', text); } catch (e) {}
            try {
              new window.QRCode(m, {
                text,
                width: 140,
                height: 140,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.L : undefined
              });
            } catch (e1) {
              try { new window.QRCode(m, text); } catch (e2) {}
            }
            setTimeout(() => {
              if (myToken !== this._invoiceQrToken) return resolve();
              try { this.ensureQrImg(m); } catch (e) {}
              resolve();
            }, 150);
          }).catch(() => resolve());
        } catch (e) { resolve(); }
      });
    },

    // Ensure the QR mount contains an <img> (data URL) — qrcode.js' CDN build
    // emits one already, but the local minimal build only emits a <canvas>,
    // and a <canvas>'s pixel data is lost when the receipt is cloned via
    // innerHTML into the print popup. The <img> survives that clone.
    ensureQrImg(mount) {
      if (!mount) return;
      let img = null;
      let canvas = null;
      try { img = mount.querySelector('img'); } catch (e) {}
      try { canvas = mount.querySelector('canvas'); } catch (e) {}
      // Always derive the <img> src from the canvas synchronously. qrcode.js'
      // CDN build sets img.src asynchronously after Image().onload, which on
      // slower mobile devices can leave the <img> with an empty src when the
      // print iframe snapshots #invoice-POS via innerHTML — producing a blank
      // QR on the printed receipt while the on-screen modal eventually shows
      // it correctly.
      let dataUrl = '';
      if (canvas) {
        try { dataUrl = canvas.toDataURL('image/png'); } catch (e) {}
      }
      if (!img && dataUrl) {
        try {
          img = document.createElement('img');
          img.alt = 'QR';
          mount.appendChild(img);
        } catch (e) {}
      }
      if (img) {
        try {
          if (dataUrl) img.setAttribute('src', dataUrl);
          img.style.display = 'block';
          img.style.marginLeft = 'auto';
          img.style.marginRight = 'auto';
        } catch (e) {}
      }
    },

    // Returns a Promise — see renderInvoiceUrlQr for why callers must await
    // this before snapshotting #invoice-POS for the print iframe.
    renderZatcaQrPos() {
      return new Promise((resolve) => {
        try {
          if (!this.invoice_pos || !this.invoice_pos.setting || !this.invoice_pos.setting.zatca_enabled || !this.invoice_pos.zatca_qr) return resolve();
          if (!this.$refs.zatcaQrcodePos) return resolve();
          const text = String(this.invoice_pos.zatca_qr || '');
          if (!text) return resolve();

          this._zatcaQrToken = (this._zatcaQrToken || 0) + 1;
          const myToken = this._zatcaQrToken;

          this.loadQRCodeLib().then(() => {
            if (myToken !== this._zatcaQrToken) return resolve();
            if (!window.QRCode) return resolve();
            const m = this.$refs.zatcaQrcodePos;
            if (!m) return resolve();
            m.innerHTML = '';
            try { m.setAttribute('title', text); } catch (e) {}
            try {
              new window.QRCode(m, {
                text,
                width: 180,
                height: 180,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.L : undefined
              });
            } catch (e1) {
              try { new window.QRCode(m, text); } catch (e2) {}
            }
            this.zatcaRenderedPos = true;
            setTimeout(() => {
              if (myToken !== this._zatcaQrToken) return resolve();
              if (m && !m.childNodes.length && window.QRCode) {
                try { new window.QRCode(m, text); } catch (e) {}
              }
              try { this.ensureQrImg(m); } catch (e) {}
              resolve();
            }, 150);
          }).catch(() => resolve());
        } catch (e) { resolve(); }
      });
    },

    // Resolve once both QR mounts (Invoice URL + ZATCA) have an <img> in the
    // DOM. Used to gate auto-print on mobile so the iframe snapshot is not
    // taken before canvas → <img> conversion completes.
    awaitQrReady() {
      const a = (() => { try { return this.renderInvoiceUrlQr(); } catch (e) { return Promise.resolve(); } })();
      const b = (() => { try { return this.renderZatcaQrPos(); } catch (e) { return Promise.resolve(); } })();
      return Promise.all([
        Promise.resolve(a).catch(() => {}),
        Promise.resolve(b).catch(() => {})
      ]);
    },

    // ==================== CUSTOMER METHODS (from old_pos) ====================
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },
    Submit_Customer() {
      NProgress.start();
      NProgress.set(0.1);
      this.$refs.Create_Customer && this.$refs.Create_Customer.validate().then(success => {
        if (!success) {
          NProgress.done();
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Create_Client();
        }
      }).catch(() => {
        // Fallback when ref not present in new design
        NProgress.done();
      });
    },
    Create_Client() {
      axios
        .post("clients", {
          name: this.client.name,
          email: this.client.email,
          phone: this.client.phone,
          tax_number: this.client.tax_number,
          country: this.client.country,
          city: this.client.city,
          adresse: this.client.adresse,
          is_royalty_eligible: this.client.is_royalty_eligible
        })
        .then(response => {
          NProgress.done();
          const newClient = response.data;
          this.clients.push({
            id: newClient.id,
            name: newClient.name,
          });
          this.selectedClientId = newClient.id;
          this.client_name = newClient.name;
          this.onClientSelected(newClient.id);
          this.makeToast(
            "success",
            this.$t("Successfully_Created"),
            this.$t("Success")
          );
          this.Get_Client_Without_Paginate();
          this.$bvModal.hide("New_Customer");
        })
        .catch(() => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },
    Submit_Quick_Add_Customer() {
      NProgress.start();
      NProgress.set(0.1);
      this.SubmitProcessing = true;
      this.$refs.Quick_Add_Customer_Form && this.$refs.Quick_Add_Customer_Form.validate().then(success => {
        if (!success) {
          NProgress.done();
          this.SubmitProcessing = false;
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
          return;
        }
        axios
          .post("clients", {
            name: this.client.name,
            email: this.client.email || '',
            phone: this.client.phone || '',
            tax_number: this.client.tax_number || '',
            country: this.client.country || '',
            city: this.client.city || '',
            adresse: this.client.adresse || '',
            is_royalty_eligible: this.client.is_royalty_eligible || false
          })
          .then(response => {
            const newClient = response.data;

            // If there are custom field values from the quick-add form, persist them
            const clientId = newClient.id || newClient.client?.id;
            const hasCustoms = clientId && this.quickAddCustomFieldValues && Object.keys(this.quickAddCustomFieldValues).length > 0;

            const afterCustoms = () => {
              NProgress.done();
              this.SubmitProcessing = false;
              this.clients.push({
                id: newClient.id,
                name: newClient.name,
                phone: newClient.phone || '',
              });
              this.selectedClientId = newClient.id;
              this.client_name = newClient.name;
              this.onClientSelected(newClient.id);
              this.makeToast(
                "success",
                this.$t("Successfully_Created"),
                this.$t("Success")
              );
              this.Get_Client_Without_Paginate();
              this.$bvModal.hide("Quick_Add_Customer");
              this.reset_Form_client();
              this.quickAddCustomFieldValues = {};
            };

            if (hasCustoms) {
              axios.post("custom-field-values", {
                entity_type: "App\\Models\\Client",
                entity_id: clientId,
                values: this.quickAddCustomFieldValues
              }).then(afterCustoms).catch(() => afterCustoms());
            } else {
              afterCustoms();
            }
          })
          .catch(() => {
            NProgress.done();
            this.SubmitProcessing = false;
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          });
      });
    },
    New_Client() {
      this.reset_Form_client();
      this.$bvModal.show("New_Customer");
    },
    Quick_Add_Client() {
      this.reset_Form_client();
      this.$bvModal.show("Quick_Add_Customer");
    },
    reset_Form_client() {
      this.client = {
        id: "",
        name: "",
        email: "",
        phone: "",
        country: "",
        city: "",
        tax_number: "",
        adresse: "",
        is_royalty_eligible: false
      };
    },
    Get_Client_Without_Paginate() {
      axios
        .get("get_clients_without_paginate")
        .then(({ data }) => (this.clients = data));
    },

    // ==================== SALES SNAPSHOT (from old_pos) ====================
    get_today_sales() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("get_today_sales")
        .then(response => {
          this.today_sales = response.data;
          setTimeout(() => {
            this.$bvModal.show("modal_today_sales");
            NProgress.done();
          }, 1000);
        })
        .catch(() => {});
    },

    // ==================== CATEGORIES DRAWER ====================
    selectCategory(id) {
      this.category_id = (id != null) ? id : '';
      this.catDrawerOpen = false;
      this.catDrawerSearch = '';
      this.getProducts();
    },
    selectBrand(id) {
      this.brand_id = (id != null) ? id : '';
      this.brandDrawerOpen = false;
      this.brandDrawerSearch = '';
      this.getProducts();
    },
    selectWarehouse(id) {
      this.sale.warehouse_id = (id != null) ? id : null;
      this.whDrawerOpen = false;
      this.whDrawerSearch = '';
      this.Selected_Warehouse(this.sale.warehouse_id);
    },
    selectCustomer(id) {
      this.selectedClientId = (id != null) ? id : '';
      this.custDrawerOpen = false;
      this.custDrawerSearch = '';
      this.onClientSelected(this.selectedClientId);
    },
    catInitial(name) {
      const s = (name || '?').trim();
      return s ? s.charAt(0).toUpperCase() : '?';
    },
    // Deterministic color per category id so the avatar is stable across renders
    catColor(id) {
      const palette = [
        'linear-gradient(135deg,#6f53d9,#8a6cf0)',
        'linear-gradient(135deg,#10b981,#34d399)',
        'linear-gradient(135deg,#f59e0b,#fbbf24)',
        'linear-gradient(135deg,#3b82f6,#60a5fa)',
        'linear-gradient(135deg,#ec4899,#f472b6)',
        'linear-gradient(135deg,#0ea5e9,#38bdf8)',
        'linear-gradient(135deg,#14b8a6,#2dd4bf)',
        'linear-gradient(135deg,#ef4444,#f87171)',
      ];
      const n = Number(id) || 0;
      return palette[Math.abs(n) % palette.length];
    },

    // ==================== POS SETTINGS MODAL ====================
    open_pos_settings_modal() {
      this.ps_loading = true;
      this.$bvModal.show("modal_pos_settings");
      // Refresh from server so the modal always shows authoritative state
      // (and so we have the row id needed for the PUT request).
      axios.get("get_pos_Settings_api")
        .then(response => {
          if (response && response.data && response.data.pos_settings) {
            this.pos_settings = {
              ...this.pos_settings,
              ...response.data.pos_settings,
            };
            // Normalize integer 0/1 → boolean for toggles
            const boolKeys = [
              'quick_add_customer','barcode_scanning_sound','show_product_images',
              'show_stock_quantity','enable_hold_sales','enable_customer_points',
              'show_categories','show_brands','allow_overselling','cash_drawer_auto_open',
            ];
            boolKeys.forEach(k => {
              if (typeof this.pos_settings[k] === 'number') {
                this.pos_settings[k] = this.pos_settings[k] === 1;
              }
            });
          }
        })
        .catch(() => {
          this.makeToast('danger', this.$t('Failed_to_load_settings') || 'Failed to load settings', this.$t('Failed'));
        })
        .finally(() => {
          // Pull invoice_format from global settings as well
          axios.get("get_Settings_data")
            .then(r => {
              const settings = (r && r.data && r.data.settings) || {};
              const raw = settings.invoice_format;
              this.invoice_format = (typeof raw === 'string' && ['thermal','a4'].includes(raw)) ? raw : 'thermal';
            })
            .catch(() => {})
            .finally(() => { this.ps_loading = false; });
        });
    },

    Submit_Pos_Settings_Modal() {
      this.$refs.Submit_Pos_Settings_Modal.validate().then(success => {
        if (!success) {
          this.makeToast('danger', this.$t('Please_fill_the_form_correctly'), this.$t('Failed'));
          return;
        }
        this.Update_Pos_Settings_Modal();
      });
    },

    Update_Pos_Settings_Modal() {
      if (!this.pos_settings || !this.pos_settings.id) {
        this.makeToast('danger', this.$t('Failed_to_load_settings') || 'Failed to load settings', this.$t('Failed'));
        return;
      }
      this.ps_saving = true;
      NProgress.start();
      NProgress.set(0.1);
      axios.put("pos_settings/" + this.pos_settings.id, {
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
          products_per_page: this.pos_settings.products_per_page,
          receipt_layout: this.pos_settings.receipt_layout,
          quick_add_customer: this.pos_settings.quick_add_customer,
          barcode_scanning_sound: this.pos_settings.barcode_scanning_sound,
          show_product_images: this.pos_settings.show_product_images,
          show_stock_quantity: this.pos_settings.show_stock_quantity,
          enable_hold_sales: this.pos_settings.enable_hold_sales,
          enable_customer_points: this.pos_settings.enable_customer_points,
          show_categories: this.pos_settings.show_categories,
          show_brands: this.pos_settings.show_brands,
          allow_overselling: this.pos_settings.allow_overselling ? 1 : 0,
          cash_drawer_auto_open: this.pos_settings.cash_drawer_auto_open ? 1 : 0,
          cash_drawer_printer_name: this.pos_settings.cash_drawer_printer_name || null,
          invoice_format: this.invoice_format,
        })
        .then(() => {
          this.makeToast('success', this.$t('Successfully_Updated'), this.$t('Success'));
          try { Fire.$emit('Event_Pos_Settings'); } catch (e) {}
          this.$bvModal.hide('modal_pos_settings');
        })
        .catch(() => {
          this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'));
        })
        .finally(() => {
          this.ps_saving = false;
          NProgress.done();
        });
    },

    // ==================== SEARCH HELPERS (from old_pos) ====================
    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },
    SearchProduct(result) {
      if (this.load_product) {
        this.load_product = false;
        this.product = {};
        if (result.product_type == 'is_service') {
          this.product.quantity = 1;
          this.product.code = result.code;
        } else {
          this.product.code = result.code;
          this.product.current = result.qte_sale;
          this.product.fix_stock = result.qte;
          if (result.qte_sale < 1) {
            this.product.quantity = result.qte_sale;
          } else {
            this.product.quantity = 1;
          }
        }
        this.product.product_variant_id = result.product_variant_id;
        this.Get_Product_Details(result.id, result.product_variant_id, result);
        this.search_input = '';
        if (this.$refs && this.$refs.product_autocomplete) {
          this.$refs.product_autocomplete.value = "";
        }
        this.product_filter = [];
      } else {
        this.makeToast(
          "warning",
          this.$t("Please_wait_until_the_product_is_loaded"),
          this.$t("Warning")
        );
      }
    },
    // Try Direct Network Printing (RAW/port 9100) when enabled in settings.
    // Returns a Promise resolving to:
    //   { ok: true }                       — receipt dispatched to printer
    //   { ok: false, configured: false }   — DNP off or IP/saleId missing (fall back to browser print)
    //   { ok: false, configured: true, message } — DNP attempt failed (do NOT fall back; surface error)
    tryDirectNetworkPrint() {
      try {
        const ps = this.pos_settings || {};
        const enabled = ps.direct_network_printing === true || ps.direct_network_printing === 1 || ps.direct_network_printing === '1';
        const ip = (ps.network_printer_ip || '').toString().trim();
        const saleId = (this.invoice_pos && this.invoice_pos.sale && this.invoice_pos.sale.id) || null;

        if (!enabled || !ip || !saleId) return Promise.resolve({ ok: false, configured: false });

        return axios.post('direct_network_print/' + saleId)
          .then((response) => {
            if (response && response.data && response.data.success) {
              try {
                this.makeToast && this.makeToast(
                  'success',
                  this.$t ? this.$t('Sent_to_network_printer') || 'Sent to network printer' : 'Sent to network printer',
                  this.$t ? this.$t('Success') : 'Success'
                );
              } catch (_) {}
              return { ok: true };
            }
            const message = (response && response.data && response.data.message) || 'Network printer error';
            return { ok: false, configured: true, message };
          })
          .catch((err) => {
            const message = (err && err.response && err.response.data && err.response.data.message) || (err && err.message) || 'Network printer unreachable';
            return { ok: false, configured: true, message };
          });
      } catch (e) {
        return Promise.resolve({ ok: false, configured: false });
      }
    },
    print_pos(opts = {}) {
      // Direct Network Printing: when enabled, ALWAYS go through the network
      // printer and never fall back to the browser print popup. On failure
      // we surface a toast so the user knows printing didn't reach the
      // device. Only when DNP is disabled do we use the browser flow.
      const ps = this.pos_settings || {};
      const dnpEnabled = ps.direct_network_printing === true || ps.direct_network_printing === 1 || ps.direct_network_printing === '1';
      if (dnpEnabled && !opts.__skipDirectNetwork) {
        this.tryDirectNetworkPrint().then((result) => {
          if (result && result.ok) {
            if (this.onlineReloadAfterSale && typeof window !== 'undefined' && window.location && typeof window.location.reload === 'function') {
              this.onlineReloadAfterSale = false;
              this.onlineReloadModalVisible = false;
              try { window.location.reload(); } catch (e) {}
            }
            return;
          }
          if (result && result.configured) {
            try {
              this.makeToast && this.makeToast(
                'danger',
                (this.$t ? this.$t('Network_print_failed') || 'Network print failed' : 'Network print failed') + ': ' + (result.message || ''),
                this.$t ? this.$t('Failed') : 'Failed'
              );
            } catch (_) {}
            // Honor post-print reload even when print failed, to match prior UX.
            if (this.onlineReloadAfterSale && typeof window !== 'undefined' && window.location && typeof window.location.reload === 'function') {
              this.onlineReloadAfterSale = false;
              this.onlineReloadModalVisible = false;
              try { window.location.reload(); } catch (e) {}
            }
            return;
          }
          // DNP not actually configured (no IP / no saleId): fall back to browser.
          this.print_pos({ ...opts, __skipDirectNetwork: true });
        });
        return;
      }

      if (typeof window !== 'undefined' && (window.innerWidth <= 768 || window.matchMedia('(max-width: 768px)').matches)) {
        return this.print_pos_mobile();
      }
      // Try to grab existing DOM markup; if not present, we will not print here.
      var el = document.getElementById('invoice-POS');
      if (!el) { return; }
      // Gate the browser-print snapshot on QR readiness: canvas pixel data
      // is lost on innerHTML clone; only the data-URL <img> survives.
      this.awaitQrReady().then(() => {
        this._writePrintWindow(el);
      });
    },

    _writePrintWindow(el) {
      var divContents = el.innerHTML;
      var a = window.open('', '', 'height=600,width=480');
      if (!a) { return; }
      const bodyClass = this.currentReceiptPaperSizeClass || '';
      a.document.write('<html><head><link rel="stylesheet" href="/css/pos_print.css"></head><body class="' + bodyClass + '">');
      // Wrap in #invoice-POS so print CSS applies correctly and centers content
      a.document.write('<div id="invoice-POS">');
      a.document.write(divContents);
      a.document.write('</div></body></html>');
      a.document.close();
      const vm = this;
      const afterPrint = () => {
        try { window.removeEventListener('focus', afterPrint); } catch(e) {}
        try { a.close(); } catch(e) {}
        // If user chose to reload after this sale, do it once printing is done.
        if (vm.onlineReloadAfterSale && typeof window !== 'undefined' && window.location && typeof window.location.reload === 'function') {
          vm.onlineReloadAfterSale = false;
          vm.onlineReloadModalVisible = false;
          try { window.location.reload(); } catch (e) {}
        }
      };
      try { window.addEventListener('focus', afterPrint); } catch(e) {}
      try { a.onafterprint = afterPrint; } catch(e) {}
      setTimeout(() => {
        try { a.focus(); } catch(e) {}
        try { a.print(); } catch(e) { afterPrint(); }
      }, 300);
    },

    // Mobile-friendly print via hidden iframe (avoids popup blockers).
    // Gated on awaitQrReady() — the iframe receives a clone of #invoice-POS
    // via innerHTML, and a <canvas>'s pixel data does not survive that
    // clone. Only the data-URL <img> created by ensureQrImg() does, so we
    // must wait for that conversion before snapshotting.
    print_pos_mobile() {
      this.awaitQrReady().then(() => this._print_pos_mobile_impl());
    },

    _print_pos_mobile_impl() {
      try {
        const el = document.getElementById('invoice-POS');
        if (!el) { return; }
        const bodyClass = this.currentReceiptPaperSizeClass || '';
        // Inline QR styles as a safety net: if /css/pos_print.css is delayed
        // on mobile, these rules still hide the (blank, post-clone) <canvas>
        // and force the data-URL <img> to render at the correct size — so the
        // QR is never invisible just because the external stylesheet hasn't
        // arrived by the time print() fires.
        const inlineQrStyles = `
          #invoice-POS .receipt-qr-row{display:flex;flex-direction:row;flex-wrap:nowrap;justify-content:center;align-items:flex-start;gap:10px;width:100%;margin-top:8px;}
          #invoice-POS .receipt-qr-block{display:flex;flex-direction:column;align-items:center;flex:0 0 auto;width:100px;margin:0;}
          #invoice-POS .receipt-qr-title{font-weight:700;font-size:10px;letter-spacing:1px;text-transform:uppercase;text-align:center;margin:0 0 4px;line-height:1.2;display:block;width:100%;}
          #invoice-POS .receipt-qr-canvas{display:flex;justify-content:center;align-items:center;width:100px;height:100px;margin:0 auto;}
          #invoice-POS .receipt-qr-canvas img{display:block !important;margin:0 auto !important;width:100px !important;height:100px !important;max-width:100px !important;}
          #invoice-POS .receipt-qr-canvas canvas,#invoice-POS .receipt-qr-canvas table{display:none !important;}
        `;
        const html = `<!doctype html><html><head>
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <link rel="stylesheet" href="/css/pos_print.css" id="pos-print-css">
          <style>${inlineQrStyles}</style>
        </head><body class="${bodyClass}"><div id="invoice-POS">${el.innerHTML}</div></body></html>`;

        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);

        const doc = iframe.contentWindow ? iframe.contentWindow.document : (iframe.contentDocument || null);
        if (!doc) { return; }
        doc.open();
        doc.write(html);
        doc.close();

        const vm = this;
        let printed = false;
        const doPrint = () => {
          if (printed) return;
          printed = true;
          try { (iframe.contentWindow || iframe).focus(); } catch(e) {}
          try { (iframe.contentWindow || iframe).print(); } catch(e) {}
          setTimeout(() => {
            try { document.body.removeChild(iframe); } catch(_) {}
            // If user chose to reload after this sale, do it once mobile printing is done.
            if (vm.onlineReloadAfterSale && typeof window !== 'undefined' && window.location && typeof window.location.reload === 'function') {
              vm.onlineReloadAfterSale = false;
              vm.onlineReloadModalVisible = false;
              try { window.location.reload(); } catch (e) {}
            }
          }, 1500);
        };

        // Wait for the external stylesheet AND every <img> in the iframe (the
        // QR data-URL imgs and the logo) to be ready before printing. A flat
        // setTimeout fires before slow mobile networks finish loading the CSS,
        // which leaves the QR styled by browser defaults (and qrcode.js' CDN
        // <img> with `display:none`) — i.e. invisible on the printed page.
        const waitForReady = () => {
          const win = iframe.contentWindow;
          const idoc = win ? win.document : null;
          if (!idoc) { return doPrint(); }

          const cssReady = new Promise((resolve) => {
            const link = idoc.getElementById('pos-print-css');
            if (!link) return resolve();
            // Already loaded (cached): sheet is accessible.
            try { if (link.sheet) return resolve(); } catch (e) {}
            link.addEventListener('load', () => resolve(), { once: true });
            link.addEventListener('error', () => resolve(), { once: true });
          });

          const imgsReady = new Promise((resolve) => {
            const imgs = idoc.querySelectorAll('img');
            if (!imgs || !imgs.length) return resolve();
            let pending = 0;
            imgs.forEach((im) => {
              if (im.complete && im.naturalWidth > 0) return;
              pending++;
              const done = () => {
                pending--;
                if (pending <= 0) resolve();
              };
              im.addEventListener('load', done, { once: true });
              im.addEventListener('error', done, { once: true });
            });
            if (pending === 0) resolve();
          });

          // Hard ceiling so we always print, even if something hangs.
          const safety = new Promise((resolve) => setTimeout(resolve, 1500));

          Promise.race([
            Promise.all([cssReady, imgsReady]),
            safety
          ]).then(doPrint);
        };

        // Give the iframe a tick to commit the written document, then gate.
        setTimeout(waitForReady, 50);
      } catch(e) {}
    },
    // Print without relying on the modal being shown
    printInvoiceFromData(data) {
      try {
        const s = (data && data.sale) ? data.sale : {};
        const set = (data && data.setting) ? data.setting : {};
        const ps = (data && data.pos_settings) ? data.pos_settings : null;
        const symbol = (data && data.symbol) ? data.symbol : '';
        const details = Array.isArray(data && data.details) ? data.details : [];
        const payments = Array.isArray(data && data.payments) ? data.payments : [];
        const zatca_qr = (data && data.zatca_qr) ? data.zatca_qr : '';

        // Company setting fallback (needed so Address/Email/Phone show in offline receipts)
        let cachedSetting = null;
        try {
          cachedSetting = (Util && Util.offlinePos && Util.offlinePos.getCachedBootstrap)
            ? (Util.offlinePos.getCachedBootstrap() || {}).setting
            : null;
        } catch (e) {}
        if (!cachedSetting) {
          try {
            const raw = localStorage.getItem('pos_receipt_company_setting');
            if (raw) cachedSetting = JSON.parse(raw);
          } catch (e) {}
        }
        const normalizedSetting = { ...(cachedSetting || {}), ...(set || {}) };

        // Keep receipt behavior consistent: use the same modal/template for both online and offline.
        try {
          if (ps && typeof ps === 'object') {
            this.pos_settings = { ...(this.pos_settings || {}), ...ps };
          }
        } catch (e) {}

        try { 
          this.invoice_pos.sale = s || {};
          // Backward compatibility: ensure discount_Method defaults to '2' (fixed) if not present
          if (this.invoice_pos.sale && !this.invoice_pos.sale.discount_Method) {
            this.invoice_pos.sale.discount_Method = '2';
          }
        } catch (e) {}
        try { this.invoice_pos.details = details; } catch (e) {}
        try { this.invoice_pos.setting = normalizedSetting || {}; } catch (e) {}
        try { this.$set(this.invoice_pos, 'symbol', symbol); } catch (e) { try { this.invoice_pos.symbol = symbol; } catch(_) {} }
        try { this.invoice_pos.zatca_qr = zatca_qr; } catch (e) {}
        try { this.public_invoice_url = (data && data.public_invoice_url) ? data.public_invoice_url : ''; } catch (e) {}
        try { this.payments = payments; } catch (e) {}

        // Respect "Print Invoice automatically" POS setting for offline / data-driven printing.
        // If auto-print is disabled, just show the invoice modal and let the user print manually.
        let autoPrintable = true;
        try {
          const rawPrintable =
            (this.pos_settings && this.pos_settings.is_printable !== undefined)
              ? this.pos_settings.is_printable
              : (ps && ps.is_printable !== undefined
                  ? ps.is_printable
                  : 1); // default enabled
          autoPrintable = (rawPrintable === true || rawPrintable === 1 || rawPrintable === '1');
        } catch (e) {
          autoPrintable = true;
        }

        const doPrint = () => {
          try { this.print_pos({ reloadAfterPrint: false }); } catch (e) {}
        };

        // If auto-print is disabled, just ensure the invoice modal/DOM is available and skip printing.
        if (!autoPrintable) {
          try { this.$bvModal && this.$bvModal.show && this.$bvModal.show('Show_invoice'); } catch(e) {}
          // Still kick off QR rendering so the on-screen modal shows the codes.
          try { this.$nextTick(() => { this.awaitQrReady(); }); } catch (e) {}
        } else {
          // Wait for Vue to render invoice values into #invoice-POS, then for
          // QR canvas → <img> conversion to finish, before printing. The
          // print iframe snapshots innerHTML, and a <canvas>'s pixel data
          // does not survive that clone — only the data-URL <img> does.
          try {
            this.$nextTick(() => {
              const el = (typeof document !== 'undefined') ? document.getElementById('invoice-POS') : null;
              if (!el) {
                // As a fallback, show the modal once so its DOM is guaranteed to exist, then print.
                try { this.$bvModal && this.$bvModal.show && this.$bvModal.show('Show_invoice'); } catch(e) {}
                this.$nextTick(() => {
                  this.awaitQrReady().then(doPrint);
                });
                return;
              }
              this.awaitQrReady().then(doPrint);
            });
          } catch (e) {
            doPrint();
          }
        }
      } catch(e) {
        // In offline mode, do not reload the main page; just ignore print failures.
      }
    },

    // ==================== DATA INITIALIZATION ====================
    GetElementsPos() {
      axios
        .get("pos/data_create_pos")
        .then(response => {
          this.clients = response.data.clients;
          this.accounts = response.data.accounts;
          this.warehouses = response.data.warehouses;
          this.categories = response.data.categories;
          this.brands = response.data.brands;
          this.payment_methods = response.data.payment_methods;
          this.default_account_id = response.data.default_account_id ?? null;
          this.default_payment_method_id = response.data.default_payment_method_id ?? null;
          this.sale.warehouse_id = response.data.defaultWarehouse;
          this.selectedClientId = response.data.defaultClient;
          this.client_name = response.data.default_client_name;
          this.clientIsEligible = response.data.default_client_eligible === true || response.data.default_client_eligible === 1;
          this.selectedClientPoints = this.clientIsEligible ? parseFloat(response.data.default_client_points) : 0;
          this.point_to_amount_rate = response.data.point_to_amount_rate;
          
          // Set default tax from settings
          if (response.data.default_tax !== undefined && response.data.default_tax !== null) {
            this.sale.tax_rate = parseFloat(response.data.default_tax) || 0;
            this.default_tax   = parseFloat(response.data.default_tax) || 0;
            this.CalculTotal();
          }

          this.product_perPage = response.data.products_per_page;
          this.languages_available = response.data.languages_available;

          // Hydrate company/receipt header info (also used for offline printing)
          try {
            if (response.data && response.data.setting) {
              const merged = { ...(this.invoice_pos && this.invoice_pos.setting ? this.invoice_pos.setting : {}), ...response.data.setting };
              this.invoice_pos.setting = merged;
              // Cache preferred invoice format for POS printing
              if (merged && typeof merged.invoice_format === 'string' && ['thermal', 'a4'].includes(merged.invoice_format)) {
                this.invoice_format = merged.invoice_format;
              } else {
                this.invoice_format = 'thermal';
              }
              // Cache for offline usage even if the page later goes offline
              try { localStorage.setItem('pos_receipt_company_setting', JSON.stringify(merged)); } catch (e) {}
            }
          } catch (e) {}

          // Ensure we always have a currency symbol fallback for receipts
          try {
            const sym = (this.currentUser && this.currentUser.currency) ? this.currentUser.currency : '';
            if (!this.invoice_pos.symbol) {
              try { this.$set(this.invoice_pos, 'symbol', sym); } catch (e) { this.invoice_pos.symbol = sym; }
            }
          } catch (e) {}

          // Load POS settings if available
          if (response.data.pos_settings) {
            this.pos_settings = response.data.pos_settings;
            // Convert integer values (0/1) to boolean for proper condition checking
            if (typeof this.pos_settings.quick_add_customer === 'number') {
              this.pos_settings.quick_add_customer = this.pos_settings.quick_add_customer === 1;
            }
            if (typeof this.pos_settings.barcode_scanning_sound === 'number') {
              this.pos_settings.barcode_scanning_sound = this.pos_settings.barcode_scanning_sound === 1;
            }
            if (typeof this.pos_settings.show_product_images === 'number') {
              this.pos_settings.show_product_images = this.pos_settings.show_product_images === 1;
            }
            if (typeof this.pos_settings.show_stock_quantity === 'number') {
              this.pos_settings.show_stock_quantity = this.pos_settings.show_stock_quantity === 1;
            }
            if (typeof this.pos_settings.enable_hold_sales === 'number') {
              this.pos_settings.enable_hold_sales = this.pos_settings.enable_hold_sales === 1;
            }
            if (typeof this.pos_settings.enable_customer_points === 'number') {
              this.pos_settings.enable_customer_points = this.pos_settings.enable_customer_points === 1;
            }
            if (typeof this.pos_settings.show_categories === 'number') {
              this.pos_settings.show_categories = this.pos_settings.show_categories === 1;
            }
            if (typeof this.pos_settings.show_brands === 'number') {
              this.pos_settings.show_brands = this.pos_settings.show_brands === 1;
            }
          }
          this.getProducts();
          this.paginate_Brands(this.brand_perPage, 0);
          this.paginate_Category(this.category_perPage, 0);
          this.stripe_key = response.data.stripe_key;
          // Cache bootstrap payload for offline usage
          try {
            if (Util && Util.offlinePos && Util.offlinePos.cacheBootstrap) {
              Util.offlinePos.cacheBootstrap(response.data);
            }
          } catch (e) {}
          this.isLoading = false;
        })
        .catch(() => {
          // Offline/failed bootstrap: hydrate from cached data where possible
          try {
            const cached = Util && Util.offlinePos && Util.offlinePos.getCachedBootstrap
              ? Util.offlinePos.getCachedBootstrap()
              : null;

            if (cached) {
              this.clients = cached.clients || [];
              this.accounts = cached.accounts || [];
              this.warehouses = cached.warehouses || [];
              this.categories = cached.categories || [];
              this.brands = cached.brands || [];
              this.payment_methods = cached.payment_methods || [];
              this.default_account_id = cached.default_account_id ?? null;
              this.default_payment_method_id = cached.default_payment_method_id ?? null;

              if (!this.sale.warehouse_id && cached.defaultWarehouse) {
                this.sale.warehouse_id = cached.defaultWarehouse;
              }
              if (!this.selectedClientId && cached.defaultClient) {
                this.selectedClientId = cached.defaultClient;
              }
              if (!this.client_name && cached.default_client_name) {
                this.client_name = cached.default_client_name;
              }

              this.clientIsEligible = cached.default_client_eligible === true || cached.default_client_eligible === 1;
              this.selectedClientPoints = this.clientIsEligible
                ? parseFloat(cached.default_client_points || 0)
                : 0;
              this.point_to_amount_rate = cached.point_to_amount_rate || this.point_to_amount_rate;

              if (cached.default_tax !== undefined && cached.default_tax !== null) {
                this.sale.tax_rate = parseFloat(cached.default_tax) || 0;
                this.default_tax = parseFloat(cached.default_tax) || 0;
                this.CalculTotal();
              }

              if (cached.products_per_page) {
                this.product_perPage = cached.products_per_page;
              }
              if (Array.isArray(cached.languages_available)) {
                this.languages_available = cached.languages_available;
              }
              if (cached.stripe_key) {
                this.stripe_key = cached.stripe_key;
              }

              // Hydrate cached company/receipt header info for offline receipts
              try {
                const cachedSetting = cached.setting || null;
                let setting = cachedSetting;
                if (!setting) {
                  try {
                    const raw = localStorage.getItem('pos_receipt_company_setting');
                    if (raw) setting = JSON.parse(raw);
                  } catch (e) {}
                }
                if (setting) {
                  this.invoice_pos.setting = { ...(this.invoice_pos && this.invoice_pos.setting ? this.invoice_pos.setting : {}), ...setting };
                }
              } catch (e) {}

              // Ensure receipt symbol exists offline too
              try {
                const sym = (this.currentUser && this.currentUser.currency) ? this.currentUser.currency : '';
                if (!this.invoice_pos.symbol) {
                  try { this.$set(this.invoice_pos, 'symbol', sym); } catch (e) { this.invoice_pos.symbol = sym; }
                }
              } catch (e) {}

              this.paginate_Brands(this.brand_perPage, 0);
              this.paginate_Category(this.category_perPage, 0);

              if (this.sale.warehouse_id) {
                // This call will fall back to cached snapshots in offline mode
                this.getProducts();
              } else {
                this.productsReady = true;
              }
            } else {
              this.productsReady = true;
            }
          } catch (e) {
            this.productsReady = true;
          }
          this.isLoading = false;
        });
    },
    onModernPaymentSuccess(evt) {
      // For ONLINE sales through ModernPaymentModal, the sale response carries
      // authoritative stock for the items just sold. Patch it into the grid
      // immediately so the cashier sees correct quantities without waiting
      // for the next delta-sync tick.
      try {
        if (evt && !evt.offline) {
          this.applyStockUpdate(evt.updated_stock, evt.server_time);
        }
      } catch (e) {}

      // If this was an offline-queued sale, build a local invoice and print it
      try {
        if (evt && evt.offline && evt.payload) {
          const payload = evt.payload;
          const now = new Date();
          const saleDate = now.toISOString().slice(0, 19).replace('T', ' ');
          // Generate an internal offline reference if needed, but do not print it on the receipt
          const offlineRef = evt.offlineId ? `OFF-${evt.offlineId}` : `OFF-${now.getTime()}`;

          // Resolve client & warehouse names from cached lists
          let clientName = this.client_name || '';
          try {
            const cId = payload.client_id || this.selectedClientId;
            const c = (this.clients || []).find(x => String(x.id) === String(cId));
            if (c && c.name) clientName = c.name;
          } catch (e2) {}

          let warehouseName = '';
          try {
            const wId = payload.warehouse_id || this.sale.warehouse_id;
            const w = (this.warehouses || []).find(x => String(x.id) === String(wId));
            if (w && w.name) warehouseName = w.name;
          } catch (e2) {}

          // Resolve seller name from current user (prefer name, then username, then email)
          let sellerName = '';
          try {
            if (this.currentUser) {
              sellerName =
                this.currentUser.name ||
                this.currentUser.username ||
                this.currentUser.email ||
                '';
            }
          } catch (e2) {}

          const sale = {
            // Do not set Ref so offline receipts have no "Ref: ..." line
            client_name: clientName,
            warehouse_name: warehouseName,
            discount: payload.discount || 0,
            taxe: payload.TaxNet || 0,
            tax_rate: payload.tax_rate || 0,
            shipping: payload.shipping || 0,
            GrandTotal: payload.GrandTotal || 0,
            paid_amount: (Array.isArray(payload.payments)
              ? payload.payments.reduce((s, p) => s + Number(p.amount || 0), 0)
              : 0),
            date: saleDate,
            seller_name: sellerName
          };

          // Map details into invoice shape
          const details = Array.isArray(payload.details) ? payload.details.map(d => ({
            name: d.name,
            quantity: d.quantity,
            unit_sale: d.unitSale || d.unit_sale || '',
            total: d.subtotal != null ? d.subtotal : (d.total != null ? d.total : (d.Net_price || 0) * (d.quantity || 0)),
            is_imei: d.is_imei,
            imei_number: d.imei_number
          })) : [];

          // Map payments into invoice shape
          const payments = Array.isArray(payload.payments) ? payload.payments.map(p => {
            const method = (this.payment_methods || []).find(m => String(m.id) === String(p.payment_method_id));
            return {
              payment_method: method ? { name: method.name } : null,
              montant: Number(p.amount || 0),
              change: 0
            };
          }) : [];

          // Fallback settings & POS print options (reuse latest online invoice/pos settings when available)
          const symbol = this.currentUser && this.currentUser.currency ? this.currentUser.currency : '';

          // Prefer full setting object from last loaded invoice (online), else fall back to currentUser logo
          const baseSetting = (this.invoice_pos && this.invoice_pos.setting &&
            (this.invoice_pos.setting.logo ||
             this.invoice_pos.setting.CompanyAdress ||
             this.invoice_pos.setting.email ||
             this.invoice_pos.setting.CompanyPhone))
            ? this.invoice_pos.setting
            : null;

          const setting = baseSetting || {
            logo: (this.currentUser && this.currentUser.logo) || '',
            CompanyAdress: '',
            email: '',
            CompanyPhone: '',
          };

          // Prefer live pos_settings (including note_customer/show_note) when available
          const ps = this.pos_settings && Object.keys(this.pos_settings).length
            ? this.pos_settings
            : {
                show_address: false,
                show_email: false,
                show_phone: false,
                show_customer: true,
                show_Warehouse: true,
                show_discount: true,
                show_tax: true,
                show_shipping: true,
                logo_size: 60,
                // In absence of explicit POS settings, still show a generic note
                show_note: true,
                note_customer: this.$t ? this.$t('Thank_you_for_your_business') : 'Thank you for your business'
              };

          this.printInvoiceFromData({
            sale,
            details,
            payments,
            setting,
            pos_settings: ps,
            symbol
          });
        }
      } catch (e) {}

      // Cash drawer auto-open: on cash payment, send ESC/POS kick via QZ Tray
      try {
        const payload = evt && evt.payload ? evt.payload : null;
        const payments = payload && Array.isArray(payload.payments) ? payload.payments : [];
        const ps = this.pos_settings || {};
        if (ps.cash_drawer_auto_open && payments.length > 0 && this.payment_methods && this.payment_methods.length) {
          const hasCash = payments.some(p => {
            const method = this.payment_methods.find(m => String(m.id) === String(p.payment_method_id));
            return method && method.name && String(method.name).toLowerCase().includes('cash');
          });
          if (hasCash) {
            const printerName = (ps.cash_drawer_printer_name && String(ps.cash_drawer_printer_name).trim()) || '';
            openCashDrawer(printerName).catch(() => {});
          }
        }
      } catch (e) {}

      // After successful payment via modal, refresh drafts if needed and reset
      if (this.draft_sale_id) {
        try { Fire.$emit('event_delete_draft_sale'); } catch(e) {}
        this.draft_sale_id = '';
      }
      try { this.Reset_Pos(); } catch(e) {}
      try { this.refreshOfflineSalesCount(); } catch(e) {}
    },
    // ---------- Offline & sync helpers ----------
    initOfflineStatus() {
      if (typeof window === 'undefined') {
        this.isOnline = true;
        this.offlineSyncInProgress = false;
        this.offlineLastSyncError = null;
        this.refreshOfflineSalesCount();
        return;
      }
      try {
        this.isOnline = window.navigator ? window.navigator.onLine !== false : true;
      } catch (e) {
        this.isOnline = true;
      }
      try {
        window.addEventListener('online', this.handleOnline);
        window.addEventListener('offline', this.handleOffline);
      } catch (e) {}
      // Some environments don't reliably dispatch online/offline events.
      // Poll navigator.onLine and trigger the same handlers on state changes.
      try {
        if (!this._posOnlineSignalPollTimer) {
          this._posOnlineSignalPollTimer = setInterval(() => {
            try {
              const browserOnline = !window.navigator || window.navigator.onLine !== false;
              if (browserOnline && !this.isOnline) {
                this.isOnline = true;
                // Ensure reconnect flow runs (ping + modal/sync gating).
                try { this.handleOnline(); } catch (e) {}
              } else if (!browserOnline && this.isOnline) {
                try { this.handleOffline(); } catch (e) { this.isOnline = false; }
              }
            } catch (e) {}
          }, 3000);
        }
      } catch (e) {}
      this.refreshOfflineSalesCount();
    },
    async handleOnline() {
      // When the browser reports that we're back online, check if there is an
      // active checkout. If so, we'll offer the user a non-blocking choice to
      // reload now or after completing the sale.
      const hadActiveCart = this.details && this.details.length > 0;

      // Keep UI "online" driven by the browser signal (fast UX), but confirm
      // backend reachability before showing the "Internet restored" modal or
      // performing reconnect actions (reload / sync triggers).
      this.isOnline = true;

      if (this._posOnlineProbeInProgress) return;
      this._posOnlineProbeInProgress = true;
      let confirmedOnline = true;
      try {
        confirmedOnline = await this.pingBackend();
      } catch (e) {
        confirmedOnline = false;
      } finally {
        this._posOnlineProbeInProgress = false;
      }

      if (!confirmedOnline) {
        return;
      }
      // When the cart is empty, we auto-sync offline sales in the background and
      // then reload the page afterwards (even if there were no pending sales).
      // We still let the global offline sync handler perform the actual API work.
      if (!hadActiveCart) {
        try {
          if (Util && Util.offlinePos && Util.offlinePos.getOfflineSales) {
            const queue = Util.offlinePos.getOfflineSales() || [];
            const pendingCount = queue.filter(
              s => s && (s.status === 'pending' || s.status === 'syncing')
            ).length;
            if (pendingCount > 0) {
              const msg = pendingCount === 1 
                ? (this.$t ? this.$t('pos.Syncing_offline_sales') : 'Syncing offline sales')
                : (this.$t ? `${this.$t('pos.Syncing_offline_sales')} (${pendingCount})` : `Syncing ${pendingCount} offline sales...`);
              this.makeToast && this.makeToast('info', msg, this.$t ? this.$t('Notice') : 'Notice');
              // Ask POS to reload after global offline sync completes successfully.
              this.reloadAfterOfflineSync = true;
            } else {
              // No pending offline sales: nothing to sync, so reload immediately.
              this.reloadAfterOfflineSync = false;
              if (typeof window !== 'undefined' && window.location && typeof window.location.reload === 'function') {
                try { window.location.reload(); } catch (e) {}
              }
            }
          }
        } catch (e) {}
      }
      // If there is an active checkout when the connection is restored, show a
      // non-blocking confirmation modal offering to reload now or after
      // completing the current sale.
      if (hadActiveCart) {
        this.onlineReloadModalVisible = true;
        this.onlineReloadAfterSale = false;
      }
      // Do NOT call this.trySyncOfflineSales() here; globalOfflineSync will run
      // the sync once per online event, which prevents duplicate submissions.
    },
    handleOffline() {
      this.isOnline = false;
      // Reset reachability so the next successful ping can trigger reconnect UX.
      this.backendReachable = false;
      try {
        const msg = this.$t ? this.$t('pos.Offline_Mode') : 'Offline mode enabled';
        this.makeToast && this.makeToast('warning', msg, this.$t ? this.$t('Warning') : 'Warning');
      } catch (e) {}
      // Any time we go offline, reset the online reload modal state.
      this.onlineReloadModalVisible = false;
      this.onlineReloadAfterSale = false;
      this.reloadAfterOfflineSync = false;
    },
    refreshOfflineSalesCount() {
      try {
        if (!Util || !Util.offlinePos || !Util.offlinePos.getOfflineSales) {
          this.offlineSalesCount = 0;
          return;
        }
        const list = Util.offlinePos.getOfflineSales() || [];
        // Only count sales that are still pending or actively syncing.
        this.offlineSalesCount = list.filter(s => s && (s.status === 'pending' || s.status === 'syncing')).length;
        // If there are no pending/syncing offline sales, clear any residual shadow stock
        if (this.offlineSalesCount === 0) {
          try {
            if (Util && Util.shadowStock && Util.shadowStock.clearAll) {
              Util.shadowStock.clearAll();
            }
          } catch (e2) {}
        }
      } catch (e) {
        this.offlineSalesCount = 0;
      }
    },
    // Handle result of auto/offline sync (both global auto-sync and local POS sync
    // call this so the feedback is consistent with clicking the offline sync button).
    handleAutoOfflineSyncResult(payload) {
      try {
        // Always refresh badge/count after any sync attempt
        this.refreshOfflineSalesCount();
      } catch (e) {}

      if (!payload || typeof this.makeToast !== 'function') return;
      const syncedCount = Number(payload.syncedCount || 0);
      const lastError = payload.lastError || null;

      if (syncedCount > 0) {
        const successMsg = syncedCount === 1
          ? '1 offline sale synced successfully'
          : `${syncedCount} offline sales synced successfully`;
        this.makeToast(
          'success',
          successMsg,
          this.$t ? this.$t('Success') : 'Success'
        );
        // If we are online again and there is an active cart, show the same
        // reload confirmation modal as on connection restore. This covers cases
        // where the browser `online` event is missed but sync succeeded.
        try {
          const hadActiveCart = this.details && this.details.length > 0;
          if (hadActiveCart && this.isOnline && !this.onlineReloadModalVisible) {
            this.onlineReloadAfterSale = false;
            this.onlineReloadModalVisible = true;
          }
        } catch (e) {}
        // If we came back online with an empty cart and requested an
        // auto-reload after offline sync, perform it now.
        if (this.reloadAfterOfflineSync && this.details && this.details.length === 0) {
          this.reloadAfterOfflineSync = false;
          if (typeof window !== 'undefined' && window.location && typeof window.location.reload === 'function') {
            try { window.location.reload(); } catch (e) {}
          }
        } else {
          // Offline sales that just flushed to the server decremented stock
          // on the backend. Proactively pull a delta so the grid reflects
          // those changes immediately (the periodic timer would eventually
          // catch up, but waiting ~30s is unnecessary here).
          try { this.syncProductsDelta(); } catch (e) {}
        }
      } else if (lastError) {
        const errDetail = String(lastError || '');
        // Some old/invalid offline records may produce low‑level validation
        // messages (e.g. "validation.required"). These are not actionable for
        // the cashier and the records are marked as "failed" and skipped on
        // future syncs, so we silently ignore them to avoid noisy toasts.
        const lower = errDetail.toLowerCase();
        const looksLikeValidationKey =
          lower.includes('validation.') ||
          lower.includes('validation_required') ||
          lower === 'validation.required';
        if (looksLikeValidationKey) {
          return;
        }

        const short = errDetail.slice(0, 200);
        const baseMsg = 'Failed to sync offline sales';
        const fullMsg = short ? `${baseMsg}: ${short}` : baseMsg;
        this.makeToast(
          'danger',
          fullMsg,
          this.$t ? this.$t('Failed') : 'Failed'
        );
      }
    },
    async trySyncOfflineSales() {
      if (this.offlineSyncInProgress) return;
      // Check online status defensively
      if (typeof window !== 'undefined') {
        try {
          if (window.navigator && window.navigator.onLine === false) {
            this.isOnline = false;
            return;
          }
        } catch (e) {}
      }

      // Confirm backend reachability before attempting any sync.
      // This prevents DevTools/flaky "online" events from triggering sync while
      // still effectively offline.
      try {
        const ok = await this.pingBackend();
        if (!ok) {
          return;
        }
      } catch (e) {
        return;
      }

      this.offlineSyncInProgress = true;
      // Notify UI (via global event bus) that POS offline sync has started
      try {
        if (typeof window !== 'undefined' && window.Fire && window.Fire.$emit) {
          window.Fire.$emit('offline-sync:start');
        }
      } catch (e) {}
      this.offlineLastSyncError = null;
      let syncedCount = 0;
      try {
        if (!Util || !Util.offlinePos || !Util.offlinePos.getOfflineSales) {
          return;
        }
        const queue = Util.offlinePos.getOfflineSales() || [];
        for (let i = 0; i < queue.length; i++) {
          const sale = queue[i];
          // Skip already-synced, failed or in-progress records
          if (!sale || !sale.payload || sale.status === 'synced' || sale.status === 'syncing' || sale.status === 'failed') continue;
          try {
            // Mark this sale as "syncing" in the shared offline queue so that
            // other sync workers (global/offline, other tabs) do not submit it
            // concurrently.
            try {
              if (Util && Util.offlinePos && Util.offlinePos.markSaleAsSyncing) {
                Util.offlinePos.markSaleAsSyncing(sale.id);
              }
            } catch (e) {}

            // Normalize payload to ensure required keys such as sale_unit_id exist
            const basePayload = sale.payload || {};
            const normalizedDetails = Array.isArray(basePayload.details)
              ? basePayload.details.map(d => ({
                  ...d,
                  sale_unit_id:
                    d && Object.prototype.hasOwnProperty.call(d, 'sale_unit_id')
                      ? d.sale_unit_id
                      : (d && d.sale_unit_id) || null,
                }))
              : basePayload.details;
            const payload = {
              ...basePayload,
              // Include offline_id so backend can optionally enforce idempotency
              offline_id: sale.id,
              details: normalizedDetails,
            };

            // Use absolute API path to avoid hitting SPA routes (e.g. /app/pos/create_pos)
            const response = await axios.post('/pos/create_pos', payload);
            if (response && response.data && response.data.success === true) {
              Util.offlinePos.markSaleAsSynced(sale.id, response.data.id);
              syncedCount++;
              // On success, clear shadow stock deductions for this sale so we don't double-subtract
              try {
                if (Util && Util.shadowStock && Util.shadowStock.revertDeductions) {
                  Util.shadowStock.revertDeductions(sale.id);
                }
              } catch (e) {}
            } else {
              Util.offlinePos.markSaleAsFailed(
                sale.id,
                'Invalid response from server',
                response && response.status
              );
              // Restore shadow stock for this failed sale
              try {
                if (Util && Util.shadowStock && Util.shadowStock.revertDeductions) {
                  Util.shadowStock.revertDeductions(sale.id);
                }
              } catch (e) {}
            }
          } catch (error) {
            const isNetworkError = !error.response || error.message === 'Network Error';
            if (typeof window !== 'undefined') {
              try {
                if (window.navigator && window.navigator.onLine === false) {
                  this.isOnline = false;
                }
              } catch (e) {}
            }
            if (isNetworkError && !this.isOnline) {
              // Still offline, stop and retry later
              break;
            }
            const msg =
              (error.response &&
                (error.response.data &&
                  (error.response.data.message || error.response.data.error))) ||
              error.message ||
              'Unknown error';
            Util.offlinePos.markSaleAsFailed(
              sale.id,
              msg,
              error.response && error.response.status
            );
            this.offlineLastSyncError = msg;
            // For non-network errors, rollback local shadow stock to keep UI consistent
            if (!isNetworkError) {
              try {
                if (Util && Util.shadowStock && Util.shadowStock.revertDeductions) {
                  Util.shadowStock.revertDeductions(sale.id);
                }
              } catch (e) {}
            }
          }
        }
      } finally {
        this.offlineSyncInProgress = false;
        this.refreshOfflineSalesCount();
        // Notify UI that POS offline sync has finished
        try {
          if (typeof window !== 'undefined' && window.Fire && window.Fire.$emit) {
            window.Fire.$emit('offline-sync:end', { syncedCount, lastError: this.offlineLastSyncError });
          }
        } catch (e) {}
        // Show same feedback as auto-sync handler
        this.handleAutoOfflineSyncResult({ syncedCount, lastError: this.offlineLastSyncError });
      }
    },
    syncOfflineSales() {
      if (!this.isOnline) {
        const msg = this.$t ? this.$t('pos.Offline_Mode') : 'You are currently offline';
        this.makeToast && this.makeToast('warning', msg, this.$t ? this.$t('Warning') : 'Warning');
        return;
      }
      this.trySyncOfflineSales();
    },
    // Ensure all non-service items have sufficient stock before proceeding to payment
    verifyAllItemsInStock() {
      // Overselling Control: when ON, payment is allowed regardless of stock.
      if (this.isOversellingAllowed) {
        return { ok: true, productName: null };
      }
      for (let i = 0; i < this.details.length; i++) {
        const d = this.details[i];
        if (d && d.product_type !== 'is_service') {
          const available = Number(d.current || 0);
          const qty = Number(d.quantity || 0);
          if (isNaN(available) || isNaN(qty) || qty > available) {
            return { ok: false, productName: d.name || d.code || 'item' };
          }
        }
      }
      return { ok: true, productName: null };
    },
    openModernPaymentModal() {
      // Guard: client and warehouse must be selected
      if (!this.selectedClientId) {
        const msg = this.$t ? this.$t('Select_Customer') : 'Please select a customer before paying.';
        this.makeToast && this.makeToast('warning', msg, this.$t ? this.$t('Warning') : 'Warning');
        return;
      }
      if (!this.sale || !this.sale.warehouse_id) {
        const msg = this.$t ? this.$t('SelectWarehouse') : 'Please select a warehouse before paying.';
        this.makeToast && this.makeToast('warning', msg, this.$t ? this.$t('Warning') : 'Warning');
        return;
      }
      // Guard: batch validation — every batch-tracked line must have a complete, valid batch allocation.
      const gate = this.payNowBatchGate;
      if (gate && gate.blocked) {
        this.makeToast('danger', gate.reason, this.$t ? this.$t('Failed') : 'Failed');
        return;
      }
      // Guard: stock validation before opening payment modal
      const stockCheck = this.verifyAllItemsInStock();
      if (!stockCheck.ok) {
        const msg = this.$t ? `${this.$t('InsufficientStock')} ${stockCheck.productName}` : `Insufficient stock for ${stockCheck.productName}`;
        this.makeToast('danger', msg, this.$t ? this.$t('Failed') : 'Failed');
        return;
      }
      // Guard: total payable must not be negative (zero allowed)
      if (Number(this.GrandTotal) < 0) {
        const msg = this.$t ? `${this.$t('pos.Total_Payable')} cannot be negative` : 'Total Payable cannot be negative';
        this.makeToast('warning', msg, this.$t ? this.$t('Warning') : 'Warning');
        return;
      }
      // Open modern payment modal with current sale data
      this.$refs.modernPaymentModal.openModal({
        amountDue: this.GrandTotal,
        reference: this.sale.Ref || "POS-" + new Date().getTime(),
        notes: this.selectedClientId ? `Payment for Customer #${this.selectedClientId}` : 'POS Payment'
      });
    },

    // F9 entry point: re-open the receipt modal for the most recent
    // completed sale. The shared print_pos() helper alone requires the
    // #invoice-POS DOM node to already exist, so this routes through
    // Invoice_POS() which fetches the sale and renders the modal first.
    print_last_receipt() {
      if (!this.last_sale_id) {
        try {
          this.makeToast && this.makeToast(
            'warning',
            this.$t ? (this.$t('No_Recent_Receipt') || 'No recent receipt to print') : 'No recent receipt to print',
            this.$t ? this.$t('Warning') : 'Warning'
          );
        } catch (e) {}
        return;
      }
      try { this.Invoice_POS(this.last_sale_id); } catch (e) {}
    },

    // ESC entry point (replaces the old direct Reset_Pos call). Opens a
    // confirmation modal so cashiers can choose to clear the cart or
    // hold it as a draft, instead of accidentally wiping work.
    confirmClearCart() {
      if (!this.details || !this.details.length) return;
      try {
        if (this.$bvModal && typeof this.$bvModal.show === 'function') {
          this.$bvModal.show('pos-confirm-clear-cart');
        }
      } catch (e) {}
    },
  },
  created() {
    // Clear cached POS data on page reload when online to avoid stale/outdated data
    // Fresh data will be fetched and cache rebuilt via GetElementsPos()
    // Only clear when online - when offline, preserve cache as it's needed for offline functionality
    try {
      // Check if we're online before clearing cache
      const isOnline = typeof window !== 'undefined' && window.navigator && window.navigator.onLine !== false;
      if (isOnline && Util && Util.offlinePos && Util.offlinePos.clearCache) {
        Util.offlinePos.clearCache();
      }
    } catch (e) {
      // Ignore errors during cache clearing
    }
    
    this.initOfflineStatus();
    // When browser signal flips POS to online, immediately confirm backend
    // reachability and run the same reconnect flow as the browser "online"
    // event. This avoids relying solely on the online event for modal/sync
    // gating logic.
    try {
      this.$watch('isOnline', (val, oldVal) => {
        // Only react to offline -> online transitions.
        if (val === true && oldVal === false) {
          // Avoid double work while handleOnline() itself is already probing.
          if (this._posOnlineProbeInProgress) return;
          try { this.handleOnline(); } catch (e) {}
        }
      });
    } catch (e) {}

    // If backend reachability flips from false -> true, show the reconnect modal
    // when there is an active cart, even if the browser `online` event was missed.
    try {
      this.$watch('backendReachable', (val, oldVal) => {
        if (val === true && oldVal !== true) {
          const hadActiveCart = this.details && this.details.length > 0;
          if (hadActiveCart && this.isOnline && !this.onlineReloadModalVisible) {
            try {
              this.$nextTick(() => {
                this.onlineReloadModalVisible = true;
                this.onlineReloadAfterSale = false;
              });
            } catch (e) {
              this.onlineReloadModalVisible = true;
              this.onlineReloadAfterSale = false;
            }
          }
        }
      });
    } catch (e) {}
    // Ensure offline sales badge is accurate immediately after POS refresh,
    // even before any user interaction.
    try {
      this.refreshOfflineSalesCount();
    } catch (e) {}

    this.GetElementsPos(); // This will fetch fresh data and rebuild the cache when online
    this.addPaymentLine();
    // Initialize warehouse options and sync selection once data is loaded
    this.$watch('warehouses', (ws) => {
      this.warehouseOptions = (ws || []).map(w => ({ value: w.id, text: w.name }));
      if (!this.registerForm.warehouse_id && this.sale && this.sale.warehouse_id) {
        this.registerForm.warehouse_id = this.sale.warehouse_id;
      }
      // Always check current register after initial data load
      this.refreshCurrentRegister();
    });
    // refresh register when warehouse changes
    this.$watch(() => this.sale.warehouse_id, () => {
      this.registerForm.warehouse_id = this.sale.warehouse_id || '';
      this.refreshCurrentRegister();
    });
    // Reset POS after successful payment from ModernPaymentModal
    if (this.$refs && this.$refs.modernPaymentModal) {
      try {
        this.$refs.modernPaymentModal.$on('payment-success', () => {
          this.Reset_Pos();
        });
      } catch(e) {}
    }
    Fire.$on("pay_now", () => {
      setTimeout(() => {
        // Guard: prevent opening legacy payment modal if total is negative
        if (Number(this.GrandTotal) < 0) {
          const msg = this.$t ? `${this.$t('pos.Total_Payable')} cannot be negative` : 'Total Payable cannot be negative';
          this.makeToast('warning', msg, this.$t ? this.$t('Warning') : 'Warning');
          // Complete the animation of the progress bar.
          NProgress.done();
          return;
        }
        this.paymentLines = [{
          amount:          parseFloat(this.GrandTotal.toFixed(2)),
          payment_method_id:       2,
        }];
        this.globalPaymentNote = '';
        this.selectedAccount= null; 
        this.$bvModal.show("Add_Payment");
        // Complete the animation of theprogress bar.
        NProgress.done();
      }, 500);
    });

    Fire.$on("event_delete_draft_sale", () => {
      // Calculate if current page would be empty after deletion
      const itemsOnCurrentPage = this.draft_sales.length;
      let pageToLoad = this.draft_sales_page;
      
      // If we're deleting the last item on a page that's not page 1, go to previous page
      if (itemsOnCurrentPage === 1 && this.draft_sales_page > 1) {
        pageToLoad = this.draft_sales_page - 1;
        this.draft_sales_page = pageToLoad;
      }
      
      this.get_Draft_Sales(pageToLoad);
      // Complete the animation of theprogress bar.
      setTimeout(() => NProgress.done(), 500);
    });

    // Listen for global auto-sync result so we can display the same
    // feedback as when the user clicks the offline sync button.
    try {
      if (typeof window !== 'undefined' && window.Fire && window.Fire.$on) {
        window.Fire.$on('offline-sync:auto-result', this.handleAutoOfflineSyncResult);
      }
    } catch (e) {}

    // ---------- Incremental stock delta sync ----------
    // Periodically ask the backend for just the product_warehouse rows that
    // changed since our last sync. Keeps stock coherent when other cashiers
    // sell the same items without re-fetching the whole catalog.
    try {
      if (typeof window !== 'undefined') {
        this._productsDeltaTimer = setInterval(() => {
          try { this.syncProductsDelta(); } catch (e) {}
        }, 30000);
        this._onProductsDeltaVisibility = () => {
          try {
            if (typeof document !== 'undefined' && document.visibilityState === 'visible') {
              this.syncProductsDelta();
            }
          } catch (e) {}
        };
        if (typeof document !== 'undefined' && document.addEventListener) {
          document.addEventListener('visibilitychange', this._onProductsDeltaVisibility);
        }
      }
    } catch (e) {}

  },
  beforeDestroy() {
    try {
      if (typeof document !== 'undefined' && document.documentElement) {
        document.documentElement.classList.remove('pos-active');
      }
    } catch (e) {}
    try {
      if (typeof window !== 'undefined') {
        window.removeEventListener('online', this.handleOnline);
        window.removeEventListener('offline', this.handleOffline);
      }
    } catch (e) {}
    try {
      if (this._posOnlineSignalPollTimer) {
        clearInterval(this._posOnlineSignalPollTimer);
        this._posOnlineSignalPollTimer = null;
      }
    } catch (e) {}
    // Clean up global auto-sync listener
    try {
      if (typeof window !== 'undefined' && window.Fire && window.Fire.$off) {
        window.Fire.$off('offline-sync:auto-result', this.handleAutoOfflineSyncResult);
      }
    } catch (e) {}
    // Stop the incremental stock delta sync timer / visibility listener.
    try {
      if (this._productsDeltaTimer) {
        clearInterval(this._productsDeltaTimer);
        this._productsDeltaTimer = null;
      }
    } catch (e) {}
    try {
      if (this._onProductsDeltaVisibility && typeof document !== 'undefined' && document.removeEventListener) {
        document.removeEventListener('visibilitychange', this._onProductsDeltaVisibility);
        this._onProductsDeltaVisibility = null;
      }
    } catch (e) {}
  }
};
</script>
<style scoped lang="scss">
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap');

/* ============================================
   REFINED CLASSIC — DESIGN TOKENS
   Sourced from POS.html visual reference.
   Scoped to .pos-codecanyon so they don't leak.
   ============================================ */
.pos-codecanyon {
  --accent: #6f53d9;
  --accent-soft: #ece9fb;
  --accent-tint: #f5f3fd;
  --accent-shadow: rgba(111, 83, 217, 0.32);
  --bg: #f7f7fb;
  --bg-deep: #f1f1f6;
  --surface: #ffffff;
  --soft: #f3f3f7;
  --line: #e6e6ec;
  --line-strong: #d8d8e0;
  --ink: #1f1f2c;
  --ink-2: #54546a;
  --ink-3: #8d8da0;
  --danger: #d64545;
  --success: #2fae5e;
  --success-soft: #eaf7ef;
  --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --font-mono: 'JetBrains Mono', 'SFMono-Regular', Menlo, Consolas, monospace;
}

// Color Palette & Typography
$color-bg-light: #f8f9fb;
$color-card-bg: #ffffff;
$color-text-primary: #1a1a2e;
$color-text-secondary: #6b7280;
$color-text-tertiary: #9ca3af;
$color-border-light: #e5e7eb;
$color-gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
$color-gradient-hover: linear-gradient(135deg, #5568d3 0%, #69408f 100%);
$color-success: #10b981;
$color-warning: #f59e0b;
$color-danger: #ef4444;

$font-family-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
$font-size-xs: 12px;
$font-size-sm: 14px;
$font-size-base: 16px;
$font-size-lg: 18px;
$font-size-xl: 20px;

$shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
$shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
$shadow-lg: 0 10px 32px rgba(0, 0, 0, 0.12);
$shadow-xl: 0 20px 48px rgba(0, 0, 0, 0.15);

$radius-sm: 6px;
$radius-md: 12px;
$radius-lg: 16px;

$transition-fast: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
$transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

.pos-codecanyon {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background: $color-bg-light;
  font-family: $font-family-primary;
  color: $color-text-primary;
  overflow: hidden;

  /* Custom Scrollbar */
  ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  ::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.02);
  }

  ::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.08);
    border-radius: 4px;

    &:hover {
      background: rgba(0, 0, 0, 0.12);
    }
  }
}

/* ============================================
   HEADER STYLES
   ============================================ */
.pos-header {
  display: flex;
  align-items: center;
  align-items: center;
  gap: 16px;
  padding: 16px 24px;
  background: $color-card-bg;
  border-bottom: 1px solid $color-border-light;
  box-shadow: $shadow-md;
  min-height: 70px;
}

/* Mobile header base styles (hidden by default; shown only on ≤480px) */
.pos-header-mobile {
  display: none;
}

.header-left {
  display: flex;
  align-items: center;
  height: 100%;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;

  .brand-icon {
    width: 40px;
    height: 40px;
    border-radius: $radius-md;
    background: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    color: inherit;
    box-shadow: none;
    flex-shrink: 0;
  }

  .brand-info {
    h2 {
      margin: 0;
      font-size: 16px;
      font-weight: 700;
      color: $color-text-primary;
      letter-spacing: -0.3px;
      line-height: 1.2;
    }

    p {
      margin: 2px 0 0 0;
      font-size: 11px;
      color: $color-text-tertiary;
      font-weight: 400;
      line-height: 1.2;
    }
  }
}

.header-center {
  display: flex;
  align-items: center;
  height: 100%;
  flex: 1 1 auto;

  .search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    height: 40px;
    width: 100%;

    .search-icon {
      position: absolute;
      left: 12px;
      width: 18px;
      height: 18px;
      color: $color-text-tertiary;
      pointer-events: none;
    }

    .search-input {
      width: 100%;
      height: 100%;
      padding: 0 50px 0 40px;
      background: $color-bg-light;
      border: none;
      border-radius: $radius-md;
      font-size: $font-size-sm;
      color: $color-text-primary;
      transition: $transition-fast;

      &:focus {
        outline: none;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      }
    }

    /* Style the Scan button like an input-group append */
    > .action-btn-icon {
      position: absolute;
      right: 0;
      top: 0;
      height: 100% !important;
      width: 44px !important;
      padding: 0 !important;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: white;
      border: 1px solid $color-border-light;
      border-left: 1px solid $color-border-light;
      border-radius: 0 $radius-md $radius-md 0;
      box-shadow: none;
    }
  }
}

/* Autocomplete dropdown: visible list below search input */
.pos-autocomplete-results {
  position: absolute;
  top: 100%;
  right: 0;
  left: 0;
  margin: 0;
  padding: 0;
  list-style: none;
  background: white;
  border: 1px solid $color-border-light;
  border-top: none;
  border-radius: 0 0 $radius-md $radius-md;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  max-height: 280px;
  overflow-y: auto;
  z-index: 1000;
}
.pos-autocomplete-item {
  padding: 10px 14px;
  font-size: 14px;
  color: $color-text-primary;
  cursor: pointer;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}
.pos-autocomplete-item:last-child {
  border-bottom: none;
}
.pos-autocomplete-item:hover {
  background: $color-bg-light;
}
.pos-autocomplete-item:active {
  background: rgba(102, 126, 234, 0.08);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1 1 auto;
  justify-content: flex-end;
}

/* Category & Brand filters row (mobile); desktop uses same v-selects as warehouse/customer */
.pos-header-filters {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

/* Register status unified button styling */
.register-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

/* Quick Add Customer placement:
   - 320px–768px: show inside the 320px-style header layout (Register Status)
   - >768px: keep it in the header-right area (unchanged position) */
.quick-add-customer--in-register { display: none !important; }
.quick-add-customer--in-header { display: inline-flex !important; }
@media (max-width: 768px) {
  .quick-add-customer--in-register { display: inline-flex !important; }
  .quick-add-customer--in-header { display: none !important; }
}

/* Display inside register-status: mobile only (pos-header-mobile) */
.register-status .register-status-display {
  display: inline-flex;
  align-items: center;
}

.register-status .register-toggle-btn {
  background: $color-bg-light;
  color: $color-text-primary;
  border: 1px solid $color-border-light;
  padding: 4px 10px;
  font-weight: 600;
}

.register-status .register-toggle-btn:hover {
  background: white;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.08);
}

.register-status .register-toggle-btn:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
}

/* POS only: v-select one-line + ellipsis (selected value + search on one line) */
.pos-codecanyon {
  ::v-deep .vs__selected-options {
    flex-wrap: nowrap !important;
    align-items: center;
    overflow: hidden;
  }
  ::v-deep .vs__selected {
    flex: 1 1 auto;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  ::v-deep .vs__search {
    flex: 1 1 auto;
    min-width: 0;
  }
}

/* All POS v-selects: same width and min-width, flexible + truncate */
.warehouse-select,
.display-screen-select,
.customer-select-header,
.category-select-header,
.brand-select-header {
  flex: 1 1 0;
  min-width: 160px;

  ::v-deep .vs__dropdown-toggle {
    width: 100%;
    min-width: 0;
    border: 1px solid #e5e7eb;
  }

  ::v-deep .vs__selected-options {
    width: 100%;
    flex: 1;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
  }

  ::v-deep .vs__placeholder {
    width: 100%;
    white-space: nowrap;
    overflow: visible;
    max-width: 100%;
  }

  ::v-deep .vs__selected {
    display: block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

// Ensure v-select and action buttons layout properly
.header-right {
  // Ensure action buttons maintain their size
  .action-btn-icon,
  .btn-new-customer,
  .btn-pos-settings,
  .btn-offline-status,
  .register-status {
    flex: 0 0 auto;
    flex-shrink: 0;
    min-width: auto;
  }

  // All selects same width and min-width (including customer when selected)
  .warehouse-select,
  .display-screen-select,
  .customer-select-header,
  .category-select-header,
  .brand-select-header {
    flex: 1 1 0;
    min-width: 160px;
  }
}

.user-profile {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: $color-gradient-primary;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  color: white;
  font-size: $font-size-sm;
  box-shadow: 0 2px 8px rgba(102, 126, 234, 0.25);
  cursor: pointer;
  transition: $transition-fast;
  flex-shrink: 0;

  &:hover {
    transform: scale(1.05);
  }
}

/* ============================================
   MAIN CONTAINER & LAYOUT
   ============================================ */
.pos-container {
  display: grid;
  grid-template-columns: 1fr 1.4fr;
  gap: 24px;
  padding: 24px;
  padding-bottom: 100px;
  flex: 1;
  overflow: hidden;
  height: 100%;
}

.pos-column-left {
  display: flex;
  flex-direction: column;
  gap: 24px;
  overflow: hidden;
  height: 100%;
  min-height: 0;
}

/* ============================================
   CARD STYLING
   ============================================ */
.card {
  background: $color-card-bg;
  border-radius: $radius-lg;
  box-shadow: $shadow-md;
  border: 1px solid $color-border-light;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: $transition-smooth;

  &:hover {
    box-shadow: $shadow-lg;
  }
}

.card-header {
  padding: 14px 20px;
  border-bottom: 1px solid $color-border-light;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(to right, #fafbfc 0%, white 100%);
  flex-shrink: 0;

  h3 {
    margin: 0;
    font-size: $font-size-lg;
    font-weight: 600;
    color: $color-text-primary;
    letter-spacing: -0.2px;
  }

  .badge-count {
    background: $color-gradient-primary;
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
  }

  .filter-section {
    display: flex;
    gap: 10px;
    align-items: center;
  }
}

/* ============================================
   FLAT INPUTS & SELECTS
   ============================================ */
.flat-input,
.flat-select {
  padding: 10px 12px;
  background: $color-bg-light;
  border: none;
  border-radius: $radius-sm;
  font-size: $font-size-sm;
  color: $color-text-primary;
  font-family: $font-family-primary;
  transition: $transition-fast;
  cursor: pointer;

  &:focus {
    outline: none;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }

  &::placeholder {
    color: $color-text-tertiary;
  }
}

.flat-select {
  appearance: none;
  background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="rgb(107, 114, 128)" stroke-width="2"%3e%3cpolyline points="6 9 12 15 18 9"%3e%3c/polyline%3e%3c/svg%3e');
  background-repeat: no-repeat;
  background-position: right 8px center;
  background-size: 20px;
  padding-right: 36px;
}

/* ============================================
   CARD: UNIFIED CHECKOUT
   ============================================ */
.card-unified-checkout {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-height: 0;
}

.cart-section {
  flex: 0 0 auto;
  overflow-y: auto;
  max-height: 45%;
  padding: 12px 16px;
  border-bottom: 1px solid $color-border-light;
  min-height: 80px;
}

.cart-items-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}

.cart-item-card {
  padding: 10px;
  background: linear-gradient(to right, #f9fafb 0%, white 100%);
  border: 1px solid $color-border-light;
  border-radius: $radius-sm;
  transition: $transition-smooth;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 8px;
  grid-template-areas:
    "header header"
    "sku sku"
    "qty price"
    "batches batches";

  &:hover {
    border-color: #667eea;
    background: white;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
  }

  .item-header {
    grid-area: header;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 6px;

    .item-name {
      margin: 0;
      font-size: 12px;
      font-weight: 600;
      color: $color-text-primary;
      flex: 1;
      word-break: break-word;
    }

    .edit-btn {
      width: 24px;
      height: 24px;
      min-width: 24px;
      border: 1px solid $color-border-light;
      background: white;
      color: $color-text-secondary;
      border-radius: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: $transition-fast;
      padding: 0;
      -webkit-tap-highlight-color: transparent;

      svg {
        width: 14px;
        height: 14px;
        display: block;
      }

      &:hover {
        border-color: #667eea;
        background: rgba(102, 126, 234, 0.06);
        color: #667eea;
        transform: scale(1.05);
      }

      &:focus,
      &:active,
      &:focus-visible {
        outline: none !important;
        box-shadow: none !important;
      }
    }

    .remove-btn {
      width: 24px;
      height: 24px;
      min-width: 24px;
      border: 1px solid $color-border-light;
      background: white;
      color: #ef4444;
      border-radius: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: $transition-fast;
      padding: 0;
      -webkit-tap-highlight-color: transparent;

      svg {
        width: 14px;
        height: 14px;
        display: block;
      }

      &:hover {
        border-color: #ef4444;
        background: rgba(239, 68, 68, 0.06);
        color: #ef4444;
        transform: scale(1.05);
      }

      &:focus,
      &:active,
      &:focus-visible {
        outline: none !important;
        box-shadow: none !important;
      }
    }
  }

  .item-sku {
    grid-area: sku;
    margin: 0;
    font-size: 10px;
    color: $color-text-tertiary;
    font-weight: 500;
  }

  .item-batches-panel {
    grid-area: batches;
    margin-top: 8px;
    border: 1px solid #e0e7ff;
    border-radius: 8px;
    background: #f8faff;
    overflow: visible;

    .item-batches-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 6px 10px;
      background: #4f46e5;
      color: #ffffff;
      border-radius: 8px 8px 0 0;
    }

    .item-batches-title {
      display: inline-flex;
      align-items: center;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .item-batches-count {
      margin-left: 8px;
      padding: 1px 7px;
      background: rgba(255, 255, 255, 0.22);
      border-radius: 10px;
      font-size: 10px;
      font-weight: 600;
      text-transform: none;
      letter-spacing: 0;
    }

    .item-batches-add-btn {
      padding: 3px 10px;
      background: #ffffff;
      color: #4f46e5;
      border: none;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
    }

    .item-batches-state {
      padding: 8px 10px;
      font-size: 11px;
      color: #6b7280;
      text-align: center;
    }

    .item-batches-error {
      color: #b91c1c;
      background: #fef2f2;
    }

    .item-batches-hint {
      color: #4b5563;
      font-style: italic;
    }

    .item-batches-list {
      padding: 6px;
    }

    .item-batch-row {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 4px 0;

      + .item-batch-row {
        border-top: 1px dashed #e0e7ff;
      }
    }

    .item-batch-select {
      flex: 1;
      min-width: 0;
      font-size: 11px;
    }

    .item-batch-qty {
      width: 70px;
      padding: 4px 6px;
      font-size: 12px;
      text-align: right;
      border: 1px solid #d1d5db;
      border-radius: 5px;
      height: 30px;
    }

    .item-batch-remove {
      width: 24px;
      height: 24px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      line-height: 1;
    }

    .item-batches-warning {
      padding: 6px 10px;
      background: #fef3c7;
      color: #92400e;
      font-size: 10.5px;
      font-weight: 600;
      border-top: 1px solid #fde68a;
      border-radius: 0 0 8px 8px;
    }
  }

.item-qty-section {
  grid-area: qty;
  display: flex;
  align-items: center;
  gap: 6px;

  .qty-controller {
    display: flex;
    align-items: center;
    gap: 4px;

    .qty-btn {
      width: 24px;
      height: 24px;
      border: 1px solid $color-border-light;
      background: white;
      border-radius: 3px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      color: $color-text-primary;
      transition: $transition-fast;
      padding: 0;
      -webkit-tap-highlight-color: transparent;
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      outline: none;

      &:hover {
        border-color: #667eea;
        background: rgba(102, 126, 234, 0.05);
      }

      &:focus,
      &:focus-visible,
      &:active {
        outline: none !important;
        box-shadow: none !important;
        -webkit-box-shadow: none !important;
        border-color: $color-border-light !important;
      }

      &::-moz-focus-inner { border: 0; }
      &:-moz-focusring { outline: none; }
    }

    .qty-input {
      width: 40px;
      padding: 4px 6px;
      background: $color-bg-light;
      border: none;
      border-radius: 3px;
      text-align: center;
      font-size: 11px;
      font-weight: 600;
      transition: $transition-fast;

      &:focus {
        outline: none;
        background: white;
        box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
      }
    }
  }
}

/* (Online reload confirmation now uses a Bootstrap-Vue modal; no extra styles required) */

.pos-price-container {
  max-width: 220px;
}

.pos-price-select {
  min-width: 120px;
  padding: 2px 6px;
  height: 28px;
}

  .item-price {
    grid-area: price;
    text-align: right;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;

    .item-amount {
      background: $color-gradient-primary;
      background-clip: text;
      background-clip: text;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .pos-price-select {
      -webkit-text-fill-color: initial;
      -webkit-background-clip: initial;
      background-clip: initial;
      color: $color-text-primary;
    }

    .item-subtotal {
      font-size: 11px;
      font-weight: 400;
      color: $color-text-secondary;
      margin-top: 2px;

      .subtotal-label {
        margin-right: 4px;
      }
    }
  }
}

.summary-section {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding-bottom: 80px;
}

/* ============================================
   CARD: SUMMARY
   ============================================ */
.card-summary {
  flex: 1;
  min-height: auto;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  padding-bottom: 0;
}

.charges-section {
  padding: 8px 20px;
  border-bottom: 1px solid $color-border-light;
  flex-shrink: 0;
}

.charges-inline {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}

.charge-col {
  flex: 1;
  min-width: 0;

  label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: $color-text-primary;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 3px;
  }

  .charge-input-group {
    position: relative;
    display: flex;
    align-items: center;

    .flat-input {
      width: 100%;
      text-align: left;
      font-size: 13px;
      padding: 4px 8px;
      padding-right: 28px;
    }

    .input-suffix {
      position: absolute;
      right: 8px;
      font-size: 10px;
      color: $color-text-tertiary;
      pointer-events: none;
      font-weight: 600;
    }
    
    &.discount-input-group {
      .flat-input {
        padding-right: 38px;
      }

      .discount-type-toggle {
        position: absolute;
        right: 4px;
        background: white;
        border: 1px solid $color-border-light;
        border-radius: 4px;
        padding: 1px 5px;
        font-size: 10px;
        font-weight: 700;
        color: $color-text-primary;
        cursor: pointer;
        pointer-events: auto;
        transition: $transition-fast;
        min-width: 28px;
        text-align: center;
        
        &:hover {
          border-color: #667eea;
          background: rgba(102, 126, 234, 0.06);
          color: #667eea;
        }
        
        &:active {
          transform: scale(0.95);
        }
        
        &:focus,
        &:focus-visible {
          outline: none;
          box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
      }
    }
  }
}

.summary-totals {
  padding: 12px 20px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: $color-card-bg;
  border-top: 2px solid $color-border-light;
  margin-top: auto;
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  gap: 8px;

  .total-label {
    color: $color-text-secondary;
    font-weight: 500;
    flex: 1;
    min-width: 0;

    &.discount-row {
      color: $color-danger;
    }
  }

  .total-value {
    color: $color-text-primary;
    font-weight: 600;
    text-align: right;
    flex-shrink: 0;

    &.discount-value {
      color: $color-danger;
    }
  }

  &.grand-total {
    margin-top: 4px;
    padding-top: 6px;
    border-top: 1px solid $color-border-light;
    margin-bottom: 0;

    .total-label {
      font-weight: 700;
      color: $color-text-primary;
    }

    .total-value {
      font-size: 15px;
      font-weight: 700;
    }
  }
}

.summary-divider {
  height: 1px;
  background: $color-border-light;
  margin: 2px 0;
}

.gradient-text {
  background: $color-gradient-primary;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* ============================================
   EMPTY STATE
   ============================================ */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  color: $color-text-tertiary;
  text-align: center;
  padding: 20px;
  min-height: 80px;

  svg {
    width: 40px;
    height: 40px;
    margin-bottom: 10px;
    opacity: 0.15;
    stroke-width: 1.5;
  }

  p {
    margin: 0 0 4px 0;
    font-size: 13px;
    font-weight: 500;
    color: $color-text-secondary;
  }

  .empty-hint {
    font-size: 11px;
    color: $color-text-tertiary;
  }
}

.register-toggle-icon {
  border: none;
  background: transparent;
  padding: 0;
  margin-right: 4px;
  cursor: pointer;
  font-size: 16px;
}

/* ============================================
   CARD: PRODUCTS
   ============================================ */
.card-products {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.card-products .card-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}

.card-products .products-search-wrapper {
  flex: 1;
  min-width: 200px;
  position: relative;
}

.card-products .products-search-wrapper .search-wrapper {
  position: relative;
  height: 40px;
  display: flex;
  align-items: center;
  background: $color-bg-light;
  border: 1px solid $color-border-light;
  border-radius: $radius-md;
  overflow: visible;
}

.card-products .products-search-wrapper .search-icon {
  position: absolute;
  left: 12px;
  width: 18px;
  height: 18px;
  color: $color-text-secondary;
  pointer-events: none;
}

.card-products .products-search-wrapper .search-input {
  flex: 1;
  height: 100%;
  padding: 0 50px 0 38px;
  border: none;
  background: transparent;
  font-size: 14px;
  color: $color-text-primary;

  &:focus,
  &:active,
  &:focus-visible {
    outline: none !important;
    box-shadow: none !important;
    border: none;
  }
}

.card-products .products-search-wrapper .action-btn-icon {
  position: absolute;
  right: 0;
  top: 0;
  height: 100%;
  width: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: white;
  border: none;
  border-left: 1px solid $color-border-light;
}

.card-products .products-search-wrapper .pos-autocomplete-results {
  left: 0;
  right: 0;
}

/* Category & brand below autocomplete: hidden on laptop (1366px+), shown on tablet and smaller */
.card-products-mobile-filters {
  display: none;
}

@media (max-width: 1365px) {
  /* Show categories and brands below search (all non-laptop sizes) */
  .card-products-mobile-filters {
    display: flex;
    flex-direction: column;
    width: 100%;
    gap: 8px;
    margin-top: 10px;
  }
  .card-products-mobile-filters .category-select-header,
  .card-products-mobile-filters .brand-select-header {
    width: 100%;
  }
  /* Hide categories and brands in desktop header when below search */
  .header-right .category-select-header,
  .header-right .brand-select-header {
    display: none !important;
  }
}

.card-products.is-loading {
  position: relative;
}

.card-products.is-loading::after {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.6);
  pointer-events: auto;
}

.card-products.is-loading::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 32px;
  height: 32px;
  margin-top: -16px;
  margin-left: -16px;
  border-radius: 50%;
  border: 3px solid rgba(102, 126, 234, 0.25);
  border-top-color: #667eea;
  animation: spinner-rotate 0.8s linear infinite;
}

@keyframes spinner-rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.reset-filters-btn {
  width: 36px;
  height: 36px;
  border: 1px solid $color-border-light;
  background: white;
  border-radius: $radius-sm;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: $transition-fast;
  outline: none;
  box-shadow: none;

  svg {
    width: 16px;
    height: 16px;
    color: $color-text-secondary;
  }

  &:hover {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);

    svg {
      color: #667eea;
    }
  }

  &:focus,
  &:active,
  &:focus-visible {
    outline: none !important;
    box-shadow: none !important;
  }
  -webkit-tap-highlight-color: transparent;
}

.products-container {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
  padding-bottom: 100px;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 16px;
}

.product-card {
  background: $color-card-bg;
  border: 1px solid $color-border-light;
  border-radius: $radius-md;
  overflow: hidden;
  cursor: pointer;
  transition: $transition-smooth;
  display: flex;
  flex-direction: column;
  height: 100%;

  &:hover {
    border-color: #667eea;
    box-shadow: 0 12px 32px rgba(102, 126, 234, 0.15);
    transform: translateY(-6px);

    .add-to-cart-btn {
      transform: scale(1.15);
    }
  }
}

.product-image-wrapper {
  position: relative;
  width: 100%;
  height: 140px;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  border-bottom: 1px solid $color-border-light;

  .product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
  }

  .product-image-placeholder {
    font-size: 48px;
    font-weight: 700;
    color: rgba(102, 126, 234, 0.2);
  }

  .discount-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: linear-gradient(135deg, $color-warning 0%, $color-danger 100%);
    color: white;
    padding: 6px 10px;
    border-radius: $radius-sm;
    font-size: 11px;
    font-weight: 700;
    box-shadow: 0 3px 10px rgba(239, 68, 68, 0.25);
  }
}

.product-details {
  padding: 12px;
  flex: 1;
  display: flex;
  flex-direction: column;

  .product-name {
    margin: 0 0 4px 0;
    font-size: 13px;
    font-weight: 600;
    color: $color-text-primary;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .product-brand {
    margin: 0 0 4px 0;
    font-size: 11px;
    color: $color-text-tertiary;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-weight: 500;
  }

  .product-stock {
    margin: 0 0 8px 0;
    font-size: 11px;
    color: $color-success;
    font-weight: 600;

    &.low-stock {
      color: $color-warning;
    }
  }

  .product-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
    padding-top: 10px;
    border-top: 1px solid $color-border-light;

    .product-price {
      font-size: 14px;
      font-weight: 700;
      background: $color-gradient-primary;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .add-to-cart-btn {
      width: 32px;
      height: 32px;
      border: none;
      background: rgba(102, 126, 234, 0.1);
      color: #667eea;
      border-radius: $radius-sm;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: $transition-fast;
      outline: none;
      box-shadow: none;

      svg {
        width: 16px;
        height: 16px;
      }

      &:hover:not(:disabled) {
        background: rgba(102, 126, 234, 0.2);
      }

      &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }

      &:focus,
      &:active,
      &:focus-visible {
        outline: none !important;
        box-shadow: none !important;
      }
      -webkit-tap-highlight-color: transparent;
    }
  }
}

/* ============================================
   FIXED FOOTER BAR
   ============================================ */
.pos-footer-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 12px 24px;
  background: $color-card-bg;
  border-top: 1px solid $color-border-light;
  box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.08);
  z-index: 1000;
  height: auto;
}

.footer-status-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 15px;
  color: $color-success;

  svg {
    width: 16px;
    height: 16px;
  }

  &.is-offline {
    color: $color-warning;
  }
}

.footer-main-group {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  justify-content: center;
}

.footer-main-group .action-btn {
  padding: 12px 0;
}

.action-btn {
  padding: 12px 20px;
  border: none;
  border-radius: $radius-md;
  font-size: $font-size-sm;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: $transition-smooth;
  font-family: $font-family-primary;
  outline: none;
  box-shadow: none;

  svg {
    width: 18px;
    height: 18px;
  }

  &:hover {
    transform: translateY(-2px);
  }

  &:active {
    transform: translateY(0);
  }

  &:focus,
  &:active,
  &:focus-visible {
    outline: none !important;
    box-shadow: none !important;
  }
  -webkit-tap-highlight-color: transparent;
}

.action-btn-secondary {
  border: 1.5px solid $color-border-light;
  background: white;
  color: $color-text-secondary;

  &:hover {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
    color: #667eea;
  }
}

::v-deep(.action-btn-icon) {
  width: 44px;
  height: 44px;
  padding: 0;
  border: 1px solid $color-border-light;
  background: white;
  color: $color-text-secondary;
  border-radius: $radius-md;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  cursor: pointer;

  i {
    font-size: 18px;
    line-height: 1;
    display: inline-block;
    vertical-align: middle;
  }

  &:hover {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
    color: #667eea;
  }

  &:focus,
  &:active,
  &:focus-visible {
    outline: none !important;
    box-shadow: none !important;
  }
  -webkit-tap-highlight-color: transparent;
}

/* Offline status button - always red */
.btn-offline-status {
  border-color: $color-danger !important;
  background: $color-danger !important;
  color: #fff !important;

  i {
    color: #fff !important;
  }

  &:hover {
    border-color: darken($color-danger, 5%) !important;
    background: darken($color-danger, 5%) !important;
    color: #fff !important;
  }
}

/* Ensure icomoon icons render consistently inside this component */
::v-deep(i[class^="i-"]) {
  line-height: 1;
  display: inline-block;
  vertical-align: middle;
}

.action-btn-primary {
  background: $color-gradient-primary;
  color: white;
  box-shadow: 0 4px 16px rgba(102, 126, 234, 0.25);
  flex: 1;
  max-width: 300px;
  justify-content: center;

  &:hover:not(:disabled) {
    box-shadow: 0 6px 24px rgba(102, 126, 234, 0.35);
    background: $color-gradient-hover;
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.footer-space {
  flex: 1;
}
/* Languages dropdown */
::v-deep(#lang-dd .dropdown-menu) {
  min-width: 220px;
  padding: 8px;
}

.lang-menu {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px;
}

.lang-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  border: 1px solid $color-border-light;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
  width: 100%;
  text-align: left;
}
.lang-item:hover {
  border-color: #667eea;
  background: rgba(102, 126, 234, 0.06);
}
.lang-item .flag-icon { width: 20px; height: 14px; object-fit: cover; }
.lang-item .title-lang { font-size: 12px; color: $color-text-primary; }
/* New Customer Modal improvements */
::v-deep(.new-customer-form) {
  .form-group {
    margin-bottom: 12px;
  }

  input.form-control {
    height: 38px;
    border-radius: 8px;
  }

  .custom-control-label {
    user-select: none;
  }

  .loyalty-eligible-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 12px;
    border: 1px solid $color-border-light;
    border-radius: 10px;
    background: #fff;
  }

  .loyalty-info {
    display: flex;
    flex-direction: column;
  }

  .loyalty-title {
    font-weight: 700;
    color: $color-text-primary;
    margin-bottom: 2px;
  }

  .loyalty-help {
    font-size: 12px;
  }
}

.pos-gate-loader {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
  width: 100vw;
}

.card-loading-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255,255,255,0.6);
  backdrop-filter: saturate(150%) blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: inherit;
  z-index: 2;
}

/* ============================================
   TODAY SALES MODAL — modern UI
   (modal-wrapper overrides live in the global <style> block at the
   end of the file; teleported modals are outside scoped style scope.)
   ============================================ */

.ts-modal-body {
  display: flex;
  flex-direction: column;
}

/* ---- Hero ---- */
.ts-hero {
  position: relative;
  padding: 22px 24px 24px;
  background:
    radial-gradient(1200px 200px at -10% -40%, rgba(255,255,255,0.18), transparent 60%),
    radial-gradient(900px 220px at 110% 0%, rgba(255,255,255,0.14), transparent 55%),
    linear-gradient(135deg, #6f53d9 0%, #8a6cf0 45%, #b58cff 100%);
  color: #fff;
  overflow: hidden;
}
.ts-hero::after {
  content: "";
  position: absolute;
  inset: auto -40px -60px auto;
  width: 220px;
  height: 220px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0) 70%);
  pointer-events: none;
}

.ts-close {
  position: absolute;
  top: 14px;
  right: 14px;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  border: 0;
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 150ms ease, transform 150ms ease;
  z-index: 2;
  i { font-size: 16px; }
  svg { width: 16px; height: 16px; }
  &:hover { background: rgba(255, 255, 255, 0.28); transform: rotate(90deg); }
}

.ts-hero-top {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}
.ts-hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: rgba(255,255,255,0.85);
  letter-spacing: 0.2px;
  i { font-size: 13px; }
  svg { width: 13px; height: 13px; }
}
.ts-hero-heading {
  margin-top: 4px;
  font-size: 20px;
  font-weight: 700;
  letter-spacing: -0.3px;
}
.ts-hero-amount-wrap {
  position: relative;
  z-index: 1;
  margin-bottom: 18px;
}
.ts-hero-label {
  font-size: 12px;
  font-weight: 500;
  color: rgba(255,255,255,0.8);
  text-transform: uppercase;
  letter-spacing: 0.6px;
}
.ts-hero-amount {
  margin-top: 4px;
  font-size: 34px;
  font-weight: 800;
  letter-spacing: -0.8px;
  font-feature-settings: "tnum";
  font-variant-numeric: tabular-nums;
}

.ts-hero-stats {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 14px;
  align-items: stretch;
  padding: 12px;
  border-radius: 14px;
  background: rgba(255,255,255,0.14);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  border: 1px solid rgba(255,255,255,0.18);
}
.ts-hero-stat {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.ts-hero-stat-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 38px;
  color: #fff;
  background: rgba(255,255,255,0.22);
  i { font-size: 18px; }
  svg { width: 18px; height: 18px; }
  &.paid { background: linear-gradient(135deg, #10b981, #34d399); }
  &.due  { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
}
.ts-hero-stat-info { min-width: 0; display: flex; flex-direction: column; }
.ts-hero-stat-label {
  font-size: 11px;
  color: rgba(255,255,255,0.85);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ts-hero-stat-val {
  margin-top: 2px;
  font-size: 16px;
  font-weight: 700;
  color: #fff;
  font-feature-settings: "tnum";
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ts-hero-divider {
  width: 1px;
  background: rgba(255,255,255,0.22);
}

/* ---- Section ---- */
.ts-section {
  padding: 18px 20px 20px;
}
.ts-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}
.ts-section-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: $color-text-primary;
  letter-spacing: 0.1px;
  i { font-size: 15px; color: #6f53d9; }
  svg { width: 15px; height: 15px; color: #6f53d9; }
}
.ts-section-sub {
  font-size: 12px;
  font-weight: 600;
  color: $color-text-tertiary;
  font-feature-settings: "tnum";
  font-variant-numeric: tabular-nums;
}

.ts-methods {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ts-method {
  display: grid;
  grid-template-columns: 38px 1fr;
  gap: 12px;
  align-items: center;
  padding: 12px;
  background: #fff;
  border: 1px solid $color-border-light;
  border-radius: 12px;
  transition: border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
}
.ts-method:hover {
  border-color: rgba(111, 83, 217, 0.35);
  box-shadow: 0 4px 14px -8px rgba(111, 83, 217, 0.45);
  transform: translateY(-1px);
}
.ts-method-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(111,83,217,0.12), rgba(138,108,240,0.12));
  color: #6f53d9;
  i { font-size: 18px; }
  svg { width: 18px; height: 18px; }
}
.ts-method-body { min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.ts-method-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.ts-method-name {
  font-size: 13px;
  font-weight: 600;
  color: $color-text-primary;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ts-method-amount {
  font-size: 14px;
  font-weight: 700;
  color: $color-text-primary;
  font-feature-settings: "tnum";
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.ts-method-bar-wrap {
  height: 6px;
  border-radius: 999px;
  background: #eef0f5;
  overflow: hidden;
}
.ts-method-bar {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #6f53d9, #8a6cf0, #b58cff);
  transition: width 400ms cubic-bezier(.4, 0, .2, 1);
  min-width: 2%;
}

/* ---- Empty state ---- */
.ts-empty {
  padding: 32px 24px 36px;
  text-align: center;
  color: $color-text-tertiary;
}
.ts-empty-icon {
  width: 56px;
  height: 56px;
  margin: 0 auto 10px;
  border-radius: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(111, 83, 217, 0.08);
  color: #6f53d9;
  i { font-size: 24px; }
  svg { width: 24px; height: 24px; }
}
.ts-empty-text { font-size: 13px; font-weight: 500; }

/* ---- Responsive ---- */
@media (max-width: 540px) {
  .ts-hero { padding: 18px 18px 20px; }
  .ts-hero-amount { font-size: 28px; }
  .ts-hero-stats {
    grid-template-columns: 1fr;
    gap: 10px;
  }
  .ts-hero-divider {
    width: 100%;
    height: 1px;
  }
  .ts-section { padding: 14px 16px 18px; }
}

/* ============================================
   POS SETTINGS MODAL — modern UI
   (wrapper overrides live in the global <style> at end of file)
   ============================================ */
.ps-modal-body {
  display: flex;
  flex-direction: column;
}

/* Hero */
.ps-hero {
  position: relative;
  padding: 22px 24px 22px;
  background:
    radial-gradient(1200px 200px at -10% -40%, rgba(255,255,255,0.18), transparent 60%),
    radial-gradient(900px 220px at 110% 0%, rgba(255,255,255,0.14), transparent 55%),
    linear-gradient(135deg, #6f53d9 0%, #8a6cf0 45%, #b58cff 100%);
  color: #fff;
  overflow: hidden;
}
.ps-hero::after {
  content: "";
  position: absolute;
  inset: auto -40px -60px auto;
  width: 220px;
  height: 220px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0) 70%);
  pointer-events: none;
}
.ps-close {
  position: absolute;
  top: 14px;
  right: 14px;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  border: 0;
  background: rgba(255,255,255,0.18);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 150ms ease, transform 150ms ease;
  z-index: 2;
  i { font-size: 16px; }
  svg { width: 16px; height: 16px; }
  &:hover { background: rgba(255,255,255,0.28); transform: rotate(90deg); }
}
.ps-hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: rgba(255,255,255,0.85);
  letter-spacing: 0.2px;
  i { font-size: 13px; }
  svg { width: 13px; height: 13px; }
}
.ps-hero-heading {
  margin-top: 4px;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.4px;
}
.ps-hero-sub {
  margin-top: 6px;
  font-size: 13px;
  color: rgba(255,255,255,0.85);
  max-width: 480px;
  line-height: 1.45;
}

/* Loading state */
.ps-loading {
  padding: 48px 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Sections */
.ps-section {
  padding: 18px 24px;
  border-bottom: 1px solid #ececf3;
  background: #fff;
}
.ps-section:last-of-type { border-bottom: 0; }
.ps-section-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: $color-text-primary;
  margin-bottom: 12px;
  letter-spacing: 0.1px;
  text-transform: uppercase;
  i { font-size: 15px; color: #6f53d9; }
  svg { width: 15px; height: 15px; color: #6f53d9; }
}

.ps-help-banner {
  font-size: 12px;
  color: $color-text-tertiary;
  background: rgba(111,83,217,0.06);
  border: 1px solid rgba(111,83,217,0.15);
  border-radius: 10px;
  padding: 10px 12px;
  margin-bottom: 12px;
  line-height: 1.5;
}

/* Toggle grid */
.ps-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
@media (max-width: 640px) {
  .ps-grid { grid-template-columns: 1fr; }
}

.ps-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid $color-border-light;
  border-radius: 12px;
  background: #fff;
  cursor: pointer;
  transition: border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
  margin: 0;
}
.ps-toggle:hover {
  border-color: rgba(111,83,217,0.35);
  box-shadow: 0 4px 14px -8px rgba(111,83,217,0.45);
  transform: translateY(-1px);
}
.ps-toggle-info { min-width: 0; flex: 1 1 auto; }
.ps-toggle-label {
  font-size: 13px;
  font-weight: 600;
  color: $color-text-primary;
  line-height: 1.3;
}
.ps-toggle-help {
  font-size: 11.5px;
  color: $color-text-tertiary;
  margin-top: 3px;
  line-height: 1.4;
}

/* Custom switch */
.ps-switch {
  position: relative;
  display: inline-block;
  width: 40px;
  height: 22px;
  flex: 0 0 40px;
}
.ps-switch input {
  opacity: 0;
  width: 0;
  height: 0;
  position: absolute;
}
.ps-switch-slider {
  position: absolute;
  inset: 0;
  background: #d8d8e3;
  border-radius: 999px;
  cursor: pointer;
  transition: background 200ms ease;
}
.ps-switch-slider::before {
  content: "";
  position: absolute;
  top: 2px;
  left: 2px;
  width: 18px;
  height: 18px;
  background: #fff;
  border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
  transition: transform 200ms cubic-bezier(.4,0,.2,1);
}
.ps-switch input:checked + .ps-switch-slider {
  background: linear-gradient(135deg, #6f53d9, #8a6cf0);
}
.ps-switch input:checked + .ps-switch-slider::before {
  transform: translateX(18px);
}
.ps-switch input:focus-visible + .ps-switch-slider {
  outline: 2px solid rgba(111,83,217,0.45);
  outline-offset: 2px;
}

/* Fields */
.ps-field { margin-top: 14px; }
.ps-field-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: $color-text-secondary;
  margin-bottom: 6px;
}
.ps-field-help {
  font-size: 11.5px;
  color: $color-text-tertiary;
  margin-top: 4px;
}
::v-deep(.ps-input) {
  border-radius: 10px;
  border: 1px solid $color-border-light;
  padding: 8px 12px;
  font-size: 13px;
  transition: border-color 150ms ease, box-shadow 150ms ease;
}
::v-deep(.ps-input:focus) {
  border-color: #6f53d9;
  box-shadow: 0 0 0 3px rgba(111,83,217,0.15);
}

/* Footer */
.ps-footer {
  padding: 14px 24px 18px;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background: #f7f7fb;
  border-top: 1px solid #ececf3;
}
.ps-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  border-radius: 10px;
  border: 1px solid transparent;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 150ms ease, box-shadow 150ms ease, background 150ms ease, border-color 150ms ease;
  i { font-size: 14px; }
  svg { width: 14px; height: 14px; }
  &:disabled { opacity: 0.65; cursor: not-allowed; }
}
.ps-btn-secondary {
  background: #fff;
  color: $color-text-secondary;
  border-color: $color-border-light;
  &:hover:not(:disabled) {
    background: #f3f3f9;
    border-color: #c8c8d6;
  }
}
.ps-btn-primary {
  background: linear-gradient(135deg, #6f53d9, #8a6cf0);
  color: #fff;
  &:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px -6px rgba(111,83,217,0.6);
  }
}

/* ============================================
   CATEGORIES — trigger button + slide-in drawer
   ============================================ */
.pos-cat-trigger {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 36px;
  padding: 0 8px 0 10px;
  min-width: 150px;
  max-width: 200px;
  border-radius: 10px;
  border: 1px solid #e6e6ec;
  background: #ffffff;
  color: $color-text-primary;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;

  .pos-cat-trigger-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 7px;
    background: linear-gradient(135deg, rgba(111,83,217,0.12), rgba(138,108,240,0.12));
    color: #6f53d9;
    flex: 0 0 22px;
    i { font-size: 12px; }
    svg { width: 13px; height: 13px; }
  }
  .pos-cat-trigger-label {
    flex: 1 1 auto;
    min-width: 0;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .pos-cat-trigger-caret {
    color: $color-text-tertiary;
    flex: 0 0 auto;
    i { font-size: 14px; }
    svg { width: 14px; height: 14px; }
  }
  .pos-cat-trigger-clear {
    border: 0;
    background: transparent;
    color: $color-text-tertiary;
    width: 20px;
    height: 20px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 120ms ease, color 120ms ease;
    flex: 0 0 auto;
    padding: 0;
    i { font-size: 12px; }
    svg { width: 12px; height: 12px; }
    &:hover { background: #f3f3f9; color: $color-text-primary; }
  }
  &:hover {
    border-color: rgba(111,83,217,0.35);
    box-shadow: 0 2px 8px -4px rgba(111,83,217,0.35);
  }
  &.is-active {
    background: linear-gradient(135deg, rgba(111,83,217,0.08), rgba(138,108,240,0.08));
    .pos-cat-trigger-label { color: #6f53d9; font-weight: 600; }
  }
}

/* Backdrop + drawer container */
.cat-drawer-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1080; /* above POS UI, below bootstrap modals (1050+) — pick high enough to win in-app */
  background: rgba(15, 15, 30, 0.42);
  backdrop-filter: blur(2px);
  -webkit-backdrop-filter: blur(2px);
  display: flex;
  justify-content: flex-start;
}
.cat-drawer {
  position: relative;
  height: 100%;
  width: 380px;
  max-width: 92vw;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  box-shadow: 18px 0 40px -12px rgba(20, 20, 40, 0.25);
  border-top-right-radius: 18px;
  border-bottom-right-radius: 18px;
  overflow: hidden;
}

/* Drawer transition (slide in from left + fade backdrop) */
.cat-drawer-enter-active,
.cat-drawer-leave-active {
  transition: opacity 200ms ease;
  .cat-drawer { transition: transform 260ms cubic-bezier(.4, 0, .2, 1); }
}
.cat-drawer-enter-from, .cat-drawer-enter,
.cat-drawer-leave-to {
  opacity: 0;
  .cat-drawer { transform: translateX(-100%); }
}

/* Header */
.cat-drawer-header {
  padding: 18px 20px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background:
    radial-gradient(900px 200px at -10% -40%, rgba(255,255,255,0.18), transparent 60%),
    linear-gradient(135deg, #6f53d9 0%, #8a6cf0 50%, #b58cff 100%);
  color: #fff;
  position: relative;
}
.cat-drawer-header::after {
  content: "";
  position: absolute;
  inset: auto -30px -50px auto;
  width: 180px;
  height: 180px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.16) 0%, rgba(255,255,255,0) 70%);
  pointer-events: none;
}
.cat-drawer-title {
  display: flex;
  align-items: center;
  gap: 10px;
  position: relative;
  z-index: 1;
}
.cat-drawer-title-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.2);
  color: #fff;
  flex: 0 0 36px;
  i { font-size: 16px; }
  svg { width: 18px; height: 18px; }
}
.cat-drawer-title-text {
  font-size: 17px;
  font-weight: 700;
  letter-spacing: -0.2px;
  line-height: 1.2;
}
.cat-drawer-title-sub {
  font-size: 11.5px;
  color: rgba(255,255,255,0.85);
  margin-top: 2px;
}
.cat-drawer-close {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  border: 0;
  background: rgba(255,255,255,0.18);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 150ms ease, transform 150ms ease;
  position: relative;
  z-index: 1;
  i { font-size: 16px; }
  svg { width: 16px; height: 16px; }
  &:hover { background: rgba(255,255,255,0.28); transform: rotate(90deg); }
}

/* Search */
.cat-drawer-search {
  position: relative;
  padding: 12px 16px 8px;
  display: flex;
  align-items: center;
  > i, > svg {
    position: absolute;
    left: 28px;
    top: 50%;
    transform: translateY(-50%);
    color: $color-text-tertiary;
    pointer-events: none;
    font-size: 14px;
    width: 14px;
    height: 14px;
  }
  input {
    width: 100%;
    height: 38px;
    padding: 0 36px 0 36px;
    border-radius: 10px;
    border: 1px solid $color-border-light;
    background: #f7f7fb;
    font-size: 13px;
    color: $color-text-primary;
    transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
    &::placeholder { color: $color-text-tertiary; }
    &:focus {
      outline: none;
      border-color: #6f53d9;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(111,83,217,0.15);
    }
  }
}
.cat-drawer-search-clear {
  position: absolute;
  right: 22px;
  top: 50%;
  transform: translateY(-50%);
  width: 22px;
  height: 22px;
  border-radius: 6px;
  border: 0;
  background: transparent;
  color: $color-text-tertiary;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  i { font-size: 12px; }
  svg { width: 12px; height: 12px; position: static; transform: none; }
  &:hover { background: #ececf3; color: $color-text-primary; }
}

/* List */
.cat-drawer-list {
  flex: 1 1 auto;
  overflow-y: auto;
  padding: 6px 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  &::-webkit-scrollbar { width: 8px; }
  &::-webkit-scrollbar-thumb { background: #d8d8e3; border-radius: 99px; }
}
.cat-drawer-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid transparent;
  background: transparent;
  cursor: pointer;
  text-align: left;
  width: 100%;
  transition: background 150ms ease, border-color 150ms ease, transform 150ms ease;
  &:hover {
    background: #f7f7fb;
    border-color: #ececf3;
  }
  &.active {
    background: linear-gradient(135deg, rgba(111,83,217,0.08), rgba(138,108,240,0.08));
    border-color: rgba(111,83,217,0.35);
    .cat-drawer-item-name { color: #6f53d9; font-weight: 700; }
  }
}
.cat-drawer-item-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(135deg, #6f53d9, #8a6cf0);
  flex: 0 0 36px;
  letter-spacing: 0.2px;
  &.all {
    background: linear-gradient(135deg, rgba(111,83,217,0.12), rgba(138,108,240,0.12));
    color: #6f53d9;
    i { font-size: 16px; }
    svg { width: 16px; height: 16px; }
  }
}
.cat-drawer-item-name {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 13.5px;
  font-weight: 500;
  color: $color-text-primary;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cat-drawer-item-check {
  color: #6f53d9;
  flex: 0 0 auto;
  i { font-size: 16px; }
  svg { width: 16px; height: 16px; }
}

/* Empty */
.cat-drawer-empty {
  text-align: center;
  padding: 32px 12px 20px;
  color: $color-text-tertiary;
}
.cat-drawer-empty-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(111,83,217,0.06);
  color: #6f53d9;
  margin-bottom: 8px;
  i { font-size: 20px; }
  svg { width: 20px; height: 20px; }
}
.cat-drawer-empty-text {
  font-size: 13px;
  font-weight: 500;
}

/* Mobile */
@media (max-width: 540px) {
  .cat-drawer { width: 100vw; max-width: 100vw; border-radius: 0; }
  .cat-drawer-header { padding: 14px 16px 12px; }
  .pos-cat-trigger { min-width: 0; max-width: 160px; }
}

/* ============================================
   WAREHOUSES — trigger + right-side drawer (distinct theme)
   Theme: cyan/teal accents (vs categories/brands purple)
   ============================================ */
.pos-wh-trigger {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  height: 40px;
  padding: 0 10px 0 8px;
  min-width: 180px;
  max-width: 230px;
  border-radius: 12px;
  border: 1px solid #cfeaf2;
  background: linear-gradient(135deg, #f0fbff 0%, #ffffff 60%);
  color: $color-text-primary;
  cursor: pointer;
  transition: border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;

  .pos-wh-trigger-icon {
    position: relative;
    width: 30px;
    height: 30px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 30px;
    background: linear-gradient(135deg, #06b6d4, #0ea5e9);
    color: #fff;
    box-shadow: 0 4px 10px -4px rgba(14,165,233,0.55);
    i { font-size: 14px; }
    svg { width: 15px; height: 15px; }
  }
  .pos-wh-trigger-icon-pulse {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #10b981;
    border: 2px solid #fff;
    box-shadow: 0 0 0 0 rgba(16,185,129,0.55);
    animation: wh-pulse 1.8s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  }
  .pos-wh-trigger-body {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    line-height: 1.2;
  }
  .pos-wh-trigger-eyebrow {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.5px;
    color: #0e7490;
    text-transform: uppercase;
  }
  .pos-wh-trigger-label {
    font-size: 13px;
    font-weight: 700;
    color: $color-text-primary;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 1px;
  }
  .pos-wh-trigger-caret {
    color: #0e7490;
    flex: 0 0 auto;
    transition: transform 200ms ease;
    i { font-size: 14px; }
    svg { width: 14px; height: 14px; }
  }
  &:hover {
    border-color: #06b6d4;
    box-shadow: 0 4px 14px -6px rgba(6,182,212,0.45);
    transform: translateY(-1px);
    .pos-wh-trigger-caret { transform: rotate(180deg); }
  }
  &.is-active {
    background: linear-gradient(135deg, #ecfeff 0%, #f0fbff 100%);
  }
}
@keyframes wh-pulse {
  0%   { box-shadow: 0 0 0 0 rgba(16,185,129,0.55); }
  70%  { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
  100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
}

/* Backdrop + drawer container */
.wh-drawer-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1080;
  background: rgba(8, 25, 40, 0.45);
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
  display: flex;
  justify-content: flex-end; /* slide in from right */
}
.wh-drawer {
  position: relative;
  height: 100%;
  width: 420px;
  max-width: 92vw;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  box-shadow: -18px 0 40px -12px rgba(8, 25, 40, 0.28);
  border-top-left-radius: 22px;
  border-bottom-left-radius: 22px;
  overflow: hidden;
}

/* Slide in from RIGHT */
.wh-drawer-enter-active,
.wh-drawer-leave-active {
  transition: opacity 200ms ease;
  .wh-drawer { transition: transform 280ms cubic-bezier(.4, 0, .2, 1); }
}
.wh-drawer-enter-from, .wh-drawer-enter,
.wh-drawer-leave-to {
  opacity: 0;
  .wh-drawer { transform: translateX(100%); }
}

/* Hero header — taller, more elaborate than the cat drawer */
.wh-drawer-header {
  position: relative;
  padding: 28px 24px 26px;
  background:
    radial-gradient(900px 240px at -10% -50%, rgba(255,255,255,0.22), transparent 60%),
    radial-gradient(700px 200px at 110% 0%, rgba(255,255,255,0.15), transparent 55%),
    linear-gradient(135deg, #0e7490 0%, #06b6d4 50%, #22d3ee 100%);
  color: #fff;
  overflow: hidden;
}
.wh-drawer-header::before {
  content: "";
  position: absolute;
  inset: auto -60px -80px -60px;
  height: 200px;
  background: repeating-linear-gradient(
    -45deg,
    transparent 0px,
    transparent 18px,
    rgba(255,255,255,0.05) 18px,
    rgba(255,255,255,0.05) 22px
  );
  pointer-events: none;
}
.wh-drawer-header::after {
  content: "";
  position: absolute;
  inset: auto -50px -70px auto;
  width: 240px;
  height: 240px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0) 70%);
  pointer-events: none;
}
.wh-drawer-close {
  position: absolute;
  top: 14px;
  right: 14px;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  border: 0;
  background: rgba(255,255,255,0.18);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 150ms ease, transform 150ms ease;
  z-index: 2;
  i { font-size: 16px; }
  svg { width: 16px; height: 16px; }
  &:hover { background: rgba(255,255,255,0.28); transform: rotate(90deg); }
}
.wh-drawer-hero-icon {
  position: relative;
  z-index: 1;
  width: 56px;
  height: 56px;
  border-radius: 16px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.22);
  border: 1px solid rgba(255,255,255,0.3);
  color: #fff;
  margin-bottom: 12px;
  i { font-size: 26px; }
  svg { width: 28px; height: 28px; }
  box-shadow: 0 8px 24px -8px rgba(0,0,0,0.25);
}
.wh-drawer-hero-text {
  position: relative;
  z-index: 1;
}
.wh-drawer-hero-eyebrow {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.85);
}
.wh-drawer-hero-title {
  margin-top: 4px;
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.5px;
}
.wh-drawer-hero-sub {
  margin-top: 4px;
  font-size: 12.5px;
  color: rgba(255,255,255,0.85);
}

/* Search */
.wh-drawer-search {
  position: relative;
  padding: 14px 18px 8px;
  display: flex;
  align-items: center;
  > i, > svg {
    position: absolute;
    left: 30px;
    top: 50%;
    transform: translateY(-50%);
    color: $color-text-tertiary;
    pointer-events: none;
    width: 14px;
    height: 14px;
    font-size: 14px;
  }
  input {
    width: 100%;
    height: 40px;
    padding: 0 36px;
    border-radius: 12px;
    border: 1px solid $color-border-light;
    background: #f5fbfd;
    font-size: 13px;
    color: $color-text-primary;
    transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
    &::placeholder { color: $color-text-tertiary; }
    &:focus {
      outline: none;
      border-color: #06b6d4;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(6,182,212,0.18);
    }
  }
}
.wh-drawer-search-clear {
  position: absolute;
  right: 24px;
  top: 50%;
  transform: translateY(-50%);
  width: 22px;
  height: 22px;
  border-radius: 6px;
  border: 0;
  background: transparent;
  color: $color-text-tertiary;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  i { font-size: 12px; }
  svg { width: 12px; height: 12px; position: static; transform: none; }
  &:hover { background: #ecf4f7; color: $color-text-primary; }
}

/* Cards list — richer than cat-drawer items */
.wh-drawer-list {
  flex: 1 1 auto;
  overflow-y: auto;
  padding: 8px 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  &::-webkit-scrollbar { width: 8px; }
  &::-webkit-scrollbar-thumb { background: #cee7ee; border-radius: 99px; }
}
.wh-drawer-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px;
  border-radius: 14px;
  border: 1.5px solid #e6eef1;
  background: #fff;
  cursor: pointer;
  text-align: left;
  width: 100%;
  transition: border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease, background 180ms ease;
  overflow: hidden;
  &::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, #06b6d4, #22d3ee);
    transform: scaleY(0);
    transform-origin: top;
    transition: transform 220ms cubic-bezier(.4, 0, .2, 1);
  }
  &:hover {
    border-color: #06b6d4;
    box-shadow: 0 6px 18px -8px rgba(6,182,212,0.35);
    transform: translateY(-2px);
    .wh-drawer-card-icon { transform: scale(1.05) rotate(-3deg); }
  }
  &.active {
    border-color: #06b6d4;
    background: linear-gradient(135deg, #ecfeff 0%, #f0fbff 100%);
    box-shadow: 0 8px 24px -10px rgba(6,182,212,0.45);
    &::before { transform: scaleY(1); }
    .wh-drawer-card-name { color: #0e7490; }
  }
}
.wh-drawer-card-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #06b6d4, #0ea5e9);
  color: #fff;
  flex: 0 0 44px;
  box-shadow: 0 6px 14px -6px rgba(14,165,233,0.5);
  transition: transform 220ms cubic-bezier(.4, 0, .2, 1);
  i { font-size: 20px; }
  svg { width: 22px; height: 22px; }
  &.clear {
    background: linear-gradient(135deg, #94a3b8, #cbd5e1);
    color: #fff;
    box-shadow: 0 6px 14px -6px rgba(100,116,139,0.4);
  }
}
.wh-drawer-card-clear {
  margin-bottom: 6px;
  &::after {
    content: "";
    position: absolute;
    left: 14px;
    right: 14px;
    bottom: -4px;
    height: 1px;
    background: #eef2f5;
  }
  &.active {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    box-shadow: 0 8px 24px -10px rgba(100,116,139,0.4);
    .wh-drawer-card-name { color: #475569; }
    &::before { background: linear-gradient(180deg, #94a3b8, #cbd5e1); }
    .wh-drawer-card-arrow { color: #94a3b8; }
  }
  &:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 18px -8px rgba(100,116,139,0.3);
  }
}
.wh-drawer-card-hint {
  font-size: 11px;
  font-weight: 500;
  color: $color-text-tertiary;
  font-style: italic;
}
.wh-drawer-card-body {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.wh-drawer-card-name {
  font-size: 14.5px;
  font-weight: 700;
  color: $color-text-primary;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: -0.1px;
}
.wh-drawer-card-meta {
  display: flex;
  align-items: center;
  gap: 8px;
}
.wh-drawer-card-id {
  font-size: 11px;
  font-weight: 600;
  color: $color-text-tertiary;
  font-family: 'JetBrains Mono', ui-monospace, monospace;
  background: #f3f4f6;
  padding: 2px 6px;
  border-radius: 6px;
}
.wh-drawer-card-active-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 2px 8px;
  border-radius: 999px;
  background: linear-gradient(135deg, #10b981, #34d399);
  color: #fff;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}
.wh-drawer-card-active-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 0 0 0 rgba(255,255,255,0.6);
  animation: wh-pulse-dot 1.6s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes wh-pulse-dot {
  0%   { box-shadow: 0 0 0 0 rgba(255,255,255,0.7); }
  70%  { box-shadow: 0 0 0 6px rgba(255,255,255,0); }
  100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); }
}
.wh-drawer-card-arrow {
  color: $color-text-tertiary;
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: color 180ms ease, transform 180ms ease;
  i { font-size: 18px; }
  svg { width: 18px; height: 18px; }
}
.wh-drawer-card.active .wh-drawer-card-arrow {
  color: #06b6d4;
}
.wh-drawer-card:hover .wh-drawer-card-arrow {
  transform: translateX(2px);
  color: #06b6d4;
}

/* Empty */
.wh-drawer-empty {
  text-align: center;
  padding: 36px 16px 24px;
  color: $color-text-tertiary;
}
.wh-drawer-empty-icon {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(6,182,212,0.08);
  color: #06b6d4;
  margin-bottom: 10px;
  i { font-size: 24px; }
  svg { width: 24px; height: 24px; }
}
.wh-drawer-empty-text {
  font-size: 13px;
  font-weight: 500;
}

@media (max-width: 540px) {
  .wh-drawer { width: 100vw; max-width: 100vw; border-radius: 0; }
  .wh-drawer-header { padding: 22px 18px 20px; }
  .wh-drawer-hero-title { font-size: 20px; }
  .pos-wh-trigger { min-width: 0; max-width: 200px; height: 38px; }
  .pos-wh-trigger .pos-wh-trigger-eyebrow { display: none; }
}

/* ============================================
   CUSTOMERS — trigger + center modal picker (distinct theme)
   Theme: rose/pink accents, scale-from-center transition,
   modal-style centered card (vs side drawers)
   ============================================ */
.pos-cust-trigger {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  height: 40px;
  padding: 0 10px 0 6px;
  min-width: 200px;
  max-width: 240px;
  border-radius: 12px;
  border: 1px solid #fbe4ed;
  background: linear-gradient(135deg, #fff5f8 0%, #ffffff 60%);
  color: $color-text-primary;
  cursor: pointer;
  transition: border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;

  .pos-cust-trigger-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 30px;
    background: linear-gradient(135deg, #f43f5e, #fb7185);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.2px;
    box-shadow: 0 4px 10px -4px rgba(244,63,94,0.5);
    i { font-size: 14px; }
    svg { width: 14px; height: 14px; }
  }
  .pos-cust-trigger-body {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    line-height: 1.2;
  }
  .pos-cust-trigger-eyebrow {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.5px;
    color: #be185d;
    text-transform: uppercase;
  }
  .pos-cust-trigger-label {
    font-size: 13px;
    font-weight: 700;
    color: $color-text-primary;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 1px;
  }
  .pos-cust-trigger-caret {
    color: #be185d;
    flex: 0 0 auto;
    transition: transform 200ms ease;
    i { font-size: 14px; }
    svg { width: 14px; height: 14px; }
  }
  &:hover {
    border-color: #f43f5e;
    box-shadow: 0 4px 14px -6px rgba(244,63,94,0.4);
    transform: translateY(-1px);
    .pos-cust-trigger-caret { transform: rotate(180deg); }
  }
  &.is-active {
    background: linear-gradient(135deg, #fff1f5 0%, #fff5f8 100%);
  }
}

/* Backdrop centers the modal */
.cust-drawer-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1080;
  background: rgba(40, 10, 30, 0.5);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.cust-drawer {
  position: relative;
  width: 580px;
  max-width: 100%;
  max-height: min(720px, calc(100vh - 48px));
  background: #ffffff;
  border-radius: 20px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow:
    0 30px 70px -20px rgba(40, 10, 30, 0.4),
    0 12px 28px -12px rgba(40, 10, 30, 0.25);
}

/* Scale + fade transition */
.cust-drawer-enter-active,
.cust-drawer-leave-active {
  transition: opacity 220ms ease;
  .cust-drawer { transition: transform 260ms cubic-bezier(.4, 0, .2, 1), opacity 220ms ease; }
}
.cust-drawer-enter-from, .cust-drawer-enter,
.cust-drawer-leave-to {
  opacity: 0;
  .cust-drawer {
    transform: scale(0.94) translateY(8px);
    opacity: 0;
  }
}

.cust-drawer-close {
  position: absolute;
  top: 14px;
  right: 14px;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  border: 0;
  background: rgba(255,255,255,0.22);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 150ms ease, transform 150ms ease;
  z-index: 3;
  i { font-size: 16px; }
  svg { width: 16px; height: 16px; }
  &:hover { background: rgba(255,255,255,0.32); transform: rotate(90deg); }
}

/* Header */
.cust-drawer-header {
  position: relative;
  padding: 24px 24px 22px;
  display: flex;
  align-items: center;
  gap: 16px;
  background:
    radial-gradient(800px 220px at -10% -50%, rgba(255,255,255,0.22), transparent 60%),
    radial-gradient(700px 220px at 120% -10%, rgba(255,255,255,0.18), transparent 55%),
    linear-gradient(135deg, #be185d 0%, #f43f5e 50%, #fb7185 100%);
  color: #fff;
  overflow: hidden;
}
.cust-drawer-header::after {
  content: "";
  position: absolute;
  inset: auto -40px -80px auto;
  width: 240px;
  height: 240px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0) 70%);
  pointer-events: none;
}
.cust-drawer-hero-icon {
  position: relative;
  z-index: 1;
  width: 52px;
  height: 52px;
  border-radius: 16px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.22);
  border: 1px solid rgba(255,255,255,0.3);
  color: #fff;
  flex: 0 0 52px;
  box-shadow: 0 8px 22px -8px rgba(0,0,0,0.25);
  i { font-size: 24px; }
  svg { width: 24px; height: 24px; }
}
.cust-drawer-hero-text {
  position: relative;
  z-index: 1;
  flex: 1 1 auto;
  min-width: 0;
}
.cust-drawer-hero-eyebrow {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.85);
}
.cust-drawer-hero-title {
  margin-top: 2px;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.3px;
  line-height: 1.15;
}
.cust-drawer-hero-sub {
  margin-top: 4px;
  font-size: 12.5px;
  color: rgba(255,255,255,0.85);
  font-feature-settings: "tnum";
  font-variant-numeric: tabular-nums;
}
.cust-drawer-quick-add {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.3);
  background: rgba(255,255,255,0.15);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: background 150ms ease, transform 150ms ease;
  white-space: nowrap;
  flex: 0 0 auto;
  i { font-size: 13px; }
  svg { width: 13px; height: 13px; }
  &:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-1px);
  }
}

/* Search */
.cust-drawer-search {
  position: relative;
  padding: 14px 20px 6px;
  display: flex;
  align-items: center;
  border-bottom: 1px solid #f3eef0;
  > i, > svg {
    position: absolute;
    left: 32px;
    top: 50%;
    transform: translateY(-50%);
    color: $color-text-tertiary;
    pointer-events: none;
    width: 14px;
    height: 14px;
    font-size: 14px;
  }
  input {
    width: 100%;
    height: 42px;
    padding: 0 36px;
    border-radius: 12px;
    border: 1px solid #f3e0e8;
    background: #fdf8fa;
    font-size: 13px;
    color: $color-text-primary;
    transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
    &::placeholder { color: $color-text-tertiary; }
    &:focus {
      outline: none;
      border-color: #f43f5e;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(244,63,94,0.15);
    }
  }
}
.cust-drawer-search-clear {
  position: absolute;
  right: 26px;
  top: 50%;
  transform: translateY(-50%);
  width: 22px;
  height: 22px;
  border-radius: 6px;
  border: 0;
  background: transparent;
  color: $color-text-tertiary;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  i { font-size: 12px; }
  svg { width: 12px; height: 12px; position: static; transform: none; }
  &:hover { background: #f6e8ee; color: $color-text-primary; }
}

/* List */
.cust-drawer-list {
  flex: 1 1 auto;
  overflow-y: auto;
  padding: 10px 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  &::-webkit-scrollbar { width: 8px; }
  &::-webkit-scrollbar-thumb { background: #f3d7e2; border-radius: 99px; }
}
.cust-drawer-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid transparent;
  background: transparent;
  cursor: pointer;
  text-align: left;
  width: 100%;
  transition: background 150ms ease, border-color 150ms ease, transform 150ms ease, box-shadow 150ms ease;
  &:hover {
    background: #fdf6f9;
    border-color: #fbe4ed;
    transform: translateX(2px);
  }
  &.active {
    background: linear-gradient(135deg, #fff1f5 0%, #fff5f8 100%);
    border-color: rgba(244,63,94,0.4);
    box-shadow: 0 6px 16px -8px rgba(244,63,94,0.45);
    .cust-drawer-card-name { color: #be185d; }
  }
}
.cust-drawer-card-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  flex: 0 0 40px;
  letter-spacing: 0.2px;
  box-shadow: 0 4px 10px -4px rgba(0,0,0,0.2);
  &.clear {
    background: linear-gradient(135deg, #94a3b8, #cbd5e1);
    color: #fff;
    i { font-size: 18px; }
    svg { width: 18px; height: 18px; }
  }
}
.cust-drawer-card-clear {
  position: relative;
  margin-bottom: 6px;
  &::after {
    content: "";
    position: absolute;
    left: 12px;
    right: 12px;
    bottom: -3px;
    height: 1px;
    background: #f3eef0;
  }
  &.active {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-color: rgba(100,116,139,0.35);
    box-shadow: 0 6px 16px -8px rgba(100,116,139,0.4);
    .cust-drawer-card-name { color: #475569; }
    .cust-drawer-card-check {
      background: linear-gradient(135deg, #94a3b8, #cbd5e1);
      box-shadow: 0 4px 10px -4px rgba(100,116,139,0.5);
    }
  }
  &:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
  }
}
.cust-drawer-card-body {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.cust-drawer-card-name {
  font-size: 14px;
  font-weight: 600;
  color: $color-text-primary;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: -0.1px;
}
.cust-drawer-card-meta {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  color: $color-text-tertiary;
  font-family: 'JetBrains Mono', ui-monospace, monospace;
  i { font-size: 11px; }
  svg { width: 11px; height: 11px; }
}
.cust-drawer-card-check {
  flex: 0 0 auto;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f43f5e, #fb7185);
  color: #fff;
  box-shadow: 0 4px 10px -4px rgba(244,63,94,0.55);
  i { font-size: 14px; }
  svg { width: 14px; height: 14px; }
}

/* Empty */
.cust-drawer-empty {
  text-align: center;
  padding: 36px 16px 24px;
  color: $color-text-tertiary;
}
.cust-drawer-empty-icon {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(244,63,94,0.08);
  color: #f43f5e;
  margin-bottom: 10px;
  i { font-size: 24px; }
  svg { width: 24px; height: 24px; }
}
.cust-drawer-empty-text {
  font-size: 13px;
  font-weight: 500;
}

@media (max-width: 540px) {
  .cust-drawer-backdrop { padding: 0; align-items: stretch; }
  .cust-drawer {
    width: 100%;
    max-height: 100vh;
    border-radius: 0;
  }
  .cust-drawer-header {
    flex-wrap: wrap;
    padding: 18px 18px 16px;
    gap: 12px;
  }
  .cust-drawer-hero-title { font-size: 19px; }
  .cust-drawer-quick-add { width: 100%; justify-content: center; }
  .pos-cust-trigger { min-width: 0; max-width: 200px; height: 38px; }
  .pos-cust-trigger .pos-cust-trigger-eyebrow { display: none; }
}

/* ============================================
   TOTAL PAYABLE SECTION
   ============================================ */
.total-payable-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 0 20px;
  border-radius: $radius-md;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
  padding: 12px 20px;
}

/* Points convert UI */
.points-group {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 8px;
  align-items: center;
}

.convert-points-btn {
  border: 1px solid $color-border-light;
  background: white;
  color: #111827;
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.convert-points-btn:hover {
  border-color: #667eea;
  background: rgba(102,126,234,.06);
}
.convert-points-btn.converted {
  border: 1px solid #9CA3AF;
  color: #6B7280;
}

/* Redesigned points row */
.points-convert-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  align-items: stretch;
  padding: 10px;
  border: 1px solid $color-border-light;
  border-radius: 10px;
  background: #fff;
}
.points-convert-row.converted {
  border-color: #9CA3AF;
  background: #f9fafb;
}
.points-left { display: flex; flex-direction: column; gap: 6px; }
.points-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.label-line {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  color: $color-text-primary;
}
.points-value { font-weight: 700; color: $color-text-primary; text-align: right; }
.discount-hint {
  grid-column: 1 / -1;
  font-size: 12px;
  color: #10b981;
}
.points-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.points-actions .flat-input { width: 140px; max-width: 100%; }
.convert-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid #e5e7eb;
  background: white;
  color: #111827;
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
.convert-btn:hover { border-color: #667eea; background: rgba(102,126,234,.06); }
.convert-btn.converted { border-color: #9CA3AF; color: #6B7280; }
.convert-btn:focus,
.convert-btn:active,
.convert-btn:focus-visible { outline: none !important; box-shadow: none !important; }
.convert-btn { -webkit-tap-highlight-color: transparent; }

/* Backward compatibility for earlier class name */
.convert-points-btn:focus,
.convert-points-btn:active,
.convert-points-btn:focus-visible { outline: none !important; box-shadow: none !important; }
.convert-points-btn { -webkit-tap-highlight-color: transparent; }

@media (max-width: 576px) {
  .points-actions { justify-content: flex-start; flex-direction: column; align-items: stretch; }
  .points-actions .flat-input { width: 100%; }
  .convert-btn { width: 100%; justify-content: center; }
}

.payable-label {
  font-size: 11px;
  font-weight: 600;
  color: $color-text-tertiary;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.payable-amount {
  font-size: 20px;
  font-weight: 700;
  background: $color-gradient-primary;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.footer-divider {
  width: 1px;
  height: 40px;
  background: $color-border-light;
  margin: 0 8px;
}

/* ============================================
   PAGINATION FOOTER
   ============================================ */
.pagination-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 12px 16px;
  border-top: 1px solid $color-border-light;
  background: linear-gradient(to right, #fafbfc 0%, white 100%);
  flex-shrink: 0;
  position: sticky;
  bottom: 0;
  z-index: 50;
}

.pagination-btn {
  width: 36px;
  height: 36px;
  border: 1px solid $color-border-light;
  background: white;
  border-radius: $radius-sm;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: $transition-fast;
  color: $color-text-secondary;
  flex-shrink: 0;

  svg {
    width: 16px;
    height: 16px;
  }

  &:hover:not(:disabled) {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
    color: #667eea;
  }

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

.pagination-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 140px;

  .page-number {
    font-size: 12px;
    font-weight: 600;
    color: $color-text-primary;
  }

  .products-count {
    font-size: 11px;
    color: $color-text-tertiary;
  }
}

.pagination-dots {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: center;
  max-width: 300px;
}

.pagination-dot {
  width: 32px;
  height: 32px;
  border: 1px solid $color-border-light;
  background: white;
  border-radius: $radius-sm;
  cursor: pointer;
  font-size: 11px;
  font-weight: 600;
  color: $color-text-secondary;
  transition: $transition-fast;
  display: flex;
  align-items: center;
  justify-content: center;

  &:hover {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
    color: #667eea;
  }

  &.active {
    background: $color-gradient-primary;
    color: white;
    border-color: transparent;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.25);
  }
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */
@media (max-width: 1400px) {
  .pos-container {
    grid-template-columns: 400px 1fr;
    gap: 20px;
  }

  .products-grid {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  }

  .pos-header { gap: 16px; }

  .header-right {
    gap: 8px;

  }
}

@media (max-width: 1200px) {
  .pos-header { gap: 12px; }

  .summary-breakdown {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed var(--color-border);
    display: grid;
    grid-template-columns: 1fr;
    gap: 6px;
  }

  .bd-item {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #6b7280;
  }

  .brand-icon {
    width: 36px;
    height: 36px;
    font-size: 18px;
  }

  .brand-info h2 {
    font-size: 16px;
  }

  .brand-info p {
    font-size: 10px;
  }

}

@media (max-width: 1000px) {
  .pos-header { gap: 10px; align-items: stretch; flex-wrap: wrap; }

  .header-left {
    min-width: 0;
  }

  .brand-info h2 {
    font-size: 15px;
  }

  .header-center {
    order: 3;
    flex: 1 1 100%;
    grid-column: 1 / -1;

    .search-wrapper {
      width: 100%;
    }
  }

  .header-right {
    gap: 6px;
    flex-wrap: wrap;
    width: 100%;


    .user-profile {
      width: 36px;
      height: 36px;
      font-size: 12px;
      flex-shrink: 0;
    }
  }
  .pos-codecanyon {
    height: auto;
    min-height: 100vh;
    overflow: visible;
  }

  .pos-container {
    grid-template-columns: 1fr;
    gap: 16px;
    padding-bottom: 24px;
    height: auto;
    overflow: visible;
  }

  .pos-column-left {
    gap: 16px;
    height: auto;
    flex-direction: row;
    overflow-x: auto;
  }

  .card-added-products {
    max-height: none;
    flex: 0 0 45%;
    min-width: 0;
  }

  .card-summary {
    flex: 0 0 55%;
    min-width: 0;
  }

  .products-grid {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  }
  .products-container { padding-bottom: 16px; }
  .cart-section { max-height: none; }
  .pos-footer-bar { position: static; }
}

@media (max-width: 1024px) {
  .pos-header { gap: 12px; padding: 12px 16px; min-height: auto; position: static !important; height: auto; align-items: stretch; flex-wrap: wrap; }
  .pos-footer-bar { position: static; }

  /* Tablet layout: stack checkout above Available Products (single column) */
  .pos-codecanyon { height: auto; min-height: 100vh; overflow: visible; }
  .pos-container { grid-template-columns: 1fr; height: auto; overflow: visible; }
  .pos-column-left { height: auto; overflow: visible; }

  /* Checkout card: avoid cramped internal scroll on tablet */
  .cart-section { max-height: none; }
  .summary-section { padding-bottom: 16px; }

  /* Available Products card: remove excessive bottom padding on tablet */
  .products-container { padding-bottom: 16px; }

  /* Show brand icon at tablet size */
  .header-left {
    order: 1;
    display: flex !important;
    width: 100%;
    height: auto;
  }

  .header-center {
    order: 3;
    width: 100%;
    height: 40px;

    .search-wrapper {
      height: 40px;
      margin-top: 20px;
      
      > .action-btn-icon {
        width: 36px !important;
      }
    }
  }

  .header-right {
    order: 2;
    width: 100%;
    height: 40px;
    gap: 6px;
    flex-wrap: wrap;


    .user-profile {
      width: 40px;
      height: 40px;
      flex-shrink: 0;
    }
  }

  /* Small size language dropdown toggle (override Bootstrap-Vue) */
  ::v-deep(button#lang-dd__BV_toggle_) {
    width: 30px !important;
    height: 30px !important;
    min-width: 30px !important;
    min-height: 30px !important;
    line-height: 30px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
  }
  ::v-deep(button#lang-dd__BV_toggle_ > a.action-btn-icon),
  ::v-deep(button#lang-dd__BV_toggle_ .action-btn-icon) {
    width: 30px !important;
    height: 30px !important;
    padding: 0 !important;
    line-height: 30px !important;
  }

  .brand {
    gap: 8px;
  }

  .brand-icon {
    width: 32px;
    height: 32px;
    font-size: 16px;
  }

  .brand-info h2 {
    font-size: 14px;
  }

  .brand-info p {
    font-size: 10px;
  }

  .pos-container {
    padding: 12px 16px;
    gap: 12px;
  }

  /* Small screen spacing removed per request */

  /* Compact header icons on small screens */
  ::v-deep(.action-btn-icon) { width: 30px !important; height: 30px !important; }

  .pos-footer-bar {
    padding: 12px 16px;
    gap: 8px;
    flex-wrap: wrap;
    flex-direction: column;
    align-items: stretch;
  }

  .footer-status-indicator {
    width: 100%;
    justify-content: center;
    margin-bottom: 4px;
  }

  .footer-main-group {
    width: 100%;
    flex-wrap: wrap;
    justify-content: center;
  }

  .action-btn {
    padding: 10px 53px;
    font-size: 12px;
  }

  .action-btn-primary {
    flex: 1;
    min-width: 100%;
  }

  .total-payable-section {
    order: -1;
    width: 100%;
    margin-bottom: 8px;
  }


  .footer-divider {
    display: none;
  }

  .products-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  }
  .pos-autocomplete-results { left: 0; right: 0; }
  
  /* Filter section full width with 40/40/20 layout */
  .pos-header-filters {
    width: 100%;
    flex: 1 1 100%;
  }
  .pos-header-filters .category-select-header,
  .pos-header-filters .brand-select-header {
    flex: 1 1 0;
    min-width: 0;
  }

  .header-right .btn-offline-status {
    position: relative;
  }

  .header-right .btn-offline-status.is-offline {
    border-color: $color-danger;
    background: $color-danger;
    color: #fff;
  }

  .header-right .btn-offline-status.is-offline i {
    color: #fff;
  }

  .header-right .btn-offline-status .offline-badge {
    position: absolute;
    top: -4px;
    right: -2px;
    background: $color-danger;
    color: #fff;
    border-radius: 999px;
    padding: 0 4px;
    font-size: 10px;
    line-height: 1.4;
  }

  /* Hide Available Products heading on small screens */
  .card.card-products .card-header > h3 { display: none !important; }
}

@media (max-width: 640px) {
  .pos-header { padding: 10px 12px; gap: 10px; min-height: auto; }

  .header-center {
    height: 38px;

    .search-wrapper {
      height: 38px;
    }

    .search-input {
      padding: 0 10px 0 36px;
      font-size: 12px;
    }

    .search-icon {
      width: 16px;
      height: 16px;
      left: 10px;
    }
  }

  .header-right {
    height: 38px;
    gap: 6px;


    .user-profile {
      width: 38px;
      height: 38px;
      font-size: 11px;
    }
  }

  .pos-container {
    padding: 12px 12px;
    gap: 10px;
  }

  .card-header {
    padding: 14px 16px;
    h3 {
      font-size: 15px;
    }
  }

  .charges-inline {
    gap: 6px;
  }

  .charge-col .charge-input-group .flat-input {
    font-size: 12px;
    padding: 3px 6px;
  }

  .products-grid {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
  }
  
  /* Hide elements on small screens */
  .header-left { display: none !important; }
  .card.card-products .card-header > h3 { display: none !important; }

  .pagination-footer {
    gap: 8px;
    padding: 10px 12px;
  }

  .pagination-dots {
    max-width: 250px;
  }

  .pos-footer-bar {
    padding: 10px 12px;
    gap: 6px;
  }

  .total-payable-section {
    padding: 10px 12px;
  }

  .payable-amount {
    font-size: 18px;
  }

  /* Header filters full width on ≤640px */
  .pos-header-filters {
    width: 100%;
    flex: 1 1 100%;
  }
  .pos-header-filters .category-select-header,
  .pos-header-filters .brand-select-header {
    flex: 1 1 0;
    min-width: 0;
  }
}

@media (max-width: 768px) {
  .pos-header { padding: 20px 10px; gap: 1px; min-height: auto; }
  /* Swap headers: hide desktop header, show mobile header */
  .pos-header { display: none !important; }
  .pos-header-mobile { display: block; padding: 12px 10px; background: $color-card-bg; border-bottom: 1px solid $color-border-light; box-shadow: $shadow-md; }

  .pos-header-mobile .mobile-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
  .pos-header-mobile .mobile-row:last-child { margin-bottom: 0; }
  .pos-header-mobile .mobile-top { justify-content: space-between; }
  .pos-header-mobile .mobile-top .brand { display: flex; align-items: center; }
  .pos-header-mobile .mobile-top .brand .brand-icon { width: 44px; height: 44px; border-radius: $radius-md; display: flex; align-items: center; justify-content: center; font-weight: 700; }
  .pos-header-mobile .mobile-top .top-icons { display: inline-flex; align-items: center; gap: 6px; }
  /* Keep icon containers same size as desktop */
  .pos-header-mobile .mobile-top .top-icons .action-btn-icon { width: 40px !important; height: 40px !important; display: inline-flex; align-items: center; justify-content: center; }
  .pos-header-mobile .mobile-top .top-icons .btn-pos-settings { width: 40px !important; height: 40px !important; }
  .pos-header-mobile .mobile-top .top-icons .btn-offline-status { width: 40px !important; height: 40px !important; }
  .pos-header-mobile .mobile-top .top-icons .btn-offline-status.is-offline { border-color: $color-danger; background: $color-danger; color: #fff; }
  .pos-header-mobile .mobile-top .top-icons .btn-offline-status.is-offline i { color: #fff; }
  .pos-header-mobile .mobile-top .top-icons .btn-offline-status .offline-badge { top: -4px; right: -2px; background: $color-danger; color: #fff; border-radius: 999px; padding: 0 4px; font-size: 10px; line-height: 1.4; }
  .pos-header-mobile .mobile-top .top-icons .user-profile { width: 40px !important; height: 40px !important; }
  /* Bootstrap-Vue language toggle button size */
  ::v-deep(button#lang-dd-mobile__BV_toggle_) { width: 40px !important; height: 40px !important; min-width: 40px !important; min-height: 40px !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }
  ::v-deep(button#lang-dd-mobile__BV_toggle_ > a.action-btn-icon),
  ::v-deep(button#lang-dd-mobile__BV_toggle_ .action-btn-icon) { width: 40px !important; height: 40px !important; }

  /* Bootstrap-Vue user dropdown container and toggle size */
  .pos-header-mobile #user-dd-mobile { width: 40px !important; height: 40px !important; }
  ::v-deep(button#user-dd-mobile__BV_toggle_) { width: 40px !important; height: 40px !important; min-width: 40px !important; min-height: 40px !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }

  .pos-header-mobile .warehouse-select,
  .pos-header-mobile .customer-select-header,
  .pos-header-mobile .category-select-header,
  .pos-header-mobile .brand-select-header {
    width: 100%;
  }

  /* On mobile hide category/brand in header; they appear below search in products card */
  .pos-header-mobile .mobile-row-category,
  .pos-header-mobile .mobile-row-brand {
    display: none !important;
  }

  /* Show category & brand below autocomplete search in products card */
  .card-products-mobile-filters {
    display: flex;
    flex-direction: column;
    width: 100%;
    gap: 8px;
    margin-top: 10px;
  }
  .card-products-mobile-filters .category-select-header,
  .card-products-mobile-filters .brand-select-header {
    width: 100%;
  }

  /* Reuse existing register button neutral style */
  .pos-header-mobile .register-status { display: inline-flex; align-items: center; gap: 6px; margin-left: auto; }
  .pos-header-mobile .register-toggle-btn { background: $color-bg-light; color: $color-text-primary; border: 1px solid $color-border-light; padding: 4px 10px; font-weight: 600; }
  /* Mobile-only POS header layout */
  .pos-header {
    position: static !important;
    height: auto !important;
    flex-wrap: wrap; /* allow stacking below top row */
    align-items: center; /* align brand with icons on the top row */
  }
  /* Ensure brand is visible and first */
  .header-left {
    display: flex !important;
    order: 0;
    width: auto;
    height: 36px;
    align-items: center;
  }

  .header-center {
    height: 36px;

    .search-wrapper {
      height: 36px;
    }

    .search-input {
      padding: 0 8px 0 32px;
      font-size: 11px;
    }

    .search-icon {
      width: 14px;
      height: 14px;
      left: 8px;
    }
  }

  /* Place search right below register-status */
  .header-center {
    order: 5;
    width: 100%;

    .search-wrapper {
      margin-top: 0;

      > .action-btn-icon {
        width: 32px !important;
      }
    }

    .search-input {
      padding: 0 36px 0 32px;
    }
  }

  .header-right {
    height: 36px;
    gap: 6px;


    .user-profile {
      width: 36px;
      height: 36px;
      font-size: 10px;
    }
  }

  /* Arrange header-right content rows and ordering */
  .header-right {
    order: 1;
    width: auto;
    flex: 1 1 auto;
    display: flex;
    flex-wrap: wrap;
    align-content: flex-start;
    min-width: 0;
  }

  /* Top row items: receipt, language, profile (brand is separate in .header-left) */
  .header-right > .action-btn-icon { order: 1; }
  .header-right > .dropdown.action-btn-icon { order: 2; display: inline-flex !important; }
  .header-right > .dropdown:not(.action-btn-icon) { order: 3; }

  /* Next rows: register, search (as sibling), then selects full width; move POS settings below */
  .header-right > .register-status { order: 4; flex: 1 1 100%; min-width: 0; }
  .header-right > .warehouse-select { order: 6; flex: 1 1 100%; min-width: 0; }
  .header-right > .customer-select-header { order: 7; flex: 1 1 100%; min-width: 0; }
  .header-right > .btn-pos-settings { order: 8; display: inline-flex !important; }

  /* Keep brand and icons on the same row */
  .header-left { flex: 0 0 auto; }
  .header-right { flex: 1 1 auto; }

  .brand {
    gap: 6px;
  }

  .brand-icon {
    width: 28px;
    height: 28px;
    font-size: 14px;
  }

  .brand-info h2 {
    font-size: 12px;
  }

  .brand-info p {
    font-size: 9px;
  }

  .pos-container {
    padding: 8px 10px;
    padding-bottom: 100px;
    gap: 8px;
  }

  .pos-column-left {
    gap: 12px;
  }

  .card-header {
    padding: 12px 12px;
    h3 {
      font-size: 13px;
    }
  }

  .charges-inline {
    gap: 4px;
  }

  .charge-col label {
    font-size: 9px;
  }

  .charge-col .charge-input-group .flat-input {
    font-size: 12px;
    padding: 3px 6px;
  }

  .products-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
  }

  .pagination-footer {
    gap: 6px;
    padding: 8px 10px;
  }

  .pagination-info {
    min-width: 120px;
  }

  .pagination-dots {
    max-width: 200px;
  }

  .pagination-dot {
    width: 28px;
    height: 28px;
    font-size: 10px;
  }

  .product-image-wrapper {
    height: 120px;
  }

  .product-details {
    padding: 10px;

    .product-name {
      font-size: 11px;
    }

    .product-brand {
      font-size: 9px;
    }

    .product-stock {
      font-size: 10px;
    }

    .product-footer {
      .product-price {
        font-size: 12px;
      }
    }
  }

  .pos-footer-bar {
    padding: 8px 10px;
    gap: 4px;

    /* 2x2 footer buttons grid (Home / Reset / Recent Drafts / Hold) */
    .footer-main-group {
      width: 100%;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px;
      justify-content: stretch;
      align-items: stretch;
    }

    /* Remove spacer in grid layout */
    .footer-space {
      display: none;
    }

    /* Secondary actions: 50% / 50% */
    .footer-main-group .action-btn.action-btn-secondary {
      width: 100%;
      justify-content: center;
      padding: 8px 10px;
      font-size: 10px;
    }

    /* Total + Pay Now full width below the 2x2 button grid */
    .total-payable-section,
    .action-btn-primary {
      grid-column: 1 / -1;
      width: 100%;
    }

    .action-btn {
      padding: 8px 12px;
      font-size: 10px;
    }

    .action-btn-icon {
      width: 36px;
      height: 36px;

      svg {
        width: 14px;
        height: 14px;
      }
    }

    .action-btn-primary {
      min-width: 100%;
      padding: 8px 12px;
    }
  }
}

.premium-payment-modal {
  --color-primary: #667eea;
  --color-secondary: #764ba2;
  --color-success: #10b981;
  --color-danger: #ef4444;
  --color-warning: #f59e0b;
  --color-border: #e5e7eb;
  --color-bg: #f8f9fb;
  --color-text: #1a1a2e;
  --color-gray: #6b7280;
}

.payment-checkout-wrapper {
  display: flex;
  flex-direction: column;
  max-height: 90vh;
  background: white;
  border-radius: 16px;
  overflow: hidden;
}

/* HEADER */
.checkout-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 28px 32px;
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
  color: white;
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.2);
}

.checkout-header-content {
  flex: 1;
}

.checkout-title {
  margin: 0;
  font-size: 28px;
  font-weight: 700;
  letter-spacing: -0.5px;
}

.checkout-subtitle {
  margin: 4px 0 0 0;
  font-size: 14px;
  opacity: 0.95;
}

.checkout-close {
  background: none;
  border: none;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 10px;
  transition: all 0.2s;

  &:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: scale(1.1);
  }

  svg {
    width: 24px;
    height: 24px;
    stroke-width: 2.5;
  }
}

/* BODY */
.checkout-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px 32px;
}

.checkout-form {
  display: contents;
}

.checkout-row {
  display: grid;
  grid-template-columns: 1fr 1.3fr;
  gap: 28px;

  @media (max-width: 1024px) {
    grid-template-columns: 1fr;
    gap: 20px;
  }
}

/* ORDER SUMMARY */
.order-summary-card {
  background: linear-gradient(135deg, var(--color-bg) 0%, #ffffff 100%);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  height: fit-content;
  position: sticky;
  top: 0;
}

.summary-card-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.summary-items {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}

.summary-label {
  color: var(--color-gray);
  font-weight: 500;
}

.summary-value {
  color: var(--color-text);
  font-weight: 600;

  &.text-danger {
    color: var(--color-danger);
  }
}

.summary-divider {
  height: 1px;
  background: var(--color-border);
  margin: 4px 0;
}

.summary-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
  border-radius: 8px;
}

.total-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-gray);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.total-amount {
  font-size: 20px;
  font-weight: 700;
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.payment-status {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.status-item {
  text-align: center;
  padding: 10px;
  background: var(--color-bg);
  border-radius: 8px;
}

.status-label {
  display: block;
  font-size: 11px;
  color: var(--color-gray);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
  margin-bottom: 4px;
}

.status-value {
  display: block;
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text);

  &.text-warning {
    color: var(--color-warning);
  }

  &.text-success {
    color: var(--color-success);
  }
}

/* PAYMENT FORM */
.payment-form-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-title {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.line-count {
  font-size: 12px;
  color: var(--color-gray);
  background: var(--color-bg);
  padding: 4px 8px;
  border-radius: 4px;
}

/* PAYMENT METHOD TABS */
.payment-method-tabs {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  gap: 10px;
}

.method-tab {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 12px;
  border: 2px solid var(--color-border);
  background: white;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text);

  &:hover {
    border-color: var(--color-primary);
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.04) 0%, rgba(118, 75, 162, 0.04) 100%);
  }

  &.active {
    border-color: var(--color-primary);
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    color: var(--color-primary);
  }
}

.method-icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;

  svg {
    width: 100%;
    height: 100%;
    stroke-width: 1.5;
  }
}

.method-name {
  text-align: center;
  font-size: 11px;
}

/* PAYMENT LINES */
.payment-lines-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.payment-line {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  transition: all 0.2s;

  &:hover {
    background: white;
    border-color: var(--color-primary);
  }
}

.line-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  min-width: 32px;
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
  color: white;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
}

.line-content {
  flex: 1;
}

.amount-input-group {
  font-size: 13px;
}

.line-amount-input {
  border: none;
  background: white;
  border-radius: 6px;
  font-weight: 600;
  font-size: 14px;

  &:focus {
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
}

.line-remove-btn {
  padding: 4px 8px !important;
  color: var(--color-danger);
  font-size: 16px;
}

.add-line-btn {
  border-color: var(--color-primary);
  color: var(--color-primary);
  margin-top: 4px;
  font-weight: 600;
}

/* QUICK AMOUNT */
.quick-amount-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.quick-btn {
  font-weight: 600;
  border-color: var(--color-border);
  transition: all 0.2s;

  &:hover {
    border-color: var(--color-primary);
    color: var(--color-primary);
  }
}

/* KEYPAD */
.keypad {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.keypad-key {
  padding: 12px;
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s;

  &:hover {
    border-color: var(--color-primary);
    background: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
  }

  &:active {
    transform: translateY(0);
  }
}

/* CREDIT CARD */
.saved-cards {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.saved-cards-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  font-weight: 600;
}

.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
}

.card-option {
  padding: 16px 12px;
  background: white;
  border: 2px solid var(--color-border);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: center;
  position: relative;

  &:hover {
    border-color: var(--color-primary);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
  }

  &.selected {
    border-color: var(--color-primary);
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
  }
}

.card-chip {
  width: 40px;
  height: 30px;
  background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
  border-radius: 4px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.card-last-four {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text);
}

.card-exp {
  font-size: 11px;
  color: var(--color-gray);
}

.card-checkmark {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 24px;
  height: 24px;
  background: var(--color-success);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
}

.new-card-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.card-form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-gray);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stripe-card-element {
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  background: white;
}

.card-errors {
  color: var(--color-danger);
  font-size: 12px;
}

/* FORM ELEMENTS */
.form-label-sm {
  font-size: 12px !important;
  font-weight: 600 !important;
  color: var(--color-gray) !important;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.form-textarea-sm {
  font-size: 13px;
  border: 1px solid var(--color-border);
  border-radius: 6px;

  &:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
}

.checkboxes-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.checkbox-item {
  font-size: 13px;
  color: var(--color-text);

  i {
    margin-right: 8px;
    font-size: 14px;
  }
}

/* FOOTER */
.checkout-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 32px;
  border-top: 1px solid var(--color-border);
  background: var(--color-bg);
  flex-wrap: wrap;
}

.footer-info {
  display: flex;
  align-items: center;
  gap: 24px;
  flex-wrap: wrap;
}

.footer-amount {
  display: flex;
  flex-direction: column;

  .label {
    font-size: 11px;
    color: var(--color-gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
  }

  .amount {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-text);

    &.text-warning {
      color: var(--color-warning);
    }

    &.text-success {
      color: var(--color-success);
    }
  }
}

.footer-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-cancel {
  padding: 10px 24px;
  font-weight: 600;
  font-size: 13px;
}

.btn-pay {
  padding: 10px 32px;
  background: linear-gradient(135deg, var(--color-success) 0%, #059669 100%);
  border: none;
  color: white;
  font-weight: 600;
  min-width: 160px;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);

  &:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35);
  }

  &:disabled {
    opacity: 0.6;
  }

  i {
    margin-right: 8px;
  }
}

/* RESPONSIVE */
@media (max-width: 1024px) {
  .payment-checkout-wrapper {
    max-height: 100vh;
  }

  .checkout-body {
    padding: 16px;
  }

  .checkout-row {
    gap: 16px;
  }

  .order-summary-card {
    position: static;
    padding: 16px;
  }

  .payment-method-tabs {
    grid-template-columns: repeat(2, 1fr);
  }

  .quick-amount-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .keypad {
    grid-template-columns: repeat(3, 1fr);
  }

  .checkout-footer {
    flex-direction: column;
    align-items: flex-end;
    padding: 12px 16px;
  }

  .footer-info {
    width: 100%;
    justify-content: space-around;
  }

  .footer-actions {
    width: 100%;
    gap: 8px;

    button {
      flex: 1;
    }
  }
}

/* ============================================================================
   ============================================================================
   REFINED CLASSIC OVERRIDES
   These rules win by source order over earlier ones in this scoped block.
   They reskin the visible POS shell to match POS.html (Inter font, purple
   accent, white surfaces, slim toolbar, pill chips, big primary pay button).
   Modal-specific styles further up are intentionally untouched.
   ============================================================================
   ============================================================================ */

.pos-codecanyon {
  background: var(--bg);
  color: var(--ink);
  font-family: var(--font-sans);
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* ---------- Top toolbar (desktop) ---------- */
.pos-codecanyon .pos-header {
  min-height: 56px;
  padding: 8px 16px;
  background: var(--surface);
  border-bottom: 1px solid var(--line);
  box-shadow: none;
  gap: 10px;
  align-items: center;
}

.pos-codecanyon .pos-header .header-left {
  padding-right: 12px;
  border-right: 1px solid var(--line);
  height: 36px;
  display: flex;
  align-items: center;
}

.pos-codecanyon .pos-header .brand {
  gap: 10px;
}

.pos-codecanyon .pos-header .brand .brand-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--accent);
  color: #fff;
  font-family: var(--font-mono);
  font-weight: 700;
  font-size: 14px;
  letter-spacing: 0.02em;
  box-shadow: 0 2px 6px var(--accent-shadow);
}

.pos-codecanyon .pos-header .brand .brand-icon img {
  border-radius: 8px !important;
}

/* Register status: keep functional buttons, restyle */
.pos-codecanyon .pos-header .register-status,
.pos-codecanyon .pos-header-mobile .register-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0;
  background: transparent;
  border: 0;
  height: 36px;
}

.pos-codecanyon .register-toggle-icon {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  background: var(--soft);
  border: 1px solid var(--line);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  cursor: pointer;
  padding: 0;
}

.pos-codecanyon .register-toggle-btn,
.pos-codecanyon .register-toggle-btn.btn,
.pos-codecanyon .register-toggle-btn.btn-secondary {
  height: 28px;
  padding: 0 10px;
  font-size: 12px;
  font-weight: 500;
  background: var(--surface);
  color: var(--ink-2);
  border: 1px solid var(--line);
  border-radius: 7px;
  box-shadow: none;
}

.pos-codecanyon .register-toggle-btn:hover {
  background: var(--soft);
  color: var(--ink);
}

.pos-codecanyon .register-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.06em;
  font-family: var(--font-mono);
  text-transform: uppercase;
}

.pos-codecanyon .register-status-pill.is-open {
  background: var(--success-soft);
  color: #1e7a44;
}

.pos-codecanyon .register-status-pill.is-closed {
  background: #fdecec;
  color: #a83232;
}

.pos-codecanyon .register-status-pill .dot {
  width: 6px;
  height: 6px;
  border-radius: 99px;
  background: currentColor;
}

/* v-select pills in header (warehouse / category / brand / customer) */
.pos-codecanyon .pos-header .warehouse-select,
.pos-codecanyon .pos-header .category-select-header,
.pos-codecanyon .pos-header .brand-select-header,
.pos-codecanyon .pos-header .customer-select-header {
  min-width: 140px;
  max-width: 200px;
}

.pos-codecanyon .pos-header ::v-deep(.warehouse-select .vs__dropdown-toggle),
.pos-codecanyon .pos-header ::v-deep(.category-select-header .vs__dropdown-toggle),
.pos-codecanyon .pos-header ::v-deep(.brand-select-header .vs__dropdown-toggle),
.pos-codecanyon .pos-header ::v-deep(.customer-select-header .vs__dropdown-toggle) {
  height: 32px;
  min-height: 32px;
  padding: 0 8px;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 7px;
  box-shadow: none;
  font-size: 12px;
}

.pos-codecanyon .pos-header ::v-deep(.vs__placeholder),
.pos-codecanyon .pos-header ::v-deep(.vs__selected) {
  font-size: 12px;
  color: var(--ink-2);
}

.pos-codecanyon .pos-header ::v-deep(.vs__open-indicator) {
  fill: var(--ink-3);
  scale: 0.8;
}

.pos-codecanyon ::v-deep(.vs__dropdown-menu) {
  min-width: 0;
}

/* Header icon buttons (32×32 squared) */
.pos-codecanyon .pos-header .action-btn-icon,
.pos-codecanyon .pos-header-mobile .action-btn-icon {
  width: 32px;
  height: 32px;
  min-width: 32px;
  border-radius: 8px;
  border: 1px solid var(--line);
  background: var(--surface);
  color: var(--ink-2);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  box-shadow: none;
  transition: all 120ms ease;
}

.pos-codecanyon .pos-header .action-btn-icon:hover,
.pos-codecanyon .pos-header-mobile .action-btn-icon:hover {
  background: var(--soft);
  color: var(--ink);
  border-color: var(--line-strong);
}

.pos-codecanyon .pos-header .action-btn-icon i,
.pos-codecanyon .pos-header-mobile .action-btn-icon i {
  font-size: 15px;
}

/* Offline status badge dot */
.pos-codecanyon .btn-offline-status .offline-badge {
  background: var(--danger);
  color: #fff;
  font-family: var(--font-mono);
  font-size: 10px;
  font-weight: 700;
  border-radius: 99px;
  padding: 1px 5px;
  position: absolute;
  top: -4px;
  right: -4px;
}

/* User avatar */
.pos-codecanyon ::v-deep(.user-profile) {
  width: 32px;
  height: 32px;
  border-radius: 99px;
  background: linear-gradient(135deg, var(--accent), #b65cd6);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 13px;
  object-fit: cover;
}

/* Quick add customer button accent */
.pos-codecanyon .btn-new-customer {
  background: var(--accent-tint) !important;
  color: var(--accent) !important;
  border-color: var(--accent-soft) !important;
}

/* ---------- Mobile top bar ---------- */
@media (max-width: 480px) {
  .pos-codecanyon .pos-header { display: none; }
  .pos-codecanyon .pos-header-mobile {
    display: block;
    background: var(--surface);
    border-bottom: 1px solid var(--line);
    padding: 8px 12px;
  }
  .pos-codecanyon .pos-header-mobile .mobile-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
  }
  .pos-codecanyon .pos-header-mobile .mobile-row:last-child { margin-bottom: 0; }
  .pos-codecanyon .pos-header-mobile .mobile-top {
    justify-content: space-between;
  }
  .pos-codecanyon .pos-header-mobile .top-icons {
    display: inline-flex;
    gap: 6px;
  }
}

/* ---------- Main split (cart left | products right) ---------- */
.pos-codecanyon .pos-container {
  display: grid;
  grid-template-columns: 380px 1fr;
  gap: 0;
  flex: 1;
  min-height: 0;
  background: var(--bg);
  padding: 0;
  overflow: hidden;
}

.pos-codecanyon .pos-column-left {
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--line);
  background: var(--surface);
  min-height: 0;
  padding: 0;
}

.pos-codecanyon .card-unified-checkout {
  display: grid;
  grid-template-rows: auto 1fr auto;
  background: var(--surface);
  border: 0;
  border-radius: 0;
  box-shadow: none;
  height: 100%;
  min-height: 0;
}

.pos-codecanyon .card-unified-checkout .card-header {
  padding: 14px 16px;
  border-bottom: 1px solid var(--line);
  background: var(--surface);
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-radius: 0;
}

.pos-codecanyon .card-unified-checkout .card-header h3 {
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
  margin: 0;
  letter-spacing: -0.01em;
}

.pos-codecanyon .card-unified-checkout .badge-count {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  background: var(--accent-tint);
  color: var(--accent);
  border-radius: 99px;
  border: 0;
}

/* Cart list */
.pos-codecanyon .cart-section {
  overflow-y: auto;
  padding: 8px;
  background: var(--surface);
  min-height: 0;
}

.pos-codecanyon .cart-items-grid {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pos-codecanyon .cart-item-card {
  background: transparent;
  border: 0;
  border-radius: 10px;
  padding: 10px;
  margin: 0;
  box-shadow: none;
  display: grid;
  grid-template-columns: 1fr auto;
  grid-template-areas:
    "name actions"
    "sku  actions"
    "qty  price"
    "batches batches";
  gap: 4px 10px;
  transition: background 120ms ease;
}

.pos-codecanyon .cart-item-card:hover { background: var(--soft); }

.pos-codecanyon .cart-item-card .item-header {
  grid-area: name;
  display: contents;
}

.pos-codecanyon .cart-item-card .item-name {
  grid-area: name;
  font-size: 13px;
  font-weight: 500;
  color: var(--ink);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.3;
}

.pos-codecanyon .cart-item-card .item-sku {
  grid-area: sku;
  font-size: 10px;
  color: var(--ink-3);
  font-family: var(--font-mono);
  margin: 0;
  letter-spacing: 0.02em;
}

.pos-codecanyon .cart-item-card .edit-btn,
.pos-codecanyon .cart-item-card .remove-btn {
  grid-area: actions;
  width: 22px;
  height: 22px;
  background: transparent;
  border: 0;
  color: var(--ink-3);
  padding: 0;
  border-radius: 5px;
  align-self: start;
  justify-self: end;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.pos-codecanyon .cart-item-card .edit-btn:hover { color: var(--accent); background: var(--accent-tint); }
.pos-codecanyon .cart-item-card .remove-btn:hover { color: var(--danger); background: #fdecec; }
.pos-codecanyon .cart-item-card .edit-btn svg,
.pos-codecanyon .cart-item-card .remove-btn svg { width: 13px; height: 13px; }

/* Stack edit + remove vertically */
.pos-codecanyon .cart-item-card .edit-btn { transform: translateY(0); }
.pos-codecanyon .cart-item-card .remove-btn { transform: translateY(26px); }

.pos-codecanyon .cart-item-card .item-qty-section { grid-area: qty; }

.pos-codecanyon .cart-item-card .qty-controller {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--line);
  border-radius: 6px;
  height: 26px;
  background: var(--surface);
}

.pos-codecanyon .cart-item-card .qty-btn {
  width: 24px;
  height: 24px;
  background: transparent;
  border: 0;
  color: var(--ink-2);
  font-size: 14px;
  cursor: pointer;
  padding: 0;
}
.pos-codecanyon .cart-item-card .qty-btn:hover { color: var(--accent); }

.pos-codecanyon .cart-item-card .qty-input {
  width: 36px;
  height: 100%;
  border: 0;
  text-align: center;
  font-size: 12px;
  font-family: var(--font-mono);
  background: transparent;
  color: var(--ink);
  outline: none;
  padding: 0;
}

.pos-codecanyon .cart-item-card .item-price {
  grid-area: price;
  text-align: right;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: flex-end;
  gap: 4px;
}

.pos-codecanyon .cart-item-card .item-amount {
  font-size: 13px;
  font-weight: 600;
  font-family: var(--font-mono);
  color: var(--ink);
}

.pos-codecanyon .cart-item-card .pos-price-select {
  height: 22px;
  padding: 0 4px;
  font-size: 10px;
  border: 1px solid var(--line);
  border-radius: 4px;
  background: var(--surface);
  color: var(--ink-2);
  width: auto;
  min-width: 80px;
}

.pos-codecanyon .cart-item-card .item-subtotal {
  font-size: 10px;
  color: var(--ink-3);
}

.pos-codecanyon .cart-item-card .subtotal-value {
  font-family: var(--font-mono);
  color: var(--ink-2);
  font-weight: 500;
}

.pos-codecanyon .cart-item-card .item-batches-panel {
  grid-area: batches;
  margin-top: 6px;
  padding: 8px;
  background: var(--soft);
  border-radius: 8px;
  border: 0;
}

.pos-codecanyon .cart-item-card .item-batches-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.pos-codecanyon .cart-item-card .item-batches-title {
  font-size: 11px;
  font-weight: 600;
  color: var(--ink-2);
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.pos-codecanyon .cart-item-card .item-batches-count {
  font-size: 10px;
  color: var(--ink-3);
  font-family: var(--font-mono);
  font-weight: 400;
  margin-left: 4px;
}

.pos-codecanyon .cart-item-card .item-batches-add-btn {
  height: 24px;
  padding: 0 8px;
  background: var(--accent-tint);
  color: var(--accent);
  border: 0;
  border-radius: 5px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
}

/* Empty cart */
.pos-codecanyon .empty-state {
  padding: 32px 16px;
  text-align: center;
  color: var(--ink-3);
}
.pos-codecanyon .empty-state svg {
  width: 32px;
  height: 32px;
  opacity: 0.3;
  margin-bottom: 8px;
}
.pos-codecanyon .empty-state p {
  font-size: 13px;
  font-weight: 500;
  color: var(--ink-2);
  margin: 0 0 4px 0;
}
.pos-codecanyon .empty-state .empty-hint {
  font-size: 11px;
  color: var(--ink-3);
}

/* Summary section */
.pos-codecanyon .summary-section {
  padding: 12px 16px;
  border-top: 1px solid var(--line);
  background: var(--bg);
}

.pos-codecanyon .charges-section { margin-bottom: 10px; }

.pos-codecanyon .charges-inline {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 8px;
}

.pos-codecanyon .charges-inline .charge-col label {
  display: block;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--ink-3);
  text-transform: uppercase;
  margin-bottom: 4px;
}

.pos-codecanyon .charges-inline .charge-input-group {
  display: flex;
  align-items: center;
  height: 28px;
  border: 1px solid var(--line);
  border-radius: 6px;
  background: var(--surface);
  overflow: hidden;
}

.pos-codecanyon .charges-inline .flat-input {
  flex: 1;
  border: 0;
  height: 100%;
  padding: 0 8px;
  background: transparent;
  font-size: 12px;
  font-family: var(--font-mono);
  color: var(--ink);
  outline: none;
  width: 100%;
  min-width: 0;
}

.pos-codecanyon .charges-inline .input-suffix,
.pos-codecanyon .charges-inline .discount-type-toggle {
  padding: 0 8px;
  height: 100%;
  display: inline-flex;
  align-items: center;
  font-size: 11px;
  color: var(--ink-3);
  font-family: var(--font-mono);
  background: transparent;
  border: 0;
  border-left: 1px solid var(--line);
  cursor: default;
}

.pos-codecanyon .charges-inline .discount-type-toggle {
  cursor: pointer;
  color: var(--accent);
  font-weight: 600;
}

/* Points convert row */
.pos-codecanyon .points-convert-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px;
  background: var(--accent-tint);
  border-radius: 8px;
  margin-top: 10px;
}
.pos-codecanyon .points-convert-row .points-header { font-size: 11px; color: var(--ink-2); }
.pos-codecanyon .points-convert-row .points-value { font-size: 14px; font-weight: 700; color: var(--accent); font-family: var(--font-mono); }
.pos-codecanyon .points-convert-row .convert-btn {
  height: 28px;
  padding: 0 10px;
  background: var(--accent);
  color: #fff;
  border: 0;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
}
.pos-codecanyon .points-convert-row .convert-btn.converted { background: var(--success); }

/* Totals */
.pos-codecanyon .summary-totals {
  margin-top: 4px;
}

.pos-codecanyon .summary-totals .total-row {
  display: flex;
  justify-content: space-between;
  padding: 4px 0;
  font-size: 12px;
}

.pos-codecanyon .summary-totals .total-label {
  color: var(--ink-2);
  font-weight: 400;
}

.pos-codecanyon .summary-totals .total-value {
  color: var(--ink);
  font-family: var(--font-mono);
  font-weight: 500;
}

.pos-codecanyon .summary-totals .discount-row { color: var(--ink-3); }
.pos-codecanyon .summary-totals .discount-value { color: var(--danger); }

.pos-codecanyon .summary-totals .summary-divider {
  height: 1px;
  background: var(--line);
  margin: 8px 0;
}

.pos-codecanyon .summary-totals .grand-total {
  padding-top: 4px;
}
.pos-codecanyon .summary-totals .grand-total .total-label {
  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ink-3);
  font-weight: 700;
}
.pos-codecanyon .summary-totals .grand-total .total-value,
.pos-codecanyon .summary-totals .grand-total .gradient-text {
  font-size: 18px;
  font-weight: 700;
  font-family: var(--font-mono);
  color: var(--ink);
  background: none;
  -webkit-text-fill-color: initial;
}

/* ---------- Products card (right) ---------- */
.pos-codecanyon .card-products {
  display: grid;
  grid-template-rows: auto 1fr auto;
  background: var(--surface);
  border: 0;
  border-radius: 0;
  box-shadow: none;
  height: 100%;
  min-height: 0;
}

.pos-codecanyon .card-products .card-header {
  padding: 14px 20px 8px;
  border-bottom: 1px solid var(--line);
  background: var(--surface);
  display: flex;
  align-items: center;
  gap: 10px;
}

.pos-codecanyon .card-products .card-header > h3 { display: none; }

.pos-codecanyon .card-products .products-search-wrapper {
  flex: 1;
  width: auto;
}

.pos-codecanyon .card-products .search-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  height: 44px;
  width: 100%;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 10px;
  transition: border-color 120ms ease, box-shadow 120ms ease;
}

.pos-codecanyon .card-products .search-wrapper:focus-within {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-tint);
}

.pos-codecanyon .card-products .search-wrapper .search-icon {
  position: absolute;
  left: 14px;
  width: 18px;
  height: 18px;
  color: var(--ink-3);
  pointer-events: none;
}

.pos-codecanyon .card-products .search-wrapper .search-input {
  flex: 1;
  height: 100%;
  padding: 0 80px 0 42px;
  background: transparent;
  border: 0;
  font-size: 14px;
  color: var(--ink);
  outline: none;
}

.pos-codecanyon .search-kbd-hint {
  position: absolute;
  right: 56px;
  top: 50%;
  transform: translateY(-50%);
  font-family: var(--font-mono);
  font-size: 11px;
  font-weight: 500;
  padding: 2px 6px;
  background: var(--soft);
  color: var(--ink-3);
  border-radius: 4px;
  border: 1px solid var(--line);
  pointer-events: none;
}

.pos-codecanyon .card-products .search-wrapper > .action-btn-icon {
  position: absolute;
  right: 6px;
  top: 50%;
  transform: translateY(-50%);
  width: 32px !important;
  height: 32px !important;
  border-radius: 7px;
  background: var(--soft);
  border: 1px solid var(--line);
  color: var(--ink-2);
}

.pos-codecanyon .card-products .pos-autocomplete-results {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  margin: 0;
  padding: 4px;
  list-style: none;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(20, 20, 40, 0.08);
  max-height: 280px;
  overflow: auto;
  z-index: 10;
}

.pos-codecanyon .card-products .pos-autocomplete-item {
  padding: 8px 12px;
  font-size: 13px;
  color: var(--ink);
  cursor: pointer;
  border-radius: 6px;
}
.pos-codecanyon .card-products .pos-autocomplete-item:hover {
  background: var(--accent-tint);
  color: var(--accent);
}

/* Mobile category/brand filter row inside card-products */
.pos-codecanyon .card-products-mobile-filters {
  display: none;
  gap: 8px;
  padding: 0 20px 8px;
  width: 100%;
}
@media (max-width: 768px) {
  .pos-codecanyon .card-products .card-header { flex-wrap: wrap; }
  .pos-codecanyon .card-products-mobile-filters {
    display: flex;
  }
  .pos-codecanyon .card-products-mobile-filters > * { flex: 1; }
}

/* Products container + grid */
.pos-codecanyon .products-container {
  overflow-y: auto;
  padding: 16px 20px;
  background: var(--surface);
  min-height: 0;
}

.pos-codecanyon .products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}

@media (max-width: 1280px) {
  .pos-codecanyon .products-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
}

.pos-codecanyon .product-card {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 10px;
  text-align: left;
  cursor: pointer;
  position: relative;
  transition: all 120ms ease;
  display: flex;
  flex-direction: column;
  gap: 8px;
  box-shadow: none;
}

.pos-codecanyon .product-card:hover {
  border-color: var(--accent);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(20, 20, 40, 0.06);
}

.pos-codecanyon .product-card .product-image-wrapper {
  position: relative;
  aspect-ratio: 1 / 1;
  border-radius: 8px;
  overflow: hidden;
  background: var(--soft);
  display: grid;
  place-items: center;
}

.pos-codecanyon .product-card .product-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.pos-codecanyon .product-card .product-image-placeholder {
  font-family: var(--font-mono);
  font-weight: 700;
  font-size: 22px;
  color: rgba(31, 31, 44, 0.45);
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.pos-codecanyon .product-card .discount-badge {
  position: absolute;
  top: 6px;
  left: 6px;
  background: var(--danger);
  color: #fff;
  font-family: var(--font-mono);
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 99px;
  letter-spacing: 0.02em;
}

.pos-codecanyon .product-card .product-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pos-codecanyon .product-card .product-name {
  font-size: 13px;
  font-weight: 500;
  color: var(--ink);
  margin: 0;
  line-height: 1.25;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.pos-codecanyon .product-card .product-brand {
  font-size: 10px;
  font-family: var(--font-mono);
  color: var(--ink-3);
  margin: 0;
  letter-spacing: 0.02em;
}

.pos-codecanyon .product-card .product-stock {
  font-size: 10px;
  color: var(--ink-3);
  margin: 2px 0 0;
  font-family: var(--font-mono);
}

.pos-codecanyon .product-card .product-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 6px;
}

.pos-codecanyon .product-card .product-price {
  font-size: 14px;
  font-weight: 700;
  font-family: var(--font-mono);
  color: var(--accent);
  letter-spacing: -0.01em;
}

.pos-codecanyon .product-card .add-to-cart-btn {
  width: 26px;
  height: 26px;
  background: var(--accent-tint);
  color: var(--accent);
  border: 0;
  border-radius: 7px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  transition: all 120ms ease;
}
.pos-codecanyon .product-card .add-to-cart-btn:hover {
  background: var(--accent);
  color: #fff;
}
.pos-codecanyon .product-card .add-to-cart-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.pos-codecanyon .product-card .add-to-cart-btn svg {
  width: 16px;
  height: 16px;
}

/* Pagination footer */
.pos-codecanyon .pagination-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 20px;
  border-top: 1px solid var(--line);
  background: var(--surface);
  font-size: 12px;
  color: var(--ink-3);
  gap: 12px;
}

.pos-codecanyon .pagination-footer .pagination-btn,
.pos-codecanyon .pagination-footer .pagination-dot {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid var(--line);
  background: var(--surface);
  color: var(--ink-2);
  font-size: 12px;
  font-family: var(--font-sans);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}
.pos-codecanyon .pagination-footer .pagination-btn:hover:not(:disabled),
.pos-codecanyon .pagination-footer .pagination-dot:hover:not(:disabled) {
  background: var(--soft);
  color: var(--ink);
}
.pos-codecanyon .pagination-footer .pagination-dot.active {
  background: var(--accent);
  color: #fff;
  border-color: var(--accent);
  font-weight: 600;
}
.pos-codecanyon .pagination-footer .pagination-dot.ellipsis {
  border: 0;
  background: transparent;
  cursor: default;
}
.pos-codecanyon .pagination-footer .pagination-dots {
  display: inline-flex;
  gap: 4px;
}
.pos-codecanyon .pagination-footer .pagination-info {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  font-size: 11px;
  color: var(--ink-3);
}
.pos-codecanyon .pagination-footer .page-number {
  font-family: var(--font-mono);
  color: var(--ink-2);
}

/* ---------- Bottom action / pay bar ---------- */
.pos-codecanyon .pos-footer-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px 8px 20px;
  background: var(--surface);
  border-top: 1px solid var(--line);
  box-shadow: none;
  min-height: 64px;
  position: static;
}

.pos-codecanyon .footer-status-indicator {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: #1e7a44;
  padding: 0;
  background: transparent;
  border: 0;
}

.pos-codecanyon .footer-status-indicator::before {
  content: "";
  width: 6px;
  height: 6px;
  border-radius: 99px;
  background: var(--success);
  display: inline-block;
}

.pos-codecanyon .footer-status-indicator svg { display: none; }

.pos-codecanyon .footer-status-indicator.is-offline {
  color: #a83232;
}
.pos-codecanyon .footer-status-indicator.is-offline::before {
  background: var(--danger);
}

.pos-codecanyon .pos-footer-bar::before {
  content: "";
  width: 1px;
  height: 24px;
  background: var(--line);
  margin: 0 4px 0 8px;
}

.pos-codecanyon .footer-main-group {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
}

.pos-codecanyon .pos-footer-bar .action-btn,
.pos-codecanyon .pos-footer-bar .action-btn-secondary,
.pos-codecanyon .pos-footer-bar a.action-btn {
  height: 36px;
  padding: 0 14px;
  background: transparent;
  color: var(--ink);
  border: 1px solid var(--line);
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  text-decoration: none;
  transition: all 120ms ease;
  box-shadow: none;
}

.pos-codecanyon .pos-footer-bar .action-btn:hover:not(:disabled),
.pos-codecanyon .pos-footer-bar .action-btn-secondary:hover {
  background: var(--soft);
  border-color: var(--line-strong);
}

.pos-codecanyon .pos-footer-bar .action-btn svg {
  width: 16px;
  height: 16px;
}

.pos-codecanyon .footer-space { flex: 1; }

.pos-codecanyon .total-payable-section {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  text-align: right;
  margin-right: 12px;
  gap: 0;
}

.pos-codecanyon .total-payable-section .payable-label {
  font-size: 10px;
  color: var(--ink-3);
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.pos-codecanyon .total-payable-section .payable-amount {
  font-size: 20px;
  font-weight: 700;
  font-family: var(--font-mono);
  color: var(--ink);
  letter-spacing: -0.01em;
}

/* Big primary Pay button */
.pos-codecanyon .pos-footer-bar .action-btn-primary {
  height: 48px;
  padding: 0 28px;
  background: var(--accent);
  color: #fff;
  border: 0;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  box-shadow: 0 4px 14px var(--accent-shadow);
  transition: all 120ms ease;
}

.pos-codecanyon .pos-footer-bar .action-btn-primary:hover:not(:disabled) {
  filter: brightness(1.05);
  box-shadow: 0 6px 18px var(--accent-shadow);
}

.pos-codecanyon .pos-footer-bar .action-btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}

.pos-codecanyon .pos-footer-bar .action-btn-primary svg {
  width: 18px;
  height: 18px;
  fill: #fff;
}

/* ---------- Loader gate ---------- */
.pos-codecanyon .pos-gate-loader {
  flex: 1;
  display: grid;
  place-items: center;
  background: var(--bg);
}

/* ---------- Responsive: collapse to single column ---------- */
@media (max-width: 1024px) {
  .pos-codecanyon .pos-container {
    grid-template-columns: 1fr;
  }
  .pos-codecanyon .pos-column-left {
    border-right: 0;
    border-bottom: 1px solid var(--line);
    max-height: 50vh;
  }
}

@media (max-width: 768px) {
  .pos-codecanyon .pos-footer-bar {
    flex-wrap: wrap;
    padding: 8px 12px;
    min-height: auto;
  }
  .pos-codecanyon .total-payable-section {
    margin-right: 0;
    align-items: flex-start;
    text-align: left;
  }
  .pos-codecanyon .pos-footer-bar .action-btn-primary {
    width: 100%;
    justify-content: center;
  }
}

/* Subtle scrollbar in this scope */
.pos-codecanyon .cart-section::-webkit-scrollbar,
.pos-codecanyon .products-container::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
.pos-codecanyon .cart-section::-webkit-scrollbar-thumb,
.pos-codecanyon .products-container::-webkit-scrollbar-thumb {
  background: var(--line-strong);
  border-radius: 4px;
}


/* ============================================================================
   POS_NEW shell — interaction states + v-select deep overrides
   The visual layout itself is delivered via inline styles in <template>;
   this block adds only what cannot be expressed inline:
   pseudo-classes (:hover/:focus), placeholder, deep v-select skin,
   media queries.
   ============================================================================ */

/* Kill default focus outlines on every interactive element inside the POS shell
   (buttons, links, inputs, selects, b-dropdown toggles, v-select internals).
   Search input keeps its custom accent ring rule defined further down. */
.pos-codecanyon button:focus,
.pos-codecanyon button:focus-visible,
.pos-codecanyon a:focus,
.pos-codecanyon a:focus-visible,
.pos-codecanyon input:focus,
.pos-codecanyon select:focus,
.pos-codecanyon textarea:focus,
.pos-codecanyon [role="button"]:focus,
.pos-codecanyon .btn:focus,
.pos-codecanyon .btn.focus,
.pos-codecanyon .dropdown-toggle:focus,
.pos-codecanyon .b-dropdown:focus,
.pos-codecanyon .b-dropdown .btn:focus,
.pos-codecanyon ::v-deep(.vs__dropdown-toggle:focus),
.pos-codecanyon ::v-deep(.vs__search:focus) {
  outline: 0 !important;
  outline-offset: 0 !important;
  -webkit-tap-highlight-color: transparent;
  box-shadow: none !important;
}
/* Keep the search input's purple ring from the .pos-shell-search-input:focus rule below */
.pos-codecanyon .pos-shell-search-input:focus {
  box-shadow: 0 0 0 3px #f5f3fd !important;
}

/* Base style for icon buttons — applied to both regular <button>s and to
   b-dropdown toggle buttons via toggle-class (where inline styles can't reach). */
.pos-codecanyon .pos-shell-icon-btn,
.pos-codecanyon button.pos-shell-icon-btn,
.pos-codecanyon a.pos-shell-icon-btn {
  width: 32px !important;
  height: 32px !important;
  min-width: 32px;
  border-radius: 8px !important;
  border: 1px solid #e6e6ec !important;
  background: #ffffff !important;
  color: #54546a !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 0 !important;
  cursor: pointer;
  transition: all 120ms ease;
  box-shadow: none !important;
  line-height: 1;
}
.pos-codecanyon .pos-shell-icon-btn i,
.pos-codecanyon .pos-shell-icon-btn svg {
  font-size: 15px;
  line-height: 1;
}
.pos-codecanyon .pos-shell-icon-btn:hover {
  background: #f3f3f7 !important;
  color: #1f1f2c !important;
  border-color: #d8d8e0 !important;
}
/* When the icon button is the toggle of a b-dropdown, the wrapper is .dropdown.
   Make sure the dropdown wrapper doesn't add unwanted spacing. */
.pos-codecanyon .dropdown > .pos-shell-icon-btn {
  display: inline-flex !important;
}
/* Dropdown menus opened from icon-button toggles — clean white panel with our palette */
.pos-codecanyon .pos-shell-dropdown-menu,
.pos-codecanyon .pos-shell-dropdown-menu.dropdown-menu {
  background: #ffffff !important;
  border: 1px solid #e6e6ec !important;
  border-radius: 10px !important;
  box-shadow: 0 8px 24px rgba(20, 20, 40, 0.08) !important;
  padding: 0 !important;
  margin-top: 4px !important;
}

.pos-codecanyon .pos-shell-action-btn:hover:not(:disabled) {
  background: #f3f3f7 !important;
  border-color: #d8d8e0 !important;
}
.pos-codecanyon .pos-shell-action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pos-codecanyon .pos-shell-pay-btn:hover:not(:disabled) {
  filter: brightness(1.05);
  box-shadow: 0 6px 18px rgba(111, 83, 217, 0.45) !important;
}

.pos-codecanyon .pos-shell-product-card:hover {
  border-color: #6f53d9 !important;
  box-shadow: 0 4px 12px rgba(20, 20, 40, 0.06);
}

.pos-codecanyon .pos-shell-cart-row:hover {
  background: #f3f3f7;
}

.pos-codecanyon .pos-shell-add-btn:hover:not(:disabled) {
  background: #6f53d9 !important;
  color: #ffffff !important;
}
.pos-codecanyon .pos-shell-add-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pos-codecanyon .pos-shell-search-input:focus {
  border-color: #6f53d9 !important;
  box-shadow: 0 0 0 3px #f5f3fd;
}
.pos-codecanyon .pos-shell-search-input::placeholder {
  color: #8d8da0;
}

.pos-codecanyon .pos-shell-autocomplete-item:hover {
  background: #f5f3fd;
  color: #6f53d9;
}

/* Header v-select skin (deep into vue-select internals) */
.pos-codecanyon .pos-shell-header ::v-deep(.pos-shell-select .vs__dropdown-toggle) {
  height: 32px;
  min-height: 32px;
  padding: 0 8px;
  background: #ffffff;
  border: 1px solid #e6e6ec;
  border-radius: 7px;
  box-shadow: none;
  font-size: 12px;
}
.pos-codecanyon .pos-shell-header ::v-deep(.pos-shell-select .vs__selected-options) {
  padding: 0;
}
.pos-codecanyon .pos-shell-header ::v-deep(.pos-shell-select .vs__placeholder),
.pos-codecanyon .pos-shell-header ::v-deep(.pos-shell-select .vs__selected) {
  font-size: 12px;
  color: #54546a;
  margin: 0;
}
.pos-codecanyon .pos-shell-header ::v-deep(.pos-shell-select .vs__search) {
  font-size: 12px;
  color: #1f1f2c;
  margin: 0;
  padding: 0;
}
.pos-codecanyon .pos-shell-header ::v-deep(.pos-shell-select .vs__open-indicator),
.pos-codecanyon .pos-shell-header ::v-deep(.pos-shell-select .vs__clear) {
  fill: #8d8da0;
  scale: 0.8;
}

/* Hide caret arrows on dropdowns inside our header */
.pos-codecanyon .dropdown-toggle-no-caret::after { display: none !important; }

/* Smaller scrollbars in this scope */
.pos-codecanyon .pos-shell-cart-scroll::-webkit-scrollbar,
.pos-codecanyon .pos-shell-products-scroll::-webkit-scrollbar,
.pos-codecanyon .pos-shell-main section ::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
.pos-codecanyon .pos-shell-cart-scroll::-webkit-scrollbar-thumb,
.pos-codecanyon .pos-shell-products-scroll::-webkit-scrollbar-thumb,
.pos-codecanyon .pos-shell-main section ::-webkit-scrollbar-thumb {
  background: #d8d8e0;
  border-radius: 4px;
}

/* Fullscreen mode hardening — when the browser Fullscreen API promotes
   .pos-codecanyon (or any ancestor) to :fullscreen, pin the layout to
   the viewport so the header/footer never push off-screen and the inner
   .pos-shell-products-scroll / .pos-shell-cart-scroll regions handle
   overflow. Desktop / tablet-landscape only — on phones (≤768px) the
   layout uses page-level scrolling, and forcing overflow:hidden here
   would freeze the page after entering fullscreen via the in-app
   button (the html element gets :fullscreen and inherits this chain). */
@media (min-width: 769px) {
  .pos-codecanyon:fullscreen,
  .pos-codecanyon:-webkit-full-screen {
    width: 100% !important;
    height: 100% !important;
    max-height: 100vh !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
  }
  :fullscreen .pos-codecanyon,
  :-webkit-full-screen .pos-codecanyon {
    width: 100% !important;
    height: 100% !important;
    max-height: 100vh !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
  }
  .pos-codecanyon:fullscreen .pos-shell-header,
  :fullscreen .pos-codecanyon .pos-shell-header,
  .pos-codecanyon:-webkit-full-screen .pos-shell-header {
    flex: 0 0 52px;
  }
  .pos-codecanyon:fullscreen .pos-shell-main,
  :fullscreen .pos-codecanyon .pos-shell-main,
  .pos-codecanyon:-webkit-full-screen .pos-shell-main {
    flex: 1 1 auto !important;
    min-height: 0 !important;
    overflow: hidden !important;
  }
}
/* Scroll regions: always need overflow:auto so they remain scrollable */
.pos-codecanyon .pos-shell-products-scroll,
.pos-codecanyon .pos-shell-cart-scroll {
  overflow-y: auto;
  min-height: 0;
}

/* ============================================================
   Defensive base — fixed widths/heights moved off inline styles
   → into classes so media queries don't have to !important-fight
   inline styles. Box-sizing/overflow defenses live in the mobile
   block (below) so they don't perturb the desktop layout.
   ============================================================ */

/* Cart aside default width (overridden for ≤1024px below) */
.pos-codecanyon .pos-shell-cart-aside {
  flex: 0 0 50%;
  width: 50%;
}

/* ============================================================
   Cart sticky header (inside .pos-shell-cart-scroll)
   ============================================================ */
.pos-codecanyon .pos-cart-header {
  position: sticky;
  top: -8px; /* compensate for the parent's 8px padding so it docks flush */
  margin: -8px -8px 8px -8px; /* span the full scroll width edge-to-edge */
  padding: 10px 12px;
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  background:
    linear-gradient(180deg, #ffffff 0%, #fafafe 100%);
  border-bottom: 1px solid #ececf3;
  box-shadow: 0 4px 8px -6px rgba(20, 20, 40, 0.08);
}
.pos-codecanyon .pos-cart-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.pos-codecanyon .pos-cart-header-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 34px;
  background: linear-gradient(135deg, rgba(111,83,217,0.12), rgba(138,108,240,0.12));
  color: #6f53d9;
  i { font-size: 16px; }
  svg { width: 16px; height: 16px; }
}
.pos-codecanyon .pos-cart-header-text {
  min-width: 0;
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}
.pos-codecanyon .pos-cart-header-title {
  font-size: 13px;
  font-weight: 700;
  color: #1f1f2c;
  letter-spacing: -0.1px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pos-codecanyon .pos-cart-header-sub {
  font-size: 11px;
  font-weight: 500;
  color: #8d8da0;
  margin-top: 1px;
}

.pos-codecanyon .pos-cart-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}
.pos-codecanyon .pos-cart-header-total {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  line-height: 1.2;
}
.pos-codecanyon .pos-cart-header-total-label {
  font-size: 10px;
  font-weight: 600;
  color: #8d8da0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.pos-codecanyon .pos-cart-header-total-value {
  font-size: 14px;
  font-weight: 800;
  color: #1f1f2c;
  font-feature-settings: "tnum";
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.2px;
  white-space: nowrap;
  margin-top: 1px;
}

.pos-codecanyon .pos-cart-header-clear-btn {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid #fbe3e3;
  background: #fff5f5;
  color: #d64545;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  flex: 0 0 34px;
  transition: background 150ms ease, border-color 150ms ease, transform 150ms ease, color 150ms ease;
  i { font-size: 15px; }
  svg { width: 15px; height: 15px; }
}
.pos-codecanyon .pos-cart-header-clear-btn:hover:not(.is-disabled):not(:disabled) {
  background: #d64545;
  color: #fff;
  border-color: #d64545;
  transform: scale(1.05);
}
.pos-codecanyon .pos-cart-header-clear-btn.is-disabled,
.pos-codecanyon .pos-cart-header-clear-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  background: #f7f7fb;
  border-color: #ececf3;
  color: #b0b0c0;
}

/* Mobile-cart-header (the existing simple label) is now redundant — the new
   sticky header replaces it on every breakpoint. */
.pos-codecanyon .pos-shell-mobile-cart-header {
  display: none !important;
}

/* Header default height (relaxes on mobile via media query → height:auto) */
.pos-codecanyon .pos-shell-header {
  min-height: 52px;
  height: 52px;
}

/* Pay bar default height (relaxes on mobile via media query → height:auto) */
.pos-codecanyon .pos-shell-pay-bar {
  min-height: 55px;
  height: 55px;
}

/* Header-row min-width safety on the header's inner items */
.pos-codecanyon .pos-shell-header > * { min-width: 0; }

/* Products grid — responsive column count */
.pos-codecanyon .pos-shell-products-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  grid-auto-rows: max-content;
  gap: 5px;
}

/* ============================================================
   Responsive breakpoints
   - >1280px       : desktop, 5 product columns, side-by-side
   - 1025-1280     : desktop side-by-side, 4 product columns
   - 481-1024      : tablet + mobile — mockup-style layout (page
                     scrolls vertically, bottom tab bar, mobile
                     cart label + pay button, 3 product cols)
   - 361-480       : small mobile — 2 cols, condensed
   - <=360         : tiny phones — single column products
   ============================================================ */
@media (max-width: 1280px) {
  .pos-codecanyon .pos-shell-products-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

/* ===== Tablet + mobile share the same mockup-style layout (≤1024px) ===== */
@media (max-width: 1024px) {
  /* --- Container --- */
  .pos-codecanyon { font-size: 13px; }

  /* --- Top header: wraps to multiple rows, dividers hidden --- */
  .pos-codecanyon .pos-shell-header {
    flex-wrap: wrap;
    height: auto !important;
    min-height: 52px !important;
    padding: 6px 10px !important;
    row-gap: 6px;
    column-gap: 6px !important;
  }
  .pos-codecanyon .pos-shell-header > div[style*="width: 1px"] {
    display: none !important;
  }
  .pos-codecanyon .pos-shell-header > div[style*="flex: 1"] {
    display: none !important;
  }
  .pos-codecanyon .pos-shell-header .pos-shell-select {
    min-width: 0 !important;
    max-width: none !important;
    flex: 1 1 140px;
  }
  /* Brand block — tighter, no right border so it doesn't look orphaned */
  .pos-codecanyon .pos-shell-header > div:first-child {
    padding-right: 6px !important;
    border-right: 0 !important;
    gap: 6px !important;
  }

  /* --- Cart aside --- */
  .pos-codecanyon .pos-shell-cart-aside,
  .pos-codecanyon .pos-shell-main > aside {
    max-height: 42vh;
  }
  .pos-codecanyon .pos-shell-cart-scroll {
    padding: 6px !important;
  }

  /* --- Cart row: tighter, allow inner row to wrap --- */
  .pos-codecanyon .pos-shell-cart-row {
    grid-template-columns: 28px 1fr auto !important;
    gap: 6px !important;
    padding: 6px !important;
  }
  /* Inner "qty + price + price-type" row wraps when narrow */
  .pos-codecanyon .pos-shell-cart-row > div:nth-child(2) > div:last-child {
    flex-wrap: wrap !important;
    row-gap: 4px;
  }
  /* Bigger touch target on qty stepper */
  .pos-codecanyon .pos-shell-cart-row .pos-qty-stepper,
  .pos-codecanyon .pos-shell-cart-row > div:nth-child(2) > div:last-child > div:first-child {
    height: 28px !important;
  }
  .pos-codecanyon .pos-shell-cart-row > div:nth-child(2) > div:last-child > div:first-child > button {
    width: 26px !important;
    height: 26px !important;
    font-size: 15px !important;
  }
  .pos-codecanyon .pos-shell-cart-row > div:nth-child(2) > div:last-child > div:first-child > input {
    width: 36px !important;
    font-size: 12px !important;
  }
  /* Edit/remove buttons bigger touch target */
  .pos-codecanyon .pos-shell-cart-row > div:nth-child(3) button {
    padding: 6px !important;
  }

  /* --- Totals/charges block --- */
  .pos-codecanyon .pos-shell-totals {
    padding: 8px !important;
  }

  /* --- Search row --- */
  .pos-codecanyon .pos-shell-search-row {
    padding: 6px 8px !important;
  }
  .pos-codecanyon .pos-shell-search-input {
    height: 38px !important;
    font-size: 13px !important;
  }
  /* Scanner button shows icon only at this size */
  .pos-codecanyon .pos-shell-search-row > button > span {
    display: none !important;
  }
  .pos-codecanyon .pos-shell-search-row > button {
    width: 38px !important;
    height: 38px !important;
    padding: 0 !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
  }

  /* --- Products grid: 3 cols --- */
  .pos-codecanyon .pos-shell-products-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
  }
  .pos-codecanyon .pos-shell-product-card {
    padding: 6px !important;
  }
  /* Image wrapper's negative margin must match card padding — otherwise the
     image extends past the border and clips it. Override to -6px to match. */
  .pos-codecanyon .pos-shell-product-card > div:first-of-type {
    margin: -6px -6px 6px !important;
  }

  /* --- Pay bar: action buttons compact, Pay-Now full width --- */
  .pos-codecanyon .pos-shell-action-btn {
    height: 36px !important;
    padding: 0 10px !important;
    font-size: 12px !important;
  }
  .pos-codecanyon .pos-shell-pay-btn {
    flex: 1 1 100% !important;
    justify-content: center;
    height: 44px !important;
    padding: 0 16px !important;
    font-size: 14px !important;
  }
  /* Total Payable: align left, full row */
  .pos-codecanyon .pos-shell-pay-bar > div[style*="align-items: flex-end"] {
    align-items: flex-start !important;
    text-align: left !important;
    margin-right: 0 !important;
    flex: 1 1 100% !important;
  }
}

/* ===== Small mobile (≤480px) ===== */
@media (max-width: 480px) {
  /* --- Header: keep brand label + register pill visible (matches mobile mockup) --- */
  .pos-codecanyon .pos-shell-header > div:first-child {
    padding-right: 4px !important;
    border-right: 0 !important;
  }

  /* --- Cart aside: roomier (the cart is the primary surface) --- */
  .pos-codecanyon .pos-shell-cart-aside,
  .pos-codecanyon .pos-shell-main > aside {
    max-height: 48vh;
  }

  /* --- Cart row internals: minimal --- */
  .pos-codecanyon .pos-shell-cart-row {
    grid-template-columns: 26px 1fr auto !important;
    gap: 4px !important;
    padding: 4px !important;
  }
  .pos-codecanyon .pos-shell-cart-row > div:first-child {
    width: 26px !important;
    height: 26px !important;
    font-size: 9px !important;
  }
  /* Hide the SKU code on tiny screens to keep name readable */
  .pos-codecanyon .pos-shell-cart-row > div:nth-child(2) > div:first-child > span {
    display: none !important;
  }

  /* --- Charges row stacks 2 cols on tiny screens (3 was too cramped) --- */
  .pos-codecanyon .pos-shell-charges-row {
    grid-template-columns: 1fr 1fr !important;
  }
  .pos-codecanyon .pos-shell-charges-row > label:nth-child(3) {
    grid-column: 1 / -1;
  }

  /* --- Search row & scanner --- */
  .pos-codecanyon .pos-shell-search-row {
    padding: 4px 6px !important;
  }

  /* --- Products: 2 cols, smaller image zone --- */
  .pos-codecanyon .pos-shell-products-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
  }
  .pos-codecanyon .pos-shell-product-card {
    padding: 5px !important;
    border-radius: 8px !important;
  }
  .pos-codecanyon .pos-shell-product-card > div:first-of-type {
    margin: -5px -5px 6px !important;
  }
  /* Match the image's top-corner rounding to the card's border-radius (8px)
     so the image doesn't poke past the card corners on small phones. */
  .pos-codecanyon .pos-shell-product-card > div:first-of-type > div {
    border-radius: 8px 8px 0 0 !important;
  }

  /* --- Pay bar: action buttons icon-only --- */
  .pos-codecanyon .pos-shell-action-btn span:not(.spinner) {
    display: none !important;
  }
  .pos-codecanyon .pos-shell-action-btn {
    width: 38px !important;
    height: 38px !important;
    padding: 0 !important;
    justify-content: center;
    flex: 0 0 auto !important;
  }
  /* Pay Now stays full width and prominent */
  .pos-codecanyon .pos-shell-pay-btn {
    flex: 1 1 100% !important;
    height: 48px !important;
    font-size: 15px !important;
  }
  /* Hide the "Online/Offline · Synced" trailing text — keep just the dot + status */
  .pos-codecanyon .pos-shell-pay-bar > div:first-child > span:nth-child(2) > span[style*="opacity: 0.7"] {
    display: none !important;
  }
}

/* ===== Tiny phones (≤360px) ===== */
@media (max-width: 360px) {
  /* Charges stack 1 column when even 2 columns is too tight */
  .pos-codecanyon .pos-shell-charges-row {
    grid-template-columns: 1fr !important;
  }
  .pos-codecanyon .pos-shell-charges-row > label:nth-child(3) {
    grid-column: auto;
  }
  /* Single-column products on the smallest devices for legibility */
  .pos-codecanyon .pos-shell-products-grid {
    grid-template-columns: 1fr !important;
  }
}

/* ===== Touch-friendly: bump min hit-area on interactive controls
       (matches Android/iOS "comfortable" target ≥32-40px) ===== */
@media (hover: none) and (pointer: coarse) {
  .pos-codecanyon .pos-shell-product-card,
  .pos-codecanyon .pos-shell-action-btn,
  .pos-codecanyon .pos-shell-pay-btn,
  .pos-codecanyon .pos-shell-icon-btn {
    -webkit-tap-highlight-color: rgba(111, 83, 217, 0.15);
  }
}

/* ============================================================
   MOBILE-ONLY ELEMENTS (hidden on desktop)
   These are rendered always in the template but only display ≤768px.
   ============================================================ */
.pos-codecanyon .pos-shell-mobile-cart-header,
.pos-codecanyon .pos-shell-mobile-pay-btn,
.pos-codecanyon .pos-shell-mobile-pagination-dots,
.pos-codecanyon .pos-shell-mobile-tabbar {
  display: none;
}

/* ============================================================
   PHONE LAYOUT (≤768px)
   This block restyles the page to match the target mobile mockup:
     - Simplified top header (brand + 3 icons; selectors below it)
     - Search row + category chips strip
     - Products grid (3 cols) + pagination dots
     - Cart aside drops BELOW products, with collapse toggle and
       its own Pay Now button
     - Desktop bottom pay bar hidden, replaced by bottom tab bar
   ============================================================ */
@media (max-width: 768px) {
  /* --- Defensive base (mobile-only — keeps desktop unchanged) --- */
  .pos-codecanyon,
  .pos-codecanyon * {
    box-sizing: border-box;
  }
  /* Let the WHOLE page scroll vertically on mobile (instead of trying to
     squeeze products + cart + header + tab bar into 100vh which collapses
     the products grid to 0). The fixed tab bar handles padding-bottom. */
  .pos-codecanyon {
    height: auto !important;
    min-height: 100vh;
    max-width: 100vw;
    overflow-x: hidden;
    overflow-y: auto;
  }

  /* --- Show mobile-only elements --- */
  .pos-codecanyon .pos-shell-mobile-cart-header,
  .pos-codecanyon .pos-shell-mobile-pay-btn,
  .pos-codecanyon .pos-shell-mobile-tabbar {
    display: flex;
  }
  .pos-codecanyon .pos-shell-mobile-pagination-dots {
    display: flex;
  }

  /* --- Reorder: products section first, cart aside second --- */
  /* Main is no longer flex:1 inside a fixed-vh root — it grows naturally. */
  .pos-codecanyon .pos-shell-main {
    flex-direction: column !important;
    flex: 0 0 auto !important;
    overflow: visible !important;
    min-height: 0;
  }
  .pos-codecanyon .pos-shell-section,
  .pos-codecanyon .pos-shell-main > section {
    order: 1;
    flex: 0 0 auto !important;
    min-height: 0;
    overflow: visible !important;
  }
  .pos-codecanyon .pos-shell-cart-aside,
  .pos-codecanyon .pos-shell-main > aside {
    order: 2;
    flex: 0 0 auto !important;
    width: 100% !important;
    border-right: 0 !important;
    border-top: 1px solid #e6e6ec;
    border-bottom: 0;
    max-height: none !important;
    overflow: visible !important;
    /* Mobile has an extra child (the cart label). */
    grid-template-rows: auto auto auto !important;
  }
  /* Products scroll: natural height on mobile (page handles vertical scroll) */
  .pos-codecanyon .pos-shell-products-scroll {
    height: auto !important;
    overflow: visible !important;
  }
  /* Cart scroll: cap at roughly 10 rows on mobile, then scroll inside the cart */
  .pos-codecanyon .pos-shell-cart-scroll {
    height: auto !important;
    max-height: 560px;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    -webkit-overflow-scrolling: touch;
  }
  /* Section grid: 5 children but all auto-sized so each takes its content height */
  .pos-codecanyon .pos-shell-section,
  .pos-codecanyon .pos-shell-main > section {
    grid-template-rows: auto auto auto auto !important;
  }
  /* Both products and cart are always visible on mobile (no collapsing). */
  /* --- Bottom padding so the tab bar doesn't cover content --- */
  .pos-codecanyon {
    padding-bottom: 60px;
  }

  /* --- Top header: 3-row mobile layout matching the design mockup ---
     Row 1: Brand block (50%) + Warehouse trigger (50%)
     Row 2: Customer trigger (1fr) + small icon cluster (quick-add + settings + user)
     Row 3: Categories trigger (50%) + Brands trigger (50%)
     The 4th row (search) is the existing .pos-shell-search-row block. */
  .pos-codecanyon .pos-shell-header {
    padding: 10px 12px !important;
    flex-wrap: wrap !important;
    row-gap: 8px !important;
    column-gap: 6px !important;
    height: auto !important;
    align-items: stretch !important;
  }

  /* Hide dividers + spacer + fullscreen + legacy settings link */
  .pos-codecanyon .pos-shell-header > a[title*="Settings"],
  .pos-codecanyon .pos-shell-header > a[title*="POS"],
  .pos-codecanyon .pos-shell-header > button[title*="Fullscreen"],
  .pos-codecanyon .pos-shell-header > div[style*="width: 1px"],
  .pos-codecanyon .pos-shell-header > div[style*="flex: 1"] {
    display: none !important;
  }

  /* ---- Row 1: Logo + today's-sales + sync/offline + POS settings + user-dd ---- */
  .pos-codecanyon .pos-shell-header > div:first-child {
    order: 1 !important;
    flex: 0 0 auto !important;
    min-width: 0 !important;
    max-width: none !important;
    border-right: 0 !important;
    padding-right: 0 !important;
    gap: 8px !important;
    height: 44px !important;
    margin-right: auto !important;
  }
  /* Today's sales — visible on mobile */
  .pos-codecanyon .pos-shell-header > button[title*="Today"] {
    order: 2 !important;
    flex: 0 0 auto !important;
    display: inline-flex !important;
  }
  /* Sync / offline — visible on mobile */
  .pos-codecanyon .pos-shell-header > button[title*="Sync"],
  .pos-codecanyon .pos-shell-header > button[title*="Offline"] {
    order: 3 !important;
    flex: 0 0 auto !important;
    display: inline-flex !important;
  }
  /* Languages dropdown */
  .pos-codecanyon .pos-shell-header > #lang-dd {
    order: 4 !important;
    flex: 0 0 32px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    align-self: center !important;
    width: 32px !important;
    height: 32px !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  /* Display screen dropdown */
  .pos-codecanyon .pos-shell-header > #display-screen-dd {
    order: 5 !important;
    flex: 0 0 32px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    align-self: center !important;
    width: 32px !important;
    height: 32px !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  /* Inner b-dropdown toggle button — kill leftover padding/margin/border from bootstrap */
  .pos-codecanyon .pos-shell-header > #lang-dd > button,
  .pos-codecanyon .pos-shell-header > #display-screen-dd > button,
  .pos-codecanyon .pos-shell-header > .pos-shell-user-dd-wrapper button {
    padding: 0 !important;
    margin: 0 !important;
    width: 32px !important;
    height: 32px !important;
    line-height: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 0 !important;
    background: transparent !important;
  }
  /* POS settings */
  .pos-codecanyon .pos-shell-header > .pos-shell-settings-btn {
    order: 6 !important;
    flex: 0 0 32px !important;
    width: 32px !important;
    height: 32px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    align-self: center !important;
  }
  /* User dropdown — rightmost on row 1 */
  .pos-codecanyon .pos-shell-header > .pos-shell-user-dd-wrapper {
    order: 7 !important;
    flex: 0 0 32px !important;
    width: 32px !important;
    height: 32px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    align-self: center !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  /* All icon-buttons in header — force exact 32x32 with centered content */
  .pos-codecanyon .pos-shell-header > button.pos-shell-icon-btn,
  .pos-codecanyon .pos-shell-header > a.pos-shell-icon-btn {
    width: 32px !important;
    height: 32px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    align-self: center !important;
    line-height: 1 !important;
  }
  /* SVG/icons inside header buttons — neutral line-height so they center cleanly */
  .pos-codecanyon .pos-shell-header svg,
  .pos-codecanyon .pos-shell-header i {
    vertical-align: middle !important;
  }
  /* Re-hide fullscreen button — earlier .pos-shell-icon-btn rule with display:inline-flex
     would otherwise override the hide rule (later rule wins on tie). Hide it last. */
  .pos-codecanyon .pos-shell-header > button[title*="Fullscreen"] {
    display: none !important;
  }

  /* ---- Row 2: Register status (full width, below the icons) ---- */
  .pos-codecanyon .pos-shell-header > .pos-shell-register-status {
    order: 8 !important;
    flex: 1 1 100% !important;
    width: 100% !important;
    min-width: 0 !important;
    max-width: none !important;
    height: auto !important;
    padding: 4px 0 !important;
  }

  /* ---- Row 3: Customer (full width, left) + Quick-add (right) ---- */
  .pos-codecanyon .pos-shell-header > .pos-cust-trigger {
    order: 9 !important;
    flex: 1 1 auto !important;
    width: auto !important;
    min-width: 0 !important;
    max-width: none !important;
  }
  .pos-codecanyon .pos-shell-header > button[title*="Quick"] {
    order: 10 !important;
    flex: 0 0 auto !important;
  }

  /* ---- Row 4: Warehouse (alone, full width) ---- */
  .pos-codecanyon .pos-shell-header > .pos-wh-trigger {
    order: 11 !important;
    flex: 1 1 100% !important;
    width: 100% !important;
    min-width: 0 !important;
    max-width: none !important;
  }

  /* ---- Row 5: Categories + Brands (50/50) ---- */
  .pos-codecanyon .pos-shell-header > .pos-cat-trigger:not(.pos-brand-trigger) {
    order: 12 !important;
    flex: 1 1 calc(50% - 3px) !important;
    min-width: 0 !important;
    max-width: none !important;
  }
  .pos-codecanyon .pos-shell-header > .pos-cat-trigger.pos-brand-trigger {
    order: 13 !important;
    flex: 1 1 calc(50% - 3px) !important;
    min-width: 0 !important;
    max-width: none !important;
  }

  /* Legacy v-select fallback (if any remain) */
  .pos-codecanyon .pos-shell-header .pos-shell-select {
    min-width: 0 !important;
    max-width: none !important;
    flex: 1 1 calc(50% - 3px);
  }

  /* All triggers match the 32px icon-button height for visual consistency */
  .pos-codecanyon .pos-shell-header > .pos-cat-trigger,
  .pos-codecanyon .pos-shell-header > .pos-wh-trigger,
  .pos-codecanyon .pos-shell-header > .pos-cust-trigger {
    height: 32px !important;
  }
  /* Inner icon chips shrink to fit cleanly inside 32px rows */
  .pos-codecanyon .pos-shell-header > .pos-cust-trigger .pos-cust-trigger-avatar,
  .pos-codecanyon .pos-shell-header > .pos-wh-trigger .pos-wh-trigger-icon,
  .pos-codecanyon .pos-shell-header > .pos-cat-trigger .pos-cat-trigger-icon {
    width: 24px !important;
    height: 24px !important;
    flex: 0 0 24px !important;
  }
  /* Brand block matches the row height too */
  .pos-codecanyon .pos-shell-header > div:first-child {
    height: 44px !important;
  }

  /* --- Search row: tighten --- */
  .pos-codecanyon .pos-shell-search-row {
    padding: 8px 12px !important;
    background: #ffffff;
  }
  .pos-codecanyon .pos-shell-search-input {
    height: 40px !important;
    font-size: 13px !important;
    border-radius: 10px !important;
  }
  /* Scanner button → square icon-only */
  .pos-codecanyon .pos-shell-search-row > button {
    width: 40px !important;
    height: 40px !important;
    padding: 0 !important;
    flex-shrink: 0 !important;
    justify-content: center !important;
    border-radius: 10px !important;
  }
  .pos-codecanyon .pos-shell-search-row > button > span {
    display: none !important;
  }

  /* --- Products grid: 3 cols --- */
  .pos-codecanyon .pos-shell-products-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    padding: 8px 10px 12px;
  }
  /* Hide stock quantity on product cards on mobile (mockup shows SKU only) */
  .pos-codecanyon .pos-shell-products-grid .pos-shell-card-stock {
    display: none;
  }
  /* Card retains its inline padding/border-radius — don't fight inline styles
     here because the image wrapper uses negative margins keyed to the card's
     padding (margin: -10px -10px 8px). Overriding card padding without also
     touching the image margin produces overlap or gaps. */

  /* --- Mobile pagination dots --- */
  .pos-codecanyon .pos-shell-mobile-pagination-dots {
    justify-content: center;
    gap: 6px;
    padding: 8px 0 12px;
    background: transparent;
  }
  .pos-codecanyon .pos-shell-mobile-page-dot {
    width: 8px;
    height: 8px;
    padding: 0;
    border: 0;
    border-radius: 99px;
    background: #d9d9e5;
    cursor: pointer;
    transition: all 160ms ease;
  }
  .pos-codecanyon .pos-shell-mobile-page-dot-active {
    width: 10px;
    height: 10px;
    background: #6f53d9;
  }
  /* Hide the desktop pagination on mobile */
  .pos-codecanyon .pos-shell-pagination {
    display: none !important;
  }

  /* --- Cart label header (visual only, no collapse) --- */
  .pos-codecanyon .pos-shell-mobile-cart-header {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    height: 40px;
    padding: 0 14px;
    background: #f7f7fb;
    border-bottom: 1px solid #e6e6ec;
  }
  .pos-codecanyon .pos-shell-mobile-cart-title {
    font-size: 14px;
    font-weight: 600;
    color: #1f1f2c;
  }

  /* --- Cart row: tighter on mobile + circle thumb (matches mockup) --- */
  .pos-codecanyon .pos-shell-cart-scroll {
    padding: 4px 8px !important;
  }
  .pos-codecanyon .pos-shell-cart-row {
    grid-template-columns: 32px 1fr auto !important;
    gap: 10px !important;
    padding: 8px !important;
    border-bottom: 1px solid #f3f3f7;
    border-radius: 0 !important;
  }
  .pos-codecanyon .pos-shell-cart-row > div:first-child {
    border-radius: 99px !important;
    background: #f5f3fd !important;
    color: #6f53d9 !important;
  }
  /* Stack name + SKU vertically on mobile (SKU below the name) */
  .pos-codecanyon .pos-shell-cart-row > div:nth-child(2) > div:first-child {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 0 !important;
  }
  /* Hide price-type select (mockup omits — desktop only) */
  .pos-codecanyon .pos-shell-cart-row > div:nth-child(2) > div:last-child > select {
    display: none !important;
  }
  /* Right column: edit/remove icons stay top, line total stays bottom (column layout) */
  .pos-codecanyon .pos-shell-cart-row > div:nth-child(3) {
    flex-direction: column !important;
    align-items: flex-end !important;
    justify-content: space-between !important;
    gap: 4px !important;
  }

  /* --- Totals/charges block tighter --- */
  .pos-codecanyon .pos-shell-totals {
    padding: 10px 12px !important;
  }
  /* Charges row keeps 3 cols on mobile (Tax | Discount | Shipping) */
  .pos-codecanyon .pos-shell-charges-row {
    grid-template-columns: 1fr 1fr 1fr !important;
    gap: 8px !important;
  }

  /* --- Mobile Pay Now button (inside cart, full-width purple) --- */
  .pos-codecanyon .pos-shell-mobile-pay-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: calc(100% - 16px);
    margin: 8px;
    height: 48px;
    padding: 0 18px;
    background: #6f53d9;
    color: #ffffff;
    border: 0;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(111, 83, 217, 0.32);
    transition: all 120ms ease;
  }
  .pos-codecanyon .pos-shell-mobile-pay-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  .pos-codecanyon .pos-shell-mobile-pay-btn-amount {
    margin-left: auto;
    font-family: 'JetBrains Mono', monospace;
  }

  /* --- Hide the desktop bottom pay bar on mobile (replaced by tab bar + cart pay btn) --- */
  .pos-codecanyon .pos-shell-pay-bar {
    display: none !important;
  }

  /* --- Mobile bottom tab bar (fixed) --- */
  .pos-codecanyon .pos-shell-mobile-tabbar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 50;
    display: flex;
    align-items: stretch;
    justify-content: space-around;
    height: 60px;
    background: #ffffff;
    border-top: 1px solid #e6e6ec;
    box-shadow: 0 -2px 12px rgba(20, 20, 40, 0.04);
    padding: 4px 0 max(4px, env(safe-area-inset-bottom));
  }
  .pos-codecanyon .pos-shell-mobile-tabbar > * {
    flex: 1 1 0;
  }
  .pos-codecanyon .pos-shell-mobile-tab {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    width: 100%;
    height: 100%;
    padding: 0;
    border: 0;
    background: transparent;
    color: #8d8da0;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
    -webkit-tap-highlight-color: rgba(111, 83, 217, 0.18);
    touch-action: manipulation;
  }
  .pos-codecanyon .pos-shell-mobile-tab > * {
    pointer-events: none;
  }
  .pos-codecanyon .pos-shell-mobile-tab:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }
  .pos-codecanyon .pos-shell-mobile-tab-active {
    color: #6f53d9;
  }
  .pos-codecanyon .pos-shell-mobile-tab-icon-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .pos-codecanyon .pos-shell-mobile-tab-badge {
    position: absolute;
    top: -4px;
    right: -8px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    background: #6f53d9;
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    border-radius: 99px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', monospace;
  }
  /* "More" b-dropdown trigger — strip default button styles */
  .pos-codecanyon .pos-shell-mobile-tabbar ::v-deep(.pos-shell-mobile-tab-toggle) {
    width: 100% !important;
    height: 100% !important;
    padding: 0 !important;
    background: transparent !important;
    border: 0 !important;
    color: #8d8da0 !important;
  }
  .pos-codecanyon .pos-shell-mobile-tabbar ::v-deep(.pos-shell-mobile-tab-toggle:focus),
  .pos-codecanyon .pos-shell-mobile-tabbar ::v-deep(.pos-shell-mobile-tab-toggle:hover) {
    background: transparent !important;
    box-shadow: none !important;
  }
}

/* Mobile charges-row tweaks (declared after the ≤768px block to win the
   cascade — addresses two issues at Mobile M (375px) and Mobile L (425px)). */
@media (max-width: 768px) {
  /* The ≤480px rule above gives shipping `grid-column: 1 / -1`, which
     makes it span the whole row underneath tax + discount. Reset it so
     all three sit side-by-side in the 3 equal columns. */
  .pos-codecanyon .pos-shell-charges-row > label:nth-child(3) {
    grid-column: auto !important;
  }
  /* Shipping's currency prefix (e.g. "MAD", "USD") is wider than tax's
     "%" / discount's "%" — trim its font + padding so all three input
     areas are visually matched. */
  .pos-codecanyon .pos-shell-charges-row > label:nth-child(3) > div > span {
    padding-left: 4px !important;
    padding-right: 0 !important;
    font-size: 10px !important;
    flex: 0 0 auto;
  }
}

/* ===== Small phones (≤480px): keep mockup layout but tighter ===== */
@media (max-width: 480px) {
  .pos-codecanyon .pos-shell-products-grid {
    gap: 6px;
    padding: 6px 8px 10px;
  }
}

/* ===== Tablet (481–1024px): warehouse + customer share a row 50/50,
   categories + brands share a row 50/50, like the design mockup.
   Declared LAST so it wins the cascade over the ≤768px mobile rules.
   Uses high order numbers (50–53) so they don't collide with the mobile
   orders 1–13 already on the icon buttons / register-status. ===== */
@media (min-width: 481px) and (max-width: 1024px) {
  .pos-codecanyon .pos-shell-header {
    flex-wrap: wrap !important;
    row-gap: 8px !important;
    column-gap: 6px !important;
  }
  /* Hide spacer + dividers so the row layout doesn't break */
  .pos-codecanyon .pos-shell-header > div[style*="flex: 1"],
  .pos-codecanyon .pos-shell-header > div[style*="width: 1px"] {
    display: none !important;
  }
  /* Hide the standalone quick-add button on tablet — it would land on its
     own awkward row. The customer drawer's own "Quick add" button still
     covers this action. */
  .pos-codecanyon .pos-shell-header > button[title*="Quick"] {
    display: none !important;
  }
  /* Warehouse — 50% of its row */
  .pos-codecanyon .pos-shell-header > .pos-wh-trigger {
    flex: 1 1 calc(50% - 4px) !important;
    width: calc(50% - 4px) !important;
    min-width: 0 !important;
    max-width: none !important;
    order: 50 !important;
  }
  /* Customer — 50% of its row, paired with warehouse */
  .pos-codecanyon .pos-shell-header > .pos-cust-trigger {
    flex: 1 1 calc(50% - 4px) !important;
    width: calc(50% - 4px) !important;
    min-width: 0 !important;
    max-width: none !important;
    order: 51 !important;
  }
  /* Categories — 50% of its row */
  .pos-codecanyon .pos-shell-header > .pos-cat-trigger:not(.pos-brand-trigger) {
    flex: 1 1 calc(50% - 4px) !important;
    width: calc(50% - 4px) !important;
    min-width: 0 !important;
    max-width: none !important;
    order: 52 !important;
  }
  /* Brands — 50% of its row, paired with categories */
  .pos-codecanyon .pos-shell-header > .pos-cat-trigger.pos-brand-trigger {
    flex: 1 1 calc(50% - 4px) !important;
    width: calc(50% - 4px) !important;
    min-width: 0 !important;
    max-width: none !important;
    order: 53 !important;
  }
}

</style>

<!-- Non-scoped block: targets html/body/#app which Vue's scoped CSS cannot reach.
     Util.toggleFullScreen() requests fullscreen on document.documentElement (the
     <html> element), so the :fullscreen pseudo-class lands there. Without these
     rules the browser's default :fullscreen { background: black } shows through
     and the document chain (html → body → #app → .pos-codecanyon) doesn't
     guarantee 100% height, leaving the layout misaligned in fullscreen. -->
<style>
/* ============================================
   POS Receipt — QR codes row (ZATCA + Invoice URL)
   `flex-wrap: nowrap` forces the two blocks to stay inline even inside
   the narrow size="sm" modal; QRs are 100px so 2 × 100 + 10 gap = 210px
   comfortably fits a ~280px content column.
   ============================================ */
#invoice-POS .receipt-qr-row {
  display: flex !important;
  flex-direction: row !important;
  flex-wrap: nowrap !important;
  justify-content: center !important;
  align-items: flex-start !important;
  gap: 10px !important;
  width: 100%;
}
#invoice-POS .receipt-qr-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 0 0 auto;
  width: 100px;
  margin: 0;
}
#invoice-POS .receipt-qr-title {
  font-weight: 700;
  font-size: 10px;
  letter-spacing: 1px;
  text-transform: uppercase;
  text-align: center;
  margin: 0 0 4px;
  line-height: 1.2;
  display: block;
  width: 100%;
}
/* The QR mount: same layout box for both ZATCA and Invoice so they align identically. */
#invoice-POS .receipt-qr-canvas {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100px;
  height: 100px;
  margin: 0 auto;
}
/* qrcode.js' CDN build emits a <canvas> + an <img>; the local minimal
   vendor build only emits a <canvas>. Our render code (ensureQrImg) makes
   sure an <img> always exists by converting canvas → toDataURL when
   needed, then we show only the <img>. The <img>'s data URL survives
   `innerHTML` cloning into the print popup (canvas pixel data does not). */
#invoice-POS .receipt-qr-canvas img {
  display: block !important;
  margin: 0 auto !important;
  width: 100px !important;
  height: 100px !important;
  max-width: 100px !important;
}
#invoice-POS .receipt-qr-canvas canvas,
#invoice-POS .receipt-qr-canvas table {
  display: none !important;
}

/* The html / body / #app / .pos-codecanyon chain pinned to 100vh +
   overflow:hidden — desktop / tablet-landscape only. On phones (≤768px)
   the POS layout (header + products + cart + tab bar) is taller than
   100vh and needs the page itself to scroll; freezing the chain here
   broke scroll after entering fullscreen via the in-app button.

   Every selector below is qualified with `html.pos-active` so these
   POS-only fullscreen overrides do not fire on dashboard / settings /
   any other route — otherwise the topnav fullscreen button there would
   hide the topnav+sidebar and zero the main-content-wrap padding,
   shifting the footer. The class is added in mounted() above and
   removed in beforeDestroy(). */
@media (min-width: 769px) {
  html.pos-active:fullscreen,
  html.pos-active:-webkit-full-screen,
  html.pos-active:-moz-full-screen,
  html.pos-active:-ms-fullscreen {
    width: 100% !important;
    height: 100% !important;
    overflow: hidden !important;
  }
  html.pos-active:fullscreen body,
  html.pos-active:-webkit-full-screen body,
  html.pos-active:-moz-full-screen body,
  html.pos-active:-ms-fullscreen body {
    width: 100% !important;
    height: 100% !important;
    min-height: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
  }
  html.pos-active:fullscreen #app,
  html.pos-active:fullscreen .main-content-wrap,
  html.pos-active:fullscreen .app-admin-wrap-layout-1,
  html.pos-active:fullscreen .app-admin-wrap,
  html.pos-active:fullscreen .main-content {
    width: 100% !important;
    height: 100% !important;
    min-height: 100% !important;
    max-height: 100vh !important;
    overflow: hidden !important;
  }
  /* Pin the POS root to 100vh (NOT 100% of main-content-wrap) — <main>
     has no explicit height in the global layout, so 100% would resolve
     to 0 and leave the pay bar / products scroll without a viewport
     anchor. 100vh forces the POS shell to own the entire screen so the
     freed space (after the topnav/sidebar/app-footer hide below) goes
     to .pos-shell-products-scroll, not to a gap below Pay Now. */
  html.pos-active:fullscreen .pos-codecanyon {
    width: 100% !important;
    height: 100vh !important;
    min-height: 100vh !important;
    max-height: 100vh !important;
    overflow: hidden !important;
  }
}

/* Background color for the fullscreen viewport applies on all sizes —
   without it the browser default :fullscreen { background: black }
   shows through (especially around the rounded layout edges). */
html.pos-active:fullscreen,
html.pos-active:-webkit-full-screen,
html.pos-active:-moz-full-screen,
html.pos-active:-ms-fullscreen {
  background: #f7f7fb !important;
}
html.pos-active:fullscreen body,
html.pos-active:-webkit-full-screen body,
html.pos-active:-moz-full-screen body,
html.pos-active:-ms-fullscreen body {
  margin: 0 !important;
  padding: 0 !important;
  background: #f7f7fb !important;
}

/* In fullscreen, also hide the global app sidebar / topnav / footer
   so the POS owns the screen. Hiding .footer_wrap (and the flex-grow-1
   spacer the layout puts between router-view and the footer) is what
   keeps the area below Pay Now from showing the global copyright bar
   and pushing layout — the freed space then goes to
   .pos-shell-products-scroll inside the POS shell. */
html.pos-active:fullscreen .side-content-wrap,
html.pos-active:fullscreen .main-header,
html.pos-active:fullscreen .layout-sidebar-large .main-header,
html.pos-active:fullscreen .layout-sidebar-large .side-content-wrap,
html.pos-active:fullscreen .main-content-wrap > .footer_wrap,
html.pos-active:fullscreen .main-content-wrap > .flex-grow-1 {
  display: none !important;
}
html.pos-active:fullscreen .main-content-wrap,
html.pos-active:fullscreen .layout-sidebar-large .main-content-wrap {
  margin: 0 !important;
  padding: 0 !important;
  width: 100% !important;
  max-width: 100% !important;
}

/* ============================================
   Today Sales modal — wrapper overrides
   (Bootstrap-vue teleports the modal to body, outside the scoped
   style scope. Wrapper-level styling lives here unscoped.)
   ============================================ */
.ts-modal-modern .modal-dialog {
  max-width: 640px;
}
.ts-modal-modern .modal-content {
  border: 0;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 24px 60px -20px rgba(31, 31, 44, 0.35),
              0 8px 24px -8px rgba(31, 31, 44, 0.18);
}
.ts-modal-modern .modal-body,
.ts-modal-modern .ts-modal-body-wrap {
  padding: 0 !important;
  background: #f7f7fb;
}

/* ============================================
   POS Settings modal — wrapper overrides
   ============================================ */
.ps-modal-modern .modal-dialog {
  max-width: 720px;
}
.ps-modal-modern .modal-content {
  border: 0;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 24px 60px -20px rgba(31, 31, 44, 0.35),
              0 8px 24px -8px rgba(31, 31, 44, 0.18);
}
.ps-modal-modern .modal-body,
.ps-modal-modern .ps-modal-body-wrap {
  padding: 0 !important;
  background: #ffffff;
}

/* ============================================
   Clear-cart confirmation modal — opened by ESC
   shortcut. Bootstrap-vue teleports the modal
   outside the scoped style scope so the wrapper
   styling lives here unscoped.
   ============================================ */
.pos-confirm-clear-modal .modal-dialog {
  max-width: 420px;
}
.pos-confirm-clear-modal .modal-content {
  border: 0;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 24px 60px -20px rgba(31, 31, 44, 0.35),
              0 8px 24px -8px rgba(31, 31, 44, 0.18);
}
.pos-confirm-clear-body {
  padding: 24px !important;
  background: #ffffff;
}
.pos-confirm-clear {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.pos-confirm-clear-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #fef2f2;
  color: #d64545;
  display: grid;
  place-items: center;
  margin-bottom: 16px;
}
.pos-confirm-clear-icon i,
.pos-confirm-clear-icon svg {
  width: 28px;
  height: 28px;
}
.pos-confirm-clear-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f1f2c;
  margin: 0 0 6px;
  line-height: 1.35;
}
.pos-confirm-clear-msg {
  font-size: 13px;
  color: #54546a;
  margin: 0 0 20px;
  line-height: 1.5;
}
.pos-confirm-clear-actions {
  display: flex;
  gap: 8px;
  width: 100%;
}
.pos-confirm-btn {
  flex: 1;
  height: 40px;
  padding: 0 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  transition: all 120ms ease;
  font-family: inherit;
}
.pos-confirm-btn i,
.pos-confirm-btn svg {
  width: 15px;
  height: 15px;
}
.pos-confirm-btn-secondary {
  background: #ffffff;
  color: #1f1f2c;
  border: 1px solid #e6e6ec;
}
.pos-confirm-btn-secondary:hover {
  background: #f5f3fd;
  color: #6f53d9;
  border-color: #6f53d9;
}
.pos-confirm-btn-danger {
  background: #d64545;
  color: #ffffff;
  border: 1px solid #d64545;
}
.pos-confirm-btn-danger:hover {
  background: #b83838;
  border-color: #b83838;
}
</style>
