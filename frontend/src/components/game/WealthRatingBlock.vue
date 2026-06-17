<template>
  <div class="wealth_block">
    <div class="wealth_header">
      <div class="wealth_title_row">
        <div class="wealth_title" @click="expanded = !expanded">{{ blockTitle }}</div>
        <div class="wealth_toggle" @click="expanded = !expanded">{{ expanded ? '−' : '+' }}</div>
      </div>
      <div class="game_bank_row" v-if="isModerator && gameBank" @click.stop>
        🏛 Госбанк: <strong>{{ formatMoney(gameBank.prognobaks) }} 💵</strong>
        <span class="bank_hint">остатки паримутуеля</span>
      </div>
      <div class="wealth_filters" v-if="expanded" @click.stop>
        <button
            type="button"
            class="filter_btn"
            :class="{ active: mode === 'rich' }"
            @click="setMode('rich')"
        >💰 Богатые</button>
        <button
            type="button"
            class="filter_btn"
            :class="{ active: mode === 'poor' }"
            @click="setMode('poor')"
        >🪫 Бедные</button>
        <button
            type="button"
            class="filter_btn"
            :class="{ active: mode === 'treasure_rich' }"
            @click="setMode('treasure_rich')"
        >🎁</button>
        <button
            type="button"
            class="filter_btn"
            :class="{ active: mode === 'pending_xp' }"
            @click="setMode('pending_xp')"
        >🎯 Есть опыт</button>
      </div>
    </div>

    <PreLoader v-if="loading && expanded"></PreLoader>

    <div class="wealth_body" v-else-if="expanded">
      <table class="table table-dark table-hover wealth_table" v-if="ratings.length">
        <thead>
        <tr>
          <th>#</th>
          <th>Ник</th>
          <th v-if="mode === 'pending_xp'">XP</th>
          <th v-if="mode === 'pending_xp'">Матчей</th>
          <th>💵</th>
          <th>💎</th>
          <th v-if="isTreasureMode">🎁</th>
          <th v-if="mode !== 'pending_xp'">Σ</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="(el, index) in ratings" :key="rowKey(el, index)">
          <td>{{ el.place }}</td>
          <td class="user_cell">
            <span class="user_ava">
              <img :src="url + el.user.img" alt="" v-if="el.user?.img">
              <img src="@/assets/img/ava_no_img.jpg" alt="" v-else>
            </span>
            <div class="user_nick">{{ el.user?.name || '—' }}</div>
            <div class="user_actions" v-if="el.user?.id">
              <span
                  v-if="canImpersonate"
                  class="user_enter"
                  title="Войти как пользователь"
                  @click.stop="loginAsUser(el.user.id)"
              >🚪</span>
              <span class="user_info" @click.stop="$router.push('/profile/' + el.user.id)">i</span>
            </div>
          </td>
          <td class="pending_xp" v-if="mode === 'pending_xp'">+{{ formatMoney(el.pending_points) }}</td>
          <td class="pending_count" v-if="mode === 'pending_xp'">{{ el.pending_count }}</td>
          <td class="money">{{ formatMoney(el.prognobaks) }}</td>
          <td class="money">{{ formatMoney(el.rublius) }}</td>
          <td class="money" v-if="isTreasureMode">{{ el.treasure_total }}</td>
          <td class="total" v-if="mode !== 'pending_xp'">{{ formatMoney(el.total) }}</td>
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
import { apiActions } from '@/api/bitrixClient';

export default {
  name: 'WealthRatingBlock',
  components: { PreLoader },
  data() {
    return {
      expanded: false,
      ratingLoaded: false,
      loading: false,
      mode: 'poor',
      ratings: [],
      gameBank: null,
      url: 'https://prognos9ys.ru',
    };
  },
  computed: {
    ...mapState({
      userInfo: state => state.auth.userInfo,
    }),
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
    blockTitle() {
      if (this.mode === 'poor') {
        return '🪫 Самые бедные';
      }
      if (this.mode === 'pending_xp') {
        return '🎯 Незабранный опыт';
      }
      if (this.mode === 'treasure_rich') {
        return '🎁 Сокровищницы';
      }

      return '💰 Самые богатые';
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
        return '🚪 — войти и нажать «Получить опыт» на матчах';
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
  },
  watch: {
    expanded(isExpanded) {
      if (isExpanded && !this.ratingLoaded) {
        this.ratingLoaded = true;
        this.loadRating();
      }
    },
    'userInfo.token'(token) {
      if (token) {
        this.loadGameBank();
      }
    },
  },
  methods: {
    ...mapActions({
      impersonateStart: 'auth/impersonateStart',
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
        }
      } catch (e) {
        console.log('game bank error', e);
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

  strong {
    color: @yellow;
  }

  .bank_hint {
    display: block;
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
        border-radius: 50%;
        border: 1px solid @YesWrite;
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
</style>
