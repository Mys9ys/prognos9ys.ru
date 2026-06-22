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
      <div class="bulk_actions" v-if="isModerator && expanded" @click.stop>
        <div class="bulk_title">Массовые действия</div>
        <div class="bulk_row">
          <button
              type="button"
              class="bulk_btn"
              :disabled="bulkLoading"
              title="Сундук ЧМ за 50 прогнобаксов — у кого доступен и не куплен"
              @click="runBulk('prognobaks_chests')"
          >
            <AppIcon name="chest_wc2026" :size="12" />
            <AppIcon name="prognobak" :size="10" />
            <span>50 всем</span>
          </button>
          <button
              type="button"
              class="bulk_btn"
              :disabled="bulkLoading"
              title="Забрать незабранный опыт у всех"
              @click="runBulk('claim_xp')"
          >
            <AppIcon name="xp" :size="12" />
            <span>Опыт всем</span>
          </button>
          <button
              type="button"
              class="bulk_btn"
              :disabled="bulkLoading"
              title="Сундук за 5 рублиусов — у кого есть 💎 и не куплен"
              @click="runBulk('rublius_chests')"
          >
            <AppIcon name="chest_wc2026" :size="12" />
            <AppIcon name="rublius" :size="10" />
            <span>5💎 всем</span>
          </button>
          <button
              type="button"
              class="bulk_btn"
              :disabled="bulkLoading"
              title="Премиум 1 сутки за 3 рублиуса — у кого есть 💎"
              @click="runBulk('premium_1d')"
          >
            <span class="bulk_emoji">📜</span>
            <span>1д всем</span>
          </button>
          <button
              type="button"
              class="bulk_btn"
              :disabled="bulkLoading"
              title="Займ 50 прогнобаксов — у кого меньше 50, из банка с max ликвидностью"
              @click="runBulk('grant_loans')"
          >
            <AppIcon name="bank" :size="12" />
            <span>Займ 50</span>
          </button>
        </div>
        <div class="bulk_msg ok" v-if="bulkMessage">{{ bulkMessage }}</div>
        <div class="bulk_msg error" v-if="bulkError">{{ bulkError }}</div>
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
          <th v-if="!isTreasureMode && mode !== 'pending_xp'"><AppIcon name="rublius" :size="16" /></th>
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
              <template v-if="showRowAdminActions">
                <button
                    type="button"
                    class="row_btn xp_btn"
                    :class="{ dim: !hasPendingXp(el) }"
                    :disabled="rowBusy(el.user.id, 'xp') || !hasPendingXp(el)"
                    :title="hasPendingXp(el) ? `Собрать +${formatMoney(el.pending_points)} XP` : 'Нет опыта'"
                    @click.stop="claimXpForUser(el)"
                >
                  <AppIcon name="xp" :size="12" />
                  <span v-if="hasPendingXp(el)">+{{ formatMoney(el.pending_points) }}</span>
                </button>
                <button
                    type="button"
                    class="row_btn chest_btn"
                    :class="{ bought: el.shop_offers?.prognobaks_bought }"
                    :disabled="rowBusy(el.user.id, 'prognobaks') || !canBuyPrognobaks(el)"
                    title="Сундук за 50 прогнобаксов"
                    @click.stop="buyChestForUser(el, 'prognobaks')"
                >
                  <AppIcon name="chest_wc2026" :size="14" />
                  <AppIcon name="prognobak" :size="11" />
                </button>
                <button
                    type="button"
                    class="row_btn chest_btn"
                    :class="{ bought: el.shop_offers?.rublius_bought }"
                    :disabled="rowBusy(el.user.id, 'rublius') || !canBuyRublius(el)"
                    title="Сундук за 5 рублиусов"
                    @click.stop="buyChestForUser(el, 'rublius')"
                >
                  <AppIcon name="chest_wc2026" :size="14" />
                  <AppIcon name="rublius" :size="11" />
                </button>
                <button
                    type="button"
                    class="row_btn premium_btn"
                    :class="{ bought: el.shop_offers?.premium_bought }"
                    :disabled="rowBusy(el.user.id, 'premium') || !canBuyPremium(el)"
                    title="Премиум 1 сутки за 3 рублиуса"
                    @click.stop="buyPremiumForUser(el)"
                >
                  <span class="premium_icon">📜</span>
                  <AppIcon name="rublius" :size="11" />
                </button>
              </template>
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
          <td class="money" v-if="!isTreasureMode && mode !== 'pending_xp'">{{ formatMoney(el.rublius) }}</td>
        </tr>
        </tbody>
      </table>
      <div class="wealth_empty" v-else>{{ emptyText }}</div>
      <div class="wealth_pager" v-if="totalPages > 1">
        <button
            type="button"
            class="pager_btn"
            :disabled="loading || page <= 1"
            @click="goToPage(page - 1)"
        >‹</button>
        <span class="pager_info">{{ page }} / {{ totalPages }}</span>
        <button
            type="button"
            class="pager_btn"
            :disabled="loading || page >= totalPages"
            @click="goToPage(page + 1)"
        >›</button>
      </div>
      <div class="wealth_hint">{{ hintText }}</div>
    </div>

    <BulkActionProgress
        :visible="bulkProgressVisible"
        :title="bulkProgressTitle"
        :lines="bulkProgressLines"
        :current="bulkProgressCurrent"
        :total="bulkProgressTotal"
        :done="bulkProgressDone"
        @close="closeBulkProgress"
    />
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import PreLoader from '@/components/main/PreLoader';
import AppIcon from '@/components/ui/AppIcon.vue';
import { apiActions } from '@/api/bitrixClient';
import BulkActionProgress from '@/components/game/BulkActionProgress.vue';
import { DEFAULT_AVATAR_URL } from '@/utils/defaultAvatar';

