<template>
  <PreLoader v-if="loader"></PreLoader>
    <div class="prognosis_block">
      <div class="part_block">
        <div class="title_block">
          <div class="item icon">{{ icons[1] }}</div>
          <div class="item title">{{ title[1] }}:</div>
        </div>
        <div class="value_block">
          <div class="goal_block">
            <div class="zero goal_btn" @click="setGoals('zero', 7)">0</div>
            <div class="minus goal_btn" @click="setGoals('minus', 7)">-</div>
            <div class="value">{{ data[7] }}</div>
            <div class="plus goal_btn" @click="setGoals('plus', 7)">+</div>
            <div class="two goal_btn" @click="setGoals('two', 7)">2</div>
          </div>
          <div class="dash">–</div>
          <div class="goal_block">
            <div class="zero goal_btn" @click="setGoals('zero', 8)">0</div>
            <div class="minus goal_btn" @click="setGoals('minus', 8)">-</div>
            <div class="value">{{ data[8] }}</div>
            <div class="plus goal_btn" @click="setGoals('plus', 8)">+</div>
            <div class="two goal_btn" @click="setGoals('two', 8)">2</div>
          </div>
        </div>

      </div>
      <div class="prognosis_dash_line"></div>

      <div class="part_block">
        <div class="title_block block_absolute">
          <div class="item icon">{{ icons[9] }}</div>
          <div class="item title">{{ title[9] }}:</div>
        </div>
        <div class="value_block">
          <div class="value_box">
            <div class="match_result_el" :class="{'active' : data[9] === 'п1'}" @click="setResult('п1')">п1</div>
            <div class="match_result_el" :class="{'active' : data[9] === 'н'}" @click="setResult('н')">н</div>
            <div class="match_result_el" :class="{'active' : data[9] === 'п2'}" @click="setResult('п2')">п2</div>
          </div>
        </div>
      </div>
      <div class="prognosis_dash_line"></div>

      <div class="part_block">
        <div class="title_block block_absolute">
          <div class="item icon">{{ icons[25] }}</div>
          <div class="item title">{{ title[25] }}:</div>
        </div>
        <div class="value_block">
          <div class="minus math_btn" @click="setMath('minus', 25, 'sum')">-</div>
          <div class="value">{{ data[25] }}</div>
          <div class="plus math_btn" @click="setMath('plus', 25, 'sum')">+</div>
        </div>
      </div>

      <div class="prognosis_dash_line"></div>

      <div class="part_block">
        <div class="title_block block_absolute">
          <div class="item icon">{{ icons[26] }}</div>
          <div class="item title">{{ title[26] }}:</div>
        </div>
        <div class="value_block">
          <div class="minus math_btn" @click="setMath('minus', 26)">-</div>
          <div class="value">{{ data[26] }}</div>
          <div class="plus math_btn" @click="setMath('plus', 26)">+</div>
        </div>
      </div>
      <div class="prognosis_dash_line"></div>
      <div class="part_block">
        <div class="title_block">
          <div class="item icon">{{ icons[10] }}</div>
          <div class="item title">{{ title[10] }}:</div>
        </div>
        <div class="value_block">
          <div class="match_domination_box">
            <div class="minus math_btn" @click="setRangeBtn('plus')">+</div>
            <div class="value left">{{ data[10] }}</div>
            <input class="domination_range" type="range" ref="iRange" :value="data[10]" @change="rangeChange()">
            <div class="value right">{{ 100 - data[10] }}</div>
            <div class="plus math_btn" @click="setRangeBtn('minus')">+</div>
            <div class="plus math_btn" @click="setRangeBtn('half')">50</div>
          </div>
        </div>
      </div>
      <div class="prognosis_dash_line"></div>
      <div class="part_block yellow">
        <div class="title_block block_absolute">
          <div class="item icon">{{ icons[12] }}</div>
          <div class="item title">{{ title[12] }}:</div>
        </div>
        <div class="value_block">
          <div class="box">
            <div class="btn" @click="setValue('zero', 12)">0</div>
            <div class="btn" @click="setValue('minus', 12)">-</div>
            <div class="value">{{ data[12] }}</div>
            <div class="btn" @click="setValue('plus', 12)">+</div>
            <div class="btn" @click="setValue('five', 12)">5</div>
          </div>
        </div>
      </div>
      <div class="prognosis_dash_line"></div>
      <div class="part_block red">
        <div class="title_block block_absolute">
          <div class="item icon">{{ icons[13] }}</div>
          <div class="item title">{{ title[13] }}:</div>
        </div>
        <div class="value_block">
          <div class="box">
            <div class="btn" @click="setValue('zero', 13)">0</div>
            <div class="btn" @click="setValue('minus', 13)">-</div>
            <div class="value">{{ data[13] }}</div>
            <div class="btn" @click="setValue('plus', 13)">+</div>
            <div class="btn" @click="setValue('one', 13)">1</div>
          </div>
        </div>
      </div>
      <div class="prognosis_dash_line"></div>
      <div class="part_block">
        <div class="title_block block_absolute">
          <div class="item icon">{{ icons[11] }}</div>
          <div class="item title">{{ title[11] }}:</div>
        </div>
        <div class="value_block">
          <div class="box">
            <div class="btn" @click="setValue('zero', 11)">0</div>
            <div class="btn" @click="setValue('minus', 11)">-</div>
            <div class="value">{{ data[11] }}</div>
            <div class="btn" @click="setValue('plus', 11)">+</div>
            <div class="btn" @click="setValue('six', 11)">6</div>
            <div class="btn" @click="setValue('twelve', 11)">12</div>
          </div>
        </div>
      </div>
      <div class="prognosis_dash_line"></div>
      <div class="part_block">
        <div class="title_block block_absolute">
          <div class="item icon">{{ icons[14] }}</div>
          <div class="item title">{{ title[14] }}:</div>
        </div>
        <div class="value_block">
          <div class="box">
            <div class="btn" @click="setValue('zero', 14)">0</div>
            <div class="btn" @click="setValue('minus', 14)">-</div>
            <div class="value">{{ data[14] }}</div>
            <div class="btn" @click="setValue('plus', 14)">+</div>
            <div class="btn" @click="setValue('one', 14)">1</div>
          </div>
        </div>
      </div>

      <div class="prognosis_dash_line"></div>

      <div class="play_off_block" v-if="stage==='Плей-офф'">
        <div class="part_block">
          <div class="title_block block_absolute">
            <div class="item icon">{{ icons[47] }}</div>
            <div class="item title">{{ title[47] }}:</div>
          </div>
          <div class="value_block">
            <div class="box">
              <div class="match_result_el play_off_el" :class="{'active' : data[47] === 'Будет'}"
                   @click="setPlayOffResult(47,'Будет')">Будет
              </div>
              <div class="match_result_el play_off_el" :class="{'active' : data[47] === 'Не будет'}"
                   @click="setPlayOffResult(47, 'Не будет')">Не будет
              </div>
            </div>
          </div>
        </div>

        <div class="prognosis_dash_line"></div>

        <div class="part_block">
          <div class="title_block block_absolute">
            <div class="item icon">{{ icons[48] }}</div>
            <div class="item title">{{ title[48] }}:</div>
          </div>
          <div class="value_block">
            <div class="box">
              <div class="match_result_el play_off_el" :class="{'active' : data[48] === 'Будет'}"
                   @click="setPlayOffResult(48,'Будет')">Будет
              </div>
              <div class="match_result_el play_off_el" :class="{'active' : data[48] === 'Не будет'}"
                   @click="setPlayOffResult(48,'Не будет')">Не будет
              </div>
            </div>
          </div>
        </div>
        <div class="prognosis_dash_line"></div>
      </div>


      <div class="btns_block">

        <div class="btn_send" @click="sendResult">Заполнить</div>

      </div>

      <div class="btn_set_result" @click="calcResult">Рассчитать результаты</div>
    </div>

    <div class="error_message" v-if="error">{{ error }}</div>
