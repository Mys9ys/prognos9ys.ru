<template>
  <div class="game_page">
    <PageHeader>Казна</PageHeader>
    <ProfileTreasuryBlock :game="gameInfo" />
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader.vue';
import ProfileTreasuryBlock from '@/components/profile/ProfileTreasuryBlock.vue';
import gamePageMixin from '@/mixins/gamePageMixin';

export default {
  name: 'TreasuryPage',
  skipGameBootstrap: true,
  components: { PageHeader, ProfileTreasuryBlock },
  mixins: [gamePageMixin],
};
</script>

<style lang="less" scoped>
.game_page {
  padding-bottom: 8px;
}
</style>
