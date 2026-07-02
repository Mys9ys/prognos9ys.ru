<template>
  <div class="game_page">
    <PageHeader>Работа</PageHeader>
    <ProfileFarmBlock />
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader.vue';
import ProfileFarmBlock from '@/components/profile/ProfileFarmBlock.vue';

export default {
  name: 'WorkPage',
  components: { PageHeader, ProfileFarmBlock },
};
</script>

<style lang="less" scoped>
.game_page {
  padding-bottom: 8px;
}
</style>
