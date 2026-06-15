<template>
  <div class="kvn_game_wrapper">
    <PageHeader class="header" :path="'/kvn/' + $route.params.event">Игра № {{ $route.params.number }}</PageHeader>
  </div>
</template>

<script>
import PageHeader from "@/components/main/PageHeader";

export default {
  name: "KvnGame",
  components: {
    PageHeader
  }
}
</script>

<style scoped>

</style>