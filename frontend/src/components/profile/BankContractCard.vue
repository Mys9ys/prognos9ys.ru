<template>
  <div class="contract">
    <div class="contract_head">
      <span>{{ title }}</span>
      <span class="contract_amount">
        {{ contract.principal }}
        <AppIcon name="prognobak" :size="14" />
        <template v-if="kind === 'loan'">
          (к возврату {{ loanDueLabel }})
        </template>
      </span>
    </div>
    <div class="meta">
      <span v-if="contract.event_name">{{ contract.event_name }} · </span>
      <span v-if="!showClient && contract.bank_id">Банк {{ contract.bank_id }} · </span>
      <span v-if="showMatchesLeft">осталось матчей: {{ contract.matches_left }}</span>
      <span v-if="contract.created_match_label"> · {{ contract.created_match_label }}</span>
      <span v-else-if="contract.opening_match_label"> · {{ contract.opening_match_label }}</span>
      <span v-if="contract.maturity_match_label"> · {{ contract.maturity_match_label }}</span>
      <span v-if="contract.interest_status_label"> · {{ contract.interest_status_label }}</span>
      <span v-if="!showClient && contract.opened_by?.name"> · открыл: {{ contract.opened_by.name }}</span>
      <span v-if="showExtendedBadge" class="badge">продлён</span>
    </div>
    <div class="contract_actions gov_return" v-if="showGovReturn && isGovSupport">
      <button
        class="btn_close"
        type="button"
        :disabled="!canGovReturn"
        :title="contract.owner_return_hint || ''"
        @click.stop="onGovReturnClick"
      >
        Вернуть вклад
      </button>
      <span v-if="contract.owner_return_hint" class="force_hint">{{ contract.owner_return_hint }}</span>
    </div>
    <div class="contract_actions" v-else-if="contract.can_close && !showGovReturn">
      <button class="btn_close" type="button" @click.stop="$emit('close', contract)">
        Забрать вклад
      </button>
    </div>
    <div class="contract_actions" v-else-if="contract.can_cancel && showCancel">
      <button class="btn_cancel" type="button" @click.stop="$emit('cancel', contract)">
        Отменить
      </button>
    </div>
    <div class="contract_actions early_repay" v-else-if="contract.show_early_repay && showEarlyRepay">
      <button
        class="btn_repay"
        type="button"
        :disabled="!contract.can_early_repay"
        :title="contract.early_repay_hint || ''"
        @click.stop="$emit('repay', contract)"
      >
        Вернуть {{ repayButtonAmount }} <AppIcon name="prognobak" :size="12" />
      </button>
      <span v-if="contract.early_repay_hint" class="force_hint">{{ contract.early_repay_hint }}</span>
    </div>
    <div class="contract_actions force_close" v-else-if="contract.can_force_close && showForceClose">
      <button class="btn_force_close" type="button" @click.stop="$emit('force-close', contract)">
        Досрочно забрать
      </button>
      <span class="force_hint">без процентов</span>
    </div>
    <div class="user_cell" v-if="showClient && displayUserId">
      <span class="gov_badge" v-if="isGovSupport">Казна</span>
      <span class="user_ava">
        <img :src="avatarUrl" alt="">
      </span>
      <div class="user_nick">
        <span class="user_role" v-if="isGovSupport">Открыл: </span>{{ displayUserName }}
      </div>
      <div class="user_actions">
        <span
          v-if="canImpersonate"
          class="user_enter"
          title="Войти как пользователь"
          @click.stop="loginAsUser(displayUserId)"
        >
          <AppIcon name="exit_door" :size="14" />
        </span>
        <span class="user_info" title="Профиль" @click.stop="goProfile(displayUserId)">i</span>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex';
import AppIcon from '@/components/ui/AppIcon.vue';
import { DEFAULT_AVATAR_URL } from '@/utils/defaultAvatar';

