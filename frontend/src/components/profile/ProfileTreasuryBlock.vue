<template>
  <div class="treasury_block">
    <div class="msg error" v-if="error">{{ error }}</div>
    <div class="msg ok" v-if="message">{{ message }}</div>

    <PreLoader v-if="treasuryLoading" />

    <template v-else-if="treasury">
      <div class="main_tabs">
        <button
          v-for="tab in mainTabs"
          :key="tab.id"
          type="button"
          class="main_tab"
          :class="{ active: activeMainTab === tab.id }"
          @click="activeMainTab = tab.id"
        >{{ tab.label }}</button>
      </div>

      <div class="tab_panel">
        <template v-if="activeMainTab === 'overview'">
          <div class="section">
            <div class="section_title">Баланс казны</div>
            <div class="balance_row">
              <span>прогнобаксы</span>
              <span class="balance_value">
                {{ formatMoney(treasury.prognobaks) }}
                <AppIcon name="prognobak" :size="14" />
              </span>
            </div>
            <div class="balance_row">
              <span>рублиусы</span>
              <span class="balance_value">
                {{ formatMoney(treasury.rublius) }}
                <AppIcon name="rublius" :size="14" />
              </span>
            </div>
            <p class="hint">Поступления из лавки казны и процентов по гос. вкладам. Покупки сундуков — на странице «Рейтинги».</p>
          </div>

          <div class="section" v-if="macro">
            <div class="section_title">Макроэкономика</div>
            <p class="hint macro_users">Зарегистрировано пользователей: <strong>{{ macro.registered_users }}</strong></p>
            <p class="hint macro_match" v-if="macro.last_settled_match_label">
              Результаты внесены за <strong>{{ macro.last_settled_match_label }}</strong>
            </p>

            <div class="macro_block">
              <div class="macro_currency_title">
                <AppIcon name="prognobak" :size="14" />
                Прогнобаксы
              </div>
              <div class="macro_row" v-for="row in prognobakRows" :key="row.key">
                <span>{{ row.label }}</span>
                <span class="macro_value">{{ formatMoney(macro.prognobaks[row.key]) }}</span>
              </div>
            </div>

            <div class="macro_block">
              <div class="macro_currency_title">
                <AppIcon name="rublius" :size="14" />
                Рублиусы
              </div>
              <div class="macro_row" v-for="row in rubliusRows" :key="row.key">
                <span>{{ row.label }}</span>
                <span class="macro_value">{{ formatMoney(macro.rublius[row.key]) }}</span>
              </div>
            </div>

            <div class="macro_block macro_monitor" v-if="macro.flows">
              <div class="macro_currency_title">Оборот и доли</div>
              <div class="macro_row">
                <span>Доля в казне (🪙)</span>
                <span class="macro_value">{{ formatPercent(macro.prognobaks.treasury_share) }}</span>
              </div>
              <div class="macro_row">
                <span>Оборот (🪙 на руках + в банках)</span>
                <span class="macro_value">{{ formatPercent(macro.prognobaks.velocity) }}</span>
              </div>
              <div class="macro_row">
                <span>Доля в казне (💎)</span>
                <span class="macro_value">{{ formatPercent(macro.rublius.treasury_share) }}</span>
              </div>
              <div class="macro_row">
                <span>Оборот (💎)</span>
                <span class="macro_value">{{ formatPercent(macro.rublius.velocity) }}</span>
              </div>
              <div class="macro_row">
                <span>Лавка: сундуки 🪙 (шт. / сумма)</span>
                <span class="macro_value">
                  {{ macro.flows.shop.prognobaks_chests }} / {{ formatMoney(macro.flows.shop.prognobaks_volume) }}
                </span>
              </div>
              <div class="macro_row">
                <span>Лавка: сундуки 💎 + премиум</span>
                <span class="macro_value">
                  {{ macro.flows.shop.rublius_chests }}+{{ macro.flows.shop.premium_1d }}
                  / {{ formatMoney(macro.flows.shop.rublius_volume) }}
                </span>
              </div>
              <div class="macro_row">
                <span>Эмиссия из сундуков (🪙 / 💎)</span>
                <span class="macro_value">
                  {{ formatMoney(macro.flows.chest_mint.prognobaks) }}
                  /
                  {{ formatMoney(macro.flows.chest_mint.rublius) }}
                </span>
              </div>
              <div class="macro_row" v-if="macro.flows.gov_support">
                <span>Господдержка: вкладов / тело в банках</span>
                <span class="macro_value">
                  {{ macro.flows.gov_support.active_count }}
                  / {{ formatMoney(macro.flows.gov_support.principal_in_banks) }} 🪙
                </span>
              </div>
            </div>

            <div class="macro_block macro_monitor" v-if="macro.exchange">
              <div class="macro_currency_title">Биржа</div>
              <div class="macro_row">
                <span>Лотов в продаже / единиц товара</span>
                <span class="macro_value">
                  {{ macro.exchange.active_listings }} / {{ macro.exchange.qty_on_sale }}
                </span>
              </div>
              <div class="macro_row">
                <span>Суммарный номинал на витрине</span>
                <span class="macro_value">{{ formatMoney(macro.exchange.nominal_total) }} 🪙</span>
              </div>
              <div class="macro_row">
                <span>Витринная стоимость (по ценам продавцов)</span>
                <span class="macro_value">{{ formatMoney(macro.exchange.ask_total) }} 🪙</span>
              </div>
              <div class="macro_row">
                <span>Лоты: комиссионка банков / от игроков</span>
                <span class="macro_value">
                  {{ macro.exchange.bank_listings }} / {{ macro.exchange.user_listings }}
                </span>
              </div>
              <div class="macro_row">
                <span>Уникальных продавцов</span>
                <span class="macro_value">{{ macro.exchange.unique_sellers }}</span>
              </div>
              <div class="macro_row" v-if="macro.exchange.trades">
                <span>Сделок / объём / комиссия в казну</span>
                <span class="macro_value">
                  {{ macro.exchange.trades.trades }}
                  / {{ formatMoney(macro.exchange.trades.volume) }} 🪙
                  / {{ formatMoney(macro.exchange.treasury_commission) }} 🪙
                </span>
              </div>
              <template v-if="exchangeBucketRows.length">
                <div class="macro_subtitle">Номинал по категориям</div>
                <div class="macro_row" v-for="row in exchangeBucketRows" :key="row.key">
                  <span>{{ row.label }} ({{ row.qty }} шт.)</span>
                  <span class="macro_value">{{ formatMoney(row.nominal) }} 🪙</span>
                </div>
              </template>
              <p class="hint exchange_hint">Номинал = остаток × снимок номинала при выставлении. Комиссионка банка — без комиссии биржи при продаже.</p>
            </div>

            <div class="section ledger_section" v-if="treasury?.ledger?.length">
              <div class="section_title">Журнал казны</div>
              <p class="hint">Последние операции: выплаты населению, поступления, лавка, гос. вклады.</p>
              <div class="ledger_list">
                <div v-for="row in treasury.ledger" :key="row.id" class="ledger_row">
                  <div class="ledger_head">
                    <span class="ledger_reason">{{ row.reason_label || row.reason }}</span>
                    <span class="ledger_amount" :class="{ out: row.amount < 0, in: row.amount > 0 }">
                      {{ row.amount > 0 ? '+' : '' }}{{ formatMoney(row.amount) }}
                      <AppIcon :name="row.currency === 'rublius' ? 'rublius' : 'prognobak'" :size="12" />
                    </span>
                  </div>
                  <div class="ledger_meta">
                    <span v-if="row.user_name">{{ row.user_name }}</span>
                    <span v-if="row.created_at">{{ row.created_at }}</span>
                    <span>остаток {{ formatMoney(row.balance_after) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <p class="hint">«В банках» — частные банки игроков и пул ставок ЧМ. Среднее = общая масса ÷ число аккаунтов. Оборот — доля не в казне.</p>
          </div>
        </template>

        <template v-else-if="activeMainTab === 'gov'">
          <div class="section" v-if="contractEvents.length">
            <div class="section_title">Гос. вклад поддержки</div>
            <p class="hint">
              Из казны в ликвидность выбранного банка: 500 или 2500
              <AppIcon name="prognobak" :size="14" />.
              После 5 туров 5% поступают в казну. Тело вклада — кнопкой «Забрать вклад».
            </p>

            <div class="event_pick" v-if="contractEvents.length > 1">
              <div class="event_pick_label">Соревнование</div>
              <select v-model.number="selectedEventId" class="event_select">
                <option v-for="ev in contractEvents" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
              </select>
            </div>

            <div class="gov_open_block">
              <div class="subsection_title">Открыть вклад</div>
              <div v-if="banks.length" class="gov_open_row">
                <select v-model.number="selectedGovBankId" class="event_select gov_bank_select">
                  <option v-for="b in banks" :key="b.id" :value="b.id">Банк #{{ b.id }} ({{ b.owner_name }})</option>
                </select>
                <div class="gov_amount_btns">
                  <button
                    class="btn small"
                    :disabled="actionLoading || !selectedGovBankId || !canOpenGovDeposit(500)"
                    @click="onCreateGovDeposit(500)"
                  >
                    500 <AppIcon name="prognobak" :size="14" />
                  </button>
                  <button
                    class="btn small"
                    :disabled="actionLoading || !selectedGovBankId || !canOpenGovDeposit(2500)"
                    @click="onCreateGovDeposit(2500)"
                  >
                    2500 <AppIcon name="prognobak" :size="14" />
                  </button>
                </div>
              </div>
              <div v-else class="hint">Сначала откройте банк во вкладке «Финансы» или дождитесь появления банков в каталоге</div>
              <p class="hint gov_treasury_hint" v-if="banks.length && !canOpenGovDeposit(500) && !canOpenGovDeposit(2500)">
                В казне недостаточно средств для нового гос. вклада (нужно минимум 500 🪙).
              </p>
            </div>

            <div v-if="govDeposits.length" class="gov_deposits_block">
              <div class="subsection_title">Активные вклады</div>
              <div class="gov_deposits_list">
                <BankContractCard
                    v-for="d in govDeposits"
                    :key="'gov' + d.id"
                    :contract="d"
                    kind="deposit"
                    show-force-close
                    @close="onCloseGovDeposit"
                    @force-close="onForceCloseGovDeposit"
                />
              </div>
            </div>
            <p v-else class="hint gov_empty">Активных гос. вкладов пока нет.</p>
          </div>
          <p v-else class="hint section_hint">Нет доступных соревнований для гос. вкладов.</p>
        </template>

        <template v-else-if="activeMainTab === 'warehouses'">
          <div class="section" v-if="warehouses">
            <div class="section_title">Государственные склады</div>
            <p class="hint">
              Сырьё и товары, поступившие с фарма на казну. Нулевые позиции — в каталоге, но на складе пока нет.
            </p>
            <p class="hint warehouse_totals" v-if="warehouses.totals">
              На складе: <strong>{{ warehouses.totals.total_units }}</strong> ед.
              ({{ warehouses.totals.items_with_stock }} видов с остатком)
            </p>

            <div class="warehouse_subtabs" v-if="warehouseGroups.length">
              <button
                v-for="group in warehouseGroups"
                :key="group.id"
                type="button"
                class="warehouse_subtab"
                :class="{ active: activeWarehouseGroupId === group.id }"
                @click="activeWarehouseGroupId = group.id"
              >{{ group.label }}</button>
            </div>

            <div class="warehouse_items" v-if="activeWarehouseGroup">
              <div class="subsection_title">
                {{ activeWarehouseGroup.label }}
                — казна {{ activeWarehouseGroup.total_qty }},
                на руках {{ activeWarehouseGroup.total_hands_qty || 0 }},
                биржа {{ activeWarehouseGroup.total_exchange_qty || 0 }}
              </div>
              <div class="stock_header">
                <span class="stock_header_label">Материал</span>
                <span class="stock_header_qty" title="Государственный склад">Казна</span>
                <span class="stock_header_qty" title="У всех игроков в инвентаре">На руках</span>
                <span class="stock_header_qty" title="Активные лоты на бирже">Биржа</span>
              </div>
              <div
                v-for="item in activeWarehouseGroup.items"
                :key="item.code"
                class="stock_row"
              >
                <span class="stock_label">
                  <span class="stock_emoji">{{ item.emoji || '📦' }}</span>
                  {{ item.label }}
                  <span v-if="item.is_premium" class="premium_tag">★</span>
                </span>
                <span class="stock_qty" :class="{ zero: item.qty <= 0 }">{{ item.qty }}</span>
                <span class="stock_qty" :class="{ zero: (item.hands_qty || 0) <= 0 }">{{ item.hands_qty || 0 }}</span>
                <span class="stock_qty" :class="{ zero: (item.exchange_qty || 0) <= 0 }">{{ item.exchange_qty || 0 }}</span>
                <button
                  v-if="canImpersonate && !item.is_premium && item.qty > 0"
                  type="button"
                  class="action_btn mini"
                  :disabled="actionLoading"
                  @click="openGovListModal(item)"
                >
                  На биржу
                </button>
              </div>
            </div>

            <div class="treasury_exchange_section" v-if="canImpersonate">
              <div class="subsection_title">Продажа с госсклада (биржа)</div>
              <p class="hint">
                Лоты от имени «Казна», цена по номиналу, без комиссии. Покупка зачисляет 🪙 в казну.
              </p>
              <div v-if="treasuryGovListings.length" class="treasury_exchange_list">
                <div
                  v-for="lot in treasuryGovListings"
                  :key="'tg' + lot.id"
                  class="treasury_exchange_row"
                >
                  <span class="treasury_exchange_label">
                    {{ lot.label }} ×{{ lot.qty_remaining }} · {{ lot.price_per_unit }} 🪙
                  </span>
                  <button
                    type="button"
                    class="action_btn mini secondary"
                    :disabled="actionLoading"
                    @click="cancelTreasuryGovListing(lot.id)"
                  >
                    Снять
                  </button>
                </div>
              </div>
              <p v-else class="hint">Нет активных лотов казны на бирже.</p>
            </div>

            <div
              v-if="govListModal"
              class="gov_list_modal_backdrop"
              @click.self="closeGovListModal"
            >
              <div class="gov_list_modal">
                <div class="subsection_title">Выставить {{ govListModal.label }}</div>
                <p class="hint">
                  На складе: {{ govListModal.maxQty }} · номинал {{ govListModal.nominal }} 🪙
                </p>
                <input
                  v-model.number="govListModal.qty"
                  type="number"
                  min="1"
                  :max="govListModal.maxQty"
                  class="qty_input"
                />
                <div class="gov_list_modal_actions">
                  <button type="button" class="action_btn" :disabled="actionLoading" @click="submitGovListMaterial">
                    Выставить
                  </button>
                  <button type="button" class="action_btn secondary" @click="closeGovListModal">Отмена</button>
                </div>
              </div>
            </div>

            <div class="labor_treasury_section" v-if="canImpersonate && laborState">
              <div class="subsection_title">Заказы на работу (казна)</div>
              <p class="hint">
                Сырьё списывается с госсклада, оплата — из казны. Исполнители берут заказ на бирже → «Работы».
                Продукт возвращается на госсклад.
              </p>
              <div class="labor_create_form">
                <select v-model="laborForm.professionCode" class="labor_select">
                  <option value="">Профессия</option>
                  <option
                    v-for="prof in laborState.professions"
                    :key="'treasury-labor-' + prof.code"
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
                <button
                  type="button"
                  class="action_btn"
                  :disabled="actionLoading"
                  @click="createTreasuryLaborOrder"
                >
                  Разместить
                </button>
              </div>
              <p class="hint labor_escrow_hint" v-if="selectedLaborProfession">
                <span v-if="selectedLaborProfession.input_label">
                  Со склада: {{ laborForm.iterations || 0 }} {{ selectedLaborProfession.input_label }} ·
                </span>
                Из казны: {{ laborPayTotal }} 🪙
              </p>

              <div class="labor_orders_list" v-if="treasuryLaborOrders.length">
                <div
                  v-for="order in treasuryLaborOrders"
                  :key="'treasury-order-' + order.id"
                  class="labor_order_row"
                >
                  <div class="labor_order_main">
                    <div class="labor_order_label">
                      {{ order.profession_label }} → {{ order.output_label }}
                    </div>
                    <div class="labor_order_meta">
                      {{ order.iterations_done }}/{{ order.iterations_total }} ·
                      {{ order.pay_per_cycle }} 🪙/цикл
                      <span v-if="order.has_active_worker" class="badge_active">в работе</span>
                      <span v-else-if="order.status === 'open'"> · осталось {{ order.iterations_remaining }}</span>
                      · {{ orderStatusLabel(order.status) }}
                    </div>
                  </div>
                  <button
                    v-if="order.can_cancel"
                    type="button"
                    class="action_btn danger"
                    :disabled="actionLoading"
                    @click="cancelTreasuryLaborOrder(order.id)"
                  >
                    Снять
                  </button>
                </div>
              </div>
              <p v-else class="hint section_hint">Нет заказов казны на бирже труда.</p>
            </div>

            <div class="macro_block macro_monitor" v-if="warehouseFlows">
              <div class="macro_currency_title">Оборот по фарму (🪙)</div>
              <div class="macro_row">
                <span>Казна → на руки (оплата работы)</span>
                <span class="macro_value">{{ formatMoney(warehouseFlows.treasury_out) }}</span>
              </div>
              <div class="macro_row">
                <span>На руки → казна (сбор за работу)</span>
                <span class="macro_value">{{ formatMoney(warehouseFlows.treasury_in) }}</span>
              </div>
              <div class="macro_row">
                <span>Зачислено игрокам</span>
                <span class="macro_value">{{ formatMoney(warehouseFlows.hands_in) }}</span>
              </div>
            </div>

            <div class="ledger_section" v-if="warehouses.ledger?.length">
              <div class="subsection_title">Журнал по фарму</div>
              <p class="hint">Выплаты и сборы, связанные с профессиями и госскладом.</p>
              <div class="ledger_list">
                <div v-for="row in warehouses.ledger" :key="row.id" class="ledger_row">
                  <div class="ledger_head">
                    <span class="ledger_reason">{{ row.reason_label || row.reason }}</span>
                    <span class="ledger_amount" :class="{ out: row.amount < 0, in: row.amount > 0 }">
                      {{ row.amount > 0 ? '+' : '' }}{{ formatMoney(row.amount) }}
                      <AppIcon :name="row.currency === 'rublius' ? 'rublius' : 'prognobak'" :size="12" />
                    </span>
                  </div>
                  <div class="ledger_meta">
                    <span v-if="row.user_name">{{ row.user_name }}</span>
                    <span v-if="row.created_at">{{ row.created_at }}</span>
                    <span>остаток {{ formatMoney(row.balance_after) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="hint section_hint">Нет данных по складам.</p>
        </template>
      </div>
    </template>
  </div>
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex';
import PreLoader from '@/components/main/PreLoader';
import AppIcon from '@/components/ui/AppIcon.vue';
import BankContractCard from '@/components/profile/BankContractCard.vue';
import { apiActions } from '@/api/bitrixClient';

export default {
  name: 'ProfileTreasuryBlock',
  components: { PreLoader, AppIcon, BankContractCard },
  props: {
    game: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      activeMainTab: 'overview',
      activeWarehouseGroupId: 'gather',
      treasuryLoading: false,
      actionLoading: false,
      treasury: null,
      macro: null,
      warehouses: null,
      banks: [],
      govDeposits: [],
      selectedGovBankId: 0,
      selectedEventId: 0,
      error: '',
      message: '',
      laborState: null,
      treasuryLaborOrders: [],
      laborForm: {
        professionCode: '',
        iterations: 6,
        payPerCycle: 2,
      },
      govListModal: null,
    };
  },
  computed: {
    ...mapState({
      authData: state => state.auth.authData,
    }),
    ...mapGetters('auth', ['canImpersonate']),
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
    bankInfo() {
      return this.game?.bank || {};
    },
    myBank() {
      return this.bankInfo.my_bank || null;
    },
    contractEvents() {
      return this.bankInfo.contract_events || [];
    },
    mainTabs() {
      return [
        { id: 'overview', label: 'Общая информация' },
        { id: 'gov', label: 'Гос. поддержка' },
        { id: 'warehouses', label: 'Склады' },
      ];
    },
    warehouseGroups() {
      return this.warehouses?.groups || [];
    },
    activeWarehouseGroup() {
      return this.warehouseGroups.find((group) => group.id === this.activeWarehouseGroupId)
        || this.warehouseGroups[0]
        || null;
    },
    warehouseFlows() {
      return this.warehouses?.flows?.profession || null;
    },
    treasuryGovListings() {
      return this.warehouses?.treasury_exchange?.listings || [];
    },
    treasuryGovQtyByCode() {
      return this.warehouses?.treasury_exchange?.by_code || {};
    },
    prognobakRows() {
      return [
        { key: 'total', label: 'Всего' },
        { key: 'hands', label: 'На руках' },
        { key: 'banks', label: 'В банках' },
        { key: 'treasury', label: 'В казне' },
        { key: 'avg_per_user', label: 'Среднее на пользователя' },
      ];
    },
    rubliusRows() {
      return [
        { key: 'total', label: 'Всего' },
        { key: 'hands', label: 'На руках' },
        { key: 'banks', label: 'В банках' },
        { key: 'treasury', label: 'В казне' },
        { key: 'avg_per_user', label: 'Среднее на пользователя' },
      ];
    },
    exchangeBucketRows() {
      const buckets = this.macro?.exchange?.by_bucket;
      if (!buckets || typeof buckets !== 'object') {
        return [];
      }

      return Object.entries(buckets).map(([key, row]) => ({
        key,
        label: this.exchangeBucketLabel(key),
        qty: row?.qty ?? 0,
        nominal: row?.nominal ?? 0,
        ask: row?.ask ?? 0,
      }));
    },
  },
  watch: {
    contractEvents: {
      immediate: true,
      handler(events) {
        if (!events.length) {
          this.selectedEventId = 0;
          return;
        }
        if (!events.some((ev) => ev.id === this.selectedEventId)) {
          this.selectedEventId = events[0].id;
        }
      },
    },
    banks(bankList) {
      if (!bankList.length) {
        this.selectedGovBankId = 0;
        return;
      }
      if (this.myBank?.id && bankList.some((b) => b.id === this.myBank.id)) {
        this.selectedGovBankId = this.myBank.id;
        return;
      }
      if (!bankList.some((b) => b.id === this.selectedGovBankId)) {
        this.selectedGovBankId = bankList[0].id;
      }
    },
    warehouseGroups(groups) {
      if (!groups.length) {
        this.activeWarehouseGroupId = 'gather';
        return;
      }
      if (!groups.some((group) => group.id === this.activeWarehouseGroupId)) {
        this.activeWarehouseGroupId = groups[0].id;
      }
    },
  },
  created() {
    this.refresh();
  },
  methods: {
    ...mapActions('game', [
      'listBanks',
      'createGovSupportDeposit',
      'closeGovSupportDeposit',
      'getGovSupportDeposits',
      'forceCloseDeposit',
    ]),
    ...mapActions('auth', ['refreshGameInfo']),

    formatMoney(value) {
      const num = Number(value ?? 0);
      return Number.isInteger(num) ? String(num) : num.toFixed(1);
    },

    formatPercent(value) {
      const num = Number(value ?? 0);
      return `${Number.isInteger(num) ? num : num.toFixed(1)}%`;
    },

    exchangeBucketLabel(key) {
      const labels = {
        'material:normal': 'Материалы',
        'material:premium': 'Премиум',
        'loot:xp_bank': 'XP-банки',
        'loot:pack': 'ККИ (паки)',
        'loot:cert': 'Лицензии',
        chest: 'Сундуки',
        pennant: 'Сувениры',
        souvenir: 'Сувениры',
      };

      return labels[key] || key;
    },

    canOpenGovDeposit(amount) {
      return Number(this.treasury?.prognobaks ?? 0) >= Number(amount || 0);
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

    async refresh() {
      this.error = '';
      await Promise.all([
        this.loadTreasury(),
        this.loadBanks(),
        this.loadGovDeposits(),
        this.loadTreasuryLaborOrders(),
      ]);
    },

    async loadTreasuryLaborOrders() {
      if (!this.canImpersonate || !this.authData?.token) {
        this.laborState = null;
        this.treasuryLaborOrders = [];
        return;
      }

      try {
        const data = await apiActions.game.getTreasuryLaborOrders(this.authData.token);
        if (data?.status === 'ok') {
          this.laborState = data.labor || null;
          this.treasuryLaborOrders = Array.isArray(data.items) ? data.items : [];
          if (this.laborState?.default_pay_per_cycle && !this.laborForm.payPerCycle) {
            this.laborForm.payPerCycle = this.laborState.default_pay_per_cycle;
          }
        }
      } catch (e) {
        console.log('treasury labor orders load error', e);
      }
    },

    async createTreasuryLaborOrder() {
      const professionCode = (this.laborForm.professionCode || '').trim();
      const iterations = Number(this.laborForm.iterations) || 0;
      const payPerCycle = Number(this.laborForm.payPerCycle) || 0;

      if (!professionCode || iterations < 1 || payPerCycle <= 0) {
        this.error = 'Заполните профессию, циклы и оплату';
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.game.createTreasuryLaborOrder(
          this.authData.token,
          professionCode,
          iterations,
          payPerCycle
        );

        if (data?.status === 'ok') {
          this.message = 'Заказ казны размещён на бирже труда';
          this.treasury = data.treasury || this.treasury;
          this.warehouses = data.warehouses || this.warehouses;
          await this.loadTreasuryLaborOrders();
        }
      } catch (e) {
        this.error = e.message || 'Не удалось разместить заказ';
      } finally {
        this.actionLoading = false;
      }
    },

    async cancelTreasuryLaborOrder(orderId) {
      this.actionLoading = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.game.cancelTreasuryLaborOrder(this.authData.token, orderId);
        if (data?.status === 'ok') {
          this.message = 'Заказ снят, эскроу возвращено на склад и в казну';
          this.treasury = data.treasury || this.treasury;
          this.warehouses = data.warehouses || this.warehouses;
          await this.loadTreasuryLaborOrders();
        }
      } catch (e) {
        this.error = e.message || 'Не удалось снять заказ';
      } finally {
        this.actionLoading = false;
      }
    },

    openGovListModal(item) {
      const maxQty = Math.max(0, Number(item?.qty) || 0);
      if (!item?.code || maxQty <= 0) {
        return;
      }
      this.govListModal = {
        code: item.code,
        label: item.label,
        nominal: Number(item.nominal) || 0,
        maxQty,
        qty: Math.min(maxQty, 50),
      };
    },

    closeGovListModal() {
      if (this.actionLoading) {
        return;
      }
      this.govListModal = null;
    },

    async submitGovListMaterial() {
      const modal = this.govListModal;
      if (!modal?.code) {
        return;
      }
      const qty = Number(modal.qty) || 0;
      if (qty < 1 || qty > modal.maxQty) {
        this.error = 'Укажите количество от 1 до ' + modal.maxQty;
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.game.listTreasuryGovMaterial(
          this.authData.token,
          modal.code,
          qty,
        );
        if (data?.status === 'ok') {
          this.message = 'На биржу выставлено ' + (data.listed_qty || qty) + ' шт.';
          this.warehouses = data.warehouses || this.warehouses;
          this.govListModal = null;
        }
      } catch (e) {
        this.error = e.message || 'Не удалось выставить лот';
      } finally {
        this.actionLoading = false;
      }
    },

    async cancelTreasuryGovListing(listingId) {
      if (!listingId) {
        return;
      }
      this.actionLoading = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.game.cancelTreasuryGovListing(this.authData.token, listingId);
        if (data?.status === 'ok') {
          this.message = 'Лот казны снят, материал возвращён на госсклад';
          this.warehouses = data.warehouses || this.warehouses;
        }
      } catch (e) {
        this.error = e.message || 'Не удалось снять лот';
      } finally {
        this.actionLoading = false;
      }
    },

    async loadTreasury() {
      const token = this.authData?.token;
      if (!token) {
        return;
      }

      this.treasuryLoading = true;
      try {
        const data = await apiActions.game.getTreasury(token);
        if (data?.status === 'ok') {
          this.treasury = data.treasury || null;
          this.macro = data.macro || null;
          this.warehouses = data.warehouses || null;
        } else {
          this.error = data?.message || 'Не удалось загрузить казну';
        }
      } catch (e) {
        this.error = 'Не удалось загрузить казну';
        console.log('treasury load error', e);
      } finally {
        this.treasuryLoading = false;
      }
    },

    async loadBanks() {
      try {
        const res = await this.listBanks(200);
        this.banks = res.banks || [];
      } catch (e) {
        this.error = e.message || 'Не удалось загрузить банки';
      }
    },

    async loadGovDeposits() {
      try {
        const res = await this.getGovSupportDeposits();
        this.govDeposits = res.deposits || [];
      } catch (e) {
        this.error = e.message || 'Не удалось загрузить гос. вклады';
      }
    },

    async onCreateGovDeposit(amount) {
      if (!this.selectedGovBankId || !amount) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        await this.createGovSupportDeposit({
          bankId: this.selectedGovBankId,
          eventId: this.selectedEventId,
          amount,
        });
        this.message = `Гос. вклад ${amount} 🪙 открыт`;
        await this.refreshGameInfo();
        await this.refresh();
      } catch (e) {
        this.error = e.message || 'Ошибка гос. вклада';
      } finally {
        this.actionLoading = false;
      }
    },

    async onCloseGovDeposit(contract) {
      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        await this.closeGovSupportDeposit(contract.id);
        this.message = 'Гос. вклад закрыт, тело возвращено';
        await this.refreshGameInfo();
        await this.refresh();
      } catch (e) {
        this.error = e.message || 'Не удалось закрыть вклад';
      } finally {
        this.actionLoading = false;
      }
    },

    async onForceCloseGovDeposit(contract) {
      if (!contract?.id) {
        return;
      }
      const msg = 'Досрочно забрать гос. вклад? Проценты в казну не поступят, вернётся только тело.';
      if (!window.confirm(msg)) {
        return;
      }
      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        await this.forceCloseDeposit(contract.id);
        this.message = 'Гос. вклад досрочно закрыт';
        await this.refreshGameInfo();
        await this.refresh();
      } catch (e) {
        this.error = e.message || 'Не удалось досрочно закрыть вклад';
      } finally {
        this.actionLoading = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.treasury_block {
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section {
  .shadow_inset;
  padding: 8px;
  border-radius: 4px;
}

.section_title {
  font-size: 13px;
  color: @orange;
  margin-bottom: 6px;
}

.balance_row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  margin-bottom: 4px;
  color: @colorText;
}

.balance_value {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
}

.macro_users {
  margin-bottom: 8px;

  strong {
    color: @yellow;
  }
}

.macro_match {
  margin: -2px 0 8px;

  strong {
    color: @orange;
  }
}

.macro_block {
  margin-bottom: 10px;

  &.macro_monitor {
    margin-top: 4px;
    padding-top: 6px;
    border-top: 1px solid fade(@colorBlur, 25%);
  }
}

.macro_currency_title {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: @colorText;
  margin-bottom: 4px;
}

.macro_subtitle {
  font-size: 11px;
  font-weight: 700;
  color: @colorText;
  margin: 8px 0 4px;
}

.exchange_hint {
  margin-top: 6px;
}

.macro_row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: @colorBlur;
  padding: 2px 0;

  .macro_value {
    color: @colorText;
    font-weight: 600;
  }
}

.hint {
  font-size: 12px;
  color: @colorBlur;
  margin: 4px 0 8px;
  line-height: 1.35;
}

.main_tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 10px;
}

.main_tab {
  background: @darkbg;
  color: @colorText;
  border: 1px solid transparent;
  border-radius: 4px;
  padding: 7px 12px;
  font-size: 12px;
  cursor: pointer;

  &.active {
    background: @orange;
    color: #fff;
  }
}

.tab_panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.subsection_title {
  font-size: 12px;
  color: @colorBlur;
  margin: 0 0 6px;
}

.gov_open_block {
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 1px solid fade(@colorBlur, 25%);
}

.gov_deposits_block {
  margin-top: 4px;
}

.gov_open_row {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.gov_amount_btns {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.gov_treasury_hint {
  margin-top: 6px;
}

.gov_bank_select {
  margin-bottom: 0;
}

.gov_deposits_list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.gov_empty,
.section_hint {
  margin: 0;
}

.warehouse_totals strong {
  color: @yellow;
}

.warehouse_subtabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 8px 0;
}

.warehouse_subtab {
  background: @darkbg;
  color: @colorText;
  border: 1px solid transparent;
  border-radius: 4px;
  padding: 5px 10px;
  font-size: 11px;
  cursor: pointer;

  &.active {
    background: @orange;
    color: #fff;
  }
}

.warehouse_items {
  margin-bottom: 10px;
}

.stock_header {
  display: grid;
  grid-template-columns: 1fr 52px 52px 52px;
  gap: 6px;
  align-items: center;
  font-size: 10px;
  color: @colorBlur;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 0 0 4px;
  border-bottom: 1px solid fade(@colorBlur, 25%);
  margin-bottom: 2px;
}

.stock_header_label {
  min-width: 0;
}

.stock_header_qty {
  text-align: right;
}

.stock_row {
  display: grid;
  grid-template-columns: 1fr 52px 52px 52px auto;
  gap: 6px;
  align-items: center;
  font-size: 12px;
  color: @colorBlur;
  padding: 3px 0;
}

.treasury_exchange_section {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid fade(@colorBlur, 20%);
}

.treasury_exchange_list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.treasury_exchange_row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 12px;
}

.treasury_exchange_label {
  flex: 1;
}

.gov_list_modal_backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1200;
  padding: 16px;
}

