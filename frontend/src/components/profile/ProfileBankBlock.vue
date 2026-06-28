<template>
  <div class="bank_block" v-if="visible">
    <div class="msg error" v-if="error">{{ error }}</div>
    <div class="msg ok" v-if="message">{{ message }}</div>

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
      <div class="event_pick" v-if="showEventPick && contractEvents.length > 1">
        <div class="event_pick_label">Соревнование для контракта</div>
        <select v-model.number="selectedEventId" class="event_select">
          <option v-for="ev in contractEvents" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
        </select>
        <div class="hint">Срок 5 матчей считается только по турам выбранного турнира.</div>
      </div>
      <div class="meta event_single" v-else-if="showEventPick && contractEvents.length === 1">
        Соревнование: {{ contractEvents[0].name }}
      </div>

      <template v-if="activeMainTab === 'banks'">
        <div class="section" v-if="canOpen && !myBank">
          <div class="section_title">Открыть банк</div>
          <p class="hint">Нужно ≥250 <AppIcon name="prognobak" :size="14" /> на кошельке, 200 замораживаются в резерве.</p>
          <button class="btn" :disabled="loading" @click="onOpenBank">Открыть банк (200 <AppIcon name="prognobak" :size="14" />)</button>
        </div>

        <div class="section">
          <div class="section_title">Каталог банков</div>
          <button class="btn secondary" :disabled="loading" @click="loadBanks">Обновить список</button>
          <div class="bank_card" v-for="b in banks" :key="b.id">
            <div class="row bank_card_head">
              <span class="bank_owner">
                {{ b.owner_name }}
                <button
                  v-if="canImpersonate && b.owner_id"
                  type="button"
                  class="bank_enter_btn"
                  title="Войти как владелец банка"
                  :disabled="loading"
                  @click="loginAsBankOwner(b)"
                >
                  <AppIcon name="exit_door" :size="14" />
                </button>
              </span>
              <span>займ: {{ b.loanable ?? (b.reserved + b.liquid) }} <AppIcon name="prognobak" :size="14" /></span>
            </div>
            <div class="meta">Резерв {{ b.reserved }} · вклады {{ b.liquid }} · +{{ b.deposit_rate_percent }}% / +{{ b.loan_rate_percent }}% за {{ b.term_matches }} матчей</div>
            <div class="actions">
              <button class="btn small" :disabled="loading" @click="onCreateDeposit(b.id)">
                Вклад {{ depositAmount }} <AppIcon name="prognobak" :size="14" />
              </button>
              <button
                v-if="!myBank || myBank.id !== b.id"
                class="btn small"
                :disabled="loading"
                @click="onTakeLoan(b.id)"
              >
                Займ {{ loanAmount }} <AppIcon name="prognobak" :size="14" />
              </button>
            </div>
          </div>
        </div>
      </template>

      <template v-else-if="activeMainTab === 'operations'">
        <div class="section">
          <div class="section_title">Операции</div>
          <div class="ops_tabs">
            <button
              v-for="tab in operationTabs"
              :key="tab.id"
              type="button"
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
          <div v-if="!myContractsCount" class="hint">Нет активных вкладов и займов</div>
          <template v-else>
            <div class="ops_tabs">
              <button
                type="button"
                class="ops_tab"
                :class="{ active: activeMyContractTab === 'deposits' }"
                @click="activeMyContractTab = 'deposits'"
              >Вклады ({{ myDepositsCount }})</button>
              <button
                type="button"
                class="ops_tab"
                :class="{ active: activeMyContractTab === 'loans' }"
                @click="activeMyContractTab = 'loans'"
              >Займы ({{ myLoansCount }})</button>
            </div>
            <template v-if="activeMyContractTab === 'deposits'">
              <div v-if="!myDepositsCount" class="hint">Нет активных вкладов</div>
              <BankContractCard
                v-for="d in contracts.deposits"
                :key="'d' + d.id"
                :contract="d"
                kind="deposit"
                show-cancel
                show-force-close
                @cancel="onCancelDeposit"
                @force-close="onForceCloseDeposit"
              />
            </template>
            <template v-else>
              <div v-if="!myLoansCount" class="hint">Нет активных займов</div>
              <BankContractCard
                v-for="l in contracts.loans"
                :key="'l' + l.id"
                :contract="l"
                kind="loan"
                show-cancel
                @cancel="onCancelLoan"
              />
            </template>
          </template>
        </div>
      </template>

      <template v-else-if="activeMainTab === 'my_bank' && myBank">
        <div class="section">
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
              <span>Возврат тела вкладов</span>
              <span class="lifetime_neutral">
                {{ formatAmount(myBank.lifetime.total_deposit_principal_returned) }}
                <AppIcon name="prognobak" :size="14" />
              </span>
            </div>
            <div class="row lifetime_row">
              <span>Проценты по вкладам</span>
              <span class="lifetime_out">
                {{ formatAmount(myBank.lifetime.total_deposit_interest_paid) }}
                <AppIcon name="prognobak" :size="14" />
              </span>
            </div>
          </div>
          <div class="bank_contracts_block" v-if="myBank.active_contracts > 0">
            <div class="subsection_title">Контракты в банке</div>
            <div class="ops_tabs">
              <button
                type="button"
                class="ops_tab"
                :class="{ active: activeBankContractTab === 'deposits' }"
                @click="activeBankContractTab = 'deposits'"
              >Вклады ({{ bankDepositsCount }})</button>
              <button
                type="button"
                class="ops_tab"
                :class="{ active: activeBankContractTab === 'loans' }"
                @click="activeBankContractTab = 'loans'"
              >Займы ({{ bankLoansCount }})</button>
            </div>
            <template v-if="activeBankContractTab === 'deposits'">
              <div v-if="!bankDepositsCount" class="hint">Нет активных вкладов</div>
              <BankContractCard
                v-for="d in myBank.deposits"
                :key="'bd' + d.id"
                :contract="d"
                kind="deposit"
                show-client
                show-gov-return
                @gov-return="onGovReturnInBank"
              />
            </template>
            <template v-else>
              <div v-if="!bankLoansCount" class="hint">Нет активных займов</div>
              <BankContractCard
                v-for="l in myBank.loans"
                :key="'bl' + l.id"
                :contract="l"
                kind="loan"
                show-client
              />
            </template>
          </div>
          <button
            class="btn danger"
            v-if="myBank.active_contracts === 0"
            :disabled="loading"
            @click="onCloseBank"
          >Закрыть банк</button>
          <div class="owner_deposit_block" v-if="contractEvents.length">
            <div class="hint">Вклад в свой банк пополняет ликвидность для выдачи займов (сумма как у всех — {{ depositAmount }} <AppIcon name="prognobak" :size="14" />).</div>
            <button class="btn small" :disabled="loading" @click="onCreateDeposit(myBank.id)">
              Вклад в свой банк {{ depositAmount }} <AppIcon name="prognobak" :size="14" />
            </button>
          </div>
          <div class="consignment_block" v-if="consignmentSettings">
            <div class="subsection_title">Комиссионка</div>
            <div class="hint">
              Игроки сдают товары — банк платит 80% сразу и выставляет на бирже.
              По умолчанию приём включён, все категории активны.
            </div>
            <label class="consign_toggle">
              <input
                type="checkbox"
                v-model="consignmentForm.enabled"
                :disabled="loading"
              />
              Принимать комиссионку
            </label>
            <div class="consign_categories" v-if="consignmentForm.enabled">
              <label
                v-for="opt in consignmentSettings.category_options"
                :key="opt.id"
                class="consign_cat"
              >
                <input
                  type="checkbox"
                  v-model="consignmentForm.categories[opt.id]"
                  :disabled="loading"
                />
                {{ opt.label }}
              </label>
            </div>
            <button
              class="btn small"
              :disabled="loading"
              @click="onSaveConsignmentSettings"
            >Сохранить настройки комиссионки</button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex';
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
      activeMainTab: 'banks',
      loading: false,
      error: '',
      message: '',
      banks: [],
      contracts: { deposits: [], loans: [] },
      operations: [],
      operationsLoaded: false,
      activeOpsTab: 'all',
      activeBankContractTab: 'deposits',
      activeMyContractTab: 'deposits',
      operationTabs: [
        { id: 'all', label: 'Все' },
        { id: 'deposits', label: 'Вклады' },
        { id: 'loans', label: 'Займы' },
        { id: 'consignment', label: 'Комиссионка' },
        { id: 'returns', label: 'Возвраты' },
      ],
      selectedEventId: 0,
      consignmentForm: {
        enabled: false,
        categories: {},
      },
    };
  },
  computed: {
    ...mapState('auth', ['authData']),
    ...mapGetters('auth', ['canImpersonate']),
    mainTabs() {
      const tabs = [
        { id: 'banks', label: 'Банки' },
        { id: 'operations', label: 'Операции' },
      ];
      if (this.myBank) {
        tabs.push({ id: 'my_bank', label: 'Мой банк' });
      }
      return tabs;
    },
    showEventPick() {
      return this.activeMainTab === 'banks' || this.activeMainTab === 'my_bank';
    },
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
    bankDepositsCount() {
      return (this.myBank?.deposits || []).length;
    },
    bankLoansCount() {
      return (this.myBank?.loans || []).length;
    },
    myDepositsCount() {
      return (this.contracts.deposits || []).length;
    },
    myLoansCount() {
      return (this.contracts.loans || []).length;
    },
    myContractsCount() {
      return this.myDepositsCount + this.myLoansCount;
    },
    consignmentSettings() {
      return this.myBank?.consignment || null;
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
    activeMainTab(val) {
      if (val === 'operations' && !this.operationsLoaded) {
        this.loadOperations();
      }
    },
    myBank(val) {
      if (!val && this.activeMainTab === 'my_bank') {
        this.activeMainTab = 'banks';
      }
      this.syncBankContractTab();
      this.syncConsignmentForm();
    },
    game: {
      deep: true,
      handler() {
        this.loadContracts();
        this.syncBankContractTab();
      },
    },
    contracts: {
      deep: true,
      handler() {
        this.syncMyContractTab();
      },
    },
  },
  mounted() {
    this.refresh();
  },
  methods: {
    ...mapActions('game', [
      'listBanks',
      'getMyContracts',
      'getBankOperations',
      'openBank',
      'createDeposit',
      'takeLoan',
      'closeBank',
      'cancelLoan',
      'cancelDeposit',
      'forceCloseDeposit',
      'closeGovSupportDeposit',
      'updateBankConsignmentSettings',
    ]),
    ...mapActions('auth', ['refreshGameInfo', 'impersonateStart']),
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
      const tasks = [this.loadBanks(), this.loadContracts()];
      if (this.activeMainTab === 'operations') {
        tasks.push(this.loadOperations());
      }
      await Promise.all(tasks);
      this.syncBankContractTab();
      this.syncMyContractTab();
    },
    syncBankContractTab() {
      if (this.activeBankContractTab === 'deposits' && !this.bankDepositsCount && this.bankLoansCount) {
        this.activeBankContractTab = 'loans';
      } else if (this.activeBankContractTab === 'loans' && !this.bankLoansCount && this.bankDepositsCount) {
        this.activeBankContractTab = 'deposits';
      }
    },
    syncMyContractTab() {
      if (this.activeMyContractTab === 'deposits' && !this.myDepositsCount && this.myLoansCount) {
        this.activeMyContractTab = 'loans';
      } else if (this.activeMyContractTab === 'loans' && !this.myLoansCount && this.myDepositsCount) {
        this.activeMyContractTab = 'deposits';
      }
    },
    syncConsignmentForm() {
      const settings = this.consignmentSettings;
      if (!settings) {
        return;
      }
      this.consignmentForm.enabled = !!settings.enabled;
      this.consignmentForm.categories = { ...(settings.categories || {}) };
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
        this.operationsLoaded = true;
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
        this.activeMainTab = 'my_bank';
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
    async onGovReturnInBank(contract) {
      if (!contract?.id) {
        return;
      }

      const early = contract.can_force_close && !contract.can_close;
      const msg = early
        ? 'Досрочно вернуть гос. вклад в казну? Проценты не поступят.'
        : 'Вернуть гос. вклад в казну?';
      if (!window.confirm(msg)) {
        return;
      }

      this.loading = true;
      this.error = '';
      this.message = '';
      try {
        if (contract.can_close) {
          await this.closeGovSupportDeposit(contract.id);
        } else {
          await this.forceCloseDeposit(contract.id);
        }
        this.message = 'Гос. вклад возвращён в казну';
        await this.refreshGameInfo();
        await this.refresh();
      } catch (e) {
        this.error = e.message || 'Не удалось вернуть гос. вклад';
      } finally {
        this.loading = false;
      }
    },
    async loginAsBankOwner(bank) {
      const ownerId = Number(bank?.owner_id || 0);
      if (!ownerId) {
        return;
      }

      this.loading = true;
      this.error = '';
      this.message = '';
      try {
        await this.impersonateStart(ownerId);
        this.message = `Вход за ${bank.owner_name || 'владельца банка'}`;
        await this.refreshGameInfo();
        await this.refresh();
        if (this.myBank) {
          this.activeMainTab = 'my_bank';
        }
      } catch (e) {
        this.error = e.message || 'Не удалось войти за владельца банка';
      } finally {
        this.loading = false;
      }
    },
    async onSaveConsignmentSettings() {
      this.loading = true;
      this.error = '';
      this.message = '';
      try {
        await this.updateBankConsignmentSettings({
          enabled: this.consignmentForm.enabled,
          categories: this.consignmentForm.categories,
        });
        this.message = this.consignmentForm.enabled
          ? 'Комиссионка включена'
          : 'Комиссионка отключена';
        await this.refreshGameInfo();
        await this.refresh();
      } catch (e) {
        this.error = e.message || 'Не удалось сохранить настройки';
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
        this.activeMainTab = 'banks';
      } catch (e) {
        this.error = e.message || 'Не удалось закрыть банк';
      } finally {
        this.loading = false;
      }
    },
    async onCancelDeposit(contract) {
      if (!contract?.id || !window.confirm('Отменить вклад и вернуть деньги на кошелёк?')) {
        return;
      }
      await this.cancelContract('deposit', contract.id);
    },
    async onForceCloseDeposit(contract) {
      if (!contract?.id) {
        return;
      }
      const msg = 'Досрочно забрать вклад? Проценты будут потеряны, вернётся только тело.';
      if (!window.confirm(msg)) {
        return;
      }
      this.loading = true;
      this.error = '';
      this.message = '';
      try {
        await this.forceCloseDeposit(contract.id);
        this.message = 'Вклад досрочно закрыт, тело возвращено';
        await this.refreshGameInfo();
        await this.refresh();
      } catch (e) {
        this.error = e.message || 'Не удалось досрочно закрыть вклад';
      } finally {
        this.loading = false;
      }
    },
    async onCancelLoan(contract) {
      if (!contract?.id || !window.confirm('Отменить займ и вернуть сумму в банк?')) {
        return;
      }
      await this.cancelContract('loan', contract.id);
    },
    async cancelContract(kind, contractId) {
      this.loading = true;
      this.error = '';
      this.message = '';
      try {
        if (kind === 'loan') {
          await this.cancelLoan(contractId);
        } else {
          await this.cancelDeposit(contractId);
        }
        this.message = kind === 'loan' ? 'Займ отменён' : 'Вклад отменён';
        await this.refreshGameInfo();
        await this.refresh();
      } catch (e) {
        this.error = e.message || 'Не удалось отменить контракт';
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
  color: @colorText;
  text-align: left;
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

.bank_contracts_block {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid fade(@colorBlur, 25%);
}

.subsection_title {
  font-size: 12px;
  color: @colorBlur;
  margin: 8px 0 4px;
}

.owner_deposit_block {
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px solid fade(@colorBlur, 25%);

  .btn {
    margin-top: 6px;
  }
}

.consignment_block {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid fade(@colorBlur, 25%);
}

.consign_toggle,
.consign_cat {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  margin: 6px 0;
  cursor: pointer;
}

.consign_categories {
  margin: 6px 0 10px 12px;
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

.lifetime_neutral {
  color: @colorText;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
  opacity: 0.85;
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

.bank_card_head {
  align-items: center;
}

.bank_owner {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.bank_enter_btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 2px 4px;
  border: 1px solid fade(@colorBlur, 45%);
  border-radius: 4px;
  background: fade(@DarkColorBG, 60%);
  cursor: pointer;

  &:hover:not(:disabled) {
    border-color: fade(@orange, 70%);
    background: fade(@orange, 15%);
  }

  &:disabled {
    opacity: 0.5;
    cursor: default;
  }
}

.bank_card, .operation {
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
  margin-bottom: 8px;

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
