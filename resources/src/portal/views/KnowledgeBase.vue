<template>
  <div class="portal-page portal-kb">
    <header class="pc-page-header">
      <div>
        <h1 class="pc-page-title">Help center</h1>
        <p class="pc-page-sub">Browse our articles and guides</p>
      </div>
    </header>

    <div class="pc-card">
      <div class="pc-toolbar">
        <div class="pc-search">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <input v-model="search" type="text" placeholder="Search articles..." @input="debounceFetch" />
        </div>
      </div>

      <div v-if="loading" class="pc-inline-loading">
        <div class="pc-spinner"></div><span>Loading articles...</span>
      </div>

      <template v-else>
        <div v-if="!articles.length" class="pc-empty">
          <div class="pc-empty-icon">📚</div>
          <p>No articles found</p>
        </div>

        <div v-else class="pc-kb-content">
          <template v-if="search.trim()">
            <ul class="pc-article-list">
              <li v-for="a in articles" :key="a.id">
                <router-link :to="`/help/${a.slug}`" class="pc-article-link">
                  <div class="pc-article-title">{{ a.title }}</div>
                  <div class="pc-article-group" v-if="a.group">{{ a.group.name }}</div>
                </router-link>
              </li>
            </ul>
          </template>

          <template v-else>
            <section v-for="g in nonEmptyGroups" :key="g.id" class="pc-group-section">
              <h2 class="pc-group-title">{{ g.name }}</h2>
              <p class="pc-group-desc" v-if="g.description">{{ g.description }}</p>
              <ul class="pc-article-list">
                <li v-for="a in articlesByGroup[g.id]" :key="a.id">
                  <router-link :to="`/help/${a.slug}`" class="pc-article-link">
                    <div class="pc-article-title">{{ a.title }}</div>
                  </router-link>
                </li>
              </ul>
            </section>

            <section v-if="ungrouped.length" class="pc-group-section">
              <h2 class="pc-group-title">Other articles</h2>
              <ul class="pc-article-list">
                <li v-for="a in ungrouped" :key="a.id">
                  <router-link :to="`/help/${a.slug}`" class="pc-article-link">
                    <div class="pc-article-title">{{ a.title }}</div>
                  </router-link>
                </li>
              </ul>
            </section>
          </template>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return { groups: [], articles: [], search: '', loading: false, debounce: null };
  },
  computed: {
    articlesByGroup() {
      const map = {};
      for (const a of this.articles) {
        const gid = a.knowledge_base_article_group_id;
        if (!gid) continue;
        if (!map[gid]) map[gid] = [];
        map[gid].push(a);
      }
      return map;
    },
    nonEmptyGroups() {
      return this.groups.filter(g => (this.articlesByGroup[g.id] || []).length > 0);
    },
    ungrouped() {
      return this.articles.filter(a => !a.knowledge_base_article_group_id);
    },
  },
  mounted() { this.fetch(); },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const { data } = await axios.get('/portal/knowledge-base', { params: { q: this.search || undefined } });
        this.groups = data.groups || [];
        this.articles = data.articles || [];
      } catch (_) {}
      this.loading = false;
    },
    debounceFetch() {
      clearTimeout(this.debounce);
      this.debounce = setTimeout(() => { this.fetch(); }, 300);
    },
  },
};
</script>

<style scoped>
.portal-kb { padding-bottom: 1rem; }
.pc-page-header { margin-bottom: 1.25rem; }
.pc-page-title { font-size: 1.5rem; font-weight: 700; color: var(--pc-text); margin: 0 0 0.2rem; }
.pc-page-sub { font-size: 0.9rem; color: var(--pc-text-muted); margin: 0; }
.pc-card { background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: var(--pc-radius); box-shadow: var(--pc-shadow-sm); overflow: hidden; }
.pc-toolbar { padding: 0.9rem 1.15rem; border-bottom: 1px solid var(--pc-border); }
.pc-search { position: relative; display: flex; align-items: center; }
.pc-search svg { position: absolute; left: 0.85rem; color: var(--pc-text-soft); pointer-events: none; }
.pc-search input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.3rem; border: 1px solid var(--pc-border-strong); border-radius: 10px; font-size: 0.92rem; background: var(--pc-surface-alt); box-sizing: border-box; }
.pc-search input:focus { outline: none; background: var(--pc-surface); border-color: var(--pc-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }
.pc-inline-loading { display: flex; align-items: center; justify-content: center; gap: 0.75rem; padding: 3rem; color: var(--pc-text-muted); }
.pc-spinner { width: 28px; height: 28px; border: 2px solid rgba(79, 70, 229, 0.15); border-top-color: var(--pc-primary); border-radius: 50%; animation: pc-spin 0.7s linear infinite; }
@keyframes pc-spin { to { transform: rotate(360deg); } }
.pc-empty { padding: 3rem 1rem; text-align: center; color: var(--pc-text-soft); }
.pc-empty-icon { font-size: 2.5rem; opacity: 0.7; margin-bottom: 0.5rem; }
.pc-kb-content { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; }
.pc-group-section { padding-bottom: 1rem; border-bottom: 1px solid var(--pc-border); }
.pc-group-section:last-child { border-bottom: none; padding-bottom: 0; }
.pc-group-title { font-size: 1.05rem; font-weight: 700; color: var(--pc-text); margin: 0 0 0.3rem; }
.pc-group-desc { font-size: 0.85rem; color: var(--pc-text-muted); margin: 0 0 0.75rem; }
.pc-article-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.pc-article-link { display: flex; flex-direction: column; padding: 0.7rem 0.85rem; text-decoration: none; color: var(--pc-text); border-radius: 10px; transition: background 0.15s; }
.pc-article-link:hover { background: var(--pc-surface-alt); color: var(--pc-primary); }
.pc-article-title { font-size: 0.92rem; font-weight: 500; }
.pc-article-group { font-size: 0.78rem; color: var(--pc-text-soft); margin-top: 0.15rem; }
</style>
