<template> 
  <div class="main-content">
    <breadcumb :page="$t('Settings')" :folder="$t('Store')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="wrapper">
      <b-form @submit.prevent="save">
        <!-- ===== Store Basics ===== -->
        <b-card class="settings-card shadow-sm mb-3" no-body>
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="h6 mb-0">{{ $t('Store_Basics') }}</div>
            <b-badge pill variant="light">#1</b-badge>
          </div>
          <div class="card-body">
            <div class="row">

              <!-- Enable / Disable Online Store URL -->
              <div class="col-md-4">
                <b-form-group label="Online Store URL">
                  <b-form-checkbox v-model="form.enabled" switch>
                    {{ form.enabled ? 'Enabled' : 'Disabled' }}
                  </b-form-checkbox>
                  <small class="text-muted d-block mt-1">
                    When disabled, the /online_store pages will be inaccessible.
                  </small>
                </b-form-group>
              </div>

              <div class="col-md-4">
                <b-form-group :label="$t('Store_Name')">
                  <b-form-input v-model="form.store_name"/>
                </b-form-group>
              </div>


             <b-col lg="4" md="4" sm="12">
              <b-form-group :label="$t('Currency')">
                <v-select
                  v-model="form.default_currency_id"
                  :reduce="label => label.value"
                  :options="currencies.map(currencies => ({
                    label: currencies.name + ' (' + currencies.symbol + ')',
                    value: currencies.id
                  }))"
                  :placeholder="$t('Choose_Currency')"
                  :clearable="false"
                />
                <small  class="text-warning d-block mt-1">
                  ⚠️ Changing currency will affect both system and online store
                </small>
              </b-form-group>
            </b-col>


              <!-- Default Warehouse -->
              <b-col lg="4" md="4" sm="12">
                <b-form-group :label="$t('DefaultWarehouse')">
                  <v-select
                    v-model="form.default_warehouse_id"
                    :options="warehouseOptions"
                    :reduce="opt => opt.value"
                    :placeholder="$t('Choose_Warehouse')"
                    :clearable="false"
                  />
                </b-form-group>
              </b-col>

              <div class="col-md-4">
                <b-form-group :label="$t('Primary_Color')">
                  <b-form-input type="color" v-model="form.primary_color"/>
                </b-form-group>
              </div>
              <div class="col-md-4">
                <b-form-group :label="$t('Secondary_Color')">
                  <b-form-input type="color" v-model="form.secondary_color"/>
                </b-form-group>
              </div>
              <div class="col-md-4">
                <b-form-group :label="$t('Font_Family')">
                  <b-form-input v-model="form.font_family"/>
                </b-form-group>
              </div>

              <!-- Registration Access Control -->
              <div class="col-md-12 mt-2">
                <h6 class="text-muted border-bottom pb-2 mb-3"><lucide-icon class="mr-1" name="lock" /> {{ $t('Registration_Access_Control') }}</h6>
              </div>
              <div class="col-md-4">
                <b-form-group :label="$t('Public_Registration')">
                  <b-form-checkbox v-model="form.registration_enabled" switch>
                    {{ form.registration_enabled ? $t('Enabled') : $t('Disabled') }}
                  </b-form-checkbox>
                  <small class="text-muted d-block mt-1">
                    {{ $t('Public_Registration_Help') }}
                  </small>
                </b-form-group>
              </div>
              <div class="col-md-4">
                <b-form-group :label="$t('Require_Invite_Code')">
                  <b-form-checkbox v-model="form.require_invite_code" switch>
                    {{ form.require_invite_code ? $t('Yes') : $t('No') }}
                  </b-form-checkbox>
                  <small class="text-muted d-block mt-1">
                    {{ $t('Require_Invite_Code_Help') }}
                  </small>
                </b-form-group>
              </div>
              <div class="col-md-4">
                <b-form-group :label="$t('Require_Admin_Approval')">
                  <b-form-checkbox v-model="form.require_admin_approval" switch>
                    {{ form.require_admin_approval ? $t('Yes') : $t('No') }}
                  </b-form-checkbox>
                  <small class="text-muted d-block mt-1">
                    {{ $t('Require_Admin_Approval_Help') }}
                  </small>
                </b-form-group>
              </div>
              <div class="col-md-12" v-if="pendingCustomersCount > 0">
                <div class="alert alert-warning d-flex align-items-center justify-content-between py-2 mb-3">
                  <div>
                    <lucide-icon class="mr-1" name="clock" />
                    <strong>{{ pendingCustomersCount }}</strong> {{ $t('Pending_Customers_Awaiting_Approval') }}
                  </div>
                  <router-link :to="{name: 'StorePendingCustomers'}" class="btn btn-sm btn-outline-warning">
                    {{ $t('Review') }}
                  </router-link>
                </div>
              </div>
              <div class="col-md-12" v-if="form.require_invite_code">
                <div class="d-flex align-items-center justify-content-between bg-light rounded p-2 mb-3">
                  <div class="small text-muted">
                    <lucide-icon class="mr-1" name="ticket" /> {{ $t('Manage_invite_codes_from_dedicated_page') }}
                  </div>
                  <router-link :to="{name: 'StoreInviteCodes'}" class="btn btn-sm btn-outline-primary">
                    {{ $t('Manage_Invite_Codes') }}
                  </router-link>
                </div>
              </div>

              <!-- Allow overselling -->
              <div class="col-md-12">
                <b-form-group :label="$t('Stock_behavior')">
                  <b-form-checkbox v-model="form.allow_overselling" switch>
                    {{ form.allow_overselling ? $t('Allow_overselling') : $t('Prevent_overselling') }}
                  </b-form-checkbox>
                  <small class="text-muted d-block mt-1">
                    {{ $t('Allow_overselling_help') }}
                  </small>
                </b-form-group>
                <div class="col-md-12">
                  <b-form-group :label="$t('Hide_out_of_stock')">
                    <b-form-checkbox v-model="form.hide_out_of_stock" switch>
                      {{ form.hide_out_of_stock ? $t('Yes') : $t('No') }}
                    </b-form-checkbox>
                    <small class="text-muted d-block mt-1">
                      {{ $t('Hide_out_of_stock_help') }}
                    </small>
                  </b-form-group>
                </div>
                <div class="col-md-12">
                  <b-form-group :label="$t('Hide_prices_for_guests')">
                    <b-form-checkbox v-model="form.hide_prices_for_guests" switch>
                      {{ form.hide_prices_for_guests ? $t('Yes') : $t('No') }}
                    </b-form-checkbox>
                    <small class="text-muted d-block mt-1">
                      {{ $t('Hide_prices_for_guests_help') }}
                    </small>
                  </b-form-group>
                </div>
                <div class="col-md-12">
                  <b-form-group :label="$t('Show_stock')">
                    <b-form-checkbox v-model="form.show_stock" switch>
                      {{ form.show_stock ? $t('Yes') : $t('No') }}
                    </b-form-checkbox>
                    <small class="text-muted d-block mt-1">
                      {{ $t('Show_stock_help') }}
                    </small>
                  </b-form-group>
                </div>
              </div>
            </div>
          </div>
        </b-card>

        <!-- ===== Contact ===== -->
        <b-card class="settings-card shadow-sm mb-3" no-body>
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="h6 mb-0">{{ $t('Contact') }}</div>
            <b-badge pill variant="light">#2</b-badge>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4">
                <b-form-group :label="$t('Contact_Email')">
                  <b-form-input v-model="form.contact_email"/>
                </b-form-group>
              </div>
              <div class="col-md-4">
                <b-form-group :label="$t('Contact_Phone')">
                  <b-form-input v-model="form.contact_phone"/>
                </b-form-group>
              </div>
              <div class="col-md-4">
                <b-form-group :label="$t('Contact_Address')">
                  <b-form-input v-model="form.contact_address"/>
                </b-form-group>
              </div>
            </div>
          </div>
        </b-card>

        <!-- ===== Branding (Logo / Favicon) ===== -->
        <b-card class="settings-card shadow-sm mb-3" no-body>
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="h6 mb-0">{{ $t('Branding') }}</div>
            <b-badge pill variant="light">#3</b-badge>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4">
                <b-form-group :label="$t('Logo')">
                  <b-form-file accept="image/*" @change="pick('logo',$event)"/>
                  <img v-if="settings.logo_path" :src="asset(settings.logo_path)" height="40" class="mt-2 rounded shadow-sm"/>
                </b-form-group>
              </div>
              <div class="col-md-4">
                <b-form-group :label="$t('Favicon')">
                  <b-form-file accept="image/*" @change="pick('favicon',$event)"/>
                  <img v-if="settings.favicon_path" :src="asset(settings.favicon_path)" height="24" class="mt-2 rounded shadow-sm"/>
                </b-form-group>
              </div>
            </div>
          </div>
        </b-card>

        <!-- ===== Hero ===== -->
        <b-card class="settings-card shadow-sm mb-3" no-body>
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="h6 mb-0">{{ $t('Hero_Header') }}</div>
            <b-badge pill variant="light">#4</b-badge>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-12">
                <b-form-group :label="$t('Hero_Title')">
                  <b-form-input v-model="form.hero_title"/>
                </b-form-group>
              </div>
              <div class="col-md-12">
                <b-form-group :label="$t('Hero_Subtitle')">
                  <b-form-textarea rows="2" v-model="form.hero_subtitle"/>
                </b-form-group>
              </div>
              <div class="col-md-6">
                <b-form-group :label="$t('Hero_Image')">
                  <b-form-file accept="image/*" @change="pick('hero_image',$event)"/>
                  <img v-if="settings.hero_image_path" :src="asset(settings.hero_image_path)" height="64" class="mt-2 rounded shadow-sm"/>
                </b-form-group>
              </div>
            </div>
          </div>
        </b-card>

        <!-- ===== SEO ===== -->
        <b-card class="settings-card shadow-sm mb-3" no-body>
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="h6 mb-0">SEO</div>
            <b-badge pill variant="light">#5</b-badge>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-12">
                <b-form-group :label="$t('SEO_Title')">
                  <b-form-input v-model="form.seo_meta_title"/>
                </b-form-group>
              </div>
              <div class="col-md-12">
                <b-form-group :label="$t('SEO_Description')">
                  <b-form-textarea rows="2" v-model="form.seo_meta_description"/>
                </b-form-group>
              </div>
            </div>
          </div>
        </b-card>

        <!-- ===== Topbar & Footer ===== -->
        <b-card class="settings-card shadow-sm mb-3" no-body>
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="h6 mb-0">{{ $t('Topbar_and_Footer') }}</div>
            <b-badge pill variant="light">#6</b-badge>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <b-form-group :label="$t('Topbar_Text_Left')">
                  <b-form-input v-model="form.topbar_text_left"/>
                </b-form-group>
              </div>
              <div class="col-md-6">
                <b-form-group :label="$t('Topbar_Text_Right')">
                  <b-form-input v-model="form.topbar_text_right"/>
                </b-form-group>
              </div>

              <div class="col-md-12">
                <b-form-group :label="$t('Footer_Text')">
                  <b-form-textarea rows="2" v-model="form.footer_text"/>
                </b-form-group>
              </div>
            </div>
          </div>
        </b-card>

        <!-- ===== Social Links ===== -->
        <b-card class="settings-card shadow-sm mb-3" no-body>
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="h6 mb-0">{{ $t('Social_Links') }}</div>
            <b-button size="sm" variant="outline-success" @click="form.social_links.push({platform:'',url:''})">
              + {{ $t('Add_Link') }}
            </b-button>
          </div>
          <div class="card-body">
            <div v-if="!form.social_links.length" class="text-muted small mb-2">{{ $t('No_items') }}</div>
            <div v-for="(link, i) in form.social_links" :key="'soc-'+i" class="d-flex mb-2 align-items-center section-row">
              <span class="pill-type mr-2">🔗</span>
              <b-form-input v-model="link.platform" placeholder="Platform (e.g. facebook)" class="mr-2"/>
              <b-form-input v-model="link.url" placeholder="URL (https://…)" class="mr-2"/>
              <b-button size="sm" variant="danger" @click="form.social_links.splice(i,1)">×</b-button>
            </div>
          </div>
        </b-card>

        <!-- ===== Homepage Blocks (Hero + Collections + Newsletter) ===== -->
        <b-card class="settings-card shadow-sm mb-3" no-body>
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="h6 mb-0">{{ $t('Homepage_Blocks') }}</div>
            <small class="text-muted">{{ $t('Toggle_to_show_on_home_and_use_arrows_to_reorder') }}</small>
          </div>
          <div class="card-body">
            <div v-if="!homeRows.length" class="empty-state my-2">
              <div class="emoji">🧩</div>
              <div class="title">{{ $t('No_items') }}</div>
            </div>

            <transition-group name="fade" tag="div">
              <div v-for="(row, idx) in homeRows" :key="row.key" class="simple-row">
                <div class="left">
                  <div class="d-flex align-items-center" style="gap:.4rem;">
                    <strong class="text-dark text-truncate" :title="row.title">{{ row.title }}</strong>
                    <b-badge pill :variant="badgeVariant(row.kind)">{{ labelFor(row.kind) }}</b-badge>
                    <b-badge v-if="row.kind==='collection' && row.products_count!=null" variant="light" pill>
                      {{ $t('Products') }}: {{ row.products_count }}
                    </b-badge>
                    <b-badge v-if="row.kind!=='collection' && row.warning" variant="warning" pill>⚠︎ {{ $t('Incomplete') }}</b-badge>
                  </div>
                 
                </div>

                <div class="right">
                  <b-form-checkbox v-model="row.active" switch class="mr-2">
                    {{ row.active ? $t('Active') : $t('Inactive') }}
                  </b-form-checkbox>

                  <div class="btn-group">
                    <b-button size="sm" variant="light" @click="move(idx,-1)" :disabled="idx===0">↑</b-button>
                    <b-button size="sm" variant="light" @click="move(idx,1)" :disabled="idx===homeRows.length-1">↓</b-button>
                  </div>
                </div>
              </div>
            </transition-group>
          </div>
        </b-card>

        <!-- Sticky Save Bar -->
        <div class="savebar shadow-sm">
          <div class="d-flex align-items-center justify-content-between">
            <small class="text-muted">{{ $t('Unsaved_changes_may_be_lost') }}</small>
            <b-button :disabled="saving" type="submit" variant="btn btn-primary">
              <span v-if="saving" class="spinner-border spinner-border-sm mr-2"/>
              <lucide-icon name="check" /> {{ $t('Save') }}
            </b-button>
          </div>
        </div>
      </b-form>
    </div>
  </div>
