<template>
  <div class="table_wrapper">
    <table class="table table-dark table-hover om_table_box">
      <thead>
      <tr>
        <th class="pr_table_col">{{ m_icons[1] }}</th>
        <th class="pr_table_col">{{ m_icons[18] }}</th>
        <th class="pr_table_col">{{ m_icons[19] }}</th>
        <th class="pr_table_col">{{ m_icons[28] }}</th>
        <th class="pr_table_col">{{ m_icons[32] }}</th>
        <th class="pr_table_col yellow_t">{{ m_icons[21] }}</th>
        <th class="pr_table_col red_t">{{ m_icons[22] }}</th>
        <th class="pr_table_col">{{ m_icons[20] }}</th>
        <th class="pr_table_col">{{ m_icons[23] }}</th>
        <th class="pr_table_col">{{ m_icons[45] }}</th>
        <th class="pr_table_col">{{ m_icons[46] }}</th>
        <th class="pr_table_col">all</th>

      </tr>
      </thead>
      <tbody>
      <tr>

        <td class="pr_table_col">{{ info.matches.goal_home }} - {{ info.matches.goal_guest }}</td>
        <td class="pr_table_col">{{ info.matches.result }}</td>
        <td class="pr_table_col">{{ info.matches.sum }}</td>
        <td class="pr_table_col">{{ info.matches.diff }}</td>
        <td class="pr_table_col">{{ info.matches.domination }} - {{ 100 - info.matches.domination }}</td>
        <td class="pr_table_col">{{ info.matches.yellow }}</td>
        <td class="pr_table_col">{{ info.matches.red }}</td>
        <td class="pr_table_col">{{ info.matches.corner }}</td>
        <td class="pr_table_col">{{ info.matches.penalty }}</td>
        <td class="pr_table_col">{{ info.matches.otime }}</td>
        <td class="pr_table_col">{{ info.matches.spenalty }}</td>
        <td class="pr_table_col"></td>

      </tr>

      <tr>

        <td class="pr_table_col result">{{ info.prognosis.goal_home }} - {{ info.prognosis.goal_guest }}</td>
        <td class="pr_table_col result">{{ info.prognosis.result }}</td>
        <td class="pr_table_col result">{{ info.prognosis.sum }}</td>
        <td class="pr_table_col result">{{ info.prognosis.diff }}</td>
        <td class="pr_table_col result">{{ info.prognosis.domination }} - {{ 100 - info.prognosis.domination }}</td>
        <td class="pr_table_col result">{{ info.prognosis.yellow }}</td>
        <td class="pr_table_col result">{{ info.prognosis.red }}</td>
        <td class="pr_table_col result">{{ info.prognosis.corner }}</td>
        <td class="pr_table_col result">{{ info.prognosis.penalty }}</td>
        <td class="pr_table_col result">{{ info.prognosis.otime }}</td>
        <td class="pr_table_col result">{{ info.prognosis.spenalty }}</td>
        <td class="pr_table_col result"></td>

      </tr>

      <tr class="prog_r">

        <td class="pr_table_col" :class="{'green' : info.result.score >0}">{{ info.result.score }}</td>
        <td class="pr_table_col" :class="{'green' : info.result.result >0}">{{ info.result.result }}</td>
        <td class="pr_table_col" :class="{'green' : info.result.sum >0}">{{ info.result.sum }}</td>
        <td class="pr_table_col" :class="{'green' : info.result.diff >0}">{{ info.result.diff }}</td>
        <td class="pr_table_col" :class="{'green' : info.result.domination >0}">{{ info.result.domination }}</td>
        <td class="pr_table_col" :class="{'green' : info.result.yellow >0}">{{ info.result.yellow }}</td>
        <td class="pr_table_col" :class="{'green' : info.result.red >0}">{{ info.result.red }}</td>
        <td class="pr_table_col" :class="{'green' : info.result.corner >0}">{{ info.result.corner }}</td>
        <td class="pr_table_col" :class="{'green' : info.result.penalty >0}">{{ info.result.penalty }}</td>
        <td class="pr_table_col" :class="{'green' : info.result.otime >0}">{{ info.result.otime }}</td>
        <td class="pr_table_col" :class="{'green' : info.result.spenalty >0}">{{ info.result.spenalty }}</td>
        <td class="pr_table_col" :class="{'green' : info.result.all >0}">{{ info.result.all }}</td>

      </tr>

      </tbody>
    </table>
    <div class="desc_block">
      <div class="cell match_res">Результат</div>
      <div class="cell prognosis">Прогноз</div>
      <div class="cell "><span class="empty">Мимо/</span><span class="ball">Баллы</span></div>
    </div>
  </div>
</template>

<script>
import {mapState} from "vuex";

export default {
  name: "MatchTable",

  props: {
    info:{
      type: Object
    }
  },

  computed: {
    ...mapState({
      m_icons: state => state.icons.m_icons,    
    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.om_table_box {
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