<template>
    <PreLoader v-if="thisLoader"></PreLoader>

  <div class="rating_wrapper">
    <div class="rating_header">
      <div class="rating_title_cell"
           :class="{'yellow': index == 21, 'red': index == 22, 'activeCell': activeCell == index}"
           v-for="(icon, index) in icons"
           @click="activeCell = index"
           :key="index">{{ icon }}
      </div>
    </div>

    <FootballRatingBody class="rating_body" :class="{'active_body':activeCell == index}" v-for="(icon, index) in icons"
                        :key="index"
                        :arRating="footballRating[relation[index]]"
                        :icon="index"
    >{{ icon }}
    </FootballRatingBody>

  </div>
</template>

<script>

import {mapActions, mapState} from "vuex";
import FootballRatingBody from "@/components/football/FootballRatingBody";
import PreLoader from "@/components/main/PreLoader";


export default {
  name: "FootballRatingBlock",
  components: {
    PreLoader,
    FootballRatingBody
  },
  props: {
    eventId: {
      type: String
    }
  },
  data() {
    return {
      thisLoader: false,
      activeCell: 1,
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

      icons: {
        1: '♛',
        2: '0-0',
        18: '✓',  // result
        28: 'Δ',
        19: 'Σ',
        32: '🡘',
        21: '▮',
        22: '▮',
        20: '🡬',
        23: '◒',
        45: '+◔',
        46: '+◒',
        100: '♚',
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
    this.loadRating(this.eventId)
  },

  watch:{
    eventId(){
      this.thisLoader = true
      this.loadRating(this.eventId)
    }
  },

  methods: {
    ...mapActions({
      getFootballRatings: 'rating/getFootballRatings',
    }),

    async loadRating(id) {
      this.ratingData.event = id
      await this.getFootballRatings()
      this.thisLoader = false
    }
  },

  computed: {
    ...mapState({
      ratingData: state => state.rating.ratingData,
      footballRating: state => state.rating.footballRating,

    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.rating_header {
  background: @DarkColorBG;
  padding: 4px;
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  gap: 3px;
  border-radius: 5px;
  margin-top: 4px;

  .rating_title_cell {
    min-width: 23px;
    cursor: pointer;
    .shadow_inset;
    color: @colorText;

    &.yellow {
      color: @maxYellow;
    }

    &.red {
      color: @maxred;
    }

    &.activeCell {
      background: @colorBlur;
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