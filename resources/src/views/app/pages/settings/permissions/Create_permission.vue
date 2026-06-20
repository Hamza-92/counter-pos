<template>
  <div class="main-content">
    <breadcumb :page="$t('Create_Permission')" :folder="$t('Users')"/>

    <validation-observer ref="Create_Permission">
      <b-form @submit.prevent="Submit_Permission">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>
                <!-- Role Name -->
                <b-col md="6">
                  <validation-provider
                    name="Role name"
                    :rules="{ required: true}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('RoleName') + ' ' + '*'">
                      <b-form-input
                        :placeholder="$t('Enter_Role_Name')"
                        :state="getValidationState(validationContext)"
                        aria-describedby="Role-feedback"
                        v-model="role.name"
                      ></b-form-input>
                      <b-form-invalid-feedback id="Role-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Role Description -->
                <b-col md="6">
                  <b-form-group :label="$t('RoleDescription')">
                    <b-form-input
                      :placeholder="$t('Enter_Role_Description')"
                      v-model="role.description"
                    ></b-form-input>
                  </b-form-group>
                </b-col>
              </b-row>

              <b-row class="mt-4">
                <!--dashboard -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-dashboard
                        variant="transparent"
                      >{{$t('dashboard')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-dashboard "
                      :visible="true"
                      accordion="my-dashboard"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--dashboard -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="dashboard"
                                >
                                <span>{{$t('dashboard')}} <lucide-icon class="text-info text-15 font-weight-bold" name="help-circle" v-b-tooltip.hover.bottom title="if unchecked only welcome message will be displayed in dashboard" /></span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!--Users -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-UserManagement
                        variant="transparent"
                      >{{$t('UserManagement')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-UserManagement "
                      :visible="true"
                      accordion="my-accordion1"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Users View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="users_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Users ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="users_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Users Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="users_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Users Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="users_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!--  Permissions -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Permissions
                        variant="transparent"
                      >{{$t('UserPermissions')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Permissions "
                      :visible="true"
                      accordion="my-accordion2"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Permissions View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="permissions_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Permissions ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="permissions_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Permissions Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="permissions_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Permissions Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="permissions_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!--  Products -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Products
                        variant="transparent"
                      >{{$t('Products')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Products"
                      :visible="true"
                      accordion="my-accordion3"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Products View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="products_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Products ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="products_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Products Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="products_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Products Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="products_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Products Barcode -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="barcode_view"
                                >
                                <span>{{$t('Barcode')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Products Import -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="product_import"
                                >
                                <span>{{$t('import_products')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                             <!--Category -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="category"
                                >
                                <span>{{$t('Categories')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--SubCategory -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="subcategory"
                                >
                                <span>{{$t('SubCategory')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Brand  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input type="checkbox" checked v-model="permissions" value="brand">
                                <span>{{$t('Brand')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                             <!--Unit  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input type="checkbox" checked v-model="permissions" value="unit">
                                <span>{{$t('Units')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--count stock  -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input type="checkbox" checked v-model="permissions" value="count_stock">
                                <span>Count Stock</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--subscription_product  -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input type="checkbox" checked v-model="permissions" value="subscription_product">
                                <span>Subscription Product</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--opening_stock_import -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="opening_stock_import"
                                >
                                <span>Opening Stock Import</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!--  Pharmacy / Batch Tracking -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Pharmacy
                        variant="transparent"
                      >{{$t('Pharmacy_Settings') || 'Pharmacy'}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Pharmacy"
                      :visible="true"
                      accordion="my-accordion-pharmacy"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!-- batch_view -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="batch_view"
                                >
                                <span>{{$t('batch_view') || 'View Batches'}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!-- batch_manage -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="batch_manage"
                                >
                                <span>{{$t('batch_manage') || 'Manage Batches'}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!-- batch_writeoff -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="batch_writeoff"
                                >
                                <span>{{$t('batch_writeoff') || 'Write-Off Batches'}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!-- batch_force_override -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="batch_force_override"
                                >
                                <span>{{$t('batch_force_override') || 'Override Expiry Block'}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!-- expiry_report -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="expiry_report"
                                >
                                <span>{{$t('expiry_report') || 'Expiry Report'}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!-- Batch_Register_Report -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="Batch_Register_Report"
                                >
                                <span>{{$t('Batch_Register_Report') || 'Batch Register Report'}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!--  Adjustment -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Adjustment
                        variant="transparent"
                      >{{$t('StockAdjustement')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Adjustment"
                      :visible="true"
                      accordion="my-accordion4"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Adjustment View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="adjustment_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Adjustment ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="adjustment_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Adjustment Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="adjustment_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Adjustment Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="adjustment_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

            <!--  Damage -->
            <b-col md="4">
              <b-card no-body class="ul-card__border-radius">
                <b-card-header header-tag="header" class="p-1" role="tab">
                  <b-button
                    class="card-title mb-0"
                    block
                    href="#"
                    v-b-toggle.panel-Damage
                    variant="transparent"
                  >Damages</b-button>
                </b-card-header>
                <b-collapse
                  id="panel-Damage"
                  :visible="true"
                  accordion="my-accordion-damage"
                  role="tabpanel"
                >
                  <b-card-body>
                    <b-card-text>
                      <b-row>
                        <b-col md="6">
                          <label class="checkbox checkbox-outline-primary">
                            <input
                              type="checkbox"
                              checked
                              v-model="permissions"
                              value="damage_view"
                            >
                            <span>Damages</span>
                            <span class="checkmark"></span>
                          </label>
                        </b-col>
                      </b-row>
                    </b-card-text>
                  </b-card-body>
                </b-collapse>
              </b-card>
            </b-col>

                <!--  Transfer -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Transfer
                        variant="transparent"
                      >{{$t('StockTransfers')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Transfer"
                      :visible="true"
                      accordion="my-accordion5"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Transfer View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="transfer_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Transfer ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="transfer_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Transfer Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="transfer_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Transfer Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="transfer_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!--  Accounting -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Accounting
                        variant="transparent"
                      >{{$t('Accounting')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Accounting"
                      :visible="true"
                      accordion="my-accordion6"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>

                            <!--accounting_dashboard -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="accounting_dashboard"
                                >
                                <span>Accounting dashboard</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--chart_of_accounts -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="chart_of_accounts"
                                >
                                <span>Chart of accounts</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--journal_entries -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="journal_entries"
                                >
                                <span>journal entries</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--trial_balance -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="trial_balance"
                                >
                                <span>Trial balance</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--accounting_profit_loss -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="accounting_profit_loss"
                                >
                                <span>Accounting profit & loss</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--balance_sheet -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="balance_sheet"
                                >
                                <span>Balance sheet</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--accounting_tax_report -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="accounting_tax_report"
                                >
                                <span>Accounting tax report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Accounts -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="account"
                                >
                                <span>{{$t('List_accounts')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--transfer_money -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="transfer_money"
                                >
                                <span>Transfer money</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>

                          <b-row>
                            <!--Expense View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="expense_view"
                                >
                                <span>{{$t('expense_view')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Expense ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="expense_add"
                                >
                                <span>{{$t('expense_add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Expense Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="expense_edit"
                                >
                                <span>{{$t('expense_edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Expense Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="expense_delete"
                                >
                                <span>{{$t('expense_delete')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>

                          <b-row>
                            <!--Deposit View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="deposit_view"
                                >
                                <span>{{$t('deposit_view')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Deposit ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="deposit_add"
                                >
                                <span>{{$t('deposit_add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Expense Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="deposit_edit"
                                >
                                <span>{{$t('deposit_edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            
                            <!--Expense Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="deposit_delete"
                                >
                                <span>{{$t('deposit_delete')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Assets -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Assets
                        variant="transparent"
                      >{{$t('Assets')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Assets"
                      :visible="true"
                      accordion="my-accordion-assets"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="assets"
                                >
                                <span>{{$t('Assets')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Service & Maintenance -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Service
                        variant="transparent"
                      >{{$t('Service_Maintenance')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Service"
                      :visible="true"
                      accordion="my-accordion-service"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="service_jobs"
                                >
                                <span>{{$t('Service_Maintenance')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Sales -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Sales
                        variant="transparent"
                      >{{$t('Sales')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Sales"
                      :visible="true"
                      accordion="my-accordion7"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Sales View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Sales_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Sales ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Sales_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Sales Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Sales_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Sales Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Sales_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Sales POS -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Pos_view"
                                >
                                <span>{{$t('pointofsales')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!-- real_time_sales_counter -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="real_time_sales_counter"
                                >
                                <span>{{ $t('Real_time_Sales_Counter') || 'Real-time Sales Counter' }}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--customer_display_screen_setup -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="customer_display_screen_setup"
                                >
                                <span>customer display screen setup</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--shipment -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="shipment"
                                >
                                <span>{{$t('Shipments')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--Change product details -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="edit_product_sale"
                                >
                                <span>{{$t('Change_product_details')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!--edit tax and discount and shipping -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="edit_tax_discount_shipping_sale"
                                >
                                <span>{{$t('edit_tax_and_discount_and_shipping')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Commissions -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Commissions
                        variant="transparent"
                      >{{$t('Commissions')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Commissions"
                      :visible="true"
                      accordion="my-accordion-commissions"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="commissions_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="commissions_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="commissions_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="commissions_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Knowledge Base -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-KnowledgeBase
                        variant="transparent"
                      >{{ $t('Knowledge_Base') || 'Knowledge Base' }}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-KnowledgeBase"
                      :visible="true"
                      accordion="my-accordion-knowledge-base"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="knowledge_base_view"
                                >
                                <span>{{ $t('View') }} / {{ $t('Manage') || 'Manage' }}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Webhooks -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Webhooks
                        variant="transparent"
                      >{{ $t('Webhooks') || 'Webhooks' }}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Webhooks"
                      :visible="true"
                      accordion="my-accordion-webhooks"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="webhooks_view"
                                >
                                <span>{{ $t('View') }}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="webhooks_add"
                                >
                                <span>{{ $t('Add') }}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="webhooks_edit"
                                >
                                <span>{{ $t('Edit') }}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  v-model="permissions"
                                  value="webhooks_delete"
                                >
                                <span>{{ $t('Del') }}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Purchases -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Purchases
                        variant="transparent"
                      >{{$t('Purchases')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Purchases"
                      :visible="true"
                      accordion="my-accordion8"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Purchases View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Purchases_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Purchases ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Purchases_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Purchases Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Purchases_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Purchases Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Purchases_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Change product details -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="edit_product_purchase"
                                >
                                <span>{{$t('Change_product_details')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--edit tax and discount and shipping -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="edit_tax_discount_shipping_purchase"
                                >
                                <span>{{$t('edit_tax_and_discount_and_shipping')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>


                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Quotations -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Quotations
                        variant="transparent"
                      >{{$t('Quotations')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Quotations"
                      :visible="true"
                      accordion="my-accordion9"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Quotations View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Quotations_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Quotations ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Quotations_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Quotations Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Quotations_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Quotations Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Quotations_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Change product details -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="edit_product_quotation"
                                >
                                <span>{{$t('Change_product_details')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--edit tax and discount and shipping -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="edit_tax_discount_shipping_quotation"
                                >
                                <span>{{$t('edit_tax_and_discount_and_shipping')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Sale Returns -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Return-Sale
                        variant="transparent"
                      >{{$t('SalesReturn')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Return-Sale"
                      :visible="true"
                      accordion="my-accordion10"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Return Sale View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Sale_Returns_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Return Sale ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Sale_Returns_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Return Sale Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Sale_Returns_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Return Sale Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Sale_Returns_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Purchase Return -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Return-Purchase
                        variant="transparent"
                      >{{$t('PurchasesReturn')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Return-Purchase"
                      :visible="true"
                      accordion="my-accordion11"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Return Purchase View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Purchase_Returns_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Return Purchase ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Purchase_Returns_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Return Purchase Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Purchase_Returns_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Return Purchase Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Purchase_Returns_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Payment Sales -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Payment-Sales
                        variant="transparent"
                      >{{$t('PaymentsSales')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Payment-Sales"
                      :visible="true"
                      accordion="my-accordion12"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Payment Sales View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_sales_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Payment Sales ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_sales_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Payment Sales Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_sales_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Payment Sales Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_sales_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Payment Purchases -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Payment-Purchases
                        variant="transparent"
                      >{{$t('PaymentsPurchases')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Payment-Purchases"
                      :visible="true"
                      accordion="my-accordion13"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Payment Purchases View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_purchases_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Payment Purchases ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_purchases_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Payment Purchases Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_purchases_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Payment Purchases Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_purchases_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Payment Returns -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Payment-Returns
                        variant="transparent"
                      >{{$t('PaymentsReturns')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Payment-Returns"
                      :visible="true"
                      accordion="my-accordion14"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Payment Returns View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_returns_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Payment Returns ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_returns_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Payment Returns Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_returns_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Payment Returns Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_returns_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Customers -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Customers
                        variant="transparent"
                      >{{$t('Customers')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Customers"
                      :visible="true"
                      accordion="my-accordion15"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Customers View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Customers_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Customers ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Customers_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Customers Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Customers_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Customers Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Customers_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Customers Import -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="customers_import"
                                >
                                <span>{{$t('Import_Customers')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--pay_all_sell_due_at_a_time -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="pay_due"
                                >
                                <span>{{$t('pay_all_sell_due_at_a_time')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--pay_sale_return_due -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="pay_sale_return_due"
                                >
                                <span>{{$t('pay_all_sell_return_due_at_a_time')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Suppliers -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Suppliers
                        variant="transparent"
                      >{{$t('Suppliers')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Suppliers"
                      :visible="true"
                      accordion="my-accordion16"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Suppliers View -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Suppliers_view"
                                >
                                <span>{{$t('View')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Suppliers ADD -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Suppliers_add"
                                >
                                <span>{{$t('Add')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Suppliers Edit -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Suppliers_edit"
                                >
                                <span>{{$t('Edit')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Suppliers Delete -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Suppliers_delete"
                                >
                                <span>{{$t('Del')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Suppliers Import -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Suppliers_import"
                                >
                                <span>{{$t('Import_Suppliers')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--pay_all_purchase_due_at_a_time -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="pay_supplier_due"
                                >
                                <span>{{$t('pay_all_purchase_due_at_a_time')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--pay_all_purchase_return_due_at_a_time -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="pay_purchase_return_due"
                                >
                                <span>{{$t('pay_all_purchase_return_due_at_a_time')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Reports -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Reports
                        variant="transparent"
                      >{{$t('Reports')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Reports"
                      :visible="true"
                      accordion="my-accordion17"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Reports_payments_Sales  -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Reports_payments_Sales"
                                >
                                <span>{{$t('Reports_payments_Sales')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Reports_payments_Purchases  -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Reports_payments_Purchases"
                                >
                                <span>{{$t('Reports_payments_Purchases')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Reports_payments_Sale_Returns  -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Reports_payments_Sale_Returns"
                                >
                                <span>{{$t('Reports_payments_Sale_Return')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Reports_payments_purchase_Return  -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Reports_payments_purchase_Return"
                                >
                                <span>{{$t('Reports_payments_Purchase_Return')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                               <!--Inventory Valuation Summary-->
                               <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="inventory_valuation"
                                >
                                <span>Inventory Valuation Summary</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!--report_transactions-->
                             <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="report_transactions"
                                >
                                <span>Report Transactions</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--cash_flow_report-->
                             <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="cash_flow_report"
                                >
                                <span>Cash flow report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--report_attendance_summary-->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="report_attendance_summary"
                                >
                                <span>Report attendance summary</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!--seller_report-->
                               <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="seller_report"
                                >
                                <span>Seller Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!--report_sales_by_category-->
                             <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="report_sales_by_category"
                                >
                                <span>Sales by Category</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                               <!--report_sales_by_brand-->
                             <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="report_sales_by_brand"
                                >
                                <span>Sales by Brand</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--AI_Reports-->
                             <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="AI_Reports"
                                >
                                <span>AI Reports</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!--expenses_report-->
                              <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="expenses_report"
                                >
                                <span>Expenses Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!--deposits_report-->
                              <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="deposits_report"
                                >
                                <span>Deposits Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            
                            <!-- Sales Reports -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Reports_sales"
                                >
                                <span>{{$t('SalesReport')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Purchases Reports -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Reports_purchase"
                                >
                                <span>{{$t('PurchasesReport')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!-- Customers Reports -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Reports_customers"
                                >
                                <span>{{$t('CustomersReport')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!-- inactive_customers_report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="inactive_customers_report"
                                >
                                <span>Inactive customers report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!-- service_jobs_report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="service_jobs_report"
                                >
                                <span>{{$t('Service_Jobs_Report')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!-- checklist_completion_report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="checklist_completion_report"
                                >
                                <span>{{$t('Checklist_Completion_Report')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!-- customer_maintenance_history_report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="customer_maintenance_history_report"
                                >
                                <span>{{$t('Customer_Maintenance_History_Report')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Suppliers Reports -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Reports_suppliers"
                                >
                                <span>{{$t('SuppliersReport')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Proft and Loss -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Reports_profit"
                                >
                                <span>{{$t('ProfitandLoss')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Product Quantity Alerts -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Reports_quantity_alerts"
                                >
                                <span>{{$t('ProductQuantityAlerts')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Warehouse Stock Chart-->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Warehouse_report"
                                >
                                <span>{{$t('WarehouseStockChart')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Internal Location Report-->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="internal_location_report"
                                >
                                <span>{{$t('Internal_Location_Report')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Top_Selling_Products-->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Top_products"
                                >
                                <span>{{$t('Top_Selling_Products')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--Top_customers-->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Top_customers"
                                >
                                <span>{{$t('Top_customers')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--users_report-->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="users_report"
                                >
                                <span>{{$t('Users_Report')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--stock_report-->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="stock_report"
                                >
                                <span>{{$t('stock_report')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--product_report-->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="product_report"
                                >
                                <span>{{$t('product_report')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!-- cash_register_report -->
                              <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="cash_register_report"
                                >
                                <span>Cash Register Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!-- report_warranty -->
                              <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="report_warranty"
                                >
                                <span>Warranty / Guarantee Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!-- zeroSalesProducts -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="zeroSalesProducts"
                                >
                                <span>zero Sales Products Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            
                              <!-- Dead_Stock_Report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Dead_Stock_Report"
                                >
                                <span>Dead Stock Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!-- Stock_Aging_Report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Stock_Aging_Report"
                                >
                                <span>Stock Aging Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!-- Stock_Transfer_Report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Stock_Transfer_Report"
                                >
                                <span>Stock Transfer Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!-- Stock_Adjustment_Report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Stock_Adjustment_Report"
                                >
                                <span>Stock Adjustment Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!-- Top_Suppliers_Report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Top_Suppliers_Report"
                                >
                                <span>Top Suppliers Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                               <!-- draft_invoices_report --> 
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="draft_invoices_report"
                                >
                                <span>Draft Invoices Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!-- discount_summary_report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="discount_summary_report"
                                >
                                <span>Discount Summary Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!-- tax_summary_report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="tax_summary_report"
                                >
                                <span>Tax Summary Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!-- return_ratio_report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="return_ratio_report"
                                >
                                <span>Return Ratio Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!-- negative_stock_report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="negative_stock_report"
                                >
                                <span>Negative Stock Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!-- customer_loyalty_points_report -->
                             <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="customer_loyalty_points_report"
                                >
                                <span>{{$t('Customer_Loyalty_Points_Report')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--product_sales_report-->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="product_sales_report"
                                >
                                <span>{{$t('product_sales_report')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--product_purchases_report-->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="product_purchases_report"
                                >
                                <span>{{$t('Product_purchases_report')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--report_error_logs-->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="report_error_logs"
                                >
                                <span>Report error logs</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!-- Login Device Management -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="report_device_management"
                                >
                                <span>{{$t('Login_Activity_Report')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!-- analytics_report -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="analytics_report"
                                >
                                <span>Analytics Report</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!-- Stock_Inventory_Valuation -->
                            <b-col md="12">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="Stock_Inventory_Valuation"
                                >
                                <span>Stock Inventory Valuation</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>



                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                 <!-- hrm -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-hrm
                        variant="transparent"
                      >{{$t('HRM')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-hrm"
                      :visible="true"
                      accordion="my-accordion21"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                             <!--view Employee-->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="view_employee"
                                >
                                <span>{{$t('view_employee')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--add Employee-->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="add_employee"
                                >
                                <span>{{$t('Add_Employee')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--edit employee -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="edit_employee"
                                >
                                <span>{{$t('edit_employee')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                             <!--delete employee -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="delete_employee"
                                >
                                <span>{{$t('delete_employee')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Company -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="company"
                                >
                                <span>{{$t('Company')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--department -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="department"
                                >
                                <span>{{$t('department')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--designation -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="designation"
                                >
                                <span>{{$t('Designation')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--office_shift -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="office_shift"
                                >
                                <span>{{$t('Office_Shift')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--attendance -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="attendance"
                                >
                                <span>{{$t('Attendance')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--leave -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="leave"
                                >
                                <span>{{$t('Leave_request')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--holiday -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="holiday"
                                >
                                <span>{{$t('Holiday')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--Payroll -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payroll"
                                >
                                <span>Payroll</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--projects -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="projects"
                                >
                                <span>Projects</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--tasks -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="tasks"
                                >
                                <span>Tasks</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--contracts -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="contracts"
                                >
                                <span>Contracts</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--bookings -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="bookings"
                                >
                                <span>Bookings</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                          
                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                <!-- Settings -->
                <b-col md="6">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-Settings
                        variant="transparent"
                      >{{$t('Settings')}}</b-button>
                    </b-card-header>
                    <b-collapse
                      id="panel-Settings"
                      :visible="true"
                      accordion="my-accordion18"
                      role="tabpanel"
                    >
                      <b-card-body>
                        <b-card-text>
                          <b-row>
                            <!--Settings System -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="setting_system"
                                >
                                <span>{{$t('SystemSettings')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--update_settings -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="update_settings"
                                >
                                <span>{{$t('update_settings')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!-- Login Device Management -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="login_device_management"
                                >
                                <span>Login Device Management</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--woocommerce_settings  -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="woocommerce_settings"
                                >
                                <span>Woocommerce settings</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--payment_methods  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_methods"
                                >
                                <span>Payment methods</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!--quickbooks_settings  -->
                              <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="quickbooks_settings"
                                >
                                <span>Quickbooks settings</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--sms_settings  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="sms_settings"
                                >
                                <span>{{$t('sms_settings')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--notification_template  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="notification_template"
                                >
                                <span>{{$t('notification_template')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--pos_settings  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="pos_settings"
                                >
                                <span>{{$t('pos_settings')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--payment_gateway  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="payment_gateway"
                                >
                                <span>{{$t('payment_gateway')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--mail_settings  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="mail_settings"
                                >
                                <span>{{$t('mail_settings')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                              <!--module_settings  -->
                              <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="module_settings"
                                >
                                <span>{{$t('module_settings')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--Currency  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="currency"
                                >
                                <span>{{$t('Currencies')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Warehouse  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="warehouse"
                                >
                                <span>{{$t('Warehouses')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Warehouse Locations (Rack/Location)  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="warehouse_locations"
                                >
                                <span>{{$t('Warehouse_Locations')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>
                            <!--Backup-->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input type="checkbox" checked v-model="permissions" value="backup">
                                <span>{{$t('Backup')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!-- System Health Dashboard -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="system_health_view"
                                >
                                <span>{{$t('System_Health')}}</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                            <!--appearance_settings  -->
                            <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="appearance_settings"
                                >
                                <span>Dynamic Appearance</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                             <!--translations_settings  -->
                             <b-col md="6">
                              <label class="checkbox checkbox-outline-primary">
                                <input
                                  type="checkbox"
                                  checked
                                  v-model="permissions"
                                  value="translations_settings"
                                >
                                <span>Translations</span>
                                <span class="checkmark"></span>
                              </label>
                            </b-col>

                          </b-row>
                        </b-card-text>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>

                  <!-- Store -->
                <b-col md="4">
                  <b-card no-body class="ul-card__border-radius">
                    <b-card-header header-tag="header" class="p-1" role="tab">
                      <b-button
                        class="card-title mb-0"
                        block
                        href="#"
                        v-b-toggle.panel-store
                        variant="transparent"
                      >Online Store</b-button>
                    </b-card-header>

                    <b-collapse
                      id="panel-store"
                      :visible="true"
                      accordion="my-accordion194"
                      role="tabpanel"
                    >
                      <b-card-body>
                      
                        <b-row>
                          <b-col md="6" class="mb-2">
                            <label class="checkbox checkbox-outline-primary d-block">
                              <input type="checkbox" v-model="permissions" value="Store_settings_view">
                              <span>Store Settings</span>
                              <span class="checkmark"></span>
                            </label>
                          </b-col>

                          <b-col md="6" class="mb-2">
                            <label class="checkbox checkbox-outline-primary d-block">
                              <input type="checkbox" v-model="permissions" value="Orders_view">
                              <span>Orders</span>
                              <span class="checkmark"></span>
                            </label>
                          </b-col>

                          <b-col md="6" class="mb-2">
                            <label class="checkbox checkbox-outline-primary d-block">
                              <input type="checkbox" v-model="permissions" value="Collections_view">
                              <span>Collections</span>
                              <span class="checkmark"></span>
                            </label>
                          </b-col>

                          <b-col md="6" class="mb-2">
                            <label class="checkbox checkbox-outline-primary d-block">
                              <input type="checkbox" v-model="permissions" value="Banners_view">
                              <span>Banners</span>
                              <span class="checkmark"></span>
                            </label>
                          </b-col>

                           <b-col md="6" class="mb-2">
                            <label class="checkbox checkbox-outline-primary d-block">
                              <input type="checkbox" v-model="permissions" value="Subscribers_view">
                              <span>Subscribers</span>
                              <span class="checkmark"></span>
                            </label>
                          </b-col>

                           <b-col md="6" class="mb-2">
                            <label class="checkbox checkbox-outline-primary d-block">
                              <input type="checkbox" v-model="permissions" value="Messages_view">
                              <span>Messages</span>
                              <span class="checkmark"></span>
                            </label>
                          </b-col>

                        </b-row>
                      </b-card-body>
                    </b-collapse>
                  </b-card>
                </b-col>


              </b-row>
              <!-- End row -->
              
               <b-col md="12">
                <b-button variant="primary" type="submit"  :disabled="SubmitProcessing"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                  <div v-once class="typo__p" v-if="SubmitProcessing">
                    <div class="spinner sm spinner-primary mt-3"></div>
                  </div>
                </b-col>

            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>
  </div>
</template>

<script>
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Create Permissions"
  },
  data() {
    return {
      SubmitProcessing:false,
      isLoading: true,
      permissions: [],
      role: {
        name: "",
        description: ""
      }
    };
  },

  methods: {
    //------------- Submit Validation Create Permissions
    Submit_Permission() {
      this.$refs.Create_Permission.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Create_Permission();
        }
      });
    },

    //---Validate State Fields
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },
   

    //------------------------ Create Permissions -------------------\\
    Create_Permission() {
      this.SubmitProcessing = true;
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("roles", {
          role: this.role,
          permissions: this.permissions
        })
        .then(response => {
          this.SubmitProcessing = false;
          NProgress.done();
          this.makeToast(
            "success",
            this.$t("Successfully_Created"),
            this.$t("Success")
          );

          this.$router.push({
            name: "groupPermission"
          });
        })
        .catch(error => {
          this.SubmitProcessing = false;
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    }
  }, //end Methods

  created: function() {
  }
};
</script>
