<template>
  <div class="rules_block">
    <div class="section_title">Как играть</div>
    <p class="rules_lead">
      Подробные инструкции по прогнозам и экономике — с картинками и пошаговыми подсказками.
      Читать можно без регистрации.
    </p>
    <router-link class="rules_link_main" to="/faq">Открыть все статьи →</router-link>
    <router-link class="rules_link_main rules_link_secondary" to="/encyclopedia">Энциклопедия предметов →</router-link>

    <div class="rules_quick">
      <router-link
        v-for="item in quickLinks"
        :key="item.slug"
        class="rules_quick_link"
        :to="'/faq/' + item.slug"
      >
        {{ item.title }}
      </router-link>
    </div>
  </div>
</template>

<script>
import { guideArticles } from '@/config/guideArticles';

export default {
  name: 'ProfileRulesBlock',
  computed: {
    quickLinks() {
      const slugs = ['start', 'prognosis', 'exchange', 'faq-extra'];
      return slugs
        .map((slug) => guideArticles.find((item) => item.slug === slug))
        .filter(Boolean);
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.rules_block {
  margin-top: 10px;
  .shadow_inset;
  padding: 8px;
  border-radius: 4px;
  text-align: left;
}

.section_title {
  font-size: 13px;
  color: @orange;
  margin-bottom: 6px;
}

.rules_lead {
  font-size: 12px;
  color: @colorBlur;
  line-height: 1.4;
  margin: 0 0 8px;
}

.rules_link_main {
  display: inline-block;
  margin-bottom: 10px;
  font-size: 12px;
  font-weight: 700;
  color: @YesWrite;
  text-decoration: none;

  &:hover {
    color: @orange;
  }
}

.rules_link_secondary {
  margin-left: 0;
  display: block;
}

.rules_quick {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.rules_quick_link {
  font-size: 12px;
  color: @colorText;
  text-decoration: none;
  padding: 4px 0;
  border-bottom: 1px solid fade(@colorBlur, 15%);

  &:hover {
    color: @orange;
  }

  &:last-child {
    border-bottom: none;
  }
}
</style>
