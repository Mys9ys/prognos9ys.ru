<template>
  <div class="body_wrapper">
    <div class="body_title">
      <div class="title_icon" :class="{'yellow': icon == 21, 'red': icon == 22}"><slot></slot></div>
      <div class="title_text">{{title[icon]}}</div>
    </div>
    <SelectBlockRating
        :arRating="arRating"
    ></SelectBlockRating>

  </div>
</template>

<script>
import SelectBlockRating from "@/components/football/SelectBlockRating";

export default {
  name: "FootballRatingBody",
  components: {SelectBlockRating},

  props: {
    arRating: {
      type: Object
    },
    icon: {
      type: String
    }
  },
  data(){
    return{
      title: {
        1: 'Сводный рейтинг (сумма остальных)',
        2: 'Счет матча',
        18: 'Исход матча (победа(п1,п2)/ничья)',
        28: 'Разница голов (п1-п2)',
        19: 'Сумма голов (п1+п2)',
        32: 'Владение мячом (%)',
        21: 'Желтые карточки (сумма)',
        22: 'Красные карточки (сумма)',
        20: 'Угловые (сумма)',
        23: 'Пенальти (сумма)',
        45: 'Доп. время (есть/нет)',
        46: 'Серия пенальти (есть/нет)',
        100: 'Лучшие прогнозы (>30 баллов)',
      }
    }
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.body_wrapper{
  width: 100%;
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;
  margin-top: 4px;

  .body_title{
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 4px;
    margin-bottom: 4px;
    .title_icon{
      .shadow_inset;
      min-width: 30px;
      height: 30px;
      color: @colorText;
      font-size: 20px;

      &.yellow {
        color: @maxYellow;
      }

      &.red {
        color: @maxred;
      }
    }

    .title_text{
      .shadow_inset;
      .flex_center;
      height: 30px;
    }
  }
}
</style>