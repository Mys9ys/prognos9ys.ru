<template>
  <div class="inventory_block" v-if="game">
    <div class="inventory_summary">
      <span class="summary_label">В инвентаре</span>
      <div class="summary_actions">
        <button type="button" class="summary_log_btn" @click="logModalVisible = true">
          Журнал
        </button>
        <span class="summary_value" v-if="hasAnyItems" :title="summaryTitle">
          {{ totalChests }}<span v-if="totalOtherItems" class="summary_other"> +{{ totalOtherItems }}</span>
        </span>
      </div>
    </div>

    <div class="inventory_grid" v-if="displayItems.length">
      <div
        v-for="item in displayItems"
        :key="item.id"
        class="inventory_slot"
        :class="{ openable: item.openable && item.count > 0 }"
        :title="item.label"
        @mouseenter="onSlotEnter(item)"
        @mouseleave="onSlotLeave"
      >
        <div class="slot_icon">
          <span v-if="item.emoji" class="slot_emoji">{{ item.emoji }}</span>
          <AppIcon v-else :name="item.icon" size="100%" icon-class="slot_app_icon" />
        </div>
        <span class="slot_count">{{ item.count }}</span>
        <span class="slot_caption">{{ item.caption }}</span>

        <div
          v-if="item.openable && item.count > 0 && hoveredSlotId === item.id"
          class="slot_actions"
        >
          <button type="button" class="slot_action_btn" :disabled="opening" @click.stop="openChests(item.pool, false)">
            Открыть
          </button>
          <button
            v-if="item.count > 1"
            type="button"
            class="slot_action_btn slot_action_btn_all"
            :disabled="opening"
            @click.stop="openChests(item.pool, true)"
          >
            Все ({{ Math.min(item.count, 30) }})
          </button>
        </div>
      </div>
    </div>

    <div class="inventory_empty" v-else>
      Пока пусто — зарабатывайте сундуки в матчах, ачивки, повышайте уровень или покупайте в лавке казны.
    </div>

    <BulkActionProgress
      :visible="openModalVisible"
      :title="openModalTitle"
      :lines="openLines"
      :current="openProgressCurrent"
      :total="openProgressTotal"
      :done="openModalDone"
      @close="closeOpenModal"
    />

    <ChestOpenLogModal
      :visible="logModalVisible"
      :user-token="authData?.token || ''"
      @close="logModalVisible = false"
    />
  </div>
</template>

<script>
import { mapActions, mapMutations, mapState } from 'vuex';
import AppIcon from '@/components/ui/AppIcon.vue';
import BulkActionProgress from '@/components/game/BulkActionProgress.vue';
import ChestOpenLogModal from '@/components/profile/ChestOpenLogModal.vue';
import { apiActions } from '@/api/bitrixClient';

const OTHER_INVENTORY_SLOTS = [
  { id: 'achievement', field: 'achievement_chests', icon: 'chest_achievement', caption: 'Ачивка', label: 'Сундуки за ачивки', openable: true, pool: 'achievement' },
  { id: 'level', field: 'level_chests', icon: 'chest_xp', caption: 'Уровень', label: 'Сундуки за уровень', openable: true, pool: 'level' },
  { id: 'premium_1d', field: 'premium_scrolls_1d', icon: 'premium_scroll_1d', caption: '1д', label: 'Свиток премиума (1 сутки)' },
  { id: 'premium_3d', field: 'premium_scrolls_3d', emoji: '📜', caption: '3д', label: 'Свиток премиума (3 суток)' },
  { id: 'premium_5d', field: 'premium_scrolls_5d', emoji: '📜', caption: '5д', label: 'Свиток премиума (5 суток)' },
  { id: 'pennant_site', field: 'pennant_site', icon: 'pennant_site', caption: 'Сайт', label: 'Вымпел Прогносяус' },
  { id: 'pennant_chm2026', field: 'pennant_chm2026', icon: 'pennant_chm2026', caption: 'ЧМ26', label: 'Вымпел ЧМ-2026' },
];

