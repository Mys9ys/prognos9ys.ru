<template>
  <PreLoader v-if="loading"></PreLoader>
  <div v-else class="event_wrapper">
    <PageHeader class="header">CS2</PageHeader>
    <SectionMatches
      v-for="(arrSection, index) in arMatches"
      :arMatches="arrSection"
      :key="index"
    />
  </div>
</template>

<script>
import PageHeader from "@/components/main/PageHeader";
import { mapActions, mapState } from "vuex";
import PreLoader from "@/components/main/PreLoader";
import SectionMatches from "@/components/football/SectionMatches";

export default {
  name: "Cs2Event",
  components: {
    PageHeader,
    PreLoader,
    SectionMatches,
  },
  data() {
    return {
      loading: false,
    };
  },
  created() {
    this.fillMatchesElem();
  },
  methods: {
    ...mapActions({
      getEventMatches: 'cs2/getEventMatchesRequest',
    }),

    async fillMatchesElem() {
      this.loading = true;
      this.queryEvent.events = this.$route.params.event;
      this.queryEvent.userToken = this.token;
      await this.getEventMatches();
      this.loading = false;
    },
  },
  computed: {
    ...mapState({
      queryEvent: state => state.cs2.queryEvent,
      token: state => state.auth.authData.token,
      arMatches: state => state.cs2.matches,
    }),
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.event_wrapper {
  padding-bottom: 12px;
}
</style>
