<template>
  <div class="body_wrapper">
    <div class="body_title_wrapper">
      <div class="title">{{ title }}</div>
    </div>

    <div class="matches_wrapper">
      <MatchTableResult
        v-for="matchNumber in visibleMatchNumbers"
        :key="matchNumber"
        :info="matches[matchNumber]"
      />
    </div>
  </div>
</template>

<script>
import MatchTableResult from "@/components/football/MatchTableResult";

export default {
  name: "ProfileEventBody",
  components: { MatchTableResult },

  props: {
    matches: {
      type: Object,
      default: () => ({}),
    },
    title: {
      type: String,
      default: '',
    },
  },

  computed: {
    matchNumbers() {
      return Object.keys(this.matches || {}).sort((a, b) => Number(b) - Number(a));
    },
    visibleMatchNumbers() {
      return this.matchNumbers.filter((matchNumber) => this.matches[matchNumber]?.matches);
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
  .body_wrapper{
    .body_title_wrapper{
      background: @DarkColorBG;
      padding: 4px;
      border-radius: 5px;
      color: @colorText;
      margin-top: 5px;
      .title{
        .shadow_inset;
      }
    }
  }
</style>
