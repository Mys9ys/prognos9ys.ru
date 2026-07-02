<template>
  <div class="game_page">
    <PageHeader>Настройки</PageHeader>
    <ImpersonationPanel />
    <div class="settings_item" @click="logoutProfile">
      <div class="icon">
        <AppIcon name="exit_door" :size="20" />
      </div>
      <div class="title">Выйти из приложения</div>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import PageHeader from '@/components/main/PageHeader.vue';
import ImpersonationPanel from '@/components/profile/ImpersonationPanel.vue';
import AppIcon from '@/components/ui/AppIcon.vue';

export default {
  name: 'SettingsPage',
  components: { PageHeader, ImpersonationPanel, AppIcon },
  methods: {
    ...mapActions({
      logoutVue: 'auth/logoutVue',
    }),
    logoutProfile() {
      this.logoutVue();
      this.$router.push('/').then(() => { this.$router.go(); });
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.game_page {
  padding-bottom: 8px;
}

.settings_item {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 8px;
  background: @DarkColorBG;
  padding: 8px;
  border-radius: 5px;
  margin-top: 8px;
  cursor: pointer;

  .title {
    color: @colorText;
  }
}
</style>
