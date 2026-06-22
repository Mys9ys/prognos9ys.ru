<template>
  <div class="wealth_block">
    <div class="wealth_header">
      <div class="wealth_title_row">
        <div class="wealth_title" @click="expanded = !expanded">
          <span class="wealth_title_icon" v-if="titleIcon">
            <AppIcon :name="titleIcon" :size="16" />
          </span>
          {{ blockTitle }}
        </div>
        <div class="wealth_toggle" @click="expanded = !expanded">{{ expanded ? '−' : '+' }}</div>
      </div>
      <div class="game_bank_row" v-if="isModerator && gameBank" @click.stop>
        <AppIcon name="bank" :size="14" /> Госбанк: <strong>{{ formatMoney(gameBank.prognobaks) }} <AppIcon name="prognobak" :size="14" /></strong>
        <span class="bank_hint">остатки паримутуеля</span>
      </div>
      <TreasuryAdminBlock v-if="isModerator && treasury" :treasury="treasury" />
      <div class="player_toolbar" v-if="isLoggedIn && expanded" @click.stop>
        <div class="toolbar_level">Ур. <strong>{{ playerLevel }}</strong></div>
        <button
            v-if="showClaimXpBtn"
            type="button"
            class="toolbar_btn xp_btn"
            :disabled="claimingXp"
            @click="claimAllExperience"
        >
          <AppIcon name="xp" :size="14" />
          <span>{{ claimingXp ? '...' : `+${pendingXpDisplay}` }}</span>
        </button>
        <button
            type="button"
            class="toolbar_btn chest_btn"
            :class="{ bought: shopOffers.prognobaks?.bought }"
            :disabled="buyingChest || !shopOffers.prognobaks?.available"
            @click="buyChest('prognobaks')"
        >
          <AppIcon name="chest_wc2026" :size="16" />
          <span>50 <AppIcon name="prognobak" :size="12" /></span>
        </button>
        <button
            type="button"
            class="toolbar_btn chest_btn"
            :class="{ bought: shopOffers.rublius?.bought }"
            :disabled="buyingChest || !shopOffers.rublius?.available"
            @click="buyChest('rublius')"
        >
          <AppIcon name="chest_wc2026" :size="16" />
          <span>5 <AppIcon name="rublius" :size="12" /></span>
        </button>
      </div>
      <div class="wealth_filters" v-if="expanded" @click.stop>
        <button
            type="button"
            class="filter_btn"
            :class="{ active: mode === 'rich' }"
            @click="setMode('rich')"
        ><span class="filter_icon_back"><AppIcon name="wealth" :size="14" /></span> Богатые</button>
        <button
            type="button"
            class="filter_btn"
            :class="{ active: mode === 'poor' }"
            @click="setMode('poor')"
        ><span class="filter_icon_back"><AppIcon name="poverty" :size="14" /></span> Бедные</button>
        <button
            type="button"
            class="filter_btn"
            :class="{ active: mode === 'treasure_rich' }"
            @click="setMode('treasure_rich')"
        ><span class="filter_icon_back"><AppIcon name="chest_wc2026" :size="18" /></span></button>
        <button
            type="button"
            class="filter_btn"
            :class="{ active: mode === 'pending_xp' }"
            @click="setMode('pending_xp')"
        ><span class="filter_icon_back"><AppIcon name="xp" :size="14" /></span> Есть опыт</button>
      </div>
    </div>

    <PreLoader v-if="loading && expanded"></PreLoader>

    <div class="wealth_body" v-else-if="expanded">
      <table class="table table-dark table-hover wealth_table" v-if="ratings.length">
        <thead>
        <tr>
          <th>#</th>
          <th>Ник</th>
          <th v-if="showLevelColumn">Ур.</th>
          <th v-if="mode === 'pending_xp'">Матчей</th>
          <th v-if="mode === 'pending_xp'"><AppIcon name="xp" :size="14" /></th>
          <th v-if="isTreasureMode"><AppIcon name="chest_wc2026" :size="16" /></th>
          <th v-if="!isTreasureMode && mode !== 'pending_xp'"><AppIcon name="prognobak" :size="16" /></th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="(el, index) in ratings" :key="rowKey(el, index)">
          <td>{{ el.place }}</td>
          <td class="user_cell">
            <span class="user_ava">
              <img :src="url + el.user.img" alt="" v-if="el.user?.img">
              <img :src="defaultAvatar" alt="" v-else>
            </span>
            <div class="user_nick">{{ el.user?.name || '—' }}</div>
            <div class="user_actions" v-if="el.user?.id">
              <span
                  v-if="canImpersonate"
                  class="user_enter"
                  title="Войти как пользователь"
                  @click.stop="loginAsUser(el.user.id)"
              >
                <AppIcon name="exit_door" :size="14" />
              </span>
              <span class="user_info" @click.stop="$router.push('/profile/' + el.user.id)">i</span>
            </div>
          </td>
          <td class="level_cell" v-if="showLevelColumn">{{ el.level ?? 0 }}</td>
          <td class="pending_count" v-if="mode === 'pending_xp'">{{ el.pending_count }}</td>
          <td class="pending_xp" v-if="mode === 'pending_xp'">+{{ formatMoney(el.pending_points) }}</td>
          <td class="money" v-if="isTreasureMode">{{ el.treasure_total }}</td>
          <td class="money" v-if="!isTreasureMode && mode !== 'pending_xp'">{{ formatMoney(el.prognobaks) }}</td>
        </tr>
        </tbody>
      </table>
      <div class="wealth_empty" v-else>{{ emptyText }}</div>
      <div class="wealth_hint">{{ hintText }}</div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import PreLoader from '@/components/main/PreLoader';
