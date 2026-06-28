<template>
  <div class="impersonation_banner" v-if="impersonation.active">
    <div v-if="stopping" class="impersonation_loader">
      <PreLoader />
    </div>

    <div class="impersonation_text">
      Вы вошли как <strong>{{ userName }}</strong>
    </div>
    <div
      class="impersonation_btn"
      :class="{ impersonation_btn_busy: stopping }"
      @click="stopImpersonation"
    >
      {{ stopping ? 'Выход…' : 'Вернуться' }}
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex'
import PreLoader from '@/components/main/PreLoader.vue'

export default {
  name: 'ImpersonationBanner',
  components: { PreLoader },
  data() {
    return {
      stopping: false,
    }
  },
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
      if (this.stopping) {
        return
      }

      this.stopping = true
      try {
        await this.impersonateStop()
        if (this.$route.path !== '/ratings') {
          await this.$router.replace('/ratings')
        }
      } catch (e) {
        console.log('impersonateStop error', e)
      } finally {
        this.stopping = false
      }
    },
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.impersonation_banner {
  position: relative;
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

.impersonation_loader {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: fade(@YesWrite, 92%);

  :deep(.wrapper_loader) {
    position: fixed;
    inset: 0;
    left: 0;
    transform: none;
    max-width: none;
    margin: 0;
    background: transparent;
  }
}

.impersonation_btn {
  flex-shrink: 0;
  padding: 4px 8px;
  border-radius: 4px;
  background: @DarkColorBG;
  color: @colorText;
  cursor: pointer;
  font-size: 11px;

  &_busy {
    opacity: 0.65;
    pointer-events: none;
  }
}
</style>
