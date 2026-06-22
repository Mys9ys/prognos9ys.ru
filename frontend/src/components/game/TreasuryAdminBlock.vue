<template>
  <div class="treasury_admin" v-if="isModerator">
    <div class="treasury_row">
      <AppIcon name="bank" :size="14" />
      Казна:
      <strong>{{ formatMoney(treasury.prognobaks) }}</strong>
      <AppIcon name="prognobak" :size="14" />
      <strong>{{ formatMoney(treasury.rublius) }}</strong>
      <AppIcon name="rublius" :size="14" />
    </div>
    <div class="treasury_hint">поступления из лавки и гос. вкладов</div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import AppIcon from '@/components/ui/AppIcon.vue';

export default {
  name: 'TreasuryAdminBlock',
  components: { AppIcon },
  props: {
    treasury: {
      type: Object,
      default: null,
    },
  },
  computed: {
    ...mapState({
      userInfo: state => state.auth.userInfo,
    }),
    isModerator() {
      const role = this.userInfo?.role;
      return !!this.userInfo?.can_impersonate
          || role === 'admin'
          || role === 'super_moder';
    },
  },
  methods: {
    formatMoney(value) {
      const num = Number(value ?? 0);
      return Number.isInteger(num) ? String(num) : num.toFixed(1);
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.treasury_admin {
  margin-top: 6px;
  padding: 5px 8px;
  border-radius: 4px;
  background: rgba(80, 40, 0, 0.25);
  font-size: 12px;
  text-align: left;

  .treasury_row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;

    strong {
      color: @yellow;
    }
  }

  .treasury_hint {
    margin-top: 2px;
    font-size: 10px;
    color: @colorBlur;
  }
}
</style>
