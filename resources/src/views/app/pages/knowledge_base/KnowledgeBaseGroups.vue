<template>
  <div class="main-content kb-page">
    <breadcumb :page="$t('Article_Groups') || 'Article groups'" :folder="$t('Knowledge_Base') || 'Knowledge Base'" />

    <b-card class="kb-card shadow-sm">
      <div class="kb-toolbar">
        <div class="kb-search-row">
          <b-input-group class="kb-search-input">
            <b-input-group-prepend is-text><lucide-icon name="search" /></b-input-group-prepend>
            <b-form-input v-model.trim="q" :placeholder="$t('Search') + '…'" />
          </b-input-group>
        </div>
        <div class="kb-actions">
          <router-link :to="{ name: 'KnowledgeBaseGroupCreate' }" class="btn btn-primary btn-sm">
            <lucide-icon name="plus" /> {{ $t('New') }}
          </router-link>
          <router-link :to="{ name: 'KnowledgeBaseList' }" class="btn btn-outline-secondary btn-sm">
            <lucide-icon name="chevron-left" /> {{ $t('Back') }}
          </router-link>
        </div>
      </div>

      <div v-if="isLoading" class="loading_page spinner spinner-primary"></div>

      <div v-else class="kb-groups-list" v-if="filtered.length">
        <div v-for="(g, idx) in filtered" :key="g.id" class="kb-group-item">
          <div class="kb-group-order">#{{ idx + 1 }}</div>
          <div class="kb-group-icon"><lucide-icon name="folder" /></div>
          <div class="kb-group-body">
            <div class="kb-group-name">{{ g.name }}</div>
            <div class="kb-group-desc text-muted small" v-if="g.description">{{ g.description }}</div>
            <span class="kb-group-slug">{{ g.slug }}</span>
          </div>
          <div class="kb-group-count">
            <span class="badge badge-pill badge-light">{{ g.articles_count != null ? g.articles_count : '0' }}</span>
            <span class="small text-muted">{{ $t('Articles') }}</span>
          </div>
          <div class="kb-group-actions">
            <router-link :to="{ name: 'KnowledgeBaseGroupEdit', params: { id: g.id } }" class="btn btn-sm btn-outline-primary" title="Edit">
              <lucide-icon name="pen" />
            </router-link>
            <b-button variant="outline-danger" size="sm" :disabled="busyId === g.id" @click="destroy(g)" title="Delete">
              <lucide-icon name="x" />
            </b-button>
          </div>
        </div>
      </div>

      <div v-else-if="!isLoading" class="kb-empty">
        <div class="kb-empty-icon"><lucide-icon name="folder" /></div>
        <p class="kb-empty-title">{{ $t('No_items') }}</p>
        <router-link :to="{ name: 'KnowledgeBaseGroupCreate' }" class="btn btn-primary"><lucide-icon name="plus" /> {{ $t('New') }}</router-link>
      </div>
    </b-card>
  </div>
</template>

<script>
export default {
  name: 'KnowledgeBaseGroups',
  metaInfo: { title: 'Knowledge Base - Groups' },
  data() {
    return { isLoading: true, busyId: null, q: '', groups: [] };
  },
  computed: {
    filtered() {
      const term = (this.q || '').toLowerCase();
      if (!term) return this.groups;
      return this.groups.filter(g =>
        String(g.name || '').toLowerCase().includes(term) ||
        String(g.slug || '').toLowerCase().includes(term)
      );
    }
  },
  mounted() {
    this.fetch();
  },
  methods: {
    makeToast(variant, msg) {
      if (this.$root && this.$root.$bvToast) this.$root.$bvToast.toast(msg, { variant, solid: true });
    },
    async fetch() {
      this.isLoading = true;
      try {
        const res = await axios.get('/knowledge-base/groups');
        this.groups = Array.isArray(res.data) ? res.data : (res.data.data || []);
      } catch (e) {
        this.makeToast('danger', this.$t('Failed_to_load'));
        this.groups = [];
      } finally {
        this.isLoading = false;
      }
    },
    async destroy(g) {
      if (!confirm(this.$t('Confirm_Delete_This_Item'))) return;
      try {
        this.busyId = g.id;
        await axios.delete('/knowledge-base/groups/' + g.id);
        this.makeToast('success', this.$t('Deleted_successfully'));
        this.groups = this.groups.filter(x => x.id !== g.id);
      } catch (e) {
        this.makeToast('danger', this.$t('Delete_failed'));
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
.kb-search-input { max-width: 280px; }
.kb-search-input .input-group-text { border-radius: 8px 0 0 8px; background: #f8f9fa; }
.kb-actions { display: flex; gap: 0.5rem; }

.kb-groups-list { border-top: 1px solid #eee; }
.kb-group-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 0;
  border-bottom: 1px solid #f0f0f0;
}
.kb-group-item:last-child { border-bottom: none; }
.kb-group-order { width: 36px; text-align: center; color: #adb5bd; font-size: 0.85rem; }
.kb-group-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: rgba(102, 126, 234, 0.1);
  color: #667eea;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.kb-group-body { flex: 1; min-width: 0; }
.kb-group-name { font-weight: 600; color: #2d3748; margin-bottom: 0.15rem; }
.kb-group-desc { max-width: 400px; margin-bottom: 0.25rem; }
.kb-group-slug { font-size: 0.75rem; color: #718096; background: #f1f3f5; padding: 0.15rem 0.4rem; border-radius: 4px; }
.kb-group-count { text-align: center; min-width: 70px; }
.kb-group-count .badge { font-size: 0.9rem; }
.kb-group-actions { display: flex; gap: 0.35rem; flex-shrink: 0; }

.kb-empty { text-align: center; padding: 3rem 1.5rem; }
.kb-empty-icon { font-size: 2.5rem; color: #dee2e6; margin-bottom: 1rem; }
.kb-empty-title { font-weight: 600; margin-bottom: 1rem; }
</style>
