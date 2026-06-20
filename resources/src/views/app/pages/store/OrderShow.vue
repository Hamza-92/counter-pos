<template>
  <div class="main-content">
    <breadcumb :page="$t('Order')" :folder="$t('Store')"/>


    <div v-if="loading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-else class="wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">{{ code }}</h5>
        <div class="d-flex align-items-center">
          <b-badge :variant="badgeVariant(order.status)" class="mr-2 text-uppercase">
            {{ order.status }}
          </b-badge>
          <b-badge v-if="order.has_preorder_items" variant="warning" class="mr-2">
            {{ $t('HasPreorderItems') }}
          </b-badge>
        </div>
      </div>

      <div class="row">
        <div class="col-md-7">
          <b-card class="mb-3">
            <h6 class="mb-3">{{ $t('Items') }}</h6>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>{{ $t('Product') }}</th>
                    <th>{{ $t('Qty') }}</th>
                    <th>{{ $t('Price') }}</th>
                    <th class="text-right">{{ $t('Total') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="it in order.items" :key="it.id">
                    <td>
                      {{ it.name }}
                      <b-badge v-if="it.is_preorder" variant="warning" class="ml-1">
                        {{ $t('PreOrder') }}
                      </b-badge>
                    </td>
                    <td>{{ it.qty }}</td>
                    <td>{{ currency(it.price) }}</td>
                    <td class="text-right">{{ currency(it.price * it.qty) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </b-card>
        </div>

        <div class="col-md-5">
          <b-card class="mb-3">
            <h6>Customer</h6>
            <div class="text-muted">{{ order.customer_name }}</div>
            <div class="text-muted">{{ order.customer_email }}</div>
            <div class="text-muted">{{ order.customer_phone }}</div>
          </b-card>

          <b-card class="mb-3">
            <h6>{{ $t('Shipping') }}</h6>
            <div class="text-muted">{{ order.customer_phone || '-' }}</div>
            <div class="text-muted">{{ order.customer_address || '-' }}</div>
          </b-card>

          <!-- NEW: Warehouse card -->
          <b-card class="mb-3">
            <h6>Warehouse</h6>
            <div class="text-muted">
              {{ order.warehouse_name || '-' }}
            </div>
          </b-card>

          <b-card class="mb-3">
            <h6>{{ $t('Payment') }}</h6>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted">{{ $t('Method') }}</span>
              <b-badge :variant="paymentMethodVariant(order.payment_method)">
                {{ paymentMethodLabel(order.payment_method) }}
              </b-badge>
            </div>
            <div class="d-flex justify-content-between">
              <span class="text-muted">{{ $t('Status') }}</span>
              <b-badge :variant="order.payment_status === 'paid' ? 'success' : 'warning'">
                {{ order.payment_status || 'pending' }}
              </b-badge>
            </div>
          </b-card>

          <b-card class="mb-3">
            <h6>Summary</h6>
            <ul class="list-unstyled mb-0">
              <li class="d-flex justify-content-between">
                <span>Subtotal</span>
                <strong>{{ currency(order.subtotal) }}</strong>
              </li>
              <li v-if="Number(order.shipping || 0) > 0" class="d-flex justify-content-between">
                <span>Shipping</span>
                <strong>{{ currency(order.shipping || 0) }}</strong>
              </li>
              <li v-if="Number(order.discount || 0) > 0" class="d-flex justify-content-between">
                <span>Discount</span>
                <strong>-{{ currency(order.discount || 0) }}</strong>
              </li>
              <li class="d-flex justify-content-between border-top pt-2">
                <span>Total</span>
                <strong>{{ currency(order.total) }}</strong>
              </li>
            </ul>
          </b-card>
        </div>

      </div>
    </b-card>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";

export default {
  metaInfo: { title: 'Store Order' },
  props:{ id:{type:[String,Number], required:true} },
  data(){ return {
    loading:true,
    actionBusy:false,
    order:{ items:[], status:'pending' },
    code:''
  }},
  mounted(){ this.fetch() },

  computed: {
    ...mapGetters(["currentUser"]),
  },
  methods:{
    currency(n) {
      // Prefer currentUser.currency if available
      let code =
        this.currentUser.currency;

      try {
        return new Intl.NumberFormat(undefined, {
          style: 'currency',
          currency: code
        }).format(n || 0);
      } catch (e) {
        // fallback if currency code invalid
        return code + ' ' + Number(n || 0).toFixed(2);
      }
    },

    badgeVariant(s){
      return { pending:'warning', confirmed:'success', cancelled:'danger' }[s] || 'secondary'
    },
    paymentMethodLabel(m) {
      return { credit_card: 'Credit Card', mobile_money: 'Mobile Money', cod: 'Cash on Delivery' }[m] || m || 'N/A'
    },
    paymentMethodVariant(m) {
      return { credit_card: 'primary', mobile_money: 'info', cod: 'secondary' }[m] || 'secondary'
    },
    async fetch(){
      try{
        const {data} = await axios.get(`/store/orders/${this.id}`)
        this.order = data || { items:[], status:'pending' }
        this.code = data && (data.code || `#${data.id}`)
      } finally { this.loading = false }
    },

    confirmOrder () {
        if (!this.order || this.order.status !== 'pending') return

        this.$swal({
          title: 'Confirm this order?',
          text:  'This will convert the online order into a Sale and deduct stock.',
          type:  'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor:  '#d33',
          cancelButtonText:   'Cancel',
          confirmButtonText:  'Confirm',
        }).then(async (result) => {
          if (!result.value) return
          if (window.NProgress) { NProgress.start(); NProgress.set(0.1) }
          this.actionBusy = true
          try {
            const { data } = await axios.patch(`/store/orders/${this.id}`, { status: 'confirmed' })
            this.order.status = 'confirmed'
            const msg = data && data.sale_ref
              ? (`Order confirmed as Sale ${data.sale_ref}`)
              : ('Order confirmed')
            this.$swal('Success', msg, 'success')
          } catch (e) {
            // Build a helpful error message (show per-item shortages if backend sent them)
            let msg = 'Operation failed. Please try again.'
            const d = e && e.response && e.response.data
            if (d) {
              if (Array.isArray(d.items) && d.items.length) {
                const lines = d.items.map(x => `${x.name} — 'Available': ${x.available}, 'Required': ${x.required}`)
                msg = ('Insufficient stock for:') + '\n' + lines.join('\n')
              } else if (d.error || d.message) {
                msg = d.error || d.message
              }
            }
            this.$swal('Error', msg, 'warning')
          } finally {
            this.actionBusy = false
            if (window.NProgress) setTimeout(() => NProgress.done(), 300)
          }
        })
    },

    cancelOrder () {
      if (!this.order || this.order.status !== 'pending') return

      this.$swal({
        title: 'Cancel this order?',
        text:  'This will mark the order as cancelled.',
        type:  'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor:  '#d33',
        cancelButtonText:   'Keep',
        confirmButtonText:  'Cancel',
      }).then(async (result) => {
        if (!result.value) return
        if (window.NProgress) { NProgress.start(); NProgress.set(0.1) }
        this.actionBusy = true
        try {
          await axios.patch(`/store/orders/${this.id}`, { status: 'cancelled' })
          this.order.status = 'cancelled'
          this.$swal('Success', 'Order cancelled', 'success')
        } catch (e) {
          this.$swal(
            'Error',
            'Operation failed. Please try again.',
            'warning'
          )
        } finally {
          this.actionBusy = false
          if (window.NProgress) setTimeout(() => NProgress.done(), 300)
        }
      })
    },

    toast (variant, title, msg) {
      if (this.$bvToast && this.$bvToast.toast) {
        this.$bvToast.toast(msg, { title, variant, autoHideDelay: 3000, solid: true })
      } else {
        if (variant === 'danger') alert((title || 'Error') + ': ' + msg)
        else console.log(title + ': ' + msg)
      }
    }
  }
}
</script>
