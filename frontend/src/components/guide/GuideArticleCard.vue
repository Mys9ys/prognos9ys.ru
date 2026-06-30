<template>
  <router-link class="guide_card" :to="'/faq/' + article.slug">
    <div class="guide_card_title">{{ article.title }}</div>
    <div class="guide_card_summary">{{ article.summary }}</div>
  </router-link>
</template>

<script>
export default {
  name: 'GuideArticleCard',
  props: {
    article: {
      type: Object,
      required: true,
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.guide_card {
  display: block;
  padding: 10px;
  border-radius: 6px;
  background: fade(@DarkColorBG, 85%);
  border: 1px solid fade(@colorBlur, 25%);
  text-decoration: none;
  text-align: left;

  &:hover {
    border-color: fade(@orange, 55%);
  }
}

.guide_card_title {
  font-size: 13px;
  font-weight: 700;
  color: @colorText;
  margin-bottom: 4px;
}

.guide_card_summary {
  font-size: 11px;
  color: @colorBlur;
  line-height: 1.35;
}
</style>
