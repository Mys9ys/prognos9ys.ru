<template>
  <div class="album_block">
    <div v-if="loading" class="hint">Загрузка коллекции…</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <template v-else-if="state">
      <div class="craft_card" :class="{ learned: state.recipe?.learned }">
        <div class="craft_title">
          Рецепт альбома
          <span v-if="state.recipe?.learned" class="learned_badge">изучен</span>
        </div>
        <p class="hint" v-if="state.craft?.needs_recipe">
          Сначала изучите рецепт (выпадает с шансом 5% при распаковке паков вымпелов/шарфов).
          <span v-if="state.recipe?.in_inventory > 0"> В инвентаре: {{ state.recipe.in_inventory }} шт.</span>
        </p>
        <p class="hint" v-else-if="state.recipe?.learned">
          Рецепт изучен — скрафтить альбомы можно на вкладке «Фарм → Запуски», выбрав профессию столяр или ткач.
        </p>
        <p class="hint">
          {{ state.craft?.plank_need }} доски + {{ state.craft?.cloth_need }} ткани →
          {{ state.craft?.output_count }} альбома
        </p>
      </div>

      <div class="activate_row" v-if="state.universal_albums > 0">
        <span class="hint">В инвентаре альбомов: {{ state.universal_albums }}</span>
        <button
          type="button"
          class="action_btn"
          :disabled="busy || !state.activate?.allowed"
          :title="state.activate?.reason || ''"
          @click="activateAlbum"
        >
          Активировать альбом
        </button>
      </div>
      <p class="hint warn" v-if="state.universal_albums > 0 && state.activate && !state.activate.allowed">
        {{ state.activate.reason }}
      </p>

      <div class="mega_row" v-if="state.mega">
        <div v-for="(row, key) in state.mega" :key="key" class="mega_card">
          <div class="mega_head">
            <span class="mega_label">{{ megaLabels[key] || key }}</span>
            <span class="mega_value">{{ row.glued }} / {{ megaMax(row) }}</span>
          </div>
          <div class="mega_track">
            <div class="mega_fill" :style="{ width: megaPercent(row) + '%' }" />
          </div>
          <div class="mega_tiers">
            <span
              v-for="tier in row.thresholds"
              :key="tier"
              class="mega_tier"
              :class="{ done: row.glued >= tier, next: row.next_threshold === tier }"
            >{{ tier }}</span>
          </div>
          <div class="mega_rewards" v-if="row.tiers?.length">
            <div v-for="tierRow in row.tiers" :key="row.achievement_code + '_' + tierRow.threshold" class="mega_reward_row">
              <span class="mega_reward_label">{{ tierRow.threshold }} шт.:</span>
              <span class="mega_reward_text">{{ formatMegaReward(tierRow.reward) }}</span>
              <button
                v-if="tierRow.claimable"
                type="button"
                class="mega_claim_btn"
                :disabled="busy"
                @click="claimMegaTier(row.achievement_code)"
              >Забрать</button>
              <span v-else-if="tierRow.claimed" class="mega_claimed">✓</span>
            </div>
          </div>
          <div v-if="key !== 'pennant_achievement' && buyTiersForRow(row, key).length" class="mega_buy_row">
            <button
              v-for="tier in buyTiersForRow(row, key)"
              :key="key + '-buy-' + tier"
              type="button"
              class="mega_buy_btn"
              :disabled="busy"
              @click="buyCollectionToTier(key, tier)"
            >
              Докупить до {{ tier }}
            </button>
          </div>
          <p v-if="key !== 'pennant_achievement' && buyTiersForRow(row, key).length" class="hint mega_buy_hint">
            Сначала вклейка из инвентаря, затем покупка на бирже (пока хватает 🪙).
          </p>
        </div>
      </div>

      <div v-if="!state.albums?.length" class="empty_hint">
        Активируйте альбом из инвентаря или скрафтите новый.
        Можно иметь до трёх альбомов: вымпелы и шарфы ЧМ-26 и альбом достижений; первая вклейка определяет тип.
      </div>

      <template v-else>
        <div class="album_tabs" v-if="state.albums.length > 1">
          <button
            v-for="album in state.albums"
            :key="album.id"
            type="button"
            class="album_tab"
            :class="{ active: selectedAlbumId === album.id }"
            @click="selectAlbum(album.id)"
          >
            #{{ album.id }} ({{ album.glued_count }}/{{ album.slot_count }})
          </button>
        </div>

        <div v-if="selectedAlbum" class="album_panel">
          <div class="album_header">
            <strong>{{ selectedAlbum.collection_label }}</strong>
            <span class="hint">{{ selectedAlbum.glued_count }} / {{ selectedAlbum.slot_count }}</span>
          </div>

          <div class="album_actions">
            <button
              type="button"
              class="action_btn"
              :disabled="busy"
              @click="glueAllEligible"
            >
              Добавить оригиналы
            </button>
          </div>

          <div class="slots_grid">
            <div
              v-for="slot in albumSlotsGrid"
              :key="slot.team_slug"
              class="slot_cell"
              :class="{
                glued: slot.glued,
                selected: selectedSlotSlug === slot.team_slug,
                clickable: !slot.glued,
              }"
              :title="slot.glued ? slot.item_label : slot.team_label"
              @click="onSlotClick(slot)"
            >
              <button
                v-if="slotPreviewSrc(slot)"
                type="button"
                class="slot_zoom_btn"
                title="Увеличить"
                @click.stop="openSlotPreview(slot)"
              >⌕</button>
              <img
                v-if="slotPennantSrc(slot)"
                :src="slotPennantSrc(slot)"
                class="slot_collectible_img"
                :class="{ slot_collectible_img_dim: !slot.glued }"
                :title="slot.team_label"
                alt=""
              >
              <span
                v-if="slotPennantSrc(slot) || isAchievementAlbum"
                class="slot_caption"
              >{{ slotTeamCaption(slot) }}</span>
              <span v-else class="slot_flag">{{ slot.team_label }}</span>
            </div>
          </div>

          <div class="glue_panel" v-if="selectedSlot && !selectedSlot.glued">
            <p class="hint">Вклейка: {{ selectedSlot.team_label }}</p>
            <div v-if="!matchingCollectibles.length" class="empty_hint">
              Нет подходящего вымпела или шарфа в инвентаре.
            </div>
            <div v-else class="glue_options">
              <button
                v-for="item in matchingCollectibles"
                :key="item.code"
                type="button"
                class="glue_btn"
                :disabled="busy"
                @click="glueItem(item.code)"
              >
                {{ item.label }} ×{{ item.count }}
              </button>
            </div>
          </div>
        </div>
      </template>

      <p v-if="message" class="message" :class="{ fail: messageFail }">{{ message }}</p>
    </template>

    <div v-if="previewSlot" class="slot_preview_overlay" @click.self="closeSlotPreview">
      <div class="slot_preview_modal">
        <div class="slot_preview_title">{{ previewSlot.label }}</div>
        <img :src="previewSlot.src" class="slot_preview_img" alt="">
        <button type="button" class="action_btn" @click="closeSlotPreview">Закрыть</button>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapMutations, mapState } from 'vuex';
