<template>
  <div class="treasury_block">
    <div class="msg error" v-if="error">{{ error }}</div>
    <div class="msg ok" v-if="message">{{ message }}</div>

    <PreLoader v-if="treasuryLoading" />
    <div class="section" v-else-if="treasury">
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

    <div class="section" v-if="contractEvents.length">
      <div class="section_title">Гос. вклад поддержки</div>
      <p class="hint">
        500 <AppIcon name="prognobak" :size="14" /> с кошелька пополняют ликвидность выбранного банка (господдержка).
        После 5 туров 5% поступают в казну. Тело вклада — кнопкой «Забрать вклад».
      </p>

      <div class="event_pick" v-if="contractEvents.length > 1">
        <div class="event_pick_label">Соревнование</div>
        <select v-model.number="selectedEventId" class="event_select">
          <option v-for="ev in contractEvents" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
        </select>
      </div>

      <div v-if="govDeposits.length">
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
      <template v-else-if="banks.length">
        <select v-model.number="selectedGovBankId" class="event_select">
          <option v-for="b in banks" :key="b.id" :value="b.id">Банк #{{ b.id }} ({{ b.owner_name }})</option>
        </select>
        <button class="btn small" :disabled="actionLoading || !selectedGovBankId" @click="onCreateGovDeposit">
          Открыть гос. вклад 500 <AppIcon name="prognobak" :size="14" />
        </button>
      </template>
      <div v-else class="hint">Сначала откройте банк во вкладке «Финансы» или дождитесь появления банков в каталоге</div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
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
      treasuryLoading: false,
      actionLoading: false,
      treasury: null,
      banks: [],
      govDeposits: [],
      selectedGovBankId: 0,
      selectedEventId: 0,
      error: '',
      message: '',
    };
  },
  computed: {
    ...mapState({
      authData: state => state.auth.authData,
    }),
    bankInfo() {
      return this.game?.bank || {};
    },
    myBank() {
      return this.bankInfo.my_bank || null;
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

    async refresh() {
      this.error = '';
      await Promise.all([
        this.loadTreasury(),
        this.loadBanks(),
        this.loadGovDeposits(),
      ]);
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
        const res = await this.listBanks();
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

    async onCreateGovDeposit() {
      if (!this.selectedGovBankId) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        await this.createGovSupportDeposit({
          bankId: this.selectedGovBankId,
          eventId: this.selectedEventId,
        });
        this.message = 'Гос. вклад поддержки открыт';
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

.hint {
  font-size: 12px;
  color: @colorBlur;
  margin: 4px 0 8px;
  line-height: 1.35;
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
