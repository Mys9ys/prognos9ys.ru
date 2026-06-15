<template>
  <PreLoader v-if="loading"></PreLoader>
  <div v-else class="event_wrapper">
    <PageHeader class="header">Соревнование</PageHeader>
    <SectionRace
        v-for="(arrSection, index) in arElements"
        :arElements="arrSection"
        :key=index
    ></SectionRace>
  </div>
</template>

<script>
import PageHeader from "@/components/main/PageHeader";
import PreLoader from "@/components/main/PreLoader";
import SectionRace from "@/components/race/SectionRace";
import {mapActions, mapState} from "vuex";

export default {
  name: "RaceEvent",
  components: {
    SectionRace,
    PageHeader,
    PreLoader,
  },
  data() {
    return {
      loading: false,
    }
  },

  created() {
    this.fillElems()
  },

  methods: {
    ...mapActions({
      getEventElements: 'race/getEventElements',
    }),

    async fillElems() {
      this.loading = true
      this.queryEvent.events = this.$route.params.event
      this.queryEvent.userToken = this.token
      await this.getEventElements()

      this.loading = false
    }
  },

  computed: {
    ...mapState({
      queryEvent: state => state.race.queryEvent,
      token: state => state.auth.authData.token,
      arElements: state => state.race.elements,
    })
  },

}
</script>

<style lang="less" scoped>

</style>