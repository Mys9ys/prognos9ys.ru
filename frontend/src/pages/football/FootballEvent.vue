<template>
  <PreLoader v-if="loading"></PreLoader>
  <div v-else class="event_wrapper">
    <PageHeader class="header">Соревнование</PageHeader>
    <SectionMatches
        v-for="(arrSection, index) in arMatches"
        :arMatches="arrSection"
        :key=index
    ></SectionMatches>
  </div>
</template>

<script>

import PageHeader from "@/components/main/PageHeader";
import {mapActions, mapState} from "vuex";
import PreLoader from "@/components/main/PreLoader";
import SectionMatches from "@/components/football/SectionMatches";

export default {
  name: "FBEvent",
  components: {
    PageHeader,
    PreLoader,
    SectionMatches
  },
  data() {
    return {
      loading: false,
    }
  },
  created() {
    this.fillMatchesElem()
  },
  methods: {
    ...mapActions({
      getEventMatches: 'football/getEventMatchesRequest',
    }),

    async fillMatchesElem() {
      this.loading = true
      this.queryEvent.events = this.$route.params.event
      this.queryEvent.userToken = this.token
      await this.getEventMatches()

      this.loading = false
    }
  },
  computed: {
    ...mapState({
      queryEvent: state => state.football.queryEvent,
      token: state => state.auth.authData.token,
      arMatches: state => state.football.matches,
    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.past_title_wrapper {
  position: relative;
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;
  text-align: left;
  margin-bottom: 6px;
  margin-top: 26px;
  display: flex;
  flex-direction: row;
  justify-content: space-between;

  .past_title {
    display: inline-block;
    .shadow_inset;
  }

  .title_count {
    position: absolute;
    right: 32px;
    .shadow_inset;
    .flex_center;
    width: 24px;
    height: 24px;
    font-size: 12px;
    padding: 0;
    border: 2px solid @kvn;
    color: @kvn;
  }

  .more_btn {
    display: flex;
    flex-direction: column;
    justify-content: center;
    max-width: 24px;
    height: 24px;
    width: 24%;
    background: @valleyball;
    padding: 2px 2px;
    border-radius: 3px;
    cursor: pointer;
    .shadow_template;

    span {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }

    .close {
      transform: rotate(90deg);
    }

    .open {
      transform: rotate(-90deg);
    }

    &:hover {
      background: @colorText;
      color: @valleyball;
      border: 1px solid @valleyball;
    }
  }
}

.match_box {
  background: @BackGreenColor;

  .doted_line {
    width: 100%;
    border-bottom: 2px dotted @darkbg;
  }

  .date_title_wrapper {
    width: 20%;
    max-width: 75px;
    background: @DarkColorBG;
    color: @colorText;
    padding: 4px;
    border-radius: 5px;
    display: inline-block;

    .date_title {
      .shadow_inset;
    }
  }

  .date_match_block {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 16px;
  }
}

</style>