<template>
  <div class="main-content">
    <breadcumb :page="$t('SystemSettings')" :folder="$t('Settings')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <!-- System Settings with Vertical Tabs -->
    <div v-if="!isLoading">
      <b-card no-body class="settings-container">
          <b-row no-gutters>
            <!-- Mobile Tab Selector (visible only on small screens) -->
            <b-col 
              cols="12" 
              class="d-md-none mobile-tab-selector"
              :class="{ 'sidebar-open': isSidebarOpenOnMobile }"
            >
              <b-form-select
                v-model="activeTab"
                :options="tabs.map(tab => ({ value: tab.id, text: tab.label }))"
                class="mobile-tab-select"
                size="lg"
              >
              </b-form-select>
            </b-col>

            <!-- Left Sidebar - Vertical Tabs (hidden on mobile) -->
            <b-col cols="12" md="3" class="settings-sidebar d-none d-md-block">
              <div class="settings-tabs-nav">
                <div class="settings-header">
                  <h5 class="mb-0">{{$t('SystemSettings')}}</h5>
                </div>
                <nav class="settings-nav">
                  <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="['settings-nav-item', { active: activeTab === tab.id }]"
                    type="button"
                  >
                    <lucide-icon :name="tab.icon" />
                    <span>{{ tab.label }}</span>
                  </button>
                </nav>
              </div>
            </b-col>

            <!-- Right Content Panel -->
            <b-col cols="12" md="9" class="settings-content">
              <div class="settings-content-wrapper">
                <div class="settings-content-header">
                  <h4 class="mb-0">{{ getActiveTabLabel() }}</h4>
                  <p class="text-muted mb-0">{{ getActiveTabDescription() }}</p>
                </div>

                <div class="settings-content-body">
                  <!-- General Settings Tab -->
                  <div v-if="activeTab === 'general'" class="tab-content">
        <validation-observer ref="generalObserver">
        <b-row>
                      <!-- Company Name -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="Company Name"
                          :rules="{ required: true}"
                          v-slot="validationContext"
                        >
                          <b-form-group :label="$t('CompanyName') + ' ' + '*'">
                            <b-form-input
                              :state="getValidationState(validationContext)"
                              aria-describedby="Company-feedback"
                              :placeholder="$t('CompanyName')"
                              v-model="setting.CompanyName"
                            ></b-form-input>
                            <b-form-invalid-feedback id="Company-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                        </validation-provider>
                  </b-col>

                      <!-- Company Phone -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="Company Phone"
                          :rules="{ required: true}"
                          v-slot="validationContext"
                        >
                          <b-form-group :label="$t('CompanyPhone') + ' ' + '*'">
                            <b-form-input
                              :state="getValidationState(validationContext)"
                              aria-describedby="Phone-feedback"
                              :placeholder="$t('CompanyPhone')"
                              v-model="setting.CompanyPhone"
                            ></b-form-input>
                            <b-form-invalid-feedback id="Phone-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                          </b-form-group>
                        </validation-provider>
                      </b-col>

                      <!-- Email -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                    <validation-provider
                      name="Email"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('DefaultEmail') + ' ' + '*'">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Email-feedback"
                          :placeholder="$t('DefaultEmail')"
                          v-model="setting.email"
                        ></b-form-input>
                            <b-form-invalid-feedback id="Email-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                      <!-- Logo -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                    <b-form-group :label="$t('ChangeLogo')">
                      <input
                        @change="onFileSelected"
                        type="file"
                        class="form-control"
                        accept="image/*"
                      >
                      <small class="text-muted d-block mt-1">Max file size: 200KB</small>
                    </b-form-group>
                  </b-col>

                      <!-- Company Address -->
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                    <validation-provider
                          name="Adress"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                          <b-form-group :label="$t('Adress') + ' ' + '*'">
                            <textarea
                              :state="getValidationState(validationContext)"
                              aria-describedby="Adress-feedback"
                              v-model="setting.CompanyAdress"
                              class="form-control"
                              :placeholder="$t('Afewwords')"
                              rows="3"
                            ></textarea>
                            <b-form-invalid-feedback id="Adress-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                          </b-form-group>
                        </validation-provider>
                      </b-col>

                      <!-- Footer -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="footer"
                          :rules="{ required: true}"
                          v-slot="validationContext"
                        >
                          <b-form-group :label="$t('footer') + ' ' + '*'">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                              aria-describedby="footer-feedback"
                              v-model="setting.footer"
                        ></b-form-input>
                            <b-form-invalid-feedback id="footer-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                      <!-- Developed By -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                    <validation-provider
                          name="developed by"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                          <b-form-group :label="$t('developed_by') + ' ' + '*'">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                              aria-describedby="developed_by-feedback"
                              v-model="setting.developed_by"
                        ></b-form-input>
                            <b-form-invalid-feedback id="developed_by-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>
                    </b-row>
                    
                    <!-- Submit Button -->
                    <b-row class="mt-4">
                      <b-col lg="12" class="d-flex justify-content-end">
                        <b-button variant="primary" size="lg" @click="Submit_General_Settings()">
{{$t('submit')}}
                        </b-button>
                      </b-col>
                    </b-row>
        </validation-observer>
                  </div>

                  <!-- Appearance Settings Tab -->
                  <div v-show="activeTab === 'appearance'" class="tab-content">
                    <!-- General Appearance -->
                    <div class="mb-4">
                      <h5 class="mb-3">{{$t('Appearance_Settings')}}</h5>
                      <b-row>
                        <!-- App Name -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <validation-provider
                            name="App Name"
                            :rules="{ required: true }"
                            v-slot="validationContext"
                          >
                            <b-form-group :label="$t('app_name') + ' *'">
                              <b-form-input
                                :state="getValidationState(validationContext)"
                                aria-describedby="app-name-feedback"
                                v-model="appearance_settings.app_name"
                              ></b-form-input>
                              <b-form-invalid-feedback id="app-name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                          </validation-provider>
                  </b-col>

                        <!-- Page Title Suffix -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <validation-provider
                            name="Page Title Suffix"
                            :rules="{ required: true }"
                            v-slot="validationContext"
                          >
                            <b-form-group :label="$t('page_title_suffix') + ' *'">
                              <b-form-input
                                :state="getValidationState(validationContext)"
                                aria-describedby="page-title-feedback"
                                v-model="appearance_settings.page_title_suffix"
                              ></b-form-input>
                              <b-form-invalid-feedback id="page-title-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                          </validation-provider>
                  </b-col>

                        <!-- Logo -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <validation-provider name="Logo" ref="AppearanceLogo" rules="mimes:image/*|size:200">
                            <b-form-group
                              slot-scope="{validate, valid, errors }"
                              :label="$t('ChangeLogo')"
                            >
                              <input
                                :state="errors[0] ? false : (valid ? true : null)"
                                :class="{'is-invalid': !!errors.length}"
                                @change="onAppearanceLogoSelected"
                                type="file"
                                class="form-control"
                              >
                              <b-form-invalid-feedback id="AppearanceLogo-feedback">{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                          </validation-provider>
                  </b-col>

                        <!-- Favicon Upload -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <validation-provider name="Favicon" ref="AppearanceFavicon" rules="mimes:image/*|size:100">
                            <b-form-group
                              slot-scope="{ validate, valid, errors }"
                              :label="$t('ChangeFavicon')"
                            >
                              <input
                                :state="errors[0] ? false : (valid ? true : null)"
                                :class="{'is-invalid': !!errors.length}"
                                @change="onAppearanceFaviconSelected"
                                type="file"
                                class="form-control"
                              >
                              <b-form-invalid-feedback id="AppearanceFavicon-feedback">{{ errors[0] }}</b-form-invalid-feedback>
                            </b-form-group>
                          </validation-provider>
                        </b-col>

                        <!-- Developed By -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                    <validation-provider
                      name="developed by"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('developed_by') + ' ' + '*'">
                         <b-form-input
                          :state="getValidationState(validationContext)"
                                aria-describedby="appearance-developed_by-feedback"
                                v-model="appearance_settings.developed_by"
                        ></b-form-input>
                              <b-form-invalid-feedback id="appearance-developed_by-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                   <!-- Footer -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                    <validation-provider
                      name="footer"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('footer') + ' ' + '*'">
                         <b-form-input
                          :state="getValidationState(validationContext)"
                                aria-describedby="appearance-footer-feedback"
                                v-model="appearance_settings.footer"
                        ></b-form-input>
                              <b-form-invalid-feedback id="appearance-footer-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                        </b-col>
                      </b-row>
                    </div>

                    <hr class="my-4">

                    <!-- Customize Button Visibility -->
                    <div class="mb-4">
                      <h5 class="mb-3">Customize Button</h5>
                      <b-row>
                        <b-col lg="12" class="mb-3">
                          <div class="d-flex align-items-center justify-content-between customize-toggle-row">
                            <div>
                              <div class="customize-toggle-title">Show the floating Customize button</div>
                              <div class="customize-toggle-hint">
                                When enabled, a Customize button appears at the bottom-right of every page so users can quickly change theme, layout, primary color and language.
                              </div>
                            </div>
                            <label class="switch switch-primary ml-3 mb-0">
                              <input
                                type="checkbox"
                                v-model="appearance_settings.customize_button_visible"
                              />
                              <span class="slider"></span>
                            </label>
                          </div>
                        </b-col>

                        <!-- Hide Site Name in Sidebar -->
                        <b-col lg="12" class="mb-3">
                          <div class="d-flex align-items-center justify-content-between customize-toggle-row">
                            <div>
                              <div class="customize-toggle-title">{{ $t('hide_site_name') }}</div>
                              <div class="customize-toggle-hint">
                                {{ $t('hide_site_name_hint') }}
                              </div>
                            </div>
                            <label class="switch switch-primary ml-3 mb-0">
                              <input
                                type="checkbox"
                                v-model="appearance_settings.hide_site_name"
                              />
                              <span class="slider"></span>
                            </label>
                          </div>
                        </b-col>
                      </b-row>
                    </div>

                    <hr class="my-4">

                    <!-- Login Page Appearance -->
                    <div class="mb-4">
                      <h5 class="mb-3">{{$t('Appearance_Settings')}} - Login Page</h5>
                      <b-row>
                        <!-- Login Hero Title -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <b-form-group label="Login hero title">
                            <b-form-input
                              v-model="appearance_settings.login_hero_title"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- Login Hero Subtitle -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <b-form-group label="Login hero subtitle">
                            <b-form-input
                              v-model="appearance_settings.login_hero_subtitle"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- Login Panel Title -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <b-form-group label="Login panel title">
                            <b-form-input
                              v-model="appearance_settings.login_panel_title"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- Login Panel Subtitle -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <b-form-group label="Login panel subtitle">
                            <b-form-input
                              v-model="appearance_settings.login_panel_subtitle"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>
                      </b-row>
                    </div>
                    
                    <!-- Submit Button -->
                    <b-row class="mt-4">
                      <b-col lg="12" class="d-flex justify-content-end">
                        <b-button variant="primary" size="lg" @click="Submit_Appearance_Settings()">
{{$t('submit')}}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>

                  <!-- Localization Tab -->
                  <div v-show="activeTab === 'localization'" class="tab-content">
                    <b-row>
                      <!-- Default Currency -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('DefaultCurrency')">
                          <v-select
                            v-model="setting.currency_id"
                            :reduce="label => label.value"
                            :placeholder="$t('Choose_Currency')"
                            :options="currencies.map(currencies => ({label: currencies.name, value: currencies.id}))"
                            :clearable="false"
                          />
                        </b-form-group>
                  </b-col>

                   <!-- Default Language -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                    <b-form-group :label="$t('DefaultLanguage')">
                      <v-select
                        v-model="setting.default_language"
                        :reduce="label => label.value"
                        :placeholder="$t('DefaultLanguage')"
                        :options="languages.map(languages => ({label: languages.name, value: languages.locale}))"
                      />
                    </b-form-group>
                  </b-col>
                  
                      <!-- Time Zone -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('Time_Zone')">
                          <v-select
                            @input="Selected_Time_Zone"
                            :placeholder="$t('Time_Zone')"
                            v-model="setting.timezone"
                            :reduce="label => label.value"
                            :options="zones_array.map(zones_array => ({label: zones_array.label, value: zones_array.zone}))"
                          />
                      </b-form-group>
                      </b-col>

                      <!-- Date Format -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('Date_Format') || 'Date Format'">
                          <v-select
                            v-model="setting.date_format"
                            :reduce="label => label.value"
                            :placeholder="$t('Date_Format') || 'Choose Date Format'"
                            :options="dateFormatOptions"
                            :clearable="false"
                          />
                        </b-form-group>
                      </b-col>

                      <!-- Price Format (frontend display only; does not affect calculations) -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('Price_Format')">
                          <b-form-select
                            v-model="setting.price_format"
                            :options="priceFormatOptions"
                            value-field="value"
                            text-field="label"
                          />
                        </b-form-group>
                      </b-col>

                      <!-- Show Languages -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('Show_Languages')">
                          <!-- Match POS Settings toggle style -->
                          <label class="switch switch-primary mr-3">
                            {{$t('Show_Languages')}}
                            <input type="checkbox" v-model="setting.show_language">
                            <span class="slider"></span>
                          </label>
                        </b-form-group>
                      </b-col>

                      <!-- Sidebar Layout -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group label="Sidebar Layout">
                          <b-form-select
                            v-model="sidebarLayoutModel"
                            :options="sidebarLayoutOptions"
                          />
                        </b-form-group>
                      </b-col>

                      <!-- Dark Mode -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('DarkMode') || 'Dark Mode'">
                          <label class="switch switch-primary mr-3">
                            {{$t('DarkMode') || 'Dark Mode'}}
                            <input type="checkbox" v-model="setting.dark_mode">
                            <span class="slider"></span>
                          </label>
                        </b-form-group>
                      </b-col>

                      <!-- RTL (Right-to-Left) -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('RTL') || 'RTL (Right-to-Left)'">
                          <label class="switch switch-primary mr-3">
                            {{$t('RTL') || 'RTL (Right-to-Left)'}}
                            <input type="checkbox" v-model="setting.rtl">
                            <span class="slider"></span>
                          </label>
                        </b-form-group>
                      </b-col>
                    </b-row>
                    
                    <!-- Submit Button -->
                    <b-row class="mt-4">
                      <b-col lg="12" class="d-flex justify-content-end">
                        <b-button variant="primary" size="lg" @click="Submit_General_Settings()">
{{$t('submit')}}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>

                  <!-- Defaults Tab -->
                  <div v-show="activeTab === 'defaults'" class="tab-content">
                    <b-row>
                  <!-- Default Customer -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                    <b-form-group :label="$t('DefaultCustomer')">
                      <v-select
                        v-model="setting.client_id"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Customer')"
                        :options="clients.map(clients => ({label: clients.name, value: clients.id}))"
                      />
                    </b-form-group>
                  </b-col>

                   <!-- Default Warehouse -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                    <b-form-group :label="$t('DefaultWarehouse')">
                      <v-select
                        v-model="setting.warehouse_id"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Warehouse')"
                        :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
                      />
                    </b-form-group>
                  </b-col>

                   <!-- Default SMS Gateway -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                    <b-form-group :label="$t('Default_SMS_Gateway')">
                      <v-select
                        v-model="setting.sms_gateway"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_SMS_Gateway')"
                        :options="sms_gateway.map(sms_gateway => ({label: sms_gateway.title, value: sms_gateway.id}))"
                      />
                    </b-form-group>
                  </b-col>

                   <!-- Default Account -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                    <b-form-group :label="$t('Default_Account')">
                      <v-select
                        v-model="setting.default_account_id"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Account')"
                        :options="accounts.map(acc => ({ label: (acc.account_name || '') + (acc.account_num ? ' (' + acc.account_num + ')' : ''), value: acc.id }))"
                      />
                    </b-form-group>
                  </b-col>

                   <!-- Default Payment Method -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                    <b-form-group :label="$t('Default_Payment_Method')">
                      <v-select
                        v-model="setting.default_payment_method_id"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Payment_Method')"
                        :options="payment_methods.map(pm => ({ label: pm.name, value: pm.id }))"
                      />
                    </b-form-group>
                  </b-col>

                      <!-- Products Per Page -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="products_per_page"
                          :rules="{ required: true}"
                          v-slot="validationContext"
                        >
                          <b-form-group  :label="$t('How_many_items_do_you_want_to_display_in_POS')">
                            <b-form-input
                              :state="getValidationState(validationContext)"
                              aria-describedby="products_per_page-feedback"
                              label="How many items do you want to display in POS."
                              placeholder="How many items do you want to display in POS."
                              v-model="pos_settings.products_per_page"
                              type="text"
                            ></b-form-input>
                            <b-form-invalid-feedback id="products_per_page-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                        </validation-provider>
                  </b-col>

                      <!-- Default Tax (moved from Tax & Pricing tab) -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="Default Tax"
                          :rules="{ regex: /^\d*\.?\d*$/}"
                          v-slot="validationContext"
                        >
                          <b-form-group :label="$t('Default_Tax') + ' (%)'">
                            <b-form-input
                              :state="getValidationState(validationContext)"
                              aria-describedby="default-tax-feedback"
                              v-model.number="setting.default_tax"
                              placeholder="0.00"
                              type="number"
                              step="0.01"
                              min="0"
                            ></b-form-input>
                            <b-form-invalid-feedback id="default-tax-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                          </b-form-group>
                        </validation-provider>
                      </b-col>

                      <!-- Point To Amount Rate (moved from Tax & Pricing tab) -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="Point To Amount Rate"
                          :rules="{ regex: /^\d*\.?\d*$/}"
                          v-slot="validationContext"
                        >
                          <b-form-group label="Point To Amount Rate * (Example: 1 Point = 0.10$)">
                            <b-input-group :append="currentUser.currency">
                              <b-form-input
                                :state="getValidationState(validationContext)"
                                aria-describedby="point-to-amount-feedback"
                                v-model.number="setting.point_to_amount_rate"
                                placeholder="Example: 1 Point = 0.10$"
                              ></b-form-input>
                            </b-input-group>
                            <b-form-invalid-feedback id="point-to-amount-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                          </b-form-group>
                        </validation-provider>
                      </b-col>

                      <!-- Quotation with Stock (moved from Invoice tab) -->
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                        <b-form-group>
                          <b-form-checkbox switch v-model="setting.quotation_with_stock">{{$t('Create_Quotation_with_Stock')}}</b-form-checkbox>
                        </b-form-group>
                      </b-col>
                    </b-row>
                    
                    <!-- Submit Button -->
                    <b-row class="mt-4">
                      <b-col lg="12" class="d-flex justify-content-end">
                        <b-button variant="primary" size="lg" @click="Submit_General_Settings()">
{{$t('submit')}}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>


                  <!-- Dashboard Settings Tab -->
                  <div v-show="activeTab === 'dashboard'" class="tab-content dashboard-settings-tab">
                    <b-row>
                      <b-col lg="6" md="6" sm="12" class="mb-4">
                        <b-form-group :label="$t('Default_Dashboard_Date_Range')">
                          <b-form-select
                            v-model="setting.default_dashboard_date_range"
                            :options="dashboardDateRangeOptions"
                            class="form-control"
                          ></b-form-select>
                          <small class="text-muted">{{ $t('Default_Dashboard_Date_Range_Help') || 'Initial date range when opening the dashboard.' }}</small>
                        </b-form-group>
                      </b-col>
                      <b-col lg="3" md="6" sm="12" class="mb-4">
                        <b-form-group :label="$t('Dashboard_Font_Size') || 'Dashboard font size'">
                          <b-form-select
                            v-model="setting.dashboard_font_size"
                            :options="dashboardFontSizeOptions"
                            class="form-control"
                          ></b-form-select>
                        </b-form-group>
                      </b-col>
                      <b-col lg="3" md="6" sm="12" class="mb-4">
                        <b-form-group :label="$t('Dashboard_Font_Family') || 'Dashboard font family'">
                          <b-form-select
                            v-model="setting.dashboard_font_family"
                            :options="dashboardFontFamilyOptions"
                            class="form-control"
                          ></b-form-select>
                        </b-form-group>
                      </b-col>
                    </b-row>
                    <b-row>
                      <b-col lg="12" class="mb-3">
                        <div class="dashboard-settings-card p-4 rounded">
                          <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                            <div>
                              <h5 class="mb-1 font-weight-bold">{{ $t('Default_Dashboard_Widget_Order') || 'Default dashboard widget order' }}</h5>
                              <p class="text-muted small mb-0">{{ $t('Dashboard_Widget_Order_Help') || 'Drag and drop to reorder sections on the default dashboard.' }}</p>
                            </div>
                            <b-button
                              variant="outline-secondary"
                              size="sm"
                              class="mt-2 mt-md-0"
                              @click="resetDashboardSectionOrder"
                            >
                              <lucide-icon class="mr-1" name="refresh-cw" />{{ $t('Reset_to_Default') || 'Reset to default' }}
                            </b-button>
                          </div>
                          <draggable
                            v-model="dashboardSectionOrderList"
                            handle=".drag-handle"
                            :animation="220"
                            ghost-class="dashboard-widget-order-ghost"
                            chosen-class="dashboard-widget-order-chosen"
                            drag-class="dashboard-widget-order-drag"
                            :force-fallback="true"
                            @end="onDashboardSectionOrderChange"
                            tag="ul"
                            class="list-unstyled dashboard-widget-order-list"
                          >
                            <li
                              v-for="(item, index) in dashboardSectionOrderList"
                              :key="item.id"
                              class="dashboard-widget-order-item"
                            >
                              <span class="drag-handle" :title="$t('Drag_to_reorder') || 'Drag to reorder'">
                                <lucide-icon name="grip-vertical" />
                              </span>
                              <span class="widget-order-number">{{ index + 1 }}</span>
                              <span class="widget-order-label">{{ $t(item.labelKey) || item.labelKey }}</span>
                            </li>
                          </draggable>
                        </div>
                      </b-col>
                    </b-row>
                    <b-row class="mt-4">
                      <b-col lg="12" class="d-flex justify-content-end">
                        <b-button variant="primary" size="lg" @click="Submit_General_Settings()">
                          {{ $t('submit') }}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>


                  <!-- Prefixes Tab -->
                  <div v-show="activeTab === 'prefixes'" class="tab-content">
                    <b-row>
                      <!-- Sale Prefix -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group label="Sale Prefix">
                          <b-form-input
                            v-model="setting.sale_prefix"
                            placeholder="SL"
                            maxlength="10"
                          ></b-form-input>
                          <small class="text-muted">Example: SL (will be prepended to sale reference numbers like SL_0001)</small>
                        </b-form-group>
                      </b-col>

                      <!-- Purchase Prefix -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group label="Purchase Prefix">
                          <b-form-input
                            v-model="setting.purchase_prefix"
                            placeholder="PR"
                            maxlength="10"
                          ></b-form-input>
                          <small class="text-muted">Example: PR (will be prepended to purchase reference numbers like PR_0001)</small>
                        </b-form-group>
                      </b-col>

                      <!-- Quotation Prefix -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group label="Quotation Prefix">
                          <b-form-input
                            v-model="setting.quotation_prefix"
                            placeholder="QT"
                            maxlength="10"
                          ></b-form-input>
                          <small class="text-muted">Example: QT (will be prepended to quotation reference numbers like QT_0001)</small>
                        </b-form-group>
                      </b-col>

                      <!-- Adjustment Prefix -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group label="Adjustment Prefix">
                          <b-form-input
                            v-model="setting.adjustment_prefix"
                            placeholder="AD"
                            maxlength="10"
                          ></b-form-input>
                          <small class="text-muted">Example: AD (will be prepended to adjustment reference numbers like AD_0001)</small>
                        </b-form-group>
                      </b-col>

                      <!-- Transfer Prefix -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group label="Transfer Prefix">
                          <b-form-input
                            v-model="setting.transfer_prefix"
                            placeholder="TR"
                            maxlength="10"
                          ></b-form-input>
                          <small class="text-muted">Example: TR (will be prepended to transfer reference numbers like TR_0001)</small>
                        </b-form-group>
                      </b-col>

                      <!-- Sale Return Prefix -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group label="Sale Return Prefix">
                          <b-form-input
                            v-model="setting.sale_return_prefix"
                            placeholder="RT"
                            maxlength="10"
                          ></b-form-input>
                          <small class="text-muted">Example: RT (will be prepended to sale return reference numbers like RT_0001)</small>
                        </b-form-group>
                      </b-col>

                      <!-- Purchase Return Prefix -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group label="Purchase Return Prefix">
                          <b-form-input
                            v-model="setting.purchase_return_prefix"
                            placeholder="RT"
                            maxlength="10"
                          ></b-form-input>
                          <small class="text-muted">Example: RT (will be prepended to purchase return reference numbers like RT_0001)</small>
                        </b-form-group>
                      </b-col>
                    </b-row>
                    
                    <b-row>
                      <b-col lg="12" class="mb-3">
                        <div class="alert alert-info">
                          <strong>Note:</strong> If a prefix is empty, the system will use the default prefix (SL for sales, PR for purchases, QT for quotations, AD for adjustments, TR for transfers, RT for returns). 
                          Prefixes only apply to newly created records.
                        </div>
                      </b-col>
                    </b-row>
                    
                    <!-- Submit Button -->
                    <b-row class="mt-4">
                      <b-col lg="12" class="d-flex justify-content-end">
                        <b-button variant="primary" size="lg" @click="Submit_General_Settings()">
{{$t('submit')}}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>

                  <!-- Mail Settings Tab -->
                  <div v-show="activeTab === 'mail'" class="tab-content">
                    <validation-observer ref="mailObserver">
                    <b-row>
                      <!-- MAIL_MAILER -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                    <validation-provider
                          name="MAIL_MAILER"
                          :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                          <b-form-group label="MAIL_MAILER *">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                              aria-describedby="MAIL_MAILER-feedback"
                              placeholder="MAIL_MAILER"
                              v-model="mail_settings.mail_mailer"
                        ></b-form-input>
                            <b-form-invalid-feedback id="MAIL_MAILER-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                            <small class="text-danger">Supported: "smtp", "sendmail", "mailgun", "ses","postmark", "log"</small>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                      <!-- MAIL_HOST -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                    <validation-provider
                          name="HOST"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                          <b-form-group label="MAIL_HOST *">
                            <b-form-input
                          :state="getValidationState(validationContext)"
                              aria-describedby="HOST-feedback"
                              placeholder="MAIL_HOST"
                              v-model="mail_settings.host"
                            ></b-form-input>
                            <b-form-invalid-feedback id="HOST-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                      <!-- MAIL_PORT -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="PORT"
                          :rules="{ required: true}"
                          v-slot="validationContext"
                        >
                          <b-form-group label="MAIL_PORT *">
                            <b-form-input
                              :state="getValidationState(validationContext)"
                              aria-describedby="PORT-feedback"
                              placeholder="MAIL_PORT"
                              v-model="mail_settings.port"
                            ></b-form-input>
                            <b-form-invalid-feedback id="PORT-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                          </b-form-group>
                        </validation-provider>
                      </b-col>

                      <!-- Sender Name -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="sender"
                          :rules="{ required: true}"
                          v-slot="validationContext"
                        >
                          <b-form-group label="Sender Name *">
                            <b-form-input
                              :state="getValidationState(validationContext)"
                              aria-describedby="sender-feedback"
                              placeholder="Sender Name"
                              v-model="mail_settings.sender_name"
                            ></b-form-input>
                            <b-form-invalid-feedback id="sender-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                          </b-form-group>
                        </validation-provider>
                      </b-col>

                      <!-- Sender Email -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="sender_email"
                          :rules="{ required: true, email: true}"
                          v-slot="validationContext"
                        >
                          <b-form-group label="Sender Email *">
                            <b-form-input
                              type="email"
                              :state="getValidationState(validationContext)"
                              aria-describedby="sender_email-feedback"
                              placeholder="Sender Email"
                              v-model="mail_settings.sender_email"
                            ></b-form-input>
                            <b-form-invalid-feedback id="sender_email-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                          </b-form-group>
                        </validation-provider>
                      </b-col>

                      <!-- MAIL_USERNAME -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="Username"
                          :rules="{ required: true}"
                          v-slot="validationContext"
                        >
                          <b-form-group label="MAIL_USERNAME *">
                            <b-form-input
                              :state="getValidationState(validationContext)"
                              aria-describedby="Username-feedback"
                              placeholder="MAIL_USERNAME"
                              v-model="mail_settings.username"
                            ></b-form-input>
                            <b-form-invalid-feedback id="Username-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                          </b-form-group>
                        </validation-provider>
                      </b-col>

                      <!-- MAIL_PASSWORD -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="Password"
                          :rules="{ required: true}"
                          v-slot="validationContext"
                        >
                          <b-form-group label="MAIL_PASSWORD *">
                            <b-form-input
                              type="password"
                              :state="getValidationState(validationContext)"
                              aria-describedby="Password-feedback"
                              placeholder="MAIL_PASSWORD"
                              v-model="mail_settings.password"
                            ></b-form-input>
                            <b-form-invalid-feedback id="Password-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                          </b-form-group>
                        </validation-provider>
                      </b-col>

                      <!-- MAIL_ENCRYPTION -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <validation-provider
                          name="encryption"
                          :rules="{ required: true}"
                          v-slot="validationContext"
                        >
                          <b-form-group label="MAIL_ENCRYPTION *">
                            <b-form-input
                              :state="getValidationState(validationContext)"
                              aria-describedby="encryption-feedback"
                              placeholder="MAIL_ENCRYPTION"
                              v-model="mail_settings.encryption"
                            ></b-form-input>
                            <b-form-invalid-feedback id="encryption-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                          </b-form-group>
                        </validation-provider>
                      </b-col>

                      <!-- Submit and Test Buttons -->
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                        <b-form-group class="d-flex align-items-center gap-2">
                          <b-button variant="primary" @click="Update_Mail_Settings()">
                            {{$t('submit')}}
                          </b-button>
                          <b-button
                            variant="outline-secondary"
                            :disabled="isTestingMail"
                            @click="Test_Mail_Settings()"
                          >
                            <span v-if="!isTestingMail">
                              Save & Test Mail
                            </span>
                            <span v-else>
                              {{$t('Loading')}}...
                            </span>
                          </b-button>
                        </b-form-group>
                      </b-col>
                    </b-row>
                    </validation-observer>
                  </div>

                  <!-- Payment Gateway Tab -->
                  <div v-show="activeTab === 'payment'" class="tab-content">
                    <b-row>
                      <!-- Stripe Key -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group label="STRIPE_KEY">
                          <b-form-input
                            type="password"
                            v-model="gateway.stripe_key"
                            :placeholder="$t('LeaveBlank')"
                          ></b-form-input>
                          <small class="text-muted">{{$t('LeaveBlank')}} {{$t('to_keep_current_value')}}</small>
                        </b-form-group>
                </b-col>

                      <!-- Stripe Secret -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group label="STRIPE_SECRET">
                          <b-form-input
                            type="password"
                            v-model="gateway.stripe_secret"
                            :placeholder="$t('LeaveBlank')"
                          ></b-form-input>
                          <small class="text-muted">{{$t('LeaveBlank')}} {{$t('to_keep_current_value')}}</small>
                        </b-form-group>
                </b-col>

                      <!-- Remove Stripe Key & Secret -->
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                    <b-form-group>
                          <b-form-checkbox switch v-model="gateway.deleted">
                            {{$t('Remove_Stripe_Key_Secret')}}
                          </b-form-checkbox>
                          <small class="text-muted d-block mt-1">{{$t('Enable_to_remove_stored_credentials')}}</small>
                    </b-form-group>
                  </b-col>
                    </b-row>
                    
                    <!-- Submit Button -->
                    <b-row class="mt-4">
                      <b-col lg="12" class="d-flex justify-content-end">
                        <b-button variant="primary" size="lg" @click="Update_Payment()">
{{$t('submit')}}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>

                  <!-- SMS Settings Tab -->
                  <div v-show="activeTab === 'sms'" class="tab-content">
                    <!-- Default SMS Gateway -->
                    <div class="sms-section mb-4">
                      <h5 class="mb-3">{{$t('Default_SMS_Gateway')}}</h5>
                      <b-row>
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <b-form-group :label="$t('Default_SMS_Gateway')">
                            <v-select
                              v-model="sms_settings.default_sms_gateway"
                              :reduce="label => label.value"
                              :placeholder="$t('Choose_SMS_Gateway')"
                              :options="sms_settings.sms_gateway.map(gateway => ({label: gateway.title, value: gateway.id}))"
                            />
                          </b-form-group>
                  </b-col>
                      </b-row>
                      <b-row>
                        <b-col lg="12" class="mb-3">
                          <b-button variant="primary" @click="Update_Default_SMS()">
                            {{$t('submit')}}
                          </b-button>
                        </b-col>
                      </b-row>
                    </div>

                    <hr class="my-4">

                    <!-- Termii SMS API -->
                    <div class="sms-section mb-4">
                      <h5 class="mb-3">Termii</h5>
                      <b-row>
                        <!-- Termii KEY -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <validation-provider
                            name="TERMI_KEY"
                            :rules="{ required: true}"
                            v-slot="validationContext"
                          >
                            <b-form-group label="Termii KEY *">
                              <b-form-input
                                :state="getValidationState(validationContext)"
                                aria-describedby="TERMI_KEY-feedback"
                                v-model="sms_settings.termi.TERMI_KEY"
                              ></b-form-input>
                              <b-form-invalid-feedback id="TERMI_KEY-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                            </b-form-group>
                          </validation-provider>
                        </b-col>

                        <!-- TERMI_SECRET -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <validation-provider
                            name="TERMI_SECRET"
                            :rules="{ required: true}"
                            v-slot="validationContext"
                          >
                            <b-form-group label="Termii SECRET *">
                              <b-form-input
                                :state="getValidationState(validationContext)"
                                aria-describedby="TERMI_SECRET-feedback"
                                v-model="sms_settings.termi.TERMI_SECRET"
                              ></b-form-input>
                              <b-form-invalid-feedback id="TERMI_SECRET-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                            </b-form-group>
                          </validation-provider>
                        </b-col>

                        <!-- TERMI_SENDER -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <validation-provider
                            name="TERMI_SENDER"
                            :rules="{ required: true}"
                            v-slot="validationContext"
                          >
                            <b-form-group label="Termii Sender *">
                              <b-form-input
                                :state="getValidationState(validationContext)"
                                aria-describedby="TERMI_SENDER-feedback"
                                v-model="sms_settings.termi.TERMI_SENDER"
                              ></b-form-input>
                              <b-form-invalid-feedback id="TERMI_SENDER-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                            </b-form-group>
                          </validation-provider>
                        </b-col>
                      </b-row>
                      <b-row>
                        <b-col lg="12" class="mb-3">
                          <b-button variant="primary" @click="Update_Termi_SMS()">
                            {{$t('submit')}}
                          </b-button>
                        </b-col>
                      </b-row>
                    </div>

                    <hr class="my-4">

                    <!-- Twilio SMS API -->
                    <div class="sms-section mb-4">
                      <h5 class="mb-3">TWILIO SMS</h5>
                      <b-row>
                        <!-- TWILIO_SID -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <validation-provider
                            name="TWILIO_SID"
                            :rules="{ required: true}"
                            v-slot="validationContext"
                          >
                            <b-form-group label="TWILIO SID *">
                              <b-form-input
                                :state="getValidationState(validationContext)"
                                aria-describedby="TWILIO_SID-feedback"
                                v-model="sms_settings.twilio.TWILIO_SID"
                              ></b-form-input>
                              <b-form-invalid-feedback id="TWILIO_SID-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                            </b-form-group>
                          </validation-provider>
                        </b-col>

                        <!-- TWILIO_TOKEN -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <b-form-group label="TWILIO TOKEN *">
                            <b-form-input
                              v-model="sms_settings.twilio.TWILIO_TOKEN"
                              :placeholder="$t('LeaveBlank')"
                            ></b-form-input>
                            <small class="text-muted">{{$t('LeaveBlank')}} {{$t('to_keep_current_value')}}</small>
                          </b-form-group>
                        </b-col>

                        <!-- TWILIO_FROM -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <validation-provider
                            name="TWILIO_FROM"
                            :rules="{ required: true}"
                            v-slot="validationContext"
                          >
                            <b-form-group label="TWILIO FROM *">
                              <b-form-input
                                :state="getValidationState(validationContext)"
                                aria-describedby="TWILIO_FROM-feedback"
                                v-model="sms_settings.twilio.TWILIO_FROM"
                              ></b-form-input>
                              <b-form-invalid-feedback id="TWILIO_FROM-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                            </b-form-group>
                          </validation-provider>
                        </b-col>
                      </b-row>
                      <b-row>
                        <b-col lg="12" class="mb-3">
                          <b-button variant="primary" @click="Update_Twilio_SMS()">
                            {{$t('submit')}}
                          </b-button>
                        </b-col>
                      </b-row>
                    </div>

                    <hr class="my-4">

                    <!-- Infobip SMS API -->
                    <div class="sms-section mb-4">
                      <h5 class="mb-3">InfoBip</h5>
                      <b-row>
                        <!-- BASE_URL -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <b-form-group label="BASE URL">
                            <b-form-input
                              v-model="sms_settings.infobip.base_url"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- API_KEY -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <b-form-group label="API KEY">
                            <b-form-input
                              v-model="sms_settings.infobip.api_key"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- SMS Sender From -->
                        <b-col lg="6" md="6" sm="12" class="mb-3">
                          <b-form-group label="SMS sender number Or Name">
                            <b-form-input
                              v-model="sms_settings.infobip.sender_from"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>
                      </b-row>
                      <b-row>
                        <b-col lg="12" class="mb-3">
                          <b-button variant="primary" @click="Update_Infobip_SMS()">
                            {{$t('submit')}}
                          </b-button>
                        </b-col>
                      </b-row>
                      <b-row class="mt-3">
                        <b-col lg="12">
                          <div class="info-box p-3 bg-light rounded">
                            <p class="mb-2"><strong>BASE_URL :</strong> The Infobip data center used for API traffic.</p>
                            <p class="mb-2"><strong>API_KEY :</strong> Authentication method. See API documentation</p>
                            <p class="mb-2"><strong>SMS sender number Or Name :</strong> displayed on recipient's device as message sender.</p>
                            <p class="mb-0"><strong>API Reference:</strong> <a href="https://www.infobip.com/docs/api" target="_blank">https://www.infobip.com/docs/api</a></p>
                          </div>
                        </b-col>
                      </b-row>
                    </div>

                    <!-- Custom SMS Gateway -->
                    <div class="sms-section mb-4">
                      <h5 class="mb-3">{{$t('Custom_SMS_Gateway')}}</h5>

                      <b-row>
                        <!-- API URL -->
                        <b-col lg="8" md="8" sm="12" class="mb-3">
                          <b-form-group :label="$t('Custom_SMS_Api_Url') + ' *'">
                            <b-form-input
                              v-model="sms_settings.custom.api_url"
                              placeholder="https://api.provider.com/sms/send"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- HTTP Method -->
                        <b-col lg="4" md="4" sm="12" class="mb-3">
                          <b-form-group :label="$t('Custom_SMS_Method')">
                            <v-select
                              v-model="sms_settings.custom.method"
                              :clearable="false"
                              :options="['POST','GET','PUT']"
                            />
                          </b-form-group>
                        </b-col>

                        <!-- Content Type -->
                        <b-col lg="4" md="4" sm="12" class="mb-3">
                          <b-form-group :label="$t('Custom_SMS_Content_Type')">
                            <v-select
                              v-model="sms_settings.custom.content_type"
                              :clearable="false"
                              :options="['json','form']"
                            />
                          </b-form-group>
                        </b-col>

                        <!-- Sender -->
                        <b-col lg="4" md="4" sm="12" class="mb-3">
                          <b-form-group :label="$t('Custom_SMS_Sender')">
                            <b-form-input
                              v-model="sms_settings.custom.sender"
                              placeholder="Sender ID or phone"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- Success Keyword -->
                        <b-col lg="4" md="4" sm="12" class="mb-3">
                          <b-form-group :label="$t('Custom_SMS_Success_Keyword')">
                            <b-form-input
                              v-model="sms_settings.custom.success_keyword"
                              placeholder="e.g. success"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- Headers rows -->
                        <b-col md="12">
                          <label class="font-weight-bold mt-2">{{ $t('Custom_SMS_Headers') }}</label>
                          <b-row
                            v-for="(row, idx) in sms_settings.customHeaderRows"
                            :key="'sys-h-'+idx"
                            class="align-items-center"
                          >
                            <b-col lg="5" md="5" sm="12" class="mb-2">
                              <b-form-input
                                v-model="row.key"
                                placeholder="Header name (e.g. Authorization)"
                              ></b-form-input>
                            </b-col>
                            <b-col lg="6" md="6" sm="12" class="mb-2">
                              <b-form-input
                                v-model="row.value"
                                placeholder="Header value (e.g. Bearer xxx)"
                              ></b-form-input>
                            </b-col>
                            <b-col lg="1" md="1" sm="12" class="mb-2">
                              <b-button variant="outline-danger" size="sm" @click="sms_removeHeaderRow(idx)">
                                <lucide-icon name="x" />
                              </b-button>
                            </b-col>
                          </b-row>
                          <b-button variant="outline-primary" size="sm" @click="sms_addHeaderRow" class="mb-3">
                            <lucide-icon name="plus" /> {{ $t('Custom_SMS_Add_Header') }}
                          </b-button>
                        </b-col>

                        <!-- Payload rows -->
                        <b-col md="12">
                          <label class="font-weight-bold mt-2">{{ $t('Custom_SMS_Payload') }}</label>
                          <p class="text-muted small">{{ $t('Custom_SMS_Payload_Hint') }}</p>
                          <b-row
                            v-for="(row, idx) in sms_settings.customPayloadRows"
                            :key="'sys-p-'+idx"
                            class="align-items-center"
                          >
                            <b-col lg="5" md="5" sm="12" class="mb-2">
                              <b-form-input
                                v-model="row.key"
                                placeholder="Field name (e.g. to)"
                              ></b-form-input>
                            </b-col>
                            <b-col lg="6" md="6" sm="12" class="mb-2">
                              <b-form-input
                                v-model="row.value"
                                placeholder="Value (e.g. {phone})"
                              ></b-form-input>
                            </b-col>
                            <b-col lg="1" md="1" sm="12" class="mb-2">
                              <b-button variant="outline-danger" size="sm" @click="sms_removePayloadRow(idx)">
                                <lucide-icon name="x" />
                              </b-button>
                            </b-col>
                          </b-row>
                          <b-button variant="outline-primary" size="sm" @click="sms_addPayloadRow" class="mb-3">
                            <lucide-icon name="plus" /> {{ $t('Custom_SMS_Add_Field') }}
                          </b-button>
                        </b-col>
                      </b-row>

                      <b-row>
                        <b-col lg="12" class="mb-3">
                          <b-button variant="primary" @click="Update_Custom_SMS()">
                            {{$t('submit')}}
                          </b-button>
                        </b-col>
                      </b-row>

                      <b-row class="mt-3">
                        <b-col lg="12">
                          <div class="info-box p-3 bg-light rounded">
                            <p class="mb-0">
                              <strong>{{ $t('Custom_SMS_Placeholders') }}:</strong>
                              <code>{phone}</code>, <code>{message}</code>, <code>{sender}</code>
                            </p>
                          </div>
                        </b-col>
                      </b-row>
                    </div>
                  </div>

                  <!-- POS Settings Tab -->
                  <div v-show="activeTab === 'pos'" class="tab-content">
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

                  <!-- Select default POS layout (same value, for clarity) -->
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
                        </div>
                      </div>
                    </b-card>
                  </b-col>
                      <!-- Note to Customer -->
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
                            <b-form-invalid-feedback id="note-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                          </b-form-group>
                        </validation-provider>
                      </b-col>
                      <!-- Show Logo -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Logo')}}
                          <input type="checkbox" v-model="pos_settings.show_logo">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Store Name -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Store_Name')}}
                          <input type="checkbox" v-model="pos_settings.show_store_name">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Reference -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Reference')}}
                          <input type="checkbox" v-model="pos_settings.show_reference">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Date -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Date')}}
                          <input type="checkbox" v-model="pos_settings.show_date">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Seller -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Seller')}}
                          <input type="checkbox" v-model="pos_settings.show_seller">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Phone -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Phone')}}
                          <input type="checkbox" v-model="pos_settings.show_phone">
                          <span class="slider"></span>
                      </label>
                      </b-col>

                      <!-- Show Address -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Address')}}
                          <input type="checkbox" v-model="pos_settings.show_address">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Email -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Email')}}
                          <input type="checkbox" v-model="pos_settings.show_email">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Customer -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Customer')}}
                          <input type="checkbox" v-model="pos_settings.show_customer">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Warehouse -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Warehouse')}}
                          <input type="checkbox" v-model="pos_settings.show_Warehouse">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Tax -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Tax')}}
                          <input type="checkbox" v-model="pos_settings.show_tax">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Discount -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Discount')}}
                          <input type="checkbox" v-model="pos_settings.show_discount">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Shipping -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Shipping')}}
                          <input type="checkbox" v-model="pos_settings.show_shipping">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Barcode -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_barcode')}}
                          <input type="checkbox" v-model="pos_settings.show_barcode">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Note to Customer -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Note_to_customer')}}
                          <input type="checkbox" v-model="pos_settings.show_note">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Paid line -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Paid_Line')}}
                          <input type="checkbox" v-model="pos_settings.show_paid">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Due line -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Due_Line')}}
                          <input type="checkbox" v-model="pos_settings.show_due">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show Payments table -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Payments_Table')}}
                          <input type="checkbox" v-model="pos_settings.show_payments">
                          <span class="slider"></span>
                        </label>
                      </b-col>

                      <!-- Show ZATCA QR -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_ZATCA_QR')}}
                          <input type="checkbox" v-model="pos_settings.show_zatca_qr">
                          <span class="slider"></span>
                        </label>
                      </b-col>
                    </b-row>

                    <!-- Footer: Receipt Paper Size and Logo Size -->
                    <b-row class="mt-4">
                      <b-col md="12" class="mb-3">
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
                    </b-row>
                    
                    <!-- Submit Button -->
                    <b-row class="mt-4">
                      <b-col lg="12" class="d-flex justify-content-end">
                        <b-button variant="primary" size="lg" @click="Submit_POS_Settings()">
{{$t('submit')}}
                        </b-button>
                      </b-col>
                    </b-row>
                    </div>

                  <!-- POS Settings Tab -->
                  <div v-show="activeTab === 'pos_settings'" class="tab-content">
                    <b-row>
                      <!-- Quick Add Customer -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Quick_Add_Customer')}}
                          <input type="checkbox" v-model="pos_settings.quick_add_customer">
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">{{$t('Enable_Quick_Add_Customer_popup_in_POS')}}</small>
                  </b-col>

                      <!-- Barcode Scanning Sound -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Barcode_Scanning_Sound')}}
                          <input type="checkbox" v-model="pos_settings.barcode_scanning_sound">
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">{{$t('Enable_sound_when_scanning_barcodes_in_POS')}}</small>
                      </b-col>

                      <!-- Show Product Images in POS -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Product_Images_in_POS')}}
                          <input type="checkbox" v-model="pos_settings.show_product_images">
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">{{$t('Show_hide_product_images_in_POS_product_listing')}}</small>
                      </b-col>

                      <!-- Show Stock Quantity in POS -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Stock_Quantity_in_POS')}}
                          <input type="checkbox" v-model="pos_settings.show_stock_quantity">
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">{{$t('Show_hide_stock_quantity_in_POS')}}</small>
                      </b-col>

                      <!-- Enable Print Invoice automatically -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Enable_Print_Invoice')}}
                          <input type="checkbox" v-model="pos_settings.is_printable">
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">{{$t('Enable_Print_Invoice_help')}}</small>
                      </b-col>

                     

                      <!-- Enable Hold Sales -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Enable_Hold_Sales')}}
                          <input type="checkbox" v-model="pos_settings.enable_hold_sales">
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">{{$t('Enable_disable_Hold_Sales_feature_in_POS')}}</small>
                      </b-col>

                      <!-- Enable Customer Points in POS -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Enable_Customer_Points_in_POS')}}
                          <input type="checkbox" v-model="pos_settings.enable_customer_points">
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">{{$t('Enable_disable_customer_points_system_in_POS')}}</small>
                      </b-col>

                      <!-- Show Categories in POS -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Categories_in_POS')}}
                          <input type="checkbox" v-model="pos_settings.show_categories">
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">{{$t('Show_hide_categories_in_POS')}}</small>
                      </b-col>

                      <!-- Show Brands in POS -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Show_Brands_in_POS')}}
                          <input type="checkbox" v-model="pos_settings.show_brands">
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">{{$t('Show_hide_brands_in_POS')}}</small>
                      </b-col>

                      <!-- Allow Overselling: when ON, POS lets cashiers add and complete sales
                           even when stock is zero or negative. When OFF (default), strict
                           stock checks remain in effect. -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Allow_Overselling') || 'Allow Overselling'}}
                          <input type="checkbox" v-model="pos_settings.allow_overselling">
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">
                          {{$t('Allow_Overselling_Help') || 'When enabled, the POS allows selling products even when stock is zero or negative. Stock can go negative after the sale.'}}
                        </small>
                      </b-col>

                      <!-- Enable Keyboard Shortcuts in POS (per-device, stored in localStorage) -->
                      <b-col md="4" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{$t('Enable_Keyboard_Shortcuts') || 'Enable Keyboard Shortcuts'}}
                          <input type="checkbox" v-model="enable_keyboard_shortcuts" @change="onToggleKeyboardShortcuts">
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">
                          {{$t('Enable_Keyboard_Shortcuts_Help') || 'Per-device setting. In the POS press Shift + ? at any time to view shortcuts.'}}
                          <a href="#" class="ml-1" @click.prevent="$bvModal.show('pos-shortcuts-guide')">
                            <lucide-icon name="info" />
                            {{$t('View_Shortcuts') || 'View shortcuts'}}
                          </a>
                        </small>
                      </b-col>

                       <!-- Invoice format: Thermal vs A4 (shortcut from POS Settings tab) -->
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                        <b-form-group :label="$t('Invoice_Format')">
                          <b-form-radio-group
                            v-model="setting.invoice_format"
                            :options="invoiceFormatOptions.map(opt => ({ value: opt.value, text: $t(opt.textKey) }))"
                            buttons
                            button-variant="outline-primary"
                            size="sm"
                          />
                          <small class="text-muted d-block mt-1">
                            {{ $t('Invoice_Format_help') }}
                          </small>
                        </b-form-group>
                      </b-col>

                      <!-- Cash drawer auto-open (QZ Tray + ESC/POS) -->
                      <b-col md="12" class="mt-4 mb-2">
                        <hr class="my-4">
                        <h6 class="mb-3">{{ $t('Cash_Drawer_Settings') }}</h6>
                        <b-alert show variant="light" class="small">
                          {{ $t('Cash_Drawer_Auto_Open_Help') }}
                        </b-alert>
                      </b-col>
                      <b-col md="6" class="mt-3 mb-3">
                        <label class="switch switch-primary mr-3">
                          {{ $t('Cash_Drawer_Auto_Open') }}
                          <input
                            type="checkbox"
                            v-model="pos_settings.cash_drawer_auto_open"
                            :true-value="true"
                            :false-value="false"
                          >
                          <span class="slider"></span>
                        </label>
                      </b-col>
                      <b-col md="6" class="mt-3 mb-3">
                        <b-form-group :label="$t('Cash_Drawer_Printer_Name')">
                          <b-form-input
                            v-model="pos_settings.cash_drawer_printer_name"
                            :placeholder="$t('Leave_blank_for_default_receipt_printer')"
                            maxlength="192"
                          />
                          <small class="text-muted">{{ $t('Cash_Drawer_Printer_Name_Help') }}</small>
                        </b-form-group>
                      </b-col>

                    </b-row>

                    <!-- Submit Button -->
                    <b-row class="mt-4">
                      <b-col lg="12" class="d-flex justify-content-end">
                        <b-button variant="primary" size="lg" @click="Submit_POS_Settings()">
                          {{$t('submit')}}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>

                  <!-- Cash drawer Tab -->
                  <div v-show="activeTab === 'cash_drawer'" class="tab-content">
                    <b-row>
                      <b-col cols="12" class="mb-4">
                        <b-alert show variant="light" class="small">
                          {{ $t('Cash_Drawer_Auto_Open_Help') }}
                        </b-alert>
                      </b-col>
                      <b-col md="6" class="mb-3">
                        <label class="switch switch-primary mr-3">
                          {{ $t('Cash_Drawer_Auto_Open') }}
                          <input
                            type="checkbox"
                            v-model="pos_settings.cash_drawer_auto_open"
                            :true-value="true"
                            :false-value="false"
                          >
                          <span class="slider"></span>
                        </label>
                      </b-col>
                      <b-col md="6" class="mb-3">
                        <b-form-group :label="$t('Cash_Drawer_Printer_Name')">
                          <b-form-input
                            v-model="pos_settings.cash_drawer_printer_name"
                            :placeholder="$t('Leave_blank_for_default_receipt_printer')"
                            maxlength="192"
                          />
                          <small class="text-muted">{{ $t('Cash_Drawer_Printer_Name_Help') }}</small>
                        </b-form-group>
                      </b-col>
                      <b-col lg="12" class="mt-4">
                        <b-button variant="primary" size="lg" @click="Submit_POS_Settings()">
                          {{ $t('submit') }}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>

                  <!-- Direct Network Printing Tab -->
                  <div v-show="activeTab === 'direct_network_printing'" class="tab-content">
                    <b-row>
                      <b-col cols="12" class="mb-4">
                        <b-alert show variant="light" class="small">
                          {{ $t('Direct_Network_Printing_Help') || 'Send receipts directly to a network thermal printer over RAW/JetDirect (default port 9100). Leave this OFF to keep using the existing browser/OS print flow.' }}
                        </b-alert>
                      </b-col>

                      <b-col md="6" class="mb-3">
                        <label class="switch switch-primary mr-3">
                          {{ $t('Enable_Direct_Network_Printing') || 'Enable Direct Network Printing' }}
                          <input
                            type="checkbox"
                            v-model="pos_settings.direct_network_printing"
                            :true-value="true"
                            :false-value="false"
                          >
                          <span class="slider"></span>
                        </label>
                        <small class="text-muted d-block mt-2">
                          {{ $t('Enable_Direct_Network_Printing_Hint') || 'When disabled, existing printing behavior is unchanged.' }}
                        </small>
                      </b-col>

                      <b-col md="6" class="mb-3"></b-col>

                      <b-col md="6" class="mb-3">
                        <b-form-group :label="$t('Network_Printer_IP') || 'Network Printer IP Address'">
                          <b-form-input
                            v-model="pos_settings.network_printer_ip"
                            placeholder="192.168.1.100"
                            maxlength="64"
                            :disabled="!pos_settings.direct_network_printing"
                          />
                          <small class="text-muted">{{ $t('Network_Printer_IP_Help') || 'IPv4/IPv6 address or hostname of the printer on your local network.' }}</small>
                        </b-form-group>
                      </b-col>

                      <b-col md="6" class="mb-3">
                        <b-form-group :label="$t('Network_Printer_Port') || 'Network Printer Port'">
                          <b-form-input
                            type="number"
                            min="1"
                            max="65535"
                            v-model.number="pos_settings.network_printer_port"
                            placeholder="9100"
                            :disabled="!pos_settings.direct_network_printing"
                          />
                          <small class="text-muted">{{ $t('Network_Printer_Port_Help') || 'Default RAW / JetDirect port is 9100.' }}</small>
                        </b-form-group>
                      </b-col>

                      <b-col lg="12" class="mt-4">
                        <b-button variant="primary" size="lg" @click="Submit_POS_Settings()">
                          {{ $t('submit') }}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>

                  <!-- ZATCA Tab -->
                  <div v-show="activeTab === 'zatca'" class="tab-content">
                    <b-row>
                      <!-- Company Name Arabic -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('CompanyNameArabic')">
                          <b-form-input v-model="setting.company_name_ar" :placeholder="$t('Optional')"></b-form-input>
                        </b-form-group>
                      </b-col>

                      <!-- VAT Number -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('VAT_Number')">
                          <b-form-input v-model="setting.vat_number"></b-form-input>
                        </b-form-group>
                      </b-col>

                      <!-- ZATCA Enabled -->
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                        <b-form-group :label="$t('ZATCA_Enabled')">
                          <b-form-checkbox switch v-model="setting.zatca_enabled">{{$t('Enable_ZATCA_QR_on_Sales_Receipts')}}</b-form-checkbox>
                        </b-form-group>
                      </b-col>
                    </b-row>
                    
                    <!-- Submit Button -->
                    <b-row class="mt-4">
                      <b-col lg="12" class="d-flex justify-content-end">
                        <b-button variant="primary" size="lg" @click="Submit_General_Settings()">
{{$t('submit')}}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>

                  <!-- Invoice Tab -->
                  <div v-show="activeTab === 'invoice'" class="tab-content">
                    <b-row>
                      <!-- Invoice format: Thermal vs A4 -->
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                        <b-form-group :label="$t('Invoice_Format')">
                          <b-form-radio-group
                            v-model="setting.invoice_format"
                            :options="invoiceFormatOptions.map(opt => ({ value: opt.value, text: $t(opt.textKey) }))"
                            buttons
                            button-variant="outline-primary"
                            size="sm"
                          />
                          <small class="text-muted d-block mt-1">
                            {{ $t('Invoice_Format_help') }}
                          </small>
                        </b-form-group>
                      </b-col>

                      <!-- A4 Invoice Logo Size -->
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                        <b-card no-body class="mb-0">
                          <b-card-body>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                              <h5 class="mb-0">{{ $t('A4_Invoice_Logo_Size') }}</h5>
                            </div>
                            <small class="text-muted d-block mb-3">
                              {{ $t('A4_Invoice_Logo_Size_help') }}
                            </small>
                            <b-row>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group :label="$t('Logo_Width') + ' (px)'">
                                  <b-input-group append="px">
                                    <b-form-input
                                      type="number"
                                      min="20"
                                      max="600"
                                      step="1"
                                      v-model.number="setting.invoice_logo_width"
                                      @input="onInvoiceLogoWidthInput"
                                    />
                                  </b-input-group>
                                  <b-form-input
                                    type="range"
                                    min="20"
                                    max="600"
                                    step="1"
                                    v-model.number="setting.invoice_logo_width"
                                    class="mt-2"
                                  />
                                </b-form-group>
                              </b-col>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group :label="$t('Logo_Height') + ' (px)'">
                                  <b-input-group append="px">
                                    <b-form-input
                                      type="number"
                                      min="20"
                                      max="400"
                                      step="1"
                                      v-model.number="setting.invoice_logo_height"
                                      @input="onInvoiceLogoHeightInput"
                                    />
                                  </b-input-group>
                                  <b-form-input
                                    type="range"
                                    min="20"
                                    max="400"
                                    step="1"
                                    v-model.number="setting.invoice_logo_height"
                                    class="mt-2"
                                  />
                                </b-form-group>
                              </b-col>
                            </b-row>

                            <!-- Live preview using current logo and chosen dimensions -->
                            <b-row v-if="setting.logo">
                              <b-col cols="12">
                                <div class="text-muted small mb-2">{{ $t('Preview') }}</div>
                                <div class="invoice-logo-preview-box">
                                  <img
                                    :src="'/images/' + setting.logo"
                                    alt="Logo preview"
                                    :style="{
                                      maxWidth: (setting.invoice_logo_width || 180) + 'px',
                                      maxHeight: (setting.invoice_logo_height || 60) + 'px',
                                      width: 'auto',
                                      height: 'auto',
                                      objectFit: 'contain'
                                    }"
                                  />
                                </div>
                              </b-col>
                            </b-row>
                          </b-card-body>
                        </b-card>
                      </b-col>

                      <!-- Invoice Footer Toggle -->
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                        <b-form-group>
                          <b-form-checkbox switch v-model="setting.is_invoice_footer">{{$t('invoice_footer')}}</b-form-checkbox>
                        </b-form-group>
                      </b-col>

                      <!-- Invoice Footer Text -->
                      <b-col lg="12" md="12" sm="12" class="mb-3" v-if="setting.is_invoice_footer">
                  <validation-provider
                      name="invoice_footer"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('invoice_footer') + ' ' + '*'">
                         <textarea
                          :state="getValidationState(validationContext)"
                          aria-describedby="invoice_footer-feedback invoice_footer-help"
                          v-model="setting.invoice_footer"
                          class="form-control"
                          :placeholder="$t('invoice_footer')"
                              rows="4"
                         ></textarea>
                            <b-form-text id="invoice_footer-help">{{ $t('invoice_footer_a4_help') || 'This footer is only used on the Invoice A4 PDF.' }}</b-form-text>
                            <b-form-invalid-feedback id="invoice_footer-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                </b-col>
                    </b-row>
                    
                    <!-- Submit Button -->
                    <b-row class="mt-4">
                      <b-col lg="12" class="d-flex justify-content-end">
                        <b-button variant="primary" size="lg" @click="Submit_General_Settings()">
{{$t('submit')}}
                        </b-button>
                </b-col>
                    </b-row>
                  </div>

                  <!-- Backup Tab -->
                  <div v-show="activeTab === 'backup'" class="tab-content">
                    <b-row>
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                        <b-alert v-if="backupDestination === 'local'" show variant="warning" class="mb-3">
                          <strong>{{$t('You_will_find_your_backup_on')}}</strong> <code>/storage/app/public/backup</code> {{$t('and_save_it_to_your_pc')}}
                        </b-alert>
                        <b-alert v-else show variant="info" class="mb-3">
                          Cloud: backups will be uploaded to the selected provider after they are generated locally.
                        </b-alert>
                        <small v-if="backupDestination === 'cloud'" class="text-muted d-block">Note: the list below shows local backups.</small>
                  </b-col>
                </b-row>

                    <!-- Backup destination (clear + simple) -->
                    <b-row class="mb-4">
                      <b-col lg="12" md="12" sm="12">
                        <b-card no-body class="mb-0">
                          <b-card-body>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                              <h5 class="mb-0">Backup destination</h5>
                            </div>

                            <b-row>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Destination">
                                  <b-form-radio-group
                                    v-model="backupDestination"
                                    :options="[
                                      { value: 'local', text: 'Local only' },
                                      { value: 'cloud', text: 'Cloud (upload after local backup)' },
                                    ]"
                                    stacked
                                  />
                                  <small class="text-muted d-block mt-1">
                                    Local backups path: <code>/storage/app/public/backup</code>.
                                  </small>
                                </b-form-group>
                              </b-col>

                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Cloud path / folder (optional)" v-if="backupDestination === 'cloud'">
                                  <b-form-input
                                    v-model="setting.backup_cloud_path"
                                    placeholder="e.g. StockyBackups/"
                                  />
                                </b-form-group>
                              </b-col>
                            </b-row>

                            <b-row v-if="backupDestination === 'cloud'">
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Cloud provider">
                                  <b-form-select
                                    v-model="setting.backup_cloud_provider"
                                    :options="[
                                      { value: null, text: 'Select provider' },
                                      { value: 'google_drive', text: 'Google Drive' },
                                      { value: 'dropbox', text: 'Dropbox' },
                                      { value: 's3', text: 'S3-compatible (AWS/MinIO/etc.)' },
                                    ]"
                                  />
                                  <small class="text-muted d-block mt-1">
                                    Cloud upload runs after the backup is generated locally.
                                  </small>
                                </b-form-group>
                              </b-col>
                            </b-row>

                            <!-- S3 fields -->
                            <b-row v-if="backupDestination === 'cloud' && setting.backup_cloud_provider === 's3'">
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Bucket">
                                  <b-form-input v-model="setting.backup_s3_bucket" placeholder="Bucket name" />
                                </b-form-group>
                              </b-col>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Region">
                                  <b-form-input v-model="setting.backup_s3_region" placeholder="e.g. us-east-1" />
                                </b-form-group>
                              </b-col>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Access key">
                                  <b-form-input v-model="setting.backup_s3_access_key" placeholder="Access key" />
                                </b-form-group>
                              </b-col>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Secret key (leave blank to keep current)">
                                  <b-form-input type="text" v-model="setting.backup_s3_secret_key" placeholder="Secret key" />
                                </b-form-group>
                              </b-col>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Endpoint (optional for MinIO)">
                                  <b-form-input v-model="setting.backup_s3_endpoint" placeholder="e.g. https://minio.example.com" />
                                </b-form-group>
                              </b-col>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Path-style URLs (MinIO often requires this)">
                                  <b-form-checkbox switch v-model="setting.backup_s3_path_style">Enable</b-form-checkbox>
                                </b-form-group>
                              </b-col>
                            </b-row>

                            <!-- Google Drive fields -->
                            <b-row v-if="backupDestination === 'cloud' && setting.backup_cloud_provider === 'google_drive'">
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Folder ID (optional)">
                                  <b-form-input v-model="setting.backup_gdrive_folder_id" placeholder="Google Drive folder id" />
                                </b-form-group>
                              </b-col>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Access token (optional, short-lived)">
                                  <b-form-input type="text" v-model="setting.backup_gdrive_access_token" placeholder="Bearer token" />
                                </b-form-group>
                              </b-col>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Refresh token (recommended)">
                                  <b-form-input type="text" v-model="setting.backup_gdrive_refresh_token" placeholder="Refresh token" />
                                </b-form-group>
                              </b-col>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Client ID">
                                  <b-form-input v-model="setting.backup_gdrive_client_id" placeholder="OAuth client id" />
                                </b-form-group>
                              </b-col>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Client secret (leave blank to keep current)">
                                  <b-form-input type="text" v-model="setting.backup_gdrive_client_secret" placeholder="OAuth client secret" />
                                </b-form-group>
                              </b-col>
                            </b-row>

                            <!-- Dropbox fields -->
                            <b-row v-if="backupDestination === 'cloud' && setting.backup_cloud_provider === 'dropbox'">
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Dropbox folder path (optional)">
                                  <b-form-input v-model="setting.backup_dropbox_path" placeholder="e.g. /StockyBackups" />
                                </b-form-group>
                              </b-col>
                              <b-col lg="6" md="6" sm="12" class="mb-3">
                                <b-form-group label="Access token (leave blank to keep current)">
                                  <b-form-input type="text" v-model="setting.backup_dropbox_access_token" placeholder="Dropbox token" />
                                </b-form-group>
                              </b-col>
                            </b-row>

                            <div class="d-flex justify-content-end">
                              <b-button variant="primary" @click="Submit_General_Settings()">
                                Save backup settings
                              </b-button>
                            </div>
                          </b-card-body>
                        </b-card>
                      </b-col>
                    </b-row>
                    
                    <b-row>
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                          <h5 class="mb-0">{{$t('BackupDatabase')}}</h5>
                          <b-button
                            @click="GenerateBackup()"
                            variant="primary"
                            class="btn-generate-backup"
                          >
                            <lucide-icon class="me-2" name="plus" />{{$t('GenerateBackup')}}
                          </b-button>
                        </div>

                        <b-alert v-if="backupError" show variant="danger" dismissible @dismissed="backupError = null" class="mb-3">
                          <h6 class="alert-heading">Backup Configuration Required</h6>
                          <p class="mb-2"><strong>mysqldump not found.</strong> Please configure DUMP_PATH in your .env file.</p>
                          <p class="mb-2"><strong>For Laragon on Windows:</strong></p>
                          <ol class="mb-2 pl-3">
                            <li>Open your <code>.env</code> file in the project root</li>
                            <li>Find your MySQL version folder in <code>C:\laragon\bin\mysql\</code></li>
                            <li>Add this line (replace with your actual version):</li>
                          </ol>
                          <pre class="bg-light p-2 mb-2"><code>DUMP_PATH="C:\\laragon\\bin\\mysql\\mysql-8.0.30\\bin\\mysqldump.exe"</code></pre>
                          <p class="mb-0">Or use forward slashes: <code>DUMP_PATH="C:/laragon/bin/mysql/mysql-8.0.30/bin/mysqldump.exe"</code></p>
                          <p class="mb-0 mt-2"><small>After updating .env, run: <code>php artisan config:clear</code></small></p>
                        </b-alert>

                        <div class="backup-table-wrapper">
                          <vue-good-table
                            v-if="backups.length > 0"
                            mode="remote"
                            :columns="backupColumns"
                            :totalRows="totalRows"
                            :rows="backups"
                            styleClass="table-hover tableOne vgt-table"
                          >
                            <template slot="table-row" slot-scope="props">
                              <span v-if="props.column.field == 'actions'">
                                <b-button
                                  variant="danger"
                                  size="sm"
                                  @click="DeleteBackup(props.row.date)"
                                  class="btn-delete-backup"
                                >
                                  <lucide-icon name="x" />
                                </b-button>
                              </span>
                            </template>
                          </vue-good-table>
                          
                          <div v-else class="text-center py-5 text-muted">
                            <lucide-icon class="text-50 mb-3 d-block" name="database-backup" />
                            <p>{{$t('No_backups_found')}}</p>
                            <p class="small">{{$t('Click_Generate_Backup_to_create_your_first_backup')}}</p>
                          </div>
                        </div>
          </b-col>
        </b-row>
                  </div>

                  <!-- Security Settings Tab -->
                  <div v-show="activeTab === 'security'" class="tab-content">
                    <b-row>
                      <b-col lg="12" md="12" sm="12">
                        <div class="system-actions-card">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                              <h5 class="mb-1">Login Device Management</h5>
                              <p class="text-muted mb-0">
                                Active login sessions for your user (per device / browser).
                              </p>
                            </div>
                            <div class="d-flex">
                              <b-button variant="outline-primary" class="mr-2" @click="LoadSecuritySessions()" :disabled="securitySessionsLoading || securitySessionsActionLoading">
                                Refresh
                              </b-button>
                              <b-button variant="danger" @click="LogoutAllOtherDevices()" :disabled="securitySessionsLoading || securitySessionsActionLoading || !hasOtherSessions">
                                Logout All Other Devices
                              </b-button>
                            </div>
                          </div>

                          <div v-if="securitySessionsLoading" class="py-4 text-center text-muted">
                            <div class="spinner spinner-primary mr-3"></div>
                          </div>

                          <b-table
                            v-else
                            :items="securitySessions"
                            :fields="securitySessionFields"
                            responsive="sm"
                            small
                            class="mt-3"
                            show-empty
                            empty-text="No active sessions found."
                          >
                            <template #cell(device)="row">
                              <div class="d-flex align-items-center">
                                <span>{{ row.item.device }}</span>
                                <b-badge v-if="row.item.is_current" variant="success" class="ms-2">Current</b-badge>
                              </div>
                            </template>

                            <template #cell(ip_address)="row">
                              <span>{{ row.item.ip_address || '-' }}</span>
                            </template>

                            <template #cell(login_at)="row">
                              <span>{{ formatDateTime(row.item.login_at) }}</span>
                            </template>

                            <template #cell(last_activity_at)="row">
                              <span>{{ row.item.last_activity_at ? formatDateTime(row.item.last_activity_at) : '-' }}</span>
                            </template>

                            <template #cell(actions)="row">
                              <b-button
                                size="sm"
                                variant="danger"
                                @click="LogoutSession(row.item.token_id)"
                                :disabled="securitySessionsLoading || securitySessionsActionLoading || row.item.is_current"
                              >
                                Logout
                              </b-button>
                            </template>
                          </b-table>
                        </div>
                      </b-col>
                    </b-row>
                  </div>

                  <!-- System Tab -->
                  <div v-show="activeTab === 'system'" class="tab-content">
                <b-row>
                      <!-- Debug Mode -->
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <div class="system-actions-card">
                          <h5 class="mb-3">{{$t('DebugMode') || 'Debug Mode'}}</h5>
                          <label class="switch switch-primary mr-3">
                            {{$t('DebugMode') || 'Debug Mode'}}
                            <input type="checkbox" v-model="setting.debug_mode">
                            <span class="slider"></span>
                          </label>
                          <div class="mt-3">
                            <b-button variant="primary" @click="Submit_General_Settings()">
                              {{$t('submit')}}
                            </b-button>
                          </div>
                        </div>
                      </b-col>

                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <div class="system-actions-card">
                          <h5 class="mb-3">{{$t('Clear_Cache')}}</h5>
                          <b-button variant="primary" @click="Clear_Cache()" class="action-btn-system">
                            <lucide-icon class="me-2" name="refresh-cw" />{{$t('Clear_Cache')}}
                          </b-button>
                        </div>
                  </b-col>
                </b-row>
                  </div>

                  <!-- Pharmacy Settings Tab -->
                  <div v-show="activeTab === 'pharmacy'" class="tab-content">
                    <b-alert show variant="warning" v-if="setting.pharmacy_mode_supported === false">
                      {{ $t('Pharmacy_Settings_Migration_Missing') || 'Pharmacy mode columns not found on the settings table. Run the pharmacy migration to enable these options.' }}
                    </b-alert>

                    <b-row v-else>
                      <b-col lg="12" md="12" sm="12" class="mb-3">
                        <div class="system-actions-card">
                          <h5 class="mb-2">{{ $t('Pharmacy_Mode') }}</h5>
                          <p class="text-muted small">
                            {{ $t('Track_Batches_Expiry_Help') }}
                          </p>
                          <label class="switch switch-primary mr-3">
                            {{ $t('Track_Batches_Expiry') }}
                            <input type="checkbox" v-model="setting.pharmacy_mode">
                            <span class="slider"></span>
                          </label>
                        </div>
                      </b-col>

                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('Expiry_Warning_Days')">
                          <b-form-input
                            type="number"
                            min="0"
                            max="3650"
                            v-model.number="setting.expiry_warning_days"
                            :disabled="!setting.pharmacy_mode"
                          ></b-form-input>
                          <small class="text-muted">{{ $t('Expiry_Warning_Days_Help') || 'Batches expiring within this number of days are flagged as near-expiry.' }}</small>
                        </b-form-group>
                      </b-col>

                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <div class="system-actions-card">
                          <label class="switch switch-primary mr-3">
                            {{ $t('Block_Expired_Sale') }}
                            <input
                              type="checkbox"
                              v-model="setting.block_expired_sale"
                              :disabled="!setting.pharmacy_mode"
                            >
                            <span class="slider"></span>
                          </label>
                          <p class="text-muted small mt-2 mb-0">
                            {{ $t('Block_Expired_Sale_Help') || 'Prevent POS / sale from accepting batches with an expiry date in the past.' }}
                          </p>
                        </div>
                      </b-col>

                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <div class="system-actions-card">
                          <label class="switch switch-primary mr-3">
                            {{ $t('Print_Expiry_On_Receipt') }}
                            <input
                              type="checkbox"
                              v-model="setting.print_expiry_on_receipt"
                              :disabled="!setting.pharmacy_mode"
                            >
                            <span class="slider"></span>
                          </label>
                          <p class="text-muted small mt-2 mb-0">
                            {{ $t('Print_Expiry_On_Receipt_Help') || 'Include batch number and expiry date next to each line on POS receipts.' }}
                          </p>
                        </div>
                      </b-col>

                      <b-col lg="12" md="12" sm="12" class="mt-2">
                        <b-button variant="primary" @click="Update_Settings()">
                          <lucide-icon class="me-2" name="check" /> {{ $t('submit') }}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>

                  <!-- Custom Fields Tab -->
                  <div v-show="activeTab === 'custom_fields'" class="tab-content">
                    <b-tabs v-model="customFieldsActiveTab" content-class="mt-3">
                      <!-- Customers Custom Fields Tab -->
                      <b-tab :title="$t('Customers')">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                          <h5>{{ $t('CustomerCustomFields') || 'Customer Custom Fields' }}</h5>
                          <b-button variant="primary" @click="New_CustomField('client')">
                            <lucide-icon name="plus" /> {{ $t('Add') }}
                          </b-button>
                        </div>

                        <vue-good-table
                          :key="'customer-fields-' + customFieldsTableKey"
                          :columns="customFieldsColumns"
                          :rows="customerFields"
                          :rtl="direction"
                          :sort-options="{
                            enabled: true,
                            initialSortBy: { field: 'sort_order', type: 'asc' }
                          }"
                          :search-options="{
                            enabled: true,
                            placeholder: $t('SearchThisTable')
                          }"
                          :pagination-options="{
                            enabled: true,
                            mode: 'records',
                            perPage: 10
                          }"
                          styleClass="tableOne vgt-table"
                        >
                          <template slot="table-row" slot-scope="props">
                            <span v-if="props.column.field == 'field_type'">
                              {{ getFieldTypeLabel(props.row.field_type) }}
                            </span>
                            <span v-else-if="props.column.field == 'is_required'">
                              <b-badge :variant="props.row.is_required ? 'success' : 'secondary'">
                                {{ props.row.is_required ? $t('Required') : $t('Optional') }}
                              </b-badge>
                            </span>
                            <span v-else-if="props.column.field == 'is_active'">
                              <b-badge :variant="props.row.is_active ? 'success' : 'danger'">
                                {{ props.row.is_active ? $t('Enabled') : $t('Disabled') }}
                              </b-badge>
                            </span>
                            <span v-else-if="props.column.field == 'actions'">
                              <b-button
                                variant="outline-primary"
                                size="sm"
                                @click="Edit_CustomField(props.row)"
                                class="mr-2"
                              >
                                <lucide-icon name="pencil" />
                              </b-button>
                              <b-button
                                variant="outline-danger"
                                size="sm"
                                @click="Delete_CustomField(props.row.id)"
                              >
                                <lucide-icon name="x" />
                              </b-button>
                            </span>
                            <span v-else>
                              {{ props.formattedRow[props.column.field] }}
                            </span>
                          </template>
                        </vue-good-table>
                      </b-tab>

                      <!-- Suppliers Custom Fields Tab -->
                      <b-tab :title="$t('Suppliers')">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                          <h5>{{ $t('SupplierCustomFields') || 'Supplier Custom Fields' }}</h5>
                          <b-button variant="primary" @click="New_CustomField('provider')">
                            <lucide-icon name="plus" /> {{ $t('Add') }}
                          </b-button>
                        </div>

                        <vue-good-table
                          :key="'supplier-fields-' + customFieldsTableKey"
                          :columns="customFieldsColumns"
                          :rows="supplierFields"
                          :rtl="direction"
                          :sort-options="{
                            enabled: true,
                            initialSortBy: { field: 'sort_order', type: 'asc' }
                          }"
                          :search-options="{
                            enabled: true,
                            placeholder: $t('SearchThisTable')
                          }"
                          :pagination-options="{
                            enabled: true,
                            mode: 'records',
                            perPage: 10
                          }"
                          styleClass="tableOne vgt-table"
                        >
                          <template slot="table-row" slot-scope="props">
                            <span v-if="props.column.field == 'field_type'">
                              {{ getFieldTypeLabel(props.row.field_type) }}
                            </span>
                            <span v-else-if="props.column.field == 'is_required'">
                              <b-badge :variant="props.row.is_required ? 'success' : 'secondary'">
                                {{ props.row.is_required ? $t('Required') : $t('Optional') }}
                              </b-badge>
                            </span>
                            <span v-else-if="props.column.field == 'is_active'">
                              <b-badge :variant="props.row.is_active ? 'success' : 'danger'">
                                {{ props.row.is_active ? $t('Enabled') : $t('Disabled') }}
                              </b-badge>
                            </span>
                            <span v-else-if="props.column.field == 'actions'">
                              <b-button
                                variant="outline-primary"
                                size="sm"
                                @click="Edit_CustomField(props.row)"
                                class="mr-2"
                              >
                                <lucide-icon name="pencil" />
                              </b-button>
                              <b-button
                                variant="outline-danger"
                                size="sm"
                                @click="Delete_CustomField(props.row.id)"
                              >
                                <lucide-icon name="x" />
                              </b-button>
                            </span>
                            <span v-else>
                              {{ props.formattedRow[props.column.field] }}
                            </span>
                          </template>
                        </vue-good-table>
                      </b-tab>
                    </b-tabs>

                    <!-- Modal Add/Edit Custom Field -->
                    <validation-observer ref="Create_CustomField">
                      <b-modal
                        hide-footer
                        size="lg"
                        :id="customFieldEditmode ? 'Edit_CustomField' : 'New_CustomField'"
                        :title="customFieldEditmode ? $t('Edit') : $t('Add')"
                      >
                        <b-form @submit.prevent="Submit_CustomField">
                          <b-row>
                            <!-- Field Name -->
                            <b-col md="12" sm="12" class="mb-3">
                              <validation-provider
                                name="Field Name"
                                :rules="{ required: true }"
                                v-slot="validationContext"
                              >
                                <b-form-group :label="$t('FieldName') + ' ' + '*'">
                                  <b-form-input
                                    :state="getValidationState(validationContext)"
                                    aria-describedby="name-feedback"
                                    :placeholder="$t('FieldName')"
                                    v-model="customField.name"
                                  ></b-form-input>
                                  <b-form-invalid-feedback id="name-feedback">
                                    {{ validationContext.errors[0] }}
                                  </b-form-invalid-feedback>
                                </b-form-group>
                              </validation-provider>
                            </b-col>

                            <!-- Field Type -->
                            <b-col md="6" sm="12" class="mb-3">
                              <validation-provider
                                name="Field Type"
                                :rules="{ required: true }"
                                v-slot="validationContext"
                              >
                                <b-form-group :label="$t('FieldType') + ' ' + '*'">
                                  <v-select
                                    :class="{'is-invalid': !!validationContext.errors[0]}"
                                    :state="validationContext.errors[0] ? false : (validationContext.valid ? true : null)"
                                    v-model="customField.field_type"
                                    :reduce="label => label.value"
                                    :options="fieldTypes"
                                    :placeholder="$t('PleaseSelect')"
                                    @input="onFieldTypeChange"
                                  ></v-select>
                                  <b-form-invalid-feedback>
                                    {{ validationContext.errors[0] }}
                                  </b-form-invalid-feedback>
                                </b-form-group>
                              </validation-provider>
                            </b-col>

                            <!-- Required -->
                            <b-col md="6" sm="12" class="mb-3">
                              <b-form-group :label="$t('Required')">
                                <b-form-checkbox v-model="customField.is_required" switch>
                                  {{ customField.is_required ? $t('Required') : $t('Optional') }}
                                </b-form-checkbox>
                              </b-form-group>
                            </b-col>

                            <!-- Default Value / Select Options -->
                            <b-col md="12" sm="12" class="mb-3" v-if="customField.field_type === 'select'">
                              <b-form-group :label="$t('SelectOptions')">
                                <b-form-textarea
                                  v-model="selectOptionsText"
                                  :placeholder="$t('EnterOptionsOnePerLine') || 'Enter options, one per line'"
                                  rows="4"
                                  @blur="updateSelectOptions"
                                ></b-form-textarea>
                                <small class="text-muted">{{ $t('EnterOptionsOnePerLine') || 'Enter options, one per line' }}</small>
                              </b-form-group>
                            </b-col>

                            <b-col md="12" sm="12" class="mb-3" v-else-if="customField.field_type !== 'select' && customField.field_type">
                              <b-form-group :label="$t('DefaultValue')">
                                <b-form-input
                                  v-if="customField.field_type === 'text' || customField.field_type === 'number'"
                                  v-model="customField.default_value"
                                  :type="customField.field_type === 'number' ? 'number' : 'text'"
                                  :placeholder="$t('DefaultValue')"
                                ></b-form-input>
                                <b-form-textarea
                                  v-else-if="customField.field_type === 'textarea'"
                                  v-model="customField.default_value"
                                  :placeholder="$t('DefaultValue')"
                                  rows="3"
                                ></b-form-textarea>
                                <b-form-datepicker
                                  v-else-if="customField.field_type === 'date'"
                                  v-model="customField.default_value"
                                  :placeholder="$t('DefaultValue')"
                                ></b-form-datepicker>
                              </b-form-group>
                            </b-col>

                            <!-- Enable/Disable -->
                            <b-col md="6" sm="12" class="mb-3">
                              <b-form-group :label="$t('Status')">
                                <b-form-checkbox v-model="customField.is_active" switch>
                                  {{ customField.is_active ? $t('Enabled') : $t('Disabled') }}
                                </b-form-checkbox>
                              </b-form-group>
                            </b-col>

                            <b-col md="12" class="mt-3">
                              <b-button
                                variant="primary"
                                type="submit"
                                :disabled="customFieldSubmitProcessing"
                              >
                                <lucide-icon class="me-2 font-weight-bold" name="check" /> {{ $t('submit') }}
                              </b-button>
                              <b-button
                                variant="secondary"
                                @click="reset_CustomField_Form"
                                class="ml-2"
                              >
                                {{ $t('Cancel') }}
                              </b-button>
                              <div v-once class="typo__p" v-if="customFieldSubmitProcessing">
                                <div class="spinner sm spinner-primary mt-3"></div>
                              </div>
                            </b-col>
                          </b-row>
                        </b-form>
                      </b-modal>
                    </validation-observer>
                  </div>
                </div>
              </div>

                  <!-- Google Calendar Tab -->
                  <div v-show="activeTab === 'calendar'" class="tab-content">
                    <b-row>
                      <b-col lg="12" class="mb-3">
                        <p class="text-muted">
                          {{ $t('Google_Calendar_Booking_Description') || 'When a booking is confirmed, an event is created in your Google Calendar with date/time, service, customer details, and reminders. Enter your Google API credentials below and connect your account.' }}
                        </p>
                      </b-col>
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('Client_ID') || 'Client ID'">
                          <b-form-input v-model="calendarForm.google_calendar_client_id" placeholder="xxxxx.apps.googleusercontent.com" type="text" autocomplete="off"/>
                          <small class="text-muted d-block mt-1">{{ $t('Google_Calendar_Client_ID_Help') || 'From Google Cloud Console → APIs & Services → Credentials.' }}</small>
                        </b-form-group>
                      </b-col>
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('Client_Secret') || 'Client Secret'">
                          <b-form-input v-model="calendarForm.google_calendar_client_secret" :placeholder="calendarForm.google_calendar_client_secret_set ? '********' : ''" type="password" autocomplete="new-password"/>
                          <small class="text-muted d-block mt-1">{{ $t('Google_Calendar_Client_Secret_Help') || 'Leave blank to keep existing secret.' }}</small>
                        </b-form-group>
                      </b-col>
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('Redirect_URI') || 'Redirect URI'">
                          <b-form-input v-model="calendarForm.google_calendar_redirect_uri" placeholder="https://yourdomain.com/google-calendar/callback" type="url"/>
                          <small class="text-muted d-block mt-1">{{ $t('Google_Calendar_Redirect_URI_Help') || 'Must match the URI configured in Google Cloud Console. Leave blank to use default.' }}</small>
                        </b-form-group>
                      </b-col>
                      <b-col lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('Status') || 'Status'">
                          <span v-if="calendarForm.google_calendar_connected" class="text-success">
                            <lucide-icon name="check" /> {{ $t('Connected') || 'Connected' }}
                          </span>
                          <span v-else class="text-muted">{{ $t('Not_connected') || 'Not connected' }}</span>
                          <div class="mt-2">
                            <a v-if="!calendarForm.google_calendar_connected" :href="calendarForm.google_calendar_connect_url" class="btn btn-sm btn-outline-primary">
                              {{ $t('Connect_Google_Calendar') || 'Connect Google Calendar' }}
                            </a>
                            <a v-else :href="calendarForm.google_calendar_disconnect_url" class="btn btn-sm btn-outline-secondary">
                              {{ $t('Disconnect') || 'Disconnect' }}
                            </a>
                          </div>
                        </b-form-group>
                      </b-col>
                      <b-col v-if="calendarForm.google_calendar_connected" lg="6" md="6" sm="12" class="mb-3">
                        <b-form-group :label="$t('Calendar_ID') || 'Calendar ID'">
                          <b-form-input v-model="calendarForm.google_calendar_calendar_id" placeholder="primary"/>
                          <small class="text-muted d-block mt-1">{{ $t('Leave_primary_for_default') || 'Leave as "primary" for your main calendar.' }}</small>
                        </b-form-group>
                      </b-col>
                      <b-col lg="12" class="mb-3">
                        <b-button variant="primary" :disabled="calendarSaving" @click="Submit_Calendar_Settings()">
                          <span v-if="calendarSaving" class="spinner-border spinner-border-sm mr-2"></span>
                          <lucide-icon name="check" /> {{ $t('Save') }}
                        </b-button>
                      </b-col>
                    </b-row>
                  </div>

          </b-col>
        </b-row>
        </b-card>

        <!-- POS Keyboard Shortcuts Guide (read-only reference) -->
        <b-modal
          id="pos-shortcuts-guide"
          :title="$t('POS_Keyboard_Shortcuts') || 'POS Keyboard Shortcuts'"
          size="md"
          ok-only
          :ok-title="$t('Close') || 'Close'"
          ok-variant="secondary"
        >
          <p class="text-muted small mb-3">
            {{$t('Shortcuts_Guide_Intro') || 'These shortcuts are available on the POS screen when “Enable Keyboard Shortcuts” is ON. They are ignored while typing in form fields (except F-keys and Esc).'}}
          </p>
          <table class="table table-sm table-striped mb-0">
            <thead>
              <tr>
                <th style="width:45%">{{$t('Shortcut') || 'Shortcut'}}</th>
                <th>{{$t('Action') || 'Action'}}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in posShortcutsList" :key="s.id">
                <td><kbd>{{ s.keys }}</kbd></td>
                <td>{{ $t(s.descriptionKey) || s.descriptionFallback }}</td>
              </tr>
            </tbody>
          </table>
        </b-modal>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapActions, mapGetters } from "vuex";
