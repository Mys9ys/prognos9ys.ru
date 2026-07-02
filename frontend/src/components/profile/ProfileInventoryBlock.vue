<template>
  <div class="inventory_block" v-if="game">
    <div class="inventory_summary">
      <span class="summary_label">В инвентаре</span>
      <div class="summary_actions">
        <button type="button" class="summary_album_btn" @click="openAlbums">
          📔 Альбомы
          <span v-if="albumGluedHint" class="summary_album_badge">{{ albumGluedHint }}</span>
        </button>
        <button type="button" class="summary_log_btn" @click="logModalVisible = true">
          Журнал
        </button>
        <span class="summary_value" v-if="hasAnyItems" :title="summaryTitle">
          {{ totalChests }}<span v-if="totalOtherItems" class="summary_other"> +{{ totalOtherItems }}</span>
        </span>
      </div>
    </div>

    <div class="inventory_tabs">
      <button
        v-for="tab in inventoryTabs"
        :key="tab.id"
        type="button"
        class="inventory_tab"
        :class="{ active: categoryTab === tab.id }"
        @click="categoryTab = tab.id"
      >
        {{ tab.label }}
      </button>
    </div>

    <div class="inventory_grid" v-if="filteredDisplayItems.length">
      <div
        v-for="item in filteredDisplayItems"
        :key="item.id"
        class="inventory_slot"
        :class="{ openable: item.openable && item.count > 0 }"
        :title="item.label"
        @mouseenter="onSlotEnter(item)"
        @mouseleave="onSlotLeave"
      >
        <div class="slot_icon">
          <img v-if="item.imageSrc" :src="item.imageSrc" class="slot_collectible_img" alt="">
          <span v-else-if="item.emoji" class="slot_emoji">{{ item.emoji }}</span>
          <AppIcon v-else :name="item.icon" size="100%" icon-class="slot_app_icon" />
        </div>
        <span class="slot_count">{{ item.count }}</span>
        <span class="slot_caption">{{ item.caption }}</span>

        <div
          v-if="item.openable && item.count > 0 && hoveredSlotId === item.id"
          class="slot_actions"
          :class="{ slot_actions_many: getOpenActionRows(item).length > 1 }"
        >
          <div
            v-for="(row, rowIndex) in getOpenActionRows(item)"
            :key="item.id + '_row_' + rowIndex"
            class="slot_actions_row"
            :class="{ slot_actions_row_split: row.length > 1 }"
          >
            <button
              v-for="(action, actionIndex) in row"
              :key="item.id + '_action_' + rowIndex + '_' + actionIndex"
              type="button"
              class="slot_action_btn"
              :class="{ slot_action_btn_all: action.kind === 'all' }"
              :disabled="opening || item.actionDisabled || action.disabled"
              @click.stop="openInventoryItem(item, action.qty)"
            >
              {{ action.label }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="inventory_empty" v-else>
      Пока пусто — зарабатывайте сундуки в матчах, ачивки, повышайте уровень, покупайте в лавке казны или добывайте сырьё на фарме.
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

    <div
      v-if="xpBankPickerVisible"
      class="xp_bank_picker_overlay"
      @click.self="closeXpBankPicker"
    >
      <div class="xp_bank_picker">
        <div class="xp_bank_picker_title">Куда начислить опыт?</div>
        <p class="xp_bank_picker_hint">{{ xpBankPickerHint }}</p>
        <div class="xp_bank_picker_list">
          <button
            v-for="prof in xpBankPickerProfessions"
            :key="prof.code"
            type="button"
            class="xp_bank_picker_item"
            @click="confirmXpBankProfession(prof.code)"
          >
            <span class="xp_bank_picker_label">{{ prof.label }}</span>
            <span class="xp_bank_picker_meta">ур. {{ prof.level }}</span>
          </button>
        </div>
        <button type="button" class="xp_bank_picker_cancel" @click="closeXpBankPicker">
          Отмена
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapMutations, mapState } from 'vuex';
import AppIcon from '@/components/ui/AppIcon.vue';
import BulkActionProgress from '@/components/game/BulkActionProgress.vue';
import ChestOpenLogModal from '@/components/profile/ChestOpenLogModal.vue';
import { apiActions } from '@/api/bitrixClient';
import { buildInventoryOpenActions, groupInventoryOpenActions, isInventoryOpenAllAction } from '@/utils/inventoryOpenActions';
import { INVENTORY_TABS, resolveInventoryTab } from '@/config/inventoryCatalog';
import { getWc26PennantIconSrc } from '@/config/wc26PennantIcons';
import { getWc26ScarfIconSrc } from '@/config/wc26ScarfIcons';
import { getWc26PackIconSrc } from '@/config/wc26PackIcons';

