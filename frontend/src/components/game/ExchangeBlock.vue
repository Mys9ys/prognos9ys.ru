<template>
  <div class="exchange_block">
    <div class="exchange_tabs">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="tab_btn"
        :class="{ active: activeTab === tab.id }"
        :disabled="loading || busy"
        @click="setActiveTab(tab.id)"
      >
        {{ tab.label }}
      </button>
    </div>

    <div class="exchange_meta" v-if="state">
      <span>🪙 {{ formatMoney(state.wallet_prognobaks) }}</span>
      <span>💎 {{ formatMoney(state.wallet_rublius) }}</span>
      <span>Лоты: {{ state.active_listings }}/{{ state.max_listings }}</span>
      <span>Комиссия: {{ state.commission_percent }}%</span>
    </div>

    <PreLoader v-if="loading" />

    <div class="msg error" v-if="error && !loading">{{ error }}</div>
    <div class="msg ok" v-if="message && !loading">{{ message }}</div>

    <!-- Каталог -->
    <div v-if="!loading && activeTab === 'catalog'" class="panel">
      <div class="category_tabs" v-if="catalogTabs.length">
        <button
          v-for="tab in catalogTabs"
          :key="'cat-' + tab.id"
          type="button"
          class="tab_btn category_tab_btn"
          :class="{ active: catalogCategoryTab === tab.id }"
          :disabled="loading || busy"
          @click="setCatalogCategoryTab(tab.id)"
        >
          {{ tab.label }}
        </button>
      </div>
      <div class="exchange_toolbar">
        <div class="exchange_search">
          <input
            v-model="catalogSearchQuery"
            type="search"
            class="search_input"
            placeholder="Поиск по названию..."
            autocomplete="off"
          />
        </div>
        <div class="exchange_sort">
          <span class="sort_label">Кол-во</span>
          <button
            type="button"
            class="sort_btn"
            :class="{ active: qtySort.catalog === 'desc' }"
            title="По убыванию"
            @click="setQtySort('catalog', 'desc')"
          >
            ↓
          </button>
          <button
            type="button"
            class="sort_btn"
            :class="{ active: qtySort.catalog === 'asc' }"
            title="По возрастанию"
            @click="setQtySort('catalog', 'asc')"
          >
            ↑
          </button>
        </div>
      </div>
      <div class="catalog_list" v-if="catalogItems.length">
        <div v-for="group in catalogItems" :key="group.group_key" class="catalog_row" :class="{ recipe_unlearned: isUnlearnedRecipe(group) }">
          <div class="row_thumb" aria-hidden="true">
            <img v-if="itemThumb(group).src" :src="itemThumb(group).src" alt="">
            <span v-else class="row_thumb_emoji">{{ itemThumb(group).emoji }}</span>
          </div>
          <div class="row_body">
            <div class="row_label" :title="group.label">
              {{ group.label }}
              <span v-if="isUnlearnedRecipe(group)" class="badge_unlearned">не изучен</span>
            </div>
            <div class="row_line row_price">
              {{ group.qty_total }} шт. · {{ group.price_per_unit }} {{ currencySymbol(group.currency) }}
              <span v-if="group.has_consignment" class="badge_consignment">комиссионка</span>
            </div>
            <div class="row_line row_sellers">
              {{ group.listings_count }} {{ sellersLabel(group.listings_count) }}
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
      <div class="empty" v-else-if="catalogSearchQuery.trim()">Ничего не найдено</div>
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
    <div v-if="!loading && activeTab === 'sell'" class="panel">
      <div class="category_tabs" v-if="catalogTabs.length">
        <button
          v-for="tab in catalogTabs"
          :key="'sell-' + tab.id"
          type="button"
          class="tab_btn category_tab_btn"
          :class="{ active: sellCategoryTab === tab.id }"
          :disabled="loading || busy"
          @click="sellCategoryTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>
      <div class="exchange_toolbar">
        <div class="exchange_search">
          <input
            v-model="sellSearchQuery"
            type="search"
            class="search_input"
            placeholder="Поиск по названию..."
            autocomplete="off"
          />
        </div>
        <div class="exchange_sort">
          <span class="sort_label">Кол-во</span>
          <button
            type="button"
            class="sort_btn"
            :class="{ active: qtySort.sell === 'desc' }"
            title="По убыванию"
            @click="setQtySort('sell', 'desc')"
          >
            ↓
          </button>
          <button
            type="button"
            class="sort_btn"
            :class="{ active: qtySort.sell === 'asc' }"
            title="По возрастанию"
            @click="setQtySort('sell', 'asc')"
          >
            ↑
          </button>
        </div>
      </div>
      <div v-if="sellCategoryTab === 'souvenir' && duplicatePlanTotal > 0" class="sell_bulk">
        <span class="sell_bulk_hint">Лишних дублей: {{ duplicatePlanTotal }} · по номиналу</span>
        <div class="sell_bulk_actions">
          <button type="button" class="action_btn" :disabled="busy" @click="bulkSellDuplicates('listing')">
            Продать дубли на биржу
          </button>
          <button type="button" class="action_btn consign_btn" :disabled="busy" @click="bulkSellDuplicates('consign')">
            В комиссионку
          </button>
        </div>
      </div>
      <div class="sell_list" v-if="sortedFilteredSellable.length">
        <div v-for="(item, index) in sortedFilteredSellable" :key="sellKey(item, index)" class="sell_row" :class="{ recipe_unlearned: isUnlearnedRecipe(item) }">
          <div class="row_thumb" aria-hidden="true">
            <img v-if="itemThumb(item).src" :src="itemThumb(item).src" alt="">
            <span v-else class="row_thumb_emoji">{{ itemThumb(item).emoji }}</span>
          </div>
          <div class="row_body">
            <div class="row_label" :title="item.label">
              {{ item.label }}
              <span v-if="isUnlearnedRecipe(item)" class="badge_unlearned">не изучен</span>
            </div>
            <div class="row_line">
              В инвентаре: {{ item.available }} · {{ item.nominal }}–{{ item.max_price }} {{ currencySymbol(item.currency) }}
            </div>
            <div class="row_line">
              макс. {{ item.pallet_limit }}/лот
              <span v-if="item.consign_price">
                · комисс. {{ item.consign_price }} {{ currencySymbol(item.currency) }}
              </span>
            </div>
          </div>
          <div class="row_actions sell_actions">
            <input v-model.number="sellForm[itemKey(item)].qty" type="number" min="1" :max="item.available" class="qty_input" />
            <div class="price_row">
              <input v-model.number="sellForm[itemKey(item)].price" type="number" step="0.1" :min="item.nominal" :max="item.max_price" class="price_input" />
              <div class="price_edge_col">
                <button type="button" class="price_edge_btn" :disabled="busy" @click="setSellPrice(item, 'min')">min</button>
                <button type="button" class="price_edge_btn" :disabled="busy" @click="setSellPrice(item, 'max')">max</button>
              </div>
            </div>
            <button
              v-if="!item.consign_only"
              type="button"
              class="action_btn"
              :disabled="busy"
              @click="createListing(item)"
            >
              Выставить
            </button>
            <button type="button" class="action_btn consign_btn" :disabled="busy" @click="consignToBank(item)">
              В комиссионку
            </button>
          </div>
        </div>
      </div>
      <div class="empty" v-else-if="sellSearchQuery.trim()">Ничего не найдено</div>
      <div class="empty" v-else>Нечего продавать — откройте сундуки, добывайте на фарме или получите награды</div>
    </div>

    <!-- Мои лоты -->
    <div v-if="!loading && activeTab === 'my'" class="panel">
      <div class="exchange_sort exchange_sort_standalone">
        <span class="sort_label">Кол-во</span>
        <button
          type="button"
          class="sort_btn"
          :class="{ active: qtySort.my === 'desc' }"
          title="По убыванию"
          @click="setQtySort('my', 'desc')"
        >
          ↓
        </button>
        <button
          type="button"
          class="sort_btn"
          :class="{ active: qtySort.my === 'asc' }"
          title="По возрастанию"
          @click="setQtySort('my', 'asc')"
        >
          ↑
        </button>
      </div>
      <div class="catalog_list" v-if="sortedMyListings.length">
        <div v-for="item in sortedMyListings" :key="item.id" class="catalog_row">
          <div class="row_thumb" aria-hidden="true">
            <img v-if="itemThumb(item).src" :src="itemThumb(item).src" alt="">
            <span v-else class="row_thumb_emoji">{{ itemThumb(item).emoji }}</span>
          </div>
          <div class="row_body">
            <div class="row_label" :title="item.label">{{ item.label }}</div>
            <div class="row_line">
              {{ item.qty_remaining }}/{{ item.qty_total }} · {{ item.price_per_unit }} {{ currencySymbol(item.currency) }}
            </div>
            <div class="row_line">до {{ item.expires_at }}</div>
          </div>
          <button type="button" class="action_btn danger my_cancel_btn" :disabled="busy" @click="cancelListing(item.id)">
            Снять
          </button>
        </div>
      </div>
      <div class="empty" v-else>Нет активных лотов</div>
    </div>

    <!-- Работы -->
    <div v-if="!loading && activeTab === 'labor'" class="panel">
      <div class="labor_hint" v-if="laborState">
        Цикл {{ laborState.iteration_minutes }} мин · 1–{{ laborState.max_cycles_per_claim }} циклов на смену ·
        комиссия 0%
      </div>

      <div class="labor_create" v-if="laborState">
        <div class="labor_section_title">Разместить заказ</div>
        <div class="row_actions labor_create_form">
          <select v-model="laborForm.professionCode" class="labor_select">
            <option value="">Профессия</option>
            <option
              v-for="prof in laborState.professions"
              :key="'post-' + prof.code"
              :value="prof.code"
            >
              {{ prof.label }}
              <template v-if="prof.input_label"> ({{ prof.input_label }} → {{ prof.output_label }})</template>
              <template v-else> ({{ prof.output_label }})</template>
            </option>
          </select>
          <input
            v-model.number="laborForm.iterations"
            type="number"
            min="1"
            class="qty_input"
            placeholder="Циклов"
          />
          <input
            v-model.number="laborForm.payPerCycle"
            type="number"
            step="0.1"
            min="0.1"
            class="price_input"
            placeholder="🪙/цикл"
          />
          <button type="button" class="action_btn" :disabled="busy" @click="createLaborOrder">
            Разместить
          </button>
        </div>
        <div class="labor_create_note" v-if="selectedLaborProfession">
          <span v-if="selectedLaborProfession.input_label">
            Эскроу: {{ laborForm.iterations || 0 }} {{ selectedLaborProfession.input_label }} +
          </span>
          {{ laborPayTotal }} 🪙 оплаты труда
        </div>
      </div>

      <div class="labor_section_title">Мои заказы</div>
      <div class="exchange_sort exchange_sort_standalone">
        <span class="sort_label">Циклов</span>
        <button
          type="button"
          class="sort_btn"
          :class="{ active: qtySort.myLabor === 'desc' }"
          title="По убыванию"
          @click="setQtySort('myLabor', 'desc')"
        >
          ↓
        </button>
        <button
          type="button"
          class="sort_btn"
          :class="{ active: qtySort.myLabor === 'asc' }"
          title="По возрастанию"
          @click="setQtySort('myLabor', 'asc')"
        >
          ↑
        </button>
      </div>
      <div class="catalog_list" v-if="sortedMyLaborOrders.length">
        <div v-for="order in sortedMyLaborOrders" :key="'my-labor-' + order.id" class="catalog_row">
          <div class="row_main">
            <div class="row_label">{{ order.profession_label }} → {{ order.output_label }}</div>
            <div class="row_meta">
              {{ order.iterations_done }}/{{ order.iterations_total }} циклов ·
              {{ order.pay_per_cycle }} 🪙/цикл
              <span v-if="order.has_active_worker" class="badge_consignment">в работе</span>
              <span v-else-if="order.status === 'open'"> · осталось {{ order.iterations_remaining }}</span>
              · {{ orderStatusLabel(order.status) }}
            </div>
          </div>
          <div class="row_actions" v-if="order.can_workshop || order.can_claim">
            <input
              v-model.number="laborClaimQty[order.id]"
              type="number"
              min="1"
              :max="laborMaxClaim(order)"
              class="qty_input"
              title="Циклов за смену"
            />
            <button
              v-if="order.can_workshop"
              type="button"
              class="action_btn"
              :disabled="busy"
              @click="startLaborWorkshop(order.id)"
            >
              Мастерская
            </button>
            <button
              v-if="order.can_claim"
              type="button"
              class="action_btn"
              :disabled="busy"
              @click="claimLaborOrder(order.id)"
            >
              Взять
            </button>
          </div>
          <div class="row_actions" v-else-if="order.can_cancel">
            <button
              type="button"
              class="action_btn danger"
              :disabled="busy"
              @click="cancelLaborOrder(order.id)"
            >
              Снять
            </button>
          </div>
        </div>
      </div>
      <div class="empty" v-else>У вас нет заказов на работу</div>

      <div class="labor_section_title">Открытые заказы</div>
      <div class="exchange_sort exchange_sort_standalone">
        <span class="sort_label">Циклов</span>
        <button
          type="button"
          class="sort_btn"
          :class="{ active: qtySort.labor === 'desc' }"
          title="По убыванию"
          @click="setQtySort('labor', 'desc')"
        >
          ↓
        </button>
        <button
          type="button"
          class="sort_btn"
          :class="{ active: qtySort.labor === 'asc' }"
          title="По возрастанию"
          @click="setQtySort('labor', 'asc')"
        >
          ↑
        </button>
      </div>
      <div class="catalog_list" v-if="sortedLaborOrders.length">
        <div v-for="order in sortedLaborOrders" :key="'labor-' + order.id" class="catalog_row">
          <div class="row_main">
            <div class="row_label">{{ order.profession_label }} → {{ order.output_label }}</div>
            <div class="row_meta">
              {{ order.poster_name }}<span v-if="order.is_treasury" class="badge_consignment">казна</span> · {{ order.iterations_remaining }} цикл.
              · {{ order.pay_per_cycle }} 🪙/цикл
              <span v-if="order.input_label"> · сырьё в эскроу</span>
            </div>
          </div>
          <div class="row_actions" v-if="order.can_claim">
            <input
              v-model.number="laborClaimQty[order.id]"
              type="number"
              min="1"
              :max="laborMaxClaim(order)"
              class="qty_input"
              title="Циклов за смену"
            />
            <button
              type="button"
              class="action_btn"
              :disabled="busy"
              @click="claimLaborOrder(order.id)"
            >
              Взять
            </button>
          </div>
        </div>
      </div>
      <div class="empty" v-else>Открытых заказов нет</div>
      <button
        v-if="laborPagination.has_more"
        type="button"
        class="more_btn"
        :disabled="loading"
        @click="loadMoreLaborOrders"
      >
        Ещё
      </button>
    </div>

    <!-- Госстройка -->
    <div v-if="!loading && activeTab === 'citybuild'" class="panel">
      <div class="labor_hint">
        Сдача крафтовых компонентов в стройку городов ЧМ-26. Оплата по номиналу (+13% за монтаж), комиссия 0%.
        Материалы списываются из инвентаря.
      </div>

      <div class="citybuild_list" v-if="cityBuildOrders.length">
        <div
          v-for="order in cityBuildOrders"
          :key="order.project_id + '-' + order.recipe_code"
          class="citybuild_order"
        >
          <div class="citybuild_order_head">
            <div>
              <div class="citybuild_city">{{ order.city_name }}</div>
              <div class="citybuild_building">{{ order.label }} · {{ order.progress_pct }}%</div>
            </div>
            <span class="citybuild_nominal">~{{ order.nominal_total }} 🪙</span>
          </div>

          <div
            v-for="item in order.remaining_items"
            :key="order.project_id + '-' + item.code"
            class="catalog_row citybuild_component_row"
          >
            <div class="row_body">
              <div class="row_label">{{ item.label }}</div>
              <div class="row_line">
                нужно ещё {{ item.qty }}
                <span v-if="item.nominal_per_unit" class="citybuild_unit_payout">
                  · получите {{ item.nominal_per_unit }} 🪙/шт
                </span>
                <span v-if="item.user_have > 0"> · у вас {{ item.user_have }}</span>
              </div>
              <div class="row_line recipe_line" v-if="item.recipe_label">
                крафт: {{ item.recipe_label }}
                <span v-if="item.profession_label"> ({{ item.profession_label }})</span>
              </div>
              <div class="row_line recipe_line warn" v-else-if="item.code">
                рецепт крафта не найден для «{{ item.label }}»
              </div>
            </div>
            <div class="row_actions">
              <input
                v-model.number="cityBuildQty[buildDonateKey(order, item.code)]"
                type="number"
                min="1"
                :max="cityBuildDonateMax(order, item)"
                class="qty_input"
                :disabled="!canSubmitCityBuild(order, item)"
              />
              <span
                v-if="item.nominal_per_unit"
                class="citybuild_payout"
                :class="{ citybuild_payout_muted: !canSubmitCityBuild(order, item) }"
              >
                +{{ cityBuildDonatePayout(order, item) }} 🪙
              </span>
              <button
                type="button"
                class="action_btn"
                :class="{ disabled: !canSubmitCityBuild(order, item) }"
                :disabled="busy || !canSubmitCityBuild(order, item)"
                @click="submitCityBuild(order, item.code)"
              >
                Сдать
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="empty" v-else-if="!loading">Нет активных госзаказов на стройку</div>
    </div>

    <!-- История -->
    <div v-if="!loading && activeTab === 'history'" class="panel">
      <div class="exchange_sort exchange_sort_standalone">
        <span class="sort_label">Кол-во</span>
        <button
          type="button"
          class="sort_btn"
          :class="{ active: qtySort.history === 'desc' }"
          title="По убыванию"
          @click="setQtySort('history', 'desc')"
        >
          ↓
        </button>
        <button
          type="button"
          class="sort_btn"
          :class="{ active: qtySort.history === 'asc' }"
          title="По возрастанию"
          @click="setQtySort('history', 'asc')"
        >
          ↑
        </button>
      </div>
      <div class="history_list" v-if="sortedHistoryItems.length">
        <div v-for="item in sortedHistoryItems" :key="item.id" class="history_row">
          <span class="hist_role" :class="item.role">{{ item.role === 'buy' ? 'Покупка' : 'Продажа' }}</span>
          <span class="hist_label">{{ item.label }} ×{{ item.qty }}</span>
          <div class="hist_amount">
            <span class="hist_total">{{ historyAmountMain(item) }} {{ currencySymbol(item.currency) }}</span>
            <span v-if="item.role === 'sell'" class="hist_net_caption">на руки</span>
            <span v-if="historyAmountHint(item)" class="hist_hint">{{ historyAmountHint(item) }}</span>
          </div>
          <span class="hist_date">{{ item.created_at }}</span>
        </div>
      </div>
      <div class="empty" v-else>Сделок пока нет</div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapMutations, mapState } from 'vuex';
