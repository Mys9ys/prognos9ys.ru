<template>
  <RaceTable v-if="$route.params.type === 'race'"></RaceTable>
  <FootballTable v-if="$route.params.type === 'football'"></FootballTable>
</template>

<script>
import RaceTable from "@/components/championship/RaceTable";
import FootballTable from "@/components/championship/FootballTable";

export default {
  name: "ChampionshipPage",
  components: {
    FootballTable,
    RaceTable
  }
}
</script>

<style scoped>

</style>