const OTHER_INVENTORY_SLOTS = [
  { id: 'achievement', field: 'achievement_chests', icon: 'chest_achievement', caption: 'Ачивка', label: 'Сундуки за ачивки', openable: true, pool: 'achievement' },
  { id: 'level', field: 'level_chests', icon: 'chest_xp', caption: 'Уровень', label: 'Сундуки за уровень', openable: true, pool: 'level' },
  { id: 'profession_t1', field: 'profession_chests_tier_1', icon: 'chest_xp', caption: 'Т1', label: 'Сундук проф.: новичок', openable: true, pool: 'profession' },
  { id: 'profession_t2', field: 'profession_chests_tier_2', icon: 'chest_xp', caption: 'Т2', label: 'Сундук проф.: мастер', openable: true, pool: 'profession' },
  { id: 'profession_t3', field: 'profession_chests_tier_3', icon: 'chest_xp', caption: 'Т3', label: 'Сундук проф.: профи', openable: true, pool: 'profession' },
  { id: 'premium_1d', field: 'premium_scrolls_1d', icon: 'premium_scroll_1d', caption: '1д', label: 'Свиток премиума (1 сутки)', openable: true, premiumScrollDays: 1, actionLabel: 'Применить' },
  { id: 'premium_3d', field: 'premium_scrolls_3d', emoji: '📜', caption: '3д', label: 'Свиток премиума (3 суток)', openable: true, premiumScrollDays: 3, actionLabel: 'Применить' },
  { id: 'premium_5d', field: 'premium_scrolls_5d', emoji: '📜', caption: '5д', label: 'Свиток премиума (5 суток)', openable: true, premiumScrollDays: 5, actionLabel: 'Применить' },
  { id: 'pennant_site', field: 'pennant_site', icon: 'pennant_site', caption: 'Сайт', label: 'Вымпел Прогносяус' },
  { id: 'pennant_chm2026', field: 'pennant_chm2026', icon: 'pennant_chm2026', caption: 'ЧМ26', label: 'Вымпел ЧМ-2026' },
];

const LOOT_EMOJI = {
  xp_bank: '🧪',
  cert: '📜',
  pack: '📦',
  album: '📔',
  recipe: '📋',
  equipment: '🥋',
  pennant: '🏴',
  scarf: '🧣',
  material: '📦',
};

const LOOT_CAPTION = {
  xp_bank: 'XP',
  cert: 'Серт',
  pack: 'Пак',
  album: 'Альбом',
  recipe: 'Рецепт',
  equipment: 'Экип',
  pennant: 'Вымпел',
  scarf: 'Шарф',
};

const PROFESSION_PACK_CODES = new Set([
  'pack_recipe_basic',
  'pack_recipe_advanced',
  'pack_equipment_work',
]);

const OPENABLE_PACK_CODES = new Set([
  'pack_pennant_wc26',
  'pack_scarf_wc26',
  ...PROFESSION_PACK_CODES,
]);

const STUB_PACK_CODES = new Set([
  'pack_pennant',
  'pack_scarf',
]);

function isWc26Collectible(item) {
  const code = String(item?.code || '');
  return (item?.category === 'pennant' && /^pennant_wc26_[a-z0-9]+$/.test(code))
    || (item?.category === 'scarf' && /^scarf_wc26_[a-z0-9]+$/.test(code));
}

function teamSlugFromCollectibleCode(code) {
  const match = String(code).match(/^pennant_wc26_([a-z0-9]+)$/)
    || String(code).match(/^scarf_wc26_([a-z0-9]+)$/);
  return match ? match[1] : '';
}

