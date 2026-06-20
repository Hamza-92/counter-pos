<template>
  <div class="main-content">
    <breadcumb :page="$t('Pending_Customers')" :folder="$t('Store')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="wrapper">
      <!-- Actions Bar -->
      <b-card class="shadow-sm mb-3" no-body>
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem">
          <b-form-input
            v-model="search"
            :placeholder="$t('Search_by_name_or_email')"
            size="sm"
            style="max-width:280px"
            @input="debounceFetch"
          />
          <b-button v-if="customers.length" size="sm" variant="success" @click="approveAll" :disabled="saving">
            <lucide-icon class="mr-1" name="check" />{{ $t('Approve_All') }}
          </b-button>
        </div>
      </b-card>

      <!-- Empty State -->
      <b-card v-if="!customers.length" class="shadow-sm text-center py-5">
        <div style="font-size:2.5rem">✅</div>
        <h5 class="mt-2">{{ $t('No_Pending_Customers') }}</h5>
        <p class="text-muted">{{ $t('All_customer_registrations_have_been_reviewed') }}</p>
      </b-card>

      <!-- Table -->
      <b-card v-else class="shadow-sm" no-body>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="bg-light">
              <tr>
                <th>{{ $t('Customer') }}</th>
                <th>{{ $t('Email') }}</th>
                <th>{{ $t('Phone') }}</th>
                <th>{{ $t('Invite_Code_Used') }}</th>
                <th>{{ $t('Registered') }}</th>
                <th class="text-right">{{ $t('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in customers" :key="c.id">
                <td>
                  <strong>{{ (c.client && c.client.name) || c.username }}</strong>
                </td>
                <td>{{ c.email }}</td>
                <td>{{ (c.client && c.client.phone) || '—' }}</td>
                <td>
                  <code v-if="c.invite_code">{{ c.invite_code.code }}</code>
                  <span v-else class="text-muted">—</span>
                </td>
                <td>{{ formatDate(c.created_at) }}</td>
                <td class="text-right">
                  <b-button size="sm" variant="success" class="mr-1" :disabled="saving" @click="approve(c)">
                    <lucide-icon class="mr-1" name="check" />{{ $t('Approve') }}
                  </b-button>
                  <b-button size="sm" variant="outline-danger" :disabled="saving" @click="reject(c)">
                    <lucide-icon class="mr-1" name="x" />{{ $t('Reject') }}
                  </b-button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center" v-if="pagination.last_page > 1">
          <small class="text-muted">{{ $t('Page') }} {{ pagination.current_page }} / {{ pagination.last_page }}</small>
          <div>
            <b-button size="sm" variant="light" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">‹</b-button>
            <b-button size="sm" variant="light" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">›</b-button>
          </div>
        </div>
      </b-card>
    </div>
  </div>
</template>

<script>
export default {
  metaInfo: { title: 'Pending Customers' },
  data () {
    return {
      isLoading: true,
      saving: false,
      search: '',
      customers: [],
      pagination: { current_page: 1, last_page: 1 },
      debounceTimer: null
    }
  },
  mounted () { this.fetchCustomers() },
  methods: {
    makeToast (variant, msg, title) {
      this.$root.$bvToast && this.$root.$bvToast.toast(msg, { title: title, variant: variant, solid: true })
    },
    formatDate (d) {
      if (!d) return ''
      var dt = new Date(d)
      return dt.toLocaleDateString() + ' ' + dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    },
    debounceFetch () {
      clearTimeout(this.debounceTimer)
      this.debounceTimer = setTimeout(this.fetchCustomers, 400)
    },
    async fetchCustomers (page) {
      try {
        this.isLoading = this.customers.length === 0
        var params = { per_page: 15, page: page || 1 }
        if (this.search) params.search = this.search
        var resp = await axios.get('/store/pending-customers', { params: params })
        this.customers = resp.data.data || []
        this.pagination = { current_page: resp.data.current_page, last_page: resp.data.last_page }
      } catch (e) {
        this.makeToast('danger', this.$t('Failed'), this.$t('Error'))
      } finally {
        this.isLoading = false
      }
    },
    goPage (p) { this.fetchCustomers(p) },
    async approve (c) {
      this.saving = true
      try {
        await axios.post('/store/pending-customers/' + c.id + '/approve')
        this.makeToast('success', c.email + ' ' + this.$t('approved'), this.$t('Success'))
        await this.fetchCustomers(this.pagination.current_page)
      } catch (e) {
        this.makeToast('danger', this.$t('Failed'), this.$t('Error'))
      } finally {
        this.saving = false
      }
    },
    async reject (c) {
      if (!confirm(this.$t('Confirm_Reject_Customer') + '\n' + c.email)) return
      this.saving = true
      try {
        await axios.post('/store/pending-customers/' + c.id + '/reject')
        this.makeToast('success', c.email + ' ' + this.$t('rejected'), this.$t('Success'))
        await this.fetchCustomers(this.pagination.current_page)
      } catch (e) {
        this.makeToast('danger', this.$t('Failed'), this.$t('Error'))
      } finally {
        this.saving = false
      }
    },
    async approveAll () {
      if (!confirm(this.$t('Confirm_Approve_All_Pending'))) return
      this.saving = true
      try {
        var resp = await axios.post('/store/pending-customers/approve-all')
        var count = (resp.data && resp.data.approved_count) || 0
        this.makeToast('success', count + ' ' + this.$t('customers_approved'), this.$t('Success'))
        await this.fetchCustomers()
      } catch (e) {
        this.makeToast('danger', this.$t('Failed'), this.$t('Error'))
      } finally {
        this.saving = false
      }
    }
  }
}
</script>