import PreLoader from '@/components/main/PreLoader.vue';
import { apiActions } from '@/api/bitrixClient';
import { getExchangeItemThumb } from '@/config/exchangeItemIcons';

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
        { id: 'labor', label: 'Работы' },
        { id: 'citybuild', label: 'Госстройка' },
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
      laborState: null,
      laborOrders: [],
      myLaborOrders: [],
      laborPagination: { offset: 0, limit: 25, has_more: false },
      laborForm: {
        professionCode: '',
        iterations: 6,
        payPerCycle: 2,
      },
      laborClaimQty: {},
      cityBuildOrders: [],
      cityBuildQty: {},
      duplicatePlanTotal: 0,
      catalogSearchQuery: '',
      sellSearchQuery: '',
      catalogSearchTimer: null,
      catalogLoadSeq: 0,
      panelLoadSeq: 0,
      qtySort: {
        catalog: '',
        sell: '',
        my: '',
        history: '',
        labor: '',
        myLabor: '',
      },
    };
  },
  computed: {
    ...mapState({
      authData: (state) => state.auth.authData,
    }),
    selectedLaborProfession() {
      const code = this.laborForm.professionCode;
      const list = Array.isArray(this.laborState?.professions) ? this.laborState.professions : [];
      return list.find((item) => item.code === code) || null;
    },
    laborPayTotal() {
      const iterations = Number(this.laborForm.iterations) || 0;
      const pay = Number(this.laborForm.payPerCycle) || 0;
      return (iterations * pay).toFixed(1).replace(/\.0$/, '');
    },
    sellable() {
      return Array.isArray(this.state?.sellable) ? this.state.sellable : [];
    },
    catalogTabs() {
      return Array.isArray(this.state?.catalog_tabs) ? this.state.catalog_tabs : [];
    },
    filteredSellable() {
      const tab = this.sellCategoryTab;
      let items = this.sellable;
      if (tab && tab !== 'all') {
        items = items.filter((item) => (item.catalog_tab || '') === tab);
      }
      const query = this.sellSearchQuery.trim().toLowerCase();
      if (!query) {
        return items;
      }
      return items.filter((item) => String(item.label || '').toLowerCase().includes(query));
    },
    sortedFilteredSellable() {
      return this.sortByQty(this.filteredSellable, (item) => item.available, this.qtySort.sell);
    },
    sortedMyListings() {
      return this.sortByQty(this.myListings, (item) => item.qty_remaining, this.qtySort.my);
    },
    sortedHistoryItems() {
      return this.sortByQty(this.historyItems, (item) => item.qty, this.qtySort.history);
    },
    sortedMyLaborOrders() {
      return this.sortByQty(
        this.myLaborOrders,
        (order) => order.iterations_remaining ?? order.iterations_total,
        this.qtySort.myLabor
      );
    },
    sortedLaborOrders() {
      return this.sortByQty(
        this.laborOrders,
        (order) => order.iterations_remaining,
        this.qtySort.labor
      );
    },
  },
  watch: {
    activeTab(tab) {
      this.loadPanelForTab(tab);
    },
    sellCategoryTab(tab) {
      if (this.activeTab === 'sell' && tab === 'souvenir') {
        this.refreshDuplicatePlan();
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
    catalogSearchQuery() {
      if (this.activeTab !== 'catalog') {
        return;
      }
      if (this.catalogSearchTimer) {
        clearTimeout(this.catalogSearchTimer);
      }
      this.catalogSearchTimer = setTimeout(() => {
        this.loadCatalog(true);
      }, 300);
    },
  },
  beforeUnmount() {
    if (this.catalogSearchTimer) {
      clearTimeout(this.catalogSearchTimer);
    }
  },
  created() {
    this.bootstrap();
  },
  methods: {
    ...mapActions({
      refreshGameInfo: 'auth/refreshGameInfo',
    }),
    ...mapMutations({
      setUserInfo: 'auth/setUserInfo',
    }),

    formatMoney(value) {
      return Number(value ?? 0).toFixed(1).replace(/\.0$/, '');
    },
    currencySymbol(currency) {
      return currency === 'rublius' ? '💎' : '🪙';
    },

    setSellPrice(item, edge) {
      const key = this.itemKey(item);
      const form = this.sellForm[key];
      if (!form) {
        return;
      }
      form.price = edge === 'max' ? Number(item.max_price) : Number(item.nominal);
    },

    sortByQty(items, getQty, direction) {
      if (!direction || !Array.isArray(items) || !items.length) {
        return items;
      }
      const sorted = [...items];
      const mult = direction === 'asc' ? 1 : -1;
      sorted.sort((a, b) => {
        const qtyCmp = (Number(getQty(a)) || 0) - (Number(getQty(b)) || 0);
        if (qtyCmp !== 0) {
          return mult * qtyCmp;
        }
        return String(a.label || '').localeCompare(String(b.label || ''), 'ru');
      });
      return sorted;
    },

    setQtySort(tab, direction) {
      const current = this.qtySort[tab] || '';
      this.qtySort = {
        ...this.qtySort,
        [tab]: current === direction ? '' : direction,
      };
      if (tab === 'catalog') {
        this.loadCatalog(true);
      }
    },

    historyAmountMain(item) {
      if (item?.role === 'sell') {
        const net = Number(item.seller_net ?? 0);
        if (net > 0) {
          return this.formatMoney(net);
        }
      }
      return this.formatMoney(item?.total);
    },

    historyAmountHint(item) {
      if (item?.role !== 'sell') {
        return '';
      }
      const commission = Number(item.commission ?? 0);
      if (commission <= 0) {
        return '';
      }
      return `сумма ${this.formatMoney(item.total)} − ${this.formatMoney(commission)} комиссия`;
    },

    itemKey(item) {
      return [item.kind, item.code, item.category, item.event_id, item.team_code].join(':');
    },

    sellKey(item, index) {
      return this.itemKey(item) + ':' + index;
    },

    itemThumb(item) {
      return getExchangeItemThumb(item);
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
      if (this.catalogCategoryTab === tabId || this.loading) {
        return;
      }
      this.catalogCategoryTab = tabId;
    },

    setActiveTab(tabId) {
      if (this.activeTab === tabId || this.loading) {
        return;
      }
      this.activeTab = tabId;
    },

    isUnlearnedRecipe(item) {
      if (!item || item.recipe_learned !== false) {
        return false;
      }

      const category = String(item.category || '');
      const tab = String(item.catalog_tab || '');

      return category === 'recipe' || tab === 'recipe';
    },

    async loadPanelForTab(tab) {
      const requestId = ++this.panelLoadSeq;

      if (tab === 'catalog') {
        await this.loadCatalog(true, requestId);
        return;
      }

      this.loading = true;
      this.error = '';

      try {
        if (tab === 'sell') {
          await this.refreshState();
          await this.refreshDuplicatePlan();
        } else if (tab === 'my') {
          await this.loadMyListings();
        } else if (tab === 'history') {
          await this.loadHistory();
        } else if (tab === 'labor') {
          await this.loadLabor(true, requestId);
          return;
        } else if (tab === 'citybuild') {
          await this.loadCityBuildOrders();
        }
      } catch (e) {
        this.error = e.message || 'Ошибка загрузки';
      } finally {
        if (requestId === this.panelLoadSeq) {
          this.loading = false;
        }
      }
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

    async loadCatalog(reset = false, panelRequestId = 0) {
      if (!this.authData?.token) {
        return;
      }

      const requestId = ++this.catalogLoadSeq;
      const tabAtStart = this.catalogCategoryTab;

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
          this.catalogCategoryTab,
          this.catalogSearchQuery.trim(),
          this.qtySort.catalog
        );

        if (requestId !== this.catalogLoadSeq || tabAtStart !== this.catalogCategoryTab) {
          return;
        }
        if (panelRequestId > 0 && panelRequestId !== this.panelLoadSeq) {
          return;
        }

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
        if (requestId === this.catalogLoadSeq) {
          this.error = e.message || 'Ошибка каталога';
        }
      } finally {
        if (requestId === this.catalogLoadSeq
          && (panelRequestId <= 0 || panelRequestId === this.panelLoadSeq)) {
          this.loading = false;
        }
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

    orderStatusLabel(status) {
      if (status === 'completed') {
        return 'выполнен';
      }
      if (status === 'cancelled') {
        return 'снят';
      }
      return 'открыт';
    },

    laborMaxClaim(order) {
      const maxPerClaim = Number(this.laborState?.max_cycles_per_claim) || 5;
      const remaining = Number(order?.max_claim_cycles ?? order?.iterations_remaining ?? maxPerClaim);
      return Math.max(1, Math.min(maxPerClaim, remaining));
    },

    ensureLaborClaimQty(orders) {
      const qty = { ...this.laborClaimQty };
      (orders || []).forEach((order) => {
        const id = order.id;
        const max = this.laborMaxClaim(order);
        if (!qty[id] || qty[id] < 1 || qty[id] > max) {
          qty[id] = max;
        }
      });
      this.laborClaimQty = qty;
    },

    resolveLaborClaimIterations(orderId) {
      const order = [...this.laborOrders, ...this.myLaborOrders].find((item) => item.id === orderId);
      const max = order ? this.laborMaxClaim(order) : 5;
      const raw = Number(this.laborClaimQty[orderId]) || max;
      return Math.max(1, Math.min(max, raw));
    },

    async loadLabor(reset = false, panelRequestId = 0) {
      if (!this.authData?.token) {
        return;
      }

      const requestId = panelRequestId > 0 ? panelRequestId : ++this.panelLoadSeq;

      if (reset) {
        this.laborPagination.offset = 0;
        this.laborOrders = [];
      }

      this.loading = true;
      this.error = '';

      try {
        const [stateData, myData, ordersData] = await Promise.all([
          apiActions.exchange.getLaborState(this.authData.token),
          apiActions.exchange.getMyLaborOrders(this.authData.token),
          apiActions.exchange.getLaborOrders(
            this.authData.token,
            this.laborPagination.offset,
            this.laborPagination.limit
          ),
        ]);

        if (requestId !== this.panelLoadSeq) {
          return;
        }

        if (stateData?.status === 'ok' && stateData.labor) {
          this.laborState = stateData.labor;
          if (!this.laborForm.payPerCycle && stateData.labor.default_pay_per_cycle) {
            this.laborForm.payPerCycle = stateData.labor.default_pay_per_cycle;
          }
        }

        if (myData?.status === 'ok') {
          this.myLaborOrders = Array.isArray(myData.items) ? myData.items : [];
          this.ensureLaborClaimQty(this.myLaborOrders);
        }

        if (ordersData?.status === 'ok') {
          const items = Array.isArray(ordersData.items) ? ordersData.items : [];
          this.laborOrders = reset ? items : [...this.laborOrders, ...items];
          this.ensureLaborClaimQty(this.laborOrders);
          this.laborPagination = {
            ...this.laborPagination,
            ...(ordersData.pagination || {}),
          };
        }
      } catch (e) {
        if (requestId === this.panelLoadSeq) {
          this.error = e.message || 'Ошибка загрузки работ';
        }
      } finally {
        if (requestId === this.panelLoadSeq
          && (panelRequestId <= 0 || panelRequestId === requestId)) {
          this.loading = false;
        }
      }
    },

    loadMoreLaborOrders() {
      this.laborPagination.offset += this.laborPagination.limit;
      this.loadLabor(false);
    },

    buildDonateKey(order, componentCode) {
      return `${order.city_slug}:${order.recipe_code}:${componentCode}`;
    },

    cityBuildDonateMax(order, item) {
      const need = Number(item?.qty) || 1;
      const have = Number(item?.user_have) || 0;
      if (have <= 0) {
        return need;
      }

      return Math.min(need, have);
    },

    cityBuildDonatePayout(order, item) {
      const perUnit = Number(item?.nominal_per_unit) || 0;
      if (perUnit <= 0) {
        return 0;
      }

      const key = this.buildDonateKey(order, item.code);
      const maxQty = this.cityBuildDonateMax(order, item);
      const qty = Math.max(1, Math.min(maxQty, Number(this.cityBuildQty[key]) || 1));

      return Math.round(qty * perUnit * 10) / 10;
    },

    canSubmitCityBuild(order, item) {
      const have = Number(item?.user_have) || 0;
      if (have < 1) {
        return false;
      }

      const key = this.buildDonateKey(order, item.code);
      const need = Number(item?.qty) || 1;
      const qty = Math.max(1, Number(this.cityBuildQty[key]) || 1);

      return qty <= have && qty <= need;
    },

    ensureCityBuildQty(orders) {
      const qty = { ...this.cityBuildQty };
      orders.forEach((order) => {
        (order.remaining_items || []).forEach((item) => {
          const key = this.buildDonateKey(order, item.code);
          if (!qty[key]) {
            qty[key] = 1;
          }
        });
      });
      this.cityBuildQty = qty;
    },

    async loadCityBuildOrders() {
      if (!this.authData?.token) {
        return;
      }

      this.loading = true;
      this.error = '';

      try {
        const data = await apiActions.exchange.getCityBuildOrders(this.authData.token);
        if (data?.status === 'ok') {
          this.cityBuildOrders = Array.isArray(data.orders) ? data.orders : [];
          this.ensureCityBuildQty(this.cityBuildOrders);
        }
      } catch (e) {
        this.error = e.message || 'Ошибка загрузки госстройки';
      } finally {
        this.loading = false;
      }
    },

    async submitCityBuild(order, componentCode) {
      const key = this.buildDonateKey(order, componentCode);
      const remainingItem = (order.remaining_items || []).find((item) => item.code === componentCode);
      if (!remainingItem || !this.canSubmitCityBuild(order, remainingItem)) {
        return;
      }

      const maxQty = this.cityBuildDonateMax(order, remainingItem);
      const qty = Math.max(1, Math.min(maxQty, Number(this.cityBuildQty[key]) || 1));

      this.busy = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.exchange.submitCityBuildComponent(
          this.authData.token,
          order.city_slug,
          order.recipe_code,
          componentCode,
          qty
        );

        if (data?.status === 'ok') {
          this.cityBuildOrders = Array.isArray(data.orders) ? data.orders : this.cityBuildOrders;
          this.ensureCityBuildQty(this.cityBuildOrders);
          if (data.game) {
            this.setUserInfo({ game: data.game });
          } else {
            await this.refreshGameInfo();
          }
          const label = data.component_label || componentCode;
          let msg = `Сдано: ${label} ×${data.donated_qty || qty}`;
          if (data.paid_total > 0) {
            msg += ` · +${data.paid_total} 🪙`;
          }
          if (data.building_complete) {
            msg += ` · ${order.label} готово`;
          }
          if (data.city_opened) {
            msg += ' · город открыт на карте';
          }
          this.message = msg;
        }
      } catch (e) {
        this.error = e.message || 'Не удалось сдать компонент';
      } finally {
        this.busy = false;
      }
    },

    async createLaborOrder() {
      const professionCode = (this.laborForm.professionCode || '').trim();
      const iterations = Number(this.laborForm.iterations) || 0;
      const payPerCycle = Number(this.laborForm.payPerCycle) || 0;

      if (!professionCode || iterations < 1 || payPerCycle <= 0) {
        this.error = 'Заполните профессию, циклы и оплату';
        return;
      }

      this.busy = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.exchange.createLaborOrder(
          this.authData.token,
          professionCode,
          iterations,
          payPerCycle
        );

        if (data?.status === 'ok') {
          this.message = 'Заказ размещён';
          this.applyGame(data.game);
          await this.refreshState();
          await this.loadLabor(true);
        }
      } catch (e) {
        this.error = e.message || 'Не удалось разместить заказ';
      } finally {
        this.busy = false;
      }
    },

    async cancelLaborOrder(orderId) {
      this.busy = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.exchange.cancelLaborOrder(this.authData.token, orderId);
        if (data?.status === 'ok') {
          this.message = 'Заказ снят, эскроу возвращено';
          this.applyGame(data.game);
          await this.refreshState();
          await this.loadLabor(true);
        }
      } catch (e) {
        this.error = e.message || 'Не удалось снять заказ';
      } finally {
        this.busy = false;
      }
    },

    async claimLaborOrder(orderId) {
      const iterations = this.resolveLaborClaimIterations(orderId);
      this.busy = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.exchange.claimLaborOrder(
          this.authData.token,
          orderId,
          iterations
        );
        if (data?.status === 'ok') {
          this.message = `Смена на ${iterations} цикл. — вкладка «Работа»`;
          this.applyGame(data.game);
          await this.loadLabor(true);
        }
      } catch (e) {
        this.error = e.message || 'Не удалось взять заказ';
      } finally {
        this.busy = false;
      }
    },

    async startLaborWorkshop(orderId) {
      const iterations = this.resolveLaborClaimIterations(orderId);
      this.busy = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.exchange.startLaborWorkshop(
          this.authData.token,
          orderId,
          iterations
        );
        if (data?.status === 'ok') {
          this.message = `Мастерская: ${iterations} цикл. — вкладка «Работа»`;
          this.applyGame(data.game);
          await this.loadLabor(true);
        }
      } catch (e) {
        this.error = e.message || 'Не удалось начать смену';
      } finally {
        this.busy = false;
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
          this.message = `Куплено ${data.bought_qty} шт. за ${data.total_spent} ${this.currencySymbol(data.currency)}`;
          this.applyGame(data.game);
          this.patchExchangeWalletFromGame(data.game);
          this.patchCatalogAfterBuy(group, data.bought_qty);
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
          this.message = `Куплено ${data.bought_qty} шт. за ${data.total_spent} ${this.currencySymbol(data.currency)}`;
          this.applyGame(data.game);
          this.patchExchangeWalletFromGame(data.game);
          this.patchCatalogAfterBuy(item, data.bought_qty);
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
          await this.syncGameAfterWalletMutation(data.game);
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
          const paidCurrency = data.currency || item.currency || 'prognobaks';
          this.message = `Сдано в комиссионку: +${data.total_paid} ${this.currencySymbol(paidCurrency)}`
            + (chunks > 1 ? ` (${chunks} лота)` : '');
          await this.syncGameAfterWalletMutation(data.game);
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
      const prev = this.$store.state.auth.userInfo?.game_info || {};
      this.setUserInfo({
        ...this.$store.state.auth.userInfo,
        game_info: { ...prev, ...game },
      });
    },

    patchExchangeWalletFromGame(game) {
      const prognobaks = Number(game?.wallet?.prognobaks);
      const rublius = Number(game?.wallet?.rublius);
      if (!Number.isFinite(prognobaks) || !Number.isFinite(rublius) || !this.state) {
        return;
      }
      this.state = { ...this.state, wallet_prognobaks: prognobaks, wallet_rublius: rublius };
    },

    patchCatalogAfterBuy(group, boughtQty) {
      const qty = Number(boughtQty) || 0;
      if (!qty || !group?.group_key) {
        return;
      }

      const idx = this.catalogItems.findIndex((item) => item.group_key === group.group_key);
      if (idx < 0) {
        return;
      }

      const current = this.catalogItems[idx];
      const nextQty = Math.max(0, (Number(current.qty_total) || 0) - qty);
      if (nextQty <= 0) {
        this.catalogItems = this.catalogItems.filter((item) => item.group_key !== group.group_key);
        return;
      }

      const items = [...this.catalogItems];
      items[idx] = { ...current, qty_total: nextQty };
      this.catalogItems = items;
    },

    async syncGameAfterWalletMutation(game) {
      this.applyGame(game);
      await this.refreshGameInfo({ refresh: true });
    },

    async refreshDuplicatePlan() {
      if (!this.authData?.token) {
        this.duplicatePlanTotal = 0;
        return;
      }

      try {
        const data = await apiActions.exchange.getDuplicateSouvenirPlan(this.authData.token);
        this.duplicatePlanTotal = Number(data?.total_qty) || 0;
      } catch (e) {
        this.duplicatePlanTotal = 0;
      }
    },

    async bulkSellDuplicates(mode) {
      if (!this.authData?.token || this.busy) {
        return;
      }

      if (mode === 'consign' && !window.confirm(
        `Сдать ${this.duplicatePlanTotal} дублей в комиссионку? Вы получите ~80% цены сразу, отменить нельзя.`
      )) {
        return;
      }

      this.busy = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.exchange.bulkSellDuplicateSouvenirs(this.authData.token, mode);
        if (data?.status === 'ok') {
          const sold = Number(data.sold_qty) || 0;
          if (sold > 0) {
            this.message = mode === 'consign'
              ? `Сдано в комиссионку: ${sold} шт.`
              : `Выставлено на биржу: ${sold} шт.`;
          } else {
            const lines = Array.isArray(data.lines) ? data.lines : [];
            const first = lines.find((line) => line.status === 'fail') || lines[0];
            this.error = first?.text || 'Нет лишних дублей для продажи';
          }
          this.applyGame(data.game);
          await this.refreshState();
          await this.refreshDuplicatePlan();
          if (mode === 'listing' && sold > 0) {
            this.activeTab = 'my';
            await this.loadMyListings();
          }
        }
      } catch (e) {
        this.error = e.message || 'Не удалось продать дубли';
      } finally {
        this.busy = false;
      }
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

