<template>
  <div
      class="header_wrapper"
      :class="{
        header_wrapper_impersonating: impersonation.active,
        header_wrapper_has_banner: levelBanner.visible,
        header_wrapper_has_rewards: levelBanner.visible && levelBanner.rewards?.length,
      }"
  >
    <div class="header_absolute" :style="{ top: headerTop }">
      <div class="header_block">
        <div class="block_title">
          <slot></slot>
        </div>
        <div v-if="path" class="btn_prev" @click="$router.push(path).then(() => { this.$router.go() })">Назад</div>
        <div v-else class="btn_prev" @click="$router.go(-1)">Назад</div>
      </div>
      <LevelUpBanner />
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import LevelUpBanner from '@/components/main/LevelUpBanner.vue';

export default {
  name: "PageHeader",
  components: {
    LevelUpBanner,
  },
  props: {
    path: {
      type: String
    }
  },
  computed: {
    ...mapState({
      impersonation: state => state.auth.impersonation,
      levelBanner: state => state.game.levelBanner,
      userInfo: state => state.auth.userInfo,
    }),
    headerTop() {
      return this.impersonation.active ? '6px' : '-20px'
    },
    userId() {
      return Number(this.userInfo?.ID || 0);
    },
    currentLevel() {
      return Number(this.userInfo?.game_info?.progress?.level || 0);
    },
  },
  watch: {
    userId() {
      this.evaluateLevelBanner();
    },
    currentLevel() {
      this.evaluateLevelBanner();
    },
  },
  mounted() {
    this.evaluateLevelBanner();
  },
  methods: {
    ...mapActions({
      evaluateLevelBanner: 'game/evaluateLevelBanner',
    }),
  },
}
</script>

<style lang="less" scoped>
@import "../../assets/css/variables.less";
.header_wrapper{
  position: relative;
  height: 25px;
  z-index: 0;

  &.header_wrapper_impersonating {
    height: 32px;
  }

  &.header_wrapper_has_banner {
    height: 54px;
  }

  &.header_wrapper_has_rewards {
    height: 72px;
  }

  &.header_wrapper_impersonating.header_wrapper_has_banner {
    height: 61px;
  }

  &.header_wrapper_impersonating.header_wrapper_has_rewards {
    height: 79px;
  }

  .header_absolute{
    position: absolute;
    top:-20px;
    width: 100%;
    z-index: 0;
  }
}
  .header_block{
    width: 100%;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;

    z-index: 0;

    .block_title{
      font-weight: 500;
      font-size: 16px;
      line-height: 22px;
      /* identical to box height, or 138% */

      /* Черный */
      color: @darkbg;
    }
    .btn_prev{
      font-weight: 500;
      font-size: 14px;
      line-height: 22px;
      /* identical to box height, or 157% */

      text-align: right;

      /* Серый */
      color: @colorBack
    }
  }
</style>
