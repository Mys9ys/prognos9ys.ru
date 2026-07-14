<template>
    <PreLoader v-if="thisLoader"></PreLoader>

  <div class="rating_wrapper">
    <div class="rating_header">
      <div class="rating_title_cell"
           :class="{ activeCell: activeCell == tabId }"
           v-for="tabId in metricTabs"
           @click="activeCell = tabId"
           :key="tabId">
        <FootballMetricIcon
          context="rating"
          :field-id="tabId"
          :size="22"
          badge
          :active="activeCell == tabId"
        />
      </div>
    </div>

    <FootballRatingBody class="rating_body" :class="{'active_body':activeCell == tabId}" v-for="tabId in metricTabs"
                        :key="'body-' + tabId"
                        v-show="activeCell == tabId"
                        :arRating="footballRating[relation[tabId]]"
                        :icon="tabId"
                        :loading="isTabLoading(tabId)"
                        :has-data="hasTabData(tabId)"
                        :match-titles="footballRatingMeta?.match_titles || {}"
                        :match-numbers="availableMatchNumbers"
                        :selected-match="selectedMatch"
                        @update:selectedMatch="onSelectedMatchChange"
    />

  </div>
</template>

<script>

import {mapActions, mapState} from "vuex";
import FootballRatingBody from "@/components/football/FootballRatingBody";
import FootballMetricIcon from "@/components/football/FootballMetricIcon.vue";
import PreLoader from "@/components/main/PreLoader";


