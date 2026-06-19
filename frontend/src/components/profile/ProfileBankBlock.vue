<template>
  <div class="bank_block" v-if="visible">
    <div class="bank_header" @click="expanded = !expanded">
      <span class="title"><AppIcon name="bank" :size="18" /> Банки и кредиты</span>
      <span class="toggle">{{ expanded ? '−' : '+' }}</span>
    </div>

    <div class="bank_body" v-if="expanded">
      <div class="msg error" v-if="error">{{ error }}</div>
      <div class="msg ok" v-if="message">{{ message }}</div>

      <div class="section" v-if="myBank">
        <div class="section_title">Мой банк</div>
        <div class="row"><span>Резерв (свои)</span><span>{{ myBank.reserved }} <AppIcon name="prognobak" :size="14" /></span></div>
        <div class="row"><span>Ликвидность (вклады)</span><span>{{ myBank.liquid }} <AppIcon name="prognobak" :size="14" /></span></div>
        <div class="row"><span>Доступно для займов</span><span>{{ myBank.loanable }} <AppIcon name="prognobak" :size="14" /></span></div>
        <div class="row"><span>Контракты</span><span>{{ myBank.active_contracts }}</span></div>
        <div class="lifetime_stats" v-if="myBank.lifetime">
          <div class="row lifetime_row">
            <span>Заработано на займах</span>
            <span class="lifetime_in">
              +{{ formatAmount(myBank.lifetime.total_loan_interest_earned) }}
              <AppIcon name="prognobak" :size="14" />
            </span>
          </div>
          <div class="row lifetime_row">
            <span>Выплачено по вкладам</span>
            <span class="lifetime_out">
              {{ formatAmount(myBank.lifetime.total_deposit_paid) }}
              <AppIcon name="prognobak" :size="14" />
            </span>
          </div>
        </div>
        <template v-if="myBank.deposits && myBank.deposits.length">
          <div class="subsection_title">Вклады в банке</div>
          <BankContractCard
            v-for="d in myBank.deposits"
            :key="'bd' + d.id"
            :contract="d"
            kind="deposit"
            show-client
          />
        </template>
        <template v-if="myBank.loans && myBank.loans.length">
          <div class="subsection_title">Займы из банка</div>
          <BankContractCard
            v-for="l in myBank.loans"
            :key="'bl' + l.id"
            :contract="l"
            kind="loan"
            show-client
          />
        </template>
        <button
          class="btn danger"
          v-if="myBank.active_contracts === 0"
          :disabled="loading"
          @click="onCloseBank"
        >Закрыть банк</button>
      </div>

      <div class="section" v-else-if="canOpen">
        <div class="section_title">Открыть банк</div>
        <p class="hint">Нужно ≥250 <AppIcon name="prognobak" :size="14" /> на кошельке, 200 замораживаются в резерве.</p>
        <button class="btn" :disabled="loading" @click="onOpenBank">Открыть банк (200 <AppIcon name="prognobak" :size="14" />)</button>
      </div>

      <div class="section">
        <div class="section_title">Операции</div>
        <div class="ops_tabs">
          <button
            v-for="tab in operationTabs"
            :key="tab.id"
            class="ops_tab"
            :class="{ active: activeOpsTab === tab.id }"
            @click="activeOpsTab = tab.id"
          >{{ tab.label }}</button>
        </div>
        <button class="btn secondary" :disabled="loading" @click="loadOperations">Обновить</button>
        <div v-if="!filteredOperations.length" class="hint">Пока нет операций в этой категории</div>
        <div class="operation" v-for="op in filteredOperations" :key="op.id">
          <div class="operation_main">
            <span class="operation_label">{{ op.label }}</span>
            <span class="operation_amount" :class="op.direction">
              {{ formatSignedAmount(op.amount) }}
              <AppIcon name="prognobak" :size="14" />
            </span>
          </div>
          <div class="meta">
            {{ op.at }}
            <span v-if="op.counterparty_name"> · {{ op.counterparty_name }}</span>
            <span v-if="op.match_label"> · {{ op.match_label }}</span>
            <span v-if="op.scope === 'bank'" class="badge">банк</span>
            <span v-if="op.balance_after !== null && op.balance_after !== undefined">
              · баланс {{ op.balance_after }}
            </span>
          </div>
        </div>
      </div>

      <div class="section">
        <div class="section_title">Мои контракты</div>
        <div v-if="!contracts.deposits.length && !contracts.loans.length" class="hint">Нет активных вкладов и займов</div>
        <BankContractCard
          v-for="d in contracts.deposits"
          :key="'d' + d.id"
          :contract="d"
          kind="deposit"
        />
        <BankContractCard
          v-for="l in contracts.loans"
          :key="'l' + l.id"
          :contract="l"
          kind="loan"
        />
      </div>

      <div class="section">
        <div class="section_title">Каталог банков</div>
        <div class="event_pick" v-if="contractEvents.length > 1">
          <div class="event_pick_label">Соревнование для контракта</div>
          <select v-model.number="selectedEventId" class="event_select">
            <option v-for="ev in contractEvents" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
          </select>
          <div class="hint">Срок 5 матчей считается только по турам выбранного турнира.</div>
        </div>
        <div class="meta event_single" v-else-if="contractEvents.length === 1">
          Соревнование: {{ contractEvents[0].name }}
        </div>
        <button class="btn secondary" :disabled="loading" @click="loadBanks">Обновить список</button>
        <div class="bank_card" v-for="b in banks" :key="b.id">
          <div class="row">
            <span>{{ b.owner_name }}</span>
            <span>займ: {{ b.loanable ?? (b.reserved + b.liquid) }} <AppIcon name="prognobak" :size="14" /></span>
          </div>
          <div class="meta">Резерв {{ b.reserved }} · вклады {{ b.liquid }} · +{{ b.deposit_rate_percent }}% / +{{ b.loan_rate_percent }}% за {{ b.term_matches }} матчей</div>
          <div class="actions" v-if="!myBank || myBank.id !== b.id">
            <button class="btn small" :disabled="loading" @click="onCreateDeposit(b.id)">
              Вклад {{ depositAmount }} <AppIcon name="prognobak" :size="14" />
            </button>
            <button class="btn small" :disabled="loading" @click="onTakeLoan(b.id)">
              Займ {{ loanAmount }} <AppIcon name="prognobak" :size="14" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import AppIcon from '@/components/ui/AppIcon.vue';