.exchange_toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.exchange_search {
  flex: 1;
  min-width: 140px;
  margin-bottom: 0;
}

.exchange_sort {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.exchange_sort_standalone {
  margin-bottom: 8px;
}

.sort_label {
  font-size: 11px;
  color: @colorBlur;
  white-space: nowrap;
}

.sort_btn {
  border: 1px solid fade(@colorBlur, 35%);
  background: fade(@DarkColorBG, 80%);
  color: @colorText;
  border-radius: 4px;
  min-width: 28px;
  padding: 4px 6px;
  font-size: 12px;
  line-height: 1;
  cursor: pointer;

  &.active {
    border-color: fade(@orange, 70%);
    background: fade(@orange, 20%);
  }
}

.search_input {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid fade(@colorBlur, 35%);
  background: fade(@DarkColorBG, 90%);
  color: @colorText;
  border-radius: 4px;
  padding: 6px 8px;
  font-size: 12px;

  &::placeholder {
    color: fade(@colorText, 45%);
  }
}

.sell_bulk {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
  padding: 8px;
  border-radius: 4px;
  background: fade(@orange, 12%);
  border: 1px solid fade(@orange, 35%);
}

.sell_bulk_hint {
  font-size: 12px;
  color: @colorText;
}

.sell_bulk_actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
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

  &:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }
}

