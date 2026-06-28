<template>
  <div class="economy_block">
    <div class="main_tabs">
      <button
        v-for="tab in mainTabs"
        :key="tab.id"
        type="button"
        class="main_tab"
        :class="{ active: activeTab === tab.id }"
        @click="activeTab = tab.id"
      >{{ tab.label }}</button>
    </div>

    <div class="tab_panel">
      <ProfileBankBlock v-if="activeTab === 'bank'" :game="game" />
      <ProfileTreasuryBlock v-else-if="activeTab === 'treasury'" :game="game" />
      <ExchangeBlock v-else-if="activeTab === 'exchange'" />
      <div v-else-if="activeTab === 'farm'" class="farm_placeholder">
        <div class="placeholder_title">Гос. делянка</div>
        <p class="placeholder_text">
          Добывающие профессии между матчами — в разработке. Здесь будут лесозаготовка, рудник,
          каменоломня и работа на казну.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import ProfileBankBlock from '@/components/profile/ProfileBankBlock.vue';
import ProfileTreasuryBlock from '@/components/profile/ProfileTreasuryBlock.vue';
import ExchangeBlock from '@/components/game/ExchangeBlock.vue';

export default {
  name: 'ProfileEconomyBlock',
  components: {
    ProfileBankBlock,
    ProfileTreasuryBlock,
    ExchangeBlock,
  },
  props: {
    game: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      activeTab: 'bank',
    };
  },
  computed: {
    mainTabs() {
      return [
        { id: 'bank', label: 'Банк' },
        { id: 'treasury', label: 'Казна' },
        { id: 'exchange', label: 'Биржа' },
        { id: 'farm', label: 'Фарм' },
      ];
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.economy_block {
  text-align: left;
}

.main_tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 10px;
}

.main_tab {
  background: @darkbg;
  color: @colorText;
  border: 1px solid transparent;
  border-radius: 4px;
  padding: 7px 12px;
  font-size: 12px;
  cursor: pointer;

  &.active {
    background: @orange;
    color: #fff;
  }
}

.tab_panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.farm_placeholder {
  .shadow_inset;
  padding: 10px;
  border-radius: 4px;
}

.placeholder_title {
  font-size: 13px;
  color: @orange;
  margin-bottom: 6px;
}

.placeholder_text {
  font-size: 12px;
  color: @colorBlur;
  line-height: 1.4;
  margin: 0;
}
</style>