</template>

<script>
import {mapActions, mapState} from "vuex";
import PreLoader from "@/components/main/PreLoader";

export default {
  name: "FootballAdminSetResult",
  components: {
    PreLoader
  },
  props: {
    id: {
      type: Number
    },
    stage: {
      type: String
    },
    result: {
      type: Object
    }
  },
  data() {
    return {

      loader: false,
      error: '',

      data: {        
        7: this.result.goal_home ?? 0, // goals_home
        8: this.result.goal_guest ?? 0, // goals_guest
        9: this.result.result ?? '', // Исход матча

        25: this.result.diff ?? '', // Разница мячей
        26: this.result.sum ?? '', // Сумма голов
        10: this.result.domination ?? 50, // Владение
        12: this.result.yellow ?? 3, // желтых
        13: this.result.red ?? 0, // красных

        11: this.result.corner ?? 9, // угловых
        14: this.result.penalty ?? 0, // пенальти

        47: this.result.otime ?? '', // m_otime
        48: this.result.spenalty ?? '', // m_spenalty

        27: '', // m_offside
      },

      icons: {
        1: '0-0',
        9: '✓',  // result
        25: 'Δ',
        26: 'Σ',
        10: '🡘',
        12: '▮',
        13: '▮',
        11: '🡬',
        14: '◒',
        47: '+◔',
        48: '+◒',
      },

      title: {
        1: 'Счет матча',
        9: 'Исход матча',
        25: 'Разница мячей',
        26: 'Сумма мячей',
        10: 'Процент владения',
        12: 'Количество желтых',
        13: 'Количество красных',
        11: 'Количество угловых',
        14: 'Количество пенальти',
        47: 'Дополнительное время',
        48: 'Серия пенальти',
      },

      description: {
        1: 'Счет матча',
        9: 'Исход матча (п1 - победа первой команды, н - ничья, п2 - победа второй)',
        25: 'Разница мячей забитые второй командой вычитаются из забитых первой командой',
        26: 'Сумма мячей забитых обеими командами',
        10: 'Процент владения мячом первой и второй командой',
        12: 'Количество желтых карточек в матче (сумма для обеих команд)',
        13: 'Количество красных карточек в матче (сумма для обеих команд)',
        11: 'Количество угловых в матче (сумма для обеих команд)',
        14: 'Количество пенальти в матче (сумма для обеих команд)',
        47: 'Дополнительное время (наличие/отсутствие)',
        48: 'Серия пенальти (наличие/отсутствие)',

      }
    }
  },

  methods: {
    ...mapActions({
      sendFootballResult: 'admin/setFootballResult',
      calcFootballResult: 'admin/calcFootballResult'
    }),

    setGoals(type, id) {

      if (type === 'minus') {
        if (this.data[id] > 0) this.data[id]--
      }

      if (type === 'plus') this.data[id]++

      if (type === 'zero') this.data[id] = 0
      if (type === 'two') this.data[id] = 2
      if (type === 'five') this.data[id] = 5

      this.data[26] = Number(this.data[7]) + Number(this.data[8])

      this.data[25] = Number(this.data[7]) - Number(this.data[8])

      if (this.data[25] > 0) this.data[9] = 'п1'
      if (this.data[25] === 0) this.data[9] = 'н'
      if (this.data[25] < 0) this.data[9] = 'п2'

    },

    setMath(operation, id, type = '') {
      if (operation === 'minus') {
        if (type === 'sum') {
          if (this.data[id] > 0) this.data[id]--
        } else {
          this.data[id]--
        }
      }

      if (operation === 'plus') this.data[id]++
    },

    setResult(res) {
      this.data[9] = res
    },

    setPlayOffResult(id, res) {
      this.data[id] = res
    },

    rangeChange() {
      this.data[10] = this.$refs.iRange.value
    },

    setValue(type, id) {

      if (type === 'minus') {
        if (this.data[id] > 0) this.data[id]--
      }

      if (type === 'plus') this.data[id]++

      if (type === 'zero') this.data[id] = 0
      if (type === 'one') this.data[id] = 1
      if (type === 'five') this.data[id] = 5
      if (type === 'six') this.data[id] = 6
      if (type === 'twelve') this.data[id] = 12

    },

    setRangeBtn(type) {
      if (type === 'minus') {
        if (this.data[10] > 0) this.data[10]--
      }

      if (type === 'plus') {
        if (this.data[10] < 101) this.data[10]++
      }

      if (type === 'half') this.data[10] = 50
    },

    async sendResult() {
      this.loader = true
      this.queryEvent.userToken = this.token
      this.queryEvent.role = this.role
      this.queryEvent.matchId = this.id
      this.queryEvent.data = this.data

      await this.sendFootballResult()

      this.loader = false
    },

    async calcResult(){
      this.loader = true

      this.queryEvent.userToken = this.token
      this.queryEvent.role = this.role
      this.queryEvent.matchId = this.id

      await this.calcFootballResult()

      this.loader = false
    }
  },

  computed: {
    ...mapState({
      token: state => state.auth.authData.token,
      queryEvent: state => state.admin.queryEvent,
      role: state => state.auth.userInfo.role,
    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.prognosis_btn {
  background: @YesWrite;

  .flex_center;
  background: @colorText2;
  color: @colorText;
  cursor: pointer;
  .shadow_template;
  padding: 2px 2px;
  font-size: 14px;
  border-radius: 3px;
  text-align: center;
  border: 1px solid transparent;
  text-decoration: none;
}
.prognosis_block {
  background: @colorBlur;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;

  display: flex;
  flex-direction: column;

  gap: 4px;

  .part_block {
    position: relative;
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 4px;

    .title_block {
      display: flex;
      flex-direction: row;
      gap: 4px;
      .item{
        .shadow_inset;
        text-align: left;
        color: @maxdarkgrey;
        font-size: 14px;
        font-weight: 700;
      }
      .icon{
        min-width: 24px;
        .flex_center;
      }
    }

    .value_block {
      width: 100%;
      display: flex;
      flex-direction: row;
      justify-content: flex-end;
      gap: 4px;

      .value_box {
        display: flex;
        flex-direction: row;
        gap: 4px;
      }
    }

    &.yellow {
      .item {
        color: @maxYellow;
      }

      .value {
        border-color: @maxYellow;
      }

      .btn {
        background: @maxYellow;
      }
    }

    &.red {
      .item {
        color: @maxred;
      }

      .value {
        border-color: @maxred;
      }

      .btn {
        background: @maxred;
      }
    }
  }

  .block_absolute{
    position: absolute;
  }

  .prognosis_dash_line {
    width: 100%;
    border-bottom: 1px dotted @maxdarkgrey;
  }

  .goal_block {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 3px;

    .value {
      .flex_center;
      .shadow_inset;
      width: 19px;
      height: 19px;
      border: 2px solid @YesWrite;
      padding: 2px 2px;
      font-size: 14px;
      border-radius: 3px;
    }

    .goal_btn {
      width: 20px;
      height: 20px;
      background: @YesWrite;

      .flex_center;
      background: @colorText2;
      color: @colorText;
      cursor: pointer;
      .shadow_template;
      padding: 2px 2px;
      font-size: 14px;
      border-radius: 3px;
      text-align: center;
      border: 1px solid transparent;
      text-decoration: none;
    }
  }

  .match_result_el {
    .flex_center;
    .shadow_inset;
    cursor: pointer;
    padding: 0;
    width: 26px;
    height: 26px;
    font-size: 12px;
    border: 2px solid @maxdarkgrey;

    &.active {
      border-color: @YesWrite;
    }
  }

  .play_off_el{
    width: 65px;
  }

  .math_btn {
    width: 26px;
    height: 26px;
    background: @YesWrite;

    .flex_center;
    background: @colorText2;
    color: @colorText;
    cursor: pointer;
    .shadow_template;
    padding: 2px 2px;
    font-size: 14px;
    border-radius: 3px;
    text-align: center;
    border: 1px solid transparent;
    text-decoration: none;
  }

  .value {
    .flex_center;
    .shadow_inset;
    width: 26px;
    height: 26px;
    border: 2px solid @YesWrite;
    padding: 2px 2px;
    font-size: 14px;
    border-radius: 3px;
  }

  .match_domination_box {
    display: flex;
    flex-direction: row;
    gap: 3px;

    .domination_range {
      width: 114px;
      max-width: 59.8%;
    }

    .value {
      width: 26px;
      height: 26px;
    }

    .math_btn {
      .prognosis_btn;
      width: 26px;
      height: 26px;
    }
  }
  .box {
    display: flex;
    flex-direction: row;
    gap: 3px;
  }
  .btn {
    .prognosis_btn;
    width: 26px;
    height: 26px;
  }


  &.yellow {
    .title {
      color: @maxYellow;
    }

    .value {
      border-color: @maxYellow;
    }

    .btn {
      background: @maxYellow;
    }
  }

  &.red {
    .title {
      color: @maxred;
    }

    .value {
      border-color: @maxred;
    }

    .btn {
      background: @maxred;
    }
  }

  .btns_block {
    display: flex;
    flex-direction: row;
    justify-content: space-between;

    margin-top: 10px;

    .other_match_btn {
      .prognosis_btn;
      width: 19px;
      height: 19px;
    }

    .inactive{
      background: @colorBlur;
      border: 2px solid crimson;
    }

    .annotation_btn {
      position: relative;
      .prognosis_btn;
      width: 140px;
      max-width: 40%;
      background: @kerling;

      .annotation_arrow {
        position: absolute;
        right: 5px;

        &.up {
          transform: rotate(-90deg);
        }
      }
    }

    .btn_send {
      .prognosis_btn;
      width: 140px;
      max-width: 40%;

      &.rewrite {
        background: @NoWrite;
      }
    }
  }
}

.error_message {
  margin-top: 4px;
  width: 100%;
  padding: 4px 2px;
  border: 1px solid @warning;
  color: @warning;
  border-radius: 6px;
}

.btn_set_result {
  display: flex;
  flex-direction: column;
  justify-content: center;
  background: @red;
  color: @colorText;
  cursor: pointer;
  .shadow_template;
  padding: 2px 2px;
  font-size: 10px;
  border-radius: 3px;
  text-align: center;
  border: 1px solid transparent;
  text-decoration: none;
  margin-top: 8px;
  margin-bottom: 38px;
}
</style>