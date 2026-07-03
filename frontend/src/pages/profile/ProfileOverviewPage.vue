<template>
  <div class="profile_overview">
    <PageHeader>Ваш профиль</PageHeader>

    <div class="section_card">
      <ProfileGameBlock :game="gameInfo" v-if="gameInfo" />
      <div v-else class="hint">Загрузка…</div>
    </div>

    <div class="section_card">
      <div class="section_title">Разделы</div>
      <div class="hub_grid">
        <router-link
          v-for="item in hubLinks"
          :key="item.id"
          :to="item.route"
          class="hub_card"
        >
          <span class="hub_emoji" v-if="item.emoji">{{ item.emoji }}</span>
          <AppIcon v-else-if="item.icon" :name="item.icon" :size="22" />
          <span class="hub_label">{{ item.title }}</span>
        </router-link>
      </div>
    </div>

    <div class="section_card">
      <ProfileRulesBlock />
    </div>
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader.vue';
import ProfileGameBlock from '@/components/profile/ProfileGameBlock.vue';
import ProfileRulesBlock from '@/components/profile/ProfileRulesBlock.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import gamePageMixin from '@/mixins/gamePageMixin';

export default {
  name: 'ProfileOverviewPage',
  components: {
    PageHeader,
    ProfileGameBlock,
    ProfileRulesBlock,
    AppIcon,
  },
  mixins: [gamePageMixin],
  computed: {
    hubLinks() {
      return [
        { id: 'prognosis', title: 'Прогнозы', icon: 'prognosis', route: '/prognosis' },
        { id: 'inventory', title: 'Инвентарь', emoji: '🎒', route: '/inventory' },
        { id: 'collection', title: 'Коллекция', emoji: '📔', route: '/collection' },
        { id: 'achievements', title: 'Награды', icon: 'achievement', route: '/achievements' },
        { id: 'bank', title: 'Банк', icon: 'bank', route: '/bank' },
        { id: 'market', title: 'Биржа', icon: 'rublius', route: '/market' },
        { id: 'work', title: 'Работа', icon: 'xp', route: '/work' },
        { id: 'estate', title: 'Усадьба', emoji: '🏡', route: '/estate' },
        { id: 'treasury', title: 'Казна', icon: 'chest_wc2026', route: '/treasury' },
        { id: 'settings', title: 'Настройки', icon: 'settings', route: '/settings' },
      ];
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.profile_overview {
  padding-bottom: 8px;
}

.section_card {
  background: @DarkColorBG;
  padding: 8px;
  border-radius: 5px;
  margin-bottom: 8px;
}

.section_title {
  color: @colorText;
  font-size: 14px;
  margin-bottom: 8px;
  text-align: left;
}

.hint {
  color: @colorBlur;
  padding: 4px 0;
}

.hub_grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
}

.hub_card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-height: 72px;
  padding: 6px 4px;
  border-radius: 5px;
  background: @colorText2;
  color: @colorText;
  text-decoration: none;
  .shadow_inset;

  &:active {
    opacity: 0.9;
  }
}

.hub_emoji {
  font-size: 22px;
  line-height: 1;
}

.hub_label {
  font-size: 11px;
  text-align: center;
  line-height: 1.2;
}
</style>
