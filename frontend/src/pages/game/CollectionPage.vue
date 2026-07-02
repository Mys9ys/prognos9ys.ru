<template>
  <div class="game_page">
    <PageHeader>Коллекция</PageHeader>
    <ProfileAlbumBlock />
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader.vue';
import ProfileAlbumBlock from '@/components/profile/ProfileAlbumBlock.vue';

export default {
  name: 'CollectionPage',
  components: { PageHeader, ProfileAlbumBlock },
};
</script>

<style lang="less" scoped>
.game_page {
  padding-bottom: 8px;
}
</style>
