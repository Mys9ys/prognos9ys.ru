<template>
  <div class="game_page">
    <PageHeader>Награды</PageHeader>
    <ProfileSeasonAwardsBlock />
    <ProfileAchievementBlock />
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader.vue';
import ProfileAchievementBlock from '@/components/achievement/ProfileAchievementBlock.vue';
import ProfileSeasonAwardsBlock from '@/components/profile/ProfileSeasonAwardsBlock.vue';

export default {
  name: 'AchievementsPage',
  components: { PageHeader, ProfileAchievementBlock, ProfileSeasonAwardsBlock },
};
</script>

<style lang="less" scoped>
.game_page {
  padding-bottom: 8px;
}
</style>