import { cachePriceFormat } from "../../../../utils/priceFormat";
import draggable from "vuedraggable";
import { posShortcutsEnabled, setPosShortcutsEnabled, POS_SHORTCUTS } from "../../../../mixins/posKeyboardShortcuts";

export default {
  components: { draggable },
  metaInfo: {
    title: "System Settings"
  },
  data() {
    return {
      activeTab: 'general',
      isLoading: true,
      data: new FormData(),
      settings: [],
      currencies: [],
      clients: [],
      warehouses: [],
      sms_gateway: [],
      accounts: [],
      payment_methods: [],
      zones_array:[],
      languages:[],
      sidebarLayoutOptions: [
        { value: 'horizontal', text: 'Sidebar 1' },
        { value: 'vertical', text: 'Sidebar 2' },
      ],
      dateFormatOptions: [
        { label: 'DD/MM/YYYY', value: 'DD/MM/YYYY' },
        { label: 'MM/DD/YYYY', value: 'MM/DD/YYYY' },
        { label: 'YYYY-MM-DD', value: 'YYYY-MM-DD' },
      ],
      dashboardDateRangeOptions: [
        { value: 'today', text: 'Today' },
        { value: 'week', text: 'This Week' },
        { value: 'month', text: 'This Month' },
      ],
      dashboardFontSizeOptions: [
        { value: '', text: 'Default' },
        { value: '12', text: '12px (Small)' },
        { value: '14', text: '14px (Medium)' },
        { value: '16', text: '16px (Large)' },
        { value: '18', text: '18px (Extra large)' },
      ],
      dashboardFontFamilyOptions: [
        { value: '', text: 'System default' },
        { value: 'inherit', text: 'Inherit' },
        { value: 'Arial, sans-serif', text: 'Arial' },
        { value: 'Georgia, serif', text: 'Georgia' },
        { value: '"Times New Roman", Times, serif', text: 'Times New Roman' },
        { value: 'Verdana, Geneva, sans-serif', text: 'Verdana' },
        { value: 'Tahoma, Geneva, sans-serif', text: 'Tahoma' },
        { value: '"Segoe UI", Tahoma, sans-serif', text: 'Segoe UI' },
        { value: 'system-ui, -apple-system, sans-serif', text: 'System UI' },
      ],
      defaultDashboardSections: [
        { id: 'header', labelKey: 'Dashboard_Header' },
        { id: 'stat_cards_1', labelKey: 'Dashboard_Stat_Cards_1' },
        { id: 'stat_cards_2', labelKey: 'Dashboard_Stat_Cards_2' },
        { id: 'chart_sales_purchases', labelKey: 'Dashboard_Chart_Sales_Purchases' },
        { id: 'chart_top_selling', labelKey: 'Dashboard_Chart_Top_Selling' },
        { id: 'sales_by_payment_stock_value', labelKey: 'Dashboard_Sales_By_Payment_Stock' },
        { id: 'chart_payment_sent_received', labelKey: 'Dashboard_Chart_Payment_Sent_Received' },
        { id: 'chart_top_customers', labelKey: 'Dashboard_Chart_Top_Customers' },
        { id: 'table_stock_alert', labelKey: 'StockAlert' },
        { id: 'table_top_selling_products', labelKey: 'Top_Selling_Products' },
        { id: 'table_recent_sales', labelKey: 'Recent_Sales' },
      ],
      dashboardSectionOrderList: [],
      // Invoice format options for POS printing
      invoiceFormatOptions: [
        { value: 'thermal', textKey: 'Invoice_Thermal' },
        { value: 'a4', textKey: 'Invoice_A4' },
      ],
      // Price format options for frontend display (POS, etc.)
      priceFormatOptions: [
        {
          label: "1,234.56 (thousand , decimal .)",
          value: "comma_dot",
        },
        {
          label: "1.234,56 (thousand . decimal ,)",
          value: "dot_comma",
        },
        {
          label: "1 234,56 (thousand space, decimal ,)",
          value: "space_comma",
        },
      ],
      setting: {
        client_id: "",
        warehouse_id: "",
        default_account_id: "",
        default_payment_method_id: "",
        currency_id: "",
        email: "",
        logo: "",
        CompanyName: "",
        CompanyPhone: "",
        CompanyAdress: "",
        footer:"",
        developed_by:"",
        default_language:"",
        date_format: 'YYYY-MM-DD',
        // Optional price format for frontend display
        price_format: "",
        sms_gateway:"",
        is_invoice_footer:'',
        invoice_footer:'',
        quotation_with_stock:'',
        show_language:'',
        point_to_amount_rate:'',
        default_tax: 0,
        default_dashboard_date_range: 'week',
        dashboard_section_order: null,
        dashboard_font_size: '',
        dashboard_font_family: '',
        dark_mode: false,
        rtl: false,
        debug_mode: false,
        sale_prefix: '',
        purchase_prefix: '',
        quotation_prefix: '',
        adjustment_prefix: '',
        transfer_prefix: '',
        sale_return_prefix: '',
        purchase_return_prefix: '',
        // ZATCA (Fatoorah)
        company_name_ar:'',
        vat_number:'',
        zatca_enabled:false,
        // Invoice format for POS printing ('thermal' or 'a4')
        invoice_format: 'thermal',
        // A4 invoice logo dimensions (pixels)
        invoice_logo_width: 180,
        invoice_logo_height: 60,


        // Security: inactivity auto-logout (minutes) - null means disabled
        session_timeout_minutes: null,

        // Optional cloud backup destination (local backup remains default)
        backup_cloud_enabled: false,
        backup_cloud_provider: null,
        backup_cloud_path: "",

        // S3-compatible
        backup_s3_bucket: "",
        backup_s3_region: "",
        backup_s3_access_key: "",
        backup_s3_secret_key: "",
        backup_s3_endpoint: "",
        backup_s3_path_style: false,

        // Google Drive
        backup_gdrive_folder_id: "",
        backup_gdrive_access_token: "",
        backup_gdrive_refresh_token: "",
        backup_gdrive_client_id: "",
        backup_gdrive_client_secret: "",

        // Dropbox
        backup_dropbox_path: "",
        backup_dropbox_access_token: "",

        // Flags (populated by API) to show if secrets are already saved (but hidden)
        backup_s3_has_secret_key: false,
        backup_gdrive_has_access_token: false,
        backup_gdrive_has_refresh_token: false,
        backup_gdrive_has_client_secret: false,
        backup_dropbox_has_access_token: false,

        // Pharmacy mode (batch & expiry tracking)
        pharmacy_mode_supported: true,
        pharmacy_mode: false,
        expiry_warning_days: 90,
        block_expired_sale: false,
        print_expiry_on_receipt: false,
      },
      // Custom Fields data
      customFieldsActiveTab: 0,
      customerFields: [],
      supplierFields: [],
      customFieldEditmode: false,
      customFieldSubmitProcessing: false,
      customField: {
        id: "",
        name: "",
        field_type: "",
        entity_type: "",
        is_required: false,
        default_value: "",
        sort_order: 0,
      },
      calendarForm: {
        google_calendar_connected: false,
        google_calendar_connect_url: "",
        google_calendar_disconnect_url: "",
        google_calendar_client_id: "",
        google_calendar_client_secret: "",
        google_calendar_client_secret_set: false,
        google_calendar_redirect_uri: "",
        google_calendar_calendar_id: "",
      },
      calendarSaving: false,
      selectOptionsText: "",
      gateway: {
        stripe_key: "",
        stripe_secret: "",
        deleted: false,
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
        show_tax: 0,
        show_shipping: 0,
        show_phone: "",
        show_email: "",
        show_address: "",
        show_customer: "",
        show_Warehouse: "",
        is_printable: '',
        products_per_page: '',
        quick_add_customer: false,
        barcode_scanning_sound: false,
        show_product_images: false,
        show_stock_quantity: false,
        enable_hold_sales: false,
        enable_customer_points: false,
        show_categories: false,
        show_brands: false,
        allow_overselling: false,
        receipt_layout: 1,
        show_paid: "",
        show_due: "",
        show_payments: "",
        show_zatca_qr: "",
        receipt_paper_size: 80,
        cash_drawer_auto_open: false,
        cash_drawer_printer_name: "",
        direct_network_printing: false,
        network_printer_ip: "",
        network_printer_port: 9100,
      },
      enable_keyboard_shortcuts: false,
      logoSizeType: 'medium', // Track the selected logo size type
      sms_settings: {
        sms_gateway: [],
        default_sms_gateway: '',
        twilio: {
          TWILIO_SID: '',
          TWILIO_TOKEN: '',
          TWILIO_FROM: '',
        },
        termi: {
          TERMI_KEY: '',
          TERMI_SECRET: '',
          TERMI_SENDER: '',
        },
        infobip: {
          base_url: '',
          api_key: '',
          sender_from: '',
        },
        custom: {
          api_url: '',
          method: 'POST',
          content_type: 'json',
          sender: '',
          success_keyword: '',
          headers: {},
          payload: {},
        },
        customHeaderRows: [],
        customPayloadRows: [],
      },
      appearance_settings: {
        logo: "",
        favicon: "",
        footer: "",
        app_name: "",
        page_title_suffix: "",
        developed_by: "",
        customize_button_visible: true,
        hide_site_name: false,
        login_hero_title: "",
        login_hero_subtitle: "",
        login_panel_title: "",
        login_panel_subtitle: "",
      },
      appearance_data: new FormData(),
      mail_settings: {
        host: "",
        port: "",
        username: "",
        password: "",
        encryption: "",
        sender_name: "",
        sender_email: "",
        mail_mailer: "",
      },
      isTestingMail: false,
      backups: [],
      backupError: null,
      totalRows: 0,

      // Security tab
      securitySessions: [],
      securitySessionsLoading: false,
      securitySessionsActionLoading: false,
      sessionTimeoutCustom: null,

      // Custom Fields data
      customFieldsActiveTab: 0,
      customerFields: [],
      supplierFields: [],
      customFieldsTableKey: 0,
      customFieldEditmode: false,
      customFieldSubmitProcessing: false,
      customField: {
        id: "",
        name: "",
        field_type: "",
        entity_type: "",
        is_required: false,
        default_value: "",
        sort_order: 0,
      },
      selectOptionsText: "",

    };
  },

   computed: {
   ...mapGetters("config", ["getThemeMode"]),
   ...mapGetters(["currentUser", "getSidebarLayout", "getSideBarToggleProperties"]),

    // List of POS keyboard shortcuts shown in the help modal
    posShortcutsList() {
      return POS_SHORTCUTS;
    },

    // Check if sidebar is open on mobile (for large sidebar layout)
    isSidebarOpenOnMobile() {
      if (this.getSidebarLayout === 'vertical') {
        // For vertical sidebar, check if mobile sidebar is open
        return false; // Vertical sidebar uses different mechanism, adjust if needed
      }
      // For large sidebar layout, check if either sidebar is open
      const props = this.getSideBarToggleProperties;
      return props && (props.isSideNavOpen || props.isSecondarySideNavOpen);
    },

    // Backup destination selector (simple UI):
    // - local => no cloud upload, keep local
    // - cloud => upload to cloud, delete local after successful upload
    // - both  => upload to cloud, keep local
    backupDestination: {
      get() {
        const cloudRaw = this.setting ? this.setting.backup_cloud_enabled : false;
        const cloud = (cloudRaw === true || cloudRaw === 1 || cloudRaw === '1' || cloudRaw === 'true');
        return cloud ? 'cloud' : 'local';
      },
      set(v) {
        if (!this.setting) return;
        this.setting.backup_cloud_enabled = (v === 'cloud');
      }
    },

    // Sidebar layout (same source as the app customizer; persists via Vuex->localStorage)
    sidebarLayoutModel: {
      get() {
        return this.getSidebarLayout || 'vertical';
      },
      set(layout) {
        try { this.setSidebarLayout(layout); } catch (e) {}
      }
    },

    // Normalize POS receipt layout selection (1, 2, 3, or 4) for demo preview
    currentReceiptLayout() {
      const raw = this.pos_settings && this.pos_settings.receipt_layout != null
        ? this.pos_settings.receipt_layout
        : 1;
      const n = Number(raw) || 1;
      return [1, 2, 3, 4].includes(n) ? n : 1;
    },


    // Security: map stored minutes <-> UI dropdown
    sessionTimeoutPreset: {
      get() {
        const v = this.setting ? this.setting.session_timeout_minutes : null;
        const n = v === '' || v === null || typeof v === 'undefined' ? null : Number(v);
        if (!n || isNaN(n) || n < 1) return 'disabled';
        if ([15, 30, 60].includes(n)) return String(n);
        return 'custom';
      },
      set(v) {
        if (!this.setting) return;
        if (v === 'disabled') {
          this.setting.session_timeout_minutes = null;
          return;
        }
        if (v === 'custom') {
          if (!this.sessionTimeoutCustom) {
            const current = Number(this.setting.session_timeout_minutes);
            this.sessionTimeoutCustom = (!isNaN(current) && current > 0) ? current : 15;
          }
          this.setting.session_timeout_minutes = Number(this.sessionTimeoutCustom) || 15;
          return;
        }
        this.setting.session_timeout_minutes = Number(v);
      }
    },

    hasOtherSessions() {
      return (this.securitySessions || []).some(s => !s.is_current);
    },

    securitySessionFields() {
      return [
        { key: 'device', label: 'Device / Browser', tdClass: 'text-left', thClass: 'text-left' },
        { key: 'ip_address', label: 'IP Address', tdClass: 'text-left', thClass: 'text-left' },
        { key: 'login_at', label: 'Login date & time', tdClass: 'text-left', thClass: 'text-left' },
        { key: 'last_activity_at', label: 'Last activity', tdClass: 'text-left', thClass: 'text-left' },
        { key: 'actions', label: 'Action', tdClass: 'text-right', thClass: 'text-right' }
      ];
    },

    backupColumns() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Filesize"),
          field: "size",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Action"),
          field: "actions",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    },
    
    tabs() {
      // Base tabs definition (kept for compatibility)
      const baseTabs = [
        { id: 'general', label: this.$t('General'), icon: 'settings', description: 'Company information and basic settings' },
        { id: 'appearance', label: this.$t('Appearance_Settings'), icon: 'paintbrush', description: 'App branding, logos, and login page customization' },
        { id: 'localization', label: this.$t('Localization'), icon: 'globe', description: 'Language, currency, and timezone settings' },
        { id: 'defaults', label: this.$t('Defaults'), icon: 'database-zap', description: 'Default customer, warehouse, and gateway settings' },
        { id: 'dashboard', label: this.$t('Dashboard_Settings') || 'Dashboard Settings', icon: 'bar-chart', description: 'Choose your preferred dashboard layout and template' },
        { id: 'tax', label: this.$t('Tax_Pricing'), icon: 'banknote', description: 'Tax rates and pricing configurations' },
        { id: 'prefixes', label: this.$t('Prefixes'), icon: 'tag', description: 'Manage prefixes for sales and purchases reference numbers' },
        { id: 'payment', label: this.$t('Payment_Gateway'), icon: 'credit-card', description: 'Stripe payment gateway configuration' },
        { id: 'mail', label: this.$t('mail_settings'), icon: 'mail', description: 'SMTP mail server configuration' },
        { id: 'sms', label: this.$t('sms_settings'), icon: 'message-square', description: 'SMS gateway and provider configurations' },
        { id: 'pos', label: this.$t('POS_Receipt'), icon: 'calculator', description: 'POS receipt configuration' },
        { id: 'pos_settings', label: this.$t('Pos_Settings'), icon: 'database-zap', description: 'POS functionality and display settings' },
        { id: 'cash_drawer', label: this.$t('Cash_drawer'), icon: 'calculator', description: this.$t('Cash_Drawer_Auto_Open_Help') },
        { id: 'direct_network_printing', label: this.$t('Direct_Network_Printing') || 'Direct Network Printing', icon: 'printer', description: this.$t('Direct_Network_Printing_Help') || 'Send receipts directly to a network printer (RAW / port 9100) without relying on the OS print dialog.' },
        { id: 'zatca', label: 'ZATCA', icon: 'clipboard-list', description: 'ZATCA (Fatoorah) integration settings' },
        { id: 'invoice', label: this.$t('Invoice'), icon: 'receipt', description: 'Invoice and quotation settings' },
        { id: 'backup', label: this.$t('BackupDatabase'), icon: 'database-backup', description: 'Database backup and restore management' },
        { id: 'security', label: this.$t('Security_Settings'), icon: 'shield-check', description: 'Session timeout and active login sessions' },
        { id: 'system', label: this.$t('System'), icon: 'settings', description: 'System maintenance and cache management' },
        { id: 'pharmacy', label: this.$t('Pharmacy_Settings'), icon: 'heart-pulse', description: this.$t('Pharmacy_Settings_Help') || 'Enable batch & expiry tracking for pharmacy inventory.' },
        { id: 'custom_fields', label: this.$t('CustomFields') || 'Custom Fields', icon: 'database-zap', description: 'Manage custom fields for customers and suppliers' },
      ];

      // Hide the legacy "Tax & Pricing" tab now that its fields live under "Defaults"
      return baseTabs.filter(t => t.id !== 'tax');
    },
    
    customFieldsColumns() {
      return [
        {
          label: this.$t("FieldName") || "Field Name",
          field: "name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("FieldType") || "Field Type",
          field: "field_type",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Required") || "Required",
          field: "is_required",
          tdClass: "text-center",
          thClass: "text-center"
        },
        {
          label: this.$t("Status") || "Status",
          field: "is_active",
          tdClass: "text-center",
          thClass: "text-center"
        },
        {
          label: this.$t("Action") || "Action",
          field: "actions",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    },
    
    fieldTypes() {
      return [
        { label: this.$t('Text') || 'Text', value: 'text' },
        { label: this.$t('Number') || 'Number', value: 'number' },
        { label: this.$t('Textarea') || 'Textarea', value: 'textarea' },
        { label: this.$t('Date') || 'Date', value: 'date' },
        { label: this.$t('Select') || 'Select', value: 'select' },
        { label: this.$t('Checkbox') || 'Checkbox', value: 'checkbox' },
      ];
    },
    
    direction() {
      if (this.$i18n.locale == "ar") {
        return "rtl";
      } else {
        return "ltr";
      }
    }
   },

  watch: {
    logoSizeType(newVal) {
      // Watch for changes to logoSizeType and update logo_size accordingly
      this.onLogoSizeTypeChange(newVal);
    },
    activeTab(val) {
      if (val === 'security') {
        this.LoadSecuritySessions();
      }
      if (val === 'calendar') {
        this.Load_Calendar_Settings();
      }
      if (val === 'custom_fields') {
        this.Get_CustomFields();
      }
      // Persist last active tab for navigation within the same session
      // Note: This is separate from submitted_tab which is used after form submissions
      try {
        if (typeof window !== 'undefined' && window.localStorage) {
          window.localStorage.setItem('system_settings_active_tab', val);
        }
      } catch (e) {}
    },
    sessionTimeoutCustom(val) {
      if (this.sessionTimeoutPreset === 'custom' && this.setting) {
        const n = Number(val);
        this.setting.session_timeout_minutes = (!isNaN(n) && n > 0) ? n : 15;
      }
    }
  },

  methods: {

    // ---------------- Security Settings ----------------
    formatDateTime(v) {
      try {
        if (!v) return '';
        const d = new Date(v);
        if (isNaN(d.getTime())) return String(v);
        return d.toLocaleString();
      } catch (e) {
        return String(v || '');
      }
    },

    LoadSecuritySessions() {
      if (this.securitySessionsLoading) return;
      this.securitySessionsLoading = true;
      axios
        .get("security/sessions")
        .then(response => {
          this.securitySessions = (response && response.data && response.data.sessions) ? response.data.sessions : [];
        })
        .catch(error => {
          const msg =
            (error && error.response && error.response.data && (error.response.data.message || error.response.data.error)) ||
            this.$t("Failed");
          this.makeToast("danger", msg, this.$t("Failed"));
        })
        .finally(() => {
          this.securitySessionsLoading = false;
        });
    },

    LogoutSession(tokenId) {
      if (!tokenId) return;
      if (this.securitySessionsActionLoading) return;
      this.securitySessionsActionLoading = true;
      axios
        .delete(`security/sessions/${encodeURIComponent(tokenId)}`)
        .then(() => {
          this.makeToast("success", "Session logged out successfully.", this.$t("Success"));
          this.LoadSecuritySessions();
        })
        .catch(error => {
          const msg =
            (error && error.response && error.response.data && (error.response.data.message || error.response.data.error)) ||
            this.$t("Failed");
          this.makeToast("danger", msg, this.$t("Failed"));
        })
        .finally(() => {
          this.securitySessionsActionLoading = false;
        });
    },

    LogoutAllOtherDevices() {
      if (this.securitySessionsActionLoading) return;
      this.securitySessionsActionLoading = true;
      axios
        .post("security/sessions/logout-other")
        .then(response => {
          const revoked = response && response.data && typeof response.data.revoked !== "undefined" ? response.data.revoked : null;
          const msg = revoked === null ? "Logged out other devices." : `Logged out ${revoked} other device(s).`;
          this.makeToast("success", msg, this.$t("Success"));
          this.LoadSecuritySessions();
        })
        .catch(error => {
          const msg =
            (error && error.response && error.response.data && (error.response.data.message || error.response.data.error)) ||
            this.$t("Failed");
          this.makeToast("danger", msg, this.$t("Failed"));
        })
        .finally(() => {
          this.securitySessionsActionLoading = false;
        });
    },

    Load_Calendar_Settings() {
      axios
        .get("settings/calendar")
        .then(response => {
          const d = response && response.data;
          if (d) {
            this.calendarForm.google_calendar_connected = !!d.google_calendar_connected;
            this.calendarForm.google_calendar_connect_url = d.google_calendar_connect_url || "";
            this.calendarForm.google_calendar_disconnect_url = d.google_calendar_disconnect_url || "";
            this.calendarForm.google_calendar_client_id = d.google_calendar_client_id || "";
            this.calendarForm.google_calendar_client_secret = "";
            this.calendarForm.google_calendar_client_secret_set = !!d.google_calendar_client_secret_set;
            this.calendarForm.google_calendar_redirect_uri = d.google_calendar_redirect_uri || "";
            this.calendarForm.google_calendar_calendar_id = d.google_calendar_calendar_id || "";
          }
        })
        .catch(() => {
          this.makeToast("danger", this.$t("Failed") || "Failed", this.$t("Failed"));
        });
    },

    Submit_Calendar_Settings() {
      if (this.calendarSaving) return;
      this.calendarSaving = true;
      axios
        .patch("settings/calendar", {
          google_calendar_client_id: this.calendarForm.google_calendar_client_id || null,
          google_calendar_client_secret: this.calendarForm.google_calendar_client_secret || null,
          google_calendar_redirect_uri: this.calendarForm.google_calendar_redirect_uri || null,
          google_calendar_calendar_id: this.calendarForm.google_calendar_calendar_id || null,
        })
        .then(() => {
          this.makeToast("success", this.$t("Success") || "Saved", this.$t("Success"));
        })
        .catch(error => {
          const msg =
            (error && error.response && error.response.data && (error.response.data.message || error.response.data.errors)) ||
            this.$t("Failed");
          this.makeToast("danger", typeof msg === "object" ? JSON.stringify(msg) : msg, this.$t("Failed"));
        })
        .finally(() => {
          this.calendarSaving = false;
        });
    },

    ...mapActions(["refreshUserPermissions", "setSidebarLayout"]),

    getActiveTabLabel() {
      const tab = this.tabs.find(t => t.id === this.activeTab);
      return tab ? tab.label : '';
    },

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

    getActiveTabDescription() {
      const tab = this.tabs.find(t => t.id === this.activeTab);
      return tab ? tab.description : '';
    },

      SetLocal(locale) {
      this.$i18n.locale = locale;
      this.$store.dispatch("setLanguage", locale);
      Fire.$emit("ChangeLanguage");
      window.location.reload();
    },

    //------------- A4 Invoice Logo Size: clamp inputs to safe ranges
    onInvoiceLogoWidthInput() {
      let v = parseInt(this.setting.invoice_logo_width, 10);
      if (isNaN(v)) v = 180;
      if (v < 20) v = 20;
      if (v > 600) v = 600;
      this.setting.invoice_logo_width = v;
    },
    onInvoiceLogoHeightInput() {
      let v = parseInt(this.setting.invoice_logo_height, 10);
      if (isNaN(v)) v = 60;
      if (v < 20) v = 20;
      if (v > 400) v = 400;
      this.setting.invoice_logo_height = v;
    },

    //------------- Submit General Settings (General tab only)
    Submit_General_Settings() {
      // Validate only the General tab fields via its own observer
      if (this.$refs.generalObserver && this.$refs.generalObserver.validate) {
        this.$refs.generalObserver.validate().then(success => {
          if (!success) {
            this.makeToast(
              "danger",
              this.$t("Please_fill_the_form_correctly"),
              this.$t("Failed")
            );
          } else {
            this.Update_Settings();
          }
        });
      } else {
        // Fallback: if observer is missing, still attempt to save
        this.Update_Settings();
      }
    },

    //------------- Submit Appearance Settings
    Submit_Appearance_Settings() {
      if (this.$refs.appearanceObserver && this.$refs.appearanceObserver.validate) {
        this.$refs.appearanceObserver.validate().then(success => {
          if (!success) {
            this.makeToast(
              "danger",
              this.$t("Please_fill_the_form_correctly"),
              this.$t("Failed")
            );
          } else {
            this.Update_Appearance_Settings();
          }
        });
      } else {
        this.Update_Appearance_Settings();
      }
    },

    //------------- Submit POS Settings
    Submit_POS_Settings() {
      // POS Settings tabs don't have required fields, so skip validation
      this.Update_Pos_Settings();
    },

    //------ Toggle keyboard shortcuts (per-device, stored in localStorage)
    onToggleKeyboardShortcuts() {
      setPosShortcutsEnabled(this.enable_keyboard_shortcuts);
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

    //------------------------------ Event Upload Logo -------------------------------\\
    async onFileSelected(e) {
      const file = e.target.files[0];
      if (file) {
        // Validate file type
        if (!file.type.match('image.*')) {
          this.makeToast("danger", this.$t("Invalid_file_type"), this.$t("Failed"));
          e.target.value = '';
          this.setting.logo = "";
          return;
        }
        // Validate file size (200KB = 200 * 1024 bytes)
        if (file.size > 200 * 1024) {
          this.makeToast("danger", this.$t("File_size_must_be_less_than_200KB"), this.$t("Failed"));
          e.target.value = '';
          this.setting.logo = "";
          return;
        }
        this.setting.logo = file;
      } else {
        this.setting.logo = "";
      }
    },

    //------------------------------ Event Upload Appearance Logo -------------------------------\\
    async onAppearanceLogoSelected(e) {
      const { valid } = await this.$refs.AppearanceLogo.validate(e);

      if (valid) {
        this.appearance_settings.logo = e.target.files[0];
      } else {
        this.appearance_settings.logo = "";
      }
    },

    //------------------------------ Event Upload Appearance Favicon -------------------------------\\
    async onAppearanceFaviconSelected(e) {
      const { valid } = await this.$refs.AppearanceFavicon.validate(e);

      if (valid) {
        this.appearance_settings.favicon = e.target.files[0];
      } else {
        this.appearance_settings.favicon = "";
      }
    },

     Selected_Time_Zone(value) {
          if (value === null) {
              this.setting.timezone = "";
          }
      },

    syncDashboardSectionOrderList() {
      let order = [];
      try {
        const raw = this.setting.dashboard_section_order;
        if (raw && typeof raw === 'string') order = JSON.parse(raw);
        else if (Array.isArray(raw)) order = raw;
      } catch (e) {}
      const byId = {};
      this.defaultDashboardSections.forEach(s => { byId[s.id] = s; });
      const ordered = [];
      order.forEach(id => {
        if (byId[id]) { ordered.push({ id: byId[id].id, labelKey: byId[id].labelKey }); delete byId[id]; }
      });
      Object.keys(byId).forEach(id => ordered.push(byId[id]));
      this.dashboardSectionOrderList = ordered;
    },

    onDashboardSectionOrderChange() {
      this.setting.dashboard_section_order = JSON.stringify(this.dashboardSectionOrderList.map(x => x.id));
    },

    resetDashboardSectionOrder() {
      this.dashboardSectionOrderList = this.defaultDashboardSections.map(s => ({ id: s.id, labelKey: s.labelKey }));
      this.setting.dashboard_section_order = JSON.stringify(this.dashboardSectionOrderList.map(x => x.id));
      this.$bvToast && this.$bvToast.toast(this.$t('Dashboard_order_reset') || 'Dashboard section order reset to default.', { title: this.$t('Success') || 'Success', variant: 'success' });
    },

    //---------------------------------- Update Settings ----------------\\
    Update_Settings() {
      NProgress.start();
      NProgress.set(0.1);
      var self = this;
      self.data = new FormData(); // Reset FormData
      self.data.append("client", self.setting.client_id);
      self.data.append("warehouse", self.setting.warehouse_id);
      self.data.append("default_account", self.setting.default_account_id || "");
      self.data.append("default_payment_method", self.setting.default_payment_method_id || "");
      self.data.append("currency", self.setting.currency_id);
      self.data.append("email", self.setting.email);
      self.data.append("logo", self.setting.logo);
      self.data.append("CompanyName", self.setting.CompanyName);
      self.data.append("CompanyPhone", self.setting.CompanyPhone);
      self.data.append("CompanyAdress", self.setting.CompanyAdress);
      self.data.append("company_name_ar", self.setting.company_name_ar || '');
      self.data.append("vat_number", self.setting.vat_number || '');
      self.data.append("zatca_enabled", self.setting.zatca_enabled ? 1 : 0);
      self.data.append("footer", self.setting.footer);
      self.data.append("developed_by", self.setting.developed_by);
      self.data.append("default_language", self.setting.default_language);
      self.data.append("sms_gateway", self.setting.sms_gateway);
      self.data.append("is_invoice_footer", self.setting.is_invoice_footer);
      self.data.append("invoice_footer", self.setting.invoice_footer);
      self.data.append("invoice_format", self.setting.invoice_format || "thermal");
      self.data.append("invoice_logo_width", self.setting.invoice_logo_width || 180);
      self.data.append("invoice_logo_height", self.setting.invoice_logo_height || 60);
      self.data.append("quotation_with_stock", self.setting.quotation_with_stock);
      self.data.append("show_language", self.setting.show_language);
      self.data.append("timezone", self.setting.timezone);
      self.data.append("date_format", self.setting.date_format || 'YYYY-MM-DD');
      // Optional price format for frontend display (POS, etc.)
      self.data.append("price_format", self.setting.price_format || "");
      self.data.append("point_to_amount_rate", self.setting.point_to_amount_rate);
      self.data.append("default_tax", self.setting.default_tax || 0);
      self.data.append("default_dashboard_date_range", self.setting.default_dashboard_date_range || "week");
      self.data.append("dashboard_section_order", typeof self.setting.dashboard_section_order === 'string' ? self.setting.dashboard_section_order : JSON.stringify(self.setting.dashboard_section_order || []));
      self.data.append("dashboard_font_size", self.setting.dashboard_font_size || "");
      self.data.append("dashboard_font_family", self.setting.dashboard_font_family || "");
      self.data.append("dark_mode", self.setting.dark_mode ? 1 : 0);
      self.data.append("rtl", self.setting.rtl ? 1 : 0);
      self.data.append("debug_mode", self.setting.debug_mode ? 1 : 0);
      self.data.append("sale_prefix", self.setting.sale_prefix || '');
      self.data.append("purchase_prefix", self.setting.purchase_prefix || '');
      self.data.append("quotation_prefix", self.setting.quotation_prefix || '');
      self.data.append("adjustment_prefix", self.setting.adjustment_prefix || '');
      self.data.append("transfer_prefix", self.setting.transfer_prefix || '');
      self.data.append("sale_return_prefix", self.setting.sale_return_prefix || '');
      self.data.append("purchase_return_prefix", self.setting.purchase_return_prefix || '');
      // Security: inactivity auto-logout (minutes) - empty => disabled/null
      self.data.append(
        "session_timeout_minutes",
        (self.setting.session_timeout_minutes === null || typeof self.setting.session_timeout_minutes === "undefined")
          ? ""
          : self.setting.session_timeout_minutes
      );

      // Cloud backup destination settings (optional)
      self.data.append("backup_cloud_enabled", self.setting.backup_cloud_enabled ? 1 : 0);
      self.data.append("backup_cloud_provider", self.setting.backup_cloud_provider || "");
      self.data.append("backup_cloud_path", self.setting.backup_cloud_path || "");

      // S3-compatible
      self.data.append("backup_s3_bucket", self.setting.backup_s3_bucket || "");
      self.data.append("backup_s3_region", self.setting.backup_s3_region || "");
      self.data.append("backup_s3_access_key", self.setting.backup_s3_access_key || "");
      self.data.append("backup_s3_secret_key", self.setting.backup_s3_secret_key || "");
      self.data.append("backup_s3_endpoint", self.setting.backup_s3_endpoint || "");
      self.data.append("backup_s3_path_style", self.setting.backup_s3_path_style ? 1 : 0);

      // Google Drive
      self.data.append("backup_gdrive_folder_id", self.setting.backup_gdrive_folder_id || "");
      self.data.append("backup_gdrive_access_token", self.setting.backup_gdrive_access_token || "");
      self.data.append("backup_gdrive_refresh_token", self.setting.backup_gdrive_refresh_token || "");
      self.data.append("backup_gdrive_client_id", self.setting.backup_gdrive_client_id || "");
      self.data.append("backup_gdrive_client_secret", self.setting.backup_gdrive_client_secret || "");

      // Dropbox
      self.data.append("backup_dropbox_path", self.setting.backup_dropbox_path || "");
      self.data.append("backup_dropbox_access_token", self.setting.backup_dropbox_access_token || "");

      // Pharmacy mode (batch & expiry tracking)
      self.data.append("pharmacy_mode", self.setting.pharmacy_mode ? 1 : 0);
      self.data.append("expiry_warning_days", self.setting.expiry_warning_days != null && self.setting.expiry_warning_days !== "" ? self.setting.expiry_warning_days : 90);
      self.data.append("block_expired_sale", self.setting.block_expired_sale ? 1 : 0);
      self.data.append("print_expiry_on_receipt", self.setting.print_expiry_on_receipt ? 1 : 0);

      self.data.append("_method", "put");

      // Defaults tab field "How many items do you want to display in POS"
      // lives on pos_settings, not the global settings table. Persist it
      // alongside the general settings save so the Defaults tab actually
      // stores it.
      const posSettingsId = self.pos_settings && self.pos_settings.id;
      const productsPerPage = self.pos_settings && self.pos_settings.products_per_page;
      const posSidecar = posSettingsId
        ? axios.put("pos_settings/" + posSettingsId, {
            products_per_page: productsPerPage,
          })
        : Promise.resolve();

      axios
        .post("settings/" + self.setting.id, self.data)
        .then(response => {
          posSidecar.catch(() => {});
          Fire.$emit("Event_Setting");

          // Sync Vuex store with saved settings by directly setting the values
          if (self.setting.dark_mode !== undefined && self.setting.dark_mode !== null) {
            this.$store.state.config.themeMode.dark = self.setting.dark_mode;
          }
          
          if (self.setting.rtl !== undefined && self.setting.rtl !== null) {
            this.$store.state.config.themeMode.rtl = self.setting.rtl;
          }
          
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          // Update date_format in Vuex store and localStorage cache
          try {
            if (self.setting.date_format) {
              // Update Vuex store (primary source)
              this.$store.commit('setDateFormat', self.setting.date_format);
              // Also update localStorage as cache
              localStorage.setItem('app_date_format', self.setting.date_format);
            }
          } catch (e) {}

          // Cache price_format in localStorage for frontend-only display helpers (e.g., POS)
          try {
            if (self.setting.price_format) {
              cachePriceFormat(self.setting.price_format);
            }
          } catch (e) {}
          this.refreshUserPermissions();
          // Save the active tab so it can be restored after reload (must be before SetLocal which may reload)
          try {
            if (typeof window !== 'undefined' && window.localStorage) {
              window.localStorage.setItem('system_settings_submitted_tab', this.activeTab);
            }
          } catch (e) {}
          NProgress.done();
          this.SetLocal(self.setting.default_language);
        })
        .catch(error => {
          const msg =
            (error && error.response && error.response.data && (error.response.data.message || error.response.data.error)) ||
            this.$t("InvalidData");
          this.makeToast("danger", msg, this.$t("Failed"));
          NProgress.done();
        });
    }, 

    //---------------------------------- Update Payment Gateway ----------------\\
    Update_Payment() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("payment_gateway", {
          stripe_key: this.gateway.stripe_key,
          stripe_secret: this.gateway.stripe_secret,
          deleted: this.gateway.deleted,
        })
        .then(response => {
          Fire.$emit("Event_payment");
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

    //---------------------------------- GET Payment_Gateway ----------------\\
    Get_Payment_Gateway() {
      axios
        .get("get_payment_gateway_ws")
        .then(response => {
          this.gateway = response.data.gateway;
        })
        .catch(error => {
          // Silently fail if payment gateway endpoint doesn't exist
        });
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
          products_per_page: this.pos_settings.products_per_page,
          quick_add_customer: this.pos_settings.quick_add_customer,
          barcode_scanning_sound: this.pos_settings.barcode_scanning_sound,
          show_product_images: this.pos_settings.show_product_images,
          show_stock_quantity: this.pos_settings.show_stock_quantity,
          enable_hold_sales: this.pos_settings.enable_hold_sales,
          enable_customer_points: this.pos_settings.enable_customer_points,
          show_categories: this.pos_settings.show_categories,
          show_brands: this.pos_settings.show_brands,
          allow_overselling: this.pos_settings.allow_overselling ? 1 : 0,
          show_paid: this.pos_settings.show_paid,
          show_due: this.pos_settings.show_due,
          show_payments: this.pos_settings.show_payments,
          show_zatca_qr: this.pos_settings.show_zatca_qr,
          receipt_paper_size: this.pos_settings.receipt_paper_size,
          receipt_layout: this.pos_settings.receipt_layout,
          cash_drawer_auto_open: this.pos_settings.cash_drawer_auto_open ? 1 : 0,
          cash_drawer_printer_name: this.pos_settings.cash_drawer_printer_name || null,
          direct_network_printing: this.pos_settings.direct_network_printing ? 1 : 0,
          network_printer_ip: (this.pos_settings.network_printer_ip || "").toString().trim() || null,
          network_printer_port: this.pos_settings.network_printer_port ? Number(this.pos_settings.network_printer_port) : null,
          invoice_format: this.setting.invoice_format || "thermal",
        })
        .then(response => {
          Fire.$emit("Event_Pos_Settings");
          // Save the active tab so it can be restored after reload
          try {
            if (typeof window !== 'undefined' && window.localStorage) {
              window.localStorage.setItem('system_settings_submitted_tab', this.activeTab);
            }
          } catch (e) {}
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

    //---------------------------------- Get_pos_Settings ----------------\\
    Get_Pos_Settings() {
      axios
        .get("get_pos_Settings")
        .then(response => {
          this.pos_settings = {
            ...this.pos_settings,
            ...(response.data.pos_settings || {}),
          };
          // Ensure show_tax and show_shipping have default values if not present
          if (this.pos_settings.show_tax === undefined || this.pos_settings.show_tax === null || this.pos_settings.show_tax === '') {
            this.pos_settings.show_tax = this.pos_settings.show_discount || 0;
          }
          if (this.pos_settings.show_shipping === undefined || this.pos_settings.show_shipping === null || this.pos_settings.show_shipping === '') {
            this.pos_settings.show_shipping = this.pos_settings.show_discount || 0;
          }
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
        })
        .catch(error => {
          // Silently fail if POS settings endpoint doesn't exist
        });
    },

    //---------------------------------- Update_Default_SMS ----------------\\
    Update_Default_SMS() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .put("update_Default_SMS", {
          default_sms_gateway: this.sms_settings.default_sms_gateway,
        })
        .then(response => {
          // Save the active tab so it can be restored after reload
          try {
            if (typeof window !== 'undefined' && window.localStorage) {
              window.localStorage.setItem('system_settings_submitted_tab', this.activeTab);
            }
          } catch (e) {}
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

    //---------------------------------- Update_Termi_SMS ----------------\\
    Update_Termi_SMS() {
      if (this.$refs.smsObserver && this.$refs.smsObserver.validate) {
        this.$refs.smsObserver.validate().then(success => {
          if (!success) {
            this.makeToast(
              "danger",
              this.$t("Please_fill_the_form_correctly"),
              this.$t("Failed")
            );
            return;
          }
          this.submitTermiSMS();
        });
      } else {
        this.submitTermiSMS();
      }
    },
    submitTermiSMS() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("update_termi_config", {
          TERMI_KEY: this.sms_settings.termi.TERMI_KEY,
          TERMI_SECRET: this.sms_settings.termi.TERMI_SECRET,
          TERMI_SENDER: this.sms_settings.termi.TERMI_SENDER,
        })
        .then(response => {
          Fire.$emit("Event_sms");
          // Save the active tab so it can be restored after reload
          try {
            if (typeof window !== 'undefined' && window.localStorage) {
              window.localStorage.setItem('system_settings_submitted_tab', this.activeTab);
            }
          } catch (e) {}
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

    //---------------------------------- Update_Twilio_SMS ----------------\\
    Update_Twilio_SMS() {
      if (this.$refs.smsObserver && this.$refs.smsObserver.validate) {
        this.$refs.smsObserver.validate().then(success => {
          if (!success) {
            this.makeToast(
              "danger",
              this.$t("Please_fill_the_form_correctly"),
              this.$t("Failed")
            );
            return;
          }
          this.submitTwilioSMS();
        });
      } else {
        this.submitTwilioSMS();
      }
    },
    submitTwilioSMS() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("update_twilio_config", {
          TWILIO_SID: this.sms_settings.twilio.TWILIO_SID,
          TWILIO_TOKEN: this.sms_settings.twilio.TWILIO_TOKEN,
          TWILIO_FROM: this.sms_settings.twilio.TWILIO_FROM,
        })
        .then(response => {
          Fire.$emit("Event_sms");
          // Save the active tab so it can be restored after reload
          try {
            if (typeof window !== 'undefined' && window.localStorage) {
              window.localStorage.setItem('system_settings_submitted_tab', this.activeTab);
            }
          } catch (e) {}
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

    //---------------------------------- Update_Infobip_SMS ----------------\\
    Update_Infobip_SMS() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("update_infobip_config", {
          base_url: this.sms_settings.infobip.base_url,
          api_key: this.sms_settings.infobip.api_key,
          sender_from: this.sms_settings.infobip.sender_from,
        })
        .then(response => {
          Fire.$emit("Event_sms");
          // Save the active tab so it can be restored after reload
          try {
            if (typeof window !== 'undefined' && window.localStorage) {
              window.localStorage.setItem('system_settings_submitted_tab', this.activeTab);
            }
          } catch (e) {}
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

    //---------------------------------- Get_SMS_Settings ----------------\\
    Get_SMS_Settings() {
      axios
        .get("get_sms_config")
        .then(response => {
          this.sms_settings.twilio = response.data.twilio || this.sms_settings.twilio;
          this.sms_settings.termi = response.data.termi || this.sms_settings.termi;
          this.sms_settings.infobip = response.data.infobip || this.sms_settings.infobip;
          this.sms_settings.sms_gateway = response.data.sms_gateway || [];
          this.sms_settings.default_sms_gateway = response.data.default_sms_gateway || '';

          const c = response.data.custom || {};
          this.sms_settings.custom = {
            api_url: c.api_url || '',
            method: c.method || 'POST',
            content_type: c.content_type || 'json',
            sender: c.sender || '',
            success_keyword: c.success_keyword || '',
            headers: c.headers || {},
            payload: c.payload || {},
          };
          this.sms_settings.customHeaderRows = this.sms_objectToRows(this.sms_settings.custom.headers);
          this.sms_settings.customPayloadRows = this.sms_objectToRows(this.sms_settings.custom.payload);
        })
        .catch(error => {
          // Silently fail if SMS settings endpoint doesn't exist
        });
    },

    //---------------------------------- Custom SMS helpers ----------------\\
    sms_addHeaderRow() {
      this.sms_settings.customHeaderRows.push({ key: '', value: '' });
    },
    sms_removeHeaderRow(idx) {
      this.sms_settings.customHeaderRows.splice(idx, 1);
    },
    sms_addPayloadRow() {
      this.sms_settings.customPayloadRows.push({ key: '', value: '' });
    },
    sms_removePayloadRow(idx) {
      this.sms_settings.customPayloadRows.splice(idx, 1);
    },
    sms_rowsToObject(rows) {
      const obj = {};
      (rows || []).forEach(r => {
        const key = (r.key || '').trim();
        if (key !== '') {
          obj[key] = r.value || '';
        }
      });
      return obj;
    },
    sms_objectToRows(obj) {
      if (!obj || typeof obj !== 'object') return [];
      return Object.keys(obj).map(k => ({ key: k, value: obj[k] }));
    },

    //---------------------------------- Update_Custom_SMS ----------------\\
    Update_Custom_SMS() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("update_custom_config", {
          api_url: this.sms_settings.custom.api_url,
          method: this.sms_settings.custom.method,
          content_type: this.sms_settings.custom.content_type,
          sender: this.sms_settings.custom.sender,
          success_keyword: this.sms_settings.custom.success_keyword,
          headers: this.sms_rowsToObject(this.sms_settings.customHeaderRows),
          payload: this.sms_rowsToObject(this.sms_settings.customPayloadRows),
        })
        .then(response => {
          Fire.$emit("Event_sms");
          try {
            if (typeof window !== 'undefined' && window.localStorage) {
              window.localStorage.setItem('system_settings_submitted_tab', this.activeTab);
            }
          } catch (e) {}
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

    //---------------------------------- Update_Appearance_Settings ----------------\\
    Update_Appearance_Settings() {
      NProgress.start();
      NProgress.set(0.1);
      var self = this;
      self.appearance_data = new FormData();
      self.appearance_data.append("favicon", self.appearance_settings.favicon);
      self.appearance_data.append("logo", self.appearance_settings.logo);
      self.appearance_data.append("app_name", self.appearance_settings.app_name);
      self.appearance_data.append("page_title_suffix", self.appearance_settings.page_title_suffix);
      self.appearance_data.append("developed_by", self.appearance_settings.developed_by);
      self.appearance_data.append("footer", self.appearance_settings.footer);
      self.appearance_data.append("customize_button_visible", self.appearance_settings.customize_button_visible ? "1" : "0");
      self.appearance_data.append("hide_site_name", self.appearance_settings.hide_site_name ? "1" : "0");
      self.appearance_data.append("login_hero_title", self.appearance_settings.login_hero_title || "");
      self.appearance_data.append("login_hero_subtitle", self.appearance_settings.login_hero_subtitle || "");
      self.appearance_data.append("login_panel_title", self.appearance_settings.login_panel_title || "");
      self.appearance_data.append("login_panel_subtitle", self.appearance_settings.login_panel_subtitle || "");
      self.appearance_data.append("_method", "put");

      axios
        .post("update_appearance_settings/" + self.appearance_settings.id, self.appearance_data)
        .then(response => {
          Fire.$emit("Event_Appearance_Setting");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
          // Reload the page to reflect appearance changes
          setTimeout(() => {
            window.location.reload();
          }, 500);
        })
        .catch(error => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          NProgress.done();
        });
    },

    //---------------------------------- Get_Appearance_Settings ----------------\\
    Get_Appearance_Settings() {
      axios
        .get("get_appearance_settings")
        .then(response => {
          this.appearance_settings = response.data.settings || this.appearance_settings;
        })
        .catch(error => {
          // Silently fail if appearance settings endpoint doesn't exist
        });
    },

    //---------------------------------- Update_Mail_Settings ----------------\\
    Update_Mail_Settings() {
      if (this.$refs.mailObserver && this.$refs.mailObserver.validate) {
        this.$refs.mailObserver.validate().then(success => {
          if (!success) {
            this.makeToast(
              "danger",
              this.$t("Please_fill_the_form_correctly"),
              this.$t("Failed")
            );
            return;
          }
          this.submitMailSettings();
        });
      } else {
        this.submitMailSettings();
      }
    },
    submitMailSettings(silent = false) {
      NProgress.start();
      NProgress.set(0.1);
      return axios
        .put("update_config_mail/" + this.mail_settings.id, {
          mail_mailer: this.mail_settings.mail_mailer,
          host: this.mail_settings.host,
          port: this.mail_settings.port,
          sender_name: this.mail_settings.sender_name,
          sender_email: this.mail_settings.sender_email,
          username: this.mail_settings.username,
          password: this.mail_settings.password,
          encryption: this.mail_settings.encryption
        })
          .then(response => {
            Fire.$emit("Event_Smtp");
            // Save the active tab so it can be restored after reload
            try {
              if (typeof window !== 'undefined' && window.localStorage) {
                window.localStorage.setItem('system_settings_submitted_tab', this.activeTab);
              }
            } catch (e) {}
            if (!silent) {
              this.makeToast(
                "success",
                this.$t("Successfully_Updated"),
                this.$t("Success")
              );
            }
            NProgress.done();
            return response;
          })
          .catch(error => {
            NProgress.done();
            if (!silent) {
              this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
            }
            throw error;
          });
    },

    //---------------------------------- Get_Mail_Settings ----------------\\
    Get_Mail_Settings() {
      axios
        .get("get_config_mail")
        .then(response => {
          this.mail_settings = response.data.server || this.mail_settings;
        })
        .catch(error => {
          // Silently fail if mail settings endpoint doesn't exist
        });
    },

    //---------------------------------- Test_Mail_Settings ----------------\\
    Test_Mail_Settings() {
      if (this.isTestingMail) return;
      
      // First validate the form
      if (this.$refs.mailObserver && this.$refs.mailObserver.validate) {
        this.$refs.mailObserver.validate().then(success => {
          if (!success) {
            this.makeToast(
              "danger",
              this.$t("Please_fill_the_form_correctly"),
              this.$t("Failed")
            );
            return;
          }

          // Save first, then test
          this.isTestingMail = true;
          NProgress.start();
          NProgress.set(0.1);

          // Save settings first (silently, without showing success toast)
          this.submitMailSettings(true)
            .then(() => {
              // After saving, test the mail
              return axios.post("test_config_mail");
            })
            .then(response => {
              const msg =
                (response.data && (response.data.message || response.data.msg)) ||
                this.$t("Successfully_Updated");

              this.makeToast("success", msg, this.$t("Success"));
            })
            .catch(error => {
              const msg =
                (error.response && error.response.data && (error.response.data.message || error.response.data.errors)) ||
                this.$t("InvalidData");
              this.makeToast("danger", msg, this.$t("Failed"));
            })
            .finally(() => {
              this.isTestingMail = false;
              NProgress.done();
            });
        });
      } else {
        // Fallback if validation observer is not available
        this.isTestingMail = true;
        NProgress.start();
        NProgress.set(0.1);

        this.submitMailSettings(true)
          .then(() => {
            return axios.post("test_config_mail");
          })
          .then(response => {
            const msg =
              (response.data && (response.data.message || response.data.msg)) ||
              this.$t("Successfully_Updated");

            this.makeToast("success", msg, this.$t("Success"));
          })
          .catch(error => {
            const msg =
              (error.response && error.response.data && (error.response.data.message || error.response.data.errors)) ||
              this.$t("InvalidData");
            this.makeToast("danger", msg, this.$t("Failed"));
          })
          .finally(() => {
            this.isTestingMail = false;
            NProgress.done();
          });
      }
    },

    //---------------------------------- Generate Backup --------------------\\
    GenerateBackup() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("generate_new_backup")
        .then(response => {
          Fire.$emit("Generate_Backup");
          
          // Check if backup was successful
          if (response.data && response.data.success === false) {
            // Backup generation failed
            const errorMsg = response.data.error || response.data.message || this.$t("Failed_to_generate_backup") || "Failed to generate backup";
            
            // Check if it's a mysqldump not found error
            if (errorMsg.includes('mysqldump') && errorMsg.includes('not found')) {
              this.backupError = true;
            }
            
            this.makeToast("danger", errorMsg, this.$t("Failed"));
          } else {
            // Clear any previous errors on success
            this.backupError = null;
            // Backup successful
            const message = this.$t("Backup_generated_successfully") || "Backup generated successfully";
            
            this.makeToast("success", message, this.$t("Success"));
          }
          
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(error => {
          // Handle error response
          let errorMsg = this.$t("Failed_to_generate_backup") || "Failed to generate backup";
          
          if (error.response && error.response.data) {
            if (error.response.data.error) {
              errorMsg = error.response.data.error;
            } else if (error.response.data.message) {
              errorMsg = error.response.data.message;
            }
          } else if (error.message) {
            errorMsg = error.message;
          }
          
          // Check if it's a mysqldump not found error
          if (errorMsg.includes('mysqldump') && errorMsg.includes('not found')) {
            this.backupError = true;
          }
          
          this.makeToast("danger", errorMsg, this.$t("Failed"));
          setTimeout(() => NProgress.done(), 500);
        });
    },

    //----------------------------------------  Get All backups -------------------------\\
    Get_Backups() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("get_backup")
        .then(response => {
          this.backups = response.data.backups || [];
          this.totalRows = response.data.totalRows || 0;
          NProgress.done();
        })
        .catch(response => {
          NProgress.done();
          // Silently fail if backup endpoint doesn't exist
        });
    },

    //--------------------------------- Delete Backup --------------------\\
    DeleteBackup(date) {
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
            .delete("delete_backup/" + date)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              Fire.$emit("Delete_Backup");
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
    }, 

    //---------------------------------- Clear_Cache ----------------\\
    Clear_Cache() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("clear_cache")
        .then(response => {
          this.makeToast(
            "success",
            this.$t("Cache_cleared_successfully"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("Failed_to_clear_cache"), this.$t("Failed"));
        });
    },   

    //---------------------------------- Get SETTINGS ----------------\\
    Get_Settings() {
      axios
        .get("get_Settings_data_api", { params: { include_secrets: 1 } })
        .then(response => {
          // Merge to preserve default keys/reactivity for newly added settings fields
          this.setting         = { ...this.setting, ...(response.data.settings || {}) };
          this.syncDashboardSectionOrderList();
          // Update date_format in Vuex store and localStorage cache
          try {
            if (this.setting.date_format) {
              // Update Vuex store (primary source from database)
              this.$store.commit('setDateFormat', this.setting.date_format);
              // Also update localStorage as cache
              localStorage.setItem('app_date_format', this.setting.date_format);
            }
          } catch (e) {}
          // Cache price_format in localStorage for frontend-only display helpers (e.g., POS)
          try {
            if (this.setting.price_format) {
              cachePriceFormat(this.setting.price_format);
            }
          } catch (e) {}
          // If current timeout is a custom value, keep it in the custom input
          try {
            const stm = Number(this.setting.session_timeout_minutes);
            if (!isNaN(stm) && stm > 0 && ![15, 30, 60].includes(stm)) {
              this.sessionTimeoutCustom = stm;
            }
          } catch (e) {}
          
          // Sync dark_mode and rtl with Vuex store if they exist in settings
          // If not in settings, use current Vuex store values
          if (this.setting.dark_mode !== undefined && this.setting.dark_mode !== null) {
            // Sync Vuex store with backend setting by directly setting the value
            if (this.getThemeMode.dark !== this.setting.dark_mode) {
              this.$store.state.config.themeMode.dark = this.setting.dark_mode;
            }
          } else {
            // If not in backend, initialize from Vuex store
            this.setting.dark_mode = this.getThemeMode.dark || false;
          }
          
          if (this.setting.rtl !== undefined && this.setting.rtl !== null) {
            // Sync Vuex store with backend setting by directly setting the value
            if (this.getThemeMode.rtl !== this.setting.rtl) {
              this.$store.state.config.themeMode.rtl = this.setting.rtl;
            }
          } else {
            // If not in backend, initialize from Vuex store
            this.setting.rtl = this.getThemeMode.rtl || false;
          }
          
          this.currencies      = response.data.currencies;
          this.clients         = response.data.clients;
          this.warehouses      = response.data.warehouses;
          this.sms_gateway     = response.data.sms_gateway;
          this.accounts       = response.data.accounts || [];
          this.payment_methods = response.data.payment_methods || [];
          this.zones_array    = response.data.zones_array;
          this.languages      = response.data.languages;
          this.isLoading = false;
        })
        .catch(error => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //----------------------------------- Custom Fields Methods -------------------------------\\
    Get_CustomFields() {
      // Get customer fields
      const customerPromise = axios
        .get("custom-fields?entity_type=client")
        .then(response => {
          this.customerFields = response.data.custom_fields || [];
        })
        .catch(error => {
          console.error('Error loading customer fields:', error);
          // Return resolved promise even on error to allow Promise.all to complete
          return Promise.resolve();
        });

      // Get supplier fields
      const supplierPromise = axios
        .get("custom-fields?entity_type=provider")
        .then(response => {
          this.supplierFields = response.data.custom_fields || [];
        })
        .catch(error => {
          console.error('Error loading supplier fields:', error);
          // Return resolved promise even on error to allow Promise.all to complete
          return Promise.resolve();
        });

      // Return promise that resolves when both requests complete (even if one fails)
      return Promise.all([customerPromise, supplierPromise]);
    },

    New_CustomField(entityType) {
      this.reset_CustomField_Form();
      this.customField.entity_type = entityType;
      this.customFieldEditmode = false;
      setTimeout(() => {
        this.$bvModal.show("New_CustomField");
      }, 500);
    },

    Edit_CustomField(customField) {
      this.reset_CustomField_Form();
      this.customField = {
        id: customField.id,
        name: customField.name,
        field_type: customField.field_type,
        entity_type: customField.entity_type,
        is_required: customField.is_required,
        is_active: customField.is_active !== undefined ? customField.is_active : true,
        default_value: customField.default_value || "",
        sort_order: customField.sort_order || 0,
      };

      // Handle select options
      if (customField.field_type === 'select' && customField.default_value) {
        const options = Array.isArray(customField.default_value) 
          ? customField.default_value 
          : JSON.parse(customField.default_value || '[]');
        this.selectOptionsText = options.join('\n');
      } else {
        this.selectOptionsText = "";
      }

      this.customFieldEditmode = true;
      setTimeout(() => {
        this.$bvModal.show("Edit_CustomField");
      }, 500);
    },

    Submit_CustomField() {
      this.$refs.Create_CustomField.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
          return;
        }

        this.customFieldSubmitProcessing = true;

        const payload = {
          name: this.customField.name,
          field_type: this.customField.field_type,
          entity_type: this.customField.entity_type,
          is_required: this.customField.is_required,
          is_active: this.customField.is_active !== undefined ? this.customField.is_active : true,
          default_value: this.customField.default_value || null,
          sort_order: this.customField.sort_order || 0,
        };

        // Handle select options
        if (this.customField.field_type === 'select') {
          const options = this.selectOptionsText
            .split('\n')
            .map(opt => opt.trim())
            .filter(opt => opt.length > 0);
          payload.default_value = JSON.stringify(options);
        }

        const url = this.customFieldEditmode
          ? `custom-fields/${this.customField.id}`
          : "custom-fields";
        const method = this.customFieldEditmode ? "put" : "post";

        axios[method](url, payload)
          .then(response => {
            this.makeToast(
              "success",
              this.customFieldEditmode ? this.$t("Successfully_Updated") : this.$t("Successfully_Created"),
              this.$t("Success")
            );
            this.customFieldSubmitProcessing = false;
            this.$bvModal.hide(this.customFieldEditmode ? "Edit_CustomField" : "New_CustomField");
            this.Get_CustomFields().then(() => {
              // Force table re-render after data is refreshed
              this.$nextTick(() => {
                this.customFieldsTableKey += 1;
              });
            });
          })
          .catch(error => {
            this.customFieldSubmitProcessing = false;
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          });
      });
    },

    Delete_CustomField(id) {
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
            .delete("custom-fields/" + id)
            .then(response => {
              this.makeToast(
                "success",
                this.$t("Deleted_in_successfully"),
                this.$t("Success")
              );
              // Remove the item from local arrays immediately (optimistic update)
              this.customerFields = this.customerFields.filter(field => field.id !== id);
              this.supplierFields = this.supplierFields.filter(field => field.id !== id);
              // Refresh the data from server to ensure consistency
              this.Get_CustomFields().then(() => {
                // Force table re-render after data is refreshed
                this.$nextTick(() => {
                  this.customFieldsTableKey += 1;
                });
              });
            })
            .catch(error => {
              this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
            });
        }
      });
    },

    reset_CustomField_Form() {
      this.customField = {
        id: "",
        name: "",
        field_type: "",
        entity_type: "",
        is_required: false,
        is_active: true,
        default_value: "",
        sort_order: 0,
      };
      this.selectOptionsText = "";
      this.customFieldEditmode = false;
    },

    onFieldTypeChange() {
      if (this.customField.field_type !== 'select') {
        this.selectOptionsText = "";
      }
      if (this.customField.field_type === 'checkbox') {
        this.customField.default_value = "";
      }
    },

    updateSelectOptions() {
      // This is handled in Submit_CustomField
    },

    getFieldTypeLabel(type) {
      const field = this.fieldTypes.find(f => f.value === type);
      return field ? field.label : type;
    },

  }, //end Methods

  //----------------------------- Created function-------------------

  created: function() {
    // Determine which tab to show first (before loading data)
    // If route has ?tab=pos or ?tab=pos_settings, open that tab by default
    if (this.$route && this.$route.query && this.$route.query.tab) {
      const tabId = this.$route.query.tab;
      if (this.tabs && this.tabs.some(t => t.id === tabId)) {
        this.activeTab = tabId;
        // Clear submission flag when explicitly navigating to a tab
        try {
          if (typeof window !== 'undefined' && window.localStorage) {
            window.localStorage.removeItem('system_settings_submitted_tab');
          }
        } catch (e) {}
      }
    } else {
      // Check if we're returning after a form submission
      try {
        if (typeof window !== 'undefined' && window.localStorage) {
          const submittedTab = window.localStorage.getItem('system_settings_submitted_tab');
          if (submittedTab) {
            // Valid tab IDs that can be restored
            const validTabs = ['general', 'appearance', 'localization', 'defaults', 'dashboard', 'prefixes', 
                              'payment', 'mail', 'sms', 'pos', 'pos_settings', 'zatca', 
                              'invoice', 'backup', 'security', 'system'];
            if (validTabs.includes(submittedTab)) {
              // Restore the tab that was active when form was submitted
              this.activeTab = submittedTab;
              // Clear the submission flag after restoring
              window.localStorage.removeItem('system_settings_submitted_tab');
            } else {
              // Invalid tab, clear it
              window.localStorage.removeItem('system_settings_submitted_tab');
              this.activeTab = 'general';
            }
          } else {
            // Default to 'general' when coming from other pages (no route tab, no submitted tab)
            this.activeTab = 'general';
          }
        } else {
          // Default to 'general' if localStorage is not available
          this.activeTab = 'general';
        }
      } catch (e) {
        // Default to 'general' on error
        this.activeTab = 'general';
      }
    }

    // Hydrate keyboard shortcuts toggle from localStorage (per-device setting)
    this.enable_keyboard_shortcuts = posShortcutsEnabled();

    // Always load data regardless of which tab is active
    this.Get_Settings();
    this.syncDashboardSectionOrderList();
    this.Get_Payment_Gateway();
    this.Get_Pos_Settings();
    this.Get_SMS_Settings();
    this.Get_Appearance_Settings();
    this.Get_Mail_Settings();
    this.Get_Backups();

    Fire.$on("Event_Setting", () => {
      this.Get_Settings();
    });

    Fire.$on("Event_payment", () => {
      this.Get_Payment_Gateway();
    });

    Fire.$on("Event_Pos_Settings", () => {
      this.Get_Pos_Settings();
    });

    Fire.$on("Event_sms", () => {
      this.Get_SMS_Settings();
    });

    Fire.$on("Event_Appearance_Setting", () => {
      this.Get_Appearance_Settings();
    });

    Fire.$on("Event_Smtp", () => {
      this.Get_Mail_Settings();
    });

    Fire.$on("Generate_Backup", () => {
      setTimeout(() => {
        this.Get_Backups();
      }, 500);
    });

    Fire.$on("Delete_Backup", () => {
      setTimeout(() => {
        this.Get_Backups();
        NProgress.done();
      }, 500);
    });

  }
};
</script>

<style scoped>
.settings-container {
  border-radius: 0.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

/* A4 invoice logo live preview */
.invoice-logo-preview-box {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: #f9fafb;
  border: 1px dashed #d1d5db;
  border-radius: 6px;
  min-height: 80px;
}

.customize-toggle-row {
  padding: 14px 18px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fafafa;
}

.customize-toggle-title {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 4px;
}

.customize-toggle-hint {
  font-size: 12px;
  color: #6b7280;
  max-width: 680px;
}

/* Left Sidebar - Vertical Tabs */
.settings-sidebar {
  background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
  border-right: 1px solid #e9ecef;
  min-height: 600px;
}

.settings-tabs-nav {
  padding: 0;
}

.settings-header {
  padding: 1.5rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #ffffff;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.settings-header h5 {
  color: #ffffff;
  font-weight: 600;
  margin: 0;
}

.settings-nav {
  padding: 1rem 0;
  display: flex;
  flex-direction: column;
}

.settings-nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1.5rem;
  border: none;
  background: transparent;
  color: #495057;
  font-size: 0.9375rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  text-align: left;
  width: 100%;
  position: relative;
  border-left: 3px solid transparent;
}

.settings-nav-item i {
  font-size: 1.125rem;
  width: 20px;
  text-align: center;
}

.settings-nav-item:hover {
  background: rgba(102, 126, 234, 0.08);
  color: #667eea;
  border-left-color: #667eea;
}

.settings-nav-item.active {
  background: linear-gradient(90deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.1) 100%);
  color: #667eea;
  border-left-color: #667eea;
  font-weight: 600;
}

.settings-nav-item.active::before {
  content: '';
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 0;
  height: 0;
  border-top: 8px solid transparent;
  border-bottom: 8px solid transparent;
  border-right: 8px solid #ffffff;
}

/* Right Content Panel */
.settings-content {
  background: #ffffff;
}

.settings-content-wrapper {
  padding: 2rem;
  min-height: 600px;
}

.settings-content-header {
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #e9ecef;
}

.settings-content-header h4 {
  color: #2c3e50;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.settings-content-header p {
  font-size: 0.875rem;
  margin: 0;
}

.settings-content-body {
  position: relative;
}

.tab-content {
  animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Form Styling */
.tab-content .form-group {
  margin-bottom: 1.5rem;
}

.tab-content label {
  font-weight: 600;
  color: #495057;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.tab-content .form-control,
.tab-content .vs__dropdown-toggle {
  border-radius: 0.375rem;
  border: 1px solid #ced4da;
  transition: all 0.3s ease;
}

.tab-content .form-control:focus,
.tab-content .vs__dropdown-toggle:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

/* System Actions Card */
.system-actions-card {
  background: #f8f9fa;
  border-radius: 0.5rem;
  padding: 2rem;
  border: 1px solid #e9ecef;
}

.system-actions-card h5 {
  color: #2c3e50;
  font-weight: 600;
}

.action-btn-system {
  padding: 0.75rem 2rem;
  font-weight: 500;
  border-radius: 0.375rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.action-btn-system:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

/* Footer Submit Button */
.settings-footer {
  margin-top: 2.5rem;
  padding-top: 2rem;
  border-top: 2px solid #e9ecef;
  display: flex;
  justify-content: flex-end;
}

.submit-btn {
  padding: 0.75rem 2.5rem;
  font-weight: 600;
  border-radius: 0.375rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
  transition: all 0.3s ease;
}

.submit-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
}

.submit-btn:active {
  transform: translateY(0);
}

/* Custom Checkbox Switch Styling */
.custom-switch {
  padding-left: 2.5rem;
}

/* Mobile Tab Selector */
.mobile-tab-selector {
  padding: 1rem;
  background: #ffffff;
  border-bottom: 1px solid #e9ecef;
  position: relative;
  z-index: 1000;
}

/* Hide mobile tab selector when sidebar is open on mobile */
@media (max-width: 767.98px) {
  .mobile-tab-selector.sidebar-open {
    display: none !important;
  }
}

.mobile-tab-select {
  width: 100%;
  padding: 0.75rem 1rem;
  padding-right: 2.5rem;
  border: 1px solid #ced4da;
  border-radius: 0.375rem;
  font-size: 0.9375rem;
  font-weight: 500;
  background: #ffffff;
  color: #495057;
  cursor: pointer;
  min-height: 48px;
  -webkit-appearance: menulist;
  -moz-appearance: menulist;
  appearance: menulist;
  text-align: left;
  direction: ltr;
}

.mobile-tab-select:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
  outline: none;
}

/* Ensure dropdown options display fully */
.mobile-tab-select option {
  padding: 0.75rem 1rem;
  font-size: 0.9375rem;
  white-space: normal;
  word-wrap: break-word;
  overflow-wrap: break-word;
  height: auto;
  line-height: 1.5;
  display: block;
  text-align: left;
  direction: ltr;
  min-height: 44px;
}

/* Fix for Bootstrap select dropdown */
.mobile-tab-selector .custom-select,
.mobile-tab-selector select {
  background-image: none;
  background-position: right 0.75rem center;
  background-repeat: no-repeat;
  background-size: 8px 10px;
  padding-right: 2.5rem;
}

/* Ensure the select wrapper doesn't constrain the dropdown */
.mobile-tab-selector {
  overflow: visible !important;
}

.mobile-tab-selector * {
  overflow: visible !important;
}

/* Fix for mobile browsers that might clip the dropdown */
@media (max-width: 767.98px) {
  .mobile-tab-selector {
    position: relative;
    z-index: 1050;
  }
  
  .mobile-tab-select {
    position: relative;
    z-index: 1051;
  }
  
  /* Ensure dropdown menu appears above other content */
  .mobile-tab-select:focus {
    z-index: 1052;
  }
}

/* Responsive Design */
@media (max-width: 991.98px) {
  .settings-sidebar {
    border-right: none;
    border-bottom: 1px solid #e9ecef;
    min-height: auto;
  }

  .settings-content-wrapper {
    padding: 1.5rem;
  }

  .settings-content-header {
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
  }

  .settings-content-header h4 {
    font-size: 1.25rem;
  }

  .settings-content-header p {
    font-size: 0.8125rem;
  }
}

@media (max-width: 767.98px) {
  .settings-container {
    margin: 0 -15px;
  }

  .settings-content-wrapper {
    padding: 1rem;
  }

  .settings-content-header {
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
  }

  .settings-content-header h4 {
    font-size: 1.125rem;
  }

  .settings-footer {
    flex-direction: column;
    gap: 1rem;
  }

  .submit-btn {
    width: 100%;
  }

  .settings-tabs-nav {
    max-height: none;
  }

  .settings-header {
    padding: 1rem;
  }

  .settings-header h5 {
    font-size: 1rem;
  }
}

@media (max-width: 575.98px) {
  .settings-content-wrapper {
    padding: 0.75rem;
  }

  .settings-content-header h4 {
    font-size: 1rem;
  }

  .mobile-tab-selector {
    padding: 0.75rem;
    position: sticky !important;
    top: 0;
    z-index: unset !important;
    background: #ffffff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    overflow: visible !important;
  }

  .mobile-tab-select {
    font-size: 0.875rem;
    padding: 0.625rem 0.875rem;
    min-height: 44px;
    width: 100%;
  }
  
  .mobile-tab-select option {
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
    max-width: 100%;
  }

  /* Make form elements more mobile-friendly */
  .tab-content .form-group {
    margin-bottom: 1rem;
  }

  .tab-content label {
    font-size: 0.8125rem;
  }

  /* Adjust card padding on mobile */
  .system-actions-card {
    padding: 1.5rem;
  }

  /* Make tables responsive on mobile */
  .tab-content .vgt-table {
    font-size: 0.8125rem;
  }

  /* Stack form columns on mobile */
  .tab-content .row > [class*="col-"] {
    margin-bottom: 1rem;
  }

  /* Adjust button sizes on mobile */
  .tab-content .btn {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
  }
}

/* Custom Scrollbar for Sidebar */
.settings-nav::-webkit-scrollbar {
  height: 4px;
}

.settings-nav::-webkit-scrollbar-track {
  background: #f1f1f1;
}

.settings-nav::-webkit-scrollbar-thumb {
  background: #667eea;
  border-radius: 2px;
}

.settings-nav::-webkit-scrollbar-thumb:hover {
  background: #764ba2;
}

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

/* Responsive styles for POS receipt layout controls and preview (System Settings -> POS tab) */
@media (max-width: 768px) {
  /* Make layout radio buttons responsive */
  .tab-content .form-group {
    width: 100%;
  }

  .tab-content .btn-group-toggle.btn-group {
    display: flex;
    flex-wrap: wrap;
    width: 100%;
  }

  .tab-content .btn-group-toggle.btn-group .btn {
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
}

@media (max-width: 480px) {
  /* Stack layout buttons vertically on small screens */
  .tab-content .btn-group-toggle.btn-group {
    flex-direction: column;
  }

  .tab-content .btn-group-toggle.btn-group .btn {
    width: 100%;
    margin-bottom: 4px;
    border-radius: 0.25rem !important;
    font-size: 0.8rem;
    padding: 0.375rem 0.5rem;
    white-space: normal;
    word-wrap: break-word;
  }

  .tab-content .btn-group-toggle.btn-group .btn:last-child {
    margin-bottom: 0;
  }

  .pos-receipt-demo {
    padding: 8px;
    font-size: 9px;
  }

  .pos-receipt-demo .table_data {
    font-size: 9px !important;
  }

  .pos-receipt-demo small {
    word-wrap: break-word;
    overflow-wrap: break-word;
  }
}

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

/* Backup Tab Styles */
.backup-table-wrapper {
  background: #ffffff;
  border-radius: 0.5rem;
  overflow: hidden;
  border: 1px solid #e9ecef;
}

.btn-generate-backup {
  padding: 0.625rem 1.5rem;
  font-weight: 500;
  border-radius: 0.375rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.btn-generate-backup:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-delete-backup {
  padding: 0.375rem 0.75rem;
  border-radius: 0.375rem;
}

.btn-delete-backup:hover {
  transform: scale(1.05);
}

/* Dashboard Settings tab */
.dashboard-settings-tab .dashboard-settings-card {
  background: #f8f9fa;
  border: 1px solid #e9ecef;
}

.dashboard-widget-order-list {
  margin: 0;
  padding: 0;
  max-width: 560px;
}

.dashboard-widget-order-item {
  display: flex;
  align-items: center;
  padding: 0.65rem 0.9rem;
  margin-bottom: 0.5rem;
  background: #fff;
  border: 1px solid #e9ecef;
  border-radius: 0.5rem;
  list-style: none;
  cursor: default;
  transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.dashboard-widget-order-item:hover {
  background: #fff;
  border-color: #dee2e6;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

/* Ghost: placeholder left in the list while dragging */
.dashboard-widget-order-ghost {
  opacity: 0.45;
  background: #e9ecef !important;
  border: 2px dashed #adb5bd !important;
  border-radius: 0.5rem;
}

.dashboard-widget-order-ghost .widget-order-number,
.dashboard-widget-order-ghost .widget-order-label {
  opacity: 0.8;
}

/* Chosen: item when first picked up (still in list) */
.dashboard-widget-order-chosen {
  background: #f0f4ff !important;
  border-color: #8b9dc3 !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

/* Drag: the element that follows the cursor */
.dashboard-widget-order-drag {
  opacity: 1;
  background: #fff !important;
  border: 2px solid #8B5CF6 !important;
  box-shadow: 0 8px 24px rgba(139, 92, 246, 0.25) !important;
  border-radius: 0.5rem;
  cursor: grabbing !important;
}

.dashboard-widget-order-drag .drag-handle {
  cursor: grabbing !important;
  color: #8B5CF6;
}

.drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  margin-right: 0.75rem;
  color: #6c757d;
  cursor: grab;
  border-radius: 0.375rem;
  transition: background 0.15s ease, color 0.15s ease;
  flex-shrink: 0;
}

.drag-handle:hover {
  background: #e9ecef;
  color: #495057;
}

.drag-handle:active {
  cursor: grabbing;
}

.drag-handle .lucide-icon {
  width: 1.1rem;
  height: 1.1rem;
}

.widget-order-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.75rem;
  height: 1.75rem;
  margin-right: 0.75rem;
  padding: 0 0.35rem;
  font-size: 0.8125rem;
  font-weight: 700;
  color: #6c757d;
  background: #e9ecef;
  border-radius: 0.375rem;
  flex-shrink: 0;
}

.dashboard-widget-order-chosen .widget-order-number,
.dashboard-widget-order-drag .widget-order-number {
  background: #8B5CF6;
  color: #fff;
}

.widget-order-label {
  flex: 1;
  font-weight: 500;
  color: #212529;
  font-size: 0.9375rem;
}
</style>