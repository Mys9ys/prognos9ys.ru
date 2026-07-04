<template>
  <div class="equipment_page">
    <PageHeader>Экипировка</PageHeader>
    <ProfileEquipmentBlock v-if="gameInfo" :game="gameInfo" />
    <div v-else class="hint">Загрузка…</div>
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader.vue';
import ProfileEquipmentBlock from '@/components/profile/ProfileEquipmentBlock.vue';
import gamePageMixin from '@/mixins/gamePageMixin';

export default {
  name: 'EquipmentPage',
  components: { PageHeader, ProfileEquipmentBlock },
  mixins: [gamePageMixin],
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.equipment_page {
  padding-bottom: 8px;
}

.hint {
  color: @colorBlur;
  padding: 8px;
}
</style>
