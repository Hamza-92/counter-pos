<template>
  <div class="main-content">
    <breadcumb :page="$t('Messages')" :folder="$t('Store')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-else class="wrapper">

      <!-- Table header actions -->
      <div class="messages-toolbar d-flex flex-wrap align-items-center justify-content-between mb-3">
        <div class="d-flex flex-wrap align-items-center messages-toolbar__left">
          <b-input-group size="sm" class="messages-search">
            <b-form-input
              v-model.trim="searchQuery"
              :placeholder="$t('Search_by_name_email_subject') + '…'"
              @input="debouncedSearch"
            />
            <b-input-group-append>
              <b-button variant="primary" :disabled="searching" @click="fetch">
                <span v-if="searching" class="spinner-border spinner-border-sm"></span>
                <lucide-icon name="search" v-else />
              </b-button>
            </b-input-group-append>
          </b-input-group>

          <b-form-checkbox v-model="onlyUnread" class="messages-unread" switch @change="fetch">
            {{ $t('Unread_only') }}
          </b-form-checkbox>
        </div>

        <div>
          <!-- You can add bulk actions here if needed -->
        </div>
      </div>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :rows="rows"
        :totalRows="totalRows"
        :pagination-options="{ enabled: true, perPage: serverParams.perPage }"
        :search-options="{ enabled: false }"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        styleClass="table-hover tableOne vgt-table"
      >
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'is_read'">
            <b-badge :variant="props.row.is_read ? 'secondary' : 'warning'">
              {{ props.row.is_read ? $t('Read') : $t('Unread') }}
            </b-badge>
          </span>

          <span v-else-if="props.column.field === 'actions'">
            <a
              v-b-tooltip.hover
              :title="$t('View')"
              @click="showMessage(props.row.id)"
            >
              <lucide-icon class="text-20 text-primary" name="eye" />
            </a>
            <a
              v-b-tooltip.hover
              :title="$t('Delete')"
              class="ml-2"
              @click="remove(props.row.id)"
            >
              <lucide-icon class="text-20 text-danger" name="x" />
            </a>
          </span>

          <span v-else>
            {{ props.formattedRow[props.column.field] }}
          </span>
        </template>
      </vue-good-table>
    </b-card>

    <!-- Show Message Modal -->
    <b-modal
      id="messageModal"
      :title="selectedMsg ? (selectedMsg.subject || $t('Message')) : $t('Message')"
      :ok-only="true"
      ok-variant="primary"
      :ok-title="$t('Close')"
      @hidden="selectedMsg = null"
      size="lg"
    >
      <div v-if="loadingOne" class="text-center my-4">
        <span class="spinner-border"></span>
      </div>

      <div v-else-if="selectedMsg">
        <div class="mb-2">
          <div class="small text-muted">{{ $t('From') }}</div>
          <div class="fw-600">
            {{ selectedMsg.name }}
            <span class="text-muted">&lt;{{ selectedMsg.email }}&gt;</span>
            <span v-if="selectedMsg.phone" class="text-muted"> • {{ selectedMsg.phone }}</span>
          </div>
        </div>

        <div class="mb-2">
          <div class="small text-muted">{{ $t('Subject') }}</div>
          <div>{{ selectedMsg.subject || '—' }}</div>
        </div>

        <div class="mb-2">
          <div class="small text-muted">{{ $t('Received') }}</div>
          <div>{{ selectedMsg.created_at }}</div>
        </div>

        <hr>
        <div class="small text-muted mb-1">{{ $t('Message') }}</div>
        <div class="msg-body">{{ selectedMsg.message }}</div>
      </div>

      <div v-else class="text-muted">
        {{ $t('No_message_selected') }}
      </div>
    </b-modal>
  </div>
</template>

<script>

