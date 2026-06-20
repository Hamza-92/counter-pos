<template>
  <div class="main-content kb-page">
    <breadcumb :page="$t('Articles')" :folder="$t('Knowledge_Base') || 'Knowledge Base'" />

    <b-card class="kb-card shadow-sm">
      <div class="kb-toolbar">
        <div class="kb-search-row">
          <b-input-group class="kb-search-input">
            <b-input-group-prepend is-text><lucide-icon name="search" /></b-input-group-prepend>
            <b-form-input v-model.trim="searchQ" :placeholder="$t('Search') + '…'" @keyup.enter="fetch" />
          </b-input-group>
          <b-form-select v-model="filterGroupId" :options="groupOptions" value-field="id" text-field="name" class="kb-group-select">
            <template #first>
              <b-form-select-option :value="null">{{ $t('All_Groups') || 'All groups' }}</b-form-select-option>
            </template>
          </b-form-select>
          <b-button variant="outline-primary" size="sm" @click="fetch">{{ $t('Search') }}</b-button>
        </div>
        <div class="kb-actions">
          <router-link :to="{ name: 'KnowledgeBaseArticleCreate' }" class="btn btn-primary btn-sm">
            <lucide-icon name="plus" /> {{ $t('New_Article') || 'New article' }}
          </router-link>
          <router-link :to="{ name: 'KnowledgeBaseList' }" class="btn btn-outline-secondary btn-sm">
            <lucide-icon name="chevron-left" /> {{ $t('Back') }}
          </router-link>
        </div>
      </div>

      <div v-if="isLoading" class="loading_page spinner spinner-primary"></div>

      <div v-else class="kb-articles-list" v-if="articles.length">
        <div v-for="a in articles" :key="a.id" class="kb-article-item">
          <div class="kb-article-item-body">
            <span class="kb-article-title-text">{{ a.title }}</span>
            <div class="kb-article-meta">
              <span class="kb-article-group">{{ a.group ? a.group.name : '—' }}</span>
              <b-badge v-if="a.is_internal" variant="warning" class="kb-badge-internal">{{ $t('Internal') }}</b-badge>
            </div>
          </div>
          <div class="kb-article-item-actions">
            <router-link :to="{ name: 'KnowledgeBaseArticleView', params: { id: a.id } }" class="btn btn-sm btn-outline-info" title="View">
              <lucide-icon name="eye" />
            </router-link>
            <router-link :to="{ name: 'KnowledgeBaseArticleEdit', params: { id: a.id } }" class="btn btn-sm btn-outline-primary" title="Edit">
              <lucide-icon name="pen" />
            </router-link>
            <b-button variant="outline-danger" size="sm" :disabled="busyId === a.id" @click="destroy(a)" title="Delete">
              <lucide-icon name="x" />
            </b-button>
          </div>
        </div>
      </div>

      <div v-else class="kb-empty">
        <div class="kb-empty-icon"><lucide-icon name="files" /></div>
        <p class="kb-empty-title">{{ $t('No_items') }}</p>
        <router-link :to="{ name: 'KnowledgeBaseArticleCreate' }" class="btn btn-primary"><lucide-icon name="plus" /> {{ $t('New_Article') }}</router-link>
      </div>
    </b-card>
  </div>
</template>

<script>
export default {
  name: 'KnowledgeBaseArticles',
  metaInfo: { title: 'Knowledge Base - Articles' },
  data() {
    return {
      isLoading: true,
      articles: [],
      groups: [],
      searchQ: '',
      filterGroupId: null,
      busyId: null
    };
  },
  computed: {
    groupOptions() {
      return this.groups;
    }
  },
  watch: {
    filterGroupId() {
      this.fetch();
    }
  },
  mounted() {
    this.fetchGroups();
    this.fetch();
  },
  methods: {
    async fetchGroups() {
      try {
        const res = await axios.get('/knowledge-base/groups');
        this.groups = Array.isArray(res.data) ? res.data : (res.data.data || []);
      } catch (e) {
        this.groups = [];
      }
    },
    async fetch() {
      this.isLoading = true;
      try {
        const params = { per_page: 100 };
        if (this.searchQ) params.q = this.searchQ;
        if (this.filterGroupId) params.group_id = this.filterGroupId;
        const res = await axios.get('/knowledge-base/articles', { params });
        const data = res.data;
        this.articles = data.data || data;
        if (!Array.isArray(this.articles)) this.articles = [];
      } catch (e) {
        this.articles = [];
      } finally {
        this.isLoading = false;
      }
    },
    async destroy(a) {
      if (!confirm(this.$t('Confirm_Delete_This_Item'))) return;
      try {
        this.busyId = a.id;
        await axios.delete('/knowledge-base/articles/' + a.id);
        if (this.$root && this.$root.$bvToast) {
          this.$root.$bvToast.toast(this.$t('Deleted_successfully'), { variant: 'success', solid: true });
        }
        this.articles = this.articles.filter(x => x.id !== a.id);
      } catch (e) {
        if (this.$root && this.$root.$bvToast) {
          this.$root.$bvToast.toast(this.$t('Delete_failed'), { variant: 'danger', solid: true });
        }
      } finally {
        this.busyId = null;
      }
    }
  }
};
</script>

<style scoped>
.kb-page { padding-bottom: 2rem; }
.kb-card { border-radius: 12px; border: none; }
.kb-toolbar { margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
.kb-search-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; }
.kb-search-input { max-width: 260px; }
.kb-search-input .input-group-text { border-radius: 8px 0 0 8px; background: #f8f9fa; }
.kb-group-select { max-width: 200px; border-radius: 8px; }
.kb-actions { display: flex; gap: 0.5rem; }

.kb-articles-list { border-top: 1px solid #eee; }
.kb-article-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem 0; border-bottom: 1px solid #f0f0f0; gap: 1rem; }
.kb-article-item:last-child { border-bottom: none; }
.kb-article-item-body { flex: 1; min-width: 0; }
.kb-article-title-text { font-weight: 600; color: #2d3748; display: block; margin-bottom: 0.25rem; }
.kb-article-meta { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.kb-article-group { font-size: 0.8rem; color: #667eea; background: rgba(102, 126, 234, 0.1); padding: 0.2rem 0.5rem; border-radius: 6px; }
.kb-badge-internal { font-size: 0.7rem; }
.kb-article-item-actions { display: flex; gap: 0.35rem; flex-shrink: 0; }

.kb-empty { text-align: center; padding: 3rem 1.5rem; }
.kb-empty-icon { font-size: 2.5rem; color: #dee2e6; margin-bottom: 1rem; }
.kb-empty-title { font-weight: 600; margin-bottom: 1rem; }
</style>
