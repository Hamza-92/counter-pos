<template>
  <div class="vertical-sidebar-wrapper" :class="{ 'is-mobile': isMobileOrTablet, 'collapsed': isCollapsed, 'mobile-open': mobileOpen }">
    <vue-perfect-scrollbar
      ref="ps"
      :settings="{ suppressScrollX: true, wheelPropagation: false }"
      class="vertical-sidebar ps scroll"
    >
      <!-- Logo Section -->
      <div class="vertical-sidebar-header">
        <div class="header-brand" @click="navigateToDashboard">
          <div class="sidebar-logo">
            <img 
              v-if="currentUser && currentUser.logo" 
              :src="'/images/' + currentUser.logo" 
              alt="logo" 
              class="logo-image"
            />
            <div v-else class="logo-placeholder">
              {{ (currentUser && currentUser.company) ? currentUser.company[0] : 'S' }}
            </div>
          </div>
          <div class="company-name" v-if="!isCollapsed && currentUser && !currentUser.hide_site_name">
            {{ currentUser.company || 'Stocky' }}
          </div>
        </div>
      </div>

      <!-- Navigation Menu -->
      <nav class="vertical-nav-menu">
        <ul class="nav-list">
          <!-- Dashboard -->
          <li
            :class="{ active: isActiveRoute('dashboard') }"
            class="nav-item"
          >
            <router-link to="/app/dashboard" class="nav-link">
              <lucide-icon class="nav-icon" name="bar-chart" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t("dashboard") }}</span>
            </router-link>
          </li>

         

          <!-- Store -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('Store_settings_view') ||
              currentUserPermissions.includes('Orders_view') ||
              currentUserPermissions.includes('Collections_view') ||
              currentUserPermissions.includes('Banners_view') ||
              currentUserPermissions.includes('Subscribers_view') ||
              currentUserPermissions.includes('Messages_view')
            )"
            :class="{ active: isActiveRoute('Store'), 'has-submenu': true, open: openMenus.includes('Store') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('Store')" class="nav-link">
              <lucide-icon class="nav-icon" name="shopping-bag" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Store') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('Store') && !isCollapsed">
              <li class="submenu-item">
                <a href="/online_store" target="_blank" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="store" />
                  <span>{{ $t('Visit_Online_Store') }}</span>
                </a>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Store_settings_view')">
                <router-link to="/app/Store/Settings" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="settings" />
                  <span>{{ $t('Settings') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Orders_view')">
                <router-link to="/app/Store/Orders" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="receipt" />
                  <span>{{ $t('Online_Orders') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Collections_view')">
                <router-link to="/app/Store/Collections" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="check" />
                  <span>{{ $t('Collections') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Banners_view')">
                <router-link to="/app/Store/Banners" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="wallet" />
                  <span>{{ $t('Banners') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Subscribers_view')">
                <router-link to="/app/Store/Subscribers" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="users" />
                  <span>{{ $t('Subscribers') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Messages_view')">
                <router-link to="/app/Store/Messages" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="message-square" />
                  <span>{{ $t('Messages') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Store_settings_view')">
                <router-link to="/app/Store/InviteCodes" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="ticket" />
                  <span>{{ $t('Invite_Codes') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Store_settings_view')">
                <router-link to="/app/Store/PendingCustomers" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="clock" />
                  <span>{{ $t('Pending_Customers') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- People -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('Customers_view') ||
              currentUserPermissions.includes('Suppliers_view') ||
              currentUserPermissions.includes('customers_import') ||
              currentUserPermissions.includes('Suppliers_import')
            )"
            :class="{ active: isActiveRoute('People'), 'has-submenu': true, open: openMenus.includes('People') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('People')" class="nav-link">
              <lucide-icon class="nav-icon" name="users" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('People') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('People') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Customers_view')">
                <router-link to="/app/People/Customers" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shield-check" />
                  <span>{{ $t('Customers') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Customers_add')">
                <router-link to="/app/People/Customers/create" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="plus" />
                  <span>{{ $t('Add') }} {{ $t('Customer') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('customers_import')">
                <router-link to="/app/People/Customers_import" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="download" />
                  <span>{{ $t('Import_Customers') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Customers_view')">
                <router-link to="/app/People/Customers_without_ecommerce" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shield-check" />
                  <span>{{ $t('Customers_without_Login') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Customers_view')">
                <router-link to="/app/People/Customers_ecommerce" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shield-check" />
                  <span>{{ $t('Customers_with_Login') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Customers_view')">
                <a href="/portal" class="submenu-link" target="_blank" rel="noopener">
                  <lucide-icon class="submenu-icon" name="app-window" />
                  <span>{{ $t('Client_Portal') }}</span>
                </a>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Suppliers_view')">
                <router-link to="/app/People/Suppliers" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shield-check" />
                  <span>{{ $t('Suppliers') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Suppliers_add')">
                <router-link to="/app/People/Suppliers/create" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="plus" />
                  <span>{{ $t('Add') }} {{ $t('Supplier') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Suppliers_import')">
                <router-link to="/app/People/Suppliers_import" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="download" />
                  <span>{{ $t('Import_Suppliers') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- User Management -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('users_view') ||
              currentUserPermissions.includes('permissions_view')
            )"
            :class="{ active: isActiveRoute('User_Management'), 'has-submenu': true, open: openMenus.includes('User_Management') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('User_Management')" class="nav-link">
              <lucide-icon class="nav-icon" name="shield-check" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('User_Management') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('User_Management') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('users_view')">
                <router-link to="/app/User_Management/Users" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shield-check" />
                  <span>{{ $t('Users') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('permissions_view')">
                <router-link to="/app/User_Management/permissions" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="key" />
                  <span>{{ $t('GroupPermissions') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- Products -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('products_add') ||
              currentUserPermissions.includes('products_view') ||
              currentUserPermissions.includes('product_import') ||
              currentUserPermissions.includes('opening_stock_import') ||
              currentUserPermissions.includes('barcode_view') ||
              currentUserPermissions.includes('brand') ||
              currentUserPermissions.includes('unit') ||
              currentUserPermissions.includes('count_stock') ||
              currentUserPermissions.includes('category') ||
              currentUserPermissions.includes('subcategory')
            )"
            :class="{ active: isActiveRoute('products'), 'has-submenu': true, open: openMenus.includes('products') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('products')" class="nav-link">
              <lucide-icon class="nav-icon" name="library-big" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Products') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('products') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('products_add')">
                <router-link to="/app/products/store" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('AddProduct') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('products_view')">
                <router-link to="/app/products/list" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('productsList') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('product_import')">
                <router-link to="/app/products/import" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="download" />
                  <span>{{ $t('import_products') }}</span>
                </router-link>
              </li>

              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('product_import')">
                <router-link to="/app/products/import-update" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="pencil" />
                  <span>Import (Update Only)</span>
                </router-link>
              </li>
              
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('opening_stock_import')">
                <router-link to="/app/products/opening_stock_import" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('Opening_Stock') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('barcode_view')">
                <router-link to="/app/products/barcode" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="barcode" />
                  <span>{{ $t('Printbarcode') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('count_stock')">
                <router-link to="/app/products/count_stock" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="check-check" />
                  <span>{{ $t('CountStock') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('category')">
                <router-link to="/app/products/Categories" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="copy" />
                  <span>{{ $t('Categories') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('subcategory')">
                <router-link to="/app/products/SubCategories" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="library" />
                  <span>{{ $t('SubCategory') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('brand')">
                <router-link to="/app/products/Brands" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="bookmark" />
                  <span>{{ $t('Brand') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('unit')">
                <router-link to="/app/products/Units" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="quote" />
                  <span>{{ $t('Units') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && (currentUserPermissions.includes('view_batches') || currentUserPermissions.includes('batch_view'))">
                <router-link to="/app/products/Batches" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="heart-pulse" />
                  <span>{{ $t('Batches') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- Adjustments -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('adjustment_view') ||
              currentUserPermissions.includes('adjustment_add')
            )"
            :class="{ active: isActiveRoute('adjustments'), 'has-submenu': true, open: openMenus.includes('adjustments') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('adjustments')" class="nav-link">
              <lucide-icon class="nav-icon" name="map-pin" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('StockAdjustement') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('adjustments') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('adjustment_add')">
                <router-link to="/app/adjustments/store" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('CreateAdjustment') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('adjustment_view')">
                <router-link to="/app/adjustments/list" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('ListAdjustments') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- Purchases -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('Purchases_view') ||
              currentUserPermissions.includes('Purchases_add')
            )"
            :class="{ active: isActiveRoute('purchases'), 'has-submenu': true, open: openMenus.includes('purchases') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('purchases')" class="nav-link">
              <lucide-icon class="nav-icon" name="receipt" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Purchases') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('purchases') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Purchases_add')">
                <router-link to="/app/purchases/store" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('AddPurchase') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Purchases_view')">
                <router-link to="/app/purchases/list" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('ListPurchases') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Purchases_add')">
                <router-link to="/app/purchases/import_purchases" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('Import_Purchases') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- Sales -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('Sales_view') ||
              currentUserPermissions.includes('Sales_add') ||
              currentUserPermissions.includes('Pos_view') ||
              currentUserPermissions.includes('customer_display_screen_setup') ||
              currentUserPermissions.includes('shipment') ||
              currentUserPermissions.includes('real_time_sales_counter')
            )"
            :class="{ active: isActiveRoute('sales'), 'has-submenu': true, open: openMenus.includes('sales') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('sales')" class="nav-link">
              <lucide-icon class="nav-icon" name="shopping-cart" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Sales') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('sales') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Sales_add')">
                <router-link to="/app/sales/store" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('AddSale') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Sales_add')">
                <router-link to="/app/sales/import_sales" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('Import_Sales') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Sales_view')">
                <router-link to="/app/sales/list" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('ListSales') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Pos_view')">
                <router-link to="/app/pos" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>POS</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('customer_display_screen_setup')">
                <router-link to="/app/customer-display/setup" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{$t('Customer_Screen')}}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('shipment')">
                <router-link to="/app/sales/shipment" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('Shipments') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('real_time_sales_counter')">
                <router-link to="/app/real-time-sales-counter" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shopping-cart" />
                  <span>{{ $t("Real_time_Sales_Counter") || "Real-time Sales Counter" }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- Sales Return -->
          <li
            v-if="currentUserPermissions && currentUserPermissions.includes('Sale_Returns_view')"
            :class="{ active: isActiveRoute('sale_return') }"
            class="nav-item"
          >
            <router-link to="/app/sale_return/list" class="nav-link">
              <lucide-icon class="nav-icon" name="chevron-right" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t("SalesReturn") }}</span>
            </router-link>
          </li>

          <!-- Quotations -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('Quotations_view') ||
              currentUserPermissions.includes('Quotations_add')
            )"
            :class="{ active: isActiveRoute('quotations'), 'has-submenu': true, open: openMenus.includes('quotations') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('quotations')" class="nav-link">
              <lucide-icon class="nav-icon" name="shopping-basket" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Quotations') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('quotations') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Quotations_add')">
                <router-link to="/app/quotations/store" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('AddQuote') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Quotations_view')">
                <router-link to="/app/quotations/list" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('ListQuotations') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- Purchase Return -->
          <li
            v-if="currentUserPermissions && currentUserPermissions.includes('Purchase_Returns_view')"
            :class="{ active: isActiveRoute('purchase_return') }"
            class="nav-item"
          >
            <router-link to="/app/purchase_return/list" class="nav-link">
              <lucide-icon class="nav-icon" name="chevron-left" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t("PurchasesReturn") }}</span>
            </router-link>
          </li>

          <!-- Transfers -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('transfer_view') ||
              currentUserPermissions.includes('transfer_add')
            )"
            :class="{ active: isActiveRoute('transfers'), 'has-submenu': true, open: openMenus.includes('transfers') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('transfers')" class="nav-link">
              <lucide-icon class="nav-icon" name="arrow-left" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('StockTransfers') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('transfers') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('transfer_add')">
                <router-link to="/app/transfers/store" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('CreateTransfer') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('transfer_view')">
                <router-link to="/app/transfers/list" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('ListTransfers') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- Damages -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('damage_view')
            )"
            :class="{ active: isActiveRoute('damages'), 'has-submenu': true, open: openMenus.includes('damages') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('damages')" class="nav-link">
              <lucide-icon class="nav-icon" name="shopping-bag" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Damages') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('damages') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('damage_view')">
                <router-link to="/app/damages/store" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('Create_Damage') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('damage_view')">
                <router-link to="/app/damages/list" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('Damages') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- HRM -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('company') ||
              currentUserPermissions.includes('department') ||
              currentUserPermissions.includes('designation') ||
              currentUserPermissions.includes('office_shift') ||
              currentUserPermissions.includes('view_employee') ||
              currentUserPermissions.includes('attendance') ||
              currentUserPermissions.includes('leave') ||
              currentUserPermissions.includes('holiday') ||
              currentUserPermissions.includes('payroll')
            )"
            :class="{ active: isActiveRoute('hrm'), 'has-submenu': true, open: openMenus.includes('hrm') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('hrm')" class="nav-link">
              <lucide-icon class="nav-icon" name="library" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('hrm') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('hrm') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('company')">
                <router-link to="/app/hrm/company" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="briefcase-business" />
                  <span>{{ $t('Company') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('department')">
                <router-link to="/app/hrm/departments" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="store" />
                  <span>{{ $t('Departments') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('designation')">
                <router-link to="/app/hrm/designations" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="camera" />
                  <span>{{ $t('Designations') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('office_shift')">
                <router-link to="/app/hrm/office_Shift" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="clock" />
                  <span>{{ $t('Office_Shift') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('view_employee')">
                <router-link to="/app/hrm/employees" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="wrench" />
                  <span>{{ $t('Employees') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('attendance')">
                <router-link to="/app/hrm/attendance" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="clock" />
                  <span>{{ $t('Attendance') }}</span>
                </router-link>
              </li>
              <li class="submenu-item has-nested" v-if="currentUserPermissions && currentUserPermissions.includes('leave')">
                <a href="#" @click.prevent="toggleNestedSubmenu('leave')" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="calendar" />
                  <span>{{ $t('Leave_request') }}</span>
                  <lucide-icon class="nested-arrow" name="chevron-down" />
                </a>
                <ul class="nested-submenu" v-if="openNestedMenus.includes('leave')">
                  <li><router-link to="/app/hrm/leaves/list" class="nested-link">{{ $t('Leave_request') }}</router-link></li>
                  <li><router-link to="/app/hrm/leaves/type" class="nested-link">{{ $t('Leave_type') }}</router-link></li>
                </ul>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('holiday')">
                <router-link to="/app/hrm/holidays" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="bell-ring" />
                  <span>{{ $t('Holidays') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('payroll')">
                <router-link to="/app/hrm/payrolls" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="banknote" />
                  <span>{{ $t('Payroll') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- Accounting -->
          <li
            v-show="currentUserPermissions && (
              currentUserPermissions.includes('expense_view') ||
              currentUserPermissions.includes('expense_add') ||
              currentUserPermissions.includes('deposit_view') ||
              currentUserPermissions.includes('deposit_add') ||
              currentUserPermissions.includes('account') ||
              currentUserPermissions.includes('transfer_money') ||
              currentUserPermissions.includes('accounting_dashboard') ||
              currentUserPermissions.includes('chart_of_accounts') ||
              currentUserPermissions.includes('journal_entries') ||
              currentUserPermissions.includes('trial_balance') ||
              currentUserPermissions.includes('accounting_profit_loss') ||
              currentUserPermissions.includes('balance_sheet') ||
              currentUserPermissions.includes('accounting_tax_report')
            )"
            :class="{ active: isActiveRoute('accounting'), 'has-submenu': true, open: openMenus.includes('accounting') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('accounting')" class="nav-link">
              <lucide-icon class="nav-icon" name="wallet" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Accounting') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('accounting') && !isCollapsed">
              <!-- NEW FEATURE - SAFE ADDITION: Advanced Accounting under Accounting -->
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('accounting_dashboard')">
                <router-link to="/app/accounting-v2/dashboard" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="trending-up" />
                  <span>{{ $t("dashboard") }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('chart_of_accounts')">
                <router-link to="/app/accounting-v2/chart-of-accounts" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="database" />
                  <span>{{ $t('Chart_of_Accounts_Title') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('journal_entries')">
                <router-link to="/app/accounting-v2/journal-entries" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('Journal_Entries_Title') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('journal_entries')">
                <router-link to="/app/accounting-v2/reports/trial-balance" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="trending-up" />
                  <span>{{ $t('Trial_Balance_Title') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('journal_entries')">
                <router-link to="/app/accounting-v2/reports/profit-and-loss" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="wallet" />
                  <span>{{ $t('Profit_Loss_Title') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('journal_entries')">
                <router-link to="/app/accounting-v2/reports/balance-sheet" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="pie-chart" />
                  <span>{{ $t('Balance_Sheet_Title') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('accounting_tax_report')">
                <router-link to="/app/accounting-v2/reports/tax-report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="receipt-text" />
                  <span>{{ $t('Tax_Summary_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('account')">
                <router-link to="/app/accounts" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('List_accounts') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('transfer_money')">
                <router-link to="/app/transfer_money" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('Transfers_Money') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('expense_add')">
                <router-link to="/app/expenses/store" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('Create_Expense') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('expense_view')">
                <router-link to="/app/expenses/list" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('ListExpenses') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('deposit_add')">
                <router-link to="/app/deposits/store" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('Create_deposit') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('deposit_view')">
                <router-link to="/app/deposits/list" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('List_Deposit') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('expense_view')">
                <router-link to="/app/expenses/category" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('Expense_Category') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('deposit_view')">
                <router-link to="/app/deposits/category" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('Deposit_Category') }}</span>
                </router-link>
              </li>
            </ul>
          </li>


          

          <!-- Subscription Product -->
          <li
            v-if="currentUserPermissions && currentUserPermissions.includes('subscription_product')"
            :class="{ active: isActiveRoute('subscription_product') }"
            class="nav-item"
          >
            <router-link to="/app/subscription_product/list" class="nav-link">
              <lucide-icon class="nav-icon" name="dollar-sign" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Subscription_Product') }}</span>
            </router-link>
          </li>

          <!-- Service & Maintenance -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('service_jobs')"
            :class="{ active: isActiveRoute('service'), 'has-submenu': true, open: openMenus.includes('service') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('service')" class="nav-link">
              <lucide-icon class="nav-icon" name="wrench" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Service_Maintenance') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('service') && !isCollapsed">
              <li class="submenu-item">
                <router-link to="/app/service/jobs" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('Service_Jobs') }}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link :to="{ path: '/app/service/jobs', query: { status: 'quoted' } }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="hourglass" />
                  <span>{{ $t('Awaiting_Approval') || 'Awaiting Approval' }}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link :to="{ path: '/app/service/jobs', query: { status: 'ready' } }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="bell" />
                  <span>{{ $t('Ready_For_Pickup') || 'Ready for Pickup' }}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link :to="{ path: '/app/service/jobs', query: { payment_status: 'unpaid' } }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="banknote" />
                  <span>{{ $t('Awaiting_Payment') || 'Awaiting Payment' }}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link to="/app/service/technicians" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('Service_Technicians') }}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link to="/app/service/checklist-categories" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="folder" />
                  <span>{{ $t('Checklist_Categories') }}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link to="/app/service/checklists" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="check" />
                  <span>{{ $t('Checklist_Items') }}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link to="/app/service/history" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="calendar-days" />
                  <span>{{ $t('Maintenance_History') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- Assets -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('assets')"
            :class="{ active: isActiveRoute('assets'), 'has-submenu': true, open: openMenus.includes('assets') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('assets')" class="nav-link">
              <lucide-icon class="nav-icon" name="settings" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Assets')}}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('assets') && !isCollapsed">
              <li class="submenu-item">
                <router-link to="/app/assets/store" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="file-plus" />
                  <span>{{ $t('Add_Asset')}}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link to="/app/assets/list" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('Assets_List')}}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link to="/app/assets/due" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="clock" />
                  <span>{{ $t('Due_Assets')}}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link to="/app/assets/category" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="folder" />
                  <span>{{ $t('Asset_Category') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- Projects -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('projects')"
            :class="{ active: isActiveRoute('projects') }"
            class="nav-item"
          >
            <router-link to="/app/projects" class="nav-link">
              <lucide-icon class="nav-icon" name="archive" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Projects') }}</span>
            </router-link>
          </li>

          <!-- Contracts -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('contracts')"
            :class="{ active: isActiveRoute('contracts') }"
            class="nav-item"
          >
            <router-link to="/app/contracts" class="nav-link">
              <lucide-icon class="nav-icon" name="clipboard-list" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Contracts') || 'Contracts' }}</span>
            </router-link>
          </li>

          <!-- Tasks -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('tasks')"
            :class="{ active: isActiveRoute('tasks') }"
            class="nav-item"
          >
            <router-link to="/app/tasks" class="nav-link">
              <lucide-icon class="nav-icon" name="check" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Tasks') }}</span>
            </router-link>
          </li>

          <!-- Bookings (with submenu: Booking List, Google Calendar) -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('bookings')"
            :class="{ active: isActiveRoute('bookings'), 'has-submenu': true, open: openMenus.includes('bookings') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('bookings')" class="nav-link">
              <lucide-icon class="nav-icon" name="calendar-days" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Bookings') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('bookings') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('bookings')">
                <router-link to="/app/bookings/list" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('Booking_List') || 'Booking List' }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('bookings')">
                <router-link to="/app/bookings/calendar" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="calendar-days" />
                  <span>{{ $t('Calendar_View') }}</span>
                </router-link>
              </li>
</ul>
          </li>

          <!-- Commissions -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('commissions_view')"
            :class="{ active: isActiveRoute('commissions'), 'has-submenu': true, open: openMenus.includes('commissions') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('commissions')" class="nav-link">
              <lucide-icon class="nav-icon" name="wallet" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Commissions') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('commissions') && !isCollapsed">
              <li class="submenu-item">
                <router-link to="/app/commissions/programs" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="files" />
                  <span>{{ $t('Commission_Programs') }}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link to="/app/commissions/agents" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="users" />
                  <span>{{ $t('Sales_Agents') }}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link to="/app/commissions/rules" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="settings" />
                  <span>{{ $t('Commission_Rules') }}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link to="/app/commissions/receipts" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="receipt" />
                  <span>{{ $t('Commission_Receipts') }}</span>
                </router-link>
              </li>
              <li class="submenu-item">
                <router-link to="/app/commissions/report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="trending-up" />
                  <span>{{ $t('Commission_Report') }}</span>
                </router-link>
              </li>
            </ul>
          </li>

           <!-- woocommerce_settings -->
           <li
           v-if="currentUserPermissions && currentUserPermissions.includes('woocommerce_settings')"
            :class="{ active: isActiveRoute('woocommerce_settings') }"
            class="nav-item"
          >
            <router-link to="/app/woocommerce" class="nav-link">
              <lucide-icon class="nav-icon" name="shopping-cart" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t("WooCommerce_Settings") }}</span>
            </router-link>
          </li>

          <!-- Knowledge Base -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('knowledge_base_view')"
            :class="{ active: isActiveRoute('knowledge_base') }"
            class="nav-item"
          >
            <router-link to="/app/knowledge-base" class="nav-link">
              <lucide-icon class="nav-icon" name="book" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Knowledge_Base') || 'Knowledge Base' }}</span>
            </router-link>
          </li>

          <!-- Settings -->
          <li
            v-show="currentUserPermissions && (  
              currentUserPermissions.includes('setting_system') ||
              currentUserPermissions.includes('update_settings') ||
              currentUserPermissions.includes('sms_settings') ||
              currentUserPermissions.includes('login_device_management') ||
              currentUserPermissions.includes('notification_template') ||
              currentUserPermissions.includes('pos_settings') ||
              currentUserPermissions.includes('appearance_settings') ||
              currentUserPermissions.includes('translations_settings') ||
              currentUserPermissions.includes('module_settings') ||
              currentUserPermissions.includes('quickbooks_settings') ||
              currentUserPermissions.includes('payment_gateway') ||
              currentUserPermissions.includes('mail_settings') ||
              currentUserPermissions.includes('warehouse') ||
              currentUserPermissions.includes('warehouse_locations') ||
              currentUserPermissions.includes('backup') ||
              currentUserPermissions.includes('payment_methods') ||
              currentUserPermissions.includes('currency') ||
              currentUserPermissions.includes('system_health_view') ||
              currentUserPermissions.includes('webhooks_view')
            )"
            :class="{ active: isActiveRoute('settings'), 'has-submenu': true, open: openMenus.includes('settings') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('settings')" class="nav-link">
              <lucide-icon class="nav-icon" name="database-zap" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Settings') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('settings') && !isCollapsed">
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('setting_system')">
                <router-link to="/app/settings/System_settings" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="settings" />
                  <span>{{ $t('SystemSettings') }}</span>
                </router-link>
              </li>

             
             
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('quickbooks_settings')">
                <router-link to="/app/settings/quickbooks_sync" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="banknote" />
                  <span>{{ $t('Quickbooks_Sync') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('appearance_settings')">
                <router-link to="/app/settings/appearance_settings" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="database-zap" />
                  <span>{{ $t('Dynamic_Appearance') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('translations_settings')">
                <router-link to="/app/settings/translations_settings" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="database-zap" />
                  <span>{{ $t('Languages') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('payment_methods')">
                <router-link to="/app/settings/payment_methods" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="banknote" />
                  <span>{{ $t('Payment_Methods') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('sms_settings')">
                <router-link to="/app/settings/sms_settings" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="message-square" />
                  <span>{{ $t('sms_settings') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('notification_template')">
                <router-link to="/app/settings/sms_templates" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="message-square" />
                  <span>{{ $t('sms_templates') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('mail_settings')">
                <router-link to="/app/settings/mail_settings" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="mail" />
                  <span>{{ $t('mail_settings') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('notification_template')">
                <router-link to="/app/settings/email_templates" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="mail" />
                  <span>{{ $t('email_templates') }}</span>
                </router-link>
              </li>
              <!-- POS Settings (dedicated page) -->
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('pos_settings')">
                <router-link to="/app/settings/pos_settings" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="database-zap" />
                  <span>{{ $t('Pos_Settings') }}</span>
                </router-link>
              </li>
              <!-- POS Receipt page (dedicated page) -->
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('pos_settings')">
                <router-link to="/app/settings/pos_receipt" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="calculator" />
                  <span>{{ $t('POS_Receipt') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('module_settings')">
                <router-link to="/app/settings/module_settings" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="database-zap" />
                  <span>{{ $t('module_settings') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('update_settings')">
                <router-link to="/app/settings/update_settings" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="arrow-up-circle" />
                  <span>{{ $t('update_settings') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('payment_gateway')">
                <router-link to="/app/settings/payment_gateway" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="banknote" />
                  <span>{{ $t('Payment_Gateway') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('warehouse')">
                <router-link to="/app/settings/Warehouses" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="store" />
                  <span>{{ $t('Warehouses') }}</span>
                </router-link>
              </li>

              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('warehouse_locations')">
                <router-link to="/app/settings/Warehouse_Locations" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="map-pin" />
                  <span>{{ $t('Warehouse_Locations') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('currency')">
                <router-link to="/app/settings/Currencies" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="dollar-sign" />
                  <span>{{ $t('Currencies') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('backup')">
                <router-link to="/app/settings/Backup" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="database-backup" />
                  <span>{{ $t('Backup') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('system_health_view')">
                <router-link to="/app/settings/system_health" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="monitor-up" />
                  <span>{{ $t('System_Health') }}</span>
                </router-link>
              </li>

              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('webhooks_view')">
                <router-link to="/app/settings/webhooks" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="cloud" />
                  <span>{{ $t('Webhooks') }}</span>
                </router-link>
              </li>

              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('login_device_management')">
                <router-link to="/app/settings/login_devices" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="lock" />
                  <span>{{$t('Login_Device_Management')}}</span>
                </router-link>
              </li>


            </ul>
          </li>

          <!-- AI Reports -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('AI_Reports')"
            :class="{ active: isActiveRoute('ai_reports') }"
            class="nav-item"
          >
            <router-link to="/app/reports/ai_reports" class="nav-link">
              <lucide-icon class="nav-icon" name="lightbulb" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('AI_Reports') || 'AI Reports' }}</span>
            </router-link>
          </li>

          <!-- Reports -->
          <li
            v-show="hasReportsPermission"
            :class="{ active: isActiveRoute('reports'), 'has-submenu': true, open: openMenus.includes('reports') }"
            class="nav-item"
          >
            <a href="#" @click.prevent="toggleSubmenu('reports')" class="nav-link">
              <lucide-icon class="nav-icon" name="trending-up" />
              <span class="nav-text" v-if="!isCollapsed">{{ $t('Reports') }}</span>
              <lucide-icon class="submenu-arrow" name="chevron-down" v-if="!isCollapsed" />
            </a>
            <ul class="submenu" v-if="openMenus.includes('reports') && !isCollapsed">
              <!-- 3D Sales Dashboard -->
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('sales_3d_dashboard')">
                <router-link to="/app/reports/sales-3d-dashboard" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="atom" />
                  <span>{{ $t('sales_3d_dashboard') }}</span>
                </router-link>
              </li>

              <!-- Payments dropdown -->
              <li class="submenu-item has-nested" v-if="hasPaymentReportsPermission">
                <a href="#" @click.prevent="toggleNestedSubmenu('payments')" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="credit-card" />
                  <span>{{ $t('Payments') }}</span>
                  <lucide-icon class="nested-arrow" name="chevron-down" />
                </a>
                <ul class="nested-submenu" v-if="openNestedMenus.includes('payments')">
                  <li v-if="currentUserPermissions && currentUserPermissions.includes('Reports_payments_Purchases')">
                    <router-link to="/app/reports/payments_purchase" class="nested-link">{{ $t('Purchases') }}</router-link>
                  </li>
                  <li v-if="currentUserPermissions && currentUserPermissions.includes('Reports_payments_Sales')">
                    <router-link to="/app/reports/payments_sale" class="nested-link">{{ $t('Sales') }}</router-link>
                  </li>
                  <li v-if="currentUserPermissions && currentUserPermissions.includes('Reports_payments_Sale_Returns')">
                    <router-link to="/app/reports/payments_sales_returns" class="nested-link">{{ $t('SalesReturn') }}</router-link>
                  </li>
                  <li v-if="currentUserPermissions && currentUserPermissions.includes('Reports_payments_purchase_Return')">
                    <router-link to="/app/reports/payments_purchases_returns" class="nested-link">{{ $t('PurchasesReturn') }}</router-link>
                  </li>
                </ul>
              </li>

              <!-- Other reports (shortened for brevity) -->
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('report_transactions')">
                <router-link to="/app/reports/report_transactions" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="dollar-sign" />
                  <span>{{ $t('Report_Transactions') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('cash_flow_report')">
                <router-link to="/app/reports/cash_flow_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="trending-up" />
                  <span>{{ $t('Cash_Flow_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('seller_report')">
                <router-link to="/app/reports/seller_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="user" />
                  <span>{{ $t('Seller_report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('report_attendance_summary')">
                <router-link :to="{ name: 'attendance_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="clock" />
                  <span>{{ $t('attendance_summary') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Reports_profit')">
                <router-link to="/app/reports/profit_and_loss" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="wallet" />
                  <span>{{ $t('ProfitandLoss') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('return_ratio_report')">
                <router-link to="/app/reports/return_ratio_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="trending-up" />
                  <span>{{ $t('Return_Ratio_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('cash_register_report')">
                <router-link :to="{ name: 'cash_register_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="banknote" />
                  <span>{{ $t('Cash_Register_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('report_warranty')">
                <router-link :to="{ name: 'warranty_guarantee_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shield" />
                  <span>{{ $t('Warranty_Guarantee_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('inventory_valuation')">
                <router-link to="/app/reports/inventory_valuation_summary" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="pie-chart" />
                  <span>{{ $t('Inventory_Valuation') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('analytics_report')">
                <router-link to="/app/reports/analytics_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="bar-chart" />
                  <span>{{ $t('Analytics_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Stock_Inventory_Valuation')">
                <router-link to="/app/reports/stock_inventory_valuation" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="trending-up" />
                  <span>{{ $t('Stock_Inventory_Valuation') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('expenses_report')">
                <router-link to="/app/reports/expenses_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="receipt-text" />
                  <span>{{ $t('Expense_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('deposits_report')">
                <router-link to="/app/reports/deposits_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="receipt-text" />
                  <span>{{ $t('Deposits_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Warehouse_report')">
                <router-link to="/app/reports/warehouse_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="building" />
                  <span>{{ $t('Warehouse_report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('internal_location_report')">
                <router-link to="/app/reports/internal_location_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="map-pin" />
                  <span>{{ $t('Internal_Location_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('stock_report')">
                <router-link to="/app/reports/stock_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="trending-up" />
                  <span>{{ $t('stock_report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('negative_stock_report')">
                <router-link to="/app/reports/negative_stock_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="trending-up" />
                  <span>{{ $t('Negative_Stock_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Reports_quantity_alerts')">
                <router-link to="/app/reports/quantity_alerts" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="bell" />
                  <span>{{ $t('ProductQuantityAlerts') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Reports_purchase')">
                <router-link to="/app/reports/purchase_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="receipt" />
                  <span>{{ $t('PurchasesReport') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Reports_sales')">
                <router-link to="/app/reports/sales_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shopping-cart" />
                  <span>{{ $t('SalesReport') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('product_sales_report')">
                <router-link to="/app/reports/product_sales_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shopping-cart" />
                  <span>{{ $t('product_sales_report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('product_purchases_report')">
                <router-link to="/app/reports/product_purchases_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="receipt" />
                  <span>{{ $t('Product_purchases_report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Reports_suppliers')">
                <router-link to="/app/reports/providers_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="users" />
                  <span>{{ $t('SuppliersReport') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Top_Suppliers_Report')">
                <router-link :to="{ name: 'top_suppliers_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="star" />
                  <span>{{ $t('Top_Suppliers_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Reports_customers')">
                <router-link :to="{ name: 'customers_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="users" />
                  <span>{{ $t('CustomersReport') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Top_products')">
                <router-link to="/app/reports/top_selling_products" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="star" />
                  <span>{{ $t('Top_Selling_Products') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('inactive_customers_report')">
                <router-link to="/app/reports/inactive_customers" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="user-minus" />
                  <span>{{ $t('Inactive_Customers_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Top_customers')">
                <router-link to="/app/reports/Top_customers" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="star" />
                  <span>{{ $t('TopCustomers') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('users_report')">
                <router-link to="/app/reports/users_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shield-check" />
                  <span>{{ $t('Users_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('report_device_management')">
                <router-link to="/app/reports/login_activity_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="lock" />
                  <span>{{ $t('Login_Activity_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('product_report')">
                <router-link to="/app/reports/product_report" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="barcode" />
                  <span>{{ $t('product_report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('zeroSalesProducts')">
                <router-link :to="{ name: 'zero_sales_products_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shopping-bag" />
                  <span>{{ $t('Zero_Sales_Products_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Dead_Stock_Report')">
                <router-link :to="{ name: 'dead_stock_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="shopping-bag" />
                  <span>{{ $t('Dead_Stock_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('expiry_report')">
                <router-link :to="{ name: 'expiry_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="timer" />
                  <span>{{ $t('Expiry_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Batch_Register_Report')">
                <router-link :to="{ name: 'batch_register_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="package" />
                  <span>{{ $t('Batch_Register_Report') || 'Batch Register' }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Stock_Aging_Report')">
                <router-link :to="{ name: 'stock_aging_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="clock" />
                  <span>{{ $t('Stock_Aging_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Stock_Transfer_Report')">
                <router-link :to="{ name: 'stock_transfer_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="arrow-left" />
                  <span>{{ $t('Stock_Transfer_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('Stock_Adjustment_Report')">
                <router-link :to="{ name: 'stock_adjustment_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="pencil" />
                  <span>{{ $t('Stock_Adjustment_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('discount_summary_report')">
                <router-link :to="{ name: 'discount_summary_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="receipt" />
                  <span>{{ $t('Discount_Summary_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('customer_loyalty_points_report')">
                <router-link :to="{ name: 'customer_loyalty_points_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="heart" />
                  <span>{{ $t('Customer_Loyalty_Points_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('tax_summary_report')">
                <router-link :to="{ name: 'tax_summary_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="receipt-text" />
                  <span>{{ $t('Tax_Summary_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('draft_invoices_report')">
                <router-link :to="{ name: 'draft_invoices_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="receipt-text" />
                  <span>{{ $t('Draft_Invoices_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('report_sales_by_category')">
                <router-link :to="{ name: 'report_sales_by_category' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="folder" />
                  <span>{{ $t('Sales_by_Category') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('report_sales_by_brand')">
                <router-link :to="{ name: 'report_sales_by_brand' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="bookmark" />
                  <span>{{ $t('Sales_by_Brand') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('report_error_logs')">
                <router-link :to="{ name: 'report_error_logs' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="x" />
                  <span>{{ $t('Error_Logs') }}</span>
                </router-link>
              </li>

              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('service_jobs_report')">
                <router-link :to="{ name: 'service_jobs_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="wrench" />
                  <span>{{ $t('Service_Jobs_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('checklist_completion_report')">
                <router-link :to="{ name: 'checklist_completion_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="check" />
                  <span>{{ $t('Checklist_Completion_Report') }}</span>
                </router-link>
              </li>
              <li class="submenu-item" v-if="currentUserPermissions && currentUserPermissions.includes('customer_maintenance_history_report')">
                <router-link :to="{ name: 'customer_maintenance_history_report' }" class="submenu-link">
                  <lucide-icon class="submenu-icon" name="calendar-days" />
                  <span>{{ $t('Customer_Maintenance_History_Report') }}</span>
                </router-link>
              </li>
            </ul>
          </li>
        </ul>
      </nav>
    </vue-perfect-scrollbar>

    <!-- Mobile/Tablet Overlay -->
    <div 
      v-if="mobileOpen && isMobileOrTablet" 
      class="mobile-overlay" 
      @click="closeMobileSidebar"
    ></div>
  </div>
</template>

<script>
import { isMobile, isTablet } from "mobile-device-detect";
import { mapGetters, mapActions } from "vuex";

export default {
  name: "VerticalSidebar",
  
  data() {
    return {
      isMobile,
      isTablet,
      windowWidth: typeof window !== 'undefined' ? window.innerWidth : 1200,
      isCollapsed: false,
      openMenus: [],
      openNestedMenus: [],
      mobileOpen: false,
    };
  },

  mounted() {
    this.initializeActiveMenu();
    this.loadCollapsedState();

    // Ensure nav text is visible on mobile/tablet
    if (window.innerWidth <= 1024 || this.isMobile) {
      this.isCollapsed = false;
    }

    // Keep collapse state synced with screen size
    window.addEventListener('resize', this.handleResize);

    // Listen for toggle event from top nav
    Fire.$on("toggleVerticalSidebar", () => {
      if (window.innerWidth <= 1024) {
        this.mobileOpen = !this.mobileOpen;
        this.isCollapsed = false;
      } else {
        this.toggleCollapse();
      }
    });

    this.$nextTick(() => {
      const el = this.$refs.ps && (this.$refs.ps.$el || this.$refs.ps);
      if (el) {
        this._onSidebarWheel = (e) => {
          const dy = e.deltaY;
          if (!dy) return;
          if (el.scrollHeight <= el.clientHeight) return;
          const atTop = el.scrollTop <= 0;
          const atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight;
          if ((dy < 0 && atTop) || (dy > 0 && atBottom)) {
            e.preventDefault();
          }
        };
        el.addEventListener('wheel', this._onSidebarWheel, { passive: false });
      }
    });
  },

  beforeDestroy() {
    // Clean up event listener
    Fire.$off("toggleVerticalSidebar");
    window.removeEventListener('resize', this.handleResize);
    const el = this.$refs.ps && (this.$refs.ps.$el || this.$refs.ps);
    if (el && this._onSidebarWheel) {
      el.removeEventListener('wheel', this._onSidebarWheel);
    }
  },

  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),

    isMobileOrTablet() {
      return this.windowWidth <= 1024 || this.isMobile || this.isTablet;
    },

    hasReportsPermission() {
      if (!this.currentUserPermissions) return false;
      const reportPermissions = [
        'Reports_payments_Sales', 'Reports_payments_Purchases', 'Reports_payments_Sale_Returns',
        'Reports_payments_purchase_Return', 'Warehouse_report', 'Reports_profit', 'inventory_valuation',
        'expenses_report', 'deposits_report', 'Reports_purchase', 'Reports_quantity_alerts',
        'Reports_sales', 'product_sales_report', 'product_purchases_report', 'Reports_suppliers',
        'Top_Suppliers_Report', 'Reports_customers', 'Top_products', 'inactive_customers_report',
        'Top_customers', 'users_report', 'product_report', 'zeroSalesProducts', 'Dead_Stock_Report',
        'Stock_Aging_Report', 'Stock_Transfer_Report', 'discount_summary_report', 'Stock_Adjustment_Report',
        'tax_summary_report', 'draft_invoices_report', 'report_transactions', 'seller_report',
        'report_sales_by_category', 'report_sales_by_brand', 'report_error_logs', 'cash_register_report', 'report_warranty',
        'stock_report','negative_stock_report','customer_loyalty_points_report','cash_flow_report',
        'report_attendance_summary','return_ratio_report','service_jobs',
        'service_jobs_report','checklist_completion_report','customer_maintenance_history_report','report_device_management',
        'analytics_report', 'Stock_Inventory_Valuation', 'internal_location_report',
        'sales_3d_dashboard', 'expiry_report'
      ];
      return reportPermissions.some(perm => this.currentUserPermissions.includes(perm));
    },

    hasPaymentReportsPermission() {
      if (!this.currentUserPermissions) return false;
      return ['Reports_payments_Purchases', 'Reports_payments_Sales', 'Reports_payments_Sale_Returns', 'Reports_payments_purchase_Return']
        .some(perm => this.currentUserPermissions.includes(perm));
    }
  },

  methods: {
    ...mapActions(["logout", "setSidebarLayout", "setVerticalSidebarCollapsed"]),

    navigateToDashboard() {
      this.$router.push("/app/dashboard");
    },

    handleResize() {
      this.windowWidth = window.innerWidth;
      if (this.isMobileOrTablet) {
        if (this.isCollapsed) this.isCollapsed = false;
      } else {
        this.isCollapsed = this.$store.getters.getVerticalSidebarCollapsed;
        if (this.mobileOpen) this.mobileOpen = false;
      }
    },

    toggleCollapse() {
      this.isCollapsed = !this.isCollapsed;
      this.setVerticalSidebarCollapsed(this.isCollapsed);
      if (this.isCollapsed) {
        this.openMenus = [];
        this.openNestedMenus = [];
      } else {
        this.initializeActiveMenu();
      }
    },

    loadCollapsedState() {
      // Load from Vuex store
      this.isCollapsed = this.$store.getters.getVerticalSidebarCollapsed;
    },

    toggleSubmenu(menu) {
      if (this.isCollapsed) return;

      if (this.openMenus.includes(menu)) {
        this.openMenus = [];
        this.openNestedMenus = [];
      } else {
        this.openMenus = [menu];
        this.openNestedMenus = [];
      }
    },

    toggleNestedSubmenu(menu) {
      const index = this.openNestedMenus.indexOf(menu);
      if (index > -1) {
        this.openNestedMenus.splice(index, 1);
      } else {
        this.openNestedMenus.push(menu);
      }
    },

    initializeActiveMenu() {
      const path = this.$route.path;
      const segments = path.split('/').filter(x => x !== '');
      
      if (segments.length >= 2) {
        const parentMenu = segments[1].toLowerCase();
        if (!this.openMenus.includes(parentMenu)) {
          this.openMenus.push(parentMenu);
        }
      }
    },

    isActiveRoute(menu) {
      if (this.$route.name === menu) return true;
      const path = this.$route.path.toLowerCase();
      if (menu === 'User_Management') {
        return path.includes('/app/user_management/users') || path.includes('/app/user_management/permissions');
      }
      if (menu === 'real_time_sales_counter') {
        return path.includes('real-time-sales-counter');
      }
      if (menu === 'knowledge_base') {
        return path.includes('/app/knowledge-base');
      }
      return path.includes(`/app/${menu.toLowerCase()}`);
    },

    async logoutUser() {
      try {
        await this.logout();
        this.$router.push({ name: "login" });
      } catch (error) {
        console.error("Logout error:", error);
      }
    },

    closeMobileSidebar() {
      if (this.mobileOpen) {
        this.mobileOpen = false;
      }
    }
  },

  watch: {
    '$route'() {
      this.initializeActiveMenu();
      if (this.isMobileOrTablet && this.mobileOpen) {
        this.mobileOpen = false;
      }
    }
  }
};
</script>

<style scoped>
/* Vertical Sidebar Container */
.vertical-sidebar-wrapper {
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  width: 260px;
  background: #fff;
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  transition: width 0.3s ease, transform 0.3s ease;
  transform: translateX(0);
}

.vertical-sidebar-wrapper.collapsed {
  transform: translateX(-100%);
  width: 260px;
}

.vertical-sidebar {
  height: 100dvh;
  max-height: 100dvh;
  overflow-y: auto;
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch;

  /* key settings */
  overscroll-behavior-y: contain; /* prevent accidental body scroll when hitting limits */
  touch-action: pan-y;            /* allow smooth vertical scroll */
  position: relative;             /* stay in layout, don’t force isolation */
  z-index: 2;
}

.vertical-nav-menu::after{
  content:"";
  display:block;
  height: calc(24px + env(safe-area-inset-bottom, 0px));
}

.vertical-sidebar.ps {
  -webkit-overflow-scrolling: touch; /* smooth on iOS */
  overscroll-behavior: contain;      /* ✅ prevent page scroll */
  touch-action: pan-y;               /* ✅ allow only vertical scroll gestures */
}

/* Header with Logo */
.vertical-sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 15px;
}

.header-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  flex: 1;
  overflow: hidden;
}

.sidebar-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 45px;
  height: 45px;
  border-radius: 12px;
  flex-shrink: 0;
  transition: all 0.3s ease;
}

.vertical-sidebar-wrapper.collapsed .sidebar-logo {
  width: 40px;
  height: 40px;
  border-radius: 10px;
}

.vertical-sidebar-wrapper.collapsed .logo-placeholder {
  font-size: 20px;
}

.logo-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.logo-placeholder {
  color: white;
  font-size: 22px;
  font-weight: 700;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
}

.company-name {
  font-size: 16px;
  font-weight: 700;
  color: #333;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.2;
}

/* Navigation Menu */
.vertical-nav-menu {
  padding: 10px 0;
}

.nav-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.nav-item {
  margin: 4px 10px;
}

.nav-link {
  display: flex;
  align-items: center;
  padding: 12px 15px;
  color: #47404f;
  text-decoration: none;
  border-radius: 8px;
  transition: all 0.3s;
  position: relative;
}

.nav-link:hover {
  background: #f7f7f7;
  color: #663399;
}

.nav-icon {
  font-size: 20px;
  min-width: 24px;
  margin-right: 12px;
}

.collapsed .nav-icon {
  margin-right: 0;
}

.nav-text {
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
}

.submenu-arrow {
  margin-left: auto;
  font-size: 12px;
  transition: transform 0.3s;
}

.nav-item.open > .nav-link .submenu-arrow {
  transform: rotate(180deg);
}

/* Submenu */
.submenu {
  list-style: none;
  padding: 8px 0;
  margin: 0;
  background: linear-gradient(180deg, rgba(102, 51, 153, 0.06) 0%, rgba(102, 51, 153, 0.02) 100%);
  border-radius: 10px;
  border: 1px solid rgba(102, 51, 153, 0.08);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6), 0 2px 6px rgba(102, 51, 153, 0.06);
  position: relative;
}

.submenu-item {
  margin: 0;
  padding: 0 8px;
}

.submenu-link {
  display: flex;
  align-items: center;
  padding: 10px 12px;
  color: #666;
  text-decoration: none;
  border-radius: 6px;
  font-size: 13px;
  transition: all 0.3s;
  position: relative;
}

.submenu-link::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 0;
  background: #663399;
  border-radius: 0 2px 2px 0;
  transition: height 0.3s;
}

.submenu-link:hover {
  background: rgba(102, 51, 153, 0.08);
  color: #663399;
  padding-left: 16px;
}

.submenu-link:hover::before {
  height: 70%;
}

.submenu-link.router-link-active {
  color: #663399;
  font-weight: 600;
  background: rgba(102, 51, 153, 0.1);
  padding-left: 16px;
}

.submenu-link.router-link-active::before {
  height: 70%;
}

.submenu-icon {
  font-size: 15px;
  min-width: 18px;
  margin-right: 10px;
  opacity: 0.8;
}

/* Nested Submenu */
.nested-submenu {
  list-style: none;
  padding: 8px 0 8px 20px;
  margin: 4px 0;
  border-left: 2px solid rgba(102, 51, 153, 0.15);
}

.nested-link {
  display: block;
  padding: 8px 12px;
  color: #666;
  text-decoration: none;
  border-radius: 4px;
  font-size: 12px;
  transition: all 0.3s;
  position: relative;
}

.nested-link:hover {
  color: #663399;
  background: rgba(102, 51, 153, 0.05);
  padding-left: 16px;
}

.nested-link.router-link-active {
  color: #663399;
  background: rgba(102, 51, 153, 0.1);
  font-weight: 600;
  padding-left: 16px;
}

.nested-arrow {
  margin-left: auto;
  font-size: 10px;
  transition: transform 0.3s;
}

.has-nested.open > .submenu-link .nested-arrow {
  transform: rotate(180deg);
}

/* Mobile & Tablet Adjustments */
@media (max-width: 1024px) {
  .vertical-sidebar-wrapper {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 260px;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    z-index: 90;
  }

  .vertical-sidebar-wrapper.mobile-open {
    transform: translateX(0);
  }

  .vertical-sidebar-wrapper.collapsed {
    width: 260px;
  }

  .sidebar-logo {
    width: 40px;
    height: 40px;
  }
}

/* Fallback for mobile-device-detect library */
.is-mobile.vertical-sidebar-wrapper {
  transform: translateX(-100%);
  transition: transform 0.3s ease;
  z-index: 90;
}

.is-mobile.vertical-sidebar-wrapper.mobile-open {
  transform: translateX(0);
}

/* Dark Mode Support */
body.dark-theme .vertical-sidebar-wrapper {
  background: #1a1a2e;
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.5);
}

body.dark-theme .company-name {
  color: #e0e0e0;
}

body.dark-theme .nav-link {
  color: #e0e0e0;
}

body.dark-theme .nav-link:hover {
  background: #2d2d44;
  color: #fff;
}

body.dark-theme .submenu {
  background: linear-gradient(180deg, rgba(118, 75, 162, 0.18) 0%, rgba(118, 75, 162, 0.06) 100%);
  border-color: rgba(118, 75, 162, 0.25);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04), 0 2px 6px rgba(0, 0, 0, 0.3);
}

body.dark-theme .submenu-link {
  color: #b0b0b0;
}

body.dark-theme .submenu-link::before {
  background: #764ba2;
}

body.dark-theme .submenu-link:hover {
  background: rgba(118, 75, 162, 0.15);
  color: #fff;
}

body.dark-theme .submenu-link.router-link-active {
  background: rgba(118, 75, 162, 0.25);
  color: #fff;
}

body.dark-theme .nested-submenu {
  border-left-color: rgba(118, 75, 162, 0.3);
}

body.dark-theme .nested-link {
  color: #b0b0b0;
}

body.dark-theme .nested-link:hover {
  background: rgba(118, 75, 162, 0.1);
  color: #fff;
}

body.dark-theme .nested-link.router-link-active {
  background: rgba(118, 75, 162, 0.2);
  color: #fff;
}

/* RTL Support */
html[dir="rtl"] .vertical-sidebar-wrapper {
  left: auto;
  right: 0;
  box-shadow: -2px 0 8px rgba(0, 0, 0, 0.1);
}

html[dir="rtl"] .nav-icon {
  margin-right: 0;
  margin-left: 12px;
}

html[dir="rtl"] .submenu-icon {
  margin-right: 0;
  margin-left: 10px;
}

html[dir="rtl"] .submenu-arrow {
  margin-left: 0;
  margin-right: auto;
}

/* Mobile Overlay */
.mobile-overlay {
  position: fixed;
  top: 0;
  left: 260px;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 80;
  animation: fadeIn 0.3s;
  pointer-events: auto;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
</style>


