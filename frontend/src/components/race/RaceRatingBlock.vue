<template>
  <PreLoader v-if="thisLoader"></PreLoader>

  <div class="rating_wrapper">
    <div class="rating_header">
      <div class="rating_title_cell"
           :class="{'activeCell': activeCell == index}"
           v-for="(icon, index) in icons"
           @click="activeCell = index"
           :key="index">{{ icon }}
      </div>
    </div>

    <RaceRatingBody class="rating_body" :class="{'active_body':activeCell == index}" v-for="(icon, index) in icons"
                        :key="index"
                        :arRating="raceRating[relation[index]]"
                        :icon="index"
    >{{ icon }}
    </RaceRatingBody>

  </div>
</template>

<script>
import PreLoader from "@/components/main/PreLoader";
import {mapActions, mapState} from "vuex";
import RaceRatingBody from "@/components/race/RaceRatingBody";

export default {
  name: "RaceRatingBlock",
  components: {
    PreLoader,
    RaceRatingBody
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
        2: 'qual_sum',
        3: 'sprint_sum',
        4: 'race_sum',
        5: 'best_lap',
        100: 'best',
      },

      icons: {
        1: '♛',
        5: '◒',
        4: 'Σ',
        2: '🡘',
        3: '🡬',
        100: '♚',
      },

      description: {
        1: 'Сводный рейтинг',
        5: 'Лучший круг',
        4: 'Баллы за гонку',
        3: 'Баллы за спринт',
        2: 'Баллы за квалификацию',
        100: 'Лучшие прогнозы',
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
      getRaceRatings: 'rating/getRaceRatings',
    }),

    async loadRating(id) {
      this.ratingData.events = id
      await this.getRaceRatings()
      this.thisLoader = false
    }
  },

  computed: {
    ...mapState({
      ratingData: state => state.rating.ratingData,
      raceRating: state => state.rating.raceRating,
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