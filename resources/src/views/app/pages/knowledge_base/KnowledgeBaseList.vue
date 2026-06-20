<template>
  <div class="main-content kb-page">
    <breadcumb :page="$t('Knowledge_Base') || 'Knowledge Base'" :folder="$t('App')" />

    <!-- Hero header -->
    <div class="kb-hero mb-4">
      <div class="kb-hero-inner">
        <div class="kb-hero-icon">
          <lucide-icon name="book" />
        </div>
        <h1 class="kb-hero-title">{{ $t('Knowledge_Base') || 'Knowledge Base' }}</h1>
        <p class="kb-hero-subtitle">{{ $t('Search_articles') || 'Search articles and find answers' }}</p>
      </div>
    </div>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-else class="kb-card shadow-sm">
      <!-- Search & filters -->
      <div class="kb-toolbar">
        <div class="kb-search-row">
          <b-input-group class="kb-search-input">
            <b-input-group-prepend is-text>
              <lucide-icon name="search" />
            </b-input-group-prepend>
            <b-form-input
              v-model.trim="searchQ"
              :placeholder="$t('Search_articles_placeholder') || 'Search by title or content…'"
              @keyup.enter="fetchArticles"
            />
          </b-input-group>
          <b-form-select
            v-model="filterGroupId"
            :options="groupOptions"
            value-field="id"
            text-field="name"
            class="kb-group-select"
          >
            <template #first>
              <b-form-select-option :value="null">{{ $t('All_Groups') || 'All groups' }}</b-form-select-option>
            </template>
          </b-form-select>
          <b-button variant="primary" class="kb-search-btn" @click="fetchArticles" :disabled="loading">
            <lucide-icon name="search" /> {{ $t('Search') }}
          </b-button>
        </div>
        <div class="kb-actions">
          <router-link :to="{ name: 'KnowledgeBaseGroups' }" class="btn btn-outline-secondary btn-sm">
            <lucide-icon name="folder" /> {{ $t('Article_Groups') || 'Groups' }}
          </router-link>
          <router-link :to="{ name: 'KnowledgeBaseArticleCreate' }" class="btn btn-primary btn-sm">
            <lucide-icon name="plus" /> {{ $t('New_Article') || 'New article' }}
          </router-link>
        </div>
      </div>

      <!-- Articles list -->
      <div class="kb-articles-list" v-if="articles.length">
        <div
          v-for="article in articles"
          :key="article.id"
          class="kb-article-item"
        >
          <div class="kb-article-item-body">
            <router-link :to="{ name: 'KnowledgeBaseArticleView', params: { id: article.id } }" class="kb-article-title">
              {{ article.title }}
            </router-link>
            <div class="kb-article-meta">
              <span class="kb-article-group">{{ article.group ? article.group.name : '—' }}</span>
              <b-badge v-if="article.is_internal" variant="warning" class="kb-badge-internal">{{ $t('Internal') }}</b-badge>
            </div>
          </div>
          <div class="kb-article-item-actions">
            <router-link
              :to="{ name: 'KnowledgeBaseArticleView', params: { id: article.id } }"
              class="btn btn-sm btn-outline-primary"
              title="View"
            >
              <lucide-icon name="eye" />
            </router-link>
            <router-link
              v-if="canManage"
              :to="{ name: 'KnowledgeBaseArticleEdit', params: { id: article.id } }"
              class="btn btn-sm btn-outline-secondary"
              title="Edit"
            >
              <lucide-icon name="pen" />
            </router-link>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="!loading" class="kb-empty">
        <div class="kb-empty-icon"><lucide-icon name="files" /></div>
        <p class="kb-empty-title">{{ $t('No_items') || 'No articles found' }}</p>
        <p class="kb-empty-text text-muted">{{ $t('Try_different_search') || 'Try a different search or filter.' }}</p>
        <router-link v-if="canManage" :to="{ name: 'KnowledgeBaseArticleCreate' }" class="btn btn-primary">
          <lucide-icon name="plus" /> {{ $t('New_Article') || 'Create first article' }}
        </router-link>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="kb-pagination">
        <span class="text-muted small">{{ $t('Page') }} {{ currentPage }} {{ $t('of') }} {{ totalPages }}</span>
        <div>
          <b-button size="sm" variant="outline-secondary" :disabled="currentPage <= 1" @click="goPage(currentPage - 1)">
            {{ $t('Previous') }}
          </b-button>
          <b-button size="sm" variant="outline-secondary" class="ml-2" :disabled="currentPage >= totalPages" @click="goPage(currentPage + 1)">
            {{ $t('Next') }}
          </b-button>
        </div>
      </div>
    </b-card>
  </div>
