<template>
  <div v-if="levelBanner.visible" class="level_up_banner">
    <div class="level_up_banner_body">
      <span class="level_up_banner_text">{{ bannerHeadline }}</span>
      <div v-if="levelBanner.rewards?.length" class="level_up_banner_rewards">
        <div
            v-for="(reward, index) in levelBanner.rewards"
            :key="`${reward.level}-${index}`"
            class="reward_row"
        >
          <span class="reward_level">ур. {{ reward.level }}</span>
          <span v-if="Number(reward.prognobaks) > 0" class="reward_bit">
            +{{ formatAmount(reward.prognobaks) }}
            <AppIcon name="prognobak" :size="12" />
          </span>
          <span v-if="Number(reward.rublius) > 0" class="reward_bit">
            +{{ formatAmount(reward.rublius) }}
            <AppIcon name="rublius" :size="12" />
          </span>
          <span v-if="Number(reward.chests) > 0" class="reward_bit">
            +{{ reward.chests }}
            <AppIcon :name="getChestIconName(reward.chest_type)" :size="12" />
          </span>
        </div>
      </div>
    </div>
    <button type="button" class="level_up_banner_close" @click="closeLevelBanner">×</button>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import AppIcon from '@/components/ui/AppIcon.vue';
import { formatAmount, getChestIconName } from '@/utils/formatLevelRewards';

export default {
  name: 'LevelUpBanner',
  components: { AppIcon },
  computed: {
    ...mapState({
      levelBanner: state => state.game.levelBanner,
    }),
    bannerHeadline() {
      const from = Number(this.levelBanner.from || 0);
      const level = Number(this.levelBanner.level || 0);

      if (from > 0 && level > from) {
        return `Поздравляем! Уровни ${from}–${level}`;
      }

      return `Поздравляем! Получен новый уровень: ${level}`;
    },
  },
  methods: {
    ...mapActions({
      closeLevelBanner: 'game/closeLevelBanner',
    }),
    formatAmount,
    getChestIconName,
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.level_up_banner {
  .inset_panel_wrapper();
  margin-top: 6px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 6px;
  width: 100%;
  box-sizing: border-box;
}

.level_up_banner_body {
  .shadow_inset;
  padding: 6px 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  flex: 1;
}

.level_up_banner_text {
  color: @orange;
  font-size: 12px;
  font-weight: 500;
  line-height: 1.25;
  text-align: left;
}

.level_up_banner_rewards {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.reward_row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  color: @colorText;
  font-size: 11px;
  line-height: 1.3;
}

.reward_level {
  color: @colorText2;
}

.reward_bit {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}

.level_up_banner_close {
  border: 0;
  background: transparent;
  color: @colorBlur;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  padding: 2px 4px 0;
  flex-shrink: 0;
}
</style>
