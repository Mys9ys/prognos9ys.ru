<template>
  <div class="game_page">
    <PageHeader>Инвентарь</PageHeader>
    <ProfileInventoryBlock v-if="gameInfo" :game="gameInfo" />
    <div v-else class="hint">Загрузка…</div>
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader.vue';
import ProfileInventoryBlock from '@/components/profile/ProfileInventoryBlock.vue';
import gamePageMixin from '@/mixins/gamePageMixin';

export default {
  name: 'InventoryPage',
  components: { PageHeader, ProfileInventoryBlock },
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
