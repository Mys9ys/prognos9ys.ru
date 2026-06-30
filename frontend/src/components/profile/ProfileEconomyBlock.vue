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
      <ProfileFarmBlock v-else-if="activeTab === 'farm'" />
    </div>
  </div>
</template>

<script>
import ProfileBankBlock from '@/components/profile/ProfileBankBlock.vue';
import ProfileTreasuryBlock from '@/components/profile/ProfileTreasuryBlock.vue';
import ExchangeBlock from '@/components/game/ExchangeBlock.vue';
import ProfileFarmBlock from '@/components/profile/ProfileFarmBlock.vue';

export default {
  name: 'ProfileEconomyBlock',
  components: {
    ProfileBankBlock,
    ProfileTreasuryBlock,
    ExchangeBlock,
    ProfileFarmBlock,
  },
  props: {
    game: {
      type: Object,
      default: null,
    },
    initialTab: {
      type: String,
      default: 'bank',
    },
  },
  data() {
    return {
      activeTab: this.initialTab || 'bank',
    };
  },
  watch: {
    initialTab(tab) {
      if (tab && this.activeTab !== tab) {
        this.activeTab = tab;
      }
    },
  },
  computed: {
    mainTabs() {
      return [
        { id: 'bank', label: 'Банк' },
        { id: 'treasury', label: 'Казна' },
        { id: 'exchange', label: 'Биржа' },
        { id: 'farm', label: 'Работа' },
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
</style>