</template>

<script>

export default {
  metaInfo: {
    title: "Store Settings"
  },
  data() {
    return {
      isLoading: true,
      saving: false,
      settings: {},

      // Authoritative collections from backend
      collections: [],
      // Unified UI list: hero + all collections + newsletter
      homeRows: [],
      warehouses: [],
      currencies: [],

      pendingCustomersCount: 0,

      form: {
        enabled: true,
        registration_enabled: true,
        require_invite_code: false,
        require_admin_approval: false,
        store_name: '',
        primary_color: '#6c5ce7',
        secondary_color: '#00c2ff',
        font_family: 'Arial, sans-serif',
        language: 'en',
        default_warehouse_id: '',
        default_currency_id: '',
        contact_email: '',
        contact_phone: '',
        contact_address: '',
        hero_title: '',
        hero_subtitle: '',
        seo_meta_title: '',
        seo_meta_description: '',
        topbar_text_left: '',
        topbar_text_right: '',
        footer_text: '',
        // Single source of truth for homepage order/visibility
        homepage_lineup: [],
        homepage_layout: 'default',
        allow_overselling: true,
        hide_out_of_stock: false,
        hide_prices_for_guests: false,
        show_stock: true,
        menus: { header: [], footer_shop: [], footer_support: [] },
        social_links: [],
        custom_css: '',
        custom_js: '',
        store_slug: 'online_store',
      },
      files: {},
      langs: [
        { value: 'en', text: 'English' },
        { value: 'fr', text: 'Français' },
        { value: 'ar', text: 'العربية' }
      ],
    }
  },
   computed: {
    // For vue-select
    warehouseOptions () {
      var arr = Array.isArray(this.warehouses) ? this.warehouses : []
      return arr.map(function (w) {
        return { label: w.name, value: Number(w.id) }
      })
    },
    // For b-form-select
    warehouseOptionsBV () {
      var arr = Array.isArray(this.warehouses) ? this.warehouses : []
      return arr.map(function (w) {
        return { text: w.name, value: Number(w.id) }
      })
    }
  },
  mounted(){ this.fetch() },
  methods:{
    makeToast(variant,msg,title){
      this.$root.$bvToast?.toast(msg,{title,variant,solid:true})
    },
    asset(p){
      if (!p) return ''
      if (p.startsWith('images/')) return `/${p}`
      if (p.startsWith('/')) return p
      return `/storage/${p}`
    },
    pick(key,e){ this.files[key] = e.target.files[0] },

    // --------- UI helpers ----------
    badgeVariant(kind){
      if (kind === 'collection') return 'info'
      if (kind === 'hero') return 'primary'
      if (kind === 'newsletter') return 'success'
      return 'light'
    },
    labelFor(kind){
      if (kind === 'collection') return this.$t('Collection')
      if (kind === 'hero') return this.$t('Hero')
      if (kind === 'newsletter') return this.$t('Newsletter')
      return kind
    },
    move(idx,dir){
      const j = idx + dir
      if (j < 0 || j >= this.homeRows.length) return
      const a = this.homeRows
      const [item] = a.splice(idx,1)
      a.splice(j,0,item)
    },
    collectionUrl(slug){
      return `/${this.form.store_slug ? this.form.store_slug + '/' : ''}collections/${slug}`
    },

    // --------- Normalizers ----------
    tryParseJson(v){ if (!v || typeof v!=='string') return null; try{ return JSON.parse(v) }catch{ return null } },
    normalizeBool(v){
      if (typeof v === 'boolean') return v
      return v === 1 || v === '1' || v === 'true' || v === 'on'
    },
    normalizeMenus(v) {
      const x = (typeof v==='string') ? this.tryParseJson(v) : v
      if (!x || typeof x !== 'object' || Array.isArray(x)) {
        return { header: [], footer_shop: [], footer_support: [] }
      }
      return {
        header: Array.isArray(x.header) ? x.header : [],
        footer_shop: Array.isArray(x.footer_shop) ? x.footer_shop : [],
        footer_support: Array.isArray(x.footer_support) ? x.footer_support : [],
      }
    },
    normalizeSocialLinks(v) {
      const x = (typeof v==='string') ? this.tryParseJson(v) : v
      if (!x) return []
      if (Array.isArray(x)) return x.map(o => ({ platform: o.platform || '', url: o.url || '' }))
      if (typeof x === 'object') return Object.entries(x).map(([platform, url]) => ({ platform, url }))
      return []
    },
    // Your API shape (slug, is_active, sort_order, limit, products_count)
    normalizeCollectionsArray(cols){
      return (cols || []).map(c=>({
        id: c.id ?? null,
        key: `collection:${String(c.slug || c.handle || '').trim()}`,
        kind: 'collection',
        slug: String(c.slug || c.handle || '').trim(),
        title: c.title || c.slug || this.$t('Untitled'),
        products_count: c.products_count ?? null,
        limit: Number(c.limit ?? 8),
        is_active: this.normalizeBool(c.is_active ?? false),
        sort_order: Number(c.sort_order ?? 9999),
      })).filter(c=>!!c.slug)
    },

    // --------- Build the unified Home list ----------
    buildHomeRows(){
      const rows = []
      const lineup = Array.isArray(this.form.homepage_lineup) ? this.form.homepage_lineup : []

      // Prepare the three “sources”
      const heroConfigured = !!(this.form.hero_title || this.settings.hero_image_path)
      const heroRow = {
        key: 'hero',
        kind: 'hero',
        title: this.form.hero_title || this.$t('Hero'),
        active: false,
        warning: !heroConfigured
      }

      const newsletterRow = {
        key: 'newsletter',
        kind: 'newsletter',
        title: this.$t('Newsletter'),
        active: false,
        warning: false
      }

      const collectionBySlug = new Map(this.collections.map(c => [c.slug, c]))
      const used = new Set()

      if (lineup.length) {
        // 1) Place items exactly as saved
        lineup.forEach(item => {
          if (!item || !item.type) return
          if (item.type === 'hero') { rows.push({ ...heroRow, active: true }); used.add('hero') }
          else if (item.type === 'newsletter') { rows.push({ ...newsletterRow, active: true }); used.add('newsletter') }
          else if (item.type === 'collection' && item.slug) {
            const c = collectionBySlug.get(String(item.slug))
            if (c) { rows.push({ ...c, key: `collection:${c.slug}`, kind:'collection', active: true }); used.add(`collection:${c.slug}`) }
          }
        })
        // 2) Append the rest (not in lineup)
        if (!used.has('hero')) rows.push(heroRow)
        if (!used.has('newsletter')) rows.push(newsletterRow)
        this.collections
          .filter(c => !used.has(`collection:${c.slug}`))
          .sort((a,b)=> (a.sort_order - b.sort_order) || a.title.localeCompare(b.title))
          .forEach(c => rows.push({ ...c, key:`collection:${c.slug}`, kind:'collection', active: this.normalizeBool(c.is_active) }))
      } else {
        // No saved lineup yet → default order
        rows.push({ ...heroRow, active: false })
        this.collections
          .slice()
          .sort((a,b)=> (a.sort_order - b.sort_order) || a.title.localeCompare(b.title))
          .forEach(c => rows.push({ ...c, key:`collection:${c.slug}`, kind:'collection', active: this.normalizeBool(c.is_active) }))
        rows.push({ ...newsletterRow, active: false })
      }

      this.homeRows = rows
    },

    // --------- IO ----------
    async fetch () {
      try {
        this.isLoading = true

        const resp = await axios.get('/admin/store/settings')
        const payload = (resp && resp.data) ? resp.data : {}

        // read new shape (fallback to old for compatibility)
        const settings = (payload && payload.settings) ? payload.settings : payload
        const warehouses = Array.isArray(payload && payload.warehouses) ? payload.warehouses : []
        const currencies = Array.isArray(payload && payload.currencies) ? payload.currencies : []

        this.settings = settings || {}
        this.warehouses = warehouses
        this.currencies = currencies
        this.pendingCustomersCount = (payload && payload.pending_customers_count) || 0

        // build form (no spread / optional chaining)
        var merged = Object.assign({}, this.form, settings)

        merged.enabled        = this.normalizeBool(settings && settings.enabled)
        merged.registration_enabled = this.normalizeBool(settings && (settings.registration_enabled ?? true))
        merged.require_invite_code = this.normalizeBool(settings && (settings.require_invite_code ?? false))
        merged.require_admin_approval = this.normalizeBool(settings && (settings.require_admin_approval ?? false))
        merged.menus          = this.normalizeMenus(settings && settings.menus)
        merged.social_links   = this.normalizeSocialLinks(settings && settings.social_links)
        merged.store_slug     = (settings && settings.store_slug) ? settings.store_slug : this.form.store_slug

        var lineupRaw = settings && settings.homepage_lineup
        merged.homepage_lineup = Array.isArray(lineupRaw)
          ? lineupRaw
          : (this.tryParseJson(lineupRaw) || [])

        merged.homepage_layout = 'default'
        merged.allow_overselling = this.normalizeBool(settings && settings.allow_overselling)
        merged.hide_out_of_stock = this.normalizeBool(settings && settings.hide_out_of_stock)
        merged.hide_prices_for_guests = this.normalizeBool(settings && settings.hide_prices_for_guests)
        merged.show_stock = this.normalizeBool(settings && (settings.show_stock ?? true))

        // Ensure default_warehouse_id is a Number; pick first if empty
        if (merged.default_warehouse_id != null) {
          merged.default_warehouse_id = Number(merged.default_warehouse_id)
        } else if (this.warehouses.length) {
          merged.default_warehouse_id = Number(this.warehouses[0].id)
        } else {
          merged.default_warehouse_id = null
        }

         // Ensure default_currency_id is a Number; pick first if empty
        if (merged.default_currency_id != null) {
          merged.default_currency_id = Number(merged.default_currency_id)
        } else if (this.currencies.length) {
          merged.default_currency_id = Number(this.currencies[0].id)
        } else {
          merged.default_currency_id = null
        }

        this.form = merged

        // Collections: from settings or endpoint fallback (unchanged)
        var cols = []
        var dataCollections = settings && settings.collections
        if (Array.isArray(dataCollections) && dataCollections.length) {
          cols = dataCollections
        } else {
          try {
            const res = await axios.get('/admin/store/collections?include_counts=1')
            cols = Array.isArray(res && res.data) ? res.data : []
          } catch (e) { cols = [] }
        }
        this.collections = this.normalizeCollectionsArray(cols)

        this.buildHomeRows()
      } catch (e) {
        this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'))
      } finally {
        this.isLoading = false
      }
    },

    async save () {
      this.saving = true
      try {
        // 1) Build homepage_lineup from active rows (keep order)
        var lineup = []
        for (var i = 0; i < this.homeRows.length; i++) {
          var r = this.homeRows[i]
          if (!r || !r.active) continue
          if (r.kind === 'hero') {
            lineup.push({ type: 'hero' })
          } else if (r.kind === 'newsletter') {
            lineup.push({ type: 'newsletter' })
          } else if (r.kind === 'collection') {
            lineup.push({
              type: 'collection',
              slug: r.slug,
              limit: r.limit ? Number(r.limit) : 8,
              layout: 'grid',
              title_override: ''
            })
          }
        }
        this.form.homepage_lineup = lineup

        // 2) Ensure numeric fields are numbers (esp. default_warehouse_id)
        if (this.form.default_warehouse_id != null && this.form.default_warehouse_id !== '') {
          this.form.default_warehouse_id = Number(this.form.default_warehouse_id)
        } else {
          this.form.default_warehouse_id = null
        }

        if (this.form.default_currency_id != null && this.form.default_currency_id !== '') {
          this.form.default_currency_id = Number(this.form.default_currency_id)
        } else {
          this.form.default_currency_id = null
        }

        // 3) Build FormData (Vue 2 compatible)
        var fd = new FormData()
        var jsonFields = ['menus', 'social_links', 'homepage_lineup']

        for (var k in this.form) {
          if (!Object.prototype.hasOwnProperty.call(this.form, k)) continue
          var v = this.form[k]

          // Booleans as 1/0 for Laravel convenience
          if (typeof v === 'boolean') {
            fd.append(k, v ? 1 : 0)
            continue
          }

          // JSON fields (arrays/objects) → stringify
          if (jsonFields.indexOf(k) !== -1) {
            try {
              fd.append(k, JSON.stringify(v || []))
            } catch (e) {
              fd.append(k, '[]')
            }
            continue
          }

          // Null/undefined → empty string
          if (v === null || typeof v === 'undefined') {
            fd.append(k, '')
            continue
          }

          // Everything else (numbers/strings) as-is
          fd.append(k, v)
        }

        // 4) Attach files (if any)
        for (var fk in this.files) {
          if (!Object.prototype.hasOwnProperty.call(this.files, fk)) continue
          var f = this.files[fk]
          if (f) fd.append(fk, f)
        }

        // 5) POST (multipart) — Laravel will read request->all()/files normally
        await axios.post('/admin/store/settings', fd, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })

        this.makeToast('success', this.$t('Successfully_Updated'), this.$t('Success'))
        await this.fetch()   // reload latest (also refreshes warehouses/default if backend changed)
        this.files = {}
      } catch (e) {
        this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'))
      } finally {
        this.saving = false
      }
    }

  }
}
</script>

