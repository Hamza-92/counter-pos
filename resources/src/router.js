import Vue from "vue";
import store from "./store";
import NProgress from "nprogress";
import Router from "vue-router";
Vue.use(Router);






// create new router

const baseRoutes = [
    {
        path: "/",
        component: () => import("./views/app"),
        redirect: "/app/dashboard",
        name: 'app',

        children: [
            {
                path: "/app/dashboard",
                name: "dashboard",
                component: () =>
                    import(
                        /* webpackChunkName: "dashboard" */ "./views/app/dashboard/dashboard"
                    )
            },
            {
                path: "/app/reports/sales-3d-dashboard",
                name: "sales_3d_dashboard",
                component: () =>
                    import(
                        /* webpackChunkName: "sales_3d_dashboard" */ "./views/app/dashboard/Sales3DDashboard"
                    )
            },
            {
                path: "/app/real-time-sales-counter",
                name: "real_time_sales_counter",
                component: () =>
                    import(
                        /* webpackChunkName: "real_time_sales_counter" */ "./views/app/pages/real_time_sales_counter"
                    )
            },

            //Products
            {
                path: "/app/products",
                component: () =>
                    import(
                        /* webpackChunkName: "products" */ "./views/app/pages/products"
                    ),
                redirect: "app/products/list",
                children: [
                    {
                        name: "index_products",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_products" */ "./views/app/pages/products/index_products"
                            )
                    },
                    {
                    path: "import",
                    name: "import_products",
                    component: () =>
                        import(/* webpackChunkName: "import_products" */ "./views/app/pages/products/Import_products.vue"),
                    },
                    {
                        path: "import-update",
                        name: "import_products_update",
                        component: () =>
                            import(/* webpackChunkName: "import_products_update" */ "./views/app/pages/products/Import_products_update.vue"),
                        },
                    {
                        path: "store",
                        name: "store_product",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_product" */ "./views/app/pages/products/Add_product"
                            )
                    },
                    {
                        path: "edit/:id",
                        name: "edit_product",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_product" */ "./views/app/pages/products/Edit_product"
                            )
                    },
                    {
                        path: "detail/:id",
                        name: "detail_product",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_product" */ "./views/app/pages/products/Detail_Product"
                            )
                    },

                    {
                        path: "opening_stock_import",
                        name: "opening_stock_import",
                        component: () =>
                            import(
                                /* webpackChunkName: "opening_stock_import" */ "./views/app/pages/products/opening_stock_import"
                            )
                    },

                    {
                        path: "barcode",
                        name: "barcode",
                        component: () =>
                            import(
                                /* webpackChunkName: "barcode" */ "./views/app/pages/products/barcode"
                            )
                    },

                    {
                        path: "count_stock",
                        name: "count_stock",
                        component: () =>
                            import(
                                /* webpackChunkName: "count_stock" */ "./views/app/pages/products/count_stock"
                            )
                    },
                     // categories
                     {
                        name: "categories",
                        path: "Categories",
                        component: () =>
                            import(
                                /* webpackChunkName: "Categories" */ "./views/app/pages/products/categorie"
                            )
                    },

                    // subcategories
                    {
                        name: "subcategories",
                        path: "SubCategories",
                        component: () =>
                            import(
                                /* webpackChunkName: "SubCategories" */ "./views/app/pages/products/subcategories"
                            )
                    },

                    // brands
                    {
                        name: "brands",
                        path: "Brands",
                        component: () =>
                            import(
                                /* webpackChunkName: "Brands" */ "./views/app/pages/products/brands"
                            )
                    },

                    // units
                    {
                        name: "units",
                        path: "Units",
                        component: () =>
                            import(
                                /* webpackChunkName: "units" */ "./views/app/pages/products/units"
                            )
                    },

                    // batches (pharmacy-mode batch & expiry tracking)
                    {
                        name: "batches",
                        path: "Batches",
                        component: () =>
                            import(
                                /* webpackChunkName: "batches" */ "./views/app/pages/products/batches"
                            )
                    },
                ]
            },

            //Adjustement
            {
                path: "/app/adjustments",
                component: () =>
                    import(
                        /* webpackChunkName: "adjustments" */ "./views/app/pages/adjustment"
                    ),
                redirect: "/app/adjustments/list",
                children: [
                    {
                        name: "index_adjustment",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_adjustment" */
                                "./views/app/pages/adjustment/index_Adjustment"
                            )
                    },
                    {
                        name: "store_adjustment",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_adjustment" */
                                "./views/app/pages/adjustment/Create_Adjustment"
                            )
                    },
                    {
                        name: "edit_adjustment",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_adjustment" */
                                "./views/app/pages/adjustment/Edit_Adjustment"
                            )
                    },
                    {
                        name: "detail_adjustment",
                        path: "detail/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_adjustment" */
                                "./views/app/pages/adjustment/Detail_Adjustment"
                            )
                    }
                ]
            },

            // Damages
            {
                path: "/app/damages",
                component: () =>
                    import(
                        /* webpackChunkName: "damages" */ "./views/app/pages/damage"
                    ),
                redirect: "/app/damages/list",
                children: [
                    {
                        name: "index_damage",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_damage" */
                                "./views/app/pages/damage/index_Damage"
                            )
                    },
                    {
                        name: "store_damage",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_damage" */
                                "./views/app/pages/damage/Create_Damage"
                            )
                    },
                    {
                        name: "edit_damage",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_damage" */
                                "./views/app/pages/damage/Edit_Damage"
                            )
                    }
                ]
            },

            //Transfer
            {
                path: "/app/transfers",
                component: () =>
                    import(
                        /* webpackChunkName: "transfers" */ "./views/app/pages/transfers"
                    ),
                redirect: "/app/transfers/list",
                children: [
                    {
                        name: "index_transfer",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_transfer" */ "./views/app/pages/transfers/index_transfer"
                            )
                    },
                    {
                        name: "store_transfer",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_transfer" */
                                "./views/app/pages/transfers/create_transfer"
                            )
                    },
                    {
                        name: "edit_transfer",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_transfer" */ "./views/app/pages/transfers/edit_transfer"
                            )
                    },
                    {
                        name: "detail_transfer",
                        path: "detail/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_transfer" */ "./views/app/pages/transfers/detail_transfer"
                            )
                    }
                ]
            },

             // accounts
             {
                name: "accounts",
                path: "/app/accounts",
                component: () =>
                    import(
                        /* webpackChunkName: "accounts" */ "./views/app/pages/accounts/account_list"
                    )
            },

            //Projects
             {
                path: "/app/projects",
                component: () =>
                    import(
                        /* webpackChunkName: "projects" */ "./views/app/pages/projects"
                    ),
                redirect: "/app/projects/list",
                children: [
                    {
                        name: "index_project",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_project" */
                                "./views/app/pages/projects/index_project"
                            )
                    },
                    {
                        name: "store_project",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_project" */
                                "./views/app/pages/projects/store_project"
                            )
                    },
                    {
                        name: "edit_project",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_project" */
                                "./views/app/pages/projects/Edit_project"
                            )
                    },

                ]
            },

            // Contracts
            {
                path: "/app/contracts",
                component: () =>
                    import(
                        /* webpackChunkName: "contracts" */ "./views/app/pages/contracts"
                    ),
                redirect: "/app/contracts/list",
                children: [
                    {
                        name: "index_contract",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_contract" */
                                "./views/app/pages/contracts/index_contract"
                            )
                    },
                    {
                        name: "store_contract",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_contract" */
                                "./views/app/pages/contracts/store_contract"
                            )
                    },
                    {
                        name: "edit_contract",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_contract" */
                                "./views/app/pages/contracts/Edit_contract"
                            )
                    },
                    {
                        name: "view_contract",
                        path: "view/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "view_contract" */
                                "./views/app/pages/contracts/View_contract"
                            )
                    },
                ]
            },

             //Tasks
             {
                path: "/app/tasks",
                component: () =>
                    import(
                        /* webpackChunkName: "tasks" */ "./views/app/pages/tasks"
                    ),
                redirect: "/app/tasks/list",
                children: [
                    {
                        name: "index_task",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_task" */
                                "./views/app/pages/tasks/index_task"
                            )
                    },
                    {
                        name: "store_task",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_task" */
                                "./views/app/pages/tasks/store_task"
                            )
                    },
                    {
                        name: "edit_task",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_task" */
                                "./views/app/pages/tasks/Edit_task"
                            )
                    },

                ]
            },

            // Bookings (simple)
            {
                path: "/app/bookings",
                component: () =>
                    import(
                        /* webpackChunkName: "bookings" */ "./views/app/pages/bookings"
                    ),
                redirect: "/app/bookings/list",
                children: [
                    {
                        name: "index_booking",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_booking" */
                                "./views/app/pages/bookings/index_booking"
                            )
                    },
                    {
                        name: "store_booking",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_booking" */
                                "./views/app/pages/bookings/store_booking"
                            )
                    },
                    {
                        name: "edit_booking",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_booking" */
                                "./views/app/pages/bookings/Edit_booking"
                            )
                    },
                    {
                        name: "calendar_booking",
                        path: "calendar",
                        component: () =>
                            import(
                                /* webpackChunkName: "calendar_booking" */
                                "./views/app/pages/bookings/BookingCalendar"
                            )
                    },
                ]
            },


            // Assets
            {
                path: "/app/assets",
                component: () =>
                    import(
                        /* webpackChunkName: "assets" */ "./views/app/pages/assets"
                    ),
                redirect: "/app/assets/list",
                children: [
                    {
                        name: "index_asset",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_asset" */ "./views/app/pages/assets/index_asset"
                            )
                    },
                    {
                        name: "store_asset",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_asset" */ "./views/app/pages/assets/store_asset"
                            )
                    },
                    {
                        name: "edit_asset",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_asset" */ "./views/app/pages/assets/edit_asset"
                            )
                    },
                    {
                        name: "asset_category",
                        path: "category",
                        component: () =>
                            import(
                                /* webpackChunkName: "asset_category" */ "./views/app/pages/assets/index_asset_category"
                            )
                    },
                    {
                        name: "due_assets",
                        path: "due",
                        component: () =>
                            import(
                                /* webpackChunkName: "due_assets" */ "./views/app/pages/assets/due_assets"
                            )
                    }
                ]
            },

            // Service & Maintenance
            {
                path: "/app/service",
                component: () =>
                    import(
                        /* webpackChunkName: "service" */ "./views/app/pages/service"
                    ),
                redirect: "/app/service/jobs",
                children: [
                    {
                        name: "service_jobs_index",
                        path: "jobs",
                        component: () =>
                            import(
                                /* webpackChunkName: "service_jobs_index" */ "./views/app/pages/service/ServiceJobsList"
                            )
                    },
                    {
                        name: "service_job_create",
                        path: "jobs/create",
                        component: () =>
                            import(
                                /* webpackChunkName: "service_job_create" */ "./views/app/pages/service/ServiceJobForm"
                            )
                    },
                    {
                        name: "service_job_edit",
                        path: "jobs/edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "service_job_edit" */ "./views/app/pages/service/ServiceJobForm"
                            )
                    },
                    {
                        name: "service_job_details",
                        path: "jobs/details/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "service_job_details" */ "./views/app/pages/service/ServiceJobDetails"
                            )
                    },
                    {
                        name: "service_checklist_categories",
                        path: "checklist-categories",
                        component: () =>
                            import(
                                /* webpackChunkName: "service_checklist_categories" */ "./views/app/pages/service/ServiceChecklistCategories"
                            )
                    },
                    {
                        name: "service_checklists",
                        path: "checklists",
                        component: () =>
                            import(
                                /* webpackChunkName: "service_checklists" */ "./views/app/pages/service/ServiceChecklists"
                            )
                    },
                    {
                        name: "service_technicians",
                        path: "technicians",
                        component: () =>
                            import(
                                /* webpackChunkName: "service_technicians" */ "./views/app/pages/service/ServiceTechnicians"
                            )
                    },
                    {
                        name: "customer_maintenance_history",
                        path: "history",
                        component: () =>
                            import(
                                /* webpackChunkName: "customer_maintenance_history" */ "./views/app/pages/service/CustomerMaintenanceHistory"
                            )
                    }
                ]
            },

             // transfer_money
             {
                name: "transfer_money",
                path: "/app/transfer_money",
                component: () =>
                    import(
                        /* webpackChunkName: "transfer_money" */ "./views/app/pages/accounts/transfer_money"
                    )
            },

          

            //expenses
            {
                path: "/app/expenses",
                component: () =>
                    import(
                        /* webpackChunkName: "expenses" */ "./views/app/pages/expense"
                    ),
                redirect: "/app/expenses/list",
                children: [
                    {
                        name: "index_expense",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_expense" */ "./views/app/pages/expense/index_expense"
                            )
                    },
                    {
                        name: "store_expense",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_expense" */ "./views/app/pages/expense/create_expense"
                            )
                    },
                    {
                        name: "edit_expense",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_expense" */ "./views/app/pages/expense/edit_expense"
                            )
                    },
                    {
                        name: "expense_category",
                        path: "category",
                        component: () =>
                            import(
                                /* webpackChunkName: "expense_category" */ "./views/app/pages/expense/category_expense"
                            )
                    },

                ]
            },

            //deposits
            {
                path: "/app/deposits",
                component: () =>
                    import(
                        /* webpackChunkName: "deposits" */ "./views/app/pages/deposits"
                    ),
                redirect: "/app/deposits/list",
                children: [

                    {
                        name: "index_deposit",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_deposit" */ "./views/app/pages/deposits/index_deposit"
                            )
                    },
                    {
                        name: "store_deposit",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_deposit" */ "./views/app/pages/deposits/create_deposit"
                            )
                    },
                    {
                        name: "edit_deposit",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_deposit" */ "./views/app/pages/deposits/edit_deposit"
                            )
                    },
                    {
                        name: "deposit_category",
                        path: "category",
                        component: () =>
                            import(
                                /* webpackChunkName: "deposit_category" */ "./views/app/pages/deposits/deposit_category"
                            )
                    }
                ]
            },

            //Quotation
            {
                path: "/app/quotations",
                component: () =>
                    import(
                        /* webpackChunkName: "quotations" */ "./views/app/pages/quotations"
                    ),
                redirect: "/app/quotations/list",
                children: [
                    {
                        name: "index_quotation",
                        path: "list",
                        component: () =>
                            import(
                                "./views/app/pages/quotations/index_quotation"
                            )
                    },
                    {
                        name: "store_quotation",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_quotation" */
                                "./views/app/pages/quotations/create_quotation"
                            )
                    },
                    {
                        name: "edit_quotation",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_quotation" */
                                "./views/app/pages/quotations/edit_quotation"
                            )
                    },
                    {
                        name: "detail_quotation",
                        path: "detail/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_quotation" */
                                "./views/app/pages/quotations/detail_quotation"
                            )
                    },
                    {
                        name: "change_to_sale",
                        path: "create_sale/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "change_to_sale" */ "./views/app/pages/sales/change_to_sale.vue"
                            )
                    },
                    
                ]
            },

            // Advanced Accounting (NEW FEATURE - SAFE ADDITION)
            {
                path: "/app/accounting-v2",
                component: () =>
                    import(
                        /* webpackChunkName: "accounting_v2" */ "./views/app/pages/accounting_v2"
                    ),
                redirect: "/app/accounting-v2/dashboard",
                children: [
                    {
                        name: "accounting_v2_dashboard",
                        path: "dashboard",
                        component: () =>
                            import(
                                /* webpackChunkName: "accounting_v2_dashboard" */ "./views/app/pages/accounting_v2/dashboard"
                            )
                    },
                    {
                        name: "accounting_v2_chart_of_accounts",
                        path: "chart-of-accounts",
                        component: () =>
                            import(
                                /* webpackChunkName: "accounting_v2_chart_of_accounts" */ "./views/app/pages/accounting_v2/chart_of_accounts"
                            )
                    },
                    {
                        name: "accounting_v2_journal_entries",
                        path: "journal-entries",
                        component: () =>
                            import(
                                /* webpackChunkName: "accounting_v2_journal_entries" */ "./views/app/pages/accounting_v2/journal_entries"
                            )
                    },
                    {
                        name: "accounting_v2_trial_balance",
                        path: "reports/trial-balance",
                        component: () =>
                            import(
                                /* webpackChunkName: "accounting_v2_trial_balance" */ "./views/app/pages/accounting_v2/reports/trial_balance"
                            )
                    },
                    {
                        name: "accounting_v2_profit_loss",
                        path: "reports/profit-and-loss",
                        component: () =>
                            import(
                                /* webpackChunkName: "accounting_v2_profit_loss" */ "./views/app/pages/accounting_v2/reports/profit_and_loss"
                            )
                    },
                    {
                        name: "accounting_v2_balance_sheet",
                        path: "reports/balance-sheet",
                        component: () =>
                            import(
                                /* webpackChunkName: "accounting_v2_balance_sheet" */ "./views/app/pages/accounting_v2/reports/balance_sheet"
                            )
                    },
                    {
                        name: "accounting_v2_tax_report",
                        path: "reports/tax-report",
                        component: () =>
                            import(
                                /* webpackChunkName: "accounting_v2_tax_report" */ "./views/app/pages/accounting_v2/reports/tax_report"
                            )
                    }
                ]
            },

             // Customer Display setup route (for cashiers to generate tokens)
                {
                    name: "customer_display_setup",
                    path: "/app/customer-display/setup",
                    component: () =>
                        import(
                            /* webpackChunkName: "customer_display_setup" */ "./views/app/pages/customer/CustomerDisplaySetup.vue"
                        )
                },

            //Purchase
            {
                path: "/app/purchases",
                component: () =>
                    import(
                        /* webpackChunkName: "purchases" */ "./views/app/pages/purchases"
                    ),
                redirect: "/app/purchases/list",
                children: [
                    {
                        name: "index_purchases",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_purchases" */ "./views/app/pages/purchases/index_purchase"
                            )
                    },
                    {
                        name: "store_purchase",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_purchase" */
                                "./views/app/pages/purchases/create_purchase"
                            )
                    },

                    {
                        name: "import_purchases",
                        path: "import_purchases",
                        component: () =>
                            import(
                                /* webpackChunkName: "import_purchases" */
                                "./views/app/pages/purchases/import_purchases"
                            )
                    },
                    {
                        name: "edit_purchase",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_purchase" */ "./views/app/pages/purchases/edit_purchase"
                            )
                    },
                    {
                        name: "purchase_return",
                        path: "purchase_return/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "create_purchase_return" */ "./views/app/pages/purchase_return/create_purchase_return"
                            )
                    },
                    {
                        name: "detail_purchase",
                        path: "detail/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_purchase" */
                                "./views/app/pages/purchases/detail_purchase"
                            )
                    }
                ]
            },

            //Sale
            {
                path: "/app/sales",
                component: () =>
                    import(
                        /* webpackChunkName: "sales" */ "./views/app/pages/sales"
                    ),
                redirect: "/app/sales/list",
                children: [
                    {
                        name: "index_sales",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_sales" */ "./views/app/pages/sales/index_sale"
                            )
                    },
                    {
                        name: "store_sale",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "store_sale" */ "./views/app/pages/sales/create_sale"
                            )
                    },
                    {
                        name: "import_sales",
                        path: "import_sales",
                        component: () =>
                            import(
                                /* webpackChunkName: "import_sales" */ "./views/app/pages/sales/import_sales"
                            )
                    },
                    {
                        name: "edit_sale",
                        path: "edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_sale" */ "./views/app/pages/sales/edit_sale"
                            )
                    },
                    {
                        name: "sale_return",
                        path: "sale_return/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "create_sale_return" */ "./views/app/pages/sale_return/create_sale_return"
                            )
                    },
                    {
                        name: "detail_sale",
                        path: "detail/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_sale" */ "./views/app/pages/sales/detail_sale"
                            )
                    },
                    {
                        name: "shipment",
                        path: "shipment",
                        component: () =>
                            import(
                                /* webpackChunkName: "shipment" */ "./views/app/pages/sales/shipments"
                            )
                    }
                ]
            },

            // Sales Return
            {
                path: "/app/sale_return",
                component: () =>
                    import(
                        /* webpackChunkName: "sale_return" */ "./views/app/pages/sale_return"
                    ),
                redirect: "/app/sale_return/list",
                children: [
                    {
                        name: "index_sale_return",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_sale_return" */
                                "./views/app/pages/sale_return/index_sale_return"
                            )
                    },
                    // {
                    //     name: "store_sale_return",
                    //     path: "store",
                    //     component: () =>
                    //         import(
                    //             /* webpackChunkName: "store_sale_return" */
                    //             "./views/app/pages/sale_return/create_sale_return"
                    //         )
                    // },
                    {
                        name: "edit_sale_return",
                        path: "edit/:id/:sale_id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_sale_return" */
                                "./views/app/pages/sale_return/edit_sale_return"
                            )
                    },
                    {
                        name: "detail_sale_return",
                        path: "detail/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_sale_return" */
                                "./views/app/pages/sale_return/detail_sale_return"
                            )
                    }
                ]
            },

            // Commission Program
            {
                path: "/app/commissions",
                component: () =>
                    import(
                        /* webpackChunkName: "commissions" */ "./views/app/pages/commissions/commissions"
                    ),
                redirect: "/app/commissions/programs",
                children: [
                    {
                        name: "commission_programs",
                        path: "programs",
                        component: () =>
                            import(
                                /* webpackChunkName: "commission_programs" */ "./views/app/pages/commissions/index_programs"
                            )
                    },
                    {
                        name: "commission_agents",
                        path: "agents",
                        component: () =>
                            import(
                                /* webpackChunkName: "commission_agents" */ "./views/app/pages/commissions/index_agents"
                            )
                    },
                    {
                        name: "commission_rules",
                        path: "rules",
                        component: () =>
                            import(
                                /* webpackChunkName: "commission_rules" */ "./views/app/pages/commissions/index_rules"
                            )
                    },
                    {
                        name: "commission_receipts",
                        path: "receipts",
                        component: () =>
                            import(
                                /* webpackChunkName: "commission_receipts" */ "./views/app/pages/commissions/index_receipts"
                            )
                    },
                    {
                        name: "commission_report",
                        path: "report",
                        component: () =>
                            import(
                                /* webpackChunkName: "commission_report" */ "./views/app/pages/commissions/commission_report"
                            )
                    }
                ]
            },

            // purchase Return
            {
                path: "/app/purchase_return",
                component: () =>
                    import(
                        /* webpackChunkName: "purchase_return" */ "./views/app/pages/purchase_return"
                    ),
                redirect: "/app/purchase_return/list",
                children: [
                    {
                        name: "index_purchase_return",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "index_purchase_return" */
                                "./views/app/pages/purchase_return/index_purchase_return"
                            )
                    },
                    // {
                    //     name: "store_purchase_return",
                    //     path: "store",
                    //     component: () =>
                    //         import(
                    //             /* webpackChunkName: "store_purchase_return" */
                    //             "./views/app/pages/purchase_return/create_purchase_return"
                    //         )
                    // },
                    {
                        name: "edit_purchase_return",
                        path: "edit/:id/:purchase_id",
                        component: () =>
                            import(
                                /* webpackChunkName: "edit_purchase_return" */
                                "./views/app/pages/purchase_return/edit_purchase_return"
                            )
                    },
                    {
                        name: "detail_purchase_return",
                        path: "detail/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_purchase_return" */
                                "./views/app/pages/purchase_return/detail_purchase_return"
                            )
                    }
                ]
            },

            // Hrm
            {
                path: "/app/hrm",
                component: () =>
                    import(
                        /* webpackChunkName: "hrm" */ "./views/app/pages/hrm"
                    ),
                redirect: "/app/hrm/employees",
                children: [
                    // employees
                    {
                        path: "employees",
                        component: () =>
                            import(
                                /* webpackChunkName: "employees" */ "./views/app/pages/hrm/employees"
                            ),
                        redirect: "/app/hrm/employees/list",
                        children: [
                            {
                                name: "employees_list",
                                path: "list",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "index_employee" */
                                        "./views/app/pages/hrm/employees/index_employee"
                                    )
                            },
                            {
                                name: "store_employee",
                                path: "store",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "store_employee" */
                                        "./views/app/pages/hrm/employees/employee_create"
                                    )
                            },
                            {
                                name: "edit_employee",
                                path: "edit/:id",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "edit_employee" */
                                        "./views/app/pages/hrm/employees/employee_edit"
                                    )
                            },
                            {
                                name: "detail_employee",
                                path: "detail/:id",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "detail_employee" */
                                        "./views/app/pages/hrm/employees/employee_details"
                                    )
                            },
                        ]
                    },
                    // company
                    {
                        name: "company",
                        path: "company",
                        component: () =>
                            import(/* webpackChunkName: "company" */"./views/app/pages/hrm/company")
                    },

                    // departments
                    {
                        name: "departments",
                        path: "departments",
                        component: () =>
                            import(/* webpackChunkName: "departments" */"./views/app/pages/hrm/department")
                    },

                      // designations
                      {
                        name: "designations",
                        path: "designations",
                        component: () =>
                            import(/* webpackChunkName: "designations" */"./views/app/pages/hrm/designation")
                    },

                    // office_shift
                    {
                        name: "office_shift",
                        path: "office_shift",
                        component: () =>
                            import(/* webpackChunkName: "office_shift" */"./views/app/pages/hrm/office_shift")
                    },

                     // attendance
                     {
                        name: "attendance",
                        path: "attendance",
                        component: () =>
                            import(/* webpackChunkName: "attendance" */"./views/app/pages/hrm/attendance")
                    },

                      // holidays
                      {
                        name: "holidays",
                        path: "holidays",
                        component: () =>
                            import(/* webpackChunkName: "holidays" */"./views/app/pages/hrm/holidays")
                    },

                     // payrolls
                     {
                        name: "payrolls",
                        path: "payrolls",
                        component: () =>
                            import(/* webpackChunkName: "payrolls" */"./views/app/pages/hrm/payrolls")
                    },


                    {
                        path: "leaves",
                        component: () =>
                            import(
                                /* webpackChunkName: "leaves" */ "./views/app/pages/hrm/leaves"
                            ),
                        redirect: "/app/hrm/leaves/list",
                        children: [
                            {
                                name: "leave_list",
                                path: "list",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "leave_list" */
                                        "./views/app/pages/hrm/leaves/leave_list"
                                    )
                            },
                            {
                                name: "leave_type",
                                path: "type",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "leave_type" */
                                        "./views/app/pages/hrm/leaves/leave_type"
                                    )
                            },
                           
                        ]
                    },

                    
                ]
            },

            {
                path: "/app/Store",
                component: () =>
                    import(
                    /* webpackChunkName: "Store" */ "./views/app/pages/store"
                    ),
                redirect: "/app/Store/Settings",
                children: [
                    // Settings
                    {
                    name: "StoreSettings",
                    path: "Settings",
                    component: () =>
                        import(
                        /* webpackChunkName: "StoreSettings" */ "./views/app/pages/store/Settings.vue"
                        )
                    },

                    // Orders
                    {
                    name: "StoreOrders",
                    path: "Orders",
                    component: () =>
                        import(
                        /* webpackChunkName: "StoreOrders" */ "./views/app/pages/store/Orders.vue"
                        )
                    },
                    {
                    name: "StoreOrderShow",
                    path: "Orders/:id",
                    component: () =>
                        import(
                        /* webpackChunkName: "StoreOrderShow" */ "./views/app/pages/store/OrderShow.vue"
                        ),
                    props: true
                    },

                    // Inside the children of your /app/Store route
                    {
                    name: "StoreCollections",
                    path: "Collections",
                    component: () =>
                        import(
                        /* webpackChunkName: "CollectionsIndex" */ "./views/app/pages/store/CollectionsIndex.vue"
                        ),
                    },
                    {
                    name: "StoreCollectionsCreate",
                    path: "Collections/create",
                    component: () =>
                        import(
                        /* webpackChunkName: "CollectionsCreate" */ "./views/app/pages/store/CollectionsCreate.vue"
                        ),
                    },
                    {
                    name: "StoreCollectionsEdit",
                    path: "Collections/:id",
                    component: () =>
                        import(
                        /* webpackChunkName: "CollectionsEdit" */ "./views/app/pages/store/CollectionsEdit.vue"
                        ),
                    },

                    {
                    name: "StoreSubscribers",
                    path: "Subscribers",
                    component: () =>
                        import(/* webpackChunkName: "StoreSubscribers" */ "./views/app/pages/store/Subscribers.vue")
                    },

                    {
                    name: "StoreMessages",
                    path: "Messages",
                    component: () =>
                        import(/* webpackChunkName: "StoreMessages" */ "./views/app/pages/store/Messages.vue")
                    },


                    // Banners / Home sections
                    {
                    name: "StoreBanners",
                    path: "Banners",
                    component: () =>
                        import(
                        /* webpackChunkName: "StoreBanners" */ "./views/app/pages/store/Banners.vue"
                        )
                    },
                    {
                    name: "StoreBannerEdit",
                    path: "Banners/Edit/:id?",
                    component: () =>
                        import(
                        /* webpackChunkName: "StoreBannerEdit" */ "./views/app/pages/store/BannerForm.vue"
                        ),
                    props: true
                    },

                    // Invite Codes
                    {
                    name: "StoreInviteCodes",
                    path: "InviteCodes",
                    component: () =>
                        import(
                        /* webpackChunkName: "StoreInviteCodes" */ "./views/app/pages/store/InviteCodes.vue"
                        )
                    },

                    // Pending Customers (admin approval)
                    {
                    name: "StorePendingCustomers",
                    path: "PendingCustomers",
                    component: () =>
                        import(
                        /* webpackChunkName: "StorePendingCustomers" */ "./views/app/pages/store/PendingCustomers.vue"
                        )
                    }
                ]
            },

            // Knowledge Base
            {
                path: "/app/knowledge-base",
                component: () =>
                    import(
                        /* webpackChunkName: "knowledge_base" */ "./views/app/pages/knowledge_base/index.vue"
                    ),
                redirect: "/app/knowledge-base/list",
                children: [
                    {
                        name: "KnowledgeBaseList",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "KnowledgeBaseList" */ "./views/app/pages/knowledge_base/KnowledgeBaseList.vue"
                            )
                    },
                    {
                        name: "KnowledgeBaseArticleView",
                        path: "article/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "KnowledgeBaseArticleView" */ "./views/app/pages/knowledge_base/KnowledgeBaseArticleView.vue"
                            ),
                        props: true
                    },
                    {
                        name: "KnowledgeBaseGroups",
                        path: "groups",
                        component: () =>
                            import(
                                /* webpackChunkName: "KnowledgeBaseGroups" */ "./views/app/pages/knowledge_base/KnowledgeBaseGroups.vue"
                            )
                    },
                    {
                        name: "KnowledgeBaseGroupCreate",
                        path: "groups/create",
                        component: () =>
                            import(
                                /* webpackChunkName: "KnowledgeBaseGroupForm" */ "./views/app/pages/knowledge_base/KnowledgeBaseGroupForm.vue"
                            )
                    },
                    {
                        name: "KnowledgeBaseGroupEdit",
                        path: "groups/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "KnowledgeBaseGroupForm" */ "./views/app/pages/knowledge_base/KnowledgeBaseGroupForm.vue"
                            ),
                        props: true
                    },
                    {
                        name: "KnowledgeBaseArticles",
                        path: "articles",
                        component: () =>
                            import(
                                /* webpackChunkName: "KnowledgeBaseArticles" */ "./views/app/pages/knowledge_base/KnowledgeBaseArticles.vue"
                            )
                    },
                    {
                        name: "KnowledgeBaseArticleCreate",
                        path: "articles/create",
                        component: () =>
                            import(
                                /* webpackChunkName: "KnowledgeBaseArticleForm" */ "./views/app/pages/knowledge_base/KnowledgeBaseArticleForm.vue"
                            )
                    },
                    {
                        name: "KnowledgeBaseArticleEdit",
                        path: "articles/:id/edit",
                        component: () =>
                            import(
                                /* webpackChunkName: "KnowledgeBaseArticleForm" */ "./views/app/pages/knowledge_base/KnowledgeBaseArticleForm.vue"
                            ),
                        props: true
                    }
                ]
            },

            // People
            {
                path: "/app/People",
                component: () =>
                    import(
                        /* webpackChunkName: "People" */ "./views/app/pages/people"
                    ),
                redirect: "/app/People/Customers",
                children: [
                    // Customers
                    {
                        name: "Customers",
                        path: "Customers",
                        component: () =>
                            import(
                                /* webpackChunkName: "Customers" */ "./views/app/pages/people/customers"
                            )
                    },

                    {
                        name: "Create_Customer",
                        path: "Customers/create",
                        component: () =>
                            import(
                                /* webpackChunkName: "Create_Customer" */ "./views/app/pages/people/CreateCustomer.vue"
                            )
                    },

                    {
                        name: "Edit_Customer",
                        path: "Customers/edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "Edit_Customer" */ "./views/app/pages/people/EditCustomer.vue"
                            ),
                        props: true
                    },

                    {
                    name: "Import_Customers",
                    path: "Customers_import",
                    component: () => import(
                        /* webpackChunkName: "Import_Customers" */ "./views/app/pages/people/ImportCustomers.vue"
                    )
                    },


                    
                    {
                    name: "CustomerLedger",
                    path: "customers/:id/ledger",
                    component: () =>
                        import(
                        /* webpackChunkName: "CustomerLedger" */ "./views/app/pages/people/CustomerLedger.vue"
                        ),
                    props: true
                    },

                    {
                    name: "CustomerDetails",
                    path: "customers/:id/details",
                    component: () =>
                        import(
                        /* webpackChunkName: "CustomerDetails" */ "./views/app/pages/people/CustomerDetails.vue"
                        ),
                    props: true
                    },

                    // Customers
                    {
                        name: "Customers_without_ecommerce",
                        path: "Customers_without_ecommerce",
                        component: () =>
                            import(
                                /* webpackChunkName: "Customers_without_ecommerce" */ "./views/app/pages/people/Customers_without_ecommerce"
                            )
                    },

                    {
                        name: "Customers_ecommerce",
                        path: "Customers_ecommerce",
                        component: () =>
                            import(
                                /* webpackChunkName: "Customers_ecommerce" */ "./views/app/pages/people/Customers_ecommerce.vue"
                            )
                    },

                    // Suppliers
                    {
                        name: "Suppliers",
                        path: "Suppliers",
                        component: () =>
                            import(
                                /* webpackChunkName: "Suppliers" */ "./views/app/pages/people/providers"
                            )
                    },

                    {
                        name: "Create_Supplier",
                        path: "Suppliers/create",
                        component: () =>
                            import(
                                /* webpackChunkName: "Create_Supplier" */ "./views/app/pages/people/CreateSupplier.vue"
                            )
                    },

                    {
                        name: "Edit_Supplier",
                        path: "Suppliers/edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "Edit_Supplier" */ "./views/app/pages/people/EditSupplier.vue"
                            ),
                        props: true
                    },

                    {
                    name: "Import_Suppliers",
                    path: "Suppliers_import",
                    component: () => import(
                        /* webpackChunkName: "Import_Suppliers" */ "./views/app/pages/people/ImportSuppliers.vue"
                    )
                    },

                ]
            },

            {
                path: "/app/User_Management",
                component: () =>
                    import(
                        /* webpackChunkName: "User_Management" */ "./views/app/pages/user_management"
                    ),
                redirect: "/app/User_Management/Users",
                children: [
                    // Users
                    {
                        name: "Users",
                        path: "Users",
                        component: () =>
                            import(
                                /* webpackChunkName: "Users" */ "./views/app/pages/people/users"
                            )
                    },

                    {
                        name: "Create_User",
                        path: "Users/create",
                        component: () =>
                            import(
                                /* webpackChunkName: "Create_User" */ "./views/app/pages/people/CreateUser.vue"
                            )
                    },

                    {
                        name: "Edit_User",
                        path: "Users/edit/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "Edit_User" */ "./views/app/pages/people/EditUser.vue"
                            ),
                        props: true
                    },

                    // Permissions
                    {
                        path: "permissions",
                        component: () =>
                            import(
                                /* webpackChunkName: "permissions" */ "./views/app/pages/settings/permissions"
                            ),
                        redirect: "/app/User_Management/permissions/list",
                        children: [
                            {
                                name: "groupPermission",
                                path: "list",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "groupPermission" */
                                        "./views/app/pages/settings/permissions/Permissions"
                                    )
                            },
                            {
                                name: "store_permission",
                                path: "store",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "store_permission" */
                                        "./views/app/pages/settings/permissions/Create_permission"
                                    )
                            },
                            {
                                name: "edit_permission",
                                path: "edit/:id",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "edit_permission" */
                                        "./views/app/pages/settings/permissions/Edit_permission"
                                    )
                            }
                        ]
                    }
                ]
            },

            // subscription_product
            {
                path: "/app/subscription_product",
                component: () =>
                    import(
                        /* webpackChunkName: "subscription_product" */ "./views/app/pages/subscription_product"
                    ),
                redirect: "/app/subscription_product/list",
                children: [
                    {
                        name: "subscription_product",
                        path: "list",
                        component: () =>
                            import(
                                /* webpackChunkName: "subscription_product" */
                                "./views/app/pages/subscription_product/subscription_product_list"
                            )
                    },

                    {
                        name: "subscription_product_create",
                        path: "store",
                        component: () =>
                            import(
                                /* webpackChunkName: "subscription_product_create" */
                                "./views/app/pages/subscription_product/subscription_product_create"
                            )
                    },
                 
                    {
                        name: "subscription_product_details",
                        path: "detail/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "subscription_product_details" */
                                "./views/app/pages/subscription_product/subscription_product_details"
                            )
                    },


                    
                    
                ]
            },

            // Settings
            {
                path: "/app/settings",
                component: () =>
                    import(
                        /* webpackChunkName: "settings" */ "./views/app/pages/settings"
                    ),
                redirect: "/app/settings/System_settings",
                children: [
                    // payment_methods
                    {
                        name: "payment_methods",
                        path: "payment_methods",
                        component: () =>
                            import(
                                /* webpackChunkName: "payment_methods" */ "./views/app/pages/settings/payment_methods"
                            )
                    },

                     // sms_settings
                     {
                        name: "sms_settings",
                        path: "sms_settings",
                        component: () =>
                            import(
                                /* webpackChunkName: "sms_settings" */ "./views/app/pages/settings/sms_settings"
                            )
                    },

                    {
                        name: "quickbooks_sync",
                        path: "quickbooks_sync",
                        component: () =>
                            import(
                            /* webpackChunkName: "quickbooks_sync" */ "./views/app/pages/settings/quickbooks_sync"
                            )
                    },

                     // sms_templates
                     {
                        name: "sms_templates",
                        path: "sms_templates",
                        component: () =>
                            import(
                                /* webpackChunkName: "sms_templates" */ "./views/app/pages/settings/sms_templates"
                            )
                    },

                     // email_templates
                     {
                        name: "email_templates",
                        path: "email_templates",
                        component: () =>
                            import(
                                /* webpackChunkName: "email_templates" */ "./views/app/pages/settings/email_templates"
                            )
                    },

                    // appearance_settings
                    {
                    name: "appearance_settings",
                    path: "appearance_settings",
                    component: () =>
                        import(
                            /* webpackChunkName: "appearance_settings" */ "./views/app/pages/settings/appearance_settings"
                        )
                    },

                    // translations_settings
                    {
                    name: "translations_settings",
                    path: "translations_settings",
                    component: () =>
                        import(
                            /* webpackChunkName: "translations_settings" */ "./views/app/pages/settings/translations_settings"
                        )
                    },

                    {
                    name: "translations_view",
                    path: "/translations_view/:locale",
                    component: () =>
                        import(
                        /* webpackChunkName: "translations_view" */ "./views/app/pages/settings/translations_view"
                        )
                    },

                    // pos_settings (POS behaviour & display settings)
                    {
                      name: "pos_settings",
                      path: "pos_settings",
                      component: () =>
                        import(
                          /* webpackChunkName: "pos_settings" */ "./views/app/pages/settings/pos_settings"
                        )
                    },

                    // pos_receipt (POS receipt layout & print configuration)
                    {
                      name: "pos_receipt",
                      path: "pos_receipt",
                      component: () =>
                        import(
                          /* webpackChunkName: "pos_receipt" */ "./views/app/pages/settings/pos_receipt"
                        )
                    },

                     // payment_gateway
                     {
                        name: "payment_gateway",
                        path: "payment_gateway",
                        component: () =>
                            import(
                                /* webpackChunkName: "payment_gateway" */ "./views/app/pages/settings/payment_gateway"
                            )
                        },

                        // mail_settings
                     {
                        name: "mail_settings",
                        path: "mail_settings",
                        component: () =>
                            import(
                                /* webpackChunkName: "mail_settings" */ "./views/app/pages/settings/mail_settings"
                            )
                        },

                        // module_settings
                     {
                        name: "module_settings",
                        path: "module_settings",
                        component: () =>
                            import(
                                /* webpackChunkName: "module_settings" */ "./views/app/pages/settings/module_settings"
                            )
                        },

                         // update_settings
                     {
                        name: "update_settings",
                        path: "update_settings",
                        component: () =>
                            import(
                                /* webpackChunkName: "update_settings" */ "./views/app/pages/settings/update_settings"
                            )
                        },

                    // currencies
                    {
                        name: "currencies",
                        path: "Currencies",
                        component: () =>
                            import(
                                /* webpackChunkName: "Currencies" */ "./views/app/pages/settings/currencies"
                            )
                    },

                    // Backup
                    {
                        name: "Backup",
                        path: "Backup",
                        component: () =>
                            import(
                                /* webpackChunkName: "Backup" */ "./views/app/pages/settings/backup"
                            )
                    },

                    // System Health Dashboard
                    {
                        name: "system_health",
                        path: "system_health",
                        component: () =>
                            import(
                                /* webpackChunkName: "system_health" */ "./views/app/pages/settings/system_health"
                            )
                    },

                    // Warehouses
                    {
                        name: "Warehouses",
                        path: "Warehouses",
                        component: () =>
                            import(
                                /* webpackChunkName: "Warehouses" */ "./views/app/pages/settings/warehouses"
                            )
                    },

                    // Warehouse Locations (Rack/Location)
                    {
                        name: "Warehouse_Locations",
                        path: "Warehouse_Locations",
                        component: () =>
                            import(
                                /* webpackChunkName: "Warehouse_Locations" */ "./views/app/pages/settings/warehouse_locations"
                            )
                    },

                    // System Settings
                    {
                        name: "system_settings",
                        path: "System_settings",
                        component: () =>
                            import(
                                /* webpackChunkName: "System_settings" */ "./views/app/pages/settings/system_settings"
                            )
                    },

                    // Login Device Management (Security Sessions)
                    {
                        name: "login_devices",
                        path: "login_devices",
                        component: () =>
                            import(
                                /* webpackChunkName: "login_devices" */ "./views/app/pages/settings/login_devices"
                            )
                    },

                    // Custom Fields
                    {
                        name: "custom_fields",
                        path: "custom_fields",
                        component: () =>
                            import(
                                /* webpackChunkName: "custom_fields" */ "./views/app/pages/settings/custom_fields"
                            )
                    },

                    // Webhooks (outgoing + incoming logs)
                    {
                        path: "webhooks",
                        component: () =>
                            import(
                                /* webpackChunkName: "webhooks" */ "./views/app/pages/settings/webhooks"
                            ),
                        redirect: "/app/settings/webhooks/list",
                        children: [
                            {
                                name: "webhooks",
                                path: "list",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "webhooks_list" */
                                        "./views/app/pages/settings/webhooks/Webhooks"
                                    )
                            },
                            {
                                name: "webhook_delivery_logs",
                                path: "delivery_logs",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "webhook_delivery_logs" */
                                        "./views/app/pages/settings/webhooks/Delivery_Logs"
                                    )
                            },
                            {
                                name: "webhook_incoming_logs",
                                path: "incoming_logs",
                                component: () =>
                                    import(
                                        /* webpackChunkName: "webhook_incoming_logs" */
                                        "./views/app/pages/settings/webhooks/Incoming_Logs"
                                    )
                            }
                        ]
                    }

                ]
            },
            // WooCommerce Settings (optional module)
            {
                path: "/app/woocommerce",
                component: () => import(
                    /* webpackChunkName: "woocommerce" */ "./views/app/pages/settings/WooCommerceSettings.vue"
                ),
                name: "woocommerce_settings"
            },

            // Reports
            {
                path: "/app/reports",
                component: () => import("./views/app/pages/reports"),
                redirect: "/app/reports/profit_and_loss",
                children: [
                    {
                        name: "payments_purchases",
                        path: "payments_purchase",
                        component: () =>
                            import(
                                /* webpackChunkName: "payments_purchases" */
                                "./views/app/pages/reports/payments/payments_purchases"
                            )
                    },
                    {
                        name: "payments_sales",
                        path: "payments_sale",
                        component: () =>
                            import(
                                /* webpackChunkName: "payments_sales" */
                                "./views/app/pages/reports/payments/payments_sales"
                            )
                    },
                    {
                        name: "payments_purchases_returns",
                        path: "payments_purchases_returns",
                        component: () =>
                            import(
                                /* webpackChunkName: "payments_purchases_returns" */
                                "./views/app/pages/reports/payments/payments_purchases_returns"
                            )
                    },
                    {
                        name: "payments_sales_returns",
                        path: "payments_sales_returns",
                        component: () =>
                            import(
                                /* webpackChunkName: "payments_sales_returns" */
                                "./views/app/pages/reports/payments/payments_sales_returns"
                            )
                    },

                     {
                        name: "inactive_customers",
                        path: "inactive_customers",
                        component: () =>
                            import(
                                /* webpackChunkName: "Inactive_Customers_Report" */
                                "./views/app/pages/reports/Inactive_Customers_Report"
                            )
                    },

                     {
                        name: "zero_sales_products_report",
                        path: "zero_sales_products_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Zero_Sales_Products_Report" */
                                "./views/app/pages/reports/Zero_Sales_Products_Report"
                            )
                    },

                      {
                        name: "dead_stock_report",
                        path: "dead_stock_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Dead_Stock_Report" */
                                "./views/app/pages/reports/Dead_Stock_Report"
                            )
                    },

                    {
                        name: "expiry_report",
                        path: "expiry_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Expiry_Report" */
                                "./views/app/pages/reports/Expiry_Report"
                            )
                    },

                    {
                        name: "batch_register_report",
                        path: "batch_register_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Batch_Register_Report" */
                                "./views/app/pages/reports/Batch_Register_Report"
                            )
                    },
                    {
                        name: "batch_history_report",
                        path: "batch_history_report/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "Batch_History_Report" */
                                "./views/app/pages/reports/Batch_History_Report"
                            )
                    },

                    {
                        name: "stock_aging_report",
                        path: "stock_aging_report",
                        component: () =>
                            import(
                            /* webpackChunkName: "Stock_Aging_Report" */
                            "./views/app/pages/reports/Stock_Aging_Report"
                            )
                    },

                    {
                        name: "stock_transfer_report",
                        path: "stock_transfer_report",
                        component: () => import(/* webpackChunkName:"Stock_Transfer_Report" */ "./views/app/pages/reports/Stock_Transfer_Report")
                    },

                    {
                        name: "stock_adjustment_report",
                        path: "stock_adjustment_report",
                        component: () => import("./views/app/pages/reports/Stock_Adjustment_Report")
                    },

                    {
                    name: "top_suppliers_report",
                    path: "top_suppliers_report",
                    component: () => import(/* webpackChunkName:"Top_Suppliers_Report" */ "./views/app/pages/reports/Top_Suppliers_Report")
                    },


                    

                      {
                        name: "draft_invoices_report",
                        path: "draft_invoices_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Draft_Invoices_Report" */
                                "./views/app/pages/reports/Draft_Invoices_Report"
                            )
                    },


                    {
                        name: "discount_summary_report",
                        path: "discount_summary_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Discount_Summary_Report" */
                                "./views/app/pages/reports/Discount_Summary_Report"
                            )
                    },

                    {
                        name: "customer_loyalty_points_report",
                        path: "customer_loyalty_points_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Customer_Loyalty_Points_Report" */
                                "./views/app/pages/reports/Customer_Loyalty_Points_Report"
                            )
                    },

                     {
                        name: "tax_summary_report",
                        path: "tax_summary_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Tax_Summary_Report" */
                                "./views/app/pages/reports/Tax_Summary_Report"
                            )
                    },

                    {
                        name: "cash_flow_report",
                        path: "cash_flow_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "cash_flow_report" */
                                "./views/app/pages/reports/Cash_Flow_Report"
                            )
                    },

                    {
                        name: "report_transactions",
                        path: "report_transactions",
                        component: () =>
                            import(
                                /* webpackChunkName: "report_transactions" */
                                "./views/app/pages/reports/report_transactions"
                            )
                    },

                     {
                        name: "seller_report",
                        path: "seller_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "seller_report" */
                                "./views/app/pages/reports/seller_report"
                            )
                    },

                     {
                        name: "report_sales_by_category",
                        path: "report_sales_by_category",
                        component: () =>
                            import(
                                /* webpackChunkName: "report_sales_by_category" */
                                "./views/app/pages/reports/report_sales_by_category"
                            )
                    },

                      {
                        name: "report_sales_by_brand",
                        path: "report_sales_by_brand",
                        component: () =>
                            import(
                                /* webpackChunkName: "report_sales_by_brand" */
                                "./views/app/pages/reports/report_sales_by_brand"
                            )
                    },

                    

                    {
                        name: "ai_reports",
                        path: "ai_reports",
                        component: () =>
                            import(
                                /* webpackChunkName: "ai_reports" */
                                "./views/app/pages/reports/AI_Reports"
                            )
                    },

                    {
                        name: "profit_and_loss",
                        path: "profit_and_loss",
                        component: () =>
                            import(
                                /* webpackChunkName: "profit_and_loss" */
                                "./views/app/pages/reports/profit_and_loss"
                            )
                    },

                    {
                        name: "inventory_valuation_summary",
                        path: "inventory_valuation_summary",
                        component: () =>
                            import(
                                /* webpackChunkName: "inventory_valuation_summary" */
                                "./views/app/pages/reports/inventory_valuation_summary"
                            )
                    },

                    {
                        name: "stock_inventory_valuation",
                        path: "stock_inventory_valuation",
                        component: () =>
                            import(
                                /* webpackChunkName: "stock_inventory_valuation" */
                                "./views/app/pages/reports/stock_inventory_valuation"
                            )
                    },

                    {
                        name: "analytics_report",
                        path: "analytics_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "analytics_report" */
                                "./views/app/pages/reports/Analytics_Report"
                            )
                    },


                    {
                        name: "expenses_report",
                        path: "expenses_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "expenses_report" */
                                "./views/app/pages/reports/expenses_report"
                            )
                    },

                    {
                        name: "deposits_report",
                        path: "deposits_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "deposits_report" */
                                "./views/app/pages/reports/deposits_report"
                            )
                    },

                    {
                        name: "return_ratio_report",
                        path: "return_ratio_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "return_ratio_report" */
                                "./views/app/pages/reports/Return_Ratio_Report"
                            )
                    },

                    {
                        name: "negative_stock_report",
                        path: "negative_stock_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "negative_stock_report" */
                                "./views/app/pages/reports/Negative_Stock_Report"
                            )
                    },


                    {
                        name: "quantity_alerts",
                        path: "quantity_alerts",
                        component: () =>
                            import(
                                /* webpackChunkName: "quantity_alerts" */
                                "./views/app/pages/reports/quantity_alerts"
                            )
                    },
                    {
                        name: "warehouse_report",
                        path: "warehouse_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "warehouse_report" */
                                "./views/app/pages/reports/warehouse_report"
                            )
                    },

                    {
                        name: "internal_location_report",
                        path: "internal_location_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "internal_location_report" */
                                "./views/app/pages/reports/internal_location_report"
                            )
                    },

                    {
                        name: "sales_report",
                        path: "sales_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "sales_report" */
                                "./views/app/pages/reports/sales_report"
                            )
                    },

                    {
                        name: "product_sales_report",
                        path: "product_sales_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "product_sales_report" */
                                "./views/app/pages/reports/product_sales_report"
                            )
                    },
                    {
                        name: "purchase_report",
                        path: "purchase_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "purchase_report" */
                                "./views/app/pages/reports/purchase_report"
                            )
                    },

                    {
                        name: "product_purchases_report",
                        path: "product_purchases_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "product_purchases_report" */
                                "./views/app/pages/reports/product_purchases_report"
                            )
                    },

                    {
                        name: "customers_report",
                        path: "customers_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "customers_report" */
                                "./views/app/pages/reports/customers_report"
                            )
                    },
                    {
                        name: "detail_customer_report",
                        path: "detail_customer/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_customer_report" */
                                "./views/app/pages/reports/detail_Customer_Report"
                            )
                    },

                    {
                        name: "providers_report",
                        path: "providers_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "providers_report" */
                                "./views/app/pages/reports/providers_report"
                            )
                    },
                    {
                        name: "detail_supplier_report",
                        path: "detail_supplier/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_supplier_report" */
                                "./views/app/pages/reports/detail_Supplier_Report"
                            )
                    },

                    {
                        name: "top_selling_products",
                        path: "top_selling_products",
                        component: () =>
                            import(
                                /* webpackChunkName: "top_selling_products" */
                                "./views/app/pages/reports/top_selling_products"
                            )
                    },

                    {
                        name: "product_report",
                        path: "product_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "product_report" */
                                "./views/app/pages/reports/product_report"
                            )
                    },
                    {
                        name: "detail_product_report",
                        path: "detail_product/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_product_report" */
                                "./views/app/pages/reports/detail_product_report"
                            )
                    },

                    {
                        name: "top_customers",
                        path: "top_customers",
                        component: () =>
                            import(
                                /* webpackChunkName: "top_customers" */
                                "./views/app/pages/reports/top_customers"
                            )
                    },

                    {
                        name: "stock_report",
                        path: "stock_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "stock_report" */
                                "./views/app/pages/reports/stock_report"
                            )
                    },
                    {
                        name: "detail_stock_report",
                        path: "detail_stock/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_stock_report" */
                                "./views/app/pages/reports/detail_stock_report"
                            )
                    },

                    {
                        name: "users_report",
                        path: "users_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "users_report" */
                                "./views/app/pages/reports/users_report"
                            )
                    },
                    {
                        name: "detail_user_report",
                        path: "detail_user/:id",
                        component: () =>
                            import(
                                /* webpackChunkName: "detail_user_report" */
                                "./views/app/pages/reports/detail_user_report"
                            )
                    },
                    {
                        name: "login_activity_report",
                        path: "login_activity_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "login_activity_report" */
                                "./views/app/pages/reports/login_activity_report"
                            )
                    },

                    {
                        name: "report_error_logs",
                        path: "report_error_logs",
                        component: () =>
                            import(
                                /* webpackChunkName: "report_error_logs" */
                                "./views/app/pages/reports/report_error_logs"
                            )
                    },
                     // Cash Register Report
                    {
                        name: "cash_register_report",
                        path: "cash-registers",
                        component: () =>
                            import(/* webpackChunkName: "cash_register_report" */ "./views/app/pages/reports/Cash_Register_Report")
                    },
                    {
                        name: "warranty_guarantee_report",
                        path: "warranty_guarantee_report",
                        component: () =>
                            import(/* webpackChunkName: "warranty_guarantee_report" */ "./views/app/pages/reports/Warranty_Guarantee_Report")
                    },
                    {
                        name: "attendance_report",
                        path: "attendance_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Attendance_Report" */
                                "./views/app/pages/reports/Attendance_Report"
                            )
                    },
                    {
                        name: "service_jobs_report",
                        path: "service_jobs_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Service_Jobs_Report" */
                                "./views/app/pages/reports/Service_Jobs_Report"
                            )
                    },
                    {
                        name: "checklist_completion_report",
                        path: "checklist_completion_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Checklist_Completion_Report" */
                                "./views/app/pages/reports/Checklist_Completion_Report"
                            )
                    },
                    {
                        name: "customer_maintenance_history_report",
                        path: "customer_maintenance_history_report",
                        component: () =>
                            import(
                                /* webpackChunkName: "Customer_Maintenance_History_Report" */
                                "./views/app/pages/reports/Customer_Maintenance_History_Report"
                            )
                    },
                ]
            },

            {
                name: "profile",
                path: "/app/profile",
                component: () =>
                    import(
                        /* webpackChunkName: "profile" */ "./views/app/pages/profile"
                    )
            }
        ]
    },

    {
        name: "pos",
        path: "/app/pos",
        // beforeEnter: authenticate,
        component: () =>
            import(/* webpackChunkName: "pos" */ "./views/app/pages/pos")
    },





    {
        path: "*",
        name: "NotFound",
        component: () =>
            import(
                /* webpackChunkName: "NotFound" */ "./views/app/pages/notFound"
            )
    },

    {
        path: "not_authorize",
        name: "not_authorize",
        component: () =>
            import(
                /* webpackChunkName: "not_authorize" */ "./views/app/pages/NotAuthorize"
            )
    }
];