import BankContractCard from '@/components/profile/BankContractCard.vue';

const DEFAULT_DEPOSIT_AMOUNT = 100;
const DEFAULT_LOAN_AMOUNT = 50;

export default {
  name: 'ProfileBankBlock',
  components: { AppIcon, BankContractCard },
  props: {
    game: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      expanded: false,
      loading: false,
      error: '',
      message: '',
      banks: [],
      contracts: { deposits: [], loans: [] },
      operations: [],
      activeOpsTab: 'all',
      operationTabs: [
        { id: 'all', label: 'Все' },
        { id: 'deposits', label: 'Вклады' },
        { id: 'loans', label: 'Займы' },
        { id: 'returns', label: 'Возвраты' },
      ],
      selectedEventId: 0,
    };
  },
  computed: {
    ...mapState('auth', ['authData']),
    filteredOperations() {
      if (this.activeOpsTab === 'all') {
        return this.operations;
      }

      return this.operations.filter((op) => op.category === this.activeOpsTab);
    },
    visible() {
      return !!this.authData?.token;
    },
    bankInfo() {
      return this.game?.bank || {};
    },
    myBank() {
      return this.bankInfo.my_bank || null;
    },
    canOpen() {
      return !!this.bankInfo.can_open;
    },
    depositAmount() {
      return this.bankInfo.deposit_amount || DEFAULT_DEPOSIT_AMOUNT;
    },
    loanAmount() {
      return this.bankInfo.loan_amount || DEFAULT_LOAN_AMOUNT;
    },
    contractEvents() {
      return this.bankInfo.contract_events || [];
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
    expanded(val) {
      if (val) {
        this.refresh();
      }
    },
    game: {
      deep: true,
      handler() {
        if (this.expanded) {
          this.loadContracts();
        }
      },
    },
  },
  methods: {
    ...mapActions('game', ['listBanks', 'getMyContracts', 'getBankOperations', 'openBank', 'createDeposit', 'takeLoan', 'closeBank']),
    ...mapActions('auth', ['refreshGameInfo']),
    formatSignedAmount(amount) {
      const value = Number(amount ?? 0);
      const fixed = value.toFixed(1).replace(/\.0$/, '');
      return value > 0 ? `+${fixed}` : fixed;
    },
    formatAmount(amount) {
      return Number(amount ?? 0).toFixed(1).replace(/\.0$/, '');
    },
    async refresh() {
      this.error = '';
      await Promise.all([this.loadBanks(), this.loadContracts(), this.loadOperations()]);
    },
    async loadBanks() {
      try {
        const res = await this.listBanks();
        this.banks = res.banks || [];
      } catch (e) {
        this.error = e.message || 'Не удалось загрузить банки';
      }
    },
    async loadContracts() {
      try {
        const res = await this.getMyContracts();
        this.contracts = {
          deposits: res.deposits || [],
          loans: res.loans || [],
        };
      } catch (e) {
        this.error = e.message || 'Не удалось загрузить контракты';
      }
    },
    async loadOperations() {
      try {
        const res = await this.getBankOperations();
        this.operations = res.operations || [];
      } catch (e) {
        this.error = e.message || 'Не удалось загрузить операции';
      }
    },
    async onOpenBank() {
      this.loading = true;
      this.error = '';
      this.message = '';
      try {
        await this.openBank();
        this.message = 'Банк открыт';
        await this.refreshGameInfo();
        await this.refresh();
      } catch (e) {
        this.error = e.message || 'Ошибка открытия банка';
      } finally {
        this.loading = false;
      }
    },
    async onCreateDeposit(bankId) {
      this.loading = true;
      this.error = '';
      this.message = '';
      try {
        await this.createDeposit({ bankId, amount: this.depositAmount, eventId: this.selectedEventId });
        this.message = `Вклад ${this.depositAmount} оформлен`;
        await this.refreshGameInfo();
        await this.refresh();
      } catch (e) {
        this.error = e.message || 'Ошибка вклада';
      } finally {
        this.loading = false;
      }
    },
    async onTakeLoan(bankId) {
      this.loading = true;
      this.error = '';
      this.message = '';
      try {
        await this.takeLoan({ bankId, amount: this.loanAmount, eventId: this.selectedEventId });
        this.message = `Займ ${this.loanAmount} выдан`;
        await this.refreshGameInfo();
        await this.refresh();
      } catch (e) {
        this.error = e.message || 'Ошибка займа';
      } finally {
        this.loading = false;
      }
    },
    async onCloseBank() {
      this.loading = true;
      this.error = '';
      try {
        await this.closeBank();
        this.message = 'Банк закрыт';
        await this.refreshGameInfo();
        await this.refresh();
      } catch (e) {
        this.error = e.message || 'Не удалось закрыть банк';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.bank_block {
  background: @DarkColorBG;
  color: @colorText;
  border-radius: 5px;
  margin: 8px 0;
  text-align: left;
}

.bank_header {
  display: flex;
  justify-content: space-between;
  padding: 10px 12px;
  cursor: pointer;
  .shadow_inset;

  .title {
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
}

.bank_body {
  padding: 8px 12px 12px;
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

.subsection_title {
  font-size: 12px;
  color: @colorBlur;
  margin: 8px 0 4px;
}

.row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  margin-bottom: 4px;
}

.lifetime_stats {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid fade(@colorBlur, 35%);
}

.lifetime_row {
  font-size: 12px;
}

.lifetime_in {
  color: #8ddf8d;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
}

.lifetime_out {
  color: #f0a0a0;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
}

.hint {
  font-size: 12px;
  color: @colorBlur;
  margin: 4px 0 8px;
}

.meta {
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
}

.event_single {
  margin-bottom: 8px;
}

.contract, .bank_card, .operation {
  margin-top: 6px;
  padding: 6px;
  background: @darkbg;
  border-radius: 4px;
  font-size: 12px;
}

.ops_tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}

.ops_tab {
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

.operation_main {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: flex-start;
}

.operation_label {
  flex: 1;
}

.operation_amount {
  font-weight: 600;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 4px;

  &.in {
    color: #8ddf8d;
  }

  &.out {
    color: #f0a0a0;
  }
}

.badge {
  color: @orange;
  margin-left: 4px;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
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

  &.secondary {
    background: @colorBlur;
    margin-bottom: 8px;
  }

  &.danger {
    background: #c44;
    margin-top: 6px;
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
