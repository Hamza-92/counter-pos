<template>
  <div class="custom-fields-form">
    <div v-if="isLoading" class="text-center py-2">
      <small class="text-muted">{{ $t('Loading') || 'Loading...' }}</small>
    </div>
    <div v-else-if="customFields.length > 0">
      <b-row>
        <b-col md="12" class="mb-3">
          <h6 class="text-primary">
            <lucide-icon class="mr-2" name="database-zap" />
            {{ $t('CustomFields') }}
          </h6>
          <hr />
        </b-col>
      </b-row>

      <b-row v-for="field in customFields" :key="field.id" class="mb-3">
        <b-col :md="field.field_type === 'textarea' ? 12 : 6" sm="12">
          <validation-provider
            :name="field.name"
            :rules="field.is_required ? { required: true } : {}"
            v-slot="validationContext"
          >
            <b-form-group :label="field.name + (field.is_required ? ' *' : '')">
            <!-- Text Input -->
            <b-form-input
              v-if="field.field_type === 'text'"
              :state="getValidationState(validationContext)"
              :placeholder="field.name"
              v-model="fieldValues[field.id]"
              :aria-describedby="`field-${field.id}-feedback`"
            ></b-form-input>

            <!-- Number Input -->
            <b-form-input
              v-else-if="field.field_type === 'number'"
              type="number"
              :state="getValidationState(validationContext)"
              :placeholder="field.name"
              v-model.number="fieldValues[field.id]"
              :aria-describedby="`field-${field.id}-feedback`"
            ></b-form-input>

            <!-- Textarea -->
            <b-form-textarea
              v-else-if="field.field_type === 'textarea'"
              :state="getValidationState(validationContext)"
              :placeholder="field.name"
              v-model="fieldValues[field.id]"
              rows="3"
              :aria-describedby="`field-${field.id}-feedback`"
            ></b-form-textarea>

            <!-- Date Picker -->
            <b-form-datepicker
              v-else-if="field.field_type === 'date'"
              :state="getValidationState(validationContext)"
              :placeholder="field.name"
              v-model="fieldValues[field.id]"
              :aria-describedby="`field-${field.id}-feedback`"
            ></b-form-datepicker>

            <!-- Select Dropdown -->
            <v-select
              v-else-if="field.field_type === 'select'"
              :class="{'is-invalid': !!validationContext.errors[0]}"
              :state="validationContext.errors[0] ? false : (validationContext.valid ? true : null)"
              v-model="fieldValues[field.id]"
              :reduce="label => label.value"
              :options="getSelectOptions(field)"
              :placeholder="$t('PleaseSelect')"
            ></v-select>

            <!-- Checkbox -->
            <b-form-checkbox
              v-else-if="field.field_type === 'checkbox'"
              v-model="fieldValues[field.id]"
              :value="true"
              :unchecked-value="false"
            >
              {{ field.name }}
            </b-form-checkbox>

            <b-form-invalid-feedback :id="`field-${field.id}-feedback`">
              {{ validationContext.errors[0] }}
            </b-form-invalid-feedback>
          </b-form-group>
        </validation-provider>
      </b-col>
    </b-row>
    </div>
  </div>
</template>

<script>
export default {
  name: "CustomFieldsForm",
  props: {
    entityType: {
      type: String,
      required: true,
      validator: value => ['client', 'provider'].includes(value)
    },
    entityId: {
      type: [Number, String],
      default: null
    },
    values: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      customFields: [],
      fieldValues: {},
      isLoading: false
    };
  },
  watch: {
    entityId: {
      handler(newId, oldId) {
        if (newId && newId !== oldId) {
          // Load values when entityId becomes available
          this.$nextTick(() => {
            if (this.customFields.length > 0) {
              this.loadFieldValues();
            }
          });
        }
      },
      immediate: false
    },
    values: {
      handler(newValues) {
        // Update fieldValues when values prop changes
        if (newValues && Object.keys(newValues).length > 0) {
          const updated = {};
          this.customFields.forEach(field => {
            if (newValues[field.id] !== undefined) {
              updated[field.id] = newValues[field.id].value || newValues[field.id];
            } else {
              updated[field.id] = this.getDefaultValue(field);
            }
          });
          this.fieldValues = updated;
        }
      },
      immediate: true,
      deep: true
    },
    fieldValues: {
      handler(newValues) {
        // Emit changes to parent
        this.$emit('input', newValues);
      },
      deep: true
    }
  },
  mounted() {
    this.loadCustomFields().then(() => {
      // After custom fields are loaded, load values if entityId exists
      if (this.entityId) {
        this.loadFieldValues();
      }
    });
  },
  methods: {
    //----------------------------------- Load Custom Fields -------------------------------\\
    loadCustomFields() {
      this.isLoading = true;
      return axios
        .get(`custom-fields?entity_type=${this.entityType}`)
        .then(response => {
          // Only show active custom fields
          this.customFields = (response.data.custom_fields || []).filter(field => field.is_active !== false);
          // Initialize field values with defaults
          const initialValues = {};
          this.customFields.forEach(field => {
            initialValues[field.id] = this.getDefaultValue(field);
          });
          this.fieldValues = { ...initialValues, ...this.fieldValues };
          this.isLoading = false;
          return Promise.resolve();
        })
        .catch(error => {
          this.isLoading = false;
          console.error('Error loading custom fields:', error);
          return Promise.reject(error);
        });
    },

    //----------------------------------- Load Field Values -------------------------------\\
    loadFieldValues() {
      if (!this.entityId) return;

      const entityTypeModel = this.entityType === 'client' 
        ? 'App\\Models\\Client' 
        : 'App\\Models\\Provider';

      axios
        .get('custom-field-values', {
          params: {
            entity_type: entityTypeModel,
            entity_id: this.entityId
          }
        })
        .then(response => {
          if (response.data.success && response.data.values) {
            const loadedValues = {};
            Object.keys(response.data.values).forEach(fieldId => {
              const value = response.data.values[fieldId];
              // Handle checkbox values
              if (value.field && value.field.field_type === 'checkbox') {
                loadedValues[fieldId] = value.value === '1' || value.value === 1 || value.value === true;
              } else {
                loadedValues[fieldId] = value.value;
              }
            });
            // Merge with existing fieldValues, but prioritize loaded values
            this.fieldValues = { ...this.fieldValues, ...loadedValues };
          }
        })
        .catch(error => {
          console.error('Error loading field values:', error);
        });
    },

    //----------------------------------- Get Default Value -------------------------------\\
    getDefaultValue(field) {
      if (field.default_value) {
        if (field.field_type === 'checkbox') {
          return field.default_value === '1' || field.default_value === 1 || field.default_value === true;
        }
        return field.default_value;
      }
      
      // Return appropriate default based on field type
      switch (field.field_type) {
        case 'checkbox':
          return false;
        case 'number':
          return null;
        default:
          return '';
      }
    },

    //----------------------------------- Get Select Options -------------------------------\\
    getSelectOptions(field) {
      if (field.field_type !== 'select' || !field.default_value) {
        return [];
      }

      let options = [];
      try {
        if (Array.isArray(field.default_value)) {
          options = field.default_value;
        } else {
          options = JSON.parse(field.default_value);
        }
      } catch (e) {
        console.error('Error parsing select options:', e);
        return [];
      }

      return options.map(opt => ({
        label: opt,
        value: opt
      }));
    },

    //------ Event Validation State
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //----------------------------------- Get Field Values for Submission -------------------------------\\
    getFieldValues() {
      return this.fieldValues;
    }
  }
};
</script>

<style scoped>
.custom-fields-form {
  margin-top: 1rem;
}
</style>