.exchange_meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  font-size: 12px;
  color: @colorText;
  margin-bottom: 8px;
}

.panel {
  background: @DarkColorBG;
  border-radius: 5px;
  padding: 8px;
  color: @colorText;
}

.catalog_row,
.sell_row {
  &.recipe_unlearned {
    border: 1px solid fade(@orange, 55%);
    background: fade(@orange, 8%);
    border-radius: 4px;
  }
}

.badge_unlearned {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 5px;
  font-size: 10px;
  line-height: 1.3;
  border-radius: 3px;
  color: lighten(@orange, 10%);
  background: fade(@orange, 18%);
  vertical-align: middle;
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
  flex-wrap: nowrap;
  gap: 8px;
  align-items: flex-start;
}

.sell_row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-start;
}

.row_thumb {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  background: fade(@darkbg, 70%);
  border: 1px solid fade(@colorBlur, 18%);
  overflow: hidden;

  img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
  }
}

.row_thumb_emoji {
  font-size: 22px;
  line-height: 1;
}

.row_body {
  flex: 1;
  min-width: 120px;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.row_line {
  font-size: 11px;
  color: @colorBlur;
  line-height: 1.25;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.row_label {
  font-size: 13px;
  font-weight: 700;
  color: @colorText;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sell_row .row_label {
  color: @colorText;
}

.catalog_row .row_main {
  flex: 1;
  min-width: 0;
}

.catalog_actions,
.sell_actions {
  flex-shrink: 0;
  margin-top: 0;
  margin-left: auto;
  align-self: center;
}

.my_cancel_btn {
  align-self: center;
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
  box-sizing: border-box;
}

.price_row {
  display: inline-flex;
  align-items: stretch;
  gap: 2px;
}

.price_edge_col {
  display: flex;
  flex-direction: column;
  gap: 1px;
  flex-shrink: 0;
}

.price_edge_btn {
  flex: 1;
  border: 1px solid fade(@colorBlur, 35%);
  background: fade(@darkbg, 90%);
  color: @colorBlur;
  font-size: 9px;
  line-height: 1;
  padding: 0 5px;
  border-radius: 2px;
  cursor: pointer;
  min-height: 0;
}

.price_edge_btn:hover:not(:disabled) {
  color: @colorText;
  border-color: fade(@orange, 50%);
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
  grid-template-columns: 72px minmax(0, 1fr) auto;
  gap: 4px 10px;
  align-items: start;
  font-size: 12px;
}

.hist_role {
  font-weight: 700;
  line-height: 1.2;
}

.hist_role.buy { color: @YesWrite; }
.hist_role.sell { color: @orange; }

.hist_label {
  color: @colorText;
  font-weight: 600;
  line-height: 1.3;
}

.hist_amount {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  text-align: right;
}

.hist_total {
  color: @YesWrite2;
  font-weight: 700;
  font-size: 13px;
  line-height: 1.2;
  white-space: nowrap;
}

.hist_net_caption {
  color: @orange;
  font-size: 10px;
  font-weight: 600;
  line-height: 1;
}

.hist_hint {
  color: @colorBlur;
  font-size: 10px;
  line-height: 1.2;
  max-width: 160px;
}

.hist_date {
  grid-column: 1 / -1;
  color: @colorBlur;
  font-size: 11px;
}

.labor_hint {
  font-size: 11px;
  color: @colorBlur;
  margin-bottom: 10px;
}

.labor_section_title {
  font-size: 12px;
  font-weight: 700;
  color: @colorText;
  margin: 12px 0 6px;
}

.labor_create {
  margin-bottom: 8px;
}

.labor_create_form {
  flex-wrap: wrap;
  gap: 6px;
}

.labor_select {
  min-width: 140px;
  flex: 1;
  padding: 4px 6px;
  border-radius: 4px;
  border: 1px solid fade(@colorBlur, 35%);
  background: fade(@darkbg, 90%);
  color: @colorText;
  font-size: 11px;
}

.labor_create_note {
  margin-top: 6px;
  font-size: 11px;
  color: @colorBlur;
}

.citybuild_list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.citybuild_order {
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 10px 12px;
  background: rgba(0, 0, 0, 0.15);
}

.citybuild_order_head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.citybuild_city {
  font-weight: 600;
}

.citybuild_building {
  font-size: 12px;
  opacity: 0.75;
  margin-top: 2px;
}

.citybuild_nominal {
  font-size: 12px;
  white-space: nowrap;
}

.citybuild_component_row {
  margin-top: 6px;
}

.citybuild_unit_payout {
  color: #e8c547;
}

.citybuild_payout {
  font-size: 12px;
  font-weight: 600;
  color: #7dcea0;
  white-space: nowrap;
  min-width: 52px;
  text-align: right;
}

.citybuild_payout_muted {
  color: rgba(125, 206, 160, 0.45);
}

.recipe_line {
  font-size: 11px;
  color: rgba(232, 197, 71, 0.85);
  margin-top: 2px;

  &.warn {
    color: rgba(255, 143, 143, 0.9);
  }
}
</style>
