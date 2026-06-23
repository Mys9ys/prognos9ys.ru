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
          <AppIcon v-else :name="item.icon" size="100%" icon-class="slot_app_icon" />
        </div>
        <span class="slot_count">{{ item.count }}</span>
        <span class="slot_caption">{{ item.caption }}</span>
      </div>
    </div>

    <div class="inventory_empty" v-else>
      Пока пусто — зарабатывайте сундуки в матчах, ачивки, повышайте уровень или покупайте в лавке казны.
    </div>
  </div>
</template>

<script>
import AppIcon from '@/components/ui/AppIcon.vue';

const INVENTORY_SLOTS = [
  { id: 'match', field: 'match_chests', icon: 'chest_wc2026', caption: 'ЧМ', label: 'Сундуки за баллы в матчах' },
  { id: 'achievement', field: 'achievement_chests', icon: 'chest_achievement', caption: 'Ачивка', label: 'Сундуки за ачивки' },
  { id: 'level', field: 'level_chests', icon: 'chest_xp', caption: 'Уровень', label: 'Сундуки за уровень' },
  { id: 'shop', field: 'shop_chests', icon: 'chest_wc2026', caption: 'Лавка', label: 'Сундуки из лавки казны' },
  { id: 'premium_1d', field: 'premium_scrolls_1d', icon: 'premium_scroll_1d', caption: '1д', label: 'Свиток премиума (1 сутки)' },
  { id: 'premium_3d', field: 'premium_scrolls_3d', emoji: '📜', caption: '3д', label: 'Свиток премиума (3 суток)' },
  { id: 'premium_5d', field: 'premium_scrolls_5d', emoji: '📜', caption: '5д', label: 'Свиток премиума (5 суток)' },
  { id: 'pennant_site', field: 'pennant_site', icon: 'pennant_site', caption: 'Сайт', label: 'Вымпел Прогносяус' },
  { id: 'pennant_chm2026', field: 'pennant_chm2026', icon: 'pennant_chm2026', caption: 'ЧМ26', label: 'Вымпел ЧМ-2026' },
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
        .map((slot) => {
          let count = Number(this.treasure[slot.field] ?? 0);
          if (slot.id === 'premium_1d' && !count) {
            count = Number(this.treasure.premium_scrolls ?? 0);
          }

          return { ...slot, count };
        })
        .filter((slot) => slot.count > 0);
    },
    totalItems() {
      return this.inventoryItems.reduce((sum, item) => sum + item.count, 0);
    },
    hasAnyItems() {
      return this.totalItems > 0;
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
  min-height: 58px;
  border: 1px solid fade(@colorBlur, 35%);
  border-radius: 2px;
  background: linear-gradient(180deg, fade(@DarkColorBG, 90%) 0%, fade(@darkbg, 95%) 100%);
  box-sizing: border-box;
  cursor: default;
}

.slot_icon {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  z-index: 1;

  :deep(.slot_app_icon) {
    width: 100% !important;
    height: 100% !important;
    min-width: 0 !important;
    min-height: 0 !important;
  }

  :deep(.app_icon) {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
  }
}

.slot_emoji {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: clamp(22px, 72%, 40px);
  line-height: 1;
}

.slot_count {
  position: absolute;
  right: 2px;
  top: 2px;
  z-index: 3;
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

.slot_caption {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 2;
  font-size: 8px;
  line-height: 10px;
  text-align: center;
  color: @colorText;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  padding: 1px 2px;
  background: fade(@darkbg, 82%);
}

.inventory_empty {
  padding: 12px 8px;
  font-size: 12px;
  line-height: 1.45;
  color: @colorBlur;
  text-align: center;
}
</style>
