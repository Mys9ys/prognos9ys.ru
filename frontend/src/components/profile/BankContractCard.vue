<template>
  <div class="contract">
    <div class="contract_head">
      <span>{{ title }}</span>
      <span class="contract_amount">
        {{ contract.principal }}
        <AppIcon name="prognobak" :size="14" />
        <template v-if="kind === 'loan'">
          (к возврату {{ contract.total_due }})
        </template>
      </span>
    </div>
    <div class="meta">
      <span v-if="contract.event_name">{{ contract.event_name }} · </span>
      <span v-if="!showClient && contract.bank_id">Банк {{ contract.bank_id }} · </span>
      осталось матчей: {{ contract.matches_left }}
      <span v-if="contract.created_match_label"> · {{ contract.created_match_label }}</span>
      <span v-else-if="contract.opening_match_label"> · {{ contract.opening_match_label }}</span>
      <span v-if="contract.maturity_match_label"> · {{ contract.maturity_match_label }}</span>
      <span v-if="contract.status === 'extended'" class="badge">продлён</span>
    </div>
    <div class="contract_actions" v-if="contract.can_cancel && showCancel">
      <button class="btn_cancel" type="button" @click.stop="$emit('cancel', contract)">
        Отменить
      </button>
    </div>
    <div class="user_cell" v-if="showClient && clientId">
      <span class="user_ava">
        <img :src="avatarUrl" alt="">
      </span>
      <div class="user_nick">{{ clientName }}</div>
      <div class="user_actions">
        <span
          v-if="canImpersonate"
          class="user_enter"
          title="Войти как пользователь"
          @click.stop="loginAsUser(clientId)"
        >
          <AppIcon name="exit_door" :size="14" />
        </span>
        <span class="user_info" title="Профиль" @click.stop="goProfile(clientId)">i</span>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
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
  },
  data() {
    return {
      url: 'https://prognos9ys.ru',
      defaultAvatar: DEFAULT_AVATAR_URL,
    };
  },
  computed: {
    ...mapState('auth', ['userInfo']),
    title() {
      const label = this.kind === 'loan' ? 'Займ' : 'Вклад';
      return `${label} #${this.contract.id}`;
    },
    client() {
      return this.contract.client || null;
    },
    clientId() {
      return this.client?.id || this.contract.user_id || 0;
    },
    clientName() {
      return this.client?.name || `user#${this.clientId}`;
    },
    avatarUrl() {
      const ava = this.client?.ava;
      if (ava) {
        return ava.startsWith('http') ? ava : this.url + ava;
      }
      return this.defaultAvatar;
    },
    canImpersonate() {
      const role = this.userInfo?.role;
      return !!this.userInfo?.can_impersonate
        || role === 'admin'
        || role === 'super_moder';
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
        this.$router.push('/').then(() => { this.$router.go(); });
      } catch (e) {
        console.log('loginAsUser error', e);
      }
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

.btn_cancel {
  background: transparent;
  color: #f0a0a0;
  border: 1px solid #c44;
  border-radius: 4px;
  padding: 4px 10px;
  font-size: 11px;
  cursor: pointer;
}
</style>