const PAGE_SIZE = 50;
const WEALTH_MODES = ['rich', 'poor', 'pending_xp', 'treasure_rich'];
const BULK_TITLES = {
  prognobaks_chests: 'Сундуки за 50 прогнобаксов',
  claim_xp: 'Сбор опыта',
  rublius_chests: 'Сундуки за 5 рублиусов',
  premium_1d: 'Премиум 1 сутки',
  grant_loans: 'Займы 50 прогнобаксов',
};

export default {
  name: 'WealthRatingBlock',
  components: { PreLoader, AppIcon, BulkActionProgress },
  data() {
    return {
      expanded: false,
      ratingLoaded: false,
      loading: false,
      mode: 'poor',
      ratings: [],
      total: 0,
      gameBank: null,
      rowAction: null,
      bulkLoading: false,
      bulkMessage: '',
      bulkError: '',
      bulkProgressVisible: false,
      bulkProgressTitle: '',
      bulkProgressLines: [],
      bulkProgressCurrent: 0,
      bulkProgressTotal: 0,
      bulkProgressDone: false,
      bulkSummary: null,
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
    showRowAdminActions() {
      return this.canImpersonate && (this.showLevelColumn || this.mode === 'pending_xp');
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
        return 'Σ = прогнобаксы · 💎 отдельно · сортировка по возрастанию';
      }
      if (this.mode === 'treasure_rich') {
        return '🎁 = сумма закрытых сундучков · сортировка по убыванию';
      }

      if (this.canImpersonate) {
        return 'Кнопки в строке: опыт, сундуки и премиум лавки (для ботов без входа)';
      }

      return 'Σ = прогнобаксы · 💎 отдельно';
    },
    page() {
      const raw = Number(this.$route?.query?.wr_page ?? 1);
      return Number.isFinite(raw) && raw > 0 ? Math.floor(raw) : 1;
    },
    offset() {
      return (this.page - 1) * PAGE_SIZE;
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.total / PAGE_SIZE));
    },
  },
  created() {
    this.applyRouteState(false);
    this.loadGameBank();
  },
  watch: {
    expanded(isExpanded) {
      if (isExpanded && !this.ratingLoaded) {
        this.ratingLoaded = true;
        this.loadRating();
      }
    },
    '$route.query': {
      deep: true,
      handler() {
        this.applyRouteState(true);
      },
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
      claimAllXp: 'game/claimAllXp',
      refreshGameInfo: 'auth/refreshGameInfo',
    }),

    setMode(mode) {
      if (this.mode === mode) {
        return;
      }

      this.updateRoute({ wr_mode: mode, wr_page: undefined });
    },

    applyRouteState(reload) {
      const routeMode = this.$route?.query?.wr_mode;
      if (WEALTH_MODES.includes(routeMode) && routeMode !== this.mode) {
        this.mode = routeMode;
      }

      if (this.$route?.query?.wr_mode || this.$route?.query?.wr_page) {
        this.expanded = true;
        if (!this.ratingLoaded) {
          this.ratingLoaded = true;
        }
        if (reload) {
          this.loadRating();
        } else if (this.ratingLoaded) {
          this.loadRating();
        }
      }
    },

    async updateRoute(patch) {
      const query = { ...this.$route.query };

      Object.entries(patch).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
          delete query[key];
        } else {
          query[key] = String(value);
        }
      });

      if (patch.wr_mode) {
        this.mode = patch.wr_mode;
      }

      const sameQuery = JSON.stringify(query) === JSON.stringify(this.$route.query);
      if (!sameQuery) {
        await this.$router.replace({ query });
        return;
      }

      await this.loadRating();
    },

    goToPage(nextPage) {
      const page = Math.max(1, Math.min(this.totalPages, Number(nextPage) || 1));
      this.updateRoute({
        wr_mode: this.mode,
        wr_page: page > 1 ? page : undefined,
      });
    },

    async runBulk(bulkAction) {
      if (!this.authData?.token || this.bulkLoading) {
        return;
      }

      const prompts = {
        prognobaks_chests: 'Купить всем доступный сундук ЧМ за 50 прогнобаксов?',
        claim_xp: 'Забрать незабранный опыт у всех игроков?',
        rublius_chests: 'Купить всем (с рублиусами) сундук за 5 💎?',
        premium_1d: 'Купить всем (с рублиусами) премиум на 1 сутки за 3 💎?',
        grant_loans: 'Выдать займ 50 прогнобаксов всем, у кого на кошельке меньше 50? Банк — с максимальной ликвидностью.',
      };

      if (!window.confirm(prompts[bulkAction] || 'Выполнить массовое действие?')) {
        return;
      }

      this.bulkLoading = true;
      this.bulkMessage = '';
      this.bulkError = '';
      this.bulkProgressVisible = true;
      this.bulkProgressDone = false;
      this.bulkProgressTitle = BULK_TITLES[bulkAction] || 'Массовое действие';
      this.bulkProgressLines = [{ text: 'Загрузка списка…', status: 'pending' }];
      this.bulkProgressCurrent = 0;
      this.bulkProgressTotal = 0;
      this.bulkSummary = { success: 0, skipped: 0, failed: 0 };

      try {
        const preview = await apiActions.game.moderatorBulkCandidates(
          this.authData.token,
          bulkAction,
        );
        const candidates = preview?.candidates || [];
        this.bulkProgressTotal = candidates.length;
        this.bulkProgressLines = [];

        if (!candidates.length) {
          this.bulkProgressLines.push({ text: 'Никого для обработки', status: 'skip' });
          this.bulkProgressDone = true;
          this.bulkMessage = 'Нет подходящих игроков';
          return;
        }

        for (let i = 0; i < candidates.length; i++) {
          const candidate = candidates[i];
          const name = candidate.name || `Игрок #${candidate.user_id}`;
          const hint = candidate.hint ? ` — ${candidate.hint}` : '';

          this.bulkProgressLines.push({
            text: `${name}${hint}…`,
            status: 'pending',
          });
          const lineIndex = this.bulkProgressLines.length - 1;

          try {
            const result = await apiActions.game.moderatorBulkRunOne(
              this.authData.token,
              bulkAction,
              candidate.user_id,
            );
            const status = result?.status || 'failed';
            const message = result?.message || '';

            if (status === 'success') {
              this.bulkSummary.success++;
              this.bulkProgressLines[lineIndex] = {
                text: `✓ ${name}: ${message}`,
                status: 'ok',
              };
            } else if (status === 'skipped') {
              this.bulkSummary.skipped++;
              this.bulkProgressLines[lineIndex] = {
                text: `— ${name}: ${message}`,
                status: 'skip',
              };
            } else {
              this.bulkSummary.failed++;
              this.bulkProgressLines[lineIndex] = {
                text: `✗ ${name}: ${message}`,
                status: 'fail',
              };
            }
          } catch (e) {
            this.bulkSummary.failed++;
            this.bulkProgressLines[lineIndex] = {
              text: `✗ ${name}: ${e.message || 'ошибка'}`,
              status: 'fail',
            };
          }

          this.bulkProgressCurrent = i + 1;
        }

        const s = this.bulkSummary;
        this.bulkMessage = `Готово: ${s.success} ок, пропущено ${s.skipped}, ошибок ${s.failed}`;
        this.bulkProgressLines.push({
          text: this.bulkMessage,
          status: 'ok',
        });
        this.bulkProgressDone = true;

        await this.loadRating();
        await this.loadGameBank();
        await this.refreshGameInfo();
      } catch (e) {
        this.bulkError = e.message || 'Массовое действие не выполнено';
        this.bulkProgressLines.push({
          text: this.bulkError,
          status: 'fail',
        });
        this.bulkProgressDone = true;
        console.log('runBulk error', e);
      } finally {
        this.bulkLoading = false;
      }
    },

    closeBulkProgress() {
      this.bulkProgressVisible = false;
      this.bulkProgressLines = [];
      this.bulkProgressCurrent = 0;
      this.bulkProgressTotal = 0;
      this.bulkProgressDone = false;
    },

    async loadRating() {
      this.loading = true;
      try {
        const data = await apiActions.game.getWealthRating(PAGE_SIZE, this.mode, this.offset);
        if (data?.status === 'ok') {
          this.ratings = data.ratings || [];
          this.total = Number(data.total ?? this.ratings.length);
          const maxPage = Math.max(1, Math.ceil(this.total / PAGE_SIZE));
          if (this.page > maxPage) {
            await this.updateRoute({ wr_mode: this.mode, wr_page: undefined });
          }
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

    async claimXpForUser(row) {
      const userId = row?.user?.id;
      if (!userId || this.rowBusy(userId, 'xp')) {
        return;
      }

      this.rowAction = { userId, action: 'xp' };
      try {
        await this.claimAllXp(userId);
        const isSelf = Number(userId) === Number(this.userInfo?.ID);
        if (isSelf) {
          await this.refreshGameInfo();
        }
        await this.loadRating();
        await this.loadGameBank();
      } catch (e) {
        console.log('claimXpForUser error', e);
      } finally {
        this.rowAction = null;
      }
    },

    async buyChestForUser(row, currency) {
      const userId = row?.user?.id;
      if (!userId || !this.authData?.token || this.rowBusy(userId, currency)) {
        return;
      }

      this.rowAction = { userId, action: currency };
      try {
        const data = await apiActions.game.buyTreasuryChest(this.authData.token, currency, userId);
        if (data?.status === 'ok') {
          const isSelf = Number(userId) === Number(this.userInfo?.ID);
          if (isSelf) {
            await this.refreshGameInfo();
          }
          await this.loadRating();
          await this.loadGameBank();
        }
      } catch (e) {
        console.log('buyChestForUser error', e);
      } finally {
        this.rowAction = null;
      }
    },

    hasPendingXp(row) {
      return Number(row?.pending_points ?? 0) > 0 || Number(row?.pending_count ?? 0) > 0;
    },

    canBuyPrognobaks(row) {
      return !!(row?.shop_offers?.prognobaks_available && !row?.shop_offers?.prognobaks_bought);
    },

    canBuyRublius(row) {
      return !!(row?.shop_offers?.rublius_available && !row?.shop_offers?.rublius_bought);
    },

    canBuyPremium(row) {
      return !!(row?.shop_offers?.premium_available && !row?.shop_offers?.premium_bought);
    },

    async buyPremiumForUser(row) {
      const userId = row?.user?.id;
      if (!userId || !this.authData?.token || this.rowBusy(userId, 'premium')) {
        return;
      }

      this.rowAction = { userId, action: 'premium' };
      try {
        const data = await apiActions.game.buyTreasuryPremium(
          this.authData.token,
          'premium_1d',
          userId,
        );
        if (data?.status === 'ok') {
          const isSelf = Number(userId) === Number(this.userInfo?.ID);
          if (isSelf) {
            await this.refreshGameInfo();
          }
          await this.loadRating();
          await this.loadGameBank();
        }
      } catch (e) {
        console.log('buyPremiumForUser error', e);
      } finally {
        this.rowAction = null;
      }
    },

    rowBusy(userId, action) {
      return this.rowAction?.userId === userId && this.rowAction?.action === action;
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

.bulk_actions {
  margin-top: 6px;
  padding: 6px 8px;
  border-radius: 4px;
  background: rgba(0, 0, 0, 0.25);
}

.bulk_title {
  font-size: 10px;
  color: @colorBlur;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 5px;
}

.bulk_row {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.bulk_btn {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  border: 1px solid @orange;
  background: @darkbg;
  color: @colorText;
  border-radius: 4px;
  padding: 4px 6px;
  font-size: 10px;
  line-height: 1.1;
  cursor: pointer;

  &:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }

  .bulk_emoji {
    font-size: 12px;
    line-height: 1;
  }
}

.bulk_msg {
  margin-top: 5px;
  font-size: 11px;
  line-height: 1.3;

  &.ok {
    color: @YesWrite;
  }

  &.error {
    color: #f88;
  }
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
      flex: 0 1 35%;
      max-width: 35%;
      min-width: 0;
      text-align: left;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .user_actions {
      display: flex;
      flex-wrap: wrap;
      gap: 3px;
      flex-shrink: 0;
      justify-content: flex-end;
      max-width: 148px;
    }

    .row_btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 2px;
      min-width: 22px;
      height: 22px;
      padding: 2px 4px;
      border-radius: 4px;
      border: 1px solid @orange;
      background: rgba(0, 0, 0, 0.2);
      color: @colorText;
      cursor: pointer;
      font-size: 9px;
      line-height: 1;

      &:disabled {
        opacity: 0.35;
        cursor: not-allowed;
      }

      &.dim:not(:disabled) {
        opacity: 0.55;
      }

      &.bought {
        border-color: @YesWrite;
        opacity: 0.5;
      }

      &.xp_btn {
        color: @yellow;
        font-weight: 700;
      }

      &.premium_btn {
        .premium_icon {
          font-size: 11px;
          line-height: 1;
        }
      }
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

.wealth_pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-top: 8px;
  padding: 4px 0;
}

.pager_btn {
  background: @darkbg;
  color: @colorText;
  border: 1px solid fade(@colorBlur, 40%);
  border-radius: 4px;
  width: 28px;
  height: 28px;
  cursor: pointer;
  font-size: 16px;
  line-height: 1;

  &:disabled {
    opacity: 0.35;
    cursor: not-allowed;
  }
}

.pager_info {
  font-size: 12px;
  color: @colorText;
  min-width: 52px;
  text-align: center;
}

.level_cell {
  text-align: center;
  font-weight: 700;
  color: @orange;
  white-space: nowrap;
}
</style>
