<template>
  <div class="main-content">
    <breadcumb :page="$t('Add_Asset')" :folder="$t('Assets')"/>

    <validation-observer ref="create_asset" v-slot="{ validate, reset }">
      <b-form @submit.prevent="submitAsset">
        <b-row>
          <b-col md="6">
            <b-form-group :label="$t('Tag') + ' *'">
              <validation-provider name="tag" :rules="{ required: true }" v-slot="validationContext">
                <b-form-input v-model="form.tag" :state="getValidationState(validationContext)" aria-describedby="tag-feedback"></b-form-input>
                <b-form-invalid-feedback id="tag-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
              </validation-provider>
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('Name') + ' *'">
              <validation-provider name="name" :rules="{ required: true }" v-slot="validationContext">
                <b-form-input v-model="form.name" :state="getValidationState(validationContext)" aria-describedby="name-feedback"></b-form-input>
                <b-form-invalid-feedback id="name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
              </validation-provider>
            </b-form-group>
          </b-col>
          <b-col md="6">
            <validation-provider name="asset_category_id" :rules="{ required: true }" v-slot="{ valid, errors }">
              <b-form-group :label="$t('Category') + ' *'">
                <v-select
                  :class="{'is-invalid': !!errors.length}"
                  :state="errors[0] ? false : (valid ? true : null)"
                  v-model="form.asset_category_id"
                  :reduce="opt => opt.value"
                  :placeholder="$t('Choose_Category')"
                  :options="categories.map(c => ({ label: c.name, value: c.id }))"
                />
                <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
              </b-form-group>
            </validation-provider>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('Serial')">
              <b-form-input v-model="form.serial_number"></b-form-input>
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('Purchase_Date')">
              <b-form-input type="date" v-model="form.purchase_date"></b-form-input>
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('Purchase_Cost')">
              <validation-provider name="purchase_cost" :rules="{ regex: /^\d*\.?\d*$/ }" v-slot="validationContext">
                <b-form-input type="text" step="0.01" v-model.number="form.purchase_cost" :state="getValidationState(validationContext)" aria-describedby="purchase_cost-feedback"></b-form-input>
                <b-form-invalid-feedback id="purchase_cost-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
              </validation-provider>
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('Status')">
              <b-form-select v-model="form.status" :options="statusOptions"></b-form-select>
            </b-form-group>
          </b-col>
          <b-col md="6">
            <validation-provider name="warehouse_id" :rules="{ required: true }" v-slot="{ valid, errors }">
              <b-form-group :label="$t('Warehouse') + ' *'">
                <v-select
                  :class="{'is-invalid': !!errors.length}"
                  :state="errors[0] ? false : (valid ? true : null)"
                  v-model="form.warehouse_id"
                  :reduce="opt => opt.value"
                  :placeholder="$t('Select_Warehouse')"
                  :options="warehouses.map(w => ({ label: w.name, value: w.id }))"
                />
                <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
              </b-form-group>
            </validation-provider>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('Last_Verification')">
              <b-form-input type="date" v-model="form.last_verification"></b-form-input>
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('Next_Validation')">
              <b-form-input type="date" v-model="form.next_validation"></b-form-input>
            </b-form-group>
          </b-col>
          <b-col md="12">
            <b-form-group :label="$t('Description')">
              <b-form-textarea v-model="form.description" rows="3"></b-form-textarea>
            </b-form-group>
          </b-col>
        </b-row>
        <div>
          <b-button type="submit" variant="primary" :disabled="SubmitProcessing">
            <span v-if="!SubmitProcessing">{{ $t('Save') }}</span>
            <span v-else>
              <span class="spinner sm spinner-primary align-middle mr-2"></span>
              {{ $t('Processing') }}
            </span>
          </b-button>
          <router-link class="btn btn-outline-secondary ml-2" to="/app/assets/list">{{ $t('Cancel') }}</router-link>
        </div>
      </b-form>
    </validation-observer>
  </div>
  
</template>

<script>
export default {
  name: 'AssetCreate',
  data() {
    return {
      form: {
        tag: '',
        name: '',
        asset_category_id: null,
        serial_number: '',
        description: '',
        purchase_date: '',
        purchase_cost: null,
        status: 'in_use',
        warehouse_id: null,
        last_verification: '',
        next_validation: ''
      },
      categories: [],
      warehouses: [],
      statusOptions: [
        { value: 'in_use', text: this.$t('In_Use') },
        { value: 'maintenance', text: this.$t('Maintenance') },
        { value: 'retired', text: this.$t('Retired') }
      ],
      SubmitProcessing: false
    }
  },
  created() {
    this.fetchElements();
  },
  methods: {
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },
    async fetchElements() {
      const { data } = await axios.get('assets/create');
      this.categories = data.asset_categories || [];
      this.warehouses = data.warehouses || [];
    },
    async submitAsset() {
      const valid = await this.$refs.create_asset.validate();
      if (!valid) return;
      this.SubmitProcessing = true;
      try {
        await axios.post('assets', this.form);
        this.$router.push('/app/assets/list');
      } catch (e) {
        this.SubmitProcessing = false;
        throw e;
      }
    }
  }
}
</script>


