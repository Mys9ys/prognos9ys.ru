<template>
  <div class="game_page">
    <PageHeader>Усадьба</PageHeader>
    <EstatePageBlock />
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader.vue';
import EstatePageBlock from '@/components/estate/EstatePageBlock.vue';

export default {
  name: 'EstatePage',
  components: { PageHeader, EstatePageBlock },
};
</script>

<style lang="less" scoped>
.game_page {
  padding-bottom: 8px;
}
</style>