import { apiActions } from '@/api/bitrixClient';
import { WC26_TEAMS } from '@/config/wc26Teams';
import { getWc26PennantIconSrc } from '@/config/wc26PennantIcons';
import { getWc26ScarfIconSrc } from '@/config/wc26ScarfIcons';
import { getAchievementPennantIconSrc, isAchievementPennantCode } from '@/config/achievementPennantIcons';

const MEGA_LABELS = {
  pennant_wc26: 'Мега: вымпелы',
  scarf_wc26: 'Мега: шарфы',
  pennant_achievement: 'Мега: альбом достижений',
};

export default {
  name: 'ProfileAlbumBlock',
  data() {
    return {
      loading: false,
      busy: false,
      error: '',
      message: '',
      messageFail: false,
      state: null,
      selectedAlbumId: 0,
      selectedSlotSlug: '',
      previewSlot: null,
      megaLabels: MEGA_LABELS,
    };
  },
  computed: {
    ...mapState({
      authData: (state) => state.auth.authData,
    }),
    selectedAlbum() {
      const albums = Array.isArray(this.state?.albums) ? this.state.albums : [];
      if (!albums.length) {
        return null;
      }
      const found = albums.find((a) => Number(a.id) === Number(this.selectedAlbumId));
      return found || albums[0];
    },
    albumSlotsGrid() {
      if (!this.selectedAlbum) {
        return [];
      }

      if (Array.isArray(this.selectedAlbum.slot_grid) && this.selectedAlbum.slot_grid.length) {
        return this.selectedAlbum.slot_grid;
      }

      const gluedMap = {};
      const gluedList = this.selectedAlbum.glued_slots || this.selectedAlbum.slots || [];
      gluedList.forEach((slot) => {
        if (slot?.team_slug) {
          gluedMap[slot.team_slug] = slot;
        }
      });

      return WC26_TEAMS.map(({ slug, label }) => {
        if (gluedMap[slug]) {
          return gluedMap[slug];
        }

        return {
          team_slug: slug,
          team_label: label,
          item_code: '',
          item_label: '',
          glued: false,
        };
      });
    },
    selectedSlot() {
      if (!this.selectedAlbum || !this.selectedSlotSlug) {
        return null;
      }
      return (this.selectedAlbum.slots || []).find((s) => s.team_slug === this.selectedSlotSlug) || null;
    },
    isAchievementAlbum() {
      return this.selectedAlbum?.collection === 'pennant_achievement';
    },
    matchingCollectibles() {
      if (!this.selectedSlot || !this.state) {
        return [];
      }
      const collection = this.selectedAlbum?.collection || '';
      const slug = this.selectedSlot.team_slug;

      if (collection === 'pennant_achievement') {
        const glued = this.state?.glued_teams?.pennant_achievement || [];
        return (this.state.achievement_pennants || []).filter((item) => {
          return item.team_slug === slug && !glued.includes(slug);
        });
      }

      if (!this.state.collectibles) {
        return [];
      }

      return this.state.collectibles.filter((item) => {
        if (item.team_slug !== slug) {
          return false;
        }
        const collectionKey = item.collection || '';
        const glued = this.state?.glued_teams?.[collectionKey] || [];
        if (glued.includes(slug)) {
          return false;
        }
        if (!collection) {
          return true;
        }
        return item.collection === collection;
      });
    },
  },
  mounted() {
    this.albumRefreshHandler = () => {
      this.refresh(true);
    };
    window.addEventListener('prognos9ys:album-refresh', this.albumRefreshHandler);
    this.refresh();
  },
  beforeUnmount() {
    if (this.albumRefreshHandler) {
      window.removeEventListener('prognos9ys:album-refresh', this.albumRefreshHandler);
    }
  },
  methods: {
    ...mapActions('auth', ['refreshGameInfo']),
    ...mapMutations('auth', ['setUserInfo', 'patchGameInfo']),

    async refresh(silent = false) {
      const token = this.authData?.token;
      if (!token) {
        return;
      }

      if (!silent) {
        this.loading = true;
      }
      this.error = '';
      try {
        const data = await apiActions.game.getAlbumState(token);
        if (data?.status === 'ok') {
          this.applyState(data.album);
        } else if (!silent) {
          this.error = data?.message || 'Не удалось загрузить коллекцию';
        }
      } catch (e) {
        if (!silent) {
          this.error = e.message || 'Не удалось загрузить коллекцию';
        }
      } finally {
        if (!silent) {
          this.loading = false;
        }
      }
    },

    megaMax(row) {
      const thresholds = Array.isArray(row?.thresholds) ? row.thresholds : [];
      if (thresholds.length) {
        return thresholds[thresholds.length - 1];
      }

      return 48;
    },

    megaPercent(row) {
      const glued = Number(row?.glued) || 0;
      const max = this.megaMax(row) || 48;

      return Math.min(100, Math.round((glued / max) * 100));
    },

    formatMegaReward(reward) {
      if (!reward || typeof reward !== 'object') {
        return '';
      }
      const parts = [];
      if (Number(reward.chests) > 0) {
        parts.push(`${reward.chests} сунд.`);
      }
      if (Number(reward.prognobaks) > 0) {
        parts.push(`${reward.prognobaks} 🪙`);
      }
      if (Number(reward.rublius) > 0) {
        parts.push(`${reward.rublius} 💎`);
      }
      return parts.join(' + ') || '—';
    },

    async claimMegaTier(achievementCode) {
      const token = this.authData?.token;
      if (!token || !achievementCode || this.busy) {
        return;
      }

      this.busy = true;
      this.message = '';
      this.messageFail = false;
      try {
        const data = await apiActions.game.claimAchievement(token, achievementCode);
        if (data?.status === 'ok') {
          await this.refresh(true);
          this.setGameFromResponse(data);
          const reward = data.claimed?.reward || {};
          const bits = [];
          if (Number(reward.prognobaks) > 0) {
            bits.push(`+${reward.prognobaks} 🪙`);
          }
          if (Number(reward.rublius) > 0) {
            bits.push(`+${reward.rublius} 💎`);
          }
          if (Number(reward.chests) > 0) {
            bits.push(`+${reward.chests} сунд.`);
          }
          this.message = bits.length ? `Награда: ${bits.join(', ')}` : 'Награда получена';
        } else {
          this.messageFail = true;
          this.message = data?.message || 'Не удалось забрать награду';
        }
      } catch (e) {
        this.messageFail = true;
        this.message = e.message || 'Не удалось забрать награду';
      } finally {
        this.busy = false;
      }
    },

    applyState(albumState) {
      this.state = albumState || null;
      const albums = this.state?.albums || [];
      if (albums.length && !albums.some((a) => Number(a.id) === Number(this.selectedAlbumId))) {
        this.selectedAlbumId = Number(albums[albums.length - 1].id);
      }
      if (!albums.length) {
        this.selectedAlbumId = 0;
        this.selectedSlotSlug = '';
      }
    },

    selectAlbum(id) {
      this.selectedAlbumId = Number(id);
      this.selectedSlotSlug = '';
      this.message = '';
    },

    onSlotClick(slot) {
      if (slot.glued) {
        return;
      }
      this.selectedSlotSlug = slot.team_slug;
      this.message = '';
    },

    slotCaption(slot) {
      const code = slot?.team_slug || slot?.item_code || '';
      if (code === 'site') {
        return 'Сайт';
      }
      if (code === 'chm2026') {
        return 'ЧМ26';
      }

      const label = String(slot?.team_label || '');
      if (!label) {
        return '';
      }
      const parts = label.split(' · ');
      if (parts.length >= 2) {
        const profession = parts[0];
        const tier = parts[1].includes('премиум') ? 'пре' : 'маст';
        return `${profession} ${tier}`;
      }
      if (label.length > 14) {
        return `${label.slice(0, 12)}…`;
      }
      return label;
    },

    slotTeamCaption(slot) {
      if (this.isAchievementAlbum) {
        return this.slotCaption(slot);
      }

      const label = String(slot?.team_label || '');
      if (label.length > 12) {
        return `${label.slice(0, 11)}…`;
      }

      return label;
    },

    slotPreviewSrc(slot) {
      if (!slot?.team_slug || !this.selectedAlbum) {
        return null;
      }

      const collection = this.selectedAlbum.collection || '';
      const slug = slot.team_slug;

      if (collection === 'pennant_wc26') {
        return getWc26PennantIconSrc(`pennant_wc26_${slug}`);
      }
      if (collection === 'scarf_wc26') {
        return getWc26ScarfIconSrc(`scarf_wc26_${slug}`);
      }
      if (collection === 'pennant_achievement') {
        const code = slot.item_code || slug;
        if (isAchievementPennantCode(code)) {
          return getAchievementPennantIconSrc(code);
        }
      }

      return this.slotPennantSrc(slot);
    },

    openSlotPreview(slot) {
      const src = this.slotPreviewSrc(slot);
      if (!src) {
        return;
      }

      this.previewSlot = {
        src,
        label: slot.team_label || slot.item_label || '',
      };
    },

    closeSlotPreview() {
      this.previewSlot = null;
    },

    slotCollectibleSrc(slot) {
      if (!slot?.glued) {
        return null;
      }
      const code = slot.item_code || '';
      if (code.startsWith('pennant_wc26_')) {
        return getWc26PennantIconSrc(code);
      }
      if (code.startsWith('scarf_wc26_')) {
        return getWc26ScarfIconSrc(code);
      }
      if (isAchievementPennantCode(code)) {
        return getAchievementPennantIconSrc(code);
      }
      return null;
    },

    slotPennantSrc(slot) {
      if (!slot) {
        return null;
      }

      const wc26 = this.slotCollectibleSrc(slot);
      if (wc26) {
        return wc26;
      }

      if (!this.isAchievementAlbum) {
        return null;
      }

      const code = slot.team_slug || slot.item_code || '';
      if (!isAchievementPennantCode(code)) {
        return null;
      }

      return getAchievementPennantIconSrc(code);
    },

    setGameFromResponse(data) {
      if (data?.game) {
        this.patchGameInfo(data.game);
      } else {
        this.refreshGameInfo().catch(() => {});
      }
    },

    async activateAlbum() {
      const token = this.authData?.token;
      if (!token || this.busy) {
        return;
      }
      this.busy = true;
      this.message = '';
      this.messageFail = false;
      try {
        const data = await apiActions.game.activateAlbum(token);
        if (data?.status === 'ok') {
          this.applyState(data.album);
          if (data.album_id) {
            this.selectedAlbumId = Number(data.album_id);
          }
          this.message = (data.lines || []).map((l) => l.text).join(' · ') || 'Альбом активирован';
          this.setGameFromResponse(data);
        } else {
          this.messageFail = true;
          this.message = data?.message || 'Не удалось активировать';
        }
      } catch (e) {
        this.messageFail = true;
        this.message = e.message || 'Ошибка активации';
      } finally {
        this.busy = false;
      }
    },

    async glueItem(itemCode) {
      const token = this.authData?.token;
      const albumId = this.selectedAlbumId;
      if (!token || !albumId || !itemCode || this.busy) {
        return;
      }
      this.busy = true;
      this.message = '';
      this.messageFail = false;
      try {
        const data = await apiActions.game.glueAlbumItem(token, albumId, itemCode);
        if (data?.status === 'ok') {
          this.applyState(data.album);
          this.selectedSlotSlug = '';
          this.message = (data.lines || []).map((l) => l.text).join(' · ') || 'Вклеено';
          this.setGameFromResponse(data);
        } else {
          this.messageFail = true;
          this.message = data?.message || 'Не удалось вклеить';
        }
      } catch (e) {
        this.messageFail = true;
        this.message = e.message || 'Ошибка вклейки';
      } finally {
        this.busy = false;
      }
    },

    buyTiersForRow(row, collectionKey = '') {
      if (collectionKey === 'pennant_achievement') {
        return [];
      }
      const glued = Number(row?.glued) || 0;
      const thresholds = Array.isArray(row?.thresholds) ? row.thresholds : [16, 32, 48];
      return thresholds.filter((tier) => glued < tier);
    },

    async buyCollectionToTier(collection, targetTier) {
      const token = this.authData?.token;
      if (!token || !collection || !targetTier || this.busy) {
        return;
      }

      const label = this.megaLabels[collection] || 'коллекцию';
      const ok = window.confirm(
        `Докупить ${label.toLowerCase()} до ${targetTier} шт.?\n`
        + 'Сначала вклейка из инвентаря, затем покупка на бирже по лучшей цене, пока хватает 🪙.',
      );
      if (!ok) {
        return;
      }

      this.busy = true;
      this.message = '';
      this.messageFail = false;

      try {
        const data = await apiActions.game.buyAlbumCollectionToTier(token, collection, targetTier);
        if (data?.status === 'ok') {
          this.applyState(data.album);
          const parts = (data.lines || []).map((line) => line.text).filter(Boolean);
          const summary = [];
          if (Number(data.bought) > 0) {
            summary.push(`куплено: ${data.bought}`);
          }
          if (Number(data.glued_from_inventory) > 0) {
            summary.push(`из инвентаря: ${data.glued_from_inventory}`);
          }
          if (Number(data.spent) > 0) {
            summary.push(`−${data.spent} 🪙`);
          }
          this.message = parts.join(' · ')
            || (summary.length ? summary.join(', ') : 'Готово');
          this.setGameFromResponse(data);
        } else {
          this.messageFail = true;
          this.message = data?.message || 'Не удалось докупить';
        }
      } catch (e) {
        this.messageFail = true;
        this.message = e.message || 'Ошибка докупки';
      } finally {
        this.busy = false;
      }
    },

    async glueAllEligible() {
      const token = this.authData?.token;
      if (!token || this.busy) {
        return;
      }

      this.busy = true;
      this.message = '';
      this.messageFail = false;

      try {
        const data = await apiActions.game.glueAllAlbumItems(
          token,
          this.selectedAlbumId || 0,
        );
        if (data?.status === 'ok') {
          this.applyState(data.album);
          const glued = Number(data.glued) || 0;
          this.message = glued > 0
            ? `Вклеено: ${glued}`
            : ((data.lines || []).map((l) => l.text).join(' · ') || 'Готово');
          this.setGameFromResponse(data);
        } else {
          this.messageFail = true;
          this.message = data?.message || 'Не удалось вклеить';
        }
      } catch (e) {
        this.messageFail = true;
        this.message = e.message || 'Ошибка массовой вклейки';
      } finally {
        this.busy = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.album_block {
  background: @DarkColorBG;
  color: @colorText;
  padding: 8px;
  border-radius: 5px;
  margin: 8px 0;
  text-align: left;
}

.hint {
  color: @colorBlur;
  font-size: 12px;
  margin: 4px 0;

  &.warn {
    color: @orange;
  }
}

.error {
  color: #f88;
  font-size: 13px;
}

.craft_card {
  .shadow_inset;
  padding: 8px;
  margin-bottom: 10px;
  border-radius: 4px;

  &.learned {
    border: 1px solid fade(@orange, 35%);
  }
}

.craft_title {
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.learned_badge {
  font-size: 10px;
  font-weight: normal;
  text-transform: uppercase;
  color: @orange;
  border: 1px solid fade(@orange, 40%);
  border-radius: 3px;
  padding: 1px 5px;
}

.materials {
  margin-bottom: 8px;
}

.action_btn,
.glue_btn,
.album_tab {
  background: @darkbg;
  color: @colorText;
  border: 1px solid fade(@colorText, 20%);
  border-radius: 4px;
  padding: 6px 10px;
  font-size: 12px;
  cursor: pointer;

  &:disabled {
    opacity: 0.5;
    cursor: default;
  }
}

.activate_row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}

.album_actions {
  margin-bottom: 8px;
}

.album_actions .action_btn {
  width: 100%;
}

.mega_row {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 10px;
}

.mega_card {
  .shadow_inset;
  padding: 8px;
  border-radius: 4px;
}

.mega_head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 6px;
  font-size: 12px;
}

.mega_label {
  font-weight: bold;
}

.mega_value {
  color: @colorBlur;
}

.mega_track {
  height: 6px;
  background: fade(@colorText, 10%);
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 6px;
}

.mega_fill {
  height: 100%;
  background: linear-gradient(90deg, fade(@orange, 70%), @orange);
  border-radius: 3px;
  transition: width 0.25s ease;
}

.mega_tiers {
  display: flex;
  gap: 6px;
}

.mega_tier {
  font-size: 10px;
  color: @colorBlur;
  border: 1px solid fade(@colorText, 15%);
  border-radius: 3px;
  padding: 1px 5px;

  &.done {
    color: @orange;
    border-color: fade(@orange, 45%);
  }

  &.next {
    box-shadow: 0 0 0 1px fade(@orange, 25%);
  }
}

.mega_rewards {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.mega_buy_row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.mega_buy_btn {
  background: fade(@orange, 12%);
  color: @orange;
  border: 1px solid fade(@orange, 35%);
  border-radius: 4px;
  padding: 5px 8px;
  font-size: 11px;
  cursor: pointer;

  &:disabled {
    opacity: 0.5;
    cursor: default;
  }
}

.mega_buy_hint {
  margin-top: 4px;
}

.mega_reward_row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  font-size: 11px;
}

.mega_reward_label {
  color: @colorBlur;
  min-width: 42px;
}

.mega_reward_text {
  flex: 1;
}

.mega_claim_btn {
  background: @orange;
  color: #fff;
  border: none;
  border-radius: 3px;
  padding: 2px 8px;
  font-size: 11px;
  cursor: pointer;

  &:disabled {
    opacity: 0.5;
    cursor: default;
  }
}

.mega_claimed {
  color: @orange;
  font-weight: bold;
}

.album_tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}

.album_tab.active {
  border-color: @orange;
}

.album_header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 8px;
}

.slots_grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
  gap: 4px;
  margin-bottom: 10px;
}

