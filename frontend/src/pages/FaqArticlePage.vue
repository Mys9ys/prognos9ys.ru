<template>
  <div class="faq_wrapper">
    <PageHeader class="header">Как играть</PageHeader>
    <GuideArticleBody v-if="article" :article="article" />
    <div v-else class="guide_missing">
      <p>Статья не найдена.</p>
      <router-link to="/faq">← К списку статей</router-link>
    </div>
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader';
import GuideArticleBody from '@/components/guide/GuideArticleBody.vue';
import { getGuideArticle } from '@/config/guideArticles';

export default {
  name: 'FaqArticlePage',
  components: {
    PageHeader,
    GuideArticleBody,
  },
  computed: {
    article() {
      return getGuideArticle(this.$route.params.slug);
    },
  },
  watch: {
    article: {
      immediate: true,
      handler(article) {
        if (article?.title) {
          document.title = article.title + ' | Как играть';
        }
      },
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.faq_wrapper {
  padding: 0 4px 12px;
}

.guide_missing {
  text-align: left;
  font-size: 13px;
  color: @colorBlur;

  a {
    color: @orange;
  }
}
</style>
