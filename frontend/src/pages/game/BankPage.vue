<template>
  <div class="game_page">
    <PageHeader>Банк</PageHeader>
    <ProfileBankBlock v-if="gameInfo" :game="gameInfo" />
    <div v-else class="hint">Загрузка…</div>
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader.vue';
import ProfileBankBlock from '@/components/profile/ProfileBankBlock.vue';
import gamePageMixin from '@/mixins/gamePageMixin';

export default {
  name: 'BankPage',
  components: { PageHeader, ProfileBankBlock },
  mixins: [gamePageMixin],
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.game_page {
  padding-bottom: 8px;
}

.hint {
  color: @colorBlur;
  padding: 12px 4px;
}
</style>
