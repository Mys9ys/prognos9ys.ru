<template>
  <div class="exchange_block">
    <div class="exchange_tabs">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="tab_btn"
        :class="{ active: activeTab === tab.id }"
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
      </button>
    </div>

    <div class="exchange_meta" v-if="state">
      <span>🪙 {{ formatMoney(state.wallet_prognobaks) }}</span>
      <span>Лоты: {{ state.active_listings }}/{{ state.max_listings }}</span>
      <span>Комиссия: {{ state.commission_percent }}%</span>
    </div>

    <PreLoader v-if="loading && !state" />

    <div class="msg error" v-if="error">{{ error }}</div>
    <div class="msg ok" v-if="message">{{ message }}</div>

  <!-- Каталог -->
    <div v-if="activeTab === 'catalog'" class="panel">
      <div class="catalog_list" v-if="catalogItems.length">
        <div v-for="item in catalogItems" :key="item.id" class="catalog_row">
          <div class="row_main">
            <div class="row_label">{{ item.label }}</div>
            <div class="row_meta">
              {{ item.qty_remaining }} шт. · {{ item.price_per_unit }} 🪙
              <span v-if="item.expires_at"> · до {{ item.expires_at }}</span>
            </div>
          </div>
          <div class="row_actions">
            <input
              v-model.number="buyQty[item.id]"
              type="number"
              min="1"
              :max="item.qty_remaining"
              class="qty_input"
            />
            <button type="button" class="action_btn" :disabled="busy" @click="buyListing(item)">
              Купить
            </button>
          </div>
        </div>
      </div>
      <div class="empty" v-else>На бирже пока пусто</div>
      <button
        v-if="catalogPagination.has_more"
        type="button"
        class="more_btn"
        :disabled="loading"
        @click="loadMoreCatalog"
      >
        Ещё
      </button>
    </div>

    <!-- Продать -->
    <div v-if="activeTab === 'sell'" class="panel">
      <div class="sell_list" v-if="sellable.length">
        <div v-for="(item, index) in sellable" :key="sellKey(item, index)" class="sell_row">
          <div class="row_label">{{ item.label }}</div>
          <div class="row_meta">
            В инвентаре: {{ item.available }} · номинал {{ item.nominal }}–{{ item.max_price }} 🪙
            · макс. {{ item.pallet_limit }}/лот
          </div>
          <div class="row_actions">
            <input v-model.number="sellForm[itemKey(item)].qty" type="number" min="1" :max="Math.min(item.available, item.pallet_limit)" class="qty_input" />
            <input v-model.number="sellForm[itemKey(item)].price" type="number" step="0.1" :min="item.nominal" :max="item.max_price" class="price_input" />
            <button type="button" class="action_btn" :disabled="busy" @click="createListing(item)">
              Выставить
            </button>
          </div>
        </div>
      </div>
      <div class="empty" v-else>Нечего продавать — откройте сундуки, добывайте на фарме или получите награды</div>
    </div>

    <!-- Мои лоты -->
    <div v-if="activeTab === 'my'" class="panel">
      <div class="catalog_list" v-if="myListings.length">
        <div v-for="item in myListings" :key="item.id" class="catalog_row">
          <div class="row_main">
            <div class="row_label">{{ item.label }}</div>
            <div class="row_meta">
              {{ item.qty_remaining }}/{{ item.qty_total }} · {{ item.price_per_unit }} 🪙 · до {{ item.expires_at }}
            </div>
          </div>
          <button type="button" class="action_btn danger" :disabled="busy" @click="cancelListing(item.id)">
            Снять
          </button>
        </div>
      </div>
      <div class="empty" v-else>Нет активных лотов</div>
    </div>

    <!-- История -->
    <div v-if="activeTab === 'history'" class="panel">
      <div class="history_list" v-if="historyItems.length">
        <div v-for="item in historyItems" :key="item.id" class="history_row">
          <span class="hist_role" :class="item.role">{{ item.role === 'buy' ? 'Покупка' : 'Продажа' }}</span>
          <span class="hist_label">{{ item.label }} ×{{ item.qty }}</span>
          <span class="hist_total">{{ item.total }} 🪙</span>
          <span class="hist_date">{{ item.created_at }}</span>
        </div>
      </div>
      <div class="empty" v-else>Сделок пока нет</div>
    </div>
  </div>
</template>

<script>
import { mapMutations, mapState } from 'vuex';
import PreLoader from '@/components/main/PreLoader.vue';
import { apiActions } from '@/api/bitrixClient';

