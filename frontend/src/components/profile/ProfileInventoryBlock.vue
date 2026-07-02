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
        >
          <button type="button" class="slot_action_btn" :disabled="opening || item.actionDisabled" @click.stop="openInventoryItem(item, false)">
            {{ item.actionLabel || 'Открыть' }}
          </button>
          <button
            v-if="item.count > 1 && item.allowOpenAll !== false"
            type="button"
            class="slot_action_btn slot_action_btn_all"
            :disabled="opening"
            @click.stop="openInventoryItem(item, true)"
          >
            Все ({{ Math.min(item.count, 30) }})
          </button>
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
import { INVENTORY_TABS, resolveInventoryTab } from '@/config/inventoryCatalog';
import { getWc26PennantIconSrc } from '@/config/wc26PennantIcons';
import { getWc26ScarfIconSrc } from '@/config/wc26ScarfIcons';
import { getWc26PackIconSrc } from '@/config/wc26PackIcons';

const OTHER_INVENTORY_SLOTS = [
  { id: 'achievement', field: 'achievement_chests', icon: 'chest_achievement', caption: 'Ачивка', label: 'Сундуки за ачивки', openable: true, pool: 'achievement' },
  { id: 'level', field: 'level_chests', icon: 'chest_xp', caption: 'Уровень', label: 'Сундуки за уровень', openable: true, pool: 'level' },
  { id: 'profession', field: 'profession_chests', icon: 'chest_achievement', caption: 'Проф', label: 'Сундуки профессий (тираж 1/2/3)', openable: true, pool: 'profession' },
  { id: 'premium_1d', field: 'premium_scrolls_1d', icon: 'premium_scroll_1d', caption: '1д', label: 'Свиток премиума (1 сутки)', openable: true, premiumScrollDays: 1, actionLabel: 'Активировать' },
  { id: 'premium_3d', field: 'premium_scrolls_3d', emoji: '📜', caption: '3д', label: 'Свиток премиума (3 суток)', openable: true, premiumScrollDays: 3, actionLabel: 'Активировать' },
  { id: 'premium_5d', field: 'premium_scrolls_5d', emoji: '📜', caption: '5д', label: 'Свиток премиума (5 суток)', openable: true, premiumScrollDays: 5, actionLabel: 'Активировать' },
  { id: 'pennant_site', field: 'pennant_site', icon: 'pennant_site', caption: 'Сайт', label: 'Вымпел Прогносяус' },
  { id: 'pennant_chm2026', field: 'pennant_chm2026', icon: 'pennant_chm2026', caption: 'ЧМ26', label: 'Вымпел ЧМ-2026' },
];

const LOOT_EMOJI = {
  xp_bank: '🧪',
  cert: '📜',
  pack: '📦',
  album: '📔',
  recipe: '📋',
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
  pennant: 'Вымпел',
  scarf: 'Шарф',
};