export default {
  metaInfo: {
    title: "Store Messages"
  },
  data () {
    return {
      isLoading: true,
      searching: false,
      rows: [],
      totalRows: 0,
      searchQuery: '',
      onlyUnread: false,
      t: null, // debounce timer

      serverParams: {
        page: 1,
        perPage: 10,
        sort: [{ field: 'created_at', type: 'desc' }]
      },

      columns: [
        { label: this.$t('Name'),       field: 'name',       sortable: true },
        { label: this.$t('Email'),      field: 'email',      sortable: true },
        { label: this.$t('Subject'),    field: 'subject',    sortable: true },
        { label: this.$t('Status'),     field: 'is_read',    sortable: true },
        { label: this.$t('Received'),   field: 'created_at', sortable: true },
        { label: this.$t('Actions'),    field: 'actions' }
      ],

      // Modal
      selectedMsg: null,
      loadingOne: false
    }
  },

  mounted () {
    this.fetch()
  },

  methods: {
    debouncedSearch () {
      if (this.t) clearTimeout(this.t)
      this.t = setTimeout(() => { this.fetch() }, 350)
    },

    async fetch () {
      this.isLoading = true
      this.searching = true
      try {
        var sortField = (this.serverParams.sort && this.serverParams.sort[0])
          ? this.serverParams.sort[0].field
          : 'created_at'
        var sortType  = (this.serverParams.sort && this.serverParams.sort[0])
          ? this.serverParams.sort[0].type
          : 'desc'

        const { data } = await axios.get('/store/messages', {
          params: {
            page: this.serverParams.page,
            per_page: this.serverParams.perPage,
            sort: sortField,
            dir: sortType,
            q: this.searchQuery || '',
            unread: this.onlyUnread ? 1 : 0
          }
        })

        // server returns { data: [...], meta: { total: N } }
        this.rows = (data && data.data) ? data.data : []
        this.totalRows = (data && data.meta && typeof data.meta.total !== 'undefined')
          ? data.meta.total
          : this.rows.length
      } finally {
        this.isLoading = false
        this.searching = false
      }
    },

    onPageChange ({ currentPage }) {
      this.serverParams.page = currentPage
      this.fetch()
    },

    onPerPageChange ({ currentPerPage }) {
      this.serverParams.perPage = currentPerPage
      this.fetch()
    },

    onSortChange (params) {
      this.serverParams.sort = params[0] ? [params[0]] : []
      this.fetch()
    },

    // Open & mark as read
    async showMessage (id) {
      this.loadingOne = true
      this.selectedMsg = null

      try {
        const { data } = await axios.get('/store/messages/' + id)
        // data = { id, name, email, phone, subject, message, is_read, created_at }
        this.selectedMsg = data

        // Update the row in-place to "Read"
        for (var i = 0; i < this.rows.length; i++) {
          if (this.rows[i].id === id) {
            this.rows[i].is_read = true
            break
          }
        }

        // Show modal
        if (this.$bvModal) this.$bvModal.show('messageModal')
      } finally {
        this.loadingOne = false
      }
    },

    // Optional delete (SweetAlert2)
    remove (id) {
      var self = this
      self.$swal({
        title: self.$t('Delete_Title'),
        text: self.$t('Delete_Text'),
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: self.$t('Delete_cancelButtonText'),
        confirmButtonText: self.$t('Delete_confirmButtonText')
      }).then(function (result) {
        var confirmed = !!(result && (result.value === true || result.isConfirmed === true))
        if (!confirmed) return

        axios.delete('/store/messages/' + id)
          .then(function () {
            self.$swal(self.$t('Delete_Deleted'), self.$t('Deleted_in_successfully'), 'success')
            self.fetch()
          })
          .catch(function (e) {
            var msg = (e && e.response && e.response.data && (e.response.data.message || e.response.data.error))
              || self.$t('Delete_Therewassomethingwronge')
            self.$swal(self.$t('Delete_Failed'), msg, 'warning')
          })
      })
    }
  }
}
</script>

<style scoped>
.msg-body {
  white-space: pre-line; /* keep user newlines */
}

/* ===== Toolbar (search + unread filter) ===== */
.messages-toolbar__left > * + * {
  margin-left: 12px;
}

.messages-search {
  width: 320px;
  max-width: 100%;
}

.messages-search .form-control {
  height: 36px;
  border: 1px solid #d1d5db;
  border-right: 0;
  border-top-left-radius: 8px;
  border-bottom-left-radius: 8px;
  font-size: 13px;
  box-shadow: none;
}

.messages-search .form-control:focus {
  border-color: #4f46e5;
  box-shadow: none;
}

.messages-search .btn {
  height: 36px;
  padding: 0 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-top-right-radius: 8px;
  border-bottom-right-radius: 8px;
  background: #4f46e5;
  border-color: #4f46e5;
  color: #fff;
  box-shadow: none;
}

.messages-search .btn:hover:not(:disabled) {
  background: #4338ca;
  border-color: #4338ca;
}

.messages-search .btn:focus {
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
}

/* Unread filter — switch-style checkbox, vertically centered with input */
.messages-unread {
  display: inline-flex;
  align-items: center;
  margin-bottom: 0;
  padding-left: 0;
  font-size: 13px;
  color: #1f2937;
  user-select: none;
}

.messages-unread.custom-switch {
  padding-left: 2.25rem;
}

.messages-unread .custom-control-label {
  cursor: pointer;
  line-height: 36px; /* match search input height */
}

.messages-unread .custom-control-input:checked ~ .custom-control-label::before {
  background-color: #4f46e5;
  border-color: #4f46e5;
}

.messages-unread .custom-control-input:focus ~ .custom-control-label::before {
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
}
</style>
