<template>
  <div class="faq_wrapper">
    <PageHeader class="header">Как играть</PageHeader>

    <div class="guide_intro">
      <p>
        Здесь всё про прогнозы и игровую экономику. Ты можешь читать инструкции
        <strong>без регистрации</strong> — для прогнозов и кошелька понадобится аккаунт.
      </p>
      <p class="guide_intro_note">
        В статьях указано, где в меню искать нужный экран. Скриншоты интерфейса добавим отдельно.
      </p>
      <router-link v-if="!token" class="guide_auth_btn" to="/auth">Войти или зарегистрироваться</router-link>
    </div>

    <section
      v-for="level in levels"
      :key="level.id"
      class="guide_level"
    >
      <h2 class="guide_level_title">{{ level.label }}</h2>
      <div class="guide_cards">
        <GuideArticleCard
          v-for="article in articlesByLevel(level.id)"
          :key="article.slug"
          :article="article"
        />
      </div>
    </section>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import PageHeader from '@/components/main/PageHeader';
import GuideArticleCard from '@/components/guide/GuideArticleCard.vue';
import { GUIDE_LEVELS, guideArticles } from '@/config/guideArticles';

export default {
  name: 'FaqPage',
  components: {
    PageHeader,
    GuideArticleCard,
  },
  computed: {
    ...mapState({
      token: (state) => state.auth.authData.token,
    }),
    levels() {
      return GUIDE_LEVELS;
    },
  },
  methods: {
    articlesByLevel(levelId) {
      return guideArticles.filter((item) => item.level === levelId);
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.faq_wrapper {
  padding: 0 4px 12px;
}

.guide_intro {
  margin-bottom: 14px;
  padding: 10px;
  border-radius: 6px;
  background: fade(@DarkColorBG, 80%);
  border: 1px solid fade(@colorBlur, 20%);
  text-align: left;

  p {
    margin: 0 0 8px;
    font-size: 13px;
    color: @colorText;
    line-height: 1.45;

    strong {
      color: @YesWrite;
      font-weight: 700;
    }
  }

  .guide_intro_note {
    margin: 0;
    font-size: 11px;
    color: @colorBlur;
    line-height: 1.35;
  }
}

.guide_auth_btn {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 4px;
  background: fade(@orange, 25%);
  border: 1px solid fade(@orange, 70%);
  color: @colorText;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
}

.guide_level {
  margin-bottom: 16px;
}

.guide_level_title {
  font-size: 12px;
  font-weight: 700;
  color: @orange;
  text-align: left;
  margin: 0 0 8px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.guide_cards {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
</style>