export default {
  name: "FootballRatingBlock",
  emits: ['loaded'],
  components: {
    PreLoader,
    FootballRatingBody,
    FootballMetricIcon,
  },
  props: {
    eventId: {
      type: String
    },
    setId: {
      type: [Number, String, null],
      default: null,
    },
  },
  data() {
    return {
      thisLoader: false,
      tabLoading: false,
      activeCell: 1,
      selectedMatch: '',
      loadedSlices: {},
      metricTabs: [1, 2, 18, 28, 19, 32, 21, 22, 20, 23, 45, 46, 100],
      relation: {
        1: 'all',
        2: 'score',
        18: 'result',
        28: 'diff',
        19: 'sum',
        32: 'domination',
        21: 'yellow',
        22: 'red',
        20: 'corner',
        23: 'penalty',
        45: 'otime',
        46: 'spenalty',
        100: 'best',
      },

      description: {
        1: 'Счет матча',
        18: 'Исход матча (п1 - победа первой команды, н - ничья, п2 - победа второй',
        28: 'Разница мячей забитые второй командой вычитаются из забитых первой командой',
        19: 'Сумма мячей забитых обеими командами',
        32: 'Процент владения мячом первой и второй командой',
        21: 'Количество желтых карточек в матче (сумма для обеих команд)',
        22: 'Количество красных карточек в матче (сумма для обеих команд)',
        20: 'Количество угловых в матче (сумма для обеих команд)',
        23: 'Количество пенальти в матче (сумма для обеих команд)',
        45: 'Дополнительное время (наличие/отсутствие)',
        46: 'Серия пенальти (наличие/отсутствие)',
      }
    }
  },
  created() {
    this.loadRating(this.eventId, this.relation[this.activeCell] || 'all')
  },

  watch:{
    eventId(){
      this.thisLoader = true
      this.selectedMatch = ''
      this.resetLoadedSlices()
      this.loadRating(this.eventId, this.relation[this.activeCell] || 'all')
    },
    setId(){
      this.thisLoader = true
      this.selectedMatch = ''
      this.resetLoadedSlices()
      this.loadRating(this.eventId, this.relation[this.activeCell] || 'all')
    },
    activeCell(newCell) {
      const selector = this.relation[newCell];
      if (!selector) {
        return;
      }
      const matchNumber = this.selectedMatch ? Number(this.selectedMatch) : null;
      if (this.isSliceLoaded(selector, matchNumber)) {
        return;
      }
      this.loadRating(this.eventId, selector, false, true, matchNumber);
    },
    authToken(token, prevToken) {
      if (!token || token === prevToken) {
        return;
      }
      this.thisLoader = true;
      this.selectedMatch = '';
      this.resetLoadedSlices();
      this.loadRating(this.eventId, this.relation[this.activeCell] || 'all');
    },
  },

  methods: {
    ...mapActions({
      getFootballRatings: 'rating/getFootballRatings',
    }),

    resetLoadedSlices() {
      this.loadedSlices = {};
      this.$store.commit('rating/clearFootballRatings');
    },

    sliceKey(selector, matchNumber) {
      return `${selector}:${matchNumber || 'latest'}`;
    },

    isSliceLoaded(selector, matchNumber) {
      return !!this.loadedSlices[this.sliceKey(selector, matchNumber)];
    },

    markSliceLoaded(selector, matchNumber) {
      this.loadedSlices = {
        ...this.loadedSlices,
        [this.sliceKey(selector, matchNumber)]: true,
      };
    },

    async onSelectedMatchChange(value) {
      const next = String(value || '');
      if (next === this.selectedMatch) {
        return;
      }
      this.selectedMatch = next;
      const selector = this.relation[this.activeCell] || 'all';
      const matchNumber = next ? Number(next) : null;
      if (this.isSliceLoaded(selector, matchNumber) && this.hasTourData(selector, matchNumber)) {
        return;
      }
      await this.loadRating(this.eventId, selector, false, true, matchNumber);
    },

    hasTourData(selector, matchNumber) {
      if (!matchNumber) {
        return false;
      }
      return !!(this.footballRating?.[selector]?.[matchNumber] || this.footballRating?.[selector]?.[String(matchNumber)]);
    },

    async loadRating(id, selector = 'all', showLoader = true, tabLoader = false, matchNumber = null) {
      if (showLoader) {
        this.thisLoader = true;
      }
      if (tabLoader) {
        this.tabLoading = true;
      }
      try {
        this.ratingData.event = id;
        this.ratingData.setId = this.setId ? Number(this.setId) : null;
        this.ratingData.selector = selector;
        this.ratingData.limit = 50;
        this.ratingData.matchNumber = matchNumber && matchNumber > 0 ? matchNumber : null;
        await this.getFootballRatings();

        const resolvedMatch = Number(this.footballRatingMeta?.match_number) || matchNumber || null;
        if (resolvedMatch && !this.selectedMatch) {
          this.selectedMatch = String(resolvedMatch);
        }

        this.markSliceLoaded(selector, resolvedMatch || matchNumber);
        this.$emit('loaded');
      } finally {
        if (showLoader) {
          this.thisLoader = false;
        }
        if (tabLoader) {
          this.tabLoading = false;
        }
      }
    },

    isTabLoading(index) {
      if (!this.tabLoading) {
        return false;
      }
      const selector = this.relation[index];
      return selector === this.ratingData.selector;
    },

    hasTabData(index) {
      const selector = this.relation[index];
      return !!(selector && this.footballRating[selector]);
    },
  },

  computed: {
    ...mapState({
      ratingData: state => state.rating.ratingData,
      footballRating: state => state.rating.footballRating,
      footballRatingMeta: state => state.rating.footballRatingMeta,
      authToken: state => state.auth.authData.token,
    }),
    availableMatchNumbers() {
      const fromMeta = this.footballRatingMeta?.match_numbers;
      if (Array.isArray(fromMeta) && fromMeta.length) {
        return fromMeta.map(Number).filter((n) => n > 0);
      }
      const titles = this.footballRatingMeta?.match_titles || {};
      return Object.keys(titles).map(Number).filter((n) => n > 0);
    },
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.rating_header {
  background: @DarkColorBG;
  padding: 2px;
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  gap: 2px;
  border-radius: 5px;
  margin-top: 12px;

  .rating_title_cell {
    flex: 1 1 0;
    min-width: 0;
    max-width: 24px;
    height: 22px;
    padding: 0;
    cursor: pointer;
    display: flex;
    align-items: stretch;
    justify-content: center;
    background: transparent;
    box-shadow: none;

    :deep(.football_metric_badge) {
      width: 100%;
      height: 100%;
      min-width: 0;
      min-height: 0;
    }
  }
}

.rating_body{
  display: none;
  &.active_body{
    display: block;
  }
}

</style>
