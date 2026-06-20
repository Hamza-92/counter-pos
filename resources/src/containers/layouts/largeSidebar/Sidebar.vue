<template>
  <div
    class="side-content-wrap"
    @mouseenter="isMenuOver = true"
    @mouseleave="isMenuOver = false"
    @touchstart="isMenuOver = true"
  >
    <vue-perfect-scrollbar
      :settings="{ suppressScrollX: true, wheelPropagation: false }"
      :class="{ open: getSideBarToggleProperties.isSideNavOpen }"
      ref="myData"
      class="sidebar-left rtl-ps-none ps scroll"
    >
      <div>
        <ul class="navigation-left">
          <li
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'dashboard' }"
            class="nav-item"
            data-item="dashboard"
          >
            <router-link tag="a" class="nav-item-hold" to="/app/dashboard">
              <lucide-icon class="nav-icon" name="bar-chart" />
              <span class="nav-text">{{ $t("dashboard") }}</span>
            </router-link>
          </li>

          <!-- Store (parent) -->
         <li
            v-show="currentUserPermissions && (
                      currentUserPermissions.includes('Store_settings_view') ||
                      currentUserPermissions.includes('Orders_view') ||
                      currentUserPermissions.includes('Collections_view') ||
                      currentUserPermissions.includes('Banners_view') ||
                      currentUserPermissions.includes('Subscribers_view') ||
                      currentUserPermissions.includes('Messages_view')
                    )"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'Store' }"
            class="nav-item"
            data-item="Store"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="shopping-bag" />
              <span class="nav-text">{{$t('Store')}}</span>
            </a>
            <div class="triangle"></div>
          </li>

          <li
            v-show="currentUserPermissions && (currentUserPermissions.includes('Customers_view')
                        ||currentUserPermissions.includes('Suppliers_view')
                        || currentUserPermissions.includes('customers_import')
                        || currentUserPermissions.includes('Suppliers_import')
                        || currentUserPermissions.includes('Suppliers_import'))"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'People' }"
            class="nav-item"
            data-item="People"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="users" />
              <span class="nav-text">{{$t('People')}}</span>
            </a>
            <div class="triangle"></div>
          </li>

          <li
            v-show="currentUserPermissions && (currentUserPermissions.includes('users_view')
                        || currentUserPermissions.includes('permissions_view'))"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'User_Management' }"
            class="nav-item"
            data-item="User_Management"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="shield-check" />
              <span class="nav-text">{{$t('User_Management')}}</span>
            </a>
            <div class="triangle"></div>
          </li>

        
          <li
            v-show="currentUserPermissions 
            && (currentUserPermissions.includes('products_add')
            || currentUserPermissions.includes('products_view') 
            || currentUserPermissions.includes('product_import') 
            || currentUserPermissions.includes('opening_stock_import') 
            || currentUserPermissions.includes('barcode_view')
             || currentUserPermissions.includes('brand') 
             || currentUserPermissions.includes('unit')  
             || currentUserPermissions.includes('count_stock')  
             || currentUserPermissions.includes('category')
             || currentUserPermissions.includes('subcategory'))"
            @mouseenter="toggleSubMenu"
            class="nav-item"
            :class="{ active: selectedParentMenu == 'products' }"
            data-item="products"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="library-big" />
              <span class="nav-text">{{$t('Products')}}</span>
            </a>
            <div class="triangle"></div>
          </li>
          <li
            v-show="currentUserPermissions 
              && (currentUserPermissions.includes('adjustment_view')
              || currentUserPermissions.includes('adjustment_add'))"
            @mouseenter="toggleSubMenu"
            class="nav-item"
            :class="{ active: selectedParentMenu == 'adjustments' }"
            data-item="adjustments"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="map-pin" />
              <span class="nav-text">{{$t('StockAdjustement')}}</span>
            </a>
            <div class="triangle"></div>
          </li>

          <li
            v-show="currentUserPermissions && (currentUserPermissions.includes('Purchases_view') 
                        || currentUserPermissions.includes('Purchases_add'))"
            @mouseenter="toggleSubMenu"
            class="nav-item"
            :class="{ active: selectedParentMenu == 'purchases' }"
            data-item="purchases"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="receipt" />
              <span class="nav-text">{{$t('Purchases')}}</span>
            </a>
            <div class="triangle"></div>
          </li>
          <li
            v-show="currentUserPermissions && (currentUserPermissions.includes('Sales_view') 
                        || currentUserPermissions.includes('Sales_add')
                        || currentUserPermissions.includes('Pos_view')
                        || currentUserPermissions.includes('customer_display_screen_setup')
                        || currentUserPermissions.includes('shipment')
                        || currentUserPermissions.includes('real_time_sales_counter'))"
            class="nav-item"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'sales' }"
            data-item="sales"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="shopping-cart" />
              <span class="nav-text">{{$t('Sales')}}</span>
            </a>
            <div class="triangle"></div>
          </li>

            <li
            v-if="currentUserPermissions && currentUserPermissions.includes('Sale_Returns_view')"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'sale_return' }"
            class="nav-item"
            data-item="sale_return"
          >

           <router-link tag="a" class="nav-item-hold" to="/app/sale_return/list">
              <lucide-icon class="nav-icon" name="chevron-right" />
              <span class="nav-text">{{ $t("SalesReturn") }}</span>
            </router-link>
          </li>

          <li
            v-show="currentUserPermissions && (currentUserPermissions.includes('Quotations_view')
                      || currentUserPermissions.includes('Quotations_add'))"
            @mouseenter="toggleSubMenu"
            class="nav-item"
            :class="{ active: selectedParentMenu == 'quotations' }"
            data-item="quotations"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="shopping-basket" />
              <span class="nav-text">{{$t('Quotations')}}</span>
            </a>
            <div class="triangle"></div>
          </li>

          <li
            v-if="currentUserPermissions && currentUserPermissions.includes('Purchase_Returns_view')"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'purchase_return' }"
            class="nav-item"
            data-item="purchase_return"
          >
          <router-link tag="a" class="nav-item-hold" to="/app/purchase_return/list">
              <lucide-icon class="nav-icon" name="chevron-left" />
              <span class="nav-text">{{ $t("PurchasesReturn") }}</span>
            </router-link>
          </li>

           <li
            v-show="currentUserPermissions && (currentUserPermissions.includes('transfer_view')
                     || currentUserPermissions.includes('transfer_add'))"
            @mouseenter="toggleSubMenu"
            class="nav-item"
            :class="{ active: selectedParentMenu == 'transfers' }"
            data-item="transfers"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="arrow-left" />
              <span class="nav-text">{{$t('StockTransfers')}}</span>
            </a>
            <div class="triangle"></div>
          </li>

          <li
            v-show="currentUserPermissions 
              && (currentUserPermissions.includes('damage_view'))"
            @mouseenter="toggleSubMenu"
            class="nav-item"
            :class="{ active: selectedParentMenu == 'damages' }"
            data-item="damages"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="shopping-bag" />
              <span class="nav-text">{{ $t('Damages') }}</span>
            </a>
            <div class="triangle"></div>
          </li>

            <li
            v-show="currentUserPermissions && (currentUserPermissions.includes('company')
                     || currentUserPermissions.includes('department')
                     || currentUserPermissions.includes('designation')
                     || currentUserPermissions.includes('office_shift')
                     || currentUserPermissions.includes('view_employee')
                     || currentUserPermissions.includes('attendance')
                     || currentUserPermissions.includes('leave')
                     || currentUserPermissions.includes('holiday')
                     || currentUserPermissions.includes('payroll')
                     )"
                     
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'hrm' }"
            class="nav-item"
            data-item="hrm"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="library" />
              <span class="nav-text">{{$t('hrm')}}</span>
            </a>
            <div class="triangle"></div>
          </li>
          <li
            v-show="currentUserPermissions && (currentUserPermissions.includes('expense_view')
              || currentUserPermissions.includes('expense_add')
              || currentUserPermissions.includes('deposit_view')
              || currentUserPermissions.includes('deposit_add')
              || currentUserPermissions.includes('account')
              || currentUserPermissions.includes('transfer_money')
              || currentUserPermissions.includes('accounting_dashboard')
              || currentUserPermissions.includes('chart_of_accounts')
              || currentUserPermissions.includes('journal_entries')
              || currentUserPermissions.includes('trial_balance')
              || currentUserPermissions.includes('accounting_profit_loss')
              || currentUserPermissions.includes('balance_sheet')
              || currentUserPermissions.includes('accounting_tax_report')
              )"
            @mouseenter="toggleSubMenu"
            class="nav-item"
            :class="{ active: selectedParentMenu == 'accounting' }"
            data-item="accounting"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="wallet" />
              <span class="nav-text">{{$t('Accounting')}}</span>
            </a>
            <div class="triangle"></div>
          </li>

          

            <li
            v-if="currentUserPermissions && currentUserPermissions.includes('subscription_product')"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'subscription_product' }"
            class="nav-item"
            data-item="subscription_product"
          >

           <router-link tag="a" class="nav-item-hold" to="/app/subscription_product/list">
              <lucide-icon class="nav-icon" name="dollar-sign" />
              <span class="nav-text">{{$t('Subscription_Product')}}</span>
            </router-link>
          </li>


          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('service_jobs')"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'service' }"
            class="nav-item"
            data-item="service"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="wrench" />
              <span class="nav-text">{{$t('Service_Maintenance')}}</span>
            </a>
            <div class="triangle"></div>
          </li>

          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('assets')"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'assets' }"
            class="nav-item"
            data-item="assets"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="settings" />
              <span class="nav-text">{{$t('Assets')}}</span>
            </a>
            <div class="triangle"></div>
          </li>

          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('projects')"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'projects' }"
            class="nav-item"
            data-item="projects"
          >
            <router-link tag="a" class="nav-item-hold" to="/app/projects">
              <lucide-icon class="nav-icon" name="archive" />
              <span class="nav-text">{{$t('Projects')}}</span>
            </router-link>
          </li>

          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('contracts')"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'contracts' }"
            class="nav-item"
            data-item="contracts"
          >
            <router-link tag="a" class="nav-item-hold" to="/app/contracts">
              <lucide-icon class="nav-icon" name="clipboard-list" />
              <span class="nav-text">{{$t('Contracts') || 'Contracts'}}</span>
            </router-link>
          </li>

          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('tasks')"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'tasks' }"
            class="nav-item"
            data-item="tasks"
          >
            <router-link tag="a" class="nav-item-hold" to="/app/tasks">
              <lucide-icon class="nav-icon" name="check" />
              <span class="nav-text">{{$t('Tasks')}}</span>
            </router-link>
          </li>

          <!-- Bookings (with submenu: Booking List, Google Calendar) -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('bookings')"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'bookings' }"
            class="nav-item"
            data-item="bookings"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="calendar-days" />
              <span class="nav-text">{{ $t('Bookings') }}</span>
            </a>
            <div class="triangle"></div>
          </li>

          <!-- Commissions -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('commissions_view')"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'commissions' }"
            class="nav-item"
            data-item="commissions"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="wallet" />
              <span class="nav-text">{{ $t('Commissions') }}</span>
            </a>
            <div class="triangle"></div>
          </li>

          <!-- woocommerce_settings -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('woocommerce_settings')"
            :class="{ active: selectedParentMenu == 'woocommerce_settings' }"
            class="nav-item"
            data-item="woocommerce_settings"
          >
            <router-link tag="a" class="nav-item-hold" to="/app/woocommerce">
              <lucide-icon class="nav-icon" name="shopping-cart" />
              <span class="nav-text">{{$t('WooCommerce_Settings')}}</span>
            </router-link>
          </li>

          <!-- Knowledge Base -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('knowledge_base_view')"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'knowledge-base' }"
            class="nav-item"
            data-item="knowledge-base"
          >
            <router-link tag="a" class="nav-item-hold" to="/app/knowledge-base">
              <lucide-icon class="nav-icon" name="book" />
              <span class="nav-text">{{ $t('Knowledge_Base') || 'Knowledge Base' }}</span>
            </router-link>
          </li>

          <li
            v-show="currentUserPermissions && (currentUserPermissions.includes('setting_system')
                        || currentUserPermissions.includes('update_settings')
                        || currentUserPermissions.includes('sms_settings')
                        || currentUserPermissions.includes('quickbooks_settings')
                        || currentUserPermissions.includes('notification_template')
                        || currentUserPermissions.includes('pos_settings')
                        || currentUserPermissions.includes('appearance_settings')
                        || currentUserPermissions.includes('translations_settings')
                        || currentUserPermissions.includes('module_settings')
                        || currentUserPermissions.includes('payment_gateway')
                        || currentUserPermissions.includes('mail_settings')
                        || currentUserPermissions.includes('warehouse')
                        || currentUserPermissions.includes('warehouse_locations')
                        || currentUserPermissions.includes('backup')
                        || currentUserPermissions.includes('payment_methods')
                        || currentUserPermissions.includes('currency')
                        || currentUserPermissions.includes('login_device_management')
                        || currentUserPermissions.includes('system_health_view')
                        || currentUserPermissions.includes('webhooks_view'))"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'settings' }"
            class="nav-item"
            data-item="settings"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="database-zap" />
              <span class="nav-text">{{$t('Settings')}}</span>
            </a>
            <div class="triangle"></div>
          </li>

          <!-- AI Reports -->
          <li
            v-show="currentUserPermissions && currentUserPermissions.includes('AI_Reports')"
            :class="{ active: selectedParentMenu == 'ai_reports' }"
            class="nav-item"
            data-item="ai_reports"
          >
            <router-link tag="a" class="nav-item-hold" to="/app/reports/ai_reports">
              <lucide-icon class="nav-icon" name="lightbulb" />
              <span class="nav-text">{{$t('AI_Reports') || 'AI Reports'}}</span>
            </router-link>
          </li>

          <li
            v-show="currentUserPermissions && 
                     (currentUserPermissions.includes('Reports_payments_Sales') 
                     || currentUserPermissions.includes('Reports_payments_Purchases')
                     || currentUserPermissions.includes('Reports_payments_Sale_Returns')
                     || currentUserPermissions.includes('Reports_payments_purchase_Return')
                     || currentUserPermissions.includes('Warehouse_report')
                     || currentUserPermissions.includes('Reports_profit')
                     || currentUserPermissions.includes('analytics_report')
                     || currentUserPermissions.includes('Stock_Inventory_Valuation')
                     || currentUserPermissions.includes('inventory_valuation')
                     || currentUserPermissions.includes('expenses_report')
                     || currentUserPermissions.includes('deposits_report')
                     || currentUserPermissions.includes('Reports_purchase') 
                     || currentUserPermissions.includes('Reports_quantity_alerts')
                     || currentUserPermissions.includes('Reports_sales') 
                     || currentUserPermissions.includes('product_sales_report')
                     || currentUserPermissions.includes('product_purchases_report')
                     || currentUserPermissions.includes('Reports_suppliers')
                     || currentUserPermissions.includes('Top_Suppliers_Report')
                     || currentUserPermissions.includes('Reports_customers')
                     || currentUserPermissions.includes('Top_products')
                     || currentUserPermissions.includes('inactive_customers_report')
                     || currentUserPermissions.includes('Top_customers')
                     || currentUserPermissions.includes('report_device_management')
                     || currentUserPermissions.includes('users_report')
                     || currentUserPermissions.includes('product_report')
                      || currentUserPermissions.includes('zeroSalesProducts')
                      || currentUserPermissions.includes('Dead_Stock_Report')
                      || currentUserPermissions.includes('expiry_report')
                       || currentUserPermissions.includes('Stock_Aging_Report')
                       || currentUserPermissions.includes('Stock_Transfer_Report')
                       || currentUserPermissions.includes('discount_summary_report')
                       || currentUserPermissions.includes('Stock_Adjustment_Report')
                      || currentUserPermissions.includes('customer_loyalty_points_report')
                      || currentUserPermissions.includes('tax_summary_report')
                      || currentUserPermissions.includes('draft_invoices_report')
                      || currentUserPermissions.includes('report_transactions')
                      || currentUserPermissions.includes('cash_flow_report')
                      || currentUserPermissions.includes('report_attendance_summary')
                       || currentUserPermissions.includes('seller_report')
                      || currentUserPermissions.includes('report_sales_by_category')
                       || currentUserPermissions.includes('report_sales_by_brand')
                      || currentUserPermissions.includes('report_error_logs')
                      || currentUserPermissions.includes('cash_register_report')
                      || currentUserPermissions.includes('report_warranty')
                     || currentUserPermissions.includes('stock_report')
                     || currentUserPermissions.includes('internal_location_report')
                     || currentUserPermissions.includes('negative_stock_report')
                     || currentUserPermissions.includes('return_ratio_report')
                     || currentUserPermissions.includes('service_jobs')
                     || currentUserPermissions.includes('service_jobs_report')
                     || currentUserPermissions.includes('checklist_completion_report')
                     || currentUserPermissions.includes('customer_maintenance_history_report')
                     || currentUserPermissions.includes('sales_3d_dashboard'))"
            @mouseenter="toggleSubMenu"
            :class="{ active: selectedParentMenu == 'reports' }"
            class="nav-item"
            data-item="reports"
            :data-submenu="true"
          >
            <a class="nav-item-hold" href="#">
              <lucide-icon class="nav-icon" name="trending-up" />
              <span class="nav-text">{{$t('Reports')}}</span>
            </a>
            <div class="triangle"></div>
          </li>    
                
        </ul>
      </div>
    </vue-perfect-scrollbar>

    <vue-perfect-scrollbar
      :class="{ open: getSideBarToggleProperties.isSecondarySideNavOpen }"
      :settings="{ suppressScrollX: true, wheelPropagation: false }"
      class="sidebar-left-secondary ps rtl-ps-none"
    >
      <div ref="sidebarChild">


        <!-- Store (children) -->
       <ul
        class="childNav d-none"
        data-parent="Store"
        :class="{ 'd-block': selectedParentMenu == 'Store' }"
      >
        <!-- Visit Online Store (external link) -->
        <li class="nav-item">
          <a class="nav-item-hold" href="/online_store" target="_blank">
            <lucide-icon class="nav-icon" name="store" />
            <span class="item-name">{{ $t('Visit_Online_Store') }}</span>
          </a>
        </li>

        <!-- Settings -->
        <li
          class="nav-item"
          v-if="currentUserPermissions && currentUserPermissions.includes('Store_settings_view')"
        >
          <router-link tag="a" class="nav-item-hold" to="/app/Store/Settings">
            <lucide-icon class="nav-icon" name="settings" />
            <span class="item-name">{{ $t('Settings') }}</span>
          </router-link>
        </li>

        <!-- Orders -->
        <li
          class="nav-item"
          v-if="currentUserPermissions && currentUserPermissions.includes('Orders_view')"
        >
          <router-link tag="a" class="nav-item-hold" to="/app/Store/Orders">
            <lucide-icon class="nav-icon" name="receipt" />
            <span class="item-name">{{ $t('Online_Orders') }}</span>
          </router-link>
        </li>

        <!-- Collections -->
        <li
          class="nav-item"
          v-if="currentUserPermissions && currentUserPermissions.includes('Collections_view')"
        >
          <router-link tag="a" class="nav-item-hold" to="/app/Store/Collections">
            <lucide-icon class="nav-icon" name="check" />
            <span class="item-name">{{ $t('Collections') }}</span>
          </router-link>
        </li>

        <!-- Banners -->
        <li
          class="nav-item"
          v-if="currentUserPermissions && currentUserPermissions.includes('Banners_view')"
        >
          <router-link tag="a" class="nav-item-hold" to="/app/Store/Banners">
            <lucide-icon class="nav-icon" name="wallet" />
            <span class="item-name">{{ $t('Banners') }}</span>
          </router-link>
        </li>

        <!-- Subscribers -->
        <li
          class="nav-item"
          v-if="currentUserPermissions && currentUserPermissions.includes('Subscribers_view')"
        >
          <router-link tag="a" class="nav-item-hold" to="/app/Store/Subscribers">
            <lucide-icon class="nav-icon" name="users" />
            <span class="item-name">{{ $t('Subscribers') }}</span>
          </router-link>
        </li>

        <!-- Messages -->
        <li
          class="nav-item"
          v-if="currentUserPermissions && currentUserPermissions.includes('Messages_view')"
        >
          <router-link tag="a" class="nav-item-hold" to="/app/Store/Messages">
            <lucide-icon class="nav-icon" name="message-square" />
            <span class="item-name">{{ $t('Messages') }}</span>
          </router-link>
        </li>

        <!-- Invite Codes -->
        <li
          class="nav-item"
          v-if="currentUserPermissions && currentUserPermissions.includes('Store_settings_view')"
        >
          <router-link tag="a" class="nav-item-hold" to="/app/Store/InviteCodes">
            <lucide-icon class="nav-icon" name="ticket" />
            <span class="item-name">{{ $t('Invite_Codes') }}</span>
          </router-link>
        </li>

        <!-- Pending Customers -->
        <li
          class="nav-item"
          v-if="currentUserPermissions && currentUserPermissions.includes('Store_settings_view')"
        >
          <router-link tag="a" class="nav-item-hold" to="/app/Store/PendingCustomers">
            <lucide-icon class="nav-icon" name="clock" />
            <span class="item-name">{{ $t('Pending_Customers') }}</span>
          </router-link>
        </li>
      </ul>


        <ul
          class="childNav d-none"
          data-parent="products"
          :class="{ 'd-block': selectedParentMenu == 'products' }"
        >
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('products_add')"
          >
            <router-link tag="a" class to="/app/products/store">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('AddProduct')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('products_view')"
          >
            <router-link tag="a" class to="/app/products/list">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('productsList')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('product_import')"
          >
            <router-link tag="a" class to="/app/products/import">
              <lucide-icon class="nav-icon" name="download" />
              <span class="item-name">{{ $t('import_products') }}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('product_import')"
          >
            <router-link tag="a" class to="/app/products/import-update">
              <lucide-icon class="nav-icon" name="pencil" />
              <span class="item-name">Import (Update Only)</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('opening_stock_import')"
          >
            <router-link tag="a" class to="/app/products/opening_stock_import">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('Opening_Stock')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('barcode_view')"
          >
            <router-link tag="a" class to="/app/products/barcode">
              <lucide-icon class="nav-icon" name="barcode" />
              <span class="item-name">{{$t('Printbarcode')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('count_stock')"
          >
            <router-link tag="a" class to="/app/products/count_stock">
              <lucide-icon class="nav-icon" name="check-check" />
              <span class="item-name">{{$t('CountStock')}}</span>
            </router-link>
          </li>
           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('category')"
          >
            <router-link tag="a" class to="/app/products/Categories">
              <lucide-icon class="nav-icon" name="copy" />
              <span class="item-name">{{$t('Categories')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('subcategory')"
          >
            <router-link tag="a" class to="/app/products/SubCategories">
              <lucide-icon class="nav-icon" name="library" />
              <span class="item-name">{{$t('SubCategory')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('brand')"
          >
            <router-link tag="a" class to="/app/products/Brands">
              <lucide-icon class="nav-icon" name="bookmark" />
              <span class="item-name">{{$t('Brand')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('unit')"
          >
            <router-link tag="a" class to="/app/products/Units">
              <lucide-icon class="nav-icon" name="quote" />
              <span class="item-name">{{$t('Units')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && (currentUserPermissions.includes('view_batches') || currentUserPermissions.includes('batch_view'))"
          >
            <router-link tag="a" class to="/app/products/Batches">
              <lucide-icon class="nav-icon" name="heart-pulse" />
              <span class="item-name">{{$t('Batches')}}</span>
            </router-link>
          </li>
        </ul>

        <ul
          class="childNav d-none"
          data-parent="accounting"
          :class="{ 'd-block': selectedParentMenu == 'accounting' }"
        >
          <!-- NEW FEATURE - SAFE ADDITION: Advanced Accounting under Accounting -->
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('accounting_dashboard')">
            <router-link tag="a" class to="/app/accounting-v2/dashboard">
              <lucide-icon class="nav-icon" name="trending-up" />
              <span class="item-name">{{ $t("dashboard") }}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('chart_of_accounts')">
            <router-link tag="a" class to="/app/accounting-v2/chart-of-accounts">
              <lucide-icon class="nav-icon" name="database" />
              <span class="item-name">{{ $t('Chart_of_Accounts_Title') }}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('journal_entries')">
            <router-link tag="a" class to="/app/accounting-v2/journal-entries">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{ $t('Journal_Entries_Title') }}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('trial_balance')">
            <router-link tag="a" class to="/app/accounting-v2/reports/trial-balance">
              <lucide-icon class="nav-icon" name="trending-up" />
              <span class="item-name">{{ $t('Trial_Balance_Title') }}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('accounting_profit_loss')">
            <router-link tag="a" class to="/app/accounting-v2/reports/profit-and-loss">
              <lucide-icon class="nav-icon" name="wallet" />
              <span class="item-name">{{ $t('Profit_Loss_Title') }}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('balance_sheet')">
            <router-link tag="a" class to="/app/accounting-v2/reports/balance-sheet">
              <lucide-icon class="nav-icon" name="pie-chart" />
              <span class="item-name">{{ $t('Balance_Sheet_Title') }}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('accounting_tax_report')">
            <router-link tag="a" class to="/app/accounting-v2/reports/tax-report">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{ $t('Tax_Summary_Report') }}</span>
            </router-link>
          </li>
        </ul>

        <ul
          class="childNav d-none"
          data-parent="adjustments"
          :class="{ 'd-block': selectedParentMenu == 'adjustments' }"
        >
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('adjustment_add')"
          >
            <router-link tag="a" class to="/app/adjustments/store">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('CreateAdjustment')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('adjustment_view')"
          >
            <router-link tag="a" class to="/app/adjustments/list">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('ListAdjustments')}}</span>
            </router-link>
          </li>
        </ul>

        <ul
          class="childNav d-none"
          data-parent="transfers"
          :class="{ 'd-block': selectedParentMenu == 'transfers' }"
        >
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('transfer_add')"
          >
            <router-link tag="a" class to="/app/transfers/store">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('CreateTransfer')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('transfer_view')"
          >
            <router-link tag="a" class to="/app/transfers/list">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('ListTransfers')}}</span>
            </router-link>
          </li>
        </ul>

        <ul
          class="childNav d-none"
          data-parent="damages"
          :class="{ 'd-block': selectedParentMenu == 'damages' }"
        >
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('damage_view')"
          >
            <router-link tag="a" class to="/app/damages/store">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{ $t('Create_Damage') }}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('damage_view')"
          >
            <router-link tag="a" class to="/app/damages/list">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{ $t('Damages') }}</span>
            </router-link>
          </li>
        </ul>

        <ul
          class="childNav d-none"
          data-parent="accounting"
          :class="{ 'd-block': selectedParentMenu == 'accounting' }"
        >
          

        <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('account')"
          >
            <router-link tag="a" class to="/app/accounts">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('List_accounts')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('transfer_money')"
          >
            <router-link tag="a" class to="/app/transfer_money">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('Transfers_Money')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('expense_add')"
          >
            <router-link tag="a" class to="/app/expenses/store">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('Create_Expense')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('expense_view')"
          >
            <router-link tag="a" class to="/app/expenses/list">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('ListExpenses')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('deposit_add')"
          >
            <router-link tag="a" class to="/app/deposits/store">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('Create_deposit')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('deposit_view')"
          >
            <router-link tag="a" class to="/app/deposits/list">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('List_Deposit')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('expense_view')"
          >
            <router-link tag="a" class to="/app/expenses/category">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('Expense_Category')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('deposit_view')"
          >
            <router-link tag="a" class to="/app/deposits/category">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('Deposit_Category')}}</span>
            </router-link>
          </li>

          
        </ul>

        
        <ul
          class="childNav d-none"
          data-parent="purchases"
          :class="{ 'd-block': selectedParentMenu == 'purchases' }"
        >
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Purchases_add')"
          >
            <router-link tag="a" class to="/app/purchases/store">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('AddPurchase')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Purchases_view')"
          >
            <router-link tag="a" class to="/app/purchases/list">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('ListPurchases')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Purchases_add')"
          >
            <router-link tag="a" class to="/app/purchases/import_purchases">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('Import_Purchases')}}</span>
            </router-link>
          </li>
          
        </ul>

        <ul
          class="childNav d-none"
          data-parent="service"
          :class="{ 'd-block': selectedParentMenu == 'service' }"
        >
          <li class="nav-item">
            <router-link tag="a" class to="/app/service/jobs">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('Service_Jobs')}}</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link tag="a" class :to="{ path: '/app/service/jobs', query: { status: 'quoted' } }">
              <lucide-icon class="nav-icon" name="hourglass" />
              <span class="item-name">{{$t('Awaiting_Approval') || 'Awaiting Approval'}}</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link tag="a" class :to="{ path: '/app/service/jobs', query: { status: 'ready' } }">
              <lucide-icon class="nav-icon" name="bell" />
              <span class="item-name">{{$t('Ready_For_Pickup') || 'Ready for Pickup'}}</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link tag="a" class :to="{ path: '/app/service/jobs', query: { payment_status: 'unpaid' } }">
              <lucide-icon class="nav-icon" name="banknote" />
              <span class="item-name">{{$t('Awaiting_Payment') || 'Awaiting Payment'}}</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link tag="a" class to="/app/service/technicians">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('Service_Technicians')}}</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link tag="a" class to="/app/service/checklist-categories">
              <lucide-icon class="nav-icon" name="folder" />
              <span class="item-name">{{$t('Checklist_Categories')}}</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link tag="a" class to="/app/service/checklists">
              <lucide-icon class="nav-icon" name="check" />
              <span class="item-name">{{$t('Checklist_Items')}}</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link tag="a" class to="/app/service/history">
              <lucide-icon class="nav-icon" name="calendar-days" />
              <span class="item-name">{{$t('Maintenance_History')}}</span>
            </router-link>
          </li>
        </ul>

        <ul
          class="childNav d-none"
          data-parent="assets"
          :class="{ 'd-block': selectedParentMenu == 'assets' }"
        >
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('assets')">
            <router-link tag="a" class to="/app/assets/store">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('Add_Asset')}}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('assets')">
            <router-link tag="a" class to="/app/assets/list">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('Assets_List')}}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('assets')">
            <router-link tag="a" class to="/app/assets/due">
              <lucide-icon class="nav-icon" name="clock" />
              <span class="item-name">{{$t('Due_Assets')}}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('assets')">
            <router-link tag="a" class to="/app/assets/category">
              <lucide-icon class="nav-icon" name="folder" />
              <span class="item-name">{{$t('Asset_Category')}}</span>
            </router-link>
          </li>
        </ul>

        <ul
          class="childNav d-none"
          data-parent="sales"
          :class="{ 'd-block': selectedParentMenu == 'sales' }"
        >
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Sales_add')"
          >
            <router-link tag="a" class to="/app/sales/store">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('AddSale')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Sales_add')"
          >
            <router-link tag="a" class to="/app/sales/import_sales">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('Import_Sales')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Sales_view')"
          >
            <router-link tag="a" class to="/app/sales/list">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('ListSales')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Pos_view')"
          >
            <router-link tag="a" class to="/app/pos">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">POS</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('customer_display_screen_setup')"
          >
            <router-link tag="a" class to="/app/customer-display/setup">
              <lucide-icon class="nav-icon" name="barcode" />
              <span class="item-name">{{$t('Customer_Screen')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('shipment')"
          >
            <router-link tag="a" class to="/app/sales/shipment">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('Shipments')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('real_time_sales_counter')"
          >
            <router-link tag="a" class to="/app/real-time-sales-counter">
              <lucide-icon class="nav-icon" name="shopping-cart" />
              <span class="item-name">{{ $t("Real_time_Sales_Counter") || "Real-time Sales Counter" }}</span>
            </router-link>
          </li>
        </ul>

        <ul
          class="childNav d-none"
          data-parent="bookings"
          :class="{ 'd-block': selectedParentMenu == 'bookings' }"
        >
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('bookings')"
          >
            <router-link tag="a" class to="/app/bookings/list">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{ $t('Booking_List') || 'Booking List' }}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('bookings')"
          >
            <router-link tag="a" class to="/app/bookings/calendar">
              <lucide-icon class="nav-icon" name="calendar-days" />
              <span class="item-name">{{ $t('Calendar_View') }}</span>
            </router-link>
          </li>
        </ul>

        <ul
          class="childNav d-none"
          data-parent="commissions"
          :class="{ 'd-block': selectedParentMenu == 'commissions' }"
        >
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('commissions_view')">
            <router-link tag="a" class to="/app/commissions/programs">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{ $t('Commission_Programs') }}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('commissions_view')">
            <router-link tag="a" class to="/app/commissions/agents">
              <lucide-icon class="nav-icon" name="users" />
              <span class="item-name">{{ $t('Sales_Agents') }}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('commissions_view')">
            <router-link tag="a" class to="/app/commissions/rules">
              <lucide-icon class="nav-icon" name="settings" />
              <span class="item-name">{{ $t('Commission_Rules') }}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('commissions_view')">
            <router-link tag="a" class to="/app/commissions/receipts">
              <lucide-icon class="nav-icon" name="receipt" />
              <span class="item-name">{{ $t('Commission_Receipts') }}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('commissions_view')">
            <router-link tag="a" class to="/app/commissions/report">
              <lucide-icon class="nav-icon" name="trending-up" />
              <span class="item-name">{{ $t('Commission_Report') }}</span>
            </router-link>
          </li>
        </ul>

        <ul
          class="childNav d-none"
          data-parent="quotations"
          :class="{ 'd-block': selectedParentMenu == 'quotations' }"
        >
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Quotations_add')"
          >
            <router-link tag="a" class to="/app/quotations/store">
              <lucide-icon class="nav-icon" name="file-plus" />
              <span class="item-name">{{$t('AddQuote')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Quotations_view')"
          >
            <router-link tag="a" class to="/app/quotations/list">
              <lucide-icon class="nav-icon" name="files" />
              <span class="item-name">{{$t('ListQuotations')}}</span>
            </router-link>
          </li>
        </ul>

       
      <!-- hrm -->
        <ul
          class="childNav d-none"
          data-parent="hrm"
          :class="{ 'd-block': selectedParentMenu == 'hrm' }"
        >
         <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('company')"
          >
            <router-link tag="a" class to="/app/hrm/company">
              <lucide-icon class="nav-icon" name="briefcase-business" />
              <span class="item-name">{{$t('Company')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('department')"
          >
            <router-link tag="a" class to="/app/hrm/departments">
              <lucide-icon class="nav-icon" name="store" />
              <span class="item-name">{{$t('Departments')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('designation')"
          >
            <router-link tag="a" class to="/app/hrm/designations">
              <lucide-icon class="nav-icon" name="camera" />
              <span class="item-name">{{$t('Designations')}}</span>
            </router-link>
          </li>
           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('office_shift')"
          >
            <router-link tag="a" class to="/app/hrm/office_Shift">
              <lucide-icon class="nav-icon" name="clock" />
              <span class="item-name">{{$t('Office_Shift')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('view_employee')"
          >
            <router-link tag="a" class to="/app/hrm/employees">
              <lucide-icon class="nav-icon" name="wrench" />
              <span class="item-name">{{$t('Employees')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('attendance')"
          >
            <router-link tag="a" class to="/app/hrm/attendance">
              <lucide-icon class="nav-icon" name="clock" />
              <span class="item-name">{{$t('Attendance')}}</span>
            </router-link>
          </li>
           <li
            v-if="currentUserPermissions && (currentUserPermissions.includes('leave'))"

           @click.prevent="toggleSidebarDropdwon($event)"
            class="nav-item dropdown-sidemenu"
          >

            <a href="#">
              <lucide-icon class="nav-icon" name="calendar" />
              <span class="item-name">{{$t('Leave_request')}}</span>
              <lucide-icon class="dd-arrow" name="chevron-down" />
            </a>
            <ul class="submenu">
              <li
              >
                <router-link tag="a" class to="/app/hrm/leaves/list">
                  <lucide-icon class="nav-icon" name="id-card" />
                  <span class="item-name">{{$t('Leave_request')}}</span>
                </router-link>
              </li>
              <li
              >
                <router-link tag="a" class to="/app/hrm/leaves/type">
                  <lucide-icon class="nav-icon" name="id-card" />
                  <span class="item-name">{{$t('Leave_type')}}</span>
                </router-link>
              </li>
              
            </ul>
          </li>
           
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('holiday')"
          >
            <router-link tag="a" class to="/app/hrm/holidays">
              <lucide-icon class="nav-icon" name="bell-ring" />
              <span class="item-name">{{$t('Holidays')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('payroll')"
          >
            <router-link tag="a" class to="/app/hrm/payrolls">
              <lucide-icon class="nav-icon" name="banknote" />
              <span class="item-name">{{$t('Payroll')}}</span>
            </router-link>
          </li>

        </ul>


         <!-- People -->
        <ul
          class="childNav d-none"
          data-parent="People"
          :class="{ 'd-block': selectedParentMenu == 'People' }"
        >
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Customers_view')"
          >
            <router-link tag="a" class to="/app/People/Customers">
              <lucide-icon class="nav-icon" name="shield-check" />
              <span class="item-name">{{$t('Customers')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Customers_add')"
          >
            <router-link tag="a" class to="/app/People/Customers/create">
              <lucide-icon class="nav-icon" name="plus" />
              <span class="item-name">{{$t('Add')}} {{$t('Customer')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('customers_import')"
          >
            <router-link tag="a" class to="/app/People/Customers_import">
              <lucide-icon class="nav-icon" name="download" />
              <span class="item-name">{{$t('Import_Customers')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Customers_view')"
          >
            <router-link tag="a" class to="/app/People/Customers_without_ecommerce">
              <lucide-icon class="nav-icon" name="shield-check" />
              <span class="item-name">{{$t('Customers_without_Login')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Customers_view')"
          >
            <router-link tag="a" class to="/app/People/Customers_ecommerce">
              <lucide-icon class="nav-icon" name="shield-check" />
              <span class="item-name">{{$t('Customers_with_Login')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Customers_view')"
          >
            <a href="/portal" target="_blank" rel="noopener" class="nav-link">
              <lucide-icon class="nav-icon" name="app-window" />
              <span class="item-name">{{$t('Client_Portal')}}</span>
            </a>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Suppliers_view')"
          >
            <router-link tag="a" class to="/app/People/Suppliers">
              <lucide-icon class="nav-icon" name="shield-check" />
              <span class="item-name">{{$t('Suppliers')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Suppliers_add')"
          >
            <router-link tag="a" class to="/app/People/Suppliers/create">
              <lucide-icon class="nav-icon" name="plus" />
              <span class="item-name">{{$t('Add')}} {{$t('Supplier')}}</span>
            </router-link>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Suppliers_import')"
          >
            <router-link tag="a" class to="/app/People/Suppliers_import">
              <lucide-icon class="nav-icon" name="download" />
              <span class="item-name">{{$t('Import_Suppliers')}}</span>
            </router-link>
          </li>

        </ul>

        <ul
          class="childNav d-none"
          data-parent="User_Management"
          :class="{ 'd-block': selectedParentMenu == 'User_Management' }"
        >
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('users_view')"
          >
            <router-link tag="a" class to="/app/User_Management/Users">
              <lucide-icon class="nav-icon" name="shield-check" />
              <span class="item-name">{{$t('Users')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('permissions_view')"
          >
            <router-link tag="a" class to="/app/User_Management/permissions">
              <lucide-icon class="nav-icon" name="key" />
              <span class="item-name">{{$t('GroupPermissions')}}</span>
            </router-link>
          </li>

        </ul>

        <ul
          class="childNav d-none"
          data-parent="settings"
          :class="{ 'd-block': selectedParentMenu == 'settings' }"
        >
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('setting_system')"
          >
            <router-link tag="a" class to="/app/settings/System_settings">
              <lucide-icon class="nav-icon" name="settings" />
              <span class="item-name">{{$t('SystemSettings')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('quickbooks_settings')"
          >
            <router-link tag="a" class to="/app/settings/quickbooks_sync">
              <lucide-icon class="nav-icon" name="banknote" />
              <span class="item-name">{{$t('Quickbooks_Sync')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('appearance_settings')"
          >
            <router-link tag="a" class to="/app/settings/appearance_settings">
              <lucide-icon class="nav-icon" name="database-zap" />
              <span class="item-name">{{$t('Dynamic_Appearance')}} </span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('translations_settings')"
          >
            <router-link tag="a" class to="/app/settings/translations_settings">
              <lucide-icon class="nav-icon" name="database-zap" />
              <span class="item-name">{{$t('Languages')}} </span>
            </router-link>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('payment_methods')"
          >
            <router-link tag="a" class to="/app/settings/payment_methods">
              <lucide-icon class="nav-icon" name="banknote" />
              <span class="item-name">{{$t('Payment_Methods')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('sms_settings')"
          >
            <router-link tag="a" class to="/app/settings/sms_settings">
              <lucide-icon class="nav-icon" name="message-square" />
              <span class="item-name">{{$t('sms_settings')}}</span>
            </router-link>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('notification_template')"
          >
            <router-link tag="a" class to="/app/settings/sms_templates">
              <lucide-icon class="nav-icon" name="message-square" />
              <span class="item-name">{{$t('sms_templates')}}</span>
            </router-link>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('mail_settings')"
          >
            <router-link tag="a" class to="/app/settings/mail_settings">
              <lucide-icon class="nav-icon" name="mail" />
              <span class="item-name">{{$t('mail_settings')}}</span>
            </router-link>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('notification_template')"
          >
            <router-link tag="a" class to="/app/settings/email_templates">
              <lucide-icon class="nav-icon" name="mail" />
              <span class="item-name">{{$t('email_templates')}}</span>
            </router-link>
          </li>

          <!-- POS Settings (System Settings -> POS Settings tab) -->
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('pos_settings')"
          >
            <router-link tag="a" class to="/app/settings/pos_settings">
              <lucide-icon class="nav-icon" name="database-zap" />
              <span class="item-name">{{$t('Pos_Settings')}}</span>
            </router-link>
          </li>

          <!-- POS Receipt page (dedicated POS receipt settings view) -->
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('pos_settings')"
          >
            <router-link tag="a" class to="/app/settings/pos_receipt">
              <lucide-icon class="nav-icon" name="calculator" />
              <span class="item-name">{{$t('POS_Receipt')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('module_settings')"
          >
            <router-link tag="a" class to="/app/settings/module_settings">
              <lucide-icon class="nav-icon" name="database-zap" />
              <span class="item-name">{{$t('module_settings')}}</span>
            </router-link>
          </li>

            <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('update_settings')"
          >
            <router-link tag="a" class to="/app/settings/update_settings">
              <lucide-icon class="nav-icon" name="arrow-up-circle" />
              <span class="item-name">{{$t('update_settings')}}</span>
            </router-link>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('payment_gateway')"
          >
            <router-link tag="a" class to="/app/settings/payment_gateway">
              <lucide-icon class="nav-icon" name="banknote" />
              <span class="item-name">{{$t('Payment_Gateway')}}</span>
            </router-link>
          </li>

          

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('warehouse')"
          >
            <router-link tag="a" class to="/app/settings/Warehouses">
              <lucide-icon class="nav-icon" name="store" />
              <span class="item-name">{{$t('Warehouses')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('warehouse_locations')"
          >
            <router-link tag="a" class to="/app/settings/Warehouse_Locations">
              <lucide-icon class="nav-icon" name="map-pin" />
              <span class="item-name">{{$t('Warehouse_Locations')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('currency')"
          >
            <router-link tag="a" class to="/app/settings/Currencies">
              <lucide-icon class="nav-icon" name="dollar-sign" />
              <span class="item-name">{{$t('Currencies')}}</span>
            </router-link>
          </li>
         
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('backup')"
          >
            <router-link tag="a" class to="/app/settings/Backup">
              <lucide-icon class="nav-icon" name="database-backup" />
              <span class="item-name">{{$t('Backup')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('system_health_view')"
          >
            <router-link tag="a" class to="/app/settings/system_health">
              <lucide-icon class="nav-icon" name="monitor-up" />
              <span class="item-name">{{$t('System_Health')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('webhooks_view')"
          >
            <router-link tag="a" class to="/app/settings/webhooks">
              <lucide-icon class="nav-icon" name="cloud" />
              <span class="item-name">{{$t('Webhooks')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('login_device_management')"
          >
            <router-link tag="a" class to="/app/settings/login_devices">
              <lucide-icon class="nav-icon" name="lock" />
              <span class="item-name">{{$t('Login_Device_Management')}}</span>
            </router-link>
          </li>

        </ul>

        <ul
          class="childNav d-none"
          data-parent="reports"
          :class="{ 'd-block': selectedParentMenu == 'reports' }"
        >
          <li
            v-if="currentUserPermissions && currentUserPermissions.includes('sales_3d_dashboard')"
            class="nav-item"
          >
            <router-link tag="a" class to="/app/reports/sales-3d-dashboard">
              <lucide-icon class="nav-icon" name="atom" />
              <span class="item-name">{{$t('sales_3d_dashboard')}}</span>
            </router-link>
          </li>

          <li
            v-if="currentUserPermissions &&
             (currentUserPermissions.includes('Reports_payments_Purchases')
           || currentUserPermissions.includes('Reports_payments_Sales')
           || currentUserPermissions.includes('Reports_payments_Sale_Returns')
           || currentUserPermissions.includes('Reports_payments_purchase_Return'))"
            @click.prevent="toggleSidebarDropdwon($event)"
            class="nav-item dropdown-sidemenu"
          >
            <a href="#">
              <lucide-icon class="nav-icon" name="credit-card" />
              <span class="item-name">{{$t('Payments')}}</span>
              <lucide-icon class="dd-arrow" name="chevron-down" />
            </a>
            <ul class="submenu">
              <li
                v-if="currentUserPermissions && currentUserPermissions.includes('Reports_payments_Purchases')"
              >
                <router-link tag="a" class to="/app/reports/payments_purchase">
                  <lucide-icon class="nav-icon" name="id-card" />
                  <span class="item-name">{{$t('Purchases')}}</span>
                </router-link>
              </li>
              <li
                v-if="currentUserPermissions && currentUserPermissions.includes('Reports_payments_Sales')"
              >
                <router-link tag="a" class to="/app/reports/payments_sale">
                  <lucide-icon class="nav-icon" name="id-card" />
                  <span class="item-name">{{$t('Sales')}}</span>
                </router-link>
              </li>
              <li
                v-if="currentUserPermissions && currentUserPermissions.includes('Reports_payments_Sale_Returns')"
              >
                <router-link tag="a" class to="/app/reports/payments_sales_returns">
                  <lucide-icon class="nav-icon" name="id-card" />
                  <span class="item-name">{{$t('SalesReturn')}}</span>
                </router-link>
              </li>
              <li
                v-if="currentUserPermissions && currentUserPermissions.includes('Reports_payments_purchase_Return')"
              >
                <router-link tag="a" class to="/app/reports/payments_purchases_returns">
                  <lucide-icon class="nav-icon" name="id-card" />
                  <span class="item-name">{{$t('PurchasesReturn')}}</span>
                </router-link>
              </li>
            </ul>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('report_transactions')"
          >
            <router-link tag="a" class to="/app/reports/report_transactions">
              <lucide-icon class="nav-icon" name="dollar-sign" />
              <span class="item-name">{{$t('Report_Transactions')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('cash_flow_report')"
          >
            <router-link tag="a" class to="/app/reports/cash_flow_report">
              <lucide-icon class="nav-icon" name="trending-up" />
              <span class="item-name">{{$t('Cash_Flow_Report')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('seller_report')"
          >
            <router-link tag="a" class to="/app/reports/seller_report">
              <lucide-icon class="nav-icon" name="user" />
              <span class="item-name">{{$t('Seller_report')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('report_attendance_summary')"
          >
            <router-link tag="a" class :to="{ name: 'attendance_report' }">
              <lucide-icon class="nav-icon" name="clock" />
              <span class="item-name">{{$t('attendance_summary')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Reports_profit')"
          >
            <router-link tag="a" class to="/app/reports/profit_and_loss">
              <lucide-icon class="nav-icon" name="wallet" />
              <span class="item-name">{{$t('ProfitandLoss')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('analytics_report')"
          >
            <router-link tag="a" class to="/app/reports/analytics_report">
              <lucide-icon class="nav-icon" name="bar-chart" />
              <span class="item-name">{{$t('Analytics_Report')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Stock_Inventory_Valuation')"
          >
            <router-link tag="a" class to="/app/reports/stock_inventory_valuation">
              <lucide-icon class="nav-icon" name="trending-up" />
              <span class="item-name">{{$t('Stock_Inventory_Valuation')}}</span>
            </router-link>
          </li>
          
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('cash_register_report')"
          >
            <router-link tag="a" class :to="{ name: 'cash_register_report' }">
              <lucide-icon class="nav-icon" name="banknote" />
              <span class="item-name">{{$t('Cash_Register_Report')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('report_warranty')"
          >
            <router-link tag="a" class :to="{ name: 'warranty_guarantee_report' }">
              <lucide-icon class="nav-icon" name="shield" />
              <span class="item-name">{{ $t('Warranty_Guarantee_Report') }}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('inventory_valuation')"
          >
            <router-link tag="a" class to="/app/reports/inventory_valuation_summary">
              <lucide-icon class="nav-icon" name="pie-chart" />
              <span class="item-name">{{$t('Inventory_Valuation')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('expenses_report')"
          >
            <router-link tag="a" class to="/app/reports/expenses_report">
              <lucide-icon class="nav-icon" name="receipt-text" />
              <span class="item-name">{{$t('Expense_Report')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('deposits_report')"
          >
            <router-link tag="a" class to="/app/reports/deposits_report">
              <lucide-icon class="nav-icon" name="shield" />
              <span class="item-name">{{$t('Deposits_Report')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Reports_quantity_alerts')"
          >
            <router-link tag="a" class to="/app/reports/quantity_alerts">
              <lucide-icon class="nav-icon" name="alarm-clock" />
              <span class="item-name">{{$t('ProductQuantityAlerts')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Warehouse_report')"
          >
            <router-link tag="a" class to="/app/reports/warehouse_report">
              <lucide-icon class="nav-icon" name="warehouse" />
              <span class="item-name">{{$t('Warehouse_report')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('internal_location_report')"
          >
            <router-link tag="a" class to="/app/reports/internal_location_report">
              <lucide-icon class="nav-icon" name="map-pin" />
              <span class="item-name">{{$t('Internal_Location_Report')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('stock_report')"
          >
            <router-link tag="a" class to="/app/reports/stock_report">
              <lucide-icon class="nav-icon" name="trending-up" />
              <span class="item-name">{{$t('stock_report')}}</span>
            </router-link>
          </li>
          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('negative_stock_report')"
          >
            <router-link tag="a" class to="/app/reports/negative_stock_report">
              <lucide-icon class="nav-icon" name="trending-up" />
              <span class="item-name">{{$t('Negative_Stock_Report')}}</span>
            </router-link>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('product_report')"
          >
            <router-link tag="a" class to="/app/reports/product_report">
              <lucide-icon class="nav-icon" name="barcode" />
              <span class="item-name">{{$t('product_report')}}</span>
            </router-link>
          </li>

          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('zeroSalesProducts')">
            <router-link tag="a" class :to="{ name: 'zero_sales_products_report' }">
              <lucide-icon class="nav-icon" name="shopping-bag" />
              <span class="item-name">{{$t('Zero_Sales_Products_Report')}}</span>
            </router-link>
          </li>

          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('Dead_Stock_Report')">
            <router-link tag="a" class :to="{ name: 'dead_stock_report' }">
              <lucide-icon class="nav-icon" name="shopping-bag" />
              <span class="item-name">{{$t('Dead_Stock_Report')}}</span>
            </router-link>
          </li>

          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('expiry_report')">
            <router-link tag="a" class :to="{ name: 'expiry_report' }">
              <lucide-icon class="nav-icon" name="timer" />
              <span class="item-name">{{$t('Expiry_Report')}}</span>
            </router-link>
          </li>

          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('Batch_Register_Report')">
            <router-link tag="a" class :to="{ name: 'batch_register_report' }">
              <lucide-icon class="nav-icon" name="package" />
              <span class="item-name">{{ $t('Batch_Register_Report') || 'Batch Register' }}</span>
            </router-link>
          </li>

          <li
          class="nav-item"
          v-if="currentUserPermissions && currentUserPermissions.includes('Stock_Aging_Report')"
        >
          <router-link tag="a" class :to="{ name: 'stock_aging_report' }">
            <lucide-icon class="nav-icon" name="clock" />
            <span class="item-name">{{$t('Stock_Aging_Report')}}</span>
          </router-link>
        </li>

        <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('Stock_Transfer_Report')">
        <router-link tag="a" class :to="{ name: 'stock_transfer_report' }">
          <lucide-icon class="nav-icon" name="arrow-left" />
          <span class="item-name">{{$t('Stock_Transfer_Report')}}</span>
        </router-link>
      </li>

      <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('Stock_Adjustment_Report')">
        <router-link tag="a" :to="{ name: 'stock_adjustment_report' }">
          <lucide-icon class="nav-icon" name="pencil" />
          <span class="item-name">{{$t('Stock_Adjustment_Report')}}</span>
        </router-link>
      </li>


        <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('discount_summary_report')">
          <router-link tag="a" class :to="{ name: 'discount_summary_report' }">
            <lucide-icon class="nav-icon" name="receipt" />
            <span class="item-name">{{$t('Discount_Summary_Report')}}</span>
          </router-link>
        </li>
      <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('customer_loyalty_points_report')">
        <router-link tag="a" class :to="{ name: 'customer_loyalty_points_report' }">
          <lucide-icon class="nav-icon" name="heart" />
          <span class="item-name">{{$t('Customer_Loyalty_Points_Report')}}</span>
        </router-link>
      </li>

        <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('tax_summary_report')">
          <router-link tag="a" class :to="{ name: 'tax_summary_report' }">
            <lucide-icon class="nav-icon" name="files" />
            <span class="item-name">{{$t('Tax_Summary_Report')}}</span>
          </router-link>
        </li>



        <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('draft_invoices_report')">
          <router-link tag="a" class :to="{ name: 'draft_invoices_report' }">
            <lucide-icon class="nav-icon" name="receipt" />
            <span class="item-name">{{$t('Draft_Invoices_Report')}}</span>
          </router-link>
        </li>


          

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('return_ratio_report')"
          >
            <router-link tag="a" class to="/app/reports/return_ratio_report">
              <lucide-icon class="nav-icon" name="trending-up" />
              <span class="item-name">{{$t('Return_Ratio_Report')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Reports_sales')"
          >
            <router-link tag="a" class to="/app/reports/sales_report">
              <lucide-icon class="nav-icon" name="bar-chart" />
              <span class="item-name">{{$t('SalesReport')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('product_sales_report')"
          >
            <router-link tag="a" class to="/app/reports/product_sales_report">
              <lucide-icon class="nav-icon" name="trending-up" />
              <span class="item-name">{{$t('product_sales_report')}}</span>
            </router-link>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('report_sales_by_category')"
          >
            <router-link tag="a" class to="/app/reports/report_sales_by_category">
              <lucide-icon class="nav-icon" name="tag" />
              <span class="item-name">{{$t('Sales_by_Category')}}</span>
            </router-link>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('report_sales_by_brand')"
          >
            <router-link tag="a" class to="/app/reports/report_sales_by_brand">
              <lucide-icon class="nav-icon" name="store" />
              <span class="item-name">{{$t('Sales_by_Brand')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Reports_purchase')"
          >
            <router-link tag="a" class to="/app/reports/purchase_report">
              <lucide-icon class="nav-icon" name="shopping-cart" />
              <span class="item-name">{{$t('PurchasesReport')}}</span>
            </router-link>
          </li>

            <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('product_purchases_report')"
          >
            <router-link tag="a" class to="/app/reports/product_purchases_report">
              <lucide-icon class="nav-icon" name="shopping-basket" />
              <span class="item-name">{{$t('Product_purchases_report')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Reports_customers')"
          >
            <router-link tag="a" class to="/app/reports/customers_report">
              <lucide-icon class="nav-icon" name="user" />
              <span class="item-name">{{$t('CustomersReport')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('inactive_customers_report')"
          >
            <router-link tag="a" class to="/app/reports/inactive_customers">
              <lucide-icon class="nav-icon" name="user-minus" />
              <span class="item-name">{{$t('Inactive_Customers_Report')}}</span>
            </router-link>
          </li>

          <li class="nav-item" v-if="currentUserPermissions && currentUserPermissions.includes('Top_Suppliers_Report')">
            <router-link tag="a" class :to="{ name: 'top_suppliers_report' }">
              <lucide-icon class="nav-icon" name="users" />
              <span class="item-name">{{$t('Top_Suppliers_Report')}}</span>
            </router-link>
          </li>


          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Reports_suppliers')"
          >
            <router-link tag="a" class to="/app/reports/providers_report">
              <lucide-icon class="nav-icon" name="user" />
              <span class="item-name">{{$t('SuppliersReport')}}</span>
            </router-link>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Top_products')"
          >
            <router-link tag="a" class to="/app/reports/top_selling_products">
              <lucide-icon class="nav-icon" name="trophy" />
              <span class="item-name">{{$t('Top_Selling_Products')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('Top_customers')"
          >
            <router-link tag="a" class to="/app/reports/top_customers">
              <lucide-icon class="nav-icon" name="trophy" />
              <span class="item-name">{{$t('Top_customers')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('users_report')"
          >
            <router-link tag="a" class to="/app/reports/users_report">
              <lucide-icon class="nav-icon" name="user" />
              <span class="item-name">{{$t('Users_Report')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('report_device_management')"
          >
            <router-link tag="a" class to="/app/reports/login_activity_report">
              <lucide-icon class="nav-icon" name="lock" />
              <span class="item-name">{{$t('Login_Activity_Report')}}</span>
            </router-link>
          </li>

           <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('report_error_logs')"
          >
            <router-link tag="a" class to="/app/reports/report_error_logs">
              <lucide-icon class="nav-icon" name="bug" />
              <span class="item-name">{{$t('Error_Logs')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('service_jobs_report')"
          >
            <router-link tag="a" class :to="{ name: 'service_jobs_report' }">
              <lucide-icon class="nav-icon" name="wrench" />
              <span class="item-name">{{$t('Service_Jobs_Report')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('checklist_completion_report')"
          >
            <router-link tag="a" class :to="{ name: 'checklist_completion_report' }">
              <lucide-icon class="nav-icon" name="check" />
              <span class="item-name">{{$t('Checklist_Completion_Report')}}</span>
            </router-link>
          </li>

          <li
            class="nav-item"
            v-if="currentUserPermissions && currentUserPermissions.includes('customer_maintenance_history_report')"
          >
            <router-link tag="a" class :to="{ name: 'customer_maintenance_history_report' }">
              <lucide-icon class="nav-icon" name="calendar-days" />
              <span class="item-name">{{$t('Customer_Maintenance_History_Report')}}</span>
            </router-link>
          </li>


          


        </ul>
      </div>
    </vue-perfect-scrollbar>
    <div
      @click="removeOverlay()"
      class="sidebar-overlay"
      :class="{ open: getSideBarToggleProperties.isSecondarySideNavOpen }"
    ></div>
  </div>
  <!--=============== Left side End ================-->
</template>

<script>
import Topnav from "./TopNav";
import { isMobile } from "mobile-device-detect";

import { mapGetters, mapActions } from "vuex";

export default {
  components: {
    Topnav
  },

  data() {
    return {
      isDisplay: true,
      isMenuOver: false,
      isStyle: true,
      selectedParentMenu: "",
      isMobile,
    };
  },
  mounted() {
    this.toggleSelectedParentMenu();
    window.addEventListener("resize", this.handleWindowResize);
    document.addEventListener("click", this.returnSelectedParentMenu);
    this.handleWindowResize();
  },

  beforeDestroy() {
    document.removeEventListener("click", this.returnSelectedParentMenu);
    window.removeEventListener("resize", this.handleWindowResize);
  },

  computed: {
    ...mapGetters(["getSideBarToggleProperties", "currentUserPermissions"])
  },

  methods: {
    ...mapActions([
      "changeSecondarySidebarProperties",
      "changeSecondarySidebarPropertiesViaMenuItem",
      "changeSecondarySidebarPropertiesViaOverlay",
      "changeSidebarProperties"
    ]),

    handleWindowResize() {
      if (window.innerWidth <= 1200) {
        if (this.getSideBarToggleProperties.isSideNavOpen) {
          this.changeSidebarProperties();
        }
        if (this.getSideBarToggleProperties.isSecondarySideNavOpen) {
          this.changeSecondarySidebarProperties();
        }
      } else {
        if (!this.getSideBarToggleProperties.isSideNavOpen) {
          this.changeSidebarProperties();
        }
      }
    },
    toggleSelectedParentMenu() {
      const currentParentUrl = this.$route.path
        .split("/")
        .filter(x => x !== "")[1];
      if (currentParentUrl !== undefined || currentParentUrl !== null) {
        this.selectedParentMenu = currentParentUrl.toLowerCase();
      } else {
        this.selectedParentMenu = "dashboard";
      }
    },
    toggleSubMenu(e) {
      let hasSubmenu = e.target.dataset.submenu;
      let parent = e.target.dataset.item;

      if (hasSubmenu) {
        this.selectedParentMenu = parent;

        this.changeSecondarySidebarPropertiesViaMenuItem(true);
      } else {
        this.selectedParentMenu = parent;
        this.changeSecondarySidebarPropertiesViaMenuItem(false);
      }
    },

    removeOverlay() {
      this.changeSecondarySidebarPropertiesViaOverlay();
      if (window.innerWidth <= 1200) {
        this.changeSidebarProperties();
      }
      this.toggleSelectedParentMenu();
    },
    returnSelectedParentMenu() {
      if (!this.isMenuOver) {
        this.toggleSelectedParentMenu();
      }
    },

    toggleSidebarDropdwon(event) {
      let dropdownMenus = this.$el.querySelectorAll(".dropdown-sidemenu.open");

      event.currentTarget.classList.toggle("open");

      dropdownMenus.forEach(dropdown => {
        dropdown.classList.remove("open");
      });
    }
  }
};
</script>

<style>

.navigation-left::after{
  content:"";
  display:block;
  height: calc(80px + env(safe-area-inset-bottom, 0px));
}

</style>