</template>

<script>
export default {
  name: 'KnowledgeBaseList',
  metaInfo: { title: 'Knowledge Base' },
  data() {
    return {
      isLoading: true,
      loading: false,
      articles: [],
      groups: [],
      searchQ: '',
      filterGroupId: null,
      currentPage: 1,
      perPage: 15,
      total: 0
    };
  },
  computed: {
    canManage() {
      return this.$store.getters.currentUserPermissions &&
        this.$store.getters.currentUserPermissions.includes('knowledge_base_view');
    },
    groupOptions() {
      return this.groups;
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.total / this.perPage));
    }
  },
  watch: {
    filterGroupId() {
      this.currentPage = 1;
      this.fetchArticles();
    }
  },
  mounted() {
    this.fetchGroups();
    this.fetchArticles();
  },
  methods: {
    async fetchGroups() {
      try {
        const url = this.canManage ? '/knowledge-base/groups' : '/knowledge-base/groups/for-filter';
        const res = await axios.get(url);
        this.groups = Array.isArray(res.data) ? res.data : (res.data.data || []);
      } catch (e) {
        this.groups = [];
      }
    },
    async fetchArticles() {
      this.loading = true;
      if (this.articles.length === 0) this.isLoading = true;
      try {
        const params = { per_page: this.perPage, page: this.currentPage };
        if (this.searchQ) params.q = this.searchQ;
        if (this.filterGroupId) params.group_id = this.filterGroupId;
        const res = await axios.get('/knowledge-base/articles', { params });
        const data = res.data;
        this.articles = data.data || data;
        if (data.total !== undefined) this.total = data.total;
        else if (Array.isArray(data)) this.total = data.length;
        else this.total = (data.data || []).length;
      } catch (e) {
        this.articles = [];
        this.total = 0;
        if (this.$root && this.$root.$bvToast) {
          this.$root.$bvToast.toast(this.$t('Failed_to_load') || 'Failed to load', { variant: 'danger', solid: true });
        }
      } finally {
        this.loading = false;
        this.isLoading = false;
      }
    },
    goPage(p) {
      this.currentPage = p;
      this.fetchArticles();
    }
  }
};
</script>

<style scoped>
.kb-page { padding-bottom: 2rem; }

.kb-hero {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  padding: 1.75rem 1.5rem;
  color: #fff;
}
.kb-hero-inner { max-width: 600px; }
.kb-hero-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  background: rgba(255,255,255,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}
.kb-hero-icon i { font-size: 1.75rem; }
.kb-hero-title { font-size: 1.5rem; font-weight: 700; margin: 0 0 0.25rem 0; }
.kb-hero-subtitle { margin: 0; opacity: 0.9; font-size: 0.95rem; }

.kb-card { border-radius: 12px; border: none; }
.kb-toolbar { margin-bottom: 1.5rem; }
.kb-search-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}
.kb-search-input { max-width: 320px; flex: 1 1 200px; }
.kb-search-input .input-group-text { border-radius: 8px 0 0 8px; background: #f8f9fa; }
.kb-group-select { max-width: 220px; border-radius: 8px; }
.kb-search-btn { border-radius: 8px; }
.kb-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

.kb-articles-list { border-top: 1px solid #eee; }
.kb-article-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 0;
  border-bottom: 1px solid #f0f0f0;
  gap: 1rem;
}
.kb-article-item:last-child { border-bottom: none; }
.kb-article-item-body { flex: 1; min-width: 0; }
.kb-article-title {
  font-weight: 600;
  color: #333;
  display: block;
  margin-bottom: 0.25rem;
  text-decoration: none;
  transition: color 0.2s;
}
.kb-article-title:hover { color: #667eea; }
.kb-article-meta { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.kb-article-group {
  font-size: 0.8rem;
  color: #667eea;
  background: rgba(102, 126, 234, 0.1);
  padding: 0.2rem 0.5rem;
  border-radius: 6px;
}
.kb-badge-internal { font-size: 0.7rem; }
.kb-article-item-actions { display: flex; gap: 0.35rem; flex-shrink: 0; }

.kb-empty {
  text-align: center;
  padding: 3rem 1.5rem;
}
.kb-empty-icon {
  font-size: 3rem;
  color: #dee2e6;
  margin-bottom: 1rem;
}
.kb-empty-title { font-weight: 600; margin-bottom: 0.25rem; }
.kb-empty-text { font-size: 0.9rem; margin-bottom: 1rem; }

.kb-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid #eee;
}
</style>
