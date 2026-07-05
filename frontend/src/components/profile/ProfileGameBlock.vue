<template>
  <div class="game_block" v-if="game">
    <div class="game_row">
      <div class="label">Уровень</div>
      <div class="value">{{ progress.level }} — {{ progress.title }}</div>
    </div>
    <div class="game_row">
      <div class="label">Опыт</div>
      <div class="value">{{ progress.xp }}</div>
    </div>
    <div class="progress_bar" v-if="progress.next_min_xp">
      <div class="progress_fill" :style="{ width: progress.progress_percent + '%' }"></div>
    </div>
    <div class="game_row small" v-if="progress.next_min_xp">
      <div class="label">До {{ progress.next_level }} ур.</div>
      <div class="value">{{ progress.xp_to_next }} XP</div>
    </div>
    <div class="wallet_row">
      <div class="coin prognobaks">
        <AppIcon name="prognobak" :size="20" />
        <span>{{ wallet.prognobaks }}</span>
      </div>
      <div class="coin rublius">
        <AppIcon name="rublius" :size="20" />
        <span>{{ wallet.rublius }}</span>
      </div>
    </div>
    <div class="starter_loan" v-if="starterLoan.can_take">
      <button
        type="button"
        class="starter_loan_btn"
        :disabled="loanLoading"
        @click="onTakeStarterLoan"
      >
        Заём {{ starterLoan.amount }}
        <AppIcon name="prognobak" :size="16" />
      </button>
      <div class="starter_loan_hint" v-if="starterLoan.hint">{{ starterLoan.hint }}</div>
    </div>
    <div class="msg error" v-if="loanError">{{ loanError }}</div>
    <div class="msg ok" v-if="loanMessage">{{ loanMessage }}</div>
    <div class="bank_hint" v-if="bank.has_bank || bank.active_deposits || bank.active_loans">
      <span v-if="bank.has_bank" class="bank_hint_line">
        <AppIcon name="bank" :size="16" /> Мой банк
      </span>
      <span v-else-if="bank.can_open" class="bank_hint_line">
        <AppIcon name="bank" :size="16" /> Можно открыть банк
      </span>
      <span v-if="bank.active_deposits"> · вкладов: {{ bank.active_deposits }}</span>
      <span v-if="bank.active_loans"> · займов: {{ bank.active_loans }}</span>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import AppIcon from '@/components/ui/AppIcon.vue';

export default {
  name: 'ProfileGameBlock',
  components: { AppIcon },
  props: {
    game: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      loanLoading: false,
      loanError: '',
      loanMessage: '',
    };
  },
  computed: {
    wallet() {
      return this.game?.wallet || { prognobaks: 0, rublius: 0 };
    },
    progress() {
      return this.game?.progress || {
        level: 0,
        title: 'Новичок',
        xp: 0,
        progress_percent: 0,
        xp_to_next: 100,
        next_level: 1,
      };
    },
    bank() {
      return this.game?.bank || {
        has_bank: false,
        can_open: false,
        active_deposits: 0,
        active_loans: 0,
      };
    },
    starterLoan() {
      return this.bank?.starter_loan || {
        can_take: false,
        amount: 500,
      };
    },
  },
  methods: {
    ...mapActions('game', ['takeStarterLoan']),
    async onTakeStarterLoan() {
      if (this.loanLoading) {
        return;
      }

      this.loanLoading = true;
      this.loanError = '';
      this.loanMessage = '';

      try {
        await this.takeStarterLoan();
        this.loanMessage = `Займ ${this.starterLoan.amount} 🪙 выдан`;
      } catch (error) {
        this.loanError = error?.message || 'Не удалось взять займ';
      } finally {
        this.loanLoading = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.game_block {
  background: @DarkColorBG;
  color: @colorText;
  padding: 8px;
  border-radius: 5px;
  margin: 8px 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  text-align: left;
}

.game_row {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  .shadow_inset;
  padding: 4px 6px;
  font-size: 13px;

  .label {
    color: @colorBlur;
  }

  &.small {
    font-size: 11px;
  }
}

.progress_bar {
  height: 8px;
  background: @darkbg;
  border-radius: 4px;
  overflow: hidden;

  .progress_fill {
    height: 100%;
    background: @orange;
    transition: width 0.3s ease;
  }
}

.wallet_row {
  display: flex;
  flex-direction: row;
  gap: 8px;

  .coin {
    flex: 1;
    .shadow_inset;
    padding: 6px;
    text-align: center;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
  }
}

.starter_loan {
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: stretch;
}

.starter_loan_btn {
  .shadow_inset;
  border: none;
  background: @orange;
  color: @colorText;
  padding: 8px 10px;
  border-radius: 4px;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;

  &:disabled {
    opacity: 0.6;
    cursor: default;
  }
}

.starter_loan_hint {
  font-size: 11px;
  color: @colorBlur;
  text-align: center;
}

.msg {
  font-size: 12px;
  text-align: center;
  padding: 4px;

  &.error {
    color: #f88;
  }

  &.ok {
    color: #8f8;
  }
}

.bank_hint {
  font-size: 12px;
  color: @colorBlur;
  text-align: center;
  padding: 4px;
}

.bank_hint_line {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
</style>