const router = new Router({
    mode: "history",
    linkActiveClass: "open",
    routes: baseRoutes,
    scrollBehavior(to, from, savedPosition) {
        return { x: 0, y: 0 };
    }
});

// Fix redundant navigation error
const originalPush = Router.prototype.push;
Router.prototype.push = function push(location, onResolve, onReject) {
  if (onResolve || onReject)
    return originalPush.call(this, location, onResolve, onReject);
  return originalPush.call(this, location).catch(err => err);
};

// ✅ Export function to set up navigation guards
export function setupRouterGuards(i18n) {
  router.beforeEach(async (to, from, next) => {
    if (to.path) {
      NProgress.start();
      NProgress.set(0.1);
    }

    // Ensure we read the actual language string from the language module
    let savedLang = store.getters.getLanguage || (store.state.language && store.state.language.language);

    if (!savedLang) {
      await store.dispatch("setLanguage", navigator.languages);
      savedLang = store.getters.getLanguage || (store.state.language && store.state.language.language);
    }

    // If locale differs, switch i18n locale and lazily load messages when needed
    if (savedLang && savedLang !== i18n.locale) {
      try {
        const existingMessages = i18n.getLocaleMessage ? i18n.getLocaleMessage(savedLang) : null;
        if (!existingMessages || Object.keys(existingMessages || {}).length === 0) {
          const response = await axios.get(`translations/${savedLang}`);
          if (i18n.setLocaleMessage) {
            i18n.setLocaleMessage(savedLang, response.data || {});
          }
        }
      } catch (e) {
        // Fallback silently if loading messages fails; keep navigation flowing
      }

      i18n.locale = savedLang;
    }

    // Sync Vue locale to Laravel session so Blade PDFs use same language (fire-and-forget)
    if (savedLang) {
      axios.post('sync-locale', { locale: savedLang }).catch(() => {});
    }

    next();
  });

  router.afterEach(() => {
    const gullPreLoading = document.getElementById("loading_wrap");
    // Defer hiding to global logic during initial boot; otherwise hide immediately
    if (gullPreLoading) {
      if (window.__initialLoaderActive) {
        // App.vue will set __appReadyToHideLoader; axios interceptors will call __hideInitialLoaderIfDone
        window.__hideInitialLoaderIfDone && window.__hideInitialLoaderIfDone();
      } else {
        gullPreLoading.style.display = "none";
      }
    }

    setTimeout(() => NProgress.done(), 500);

    if (window.innerWidth <= 1200) {
      store.dispatch("changeSidebarProperties");

      if (store.getters.getSideBarToggleProperties.isSecondarySideNavOpen) {
        store.dispatch("changeSecondarySidebarProperties");
      }

      if (store.getters.getCompactSideBarToggleProperties.isSideNavOpen) {
        store.dispatch("changeCompactSidebarProperties");
      }
    } else {
      if (store.getters.getSideBarToggleProperties.isSecondarySideNavOpen) {
        store.dispatch("changeSecondarySidebarProperties");
      }
    }
  });
}


async function Check_Token(to, from, next) {
    let token = to.params.token;
    const res = await axios
        .get("password/find/" + token)
        .then(response => response.data);

    if (!res.success) {
        next("/app/sessions/signIn");
    } else {
        return next();
    }
}

export default router;


