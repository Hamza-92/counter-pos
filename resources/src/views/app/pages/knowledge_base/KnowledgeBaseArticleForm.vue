<template>
  <div class="main-content kb-page">
    <breadcumb
      :page="isEdit ? ($t('Edit') + ' ' + $t('Article')) : ($t('New') + ' ' + $t('Article'))"
      :folder="$t('Knowledge_Base') || 'Knowledge Base'"
    />
    <b-card class="kb-card shadow-sm">
      <div class="card-body">
        <div class="kb-form-header">
          <h5 class="kb-form-title">{{ isEdit ? ($t('Edit') + ' ' + $t('Article')) : ($t('New') + ' ' + $t('Article')) }}</h5>
        </div>
        <b-form @submit.prevent="save" class="kb-form">
          <b-row>
            <b-col cols="12" md="8">
            <b-form-group :label="$t('Group')" label-for="group_id">
              <b-form-select
                id="group_id"
                v-model="form.knowledge_base_article_group_id"
                :options="groupOptions"
                value-field="id"
                text-field="name"
                class="kb-input"
                required
              />
            </b-form-group>
            <b-form-group :label="$t('Title')" label-for="title">
              <b-form-input id="title" v-model.trim="form.title" class="kb-input" required />
            </b-form-group>
            <b-form-group :label="$t('Slug')" label-for="slug">
              <b-form-input id="slug" v-model.trim="form.slug" class="kb-input" required />
            </b-form-group>
            <b-form-group :label="$t('Content')" label-for="content">
              <div class="kb-editor-wrap">
                <RichTextEditor
                  :value="form.content"
                  @input="form.content = $event"
                  editor-id="kb-article-editor"
                />
              </div>
            </b-form-group>
            <b-form-group>
              <b-form-checkbox v-model="form.is_internal" class="kb-checkbox">
                {{ $t('Internal_Article') || 'Internal article (visible only to users with Knowledge Base manage permission)' }}
              </b-form-checkbox>
            </b-form-group>
            <b-form-group :label="$t('Publication_date') || 'Publication date'" label-for="published_at">
              <b-form-input id="published_at" type="date" v-model="form.published_at" class="kb-input" style="max-width: 200px;" />
              <small class="text-muted">{{ $t('Leave_empty_for_now') || 'Optional — leave empty for an unpublished draft.' }}</small>
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
            <router-link :to="{ name: 'KnowledgeBaseArticles' }" class="btn btn-outline-secondary">{{ $t('Cancel') }}</router-link>
            <router-link v-if="isEdit" :to="{ name: 'KnowledgeBaseArticleView', params: { id: id } }" class="btn btn-outline-info">{{ $t('View') }}</router-link>
          </div>
        </b-form>
      </div>
    </b-card>
  </div>
</template>

<script>
import RichTextEditor from '@/components/RichTextEditor.vue';

export default {
  name: 'KnowledgeBaseArticleForm',
  components: { RichTextEditor },
  props: {
    id: { type: [String, Number], default: null }
  },
  data() {
    return {
      form: {
        knowledge_base_article_group_id: null,
        title: '',
        slug: '',
        content: '',
        is_internal: false,
        sort_order: 0,
        published_at: ''
      },
      groups: [],
      saving: false
    };
  },
  computed: {
    isEdit() {
      return this.id != null && this.id !== '';
    },
    groupOptions() {
      return this.groups;
    }
  },
  mounted() {
    this.fetchGroups();
    if (this.isEdit) this.fetch();
  },
  methods: {
    async fetchGroups() {
      try {
        const res = await axios.get('/knowledge-base/groups');
        this.groups = Array.isArray(res.data) ? res.data : (res.data.data || []);
        if (this.groups.length && !this.form.knowledge_base_article_group_id) {
          this.form.knowledge_base_article_group_id = this.groups[0].id;
        }
      } catch (e) {
        this.groups = [];
      }
    },
    async fetch() {
      try {
        const res = await axios.get('/knowledge-base/articles/' + this.id);
        const a = res.data;
        this.form = {
          knowledge_base_article_group_id: a.knowledge_base_article_group_id,
          title: a.title,
          slug: a.slug,
          content: a.content || '',
          is_internal: !!a.is_internal,
          sort_order: a.sort_order ?? 0,
          published_at: a.published_at ? String(a.published_at).slice(0, 10) : ''
        };
      } catch (e) {
        if (this.$root && this.$root.$bvToast) {
          this.$root.$bvToast.toast(this.$t('Failed_to_load') || 'Failed to load', { variant: 'danger', solid: true });
        }
      }
    },
    async save() {
      this.saving = true;
      try {
        const payload = { ...this.form };
        if (!payload.published_at) payload.published_at = null;
        if (this.isEdit) {
          await axios.put('/knowledge-base/articles/' + this.id, payload);
          if (this.$root && this.$root.$bvToast) {
            this.$root.$bvToast.toast(this.$t('Updated') || 'Updated', { variant: 'success', solid: true });
          }
        } else {
          await axios.post('/knowledge-base/articles', payload);
          if (this.$root && this.$root.$bvToast) {
            this.$root.$bvToast.toast(this.$t('Saved') || 'Saved', { variant: 'success', solid: true });
          }
        }
        this.$router.push({ name: 'KnowledgeBaseArticles' });
      } catch (e) {
        const msg = (e.response && e.response.data && (e.response.data.message || (e.response.data.errors && Object.values(e.response.data.errors).flat()[0]))) || this.$t('InvalidData') || 'Invalid data';
        if (this.$root && this.$root.$bvToast) {
          this.$root.$bvToast.toast(msg, { variant: 'danger', solid: true });
        }
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
.kb-editor-wrap { border-radius: 8px; overflow: hidden; border: 1px solid #ced4da; }
.kb-editor-wrap >>> .ql-toolbar { border-radius: 8px 8px 0 0; background: #f8f9fa; }
.kb-editor-wrap >>> .ql-container { border-radius: 0 0 8px 8px; min-height: 220px; }
.kb-checkbox >>> label { font-weight: 500; color: #4a5568; }
.kb-form-actions { margin-top: 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
.kb-btn-primary { border-radius: 8px; }
</style>