import AppIcon from '@/components/ui/AppIcon.vue';
import TreasuryAdminBlock from '@/components/game/TreasuryAdminBlock.vue';
import { apiActions } from '@/api/bitrixClient';
import { DEFAULT_AVATAR_URL } from '@/utils/defaultAvatar';

export default {
  name: 'WealthRatingBlock',
  components: { PreLoader, AppIcon, TreasuryAdminBlock },
  data() {
    return {
      expanded: false,
      ratingLoaded: false,
      loading: false,
      mode: 'poor',
      ratings: [],
      gameBank: null,
      treasury: null,
      shop: null,
      claimingXp: false,
      buyingChest: false,
      toolbarError: '',
      url: 'https://prognos9ys.ru',
      defaultAvatar: DEFAULT_AVATAR_URL,
    };
  },
  computed: {
    ...mapState({
      userInfo: state => state.auth.userInfo,
      authData: state => state.auth.authData,
    }),
    isLoggedIn() {
      return !!this.authData?.token;
    },
    playerLevel() {
      return Number(this.userInfo?.game_info?.progress?.level ?? 0);
    },
    pendingXp() {
      const pending = this.userInfo?.game_info?.pending_xp || {};
      return {
        count: Number(pending.count ?? 0),
        points: Number(pending.points ?? 0),
      };
    },
    pendingXpDisplay() {
      const points = this.pendingXp.points;
      return Number.isInteger(points) ? points : points.toFixed(1);
    },
    showClaimXpBtn() {
      return this.pendingXp.count > 0 || this.pendingXp.points > 0;
    },
    shopOffers() {
      const offers = this.shop?.offers || {};
      return {
        prognobaks: offers.prognobaks_chest || null,
        rublius: offers.rublius_chest || null,
      };
    },
    showLevelColumn() {
      return this.mode === 'rich' || this.mode === 'poor';
    },
    isTreasureMode() {
      return this.mode === 'treasure_rich';
    },
    canImpersonate() {
      const role = this.userInfo?.role;
      return !!this.userInfo?.can_impersonate
          || role === 'admin'
          || role === 'super_moder';
    },
    isModerator() {
      return this.canImpersonate;
    },
    titleIcon() {
      if (this.mode === 'poor') {
        return 'poverty';
      }
      if (this.mode === 'pending_xp') {
        return 'xp';
      }
      if (this.mode === 'treasure_rich') {
        return 'chest_wc2026';
      }

      return 'wealth';
    },
    blockTitle() {
      if (this.mode === 'poor') {
        return 'Самые бедные';
      }
      if (this.mode === 'pending_xp') {
        return 'Незабранный опыт';
      }
      if (this.mode === 'treasure_rich') {
        return 'Сокровищницы';
      }

      return 'Самые богатые';
    },
    emptyText() {
      if (this.mode === 'pending_xp') {
        return 'Нет незабранного опыта';
      }
      if (this.mode === 'poor') {
        return 'Нет участников с кошельком';
      }
      if (this.mode === 'treasure_rich') {
        return 'Пока никто не накопил сокровища';
      }

      return 'Пока никто не накопил капитал';
    },
    hintText() {
      if (this.mode === 'pending_xp') {
        return 'Дверь — войти и нажать «Получить опыт» на матчах';
      }
      if (this.mode === 'poor') {
        return 'Σ = прогнобаксы · сортировка по возрастанию';
      }
      if (this.mode === 'treasure_rich') {
        return '🎁 = сумма закрытых сундучков · сортировка по убыванию';
      }

      return 'Σ = прогнобаксы';
    },
  },
  created() {
    this.loadGameBank();
    this.loadShop();
  },
  watch: {
    expanded(isExpanded) {
      if (isExpanded && !this.ratingLoaded) {
        this.ratingLoaded = true;
        this.loadRating();
      }
      if (isExpanded && this.isLoggedIn) {
        this.loadShop();
      }
    },
    'userInfo.token'(token) {
      if (token) {
        this.loadGameBank();
        this.loadShop();
      }
    },
    'authData.token'(token) {
      if (token) {
        this.loadShop();
      }
    },
  },
  methods: {
    ...mapActions({
      impersonateStart: 'auth/impersonateStart',
      claimAllXp: 'game/claimAllXp',
      refreshGameInfo: 'auth/refreshGameInfo',
      showBulkLevelBanner: 'game/showBulkLevelBanner',
    }),

    setMode(mode) {
      if (this.mode === mode) {
        return;
      }

      this.mode = mode;
      this.loadRating();
    },

    async loadRating() {
      this.loading = true;
      try {
        const data = await apiActions.game.getWealthRating(50, this.mode);
        if (data?.status === 'ok') {
          this.ratings = data.ratings || [];
        }
      } catch (e) {
        console.log('wealth rating error', e);
      } finally {
        this.loading = false;
      }
    },

    async loadGameBank() {
      if (!this.isModerator) {
        return;
      }

      const userToken = this.userInfo?.token;
      if (!userToken) {
        return;
      }

      try {
        const data = await apiActions.game.getGameBank(userToken);
        if (data?.status === 'ok') {
          this.gameBank = data.bank || null;
          this.treasury = data.treasury || null;
        }
      } catch (e) {
        console.log('game bank error', e);
      }
    },

    async loadShop() {
      if (!this.isLoggedIn) {
        return;
      }

      try {
        const data = await apiActions.game.getTreasuryShop(this.authData.token);
        if (data?.status === 'ok') {
          this.shop = data.shop || null;
        }
      } catch (e) {
        console.log('treasury shop error', e);
      }
    },

    async claimAllExperience() {
      if (this.claimingXp) {
        return;
      }

      this.claimingXp = true;
      try {
        const result = await this.claimAllXp();
        await this.refreshGameInfo();
        if (result?.level_up) {
          this.showBulkLevelBanner({
            oldLevel: result.old_level,
            newLevel: result.new_level,
            levelRewards: result.level_rewards || [],
          });
        }
        if (this.ratingLoaded) {
          this.loadRating();
        }
      } catch (e) {
        console.log('claimAllXp error', e);
      } finally {
        this.claimingXp = false;
      }
    },

    async buyChest(currency) {
      if (this.buyingChest || !this.authData?.token) {
        return;
      }

      this.buyingChest = true;
      try {
        const data = await apiActions.game.buyTreasuryChest(this.authData.token, currency);
        if (data?.status === 'ok') {
          this.shop = data.shop || this.shop;
          await this.refreshGameInfo();
          if (data?.level_up) {
            this.showBulkLevelBanner({
              oldLevel: data.old_level,
              newLevel: data.new_level,
              levelRewards: data.level_rewards || [],
            });
          }
        }
      } catch (e) {
        console.log('buyTreasuryChest error', e);
      } finally {
        this.buyingChest = false;
      }
    },

    formatMoney(value) {
      const num = Number(value ?? 0);
      return Number.isInteger(num) ? String(num) : num.toFixed(1);
    },

    rowKey(el, index) {
      return el?.user?.id || index;
    },

    async loginAsUser(userId) {
      try {
        await this.impersonateStart(userId);
        this.$router.push('/').then(() => { this.$router.go(); });
      } catch (e) {
        console.log('loginAsUser error', e);
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.wealth_block {
  background: @DarkColorBG;
  color: @colorText;
  border-radius: 5px;
  margin: 8px 0;
  padding: 4px;
}

.wealth_header {
  .shadow_inset;
  padding: 6px 8px;
}

.game_bank_row {
  margin-top: 6px;
  padding: 5px 8px;
  border-radius: 4px;
  background: rgba(0, 0, 0, 0.2);
  font-size: 12px;
  text-align: left;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;

  strong {
    color: @yellow;
  }

  .bank_hint {
    display: block;
    width: 100%;
    margin-top: 2px;
    font-size: 10px;
    color: @colorBlur;
  }
}

.wealth_title_row {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
}

.wealth_title {
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  user-select: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: @orange;
}

.wealth_title_icon,
.filter_icon_back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: @colorBlur;
  border-radius: 3px;
  padding: 2px;
  flex-shrink: 0;
}

.wealth_title_icon {
  width: 22px;
  height: 22px;
}

.wealth_toggle {
  font-size: 18px;
  line-height: 1;
  color: @orange;
  cursor: pointer;
  user-select: none;
}

.wealth_filters {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 6px;
}

.filter_btn {
  border: none;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
  background: @darkbg;
  color: @colorBlur;
  display: inline-flex;
  align-items: center;
  gap: 4px;

  &.active {
    background: @orange;
    color: @DarkColorBG;
    font-weight: 700;
  }
}

.wealth_body {
  margin-top: 4px;
}

.wealth_table {
  width: 100%;
  font-size: 12px;

  th, td {
    padding: 3px 4px;
    vertical-align: middle;
  }

  .money, .total, .pending_xp, .pending_count {
    text-align: right;
    white-space: nowrap;
  }

  .total, .pending_xp {
    font-weight: 700;
    color: @yellow;
  }

  .user_cell {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 4px;

    .user_ava {
      width: 28px;
      flex-shrink: 0;

      img {
        width: 100%;
        height: 28px;
        border-radius: 50%;
        border: 1px solid @YesWrite;
        object-fit: cover;
        object-position: center 12%;
        background: #ffffff;
      }
    }

    .user_nick {
      flex: 1;
      min-width: 0;
      text-align: left;
    }

    .user_actions {
      display: flex;
      gap: 3px;
      flex-shrink: 0;
    }

    .user_enter,
    .user_info {
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 22px;
      height: 22px;
      font-size: 11px;
      border-radius: 5px;
    }

    .user_enter {
      border: 2px solid @yellow;
      background: rgba(0, 0, 0, 0.15);
      padding: 2px;
    }

    .user_info {
      font-weight: 700;
      color: @YesWrite;
      border: 2px solid @YesWrite;
    }
  }
}

.wealth_empty {
  padding: 12px;
  text-align: center;
  color: @colorBlur;
  font-size: 13px;
}

.wealth_hint {
  margin-top: 4px;
  font-size: 10px;
  color: @colorBlur;
  text-align: right;
  padding-right: 4px;
}

.player_toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  padding: 6px;
  border-radius: 4px;
  background: rgba(0, 0, 0, 0.2);
}

.toolbar_level {
  font-size: 12px;
  color: @colorBlur;
  margin-right: 4px;

  strong {
    color: @orange;
    font-size: 14px;
  }
}

.toolbar_btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 1px solid @orange;
  border-radius: 4px;
  background: @darkbg;
  color: @colorText;
  font-size: 11px;
  padding: 4px 8px;
  cursor: pointer;

  &:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }

  &.bought {
    border-color: @YesWrite;
  }
}

.xp_btn {
  color: @yellow;
  font-weight: 700;
}

.level_cell {
  text-align: center;
  font-weight: 700;
  color: @orange;
  white-space: nowrap;
}
</style>
