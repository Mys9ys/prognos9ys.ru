<template>
  <div class="treasury_block">
    <PreLoader v-if="loading" />
    <template v-else>
      <div class="msg error" v-if="error">{{ error }}</div>
      <template v-else-if="treasury">
        <div class="balance_card">
          <div class="balance_row">
            <span class="label">Прогнобаки</span>
            <span class="value">
              <strong>{{ formatMoney(treasury.prognobaks) }}</strong>
              <AppIcon name="prognobak" :size="18" />
            </span>
          </div>
          <div class="balance_row">
            <span class="label">Рублиусы</span>
            <span class="value">
              <strong>{{ formatMoney(treasury.rublius) }}</strong>
              <AppIcon name="rublius" :size="18" />
            </span>
          </div>
        </div>
        <div class="hint">Поступления из лавки казны и гос. вкладов. Покупки сундуков — на странице «Рейтинги».</div>
      </template>
    </template>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import PreLoader from '@/components/main/PreLoader';
import AppIcon from '@/components/ui/AppIcon.vue';
import { apiActions } from '@/api/bitrixClient';

export default {
  name: 'ProfileTreasuryBlock',
  components: { PreLoader, AppIcon },
  data() {
    return {
      loading: false,
      treasury: null,
      error: '',
    };
  },
  computed: {
    ...mapState({
      authData: state => state.auth.authData,
    }),
  },
  created() {
    this.loadTreasury();
  },
  methods: {
    formatMoney(value) {
      const num = Number(value ?? 0);
      return Number.isInteger(num) ? String(num) : num.toFixed(1);
    },

    async loadTreasury() {
      const token = this.authData?.token;
      if (!token) {
        return;
      }

      this.loading = true;
      this.error = '';
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
        this.loading = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.treasury_block {
  text-align: left;
}

.balance_card {
  background: rgba(80, 40, 0, 0.25);
  border-radius: 5px;
  padding: 8px 10px;
}

.balance_row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  font-size: 14px;

  .label {
    color: @colorBlur;
  }

  .value {
    display: flex;
    align-items: center;
    gap: 4px;

    strong {
      color: @yellow;
      font-size: 16px;
    }
  }
}

.hint {
  margin-top: 8px;
  font-size: 11px;
  color: @colorBlur;
  line-height: 1.35;
}

.msg.error {
  color: @NoWrite;
  font-size: 12px;
}
</style>
