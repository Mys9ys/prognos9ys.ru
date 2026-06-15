<template>
  <div class="exist" v-if="dataBlock.exist">
    <div class="title_wrapper">
      <div class="title">{{dataBlock.title}}. Результат.</div>
    </div>
    <table class="table table-dark table-hover race_table_box" v-if="result">
      <thead>
      <tr>
        <th class="pr_table_col"><div class="title_wrapper"><div class="title">№</div></div></th>
        <th class="pr_table_col"><div class="title_wrapper"><div class="title">Протокол</div></div></th>
        <th class="pr_table_col"><div class="title_wrapper"><div class="title">Ставка</div></div></th>
        <th class="pr_table_col"><div class="title_wrapper"><div class="title">Баллы</div></div></th>
      </tr>
      </thead>
      <tbody>
      <tr v-for="(el, place) in result"  :key="place">
        <td class="pr_table_col"><div class="place_wrapper"><div class="place">{{place+1}}</div></div></td>
        <td class="pr_table_col">
          <RacerItem
              :place="place"
              :item="racers[el]"
          ></RacerItem>
        </td>
        <td class="pr_table_col">
          <RacerItem
              v-if="racers[prognosis[place]]"
              :place="place"
              :item="racers[prognosis[place]]"
              :score = "dataBlock.type + String(score[place]).replace('.', '-')"
          ></RacerItem>
          <div v-else>не заполнено</div>
        </td>
        <td class="pr_table_col">
          <div class="racer_score_wrapper" :class="dataBlock.type + String(score[place]).replace('.', '-')">
            <div class="racer_score">{{score[place]}}</div>
          </div>
        </td>
      </tr>
      <tr>
        <td colspan=3 class="sum_cell"> <div class="sum_wrapper"><div class="sum">Сумма баллов</div></div></td>
        <td><div class="score_wrapper"><div class="score">{{sum}}</div></div></td>
      </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import RacerItem from "@/components/race/RacerItem";

export default {
  name: "ResultRaceBlock",
  components: {
    RacerItem
  },
  props: {
    dataBlock: {
      type: Object
    },
    racers: {
      type: Object
    },
    prognosis: {
      type: Object
    },
    result: {
      type: Object
    },
    score: {
      type: Object
    },
    raceInfo: {
      type: Object
    },
  },
  data(){
    return{
      sum: this.score.reduce((partialSum, a) => partialSum + a, 0)
    }
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.title_wrapper{
  display: flex;
  flex-direction: row;
  gap: 4px;
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;

  .title{
    .shadow_inset;
    .flex_center;
  }
}

.race_table_box {
  border-radius: 5px;

  th, td {
    padding: 2px;
    font-size: 12px;
    text-align: left;
  }
  .td{
    //.flex_center;
  }
  .sum_cell{
    text-align: right;
  }

  .title_wrapper{
    padding: 2px
  }

}
.place_wrapper{
  display: inline-block;
  border-radius: 5px;
  padding: 2px;

  background: @hockei;

  .place{
    .shadow_inset;
    .flex_center;
    font-size: 13px;
    min-width: 24px;
  }
}

.score_wrapper{
  display: inline-block;
  border-radius: 5px;
  padding: 2px;

  background: @colorText2;

  .score{
    .shadow_inset;
    .flex_center;
    font-size: 13px;
    min-width: 45px;
  }
}
.sum_wrapper{
  display: inline-block;
  border-radius: 5px;
  padding: 2px;

  background: @colorText2;

  .sum{
    .shadow_inset;
    .flex_center;
    font-size: 13px;
    min-width: 45px;

  }
}
.racer_score_wrapper{
  display: inline-block;
  border-radius: 5px;
  padding: 2px;

  background: @hockei;

  .racer_score{
    .shadow_inset;
    .flex_center;
    font-size: 13px;
    min-width: 45px;
  }
}

.qual0-5, .sprint0-5, .race0-5{
  background: @yellowblur;
  color: @colorText;
}
.qual0, .sprint0, .race0{
  filter: grayscale(100%);
}
.qual1, .sprint1, .race1{
  background: @greenblur;
}
.qual2, .sprint2, .race3{
  background: @colorText2;
}
.qual3, .sprint3, .race5, .best_lap5{
  background: @green;
}
</style>