<template>
  <div class="body_wrapper">
    <div class="body_title_wrapper">
      <img
          v-if="eventImg"
          class="body_title_event_img"
          :src="url + eventImg"
          :alt="title"
      >
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
    eventImg: {
      type: String,
      default: '',
    },
  },

  data() {
    return {
      url: 'https://prognos9ys.ru/',
    };
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
      display: flex;
      flex-direction: row;
      align-items: center;
      gap: 8px;

      .body_title_event_img{
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        object-fit: contain;
        border-radius: 4px;
        background: @colorBlur;
      }

      .title{
        .shadow_inset;
        flex: 1;
        min-width: 0;
        padding: 4px 6px;
        font-size: 13px;
        line-height: 1.25;
      }
    }
  }
</style>
