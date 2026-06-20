<template>
  <div class="main-content kb-page">
    <breadcumb
      :page="isEdit ? ($t('Edit') + ' ' + $t('Group')) : ($t('New') + ' ' + $t('Group'))"
      :folder="$t('Knowledge_Base') || 'Knowledge Base'"
    />
    <b-card class="kb-card shadow-sm">
      <div class="card-body">
        <div class="kb-form-header">
          <h5 class="kb-form-title">{{ isEdit ? ($t('Edit') + ' ' + $t('Group')) : ($t('New') + ' ' + $t('Group')) }}</h5>
        </div>
        <b-form @submit.prevent="save" class="kb-form">
          <b-row>
            <b-col cols="12" md="8">
            <b-form-group :label="$t('Name')" label-for="name">
              <b-form-input id="name" v-model.trim="form.name" class="kb-input" required />
            </b-form-group>
            <b-form-group :label="$t('Slug')" label-for="slug">
              <b-form-input id="slug" v-model.trim="form.slug" class="kb-input" required />
            </b-form-group>
            <b-form-group :label="$t('Description')" label-for="description">
              <b-form-textarea id="description" rows="3" v-model.trim="form.description" class="kb-input" />
            </b-form-group>
            <b-form-group :label="$t('Sort_order') || 'Sort order'" label-for="sort_order">
              <b-form-input id="sort_order" type="number" v-model.number="form.sort_order" min="0" class="kb-input" style="max-width: 120px;" />
            </b-form-group>
          </b-col>
          </b-row>
          <div class="kb-form-actions">
            <b-button type="submit" variant="primary" :disabled="saving" class="kb-btn-primary">
              <span v-if="saving" class="spinner-border spinner-border-sm mr-2"></span>
              {{ $t('Save') }}
            </b-button>
            <router-link :to="{ name: 'KnowledgeBaseGroups' }" class="btn btn-outline-secondary">{{ $t('Cancel') }}</router-link>
          </div>
        </b-form>
      </div>
    </b-card>
  </div>
</template>

<script>
export default {
  name: 'KnowledgeBaseGroupForm',
  props: {
    id: { type: [String, Number], default: null }
  },
  data() {
    return {
      form: { name: '', slug: '', description: '', sort_order: 0 },
      saving: false
    };
  },
  computed: {
    isEdit() {
      return this.id != null && this.id !== '';
    }
  },
  mounted() {
    if (this.isEdit) this.fetch();
  },
  methods: {
    async fetch() {
      try {
        const res = await axios.get('/knowledge-base/groups/' + this.id);
        const g = res.data;
        this.form = { name: g.name, slug: g.slug, description: g.description || '', sort_order: g.sort_order ?? 0 };
      } catch (e) {
        if (this.$root && this.$root.$bvToast) {
          this.$root.$bvToast.toast(this.$t('Failed_to_load') || 'Failed to load', { variant: 'danger', solid: true });
        }
      }
    },
    async save() {
      this.saving = true;
      try {
        if (this.isEdit) {
          await axios.put('/knowledge-base/groups/' + this.id, this.form);
          if (this.$root && this.$root.$bvToast) this.$root.$bvToast.toast(this.$t('Updated') || 'Updated', { variant: 'success', solid: true });
        } else {
          await axios.post('/knowledge-base/groups', this.form);
          if (this.$root && this.$root.$bvToast) this.$root.$bvToast.toast(this.$t('Saved') || 'Saved', { variant: 'success', solid: true });
        }
        this.$router.push({ name: 'KnowledgeBaseGroups' });
      } catch (e) {
        const msg = (e.response && e.response.data && e.response.data.message) || this.$t('InvalidData') || 'Invalid data';
        if (this.$root && this.$root.$bvToast) this.$root.$bvToast.toast(msg, { variant: 'danger', solid: true });
      } finally {
        this.saving = false;
      }
    }
  }
};
</script>

<style scoped>
.kb-page { padding-bottom: 2rem; min-height: 400px; }
.kb-card { border-radius: 12px; border: none; }
.kb-card .card-body { padding: 1.5rem; }
.kb-form-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee; }
.kb-form-title { margin: 0; font-weight: 600; color: #2d3748; }
.kb-input { border-radius: 8px; }
.kb-form-actions { margin-top: 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
.kb-btn-primary { border-radius: 8px; }
</style>
