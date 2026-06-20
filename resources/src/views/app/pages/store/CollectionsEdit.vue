<template>
  <div class="main-content">
  <breadcumb :page="$t('Edit_Collection')" :folder="$t('Store')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-else class="px-0">
      <b-form @submit.prevent="update">
        <!-- ROW 1: Header + Sticky side -->
        <div class="row no-gutters">
          <!-- LEFT: Collection header -->
          <div class="col-lg-8 p-3 p-lg-4">
            <div class="card card-soft shadow-sm mb-4">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                  <h5 class="mb-0">{{ $t('Collection_Details') }}</h5>
                  
                </div>

                <div class="row">
                  <div class="col-md-8">
                    <b-form-group :label="$t('Title')">
                      <b-form-input v-model.trim="form.title" @input="autoSlugIfEmpty" required />
                    </b-form-group>
                  </div>
                
                </div>

                <b-form-group :label="$t('Slug')">
                  <b-input-group>
                    <b-input-group-prepend is-text>/collections/</b-input-group-prepend>
                    <b-form-input v-model.trim="form.slug" required />
                  </b-input-group>
                </b-form-group>

                <b-form-group :label="$t('Description')">
                  <b-form-textarea rows="3" v-model.trim="form.description" />
                </b-form-group>

                <div class="row">
                  <div class="col-md-4">
                    <b-form-group :label="$t('Limit')">
                      <b-form-input type="text" min="1" v-model.number="form.limit" />
                    </b-form-group>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- RIGHT: Sticky actions -->
          <div class="col-lg-4 p-3 p-lg-4">
            <div class="side sticky-top">
              <div class="card shadow-sm">
                <div class="card-body">
                  <div class="d-grid gap-2">
                    <b-button :disabled="saving" type="submit" variant="btn btn-primary btn-block">
                      <span v-if="saving" class="spinner-border spinner-border-sm mr-2"></span>
                      <lucide-icon name="check" /> {{ $t('Save') }}
                    </b-button>
                    <b-button :disabled="saving" variant="btn btn-outline-secondary btn-block" @click="updateAndClose">
                      <lucide-icon name="check" /> {{ $t('Save_and_Close') }}
                    </b-button>
                    <router-link :to="{ name:'StoreCollections' }" class="btn btn-outline-dark btn-block">
                      {{ $t('Cancel') }}
                    </router-link>
                  </div>
                </div>
              </div>

              <div class="helper mt-3">
                <div class="small text-muted">
                  💡 {{ $t('Tip_reorder_products_for_priority') }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ROW 2: FULL-WIDTH Products_in_Collection -->
        <div class="row">
          <div class="col-12 p-3 p-lg-4">
            <div class="card card-soft shadow-sm">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <h5 class="mb-2">{{ $t('Products_in_Collection') }}</h5>
                  <div class="small text-muted">
                    {{ selected.length }} {{ $t('selected') }}
                    <span v-if="form.limit"> • {{ $t('Display_limit') }}: {{ form.limit }}</span>
                  </div>
                </div>

                <div class="row mt-2">
                  <!-- Search -->
                  <div class="col-lg-6">
                    <div class="finder border rounded p-3">
                      <div class="d-flex align-items-center justify-content-between">
                        <b-input-group>
                          <b-form-input
                            v-model.trim="productQuery"
                            :placeholder="$t('Search_products') + '…'"
                            @input="debouncedSearch"
                          />
                          <b-input-group-append>
                            <b-button :disabled="searching" variant="outline-secondary" @click="searchProducts">
                              <span v-if="searching" class="spinner-border spinner-border-sm mr-1"></span>
                              <lucide-icon name="search" v-else />
                            </b-button>
                          </b-input-group-append>
                        </b-input-group>
                      </div>

                      <div class="small text-muted mt-1" v-if="!searching && productQuery && !results.length">
                        {{ $t('No_results') }}
                      </div>

                      <div class="results-list mt-3">
                        <div
                          v-for="p in results"
                          :key="'r-'+p.id"
                          class="result-row"
                        >
                          <div class="d-flex align-items-center">
                          
                            <div class="text-truncate">
                              <div class="fw-600">
                                {{ p.name ? p.name : (p.title ? p.title : ('#'+p.id)) }}
                              </div>
                              <div class="small text-muted">
                                #{{ p.id }}
                                <span v-if="p.code || p.sku">• {{ p.code || p.sku }}</span>
                                <span v-if="Array.isArray(p.variants) && p.variants.length">• {{ p.variants.length }} {{ $t('variants') }}</span>
                              </div>
                            </div>
                          </div>
                          <div>
                            <b-button
                              size="sm"
                              variant="outline-primary"
                              @click="addProduct(p)"
                              :disabled="hasProduct(p.id)"
                            >
                              {{ hasProduct(p.id) ? $t('Added') : $t('Add') }}
                            </b-button>
                          </div>
                        </div>

                        <!-- Empty state -->
                        <div v-if="!productQuery && !results.length && !searching" class="empty-state mt-3">
                          <div class="emoji">🔎</div>
                          <div class="title">{{ $t('Start_typing_to_search') }}</div>
                          <div class="subtitle">{{ $t('Search_by_name_SKU_or_ID') }}</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Selected -->
                  <div class="col-lg-6 mt-3 mt-lg-0">
                    <div class="border rounded p-3">
                      <div v-if="!selected.length" class="empty-state">
                        <div class="emoji">🧺</div>
                        <div class="title">{{ $t('No_products_in_collection_yet') }}</div>
                        <div class="subtitle">{{ $t('Use_search_to_add_products') }}</div>
                      </div>

                      <div v-else class="table-responsive">
                        <table class="table table-sm align-middle">
                          <thead>
                            <tr>
                              <th style="width:60px">#</th>
                              <th>{{ $t('Product') }}</th>
                              <th class="text-right" style="width:220px">{{ $t('Actions') }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(item, idx) in selected" :key="'s-'+item.product_id">
                              <td><code class="small">{{ idx+1 }}</code></td>
                              <td>
                                <div class="d-flex align-items-center">
                                  <div class="thumb mr-2" v-if="item.thumb">
                                    <img :src="item.thumb" alt="thumb" />
                                  </div>
                                  <div>
                                    <div class="fw-600">{{ item.name }}</div>
                                    <small class="text-muted">#{{ item.product_id }} <span v-if="item.sku">• {{ item.sku }}</span></small>
                                  </div>
                                </div>
                              </td>
                            
                              <td class="text-right">
                                <div class="btn-group btn-group-sm">
                                  <b-button variant="outline-secondary" :disabled="idx===0" @click="move(idx,-1)">↑</b-button>
                                  <b-button variant="outline-secondary" :disabled="idx===selected.length-1" @click="move(idx,1)">↓</b-button>
                                  <b-button variant="outline-danger" @click="remove(idx)">{{ $t('Remove') }}</b-button>
                                </div>
                              </td>
                            </tr>
                          </tbody>
                        </table>

                        <div class="d-flex align-items-center justify-content-between mt-2">
                          <div class="small text-muted">
                            {{ $t('Order_determines_display_priority') }}
                          </div>
                          <div>
                            <b-button size="sm" variant="outline-danger" @click="clearSelected" :disabled="!selected.length">
                              {{ $t('Clear_all') }}
                            </b-button>
                          </div>
                        </div>

                      </div>
                    </div>
                  </div>
                </div><!-- /row -->
              </div>
            </div>
          </div>
        </div>
      </b-form>
    </b-card>
  </div>
</template>

<script>

export default {
  metaInfo: {
    title: "Store Collections Edit"
  },
  props: { id: { type: [String, Number], required: false } },

  data () {
    return {
      isLoading: true,
      saving: false,

      // search
      searching: false,
      productQuery: '',
      results: [],

      // form
      form: {
        title: '',
        slug: '',
        description: '',
        limit: 8,
        sort_order: 0,
      },

      // selected products [{product_id, name, sku, pinned, thumb}]
      selected: [],

      // debounce timer
      t: null
    }
  },

  mounted () {
    this.fetch()
  },

  methods: {
    makeToast (variant, msg, title) {
      if (this.$root && this.$root.$bvToast) {
        this.$root.$bvToast.toast(msg, { title, variant, solid: true })
      }
    },

    slugify (v) {
      return String(v || '')
        .toLowerCase()
        .trim()
        .replace(/['"]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
    },
    autoSlugIfEmpty () {
      if (!this.form.slug) this.form.slug = this.slugify(this.form.title)
    },


    // ---------- Fetch existing data ----------
    async fetch () {
      this.isLoading = true
      try {
        const collectionId = this.id || this.$route.params.id
        const resp = await axios.get(`/admin/store/collections/${collectionId}`)
        let c = (resp && resp.data && resp.data.data) ? resp.data.data : (resp ? resp.data : null)
        if (!c) c = {}

        this.form = {
          title: c.title || '',
          slug: c.slug || '',
          description: c.description || '',
          limit: (c.limit != null ? c.limit : 8),
          sort_order: (c.sort_order != null ? c.sort_order : 0),
        }

        const prods = Array.isArray(c.products) ? c.products.slice() : []
        const pivotMap = {}
        for (let k = 0; k < prods.length; k++) {
          const pp = prods[k]
          if (pp && pp.id && pp.pivot) {
            pivotMap[pp.id] = { sort_order: (pp.pivot.sort_order != null ? pp.pivot.sort_order : 0), pinned: !!pp.pivot.pinned }
          }
        }

        this.selected = prods.map(p => ({
          product_id: p.id,
          name: p.name || p.title || ('#' + p.id),
          sku: p.sku || p.code || '',
          pinned: !!(p.pivot && p.pivot.pinned),
        })).sort((a, b) => {
          const ao = pivotMap[a.product_id] ? pivotMap[a.product_id].sort_order : 0
          const bo = pivotMap[b.product_id] ? pivotMap[b.product_id].sort_order : 0
          return ao - bo
        })
      } catch (e) {
        this.makeToast('danger', this.$t('Failed_to_load'), this.$t('Failed'))
      } finally {
        this.isLoading = false
      }
    },

    // ---------- Search ----------
    debouncedSearch () {
      if (this.t) clearTimeout(this.t)
      this.t = setTimeout(() => { this.searchProducts() }, 300)
    },

    async searchProducts () {
      const q = (this.productQuery || '').trim()
      if (!q) { this.results = []; return }
      this.searching = true
      try {
        let resp = null
        try {
          resp = await axios.get('/admin/store/products', { params: { q, limit: 20} })
        } catch (e1) {
         
        }

        const payload = Array.isArray(resp?.data?.data)
          ? resp.data.data
          : (Array.isArray(resp?.data) ? resp.data : [])

        this.results = Array.isArray(payload) ? payload : []
      } catch (e) {
        this.makeToast('danger', this.$t('Failed_to_load'), this.$t('Failed'))
      } finally {
        this.searching = false
      }
    },

    hasProduct (id) {
      return this.selected.some(x => x.product_id === id)
    },
    addProduct (p) {
      if (!p || this.hasProduct(p.id)) return
      this.selected.push({
        product_id: p.id,
        name: p.name ? p.name : (p.title ? p.title : ('#' + p.id)),
        sku: p.sku || p.code || '',
        pinned: false,
      })
    },
    remove (idx) { this.selected.splice(idx, 1) },
    move (idx, dir) {
      const j = idx + dir
      if (j < 0 || j >= this.selected.length) return
      const row = this.selected.splice(idx, 1)[0]
      this.selected.splice(j, 0, row)
    },
    clearSelected () {
      if (!this.selected.length) return
      if (confirm(this.$t('Confirm_Clear_All'))) this.selected = []
    },

    itemsPayload () {
      return this.selected.map((x, i) => ({
        product_id: x.product_id,
        sort_order: (i + 1) * 10,
        pinned: !!x.pinned
      }))
    },

    // ---------- Update ----------
    async update () {
      if (!this.form.title || !this.form.slug) {
        this.makeToast('danger', this.$t('Title_and_Slug_required'), this.$t('Invalid'))
        return
      }

      this.saving = true
      try {
        const collectionId = this.id || this.$route.params.id

        await axios.put(`/admin/store/collections/${collectionId}`, this.form)

        if (this.selected.length) {
          try {
            await axios.post(`/admin/store/collections/${collectionId}/products`, { items: this.itemsPayload() })
          } catch (e) {
            this.makeToast('warning', this.$t('Collection_saved_but_products_not_synced'), this.$t('Warning'))
          }
        }

        this.makeToast('success', this.$t('Successfully_Updated'), this.$t('Success'))
      } catch (e) {
        this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'))
      } finally {
        this.saving = false
      }
    },

    async updateAndClose () {
      await this.update()
      this.$router.push({ name: 'StoreCollections' })
    }
  }
}
</script>

<style scoped>
.text-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }

.card-soft { border: 1px solid #edf2f7; border-radius: 12px; }
.finder { background: #fbfbfd; }
.results-list { max-height: 420px; overflow: auto; }

/* Result row */
.result-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
  padding: .5rem 0;
  border-bottom: 1px dashed #e5e7eb;
}
.result-row:last-child { border-bottom: 0; }
.result-row .thumb {
  width: 40px; height: 40px; border-radius: 6px; overflow: hidden;
  background: #f3f4f6; margin-right: .5rem; border: 1px solid #eef2f7;
}
.result-row .thumb img { width: 100%; height: 100%; object-fit: cover; }

/* Selected table thumb */
.table .thumb {
  width: 36px; height: 36px; border-radius: 6px; overflow: hidden;
  background: #f3f4f6; border: 1px solid #eef2f7;
}
.table .thumb img { width: 100%; height: 100%; object-fit: cover; }

/* Empty state */
.empty-state {
  border: 2px dashed #e2e8f0;
  border-radius: 1rem;
  padding: 2rem;
  text-align: center;
  background: #fafafa;
}
.empty-state .emoji { font-size: 1.8rem; }
.empty-state .title { font-weight: 700; margin-top: .25rem; }
.empty-state .subtitle { color: #6b7280; }

/* Sticky side */
.side { top: 88px; }
.btn-block { width: 100%; }

/* Helpers */
.fw-600 { font-weight: 600; }
</style>
