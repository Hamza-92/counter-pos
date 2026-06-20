<template>
  <div class="portal-page portal-article">
    <header class="pc-page-header">
      <router-link to="/help" class="pc-link-back">&larr; Back to help center</router-link>
    </header>

    <div v-if="loading" class="pc-inline-loading">
      <div class="pc-spinner"></div><span>Loading article...</span>
    </div>

    <article v-else-if="article.id" class="pc-card pc-article-body">
      <div v-if="article.group" class="pc-article-crumb">{{ article.group.name }}</div>
      <h1 class="pc-article-h1">{{ article.title }}</h1>
      <div v-if="article.updated_at" class="pc-article-meta">Updated {{ formatDate(article.updated_at) }}</div>
      <div class="pc-article-content" v-html="article.content || ''"></div>
    </article>

    <div v-else class="pc-card pc-empty">
      <div class="pc-empty-icon">📄</div>
      <p>Article not found</p>
    </div>
  </div>
</template>

<script>
export default {
  data() { return { article: {}, loading: false }; },
  watch: { '$route.params.slug': { handler() { this.fetch(); }, immediate: false } },
  mounted() { this.fetch(); },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const { data } = await axios.get(`/portal/knowledge-base/${this.$route.params.slug}`);
        this.article = data || {};
      } catch (_) {
        this.article = {};
      }
      this.loading = false;
    },
    formatDate(iso) {
      if (!iso) return '';
      try { return new Date(iso).toLocaleDateString(); } catch (_) { return iso; }
    },
  },
};
</script>

<style scoped>
.portal-article { padding-bottom: 1rem; }
.pc-page-header { margin-bottom: 1rem; }
.pc-link-back { color: var(--pc-text-muted); text-decoration: none; font-size: 0.88rem; }
.pc-link-back:hover { color: var(--pc-primary); }
.pc-card { background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: var(--pc-radius); box-shadow: var(--pc-shadow-sm); }
.pc-article-body { padding: 2rem; max-width: 820px; margin: 0 auto; }
.pc-article-crumb { font-size: 0.78rem; font-weight: 600; color: var(--pc-primary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
.pc-article-h1 { font-size: 1.85rem; font-weight: 800; color: var(--pc-text); margin: 0 0 0.45rem; letter-spacing: -0.015em; line-height: 1.2; }
.pc-article-meta { font-size: 0.82rem; color: var(--pc-text-soft); margin-bottom: 1.5rem; }
.pc-article-content { font-size: 1rem; line-height: 1.7; color: var(--pc-text); }
.pc-article-content >>> h2 { font-size: 1.3rem; font-weight: 700; margin: 1.6rem 0 0.6rem; }
.pc-article-content >>> h3 { font-size: 1.1rem; font-weight: 700; margin: 1.3rem 0 0.5rem; }
.pc-article-content >>> p { margin: 0 0 1rem; }
.pc-article-content >>> ul, .pc-article-content >>> ol { margin: 0 0 1rem; padding-left: 1.4rem; }
.pc-article-content >>> li { margin-bottom: 0.3rem; }
.pc-article-content >>> a { color: var(--pc-primary); text-decoration: none; }
.pc-article-content >>> a:hover { text-decoration: underline; }
.pc-article-content >>> code { background: var(--pc-surface-alt); padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.88em; }
.pc-article-content >>> pre { background: var(--pc-surface-alt); padding: 0.9rem 1rem; border-radius: 10px; overflow-x: auto; }
.pc-article-content >>> blockquote { border-left: 3px solid var(--pc-border-strong); padding-left: 1rem; color: var(--pc-text-muted); margin: 1rem 0; }
.pc-article-content >>> img { max-width: 100%; height: auto; border-radius: 10px; }
.pc-inline-loading { display: flex; align-items: center; justify-content: center; gap: 0.75rem; padding: 3rem; color: var(--pc-text-muted); }
.pc-spinner { width: 28px; height: 28px; border: 2px solid rgba(79, 70, 229, 0.15); border-top-color: var(--pc-primary); border-radius: 50%; animation: pc-spin 0.7s linear infinite; }
@keyframes pc-spin { to { transform: rotate(360deg); } }
.pc-empty { padding: 3rem 1rem; text-align: center; color: var(--pc-text-soft); }
.pc-empty-icon { font-size: 2.5rem; opacity: 0.7; margin-bottom: 0.5rem; }
@media (max-width: 768px) { .pc-article-body { padding: 1.25rem; } .pc-article-h1 { font-size: 1.4rem; } }
</style>
