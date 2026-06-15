<template>
  <PageHeader class="header">Главная</PageHeader>
  <NearestEvent></NearestEvent>

  <HumorBlock></HumorBlock>

  <NewsBlock></NewsBlock>

</template>

<script>
import PageHeader from "@/components/main/PageHeader";
import NearestEvent from "@/components/main_page/NearestEvent";
import HumorBlock from "@/components/main_page/HumorBlock";
import NewsBlock from "@/components/main_page/NewsBlock";

export default {
  name: "MainPage",
  components: {
    PageHeader,
    NearestEvent,
    HumorBlock,
    NewsBlock
  },
}
</script>

<style lang="less" scoped>

</style>