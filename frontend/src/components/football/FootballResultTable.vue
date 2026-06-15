<template>
<!--  <div>-->
<!--    <p v-if="match.match_result">{{match.match_result.goal_score}}</p>-->
<!--  </div>-->
  <div class="header_line"></div>
  <table class="table table-dark table-hover football_table_box">
    <thead>
    <tr>
      <th class="pr_table_col"
          v-for="(icon, index) in icons" :key="index"
          :class="[index == 6 ? 'yellow_t' : '', index == 7 ? 'red_t' : '']"
      >
        {{icon}}
      </th>
    </tr>
    </thead>
    <tbody>
    <tr v-if="match.match_result">
      <td class="pr_table_col" v-for="(selector, index) in selectors" :key="index">{{match.match_result[selector]}}</td>
      <td class="pr_table_col"></td>
    </tr>

    <tr v-if="match.prognosis">
      <td class="pr_table_col result" v-for="(selector, index) in selectors" :key="index">{{match.prognosis[selector]}}</td>
      <td class="pr_table_col"></td>
    </tr>

    <tr v-if="match.prog_result" class="prog_r">
      <td class="pr_table_col"
          :class="{'green' : match.prog_result[selector] >0}"
          v-for="(selector, index) in selectors" :key="index">
        <span v-if="selector !== 'domination2'" >{{match.prog_result[selector]}}</span>
        <span v-else :class="{'green' : match.prog_result['domination2'] && match.prog_result['domination2'] !==0}">{{match.prog_result['domination']}}</span>
      </td>
      <td class="pr_table_col" :class="{'green' : match.prog_result.all >0}">{{match.prog_result.all}}</td>
    </tr>
    </tbody>
  </table>
  <div class="desc_block">
    <div class="cell match_res">Результат</div>
    <div class="cell prognosis">Прогноз</div>
    <div class="cell "><span class="empty">Мимо/</span><span class="ball">Баллы</span></div>
  </div>

</template>

<script>
export default {
  name: "FootballResultTable",
  props: {
    match: {
      type: Object
    }
  },
  data() {
    return {
      icons: {
        1: '0-0',
        2: '✓',  // result
        3: 'Δ',
        4: 'Σ',
        5: '🡘',
        6: '▮',
        7: '▮',
        8: '🡬',
        9: '◒',
        10: '+◔',
        11: '+◒',
        12: 'all',
      },

      selectors: {
        1: 'goal_score',
        2: 'result',
        3: 'sum',
        4: 'diff',
        5: 'domination2',
        6: 'yellow',
        7: 'red',
        8: 'corner',
        9: 'penalty',
        10: 'otime',
        11: 'spenalty',

      }
    }
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.football_table_box {
  margin: 0;
  border-radius: 5px;

  th, td {
    padding: 2px;
    font-size: 11px;
  }

  .result {
    color: @NoWrite;
  }

  .prog_r {
    td {
      color: @colorBlur;
    }

    .green {
      color: @YesWrite;
    }
  }

}
.yellow_t {
  color: @maxYellow;
}

.red_t {
  color: @maxred;
}
.header_line{
  background: @DarkColorBG;
  padding: 3px;
  border-radius:  5px 5px 0 0;
  font-size: 14px;
  display: flex;
  flex-direction: row;
  gap: 4px;
}
.desc_block{
  background: @DarkColorBG;
  padding: 4px;
  border-radius:  0 0 5px 5px;
  font-size: 14px;
  display: flex;
  flex-direction: row;
  gap: 4px;
  .cell{
    .shadow_inset;
  }
  .match_res{
    color: @colorText;
  }
  .prognosis{
    color: @NoWrite;
  }
  .empty{
    color: @colorBlur;
  }
  .ball{
    color: @YesWrite;
  }
}
</style>