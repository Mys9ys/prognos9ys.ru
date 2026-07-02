<template>
  <div class="game_page">
    <PageHeader>Биржа</PageHeader>
    <ExchangeBlock />
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader.vue';
import ExchangeBlock from '@/components/game/ExchangeBlock.vue';

export default {
  name: 'MarketPage',
  components: { PageHeader, ExchangeBlock },
};
</script>

<style lang="less" scoped>
.game_page {
  padding-bottom: 8px;
}
</style>