function getWc26CollectibleIconSrc(item) {
  if (item?.category === 'pennant') {
    return getWc26PennantIconSrc(item.code);
  }
  if (item?.category === 'scarf') {
    return getWc26ScarfIconSrc(item.code);
  }
  if (item?.category === 'pack') {
    return getWc26PackIconSrc(item.code);
  }
  return null;
}

function parseXpBankKind(code) {
  const match = String(code || '').match(/^xp_bank_(player|mining|crafting)_\d+$/);
  return match ? match[1] : null;
}

function resolveAlbumForGlue(albums, collection, teamSlug) {
  for (const album of albums || []) {
    const albumCollection = album.collection || '';
    if (albumCollection && albumCollection !== collection) {
      continue;
    }

    const gluedSlugs = Array.isArray(album.glued_slugs) ? album.glued_slugs : [];
    if (!gluedSlugs.includes(teamSlug)) {
      return album;
    }
  }

  return null;
}

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
      categoryTab: 'all',
      xpBankPickerVisible: false,
      xpBankPickerProfessions: [],
      xpBankPickerHint: '',
      xpBankPickerPending: null,
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
        const count = Number(this.treasure[slot.field] ?? 0);

        if (count > 0) {
          slots.push({ ...slot, count });
        }
      });

      return slots;
    },
    lootSlots() {
      const items = Array.isArray(this.game?.inventory_items) ? this.game.inventory_items : [];
      const learnedRecipes = Array.isArray(this.game?.learned_recipes) ? this.game.learned_recipes : [];

      return items
        .filter((item) => Number(item.count) > 0)
        .map((item) => {
          const isProfessionCert = item.code === 'cert_profession' && item.category === 'cert';
          const isRecipeItem = item.category === 'recipe';
          const isCaftanEquipment = item.category === 'equipment' && String(item.code || '').startsWith('caftan_');
          const equippedCaftan = this.game?.equipment?.equipped_caftan || '';
          const recipeLearned = isRecipeItem && learnedRecipes.includes(item.code);
          const isAlbumRecipe = item.code === 'recipe_album' && isRecipeItem;
          const isOpenablePack = item.category === 'pack'
            && item.sealed
            && OPENABLE_PACK_CODES.has(item.code);
          const isStubPack = item.category === 'pack'
            && item.sealed
            && STUB_PACK_CODES.has(item.code);
          const isWc26Glueable = isWc26Collectible(item);
          const teamSlug = isWc26Glueable ? teamSlugFromCollectibleCode(item.code) : '';
          const collectionKey = item.code?.startsWith('pennant_wc26_')
            ? 'pennant_wc26'
            : (item.code?.startsWith('scarf_wc26_') ? 'scarf_wc26' : '');
          const gluedTeams = this.game?.album_meta?.glued_teams?.[collectionKey] || [];
          const teamAlreadyGlued = Boolean(teamSlug && gluedTeams.includes(teamSlug));

          const collectibleIcon = getWc26CollectibleIconSrc(item);

          const isProfessionPack = PROFESSION_PACK_CODES.has(item.code);

          return {
            id: `loot_${item.code}${item.is_premium ? '_p' : ''}${item.sealed ? '_s' : ''}`,
            count: Number(item.count),
            emoji: collectibleIcon ? null : (isProfessionPack ? '⚙️' : (item.emoji || LOOT_EMOJI[item.category] || '📦')),
            imageSrc: collectibleIcon,
            caption: isProfessionPack ? 'Рецепты' : (item.type_caption || LOOT_CAPTION[item.category] || 'Лут'),
            label: item.label || item.code,
            code: item.code,
            category: item.category,
            openable: item.category === 'xp_bank' || isProfessionCert || isOpenablePack || isStubPack
              || isCaftanEquipment
              || (isRecipeItem && !recipeLearned) || isWc26Glueable,
            xpBankCode: item.category === 'xp_bank' ? item.code : null,
            packCode: isOpenablePack ? item.code : null,
            packStub: isStubPack,
            professionCert: isProfessionCert,
            caftanEquipment: isCaftanEquipment,
            caftanEquipped: isCaftanEquipment && equippedCaftan === item.code,
            recipeItem: isRecipeItem,
            albumRecipe: isAlbumRecipe,
            glueableCollectible: isWc26Glueable,
            teamAlreadyGlued,
            recipeLearned,
            recipeLearnable: isRecipeItem && !recipeLearned,
            actionDisabled: (isRecipeItem && recipeLearned) || (isWc26Glueable && teamAlreadyGlued),
            actionLabel: isWc26Glueable
              ? (teamAlreadyGlued ? 'В альбоме' : 'В альбом')
              : (isStubPack
                ? 'Скоро'
                : (isCaftanEquipment
                  ? 'Надеть'
                  : (isRecipeItem
                    ? (recipeLearned ? 'Изучено' : 'Изучить')
                    : (isProfessionCert ? 'Активировать' : null)))),
          };
        });
    },
    displayItems() {
      return [...this.chestSlots, ...this.lootSlots];
    },
    inventoryTabs() {
      return INVENTORY_TABS;
    },
    filteredDisplayItems() {
      return this.displayItems.filter((item) => resolveInventoryTab(item, this.categoryTab));
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
        + Number(this.treasure.profession_chests ?? 0)
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
    albumGluedHint() {
      const teams = this.game?.album_meta?.glued_teams || {};
      const pennant = Array.isArray(teams.pennant_wc26) ? teams.pennant_wc26.length : 0;
      const scarf = Array.isArray(teams.scarf_wc26) ? teams.scarf_wc26.length : 0;
      const albums = Array.isArray(this.game?.album_meta?.albums) ? this.game.album_meta.albums.length : 0;

      if (pennant <= 0 && scarf <= 0 && albums <= 0) {
        return '';
      }
      if (pennant > 0 && scarf <= 0) {
        return pennant >= 48 ? '48/48 🏆' : `${pennant}/48`;
      }
      if (scarf > 0 && pennant <= 0) {
        return scarf >= 48 ? '48/48 🏆' : `шарф ${scarf}/48`;
      }
      return `вымп. ${pennant} · шарф ${scarf}`;
    },
  },
  methods: {
    ...mapActions({
      refreshGameInfo: 'auth/refreshGameInfo',
    }),
    ...mapMutations({
      setUserInfo: 'auth/setUserInfo',
      patchGameInfo: 'auth/patchGameInfo',
    }),

    async applyGameResponse(data) {
      if (data?.game) {
        this.patchGameInfo(data.game);
        return;
      }

      await this.refreshGameInfo();
    },

    openAlbums() {
      this.$router.push('/collection');
    },

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
      const profession = Number(this.treasure.profession_chests ?? 0);
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
      if (profession > 0) {
        parts.push(`проф. сундуки: ${profession}`);
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

    getOpenActions(item) {
      if (!item?.openable || item.count <= 0) {
        return [];
      }

      if (item.recipeLearnable) {
        return [{ qty: 1, label: 'Изучить', kind: 'primary' }];
      }

      if (item.professionCert) {
        return [{ qty: 1, label: 'Активировать', kind: 'primary' }];
      }

      if (item.caftanEquipment) {
        return [{ qty: 1, label: 'Надеть', kind: 'primary' }];
      }

      if (item.glueableCollectible) {
        return [{
          qty: 1,
          label: item.teamAlreadyGlued ? 'В альбоме' : 'В альбом',
          kind: 'primary',
          disabled: item.teamAlreadyGlued,
        }];
      }

      if (item.packStub) {
        return [{ qty: 1, label: 'Скоро', kind: 'primary' }];
      }

      if (item.actionDisabled) {
        return [];
      }

      return buildInventoryOpenActions(item.count, item.actionLabel || 'Открыть');
    },

    getOpenActionRows(item) {
      return groupInventoryOpenActions(this.getOpenActions(item));
    },

    resolveOpenQty(item, qty) {
      return Math.max(1, Math.min(Number(qty) || 1, Number(item?.count) || 1, 30));
    },

    openInventoryItem(item, qty = 1) {
      if (item.professionCert) {
        this.activateProfessionCertificate(item);
        return;
      }

      if (item.caftanEquipment) {
        this.equipCaftan(item);
        return;
      }

      if (item.recipeLearnable) {
        this.learnRecipe(item);
        return;
      }

      if (item.albumRecipe && item.recipeLearned) {
        return;
      }

      if (item.glueableCollectible) {
        this.glueCollectibleToAlbum(item);
        return;
      }

      if (item.packStub) {
        this.showPackStub(item);
        return;
      }

      const openQty = this.resolveOpenQty(item, qty);

      if (item.packCode) {
        this.openLootPack(item.packCode, openQty, item);
        return;
      }

      if (item.premiumScrollDays) {
        this.activatePremiumScroll(item, openQty);
        return;
      }

      if (item.xpBankCode) {
        this.promptXpBankOpen(item.xpBankCode, openQty, item);
        return;
      }

      if (item.pool) {
        this.openChests(item.pool, openQty, item);
      }
    },

    async openChests(pool, qty, slot) {
      if (!this.authData?.token || this.opening || !pool) {
        return;
      }

      const resolvedSlot = slot || this.chestSlots.find((item) => item.pool === pool);
      if (!resolvedSlot || resolvedSlot.count <= 0) {
        return;
      }

      const openQty = this.resolveOpenQty(resolvedSlot, qty);
      const titles = {
        wc26: openQty > 1 ? `Открытие сундуков ЧМ-26 (${openQty})` : 'Открытие сундука ЧМ-26',
        level: openQty > 1 ? `Открытие сундуков за уровень (${openQty})` : 'Открытие сундука за уровень',
        achievement: openQty > 1 ? `Открытие сундуков за ачивки (${openQty})` : 'Открытие сундука за ачивки',
        profession: openQty > 1 ? `Открытие проф. сундуков (${openQty})` : 'Открытие проф. сундука',
      };

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = titles[pool] || 'Открытие сундука';
      this.openLines = [{ text: 'Крутим барабан…', status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = openQty;

      try {
        const data = await apiActions.game.openChests(this.authData.token, pool, openQty);

        if (data?.status === 'ok') {
          this.openLines = Array.isArray(data.lines) ? data.lines : [];
          this.openProgressCurrent = Number(data.summary?.opened_count ?? this.openProgressTotal);
          this.openModalDone = true;

          if (data.game) {
            this.patchGameInfo(data.game);
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

    async promptXpBankOpen(code, qty, slot) {
      if (!this.authData?.token || this.opening || !code || !slot || slot.count <= 0) {
        return;
      }

      const openQty = this.resolveOpenQty(slot, qty);
      const kind = parseXpBankKind(code);
      if (!kind || kind === 'player') {
        await this.openXpBank(code, openQty, slot);
        return;
      }

      const professionType = kind === 'mining' ? 'gather' : 'process';
      const kindLabel = kind === 'mining' ? 'добычи' : 'крафта';

      this.hoveredSlotId = null;

      try {
        const data = await apiActions.game.getFarmState(this.authData.token);
        const professions = (data?.farm?.professions || [])
          .filter((prof) => prof.type === professionType);

        if (!professions.length) {
          this.openModalVisible = true;
          this.openModalDone = false;
          this.openModalTitle = 'Банка опыта';
          this.openLines = [{
            text: kind === 'mining'
              ? 'Сначала изучите профессию добычи на вкладке «Работа»'
              : 'Сначала изучите профессию крафта на вкладке «Работа»',
            status: 'fail',
          }];
          this.openProgressCurrent = 0;
          this.openProgressTotal = 0;
          return;
        }

        if (professions.length === 1) {
          await this.openXpBank(code, openQty, slot, professions[0].code);
          return;
        }

        this.xpBankPickerPending = { code, qty: openQty, slot };
        this.xpBankPickerProfessions = professions;
        this.xpBankPickerHint = `Выберите профессию ${kindLabel} — опыт пойдёт в неё`;
        this.xpBankPickerVisible = true;
      } catch (e) {
        this.openModalVisible = true;
        this.openModalDone = false;
        this.openModalTitle = 'Банка опыта';
        this.openLines = [{ text: e.message || 'Не удалось загрузить профессии', status: 'fail' }];
        this.openProgressCurrent = 0;
        this.openProgressTotal = 0;
      }
    },

    closeXpBankPicker() {
      this.xpBankPickerVisible = false;
      this.xpBankPickerProfessions = [];
      this.xpBankPickerHint = '';
      this.xpBankPickerPending = null;
    },

    async confirmXpBankProfession(professionCode) {
      const pending = this.xpBankPickerPending;
      if (!pending || !professionCode) {
        return;
      }

      this.closeXpBankPicker();
      await this.openXpBank(pending.code, pending.qty, pending.slot, professionCode);
    },

    async openXpBank(code, qty, slot, professionCode = '') {
      if (!this.authData?.token || this.opening || !code || !slot || slot.count <= 0) {
        return;
      }

      const openQty = this.resolveOpenQty(slot, qty);

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = openQty > 1 ? `Открытие банок опыта (${openQty})` : 'Открытие банки опыта';
      this.openLines = [{ text: 'Начисляем опыт…', status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = openQty;

      try {
        const data = await apiActions.game.openXpBanks(
          this.authData.token,
          code,
          openQty,
          professionCode,
        );

        if (data?.status === 'ok') {
          this.openLines = Array.isArray(data.lines) ? data.lines : [];
          this.openProgressCurrent = Number(data.opened_count ?? this.openProgressTotal);
          this.openModalDone = true;

          if (data.game) {
            this.patchGameInfo(data.game);
          } else {
            await this.refreshGameInfo();
          }
        } else {
          this.openLines = [{ text: 'Не удалось открыть банку опыта', status: 'fail' }];
          this.openModalDone = true;
        }
      } catch (e) {
        this.openLines = [{ text: e.message || 'Ошибка открытия банки опыта', status: 'fail' }];
        this.openModalDone = true;
      } finally {
        this.opening = false;
      }
    },

    async activatePremiumScroll(slot, qty) {
      if (!this.authData?.token || this.opening || !slot?.premiumScrollDays || slot.count <= 0) {
        return;
      }

      const openQty = this.resolveOpenQty(slot, qty);

      if (isInventoryOpenAllAction(slot.count, openQty)) {
        const confirmed = window.confirm(
          `Применить все свитки «${slot.label}» (${openQty} шт.)?\n\n`
          + 'Премиум — ценный ресурс. Продолжить?',
        );
        if (!confirmed) {
          return;
        }
      }

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = openQty > 1
        ? `Активация свитков премиума (${openQty})`
        : 'Активация свитка премиума';
      this.openLines = [{ text: 'Продлеваем премиум…', status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = openQty;

      try {
        const data = await apiActions.game.activatePremiumScroll(
          this.authData.token,
          slot.premiumScrollDays,
          openQty,
        );

        if (data?.status === 'ok') {
          this.openLines = (data.lines || []).map((text) => ({ text, status: 'ok' }));
          this.openProgressCurrent = openQty;
          this.openModalDone = true;

          if (data.game) {
            this.patchGameInfo({
              ...data.game,
              premium: data.premium || data.game.premium,
            });
          } else {
            await this.refreshGameInfo();
          }
        } else {
          this.openLines = [{ text: 'Не удалось активировать свиток', status: 'fail' }];
          this.openModalDone = true;
        }
      } catch (e) {
        this.openLines = [{ text: e.message || 'Ошибка активации премиума', status: 'fail' }];
        this.openModalDone = true;
      } finally {
        this.opening = false;
      }
    },

    async activateProfessionCertificate(slot) {
      if (!this.authData?.token || this.opening || !slot || slot.count <= 0) {
        return;
      }

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = 'Активация сертификата';
      this.openLines = [{ text: 'Открываем дополнительный слот профессии…', status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = 1;

      try {
        const data = await apiActions.game.activateProfessionCertificate(this.authData.token);

        if (data?.status === 'ok') {
          this.openLines = Array.isArray(data.lines) ? data.lines : [];
          this.openProgressCurrent = 1;
          this.openModalDone = true;

          if (data.game) {
            this.patchGameInfo(data.game);
          } else {
            await this.refreshGameInfo();
          }

          window.dispatchEvent(new CustomEvent('prognos9ys:farm-refresh'));
        } else {
          this.openLines = [{ text: 'Не удалось активировать сертификат', status: 'fail' }];
          this.openModalDone = true;
        }
      } catch (e) {
        this.openLines = [{ text: e.message || 'Ошибка активации сертификата', status: 'fail' }];
        this.openModalDone = true;
      } finally {
        this.opening = false;
      }
    },

    async equipCaftan(slot) {
      if (!this.authData?.token || this.opening || !slot?.code || slot.count <= 0) {
        return;
      }

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = 'Экипировка';
      this.openLines = [{ text: `Надеваем: ${slot.label}…`, status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = 1;

      try {
        const data = await apiActions.game.equipCaftan(this.authData.token, slot.code);

        if (data?.status === 'ok') {
          this.openLines = Array.isArray(data.lines) ? data.lines : [];
          this.openProgressCurrent = 1;
          this.openModalDone = true;

          if (data.game) {
            this.patchGameInfo(data.game);
          } else {
            await this.refreshGameInfo();
          }

          window.dispatchEvent(new CustomEvent('prognos9ys:farm-refresh'));
        } else {
          this.openLines = [{ text: 'Не удалось надеть кафтан', status: 'fail' }];
          this.openModalDone = true;
        }
      } catch (e) {
        this.openLines = [{ text: e.message || 'Ошибка экипировки', status: 'fail' }];
        this.openModalDone = true;
      } finally {
        this.opening = false;
      }
    },

    async learnRecipe(slot) {
      if (!this.authData?.token || this.opening || !slot || slot.count <= 0 || slot.recipeLearned) {
        return;
      }

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = 'Изучение рецепта';
      this.openLines = [{ text: `Запоминаем: ${slot.label}…`, status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = 1;

      try {
        const data = await apiActions.game.learnAlbumRecipe(this.authData.token, slot.code);

        if (data?.status === 'ok') {
          this.openLines = Array.isArray(data.lines) ? data.lines : [];
          this.openProgressCurrent = 1;
          this.openModalDone = true;

          if (data.game) {
            this.patchGameInfo(data.game);
          } else {
            await this.refreshGameInfo();
          }
        } else {
          this.openLines = [{ text: 'Не удалось изучить рецепт', status: 'fail' }];
          this.openModalDone = true;
        }
      } catch (e) {
        this.openLines = [{ text: e.message || 'Ошибка изучения рецепта', status: 'fail' }];
        this.openModalDone = true;
      } finally {
        this.opening = false;
      }
    },

    async learnAlbumRecipe(slot) {
      await this.learnRecipe(slot);
    },

    async glueCollectibleToAlbum(slot) {
      if (!this.authData?.token || this.opening || !slot?.code || slot.count <= 0 || slot.teamAlreadyGlued) {
        return;
      }

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = 'Вклейка в альбом';
      this.openLines = [{ text: 'Ищем альбом…', status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = 1;

      const itemCode = slot.code;
      const collection = itemCode.startsWith('pennant_wc26_') ? 'pennant_wc26' : 'scarf_wc26';
      const teamSlug = teamSlugFromCollectibleCode(itemCode);

      try {
        const albums = this.game?.album_meta?.albums || [];

        if (!albums.length) {
          this.openLines = [{
            text: 'Сначала активируйте альбом на вкладке «Коллекция»',
            status: 'fail',
          }];
          this.openModalDone = true;
          this.openProgressCurrent = 1;
          return;
        }

        const targetAlbum = resolveAlbumForGlue(albums, collection, teamSlug);

        if (!targetAlbum) {
          this.openLines = [{
            text: 'Нет свободного слота для этой сборной в подходящем альбоме',
            status: 'fail',
          }];
          this.openModalDone = true;
          this.openProgressCurrent = 1;
          return;
        }

        const data = await apiActions.game.glueAlbumItem(
          this.authData.token,
          targetAlbum.id,
          itemCode,
        );

        if (data?.status === 'ok') {
          this.openLines = Array.isArray(data.lines) ? data.lines : [{ text: 'Вклеено в альбом', status: 'ok' }];
          this.openProgressCurrent = 1;
          this.openModalDone = true;
          await this.applyGameResponse(data);
          window.dispatchEvent(new CustomEvent('prognos9ys:album-refresh'));
        } else {
          this.openLines = [{ text: data?.message || 'Не удалось вклеить', status: 'fail' }];
          this.openModalDone = true;
        }
      } catch (e) {
        this.openLines = [{ text: e.message || 'Ошибка вклейки', status: 'fail' }];
        this.openModalDone = true;
      } finally {
        this.opening = false;
      }
    },

    showPackStub(slot) {
      if (!slot || slot.count <= 0) {
        return;
      }

      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = true;
      this.openModalTitle = 'Распаковка паков';
      this.openLines = [{
        text: 'Распаковка паков этой коллекции пока в разработке. Сейчас доступны только паки ЧМ-26.',
        status: 'pending',
      }];
      this.openProgressCurrent = 1;
      this.openProgressTotal = 1;
    },

    async openLootPack(code, qty, slot) {
      if (!this.authData?.token || this.opening || !code || !slot || slot.count <= 0) {
        return;
      }

      const openQty = this.resolveOpenQty(slot, qty);

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = openQty > 1 ? `Открытие паков (${openQty})` : 'Открытие пака';
      this.openLines = [{ text: 'Распаковываем…', status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = openQty;

      try {
        const data = await apiActions.game.openLootPacks(this.authData.token, code, openQty);

        if (data?.status === 'ok') {
          this.openLines = Array.isArray(data.lines) ? data.lines : [];
          this.openProgressCurrent = Number(data.opened_count ?? this.openProgressTotal);
          this.openModalDone = true;

          if (data.game) {
            this.patchGameInfo(data.game);
          } else {
            await this.refreshGameInfo();
          }
        } else {
          this.openLines = [{ text: 'Не удалось открыть пак', status: 'fail' }];
          this.openModalDone = true;
        }
      } catch (e) {
        this.openLines = [{ text: e.message || 'Ошибка открытия пака', status: 'fail' }];
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

.inventory_tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 8px;
}

.inventory_tab {
  background: @darkbg;
  color: @colorText;
  border: 1px solid transparent;
  border-radius: 4px;
  padding: 5px 8px;
  font-size: 11px;
  cursor: pointer;

  &.active {
    background: @orange;
    color: #fff;
  }
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

  .summary_album_btn {
    border: 1px solid fade(#6eb5ff, 70%);
    background: fade(#6eb5ff, 18%);
    color: @colorText;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  .summary_album_badge {
    font-size: 10px;
    font-weight: 800;
    color: lighten(@orange, 8%);
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

.slot_collectible_img {
  width: 92%;
  height: 92%;
  object-fit: contain;
  object-position: center;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.35));
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

.slot_actions_many {
  gap: 4px;
  padding: 4px 3px;

  .slot_action_btn {
    font-size: 9px;
    padding: 4px 3px;
  }
}

.slot_actions_row {
  display: flex;
  flex: 1;
  min-height: 0;
  align-items: stretch;
}

.slot_actions_row_split {
  gap: 3px;

  .slot_action_btn {
    flex: 1;
  }
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

.xp_bank_picker_overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: fade(#000, 55%);
}

.xp_bank_picker {
  width: 100%;
  max-width: 320px;
  padding: 14px 12px 12px;
  border-radius: 6px;
  background: @darkbg;
  border: 1px solid fade(@colorBlur, 50%);
  box-shadow: 0 8px 24px fade(#000, 45%);
}

.xp_bank_picker_title {
  font-size: 14px;
  font-weight: 700;
  color: @colorText;
  margin-bottom: 6px;
}

.xp_bank_picker_hint {
  margin: 0 0 10px;
  font-size: 11px;
  line-height: 1.35;
  color: fade(@colorText, 75%);
}

.xp_bank_picker_list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 10px;
}

.xp_bank_picker_item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
  padding: 10px 12px;
  border: 1px solid fade(@orange, 70%);
  border-radius: 4px;
  background: fade(@orange, 18%);
  color: @colorText;
  font-size: 12px;
  cursor: pointer;
  text-align: left;

  &:hover {
    background: fade(@orange, 28%);
  }
}

.xp_bank_picker_label {
  font-weight: 700;
}

.xp_bank_picker_meta {
  font-size: 11px;
  color: fade(@colorText, 70%);
  white-space: nowrap;
}

.xp_bank_picker_cancel {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid fade(@colorBlur, 45%);
  border-radius: 4px;
  background: transparent;
  color: fade(@colorText, 80%);
  font-size: 12px;
  cursor: pointer;
}

.inventory_empty {
  padding: 12px 8px;
  font-size: 12px;
  line-height: 1.45;
  color: @colorBlur;
  text-align: center;
}
</style>