const LOOT_EMOJI = {
  xp_bank: '🧪',
  cert: '📜',
  pack: '📦',
};

const LOOT_CAPTION = {
  xp_bank: 'XP',
  cert: 'Серт',
  pack: 'Пак',
};

export default {
  name: 'ProfileInventoryBlock',
  components: { AppIcon, BulkActionProgress, ChestOpenLogModal },
  props: {
    game: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      hoveredSlotId: null,
      opening: false,
      openModalVisible: false,
      openModalTitle: 'Открытие сундука',
      openLines: [],
      openModalDone: false,
      openProgressCurrent: 0,
      openProgressTotal: 0,
      logModalVisible: false,
    };
  },
  computed: {
    ...mapState({
      authData: (state) => state.auth.authData,
    }),
    treasure() {
      return this.game?.treasure || {};
    },
    wc26Openable() {
      const direct = Number(this.treasure.wc26_openable_chests ?? 0);
      if (direct > 0) {
        return direct;
      }

      return Number(this.treasure.match_chests ?? 0)
        + Number(this.treasure.wc26_achievement_chests ?? 0)
        + Number(this.treasure.shop_chests ?? 0);
    },
    chestSlots() {
      const slots = [];

      if (this.wc26Openable > 0) {
        slots.push({
          id: 'wc26',
          count: this.wc26Openable,
          icon: 'chest_wc2026',
          caption: 'ЧМ-26',
          label: this.buildWc26Tooltip(),
          openable: true,
          pool: 'wc26',
        });
      }

      OTHER_INVENTORY_SLOTS.forEach((slot) => {
        let count = Number(this.treasure[slot.field] ?? 0);
        if (slot.id === 'premium_1d' && !count) {
          count = Number(this.treasure.premium_scrolls ?? 0);
        }

        if (count > 0) {
          slots.push({ ...slot, count });
        }
      });

      return slots;
    },
    lootSlots() {
      const items = Array.isArray(this.game?.inventory_items) ? this.game.inventory_items : [];

      return items
        .filter((item) => Number(item.count) > 0)
        .map((item) => ({
          id: `loot_${item.code}`,
          count: Number(item.count),
          emoji: LOOT_EMOJI[item.category] || '📦',
          caption: item.type_caption || LOOT_CAPTION[item.category] || 'Лут',
          label: item.label || item.code,
        }));
    },
    displayItems() {
      return [...this.chestSlots, ...this.lootSlots];
    },
    totalItems() {
      return this.displayItems.reduce((sum, item) => sum + item.count, 0);
    },
    totalChests() {
      const fromApi = Number(this.treasure.closed_chests ?? 0);
      if (fromApi > 0) {
        return fromApi;
      }

      return Number(this.treasure.match_chests ?? 0)
        + Number(this.treasure.level_chests ?? 0)
        + Number(this.treasure.achievement_chests ?? 0)
        + Number(this.treasure.wc26_achievement_chests ?? 0)
        + Number(this.treasure.shop_chests ?? 0);
    },
    totalOtherItems() {
      const other = this.totalItems - this.totalChests;
      return other > 0 ? other : 0;
    },
    summaryTitle() {
      if (!this.totalOtherItems) {
        return 'Сундуков в инвентаре';
      }
      return `Сундуков: ${this.totalChests}, прочее (вымпелы, свитки, лут): ${this.totalOtherItems}`;
    },
    hasAnyItems() {
      return this.totalItems > 0;
    },
  },
  methods: {
    ...mapActions({
      refreshGameInfo: 'auth/refreshGameInfo',
    }),
    ...mapMutations({
      setUserInfo: 'auth/setUserInfo',
    }),

    onSlotEnter(item) {
      if (item.openable) {
        this.hoveredSlotId = item.id;
      }
    },

    onSlotLeave() {
      this.hoveredSlotId = null;
    },

    buildWc26Tooltip() {
      const parts = [];
      const match = Number(this.treasure.match_chests ?? 0);
      const wc26Achievement = Number(this.treasure.wc26_achievement_chests ?? 0);
      const achievement = Number(this.treasure.achievement_chests ?? 0);
      const shop = Number(this.treasure.shop_chests ?? 0);

      if (match > 0) {
        parts.push(`матчи: ${match}`);
      }
      if (wc26Achievement > 0) {
        parts.push(`ачивка ЧМ: ${wc26Achievement}`);
      }
      if (achievement > 0) {
        parts.push(`ачивки: ${achievement}`);
      }
      if (shop > 0) {
        parts.push(`лавка: ${shop}`);
      }

      const breakdown = parts.length ? ` (${parts.join(', ')})` : '';

      return `Сундуки ЧМ-26 — один пул лута${breakdown}`;
    },

    closeOpenModal() {
      this.openModalVisible = false;
      this.openLines = [];
      this.openModalDone = false;
      this.openProgressCurrent = 0;
      this.openProgressTotal = 0;
    },

    async openChests(pool, openAll) {
      if (!this.authData?.token || this.opening || !pool) {
        return;
      }

      const slot = this.chestSlots.find((item) => item.pool === pool);
      if (!slot || slot.count <= 0) {
        return;
      }

      const titles = {
        wc26: openAll ? 'Открытие сундуков ЧМ-26' : 'Открытие сундука ЧМ-26',
        level: openAll ? 'Открытие сундуков за уровень' : 'Открытие сундука за уровень',
        achievement: openAll ? 'Открытие сундуков за ачивки' : 'Открытие сундука за ачивки',
      };

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = titles[pool] || 'Открытие сундука';
      this.openLines = [{ text: 'Крутим барабан…', status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = openAll ? Math.min(slot.count, 30) : 1;

      try {
        const data = await apiActions.game.openChests(this.authData.token, pool, openAll);

        if (data?.status === 'ok') {
          this.openLines = Array.isArray(data.lines) ? data.lines : [];
          this.openProgressCurrent = Number(data.summary?.opened_count ?? this.openProgressTotal);
          this.openModalDone = true;

          if (data.game) {
            this.setUserInfo({
              ...this.$store.state.auth.userInfo,
              game_info: data.game,
            });
          } else {
            await this.refreshGameInfo();
          }
        } else {
          this.openLines = [{ text: 'Не удалось открыть сундук', status: 'fail' }];
          this.openModalDone = true;
        }
      } catch (e) {
        this.openLines = [{ text: e.message || 'Ошибка открытия сундука', status: 'fail' }];
        this.openModalDone = true;
      } finally {
        this.opening = false;
      }
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

  .summary_actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .summary_log_btn {
    border: 1px solid fade(@orange, 70%);
    background: fade(@orange, 15%);
    color: @colorText;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
  }

  .summary_value {
    font-size: 15px;
    font-weight: 700;
  }

  .summary_other {
    font-size: 11px;
    font-weight: 600;
    color: @colorBlur;
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

  &.openable:hover {
    border-color: fade(@orange, 70%);
    z-index: 2;
  }
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

.slot_actions {
  position: absolute;
  inset: 0;
  z-index: 4;
  display: flex;
  flex-direction: column;
  align-items: stretch;
  justify-content: center;
  gap: 3px;
  padding: 4px 3px;
  background: fade(@darkbg, 88%);
  border-radius: 2px;
}

.slot_action_btn {
  flex: 1;
  min-height: 0;
  border: 1px solid fade(@orange, 80%);
  border-radius: 2px;
  background: fade(@orange, 25%);
  color: @colorText;
  font-size: 9px;
  font-weight: 700;
  line-height: 1.1;
  padding: 2px 3px;
  cursor: pointer;

  &:disabled {
    opacity: 0.5;
    cursor: default;
  }

  &_all {
    background: fade(@YesWrite, 18%);
    border-color: fade(@YesWrite, 70%);
  }
}

.inventory_empty {
  padding: 12px 8px;
  font-size: 12px;
  line-height: 1.45;
  color: @colorBlur;
  text-align: center;
}
</style>