export default {
  name: 'ExchangeBlock',
  components: { PreLoader },
  data() {
    return {
      activeTab: 'catalog',
      tabs: [
        { id: 'catalog', label: 'Каталог' },
        { id: 'sell', label: 'Продать' },
        { id: 'my', label: 'Мои лоты' },
        { id: 'history', label: 'История' },
      ],
      loading: false,
      busy: false,
      error: '',
      message: '',
      state: null,
      catalogItems: [],
      catalogPagination: { offset: 0, limit: 25, has_more: false },
      myListings: [],
      historyItems: [],
      buyQty: {},
      sellForm: {},
    };
  },
  computed: {
    ...mapState({
      authData: (state) => state.auth.authData,
    }),
    sellable() {
      return Array.isArray(this.state?.sellable) ? this.state.sellable : [];
    },
  },
  watch: {
    activeTab(tab) {
      if (tab === 'catalog') {
        this.loadCatalog(true);
      } else if (tab === 'my') {
        this.loadMyListings();
      } else if (tab === 'history') {
        this.loadHistory();
      }
    },
    sellable: {
      immediate: true,
      handler(items) {
        const form = { ...this.sellForm };
        items.forEach((item) => {
          const key = this.itemKey(item);
          if (!form[key]) {
            form[key] = {
              qty: Math.min(item.available, item.pallet_limit),
              price: item.nominal,
            };
          }
        });
        this.sellForm = form;
      },
    },
  },
  created() {
    this.bootstrap();
  },
  methods: {
    ...mapMutations({
      setUserInfo: 'auth/setUserInfo',
    }),

    formatMoney(value) {
      return Number(value ?? 0).toFixed(1).replace(/\.0$/, '');
    },

    itemKey(item) {
      return [item.kind, item.code, item.category, item.event_id, item.team_code].join(':');
    },

    sellKey(item, index) {
      return this.itemKey(item) + ':' + index;
    },

    async bootstrap() {
      if (!this.authData?.token) {
        return;
      }

      this.loading = true;
      this.error = '';

      try {
        const data = await apiActions.exchange.getState(this.authData.token);
        if (data?.status === 'ok') {
          this.state = data;
        }
        await this.loadCatalog(true);
      } catch (e) {
        this.error = e.message || 'Не удалось загрузить биржу';
      } finally {
        this.loading = false;
      }
    },

    async refreshState() {
      const data = await apiActions.exchange.getState(this.authData.token);
      if (data?.status === 'ok') {
        this.state = data;
      }
    },

    async loadCatalog(reset = false) {
      if (!this.authData?.token) {
        return;
      }

      if (reset) {
        this.catalogPagination.offset = 0;
        this.catalogItems = [];
      }

      this.loading = true;
      try {
        const data = await apiActions.exchange.getCatalog(
          this.authData.token,
          this.catalogPagination.offset,
          this.catalogPagination.limit
        );

        if (data?.status === 'ok') {
          const items = Array.isArray(data.items) ? data.items : [];
          this.catalogItems = reset ? items : [...this.catalogItems, ...items];
          this.catalogPagination = {
            ...this.catalogPagination,
            ...(data.pagination || {}),
          };
          items.forEach((item) => {
            if (!this.buyQty[item.id]) {
              this.buyQty[item.id] = 1;
            }
          });
        }
      } catch (e) {
        this.error = e.message || 'Ошибка каталога';
      } finally {
        this.loading = false;
      }
    },

    loadMoreCatalog() {
      this.catalogPagination.offset += this.catalogPagination.limit;
      this.loadCatalog(false);
    },

    async loadMyListings() {
      try {
        const data = await apiActions.exchange.getMyListings(this.authData.token);
        if (data?.status === 'ok') {
          this.myListings = Array.isArray(data.items) ? data.items : [];
        }
      } catch (e) {
        this.error = e.message || 'Ошибка загрузки лотов';
      }
    },

    async loadHistory() {
      try {
        const data = await apiActions.exchange.getTradeHistory(this.authData.token);
        if (data?.status === 'ok') {
          this.historyItems = Array.isArray(data.items) ? data.items : [];
        }
      } catch (e) {
        this.error = e.message || 'Ошибка истории';
      }
    },

    async buyListing(item) {
      const qty = Number(this.buyQty[item.id] || 1);
      if (!qty || qty < 1) {
        return;
      }

      this.busy = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.exchange.buy(
          this.authData.token,
          item.kind,
          item.code,
          qty,
          item.category || '',
          item.event_id || 0,
          item.team_code || ''
        );

        if (data?.status === 'ok') {
          this.message = `Куплено ${data.bought_qty} шт. за ${data.total_spent} 🪙`;
          this.applyGame(data.game);
          await this.refreshState();
          await this.loadCatalog(true);
        }
      } catch (e) {
        this.error = e.message || 'Не удалось купить';
      } finally {
        this.busy = false;
      }
    },

    async createListing(item) {
      const key = this.itemKey(item);
      const form = this.sellForm[key] || {};
      const qty = Number(form.qty || 0);
      const price = Number(form.price || 0);

      if (!qty || qty < 1) {
        return;
      }

      this.busy = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.exchange.createListing(
          this.authData.token,
          item.kind,
          item.code,
          qty,
          price,
          item.category || '',
          item.event_id || 0,
          item.team_code || ''
        );

        if (data?.status === 'ok') {
          this.message = 'Лот выставлен на биржу';
          this.applyGame(data.game);
          await this.refreshState();
          this.activeTab = 'my';
          await this.loadMyListings();
        }
      } catch (e) {
        this.error = e.message || 'Не удалось выставить лот';
      } finally {
        this.busy = false;
      }
    },

    async cancelListing(listingId) {
      this.busy = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.exchange.cancelListing(this.authData.token, listingId);
        if (data?.status === 'ok') {
          this.message = 'Лот снят, предметы возвращены';
          this.applyGame(data.game);
          await this.refreshState();
          await this.loadMyListings();
        }
      } catch (e) {
        this.error = e.message || 'Не удалось снять лот';
      } finally {
        this.busy = false;
      }
    },

    applyGame(game) {
      if (!game) {
        return;
      }
      this.setUserInfo({
        ...this.$store.state.auth.userInfo,
        game_info: game,
      });
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.exchange_block {
  text-align: left;
}

.exchange_tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 8px;
}

