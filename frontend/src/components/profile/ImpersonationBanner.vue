<template>
  <div class="impersonation_banner" v-if="impersonation.active">
    <div class="impersonation_text">
      Вы вошли как <strong>{{ userName }}</strong>
    </div>
    <div class="impersonation_btn" @click="stopImpersonation">Вернуться</div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex'

export default {
  name: 'ImpersonationBanner',
  computed: {
    ...mapState({
      impersonation: state => state.auth.impersonation,
      userInfo: state => state.auth.userInfo,
    }),
    userName() {
      return this.userInfo?.NAME || this.userInfo?.EMAIL || 'пользователь'
    },
  },
  methods: {
    ...mapActions({
      impersonateStop: 'auth/impersonateStop',
    }),
    async stopImpersonation() {
      try {
        await this.impersonateStop()
        this.$router.push('/ratings').then(() => { this.$router.go() })
      } catch (e) {
        console.log('impersonateStop error', e)
      }
    },
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.impersonation_banner {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 5px 8px;
  border-radius: 5px;
  background: @yellow;
  color: @DarkColorBG;
  font-size: 11px;
  text-align: left;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.impersonation_btn {
  flex-shrink: 0;
  padding: 4px 8px;
  border-radius: 4px;
  background: @DarkColorBG;
  color: @colorText;
  cursor: pointer;
  font-size: 11px;
}
</style>
