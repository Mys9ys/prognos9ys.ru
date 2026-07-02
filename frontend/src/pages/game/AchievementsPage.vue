<template>
  <div class="game_page">
    <PageHeader>Награды</PageHeader>
    <ProfileAchievementBlock />
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader.vue';
import ProfileAchievementBlock from '@/components/achievement/ProfileAchievementBlock.vue';

export default {
  name: 'AchievementsPage',
  components: { PageHeader, ProfileAchievementBlock },
};
</script>

<style lang="less" scoped>
.game_page {
  padding-bottom: 8px;
}
</style>
