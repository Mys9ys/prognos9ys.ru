<template>
  <div class="inventory_block" v-if="game">
    <div class="inventory_summary" v-if="hasAnyItems">
      <span class="summary_label">В инвентаре</span>
      <span class="summary_value">{{ totalItems }}</span>
    </div>

    <div class="inventory_grid" v-if="inventoryItems.length">
      <div
        v-for="item in inventoryItems"
        :key="item.id"
        class="inventory_slot"
        :title="item.label"
      >
        <div class="slot_icon">
          <span v-if="item.emoji" class="slot_emoji">{{ item.emoji }}</span>
          <AppIcon v-else :name="item.icon" :size="slotIconSize" />
        </div>
        <span class="slot_count">{{ item.count }}</span>
      </div>
    </div>

    <div class="inventory_empty" v-else>
      Пока пусто — зарабатывайте сундуки в матчах, повышайте уровень или покупайте в лавке казны.
    </div>
  </div>
</template>

<script>
import AppIcon from '@/components/ui/AppIcon.vue';

const INVENTORY_SLOTS = [
  { id: 'match', field: 'match_chests', icon: 'chest_wc2026', label: 'Сундуки за баллы в матчах' },
  { id: 'achievement', field: 'achievement_chests', icon: 'chest_wc2026', label: 'Сундуки за ачивки' },
  { id: 'level', field: 'level_chests', icon: 'chest_xp', label: 'Сундуки за уровень' },
  { id: 'shop', field: 'shop_chests', icon: 'chest_wc2026', label: 'Сундуки из лавки казны' },
  { id: 'premium', field: 'premium_scrolls', emoji: '📜', label: 'Свиток премиума (1 сутки)' },
];

export default {
  name: 'ProfileInventoryBlock',
  components: { AppIcon },
  props: {
    game: {
      type: Object,
      default: null,
    },
  },
  computed: {
    treasure() {
      return this.game?.treasure || {};
    },
    inventoryItems() {
      return INVENTORY_SLOTS
        .map((slot) => ({
          ...slot,
          count: Number(this.treasure[slot.field] ?? 0),
        }))
        .filter((slot) => slot.count > 0);
    },
    totalItems() {
      return this.inventoryItems.reduce((sum, item) => sum + item.count, 0);
    },
    hasAnyItems() {
      return this.totalItems > 0;
    },
    slotIconSize() {
      return 34;
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.inventory_block {
  background: @DarkColorBG;
  color: @colorText;
  padding: 8px;
  border-radius: 5px;
  margin: 8px 0;
  text-align: left;
}

.inventory_summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  .shadow_inset;
  padding: 6px 8px;
  margin-bottom: 8px;
  font-size: 13px;

  .summary_label {
    color: @colorBlur;
  }

  .summary_value {
    font-size: 15px;
    font-weight: 700;
  }
}

.inventory_grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 3px;
  padding: 4px;
  background: darken(@darkbg, 4%);
  border-radius: 4px;
  border: 1px solid fade(@colorBlur, 20%);
}

.inventory_slot {
  position: relative;
  aspect-ratio: 1;
  min-height: 52px;
  border: 1px solid fade(@colorBlur, 35%);
  border-radius: 2px;
  background: linear-gradient(180deg, fade(@DarkColorBG, 90%) 0%, fade(@darkbg, 95%) 100%);
  box-sizing: border-box;
  cursor: default;
}

.slot_icon {
  position: absolute;
  inset: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}

.slot_emoji {
  font-size: 28px;
  line-height: 1;
}

.slot_count {
  position: absolute;
  right: 2px;
  bottom: 2px;
  min-width: 14px;
  padding: 0 3px;
  font-size: 10px;
  font-weight: 700;
  line-height: 14px;
  text-align: center;
  color: @colorText;
  background: fade(@darkbg, 85%);
  border: 1px solid fade(@colorBlur, 45%);
  border-radius: 2px;
  box-shadow: 0 0 0 1px fade(#000, 25%);
}

.inventory_empty {
  padding: 12px 8px;
  font-size: 12px;
  line-height: 1.45;
  color: @colorBlur;
  text-align: center;
}
</style>