.slot_cell {
  position: relative;
  min-height: 52px;
  padding: 4px;
  border: 1px solid fade(@colorText, 15%);
  border-radius: 4px;
  background: @darkbg;
  color: @colorText;
  text-align: center;
  cursor: default;
  font-size: 10px;
  line-height: 1.2;

  &.clickable {
    cursor: pointer;
  }

  &.glued {
    border-color: fade(@orange, 50%);
    background: fade(@orange, 8%);
  }

  &.selected {
    outline: 1px solid @orange;
  }
}

.slot_flag {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.slot_caption {
  display: block;
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 9px;
  line-height: 1.1;
  color: fade(@colorText, 72%);
}

.slot_zoom_btn {
  position: absolute;
  top: 2px;
  right: 2px;
  z-index: 2;
  width: 18px;
  height: 18px;
  padding: 0;
  border: 1px solid fade(@colorBlur, 35%);
  border-radius: 3px;
  background: fade(@darkbg, 92%);
  color: @colorText;
  font-size: 12px;
  line-height: 1;
  cursor: pointer;

  &:hover {
    border-color: fade(@orange, 55%);
    color: @orange;
  }
}

.slot_preview_overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(0, 0, 0, 0.72);
}

.slot_preview_modal {
  max-width: min(92vw, 420px);
  width: 100%;
  padding: 14px;
  border-radius: 8px;
  background: @darkbg;
  border: 1px solid fade(@orange, 35%);
  text-align: center;
}

.slot_preview_title {
  margin-bottom: 10px;
  font-size: 14px;
  font-weight: 600;
}

.slot_preview_img {
  display: block;
  width: 100%;
  max-height: 60vh;
  margin: 0 auto 12px;
  object-fit: contain;
  filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.45));
}

.slot_item {
  display: block;
  font-size: 16px;
  margin-top: 2px;
}

.slot_collectible_img {
  display: block;
  width: 100%;
  max-height: 42px;
  margin-top: 2px;
  object-fit: contain;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.35));
}

.slot_collectible_img_dim {
  opacity: 0.38;
  filter: grayscale(0.35) drop-shadow(0 1px 2px rgba(0, 0, 0, 0.2));
}

.glue_panel {
  .shadow_inset;
  padding: 8px;
}

.glue_options {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.empty_hint {
  color: @colorBlur;
  font-size: 12px;
  padding: 8px 0;
}

.message {
  margin-top: 8px;
  font-size: 12px;
  color: @orange;

  &.fail {
    color: #f88;
  }
}
</style>