.tab_btn {
  border: 1px solid fade(@colorBlur, 35%);
  background: fade(@DarkColorBG, 80%);
  color: @colorText;
  border-radius: 4px;
  padding: 4px 8px;
  font-size: 11px;
  cursor: pointer;

  &.active {
    border-color: fade(@orange, 70%);
    background: fade(@orange, 20%);
  }
}

.exchange_meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  font-size: 12px;
  color: @colorBlur;
  margin-bottom: 8px;
}

.panel {
  background: @DarkColorBG;
  border-radius: 5px;
  padding: 8px;
}

.catalog_row,
.sell_row,
.history_row {
  border: 1px solid fade(@colorBlur, 25%);
  border-radius: 4px;
  padding: 8px;
  margin-bottom: 6px;
}

.row_label {
  font-size: 13px;
  font-weight: 700;
}

.row_meta {
  font-size: 11px;
  color: @colorBlur;
  margin-top: 2px;
}

.row_actions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 6px;
  align-items: center;
}

.qty_input,
.price_input {
  width: 56px;
  padding: 4px;
  border-radius: 3px;
  border: 1px solid fade(@colorBlur, 40%);
  background: @darkbg;
  color: @colorText;
  font-size: 12px;
}

.price_input {
  width: 72px;
}

.action_btn {
  border: 1px solid fade(@orange, 70%);
  background: fade(@orange, 20%);
  color: @colorText;
  border-radius: 4px;
  padding: 4px 10px;
  font-size: 11px;
  cursor: pointer;

  &:disabled {
    opacity: 0.5;
    cursor: default;
  }

  &.danger {
    border-color: fade(@NoWrite, 70%);
    background: fade(@NoWrite, 15%);
  }
}

.more_btn {
  margin-top: 8px;
  width: 100%;
  padding: 6px;
  border-radius: 4px;
  border: 1px solid fade(@colorBlur, 40%);
  background: fade(@darkbg, 90%);
  color: @colorText;
  cursor: pointer;
}

.empty {
  text-align: center;
  color: @colorBlur;
  font-size: 12px;
  padding: 16px 8px;
}

.msg {
  font-size: 12px;
  margin-bottom: 8px;
  &.error { color: @NoWrite; }
  &.ok { color: @YesWrite; }
}

.history_row {
  display: grid;
  grid-template-columns: 70px 1fr auto;
  gap: 4px 8px;
  align-items: center;
  font-size: 11px;
}

.hist_role.buy { color: @YesWrite; }
.hist_role.sell { color: @orange; }
.hist_date {
  grid-column: 1 / -1;
  color: @colorBlur;
  font-size: 10px;
}
</style>
