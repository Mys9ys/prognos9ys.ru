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
        <template v-if="myBank.deposits && myBank.deposits.length">
          <div class="subsection_title">Вклады в банке</div>
          <div class="contract" v-for="d in myBank.deposits" :key="'bd' + d.id">
            <div>Вклад #{{ d.id }} — {{ d.principal }} <AppIcon name="prognobak" :size="14" /></div>
            <div class="meta">осталось матчей: {{ d.matches_left }}
              <span v-if="d.status === 'extended'" class="badge">продлён</span>
            </div>
          </div>
        </template>
        <template v-if="myBank.loans && myBank.loans.length">
          <div class="subsection_title">Займы из банка</div>
          <div class="contract" v-for="l in myBank.loans" :key="'bl' + l.id">
            <div>Займ #{{ l.id }} — {{ l.principal }} <AppIcon name="prognobak" :size="14" /></div>
            <div class="meta">осталось матчей: {{ l.matches_left }}
              <span v-if="l.status === 'extended'" class="badge">продлён</span>
            </div>
          </div>
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
        <div class="section_title">Мои контракты</div>
        <div v-if="!contracts.deposits.length && !contracts.loans.length" class="hint">Нет активных вкладов и займов</div>
        <div class="contract" v-for="d in contracts.deposits" :key="'d' + d.id">
          <div>Вклад #{{ d.id }} — {{ d.principal }} <AppIcon name="prognobak" :size="14" /></div>
          <div class="meta">Банк {{ d.bank_id }} · осталось матчей: {{ d.matches_left }}
            <span v-if="d.status === 'extended'" class="badge">продлён</span>
          </div>
        </div>
        <div class="contract" v-for="l in contracts.loans" :key="'l' + l.id">
          <div>Займ #{{ l.id }} — {{ l.principal }} <AppIcon name="prognobak" :size="14" /> (к возврату {{ l.total_due }})</div>
          <div class="meta">Банк {{ l.bank_id }} · осталось матчей: {{ l.matches_left }}
            <span v-if="l.status === 'extended'" class="badge">продлён</span>
          </div>
        </div>
      </div>

      <div class="section">
        <div class="section_title">Каталог банков</div>
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

const DEFAULT_DEPOSIT_AMOUNT = 100;
const DEFAULT_LOAN_AMOUNT = 50;

export default {
  name: 'ProfileBankBlock',
  components: { AppIcon },
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
    };
  },
  computed: {
    ...mapState('auth', ['authData']),
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
  },
  watch: {
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
    ...mapActions('game', ['listBanks', 'getMyContracts', 'openBank', 'createDeposit', 'takeLoan', 'closeBank']),
    ...mapActions('auth', ['refreshGameInfo']),
    async refresh() {
      this.error = '';
      await Promise.all([this.loadBanks(), this.loadContracts()]);
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
        await this.createDeposit({ bankId, amount: this.depositAmount });
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
        await this.takeLoan({ bankId, amount: this.loanAmount });
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

.hint {
  font-size: 12px;
  color: @colorBlur;
  margin: 4px 0 8px;
}

.meta {
  font-size: 11px;
  color: @colorBlur;
}

.contract, .bank_card {
  margin-top: 6px;
  padding: 6px;
  background: @darkbg;
  border-radius: 4px;
  font-size: 12px;
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
