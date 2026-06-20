<template>
  <div class="main-content">
    <breadcumb :page="$t('CustomFields')" :folder="$t('Settings')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-if="!isLoading">
      <b-card>
        <b-tabs v-model="activeTab" content-class="mt-3">
          <!-- Customers Custom Fields Tab -->
          <b-tab :title="$t('Customers')">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5>{{ $t('CustomerCustomFields') }}</h5>
              <b-button variant="primary" @click="New_CustomField('client')">
                <lucide-icon name="plus" /> {{ $t('Add') }}
              </b-button>
            </div>

            <vue-good-table
              :columns="columns"
              :rows="customerFields"
              :rtl="direction"
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
              <h5>{{ $t('SupplierCustomFields') }}</h5>
              <b-button variant="primary" @click="New_CustomField('provider')">
                <lucide-icon name="plus" /> {{ $t('Add') }}
              </b-button>
            </div>

            <vue-good-table
              :columns="columns"
              :rows="supplierFields"
              :rtl="direction"
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
      </b-card>
    </div>

    <!-- Modal Add/Edit Custom Field -->
    <validation-observer ref="Create_CustomField">
      <b-modal
        hide-footer
        size="lg"
        :id="editmode ? 'Edit_CustomField' : 'New_CustomField'"
        :title="editmode ? $t('Edit') : $t('Add')"
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
                  :placeholder="$t('EnterOptionsOnePerLine')"
                  rows="4"
                  @blur="updateSelectOptions"
                ></b-form-textarea>
                <small class="text-muted">{{ $t('EnterOptionsOnePerLine') }}</small>
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

            <!-- Sort Order -->
            <b-col md="6" sm="12" class="mb-3">
              <b-form-group :label="$t('SortOrder')">
                <b-form-input
                  type="number"
                  v-model.number="customField.sort_order"
                  :placeholder="$t('SortOrder')"
                  min="0"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <b-col md="12" class="mt-3">
              <b-button
                variant="primary"
                type="submit"
                :disabled="SubmitProcessing"
              >
                <lucide-icon class="me-2 font-weight-bold" name="check" /> {{ $t('submit') }}
              </b-button>
              <b-button
                variant="secondary"
                @click="reset_Form"
                class="ml-2"
              >
                {{ $t('Cancel') }}
              </b-button>
              <div v-once class="typo__p" v-if="SubmitProcessing">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Custom Fields"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      editmode: false,
      activeTab: 0,
      customerFields: [],
      supplierFields: [],
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
      fieldTypes: [
        { label: this.$t('Text'), value: 'text' },
        { label: this.$t('Number'), value: 'number' },
        { label: this.$t('Textarea'), value: 'textarea' },
        { label: this.$t('Date'), value: 'date' },
        { label: this.$t('Select'), value: 'select' },
        { label: this.$t('Checkbox'), value: 'checkbox' },
      ],
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    direction() {
      if (this.$i18n.locale == "ar") {
        return "rtl";
      } else {
        return "ltr";
      }
    },
    columns() {
      return [
        {
          label: this.$t("FieldName"),
          field: "name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("FieldType"),
          field: "field_type",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Required"),
          field: "is_required",
          tdClass: "text-center",
          thClass: "text-center"
        },
        {
          label: this.$t("SortOrder"),
          field: "sort_order",
          tdClass: "text-center",
          thClass: "text-center"
        },
        {
          label: this.$t("Action"),
          field: "actions",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    }
  },
  mounted() {
    this.Get_CustomFields();
  },
  methods: {
    //----------------------------------- Get Custom Fields -------------------------------\\
    Get_CustomFields() {
      NProgress.start();
      NProgress.set(0.1);
      
      // Get customer fields
      axios
        .get("custom-fields?entity_type=client")
        .then(response => {
          this.customerFields = response.data.custom_fields;
        })
        .catch(error => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });

      // Get supplier fields
      axios
        .get("custom-fields?entity_type=provider")
        .then(response => {
          this.supplierFields = response.data.custom_fields;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(error => {
          NProgress.done();
          this.isLoading = false;
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    //----------------------------------- New Custom Field -------------------------------\\
    New_CustomField(entityType) {
      this.reset_Form();
      this.customField.entity_type = entityType;
      this.editmode = false;
      setTimeout(() => {
        this.$bvModal.show("New_CustomField");
      }, 500);
    },

    //----------------------------------- Edit Custom Field -------------------------------\\
    Edit_CustomField(customField) {
      this.reset_Form();
      this.customField = {
        id: customField.id,
        name: customField.name,
        field_type: customField.field_type,
        entity_type: customField.entity_type,
        is_required: customField.is_required,
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

      this.editmode = true;
      setTimeout(() => {
        this.$bvModal.show("Edit_CustomField");
      }, 500);
    },

    //----------------------------------- Submit Custom Field -------------------------------\\
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

        this.SubmitProcessing = true;

        const payload = {
          name: this.customField.name,
          field_type: this.customField.field_type,
          entity_type: this.customField.entity_type,
          is_required: this.customField.is_required,
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

        const url = this.editmode
          ? `custom-fields/${this.customField.id}`
          : "custom-fields";
        const method = this.editmode ? "put" : "post";

        axios[method](url, payload)
          .then(response => {
            this.makeToast(
              "success",
              this.editmode ? this.$t("Successfully_Updated") : this.$t("Successfully_Created"),
              this.$t("Success")
            );
            this.SubmitProcessing = false;
            this.$bvModal.hide(this.editmode ? "Edit_CustomField" : "New_CustomField");
            this.Get_CustomFields();
          })
          .catch(error => {
            this.SubmitProcessing = false;
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          });
      });
    },

    //----------------------------------- Delete Custom Field -------------------------------\\
    Delete_CustomField(id) {
      this.$swal({
        title: this.$t("DeleteTitle"),
        text: this.$t("DeleteMessage"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Cancel"),
        confirmButtonText: this.$t("Delete")
      }).then(result => {
        if (result.value) {
          axios
            .delete("custom-fields/" + id)
            .then(response => {
              this.makeToast(
                "success",
                this.$t("Successfully_Deleted"),
                this.$t("Success")
              );
              this.Get_CustomFields();
            })
            .catch(error => {
              this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
            });
        }
      });
    },

    //----------------------------------- Reset Form -------------------------------\\
    reset_Form() {
      this.customField = {
        id: "",
        name: "",
        field_type: "",
        entity_type: "",
        is_required: false,
        default_value: "",
        sort_order: 0,
      };
      this.selectOptionsText = "";
      this.editmode = false;
    },

    //----------------------------------- Field Type Change -------------------------------\\
    onFieldTypeChange() {
      if (this.customField.field_type !== 'select') {
        this.selectOptionsText = "";
      }
      if (this.customField.field_type === 'checkbox') {
        this.customField.default_value = "";
      }
    },

    //----------------------------------- Update Select Options -------------------------------\\
    updateSelectOptions() {
      // This is handled in Submit_CustomField
    },

    //----------------------------------- Get Field Type Label -------------------------------\\
    getFieldTypeLabel(type) {
      const field = this.fieldTypes.find(f => f.value === type);
      return field ? field.label : type;
    },

    //------ Event Validation State
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
  }
};
</script>

