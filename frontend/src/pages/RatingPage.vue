<template>
  <PreLoader v-if="catLoader"></PreLoader>
  <div class="ratings_wrapper">
    <PageHeader class="header">Рейтинги</PageHeader>

    <div class="event_block" :class="{'small_category' : category}" v-if="mergePeriodEvent">
      <div class="el_event" v-for="(el, index) in mergePeriodEvent" :key="index">
        <div class="img_box" @click="selectRating(el.ID, el.code)">
          <img :src="url+el.img" alt="">
        </div>
      </div>
    </div>

    <div class="rating_block" v-if="eventId">
      <div class="rating_title_wrapper">
        <div class="rating_title">{{mergePeriodEvent[eventId]["NAME"]}}</div>
      </div>
      <FootballRatingBlock v-if="category === 'football'" :eventId="eventId"></FootballRatingBlock>
      <RaceRatingBlock v-if="category === 'race'" :eventId="eventId"></RaceRatingBlock>
    </div>
  </div>
</template>

<script>
import PageHeader from "@/components/main/PageHeader";
import {mapActions, mapState} from "vuex";
import PreLoader from "@/components/main/PreLoader";
import FootballRatingBlock from "@/components/football/FootballRatingBlock";
import RaceRatingBlock from "@/components/race/RaceRatingBlock";

export default {
  name: "RatingPage",
  components: {
    PageHeader,
    PreLoader,
    FootballRatingBlock,
    RaceRatingBlock
  },
  data() {
    return {
      url:  'https://prognos9ys.ru/',
      category: '',
      eventId: '',
      catLoader: false,
      mergePeriodEvent: {}
    }
  },

  created() {
    this.fillCatalogElem()
  },

  methods: {
    ...mapActions({
      getEventsInfo: 'catalog/getEventsInfo',
    }),

    async fillCatalogElem() {
      this.catLoader = true
      this.queryData['type'] = 'all'

      await this.getEventsInfo()
      this.catLoader = false

      Object.keys(this.ratingEvents).forEach((index)=>{// костыль после изменения выборки собитий с периодом old|now
        this.mergePeriodEvent = Object.assign(this.mergePeriodEvent,this.ratingEvents[index])
      })

    },

    async selectRating(id, code){
      this.catLoader = true
      this.eventId = id
      this.category = code
      this.catLoader = false
    }
  },

  computed: {
    ...mapState({      
      ratingEvents: state => state.catalog.ratingEvents,
      queryData: state => state.catalog.queryData,
      ratingData: state => state.rating.ratingData,
      footballRating: state => state.rating.footballRating,
    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.ratings_wrapper{
  .event_block{
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 4px;
    .el_event{
      background: @DarkColorBG;
      width: 19%;
      padding: 4px;
      border-radius: 5px;
      .img_box{
        cursor: pointer;
        background: @colorBlur;
        width: 100%;
        img{
          width: 100%;
        }
      }
    }

    &.small_category{
      .el_event{
        width: 40px;
      }
    }
  }

  .rating_block{
    margin-top: 25px;
  }
  .rating_title_wrapper{
    background: @DarkColorBG;
    padding: 4px;
    border-radius: 5px;
    .rating_title{
      .shadow_inset;
      color: @colorText;
    }
  }
}
</style>