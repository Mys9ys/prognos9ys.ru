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
      <div class="category_tabs" v-if="catalogTabs.length">
        <button
          v-for="tab in catalogTabs"
          :key="'cat-' + tab.id"
          type="button"
          class="tab_btn category_tab_btn"
          :class="{ active: catalogCategoryTab === tab.id }"
          @click="setCatalogCategoryTab(tab.id)"
        >
          {{ tab.label }}
        </button>
      </div>
      <div class="catalog_list" v-if="catalogItems.length">
        <div v-for="group in catalogItems" :key="group.group_key" class="catalog_row">
          <div class="row_main">
            <div class="row_label">{{ group.label }}</div>
            <div class="row_meta">
              {{ group.qty_total }} шт. · {{ group.price_per_unit }} 🪙
              <span v-if="group.has_consignment" class="badge_consignment">есть комиссионка</span>
              · {{ group.listings_count }} {{ sellersLabel(group.listings_count) }}
            </div>
            <div class="offers_panel" v-if="expandedGroups[group.group_key]">
              <div
                v-for="offer in group.offers"
                :key="offer.listing_id"
                class="offer_row"
              >
                <span class="offer_seller">{{ offer.seller_name }}</span>
                <span class="offer_qty">{{ offer.qty_remaining }} шт.</span>
                <span v-if="offer.expires_at" class="offer_exp">до {{ offer.expires_at }}</span>
              </div>
            </div>
          </div>
          <div class="row_actions catalog_actions">
            <input
              v-model.number="buyQty[group.group_key]"
              type="number"
              min="1"
              :max="group.qty_total"
              class="qty_input"
            />
            <button
              type="button"
              class="action_btn expand_btn"
              :class="{ active: expandedGroups[group.group_key] }"
              @click="toggleOffers(group.group_key)"
            >
              {{ expandedGroups[group.group_key] ? '▲' : '▼' }}
            </button>
            <button type="button" class="action_btn" :disabled="busy" @click="buyGroup(group)">
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
      <div class="category_tabs" v-if="catalogTabs.length">
        <button
          v-for="tab in catalogTabs"
          :key="'sell-' + tab.id"
          type="button"
          class="tab_btn category_tab_btn"
          :class="{ active: sellCategoryTab === tab.id }"
          @click="sellCategoryTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>
      <div class="sell_list" v-if="filteredSellable.length">
        <div v-for="(item, index) in filteredSellable" :key="sellKey(item, index)" class="sell_row">
          <div class="row_label">{{ item.label }}</div>
          <div class="row_meta">
            В инвентаре: {{ item.available }} · номинал {{ item.nominal }}–{{ item.max_price }} 🪙
            · макс. {{ item.pallet_limit }}/лот
            <span v-if="item.consign_price">
              · комиссионка: {{ item.consign_price }} 🪙 (сразу {{ item.consign_instant_per_unit }}/шт.)
            </span>
          </div>
          <div class="row_actions">
            <input v-model.number="sellForm[itemKey(item)].qty" type="number" min="1" :max="item.available" class="qty_input" />
            <input v-model.number="sellForm[itemKey(item)].price" type="number" step="0.1" :min="item.nominal" :max="item.max_price" class="price_input" />
            <button type="button" class="action_btn" :disabled="busy" @click="createListing(item)">
              Выставить
            </button>
            <button type="button" class="action_btn consign_btn" :disabled="busy" @click="consignToBank(item)">
              В комиссионку
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
      catalogCategoryTab: 'all',
      sellCategoryTab: 'all',
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
      expandedGroups: {},
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
    catalogTabs() {
      return Array.isArray(this.state?.catalog_tabs) ? this.state.catalog_tabs : [];
    },
    filteredSellable() {
      const tab = this.sellCategoryTab;
      if (!tab || tab === 'all') {
        return this.sellable;
      }
      return this.sellable.filter((item) => (item.catalog_tab || '') === tab);
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
    catalogCategoryTab() {
      if (this.activeTab === 'catalog') {
        this.loadCatalog(true);
      }
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

    sellersLabel(count) {
      const n = Number(count || 0);
      const mod10 = n % 10;
      const mod100 = n % 100;
      if (mod10 === 1 && mod100 !== 11) {
        return 'продавец';
      }
      if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
        return 'продавца';
      }
      return 'продавцов';
    },

    toggleOffers(groupKey) {
      this.expandedGroups = {
        ...this.expandedGroups,
        [groupKey]: !this.expandedGroups[groupKey],
      };
    },

    setCatalogCategoryTab(tabId) {
      if (this.catalogCategoryTab === tabId) {
        return;
      }
      this.catalogCategoryTab = tabId;
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
          this.catalogPagination.limit,
          this.catalogCategoryTab
        );

        if (data?.status === 'ok') {
          const items = Array.isArray(data.items) ? data.items : [];
          this.catalogItems = reset ? items : [...this.catalogItems, ...items];
          this.catalogPagination = {
            ...this.catalogPagination,
            ...(data.pagination || {}),
          };
          items.forEach((group) => {
            if (!this.buyQty[group.group_key]) {
              this.buyQty[group.group_key] = 1;
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

    async buyGroup(group) {
      const qty = Number(this.buyQty[group.group_key] || 1);
      if (!qty || qty < 1) {
        return;
      }

      this.busy = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.exchange.buy(
          this.authData.token,
          group.kind,
          group.code,
          qty,
          group.category || '',
          group.event_id || 0,
          group.team_code || '',
          group.price_per_unit || 0
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

    async consignToBank(item) {
      const key = this.itemKey(item);
      const form = this.sellForm[key] || {};
      const qty = Number(form.qty || 0);

      if (!qty || qty < 1) {
        return;
      }

      if (!window.confirm(
        `Сдать ${qty} шт. в комиссионку банка? Вы получите ~80% цены сразу, отменить нельзя.`
      )) {
        return;
      }

      this.busy = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.exchange.consignToBank(
          this.authData.token,
          item.kind,
          item.code,
          qty,
          item.category || '',
          item.event_id || 0,
          item.team_code || ''
        );

        if (data?.status === 'ok') {
          const chunks = Array.isArray(data.chunks) ? data.chunks.length : 1;
          this.message = `Сдано в комиссионку: +${data.total_paid} 🪙`
            + (chunks > 1 ? ` (${chunks} лота)` : '');
          this.applyGame(data.game);
          await this.refreshState();
          await this.loadCatalog(true);
        }
      } catch (e) {
        this.error = e.message || 'Не удалось сдать в комиссионку';
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

.category_tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 8px;
}

.category_tab_btn {
  font-size: 10px;
  padding: 3px 6px;
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

.catalog_row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-start;
  justify-content: space-between;
}

.row_label {
  font-size: 13px;
  font-weight: 700;
  color: @colorText;
}

.sell_row .row_label {
  color: @colorText;
}

.catalog_row .row_main {
  flex: 1;
  min-width: 0;
}

.catalog_actions {
  flex-shrink: 0;
}

.offers_panel {
  margin-top: 6px;
  padding: 6px 8px;
  border-radius: 4px;
  background: fade(@darkbg, 80%);
  border: 1px solid fade(@colorBlur, 20%);
}

.offer_row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 10px;
  font-size: 11px;
  color: @colorBlur;
  padding: 2px 0;

  & + & {
    border-top: 1px solid fade(@colorBlur, 15%);
    margin-top: 4px;
    padding-top: 6px;
  }
}

.offer_seller {
  color: @colorText;
  font-weight: 600;
}

.expand_btn {
  min-width: 32px;
  padding: 4px 6px;

  &.active {
    border-color: fade(@orange, 70%);
    background: fade(@orange, 25%);
  }
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

  &.consign_btn {
    border-color: fade(@colorBlur, 60%);
    background: fade(@colorBlur, 15%);
  }
}

.badge_consignment {
  display: inline-block;
  margin-left: 4px;
  padding: 0 4px;
  border-radius: 3px;
  font-size: 10px;
  background: fade(@colorBlur, 25%);
  color: @colorText;
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