const OPENABLE_PACK_CODES = new Set([
  'pack_pennant_wc26',
  'pack_scarf_wc26',
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
          const isAlbumRecipe = item.code === 'recipe_album' && item.category === 'recipe';
          const recipeLearned = isAlbumRecipe && learnedRecipes.includes('recipe_album');
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

          return {
            id: `loot_${item.code}${item.is_premium ? '_p' : ''}${item.sealed ? '_s' : ''}`,
            count: Number(item.count),
            emoji: collectibleIcon ? null : (item.emoji || LOOT_EMOJI[item.category] || '📦'),
            imageSrc: collectibleIcon,
            caption: item.type_caption || LOOT_CAPTION[item.category] || 'Лут',
            label: item.label || item.code,
            code: item.code,
            category: item.category,
            openable: item.category === 'xp_bank' || isProfessionCert || isOpenablePack || isStubPack || isAlbumRecipe || isWc26Glueable,
            xpBankCode: item.category === 'xp_bank' ? item.code : null,
            packCode: isOpenablePack ? item.code : null,
            packStub: isStubPack,
            professionCert: isProfessionCert,
            albumRecipe: isAlbumRecipe,
            glueableCollectible: isWc26Glueable,
            teamAlreadyGlued,
            recipeLearned,
            actionDisabled: (isAlbumRecipe && recipeLearned) || (isWc26Glueable && teamAlreadyGlued),
            actionLabel: isWc26Glueable
              ? (teamAlreadyGlued ? 'В альбоме' : 'В альбом')
              : (isStubPack
                ? 'Скоро'
                : (isAlbumRecipe
                  ? (recipeLearned ? 'Изучено' : 'Изучить')
                  : (isProfessionCert ? 'Активировать' : null))),
            allowOpenAll: !isProfessionCert && !isAlbumRecipe && !isStubPack && !isWc26Glueable,
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

    openInventoryItem(item, openAll) {
      if (item.professionCert) {
        this.activateProfessionCertificate(item);
        return;
      }

      if (item.albumRecipe) {
        if (!item.recipeLearned) {
          this.learnAlbumRecipe(item);
        }
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

      if (item.packCode) {
        this.openLootPack(item.packCode, openAll, item);
        return;
      }

      if (item.premiumScrollDays) {
        this.activatePremiumScroll(item, openAll);
        return;
      }

      if (item.xpBankCode) {
        this.promptXpBankOpen(item.xpBankCode, openAll, item);
        return;
      }

      if (item.pool) {
        this.openChests(item.pool, openAll);
      }
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

    async promptXpBankOpen(code, openAll, slot) {
      if (!this.authData?.token || this.opening || !code || !slot || slot.count <= 0) {
        return;
      }

      const kind = parseXpBankKind(code);
      if (!kind || kind === 'player') {
        await this.openXpBank(code, openAll, slot);
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
          await this.openXpBank(code, openAll, slot, professions[0].code);
          return;
        }

        this.xpBankPickerPending = { code, openAll, slot };
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
      await this.openXpBank(pending.code, pending.openAll, pending.slot, professionCode);
    },

    async openXpBank(code, openAll, slot, professionCode = '') {
      if (!this.authData?.token || this.opening || !code || !slot || slot.count <= 0) {
        return;
      }

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = openAll ? 'Открытие банок опыта' : 'Открытие банки опыта';
      this.openLines = [{ text: 'Начисляем опыт…', status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = openAll ? Math.min(slot.count, 30) : 1;

      try {
        const data = await apiActions.game.openXpBanks(
          this.authData.token,
          code,
          openAll,
          professionCode,
        );

        if (data?.status === 'ok') {
          this.openLines = Array.isArray(data.lines) ? data.lines : [];
          this.openProgressCurrent = Number(data.opened_count ?? this.openProgressTotal);
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

    async activatePremiumScroll(slot, activateAll) {
      if (!this.authData?.token || this.opening || !slot?.premiumScrollDays || slot.count <= 0) {
        return;
      }

      if (activateAll) {
        const confirmed = window.confirm(
          `Активировать все свитки «${slot.label}» (${Math.min(slot.count, 30)} шт.)?\n\n`
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
      this.openModalTitle = activateAll ? 'Активация свитков премиума' : 'Активация свитка премиума';
      this.openLines = [{ text: 'Продлеваем премиум…', status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = 1;

      try {
        const data = await apiActions.game.activatePremiumScroll(
          this.authData.token,
          slot.premiumScrollDays,
          activateAll,
        );

        if (data?.status === 'ok') {
          this.openLines = (data.lines || []).map((text) => ({ text, status: 'ok' }));
          this.openProgressCurrent = 1;
          this.openModalDone = true;

          if (data.game) {
            this.setUserInfo({
              ...this.$store.state.auth.userInfo,
              game_info: {
                ...data.game,
                premium: data.premium || data.game.premium,
              },
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
            this.setUserInfo({
              ...this.$store.state.auth.userInfo,
              game_info: data.game,
            });
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

    async learnAlbumRecipe(slot) {
      if (!this.authData?.token || this.opening || !slot || slot.count <= 0 || slot.recipeLearned) {
        return;
      }

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = 'Изучение рецепта';
      this.openLines = [{ text: 'Запоминаем рецепт альбома…', status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = 1;

      try {
        const data = await apiActions.game.learnAlbumRecipe(this.authData.token);

        if (data?.status === 'ok') {
          this.openLines = Array.isArray(data.lines) ? data.lines : [];
          this.openProgressCurrent = 1;
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

    async openLootPack(code, openAll, slot) {
      if (!this.authData?.token || this.opening || !code || !slot || slot.count <= 0) {
        return;
      }

      this.opening = true;
      this.hoveredSlotId = null;
      this.openModalVisible = true;
      this.openModalDone = false;
      this.openModalTitle = openAll ? 'Открытие паков' : 'Открытие пака';
      this.openLines = [{ text: 'Распаковываем…', status: 'pending' }];
      this.openProgressCurrent = 0;
      this.openProgressTotal = openAll ? Math.min(slot.count, 30) : 1;

      try {
        const data = await apiActions.game.openLootPacks(this.authData.token, code, openAll);

        if (data?.status === 'ok') {
          this.openLines = Array.isArray(data.lines) ? data.lines : [];
          this.openProgressCurrent = Number(data.opened_count ?? this.openProgressTotal);
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
