<template>
  <PageHeader class="header">Новости</PageHeader>
  <div class="title_wrapper news">
    <div class="title">Все новости проекта</div>
  </div>
  <NewsElement></NewsElement>
</template>

<script>
import PageHeader from "@/components/main/PageHeader";
import NewsElement from "@/components/ui/NewsElement";

export default {
  name: "NewsPage",
  components: {
    PageHeader,
    NewsElement
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.title_wrapper{
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  margin-top: 25px;
  text-align: left;
  margin-bottom: 10px;
  .title{
    .shadow_inset;
  }
}
.news{
  background: @kerling;
}
</style>