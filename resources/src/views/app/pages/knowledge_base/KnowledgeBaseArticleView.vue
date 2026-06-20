<template>
  <div class="main-content kb-article-page">
    <breadcumb
      :page="article.title"
      :folder="$t('Knowledge_Base') || 'Knowledge Base'"
    />
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="kb-article-wrap">
      <b-card class="kb-article-card shadow-sm">
        <!-- Header -->
        <div class="kb-article-header">
          <div class="kb-article-header-content">
            <h1 class="kb-article-title">{{ article.title }}</h1>
            <div class="kb-article-badges">
              <span class="kb-badge-group">{{ article.group ? article.group.name : '' }}</span>
              <b-badge v-if="article.is_internal" variant="warning" class="kb-badge-internal">{{ $t('Internal') || 'Internal' }}</b-badge>
            </div>
          </div>
          <router-link :to="{ name: 'KnowledgeBaseList' }" class="btn btn-outline-secondary btn-sm kb-back-btn">
            <lucide-icon name="chevron-left" /> {{ $t('Back') }}
          </router-link>
        </div>

        <!-- Content -->
        <div class="kb-article-content ql-editor" v-html="article.content"></div>

        <!-- Feedback section -->
        <div class="kb-feedback-box">
          <p class="kb-feedback-question">{{ $t('Was_this_helpful') || 'Was this helpful?' }}</p>
          <div class="kb-feedback-buttons">
            <b-button
              :variant="feedbackSent && userFeedback === true ? 'success' : 'outline-success'"
              size="sm"
              class="kb-feedback-btn"
              :disabled="feedbackSent && userFeedback !== null"
              @click="submitFeedback(true)"
            >
              <lucide-icon name="check" /> {{ $t('Yes') }}
            </b-button>
            <b-button
              :variant="feedbackSent && userFeedback === false ? 'danger' : 'outline-danger'"
              size="sm"
              class="kb-feedback-btn"
              :disabled="feedbackSent && userFeedback !== null"
              @click="submitFeedback(false)"
            >
              <lucide-icon name="x" /> {{ $t('No') }}
            </b-button>
            <span v-if="feedbackSent" class="kb-feedback-thanks">{{ $t('Thank_you_for_feedback') || 'Thank you for your feedback.' }}</span>
          </div>
          <p class="kb-feedback-stats text-muted small mb-0" v-if="article.feedback_helpful_count != null || article.feedback_not_helpful_count != null">
            {{ (article.feedback_helpful_count || 0) }} {{ $t('Yes') }} · {{ (article.feedback_not_helpful_count || 0) }} {{ $t('No') }}
          </p>
        </div>
      </b-card>
    </div>
  </div>
</template>

<script>
export default {
  name: 'KnowledgeBaseArticleView',
  metaInfo() {
    return { title: this.article.title || 'Article' };
  },
  props: {
    id: { type: [String, Number], required: true }
  },
  data() {
    return {
      isLoading: true,
      article: {},
      feedbackSent: false,
      userFeedback: null
    };
  },
  mounted() {
    this.fetch();
  },
  methods: {
    async fetch() {
      this.isLoading = true;
      try {
        const res = await axios.get('/knowledge-base/articles/' + this.id);
        this.article = res.data;
      } catch (e) {
        if (e.response && e.response.status === 403) {
          if (this.$root && this.$root.$bvToast) {
            this.$root.$bvToast.toast(this.$t('Access_denied') || 'Access denied', { variant: 'danger', solid: true });
          }
          this.$router.replace({ name: 'KnowledgeBaseList' });
          return;
        }
        this.article = {};
        if (this.$root && this.$root.$bvToast) {
          this.$root.$bvToast.toast(this.$t('Failed_to_load') || 'Failed to load', { variant: 'danger', solid: true });
        }
      } finally {
        this.isLoading = false;
      }
    },
    async submitFeedback(helpful) {
      try {
        await axios.post('/knowledge-base/articles/' + this.id + '/feedback', { helpful });
        this.feedbackSent = true;
        this.userFeedback = helpful;
        if (helpful) this.article.feedback_helpful_count = (this.article.feedback_helpful_count || 0) + 1;
        else this.article.feedback_not_helpful_count = (this.article.feedback_not_helpful_count || 0) + 1;
        if (this.$root && this.$root.$bvToast) {
          this.$root.$bvToast.toast(this.$t('Thank_you_for_feedback') || 'Thank you for your feedback.', { variant: 'success', solid: true });
        }
      } catch (e) {
        if (this.$root && this.$root.$bvToast) {
          this.$root.$bvToast.toast(this.$t('Failed_to_save') || 'Failed to save', { variant: 'danger', solid: true });
        }
      }
    }
  }
};
</script>

<style scoped>
.kb-article-page { padding-bottom: 2rem; }

.kb-article-wrap { max-width: 900px; }
.kb-article-card { border-radius: 12px; border: none; }

.kb-article-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid #eee;
}
.kb-article-header-content { flex: 1; min-width: 0; }
.kb-article-title {
  font-size: 1.6rem;
  font-weight: 700;
  color: #2d3748;
  margin: 0 0 0.5rem 0;
  line-height: 1.3;
}
.kb-article-badges { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.kb-badge-group {
  font-size: 0.8rem;
  color: #667eea;
  background: rgba(102, 126, 234, 0.12);
  padding: 0.35rem 0.65rem;
  border-radius: 8px;
  font-weight: 500;
}
.kb-badge-internal { font-size: 0.75rem; }
.kb-back-btn { border-radius: 8px; }

.kb-article-content {
  min-height: 80px;
  line-height: 1.75;
  color: #4a5568;
  font-size: 1rem;
}
.kb-article-content >>> img { max-width: 100%; height: auto; border-radius: 8px; }
.kb-article-content >>> h1, .kb-article-content >>> h2, .kb-article-content >>> h3 { color: #2d3748; margin-top: 1.25em; margin-bottom: 0.5em; }
.kb-article-content >>> p { margin-bottom: 0.75em; }
.kb-article-content >>> ul, .kb-article-content >>> ol { padding-left: 1.5em; margin-bottom: 0.75em; }
.kb-article-content >>> a { color: #667eea; }
.kb-article-content >>> blockquote { border-left: 4px solid #667eea; padding-left: 1rem; margin: 1rem 0; color: #718096; }

.kb-feedback-box {
  margin-top: 2rem;
  padding: 1.25rem;
  background: #f8f9fc;
  border-radius: 12px;
  border: 1px solid #eef1f6;
}
.kb-feedback-question { font-weight: 600; margin-bottom: 0.75rem; color: #2d3748; }
.kb-feedback-buttons { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.kb-feedback-btn { border-radius: 8px; }
.kb-feedback-thanks { margin-left: 0.5rem; color: #48bb78; font-size: 0.9rem; }
.kb-feedback-stats { margin-top: 0.75rem; font-size: 0.85rem; }
</style>