.gov_list_modal {
  background: @darkbg;
  border: 1px solid fade(@colorBlur, 25%);
  border-radius: 8px;
  padding: 16px;
  width: 100%;
  max-width: 320px;
}

.gov_list_modal_actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}

.action_btn.mini {
  padding: 4px 8px;
  font-size: 11px;
}

.stock_label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: @colorText;
}

.stock_emoji {
  font-size: 16px;
  line-height: 1;
}

.premium_tag {
  color: @yellow;
  margin-left: 4px;
}

.stock_qty {
  font-weight: 600;
  color: @colorText;
  text-align: right;

  &.zero {
    color: fade(@colorBlur, 70%);
    font-weight: 400;
  }
}

.labor_treasury_section {
  margin: 12px 0;
  padding-top: 10px;
  border-top: 1px solid fade(@colorBlur, 25%);
}

.labor_create_form {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 6px;
}

.labor_select,
.qty_input,
.price_input {
  padding: 4px 6px;
  border-radius: 4px;
  border: 1px solid fade(@colorBlur, 35%);
  background: fade(@darkbg, 90%);
  color: @colorText;
  font-size: 11px;
}

.labor_select {
  min-width: 160px;
  flex: 1;
}

.action_btn {
  border: 1px solid fade(@orange, 60%);
  background: fade(@orange, 20%);
  color: @colorText;
  border-radius: 4px;
  padding: 5px 10px;
  font-size: 11px;
  cursor: pointer;

  &.danger {
    border-color: fade(@NoWrite, 60%);
    background: fade(@NoWrite, 15%);
  }

  &:disabled {
    opacity: 0.5;
    cursor: default;
  }
}