export default {
  name: 'BankContractCard',
  components: { AppIcon },
  props: {
    contract: {
      type: Object,
      required: true,
    },
    kind: {
      type: String,
      default: 'deposit',
      validator: (v) => ['deposit', 'loan'].includes(v),
    },
    showClient: {
      type: Boolean,
      default: false,
    },
    showCancel: {
      type: Boolean,
      default: false,
    },
    showEarlyRepay: {
      type: Boolean,
      default: false,
    },
    showForceClose: {
      type: Boolean,
      default: false,
    },
    showGovReturn: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      url: 'https://prognos9ys.ru',
      defaultAvatar: DEFAULT_AVATAR_URL,
    };
  },
  computed: {
    ...mapGetters('auth', ['canImpersonate']),
    ...mapState('auth', ['userInfo']),
    title() {
      if (this.contract.label) {
        return this.contract.label;
      }
      const label = this.kind === 'loan' ? 'Займ' : 'Вклад';
      return `${label} #${this.contract.id}`;
    },
    loanDueLabel() {
      if (this.contract.can_cancel) {
        return this.contract.principal;
      }

      return this.contract.early_repay_due ?? this.contract.total_due;
    },
    repayButtonAmount() {
      return this.contract.early_repay_due ?? this.contract.total_due;
    },
    isGovSupport() {
      return !!(this.contract.is_gov_support || this.contract.contract_type === 'gov_support');
    },
    showExtendedBadge() {
      if (this.contract.status === 'closed') {
        return false;
      }

      if (this.contract.status === 'extended') {
        return true;
      }

      const matchesLeft = Number(this.contract.matches_left ?? 0);

      return matchesLeft <= 0 && ['active', 'interest_paid'].includes(this.contract.status);
    },
    showMatchesLeft() {
      return !this.showExtendedBadge;
    },
    canGovReturn() {
      return !!(this.contract.can_close || this.contract.can_force_close);
    },
    client() {
      return this.contract.client || null;
    },
    displayUser() {
      if (this.isGovSupport) {
        return this.contract.opened_by || null;
      }

      return this.client;
    },
    displayUserId() {
      return this.displayUser?.id || (this.isGovSupport ? 0 : (this.client?.id || this.contract.user_id || 0));
    },
    displayUserName() {
      return this.displayUser?.name || `user#${this.displayUserId}`;
    },
    clientId() {
      return this.client?.id || this.contract.user_id || 0;
    },
    clientName() {
      return this.client?.name || `user#${this.clientId}`;
    },
    avatarUrl() {
      const ava = this.displayUser?.ava || this.client?.ava;
      if (ava) {
        return ava.startsWith('http') ? ava : this.url + ava;
      }
      return this.defaultAvatar;
    },
  },
  methods: {
    ...mapActions('auth', ['impersonateStart']),
    goProfile(userId) {
      this.$router.push('/profile/' + userId);
    },
    async loginAsUser(userId) {
      try {
        await this.impersonateStart(userId);
      } catch (e) {
        console.log('loginAsUser error', e);
      }
    },
    onGovReturnClick() {
      if (!this.canGovReturn) {
        return;
      }

      this.$emit('gov-return', this.contract);
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.contract {
  margin-top: 6px;
  padding: 6px;
  background: @darkbg;
  border-radius: 4px;
  font-size: 12px;
}

.contract_head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: flex-start;
}

.contract_amount {
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.meta {
  font-size: 11px;
  color: @colorBlur;
  margin-top: 2px;
}

.badge {
  color: @orange;
  margin-left: 4px;
}

.gov_badge {
  font-size: 10px;
  color: @orange;
  border: 1px solid @orange;
  border-radius: 3px;
  padding: 2px 4px;
  flex-shrink: 0;
}

.user_role {
  color: @colorBlur;
  font-size: 11px;
}

.user_cell {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 4px;
  margin-top: 6px;

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
    flex: 1;
    min-width: 0;
    text-align: left;
    font-size: 12px;
  }

  .user_actions {
    display: flex;
    gap: 3px;
    flex-shrink: 0;
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

.contract_actions {
  margin-top: 8px;
}

.gov_return .btn_close:disabled {
  opacity: 0.45;
  cursor: default;
}

.btn_cancel {
  background: transparent;
  color: #f0a0a0;
  border: 1px solid #c44;
  border-radius: 4px;
  padding: 4px 10px;
  font-size: 11px;
  cursor: pointer;
}

.early_repay {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.btn_repay {
  background: transparent;
  color: @YesWrite;
  border: 1px solid @YesWrite;
  border-radius: 4px;
  padding: 4px 10px;
  font-size: 11px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;

  &:disabled {
    opacity: 0.45;
    cursor: default;
  }
}

.btn_close {
  background: transparent;
  color: @YesWrite;
  border: 1px solid @YesWrite;
  border-radius: 4px;
  padding: 4px 10px;
  font-size: 11px;
  cursor: pointer;
}

.force_close {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.btn_force_close {
  background: transparent;
  color: #f0c080;
  border: 1px solid @orange;
  border-radius: 4px;
  padding: 4px 10px;
  font-size: 11px;
  cursor: pointer;
}

.force_hint {
  font-size: 10px;
  color: @colorBlur;
}
</style>
