<template>
  <div class="ency_wrapper">
    <PageHeader>Энциклопедия</PageHeader>

    <div class="ency_intro">
      <p>
        Полный справочник материалов, рецептов, экипировки и построек.
        Доступен <strong>без регистрации</strong> — цифры подтягиваются из игровых конфигов.
      </p>
      <router-link v-if="!token" class="ency_auth_btn" to="/auth">Войти или зарегистрироваться</router-link>
    </div>

    <div v-if="loading" class="ency_hint">Загрузка справочника…</div>
    <div v-else-if="error" class="ency_error">{{ error }}</div>

    <template v-else-if="encyclopedia">
      <div class="ency_tabs">
        <button
          v-for="section in encyclopedia.sections"
          :key="section.id"
          type="button"
          class="ency_tab"
          :class="{ active: activeSectionId === section.id }"
          @click="setSection(section.id)"
        >
          {{ section.label }}
          <span class="ency_tab_count">{{ section.entries.length }}</span>
        </button>
      </div>

      <div v-if="activeSection" class="ency_grid">
        <EncyclopediaEntryCard
          v-for="entry in activeSection.entries"
          :key="entry.code"
          :section-id="activeSection.id"
          :entry="entry"
        />
      </div>
    </template>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import PageHeader from '@/components/main/PageHeader.vue';
import EncyclopediaEntryCard from '@/components/encyclopedia/EncyclopediaEntryCard.vue';
import { apiActions } from '@/api/bitrixClient';

export default {
  name: 'EncyclopediaPage',
  components: {
    PageHeader,
    EncyclopediaEntryCard,
  },
  data() {
    return {
      loading: true,
      error: '',
      encyclopedia: null,
      activeSectionId: 'materials',
    };
  },
  computed: {
    ...mapState({
      token: (state) => state.auth.authData.token,
    }),
    activeSection() {
      if (!this.encyclopedia) return null;
      return this.encyclopedia.sections.find((s) => s.id === this.activeSectionId)
        || this.encyclopedia.sections[0]
        || null;
    },
  },
  watch: {
    '$route.query.section': {
      immediate: true,
      handler(sectionId) {
        if (sectionId) {
          this.activeSectionId = String(sectionId);
        }
      },
    },
  },
  async created() {
    try {
      const data = await apiActions.game.getEncyclopedia(this.token || '');
      if (data.status !== 'ok' || !data.encyclopedia) {
        throw new Error(data.message || 'Не удалось загрузить энциклопедию');
      }
      this.encyclopedia = data.encyclopedia;
      if (this.$route.query.section) {
        this.activeSectionId = String(this.$route.query.section);
      } else if (this.encyclopedia.sections.length) {
        this.activeSectionId = this.encyclopedia.sections[0].id;
      }
    } catch (err) {
      this.error = err.message || 'Ошибка загрузки';
    } finally {
      this.loading = false;
    }
  },
  methods: {
    setSection(sectionId) {
      this.activeSectionId = sectionId;
      this.$router.replace({
        path: '/encyclopedia',
        query: { section: sectionId },
      }).catch(() => {});
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.ency_wrapper {
  padding: 0 4px 12px;
}

.ency_intro {
  margin-bottom: 12px;
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
    }
  }
}

.ency_auth_btn {
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

.ency_hint,
.ency_error {
  font-size: 13px;
  padding: 8px 0;
}

.ency_error {
  color: @NoWrite;
}

.ency_tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 10px;
}

.ency_tab {
  border: 1px solid fade(@colorBlur, 35%);
  background: @colorText2;
  color: @colorText;
  border-radius: 4px;
  padding: 5px 8px;
  font-size: 11px;
  cursor: pointer;
  .shadow_inset;

  &.active {
    border-color: fade(@orange, 70%);
    color: @orange;
    font-weight: 700;
  }
}

.ency_tab_count {
  margin-left: 4px;
  color: @colorBlur;
  font-weight: 400;
}

.ency_grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 6px;
}
</style>