<style scoped>
/* Cards */
.settings-card .card-header{
  background: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
}
.settings-card .card-body{
  background: #fff;
}

/* Social links row */
.section-row {
  background: #f8fafc;
  border: 1px dashed #e2e8f0;
  border-radius: .5rem;
  padding: .5rem;
}
.pill-type {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  padding: .15rem .5rem;
  font-size: .85rem;
}

/* Homepage blocks list */
.empty-state {
  border: 2px dashed #e2e8f0;
  border-radius: 1rem;
  padding: 1.25rem;
  text-align: center;
  background: #fafafa;
  color: #6b7280;
}
.empty-state .emoji { font-size: 1.6rem; }
.empty-state .title { font-weight: 700; margin-top: .2rem; }

.simple-row{
  display:flex; align-items:center; justify-content:space-between;
  padding:.75rem .8rem; background:#fff; border:1px solid #e5e7eb;
  border-radius:.9rem; margin-bottom:.6rem;
}
.simple-row .left{ min-width:0; }
.muted-row { color:#6b7280; font-size:.85rem; }
.a-muted { color:#6b7280; }
.a-muted:hover { color:#111827; text-decoration:none; }

.fade-enter-active, .fade-leave-active { transition: all .15s ease; }
.fade-enter, .fade-leave-to { opacity:0; transform: translateY(-4px); }

/* Sticky Save Bar */
.savebar{
  position: sticky;
  bottom: 0;
  background: linear-gradient(180deg, rgba(255,255,255,.0), rgba(255,255,255,.95) 20%);
  padding: .75rem 1rem;
  border-top: 1px solid #e5e7eb;
  border-radius: .75rem;
}
</style>