.labor_orders_list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 8px;
}

.labor_order_row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-start;
  justify-content: space-between;
  border: 1px solid fade(@colorBlur, 25%);
  border-radius: 4px;
  padding: 8px;
}

.labor_order_label {
  font-size: 13px;
  font-weight: 700;
  color: @colorText;
}

.labor_order_meta {
  font-size: 11px;
  color: @colorBlur;
}

.badge_active {
  display: inline-block;
  margin-left: 4px;
  padding: 0 4px;
  border-radius: 3px;
  font-size: 10px;
  background: fade(@orange, 25%);
  color: @colorText;
}

.labor_escrow_hint {
  margin-top: 4px;
}

.ledger_section {
  margin-top: 10px;
}

.ledger_list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 280px;
  overflow-y: auto;
}

.ledger_row {
  padding: 6px 8px;
  border-radius: 4px;
  background: fade(@darkbg, 50%);
}

.ledger_head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
  font-size: 12px;
}

.ledger_reason {
  color: @colorText;
}

.ledger_amount {
  flex-shrink: 0;
  font-weight: 600;

  &.out {
    color: #f88;
  }

  &.in {
    color: @yellow;
  }
}

.ledger_meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 3px;
  font-size: 11px;
  color: @colorBlur;
}

.event_pick {
  margin-bottom: 8px;
}

.event_pick_label {
  font-size: 12px;
  color: @colorText;
  margin-bottom: 4px;
}

.event_select {
  width: 100%;
  background: @darkbg;
  color: @colorText;
  border: 1px solid fade(@colorBlur, 40%);
  border-radius: 4px;
  padding: 6px 8px;
  font-size: 12px;
  margin-bottom: 8px;
}

.btn {
  background: @orange;
  color: #fff;
  border: none;
  border-radius: 4px;
  padding: 6px 10px;
  font-size: 12px;
  cursor: pointer;

  &.small {
    padding: 6px 12px;
  }

  &:disabled {
    opacity: 0.5;
    cursor: default;
  }
}

.msg {
  font-size: 12px;
  padding: 6px;
  border-radius: 4px;

  &.error {
    background: rgba(200, 60, 60, 0.2);
    color: #f88;
  }

  &.ok {
    background: rgba(60, 160, 80, 0.2);
    color: #8f8;
  }
}
</style>
