<template>
  <PreLoader v-if="prognosisLoader"></PreLoader>
  <SendSuccess v-if="prognosisSuccess" :closeSuccess="closeSuccess"></SendSuccess>
  <ActionFailure v-if="actionFailure" :closeSuccess="closeSuccess">{{ errorMessage }}</ActionFailure>

  <div v-else class="match_wrapper">
    <PageHeader class="header" :path="'/football/' + $route.params.event">Матч № {{ $route.params.number }}</PageHeader>
    <div class="match_title">
      <div class="number title_cell"># {{ arMatch.number }}</div>
      <div class="date title_cell">&#128197; {{ arMatch.date }}</div>
      <div class="time title_cell">&#128344; {{ arMatch.time }}</div>
      <div class="stage title_cell">Тур: {{ arMatch.tur }}</div>
    </div>

    <div class="teams_block" v-if="home">
      <div class="team">
        <div class="flag">
          <img :src="urlImg + home.flag" alt="">
        </div>
        <div class="name name_home">{{ home.name }}</div>
      </div>
      <div class="dash">–</div>
      <div class="team">
        <div class="name name_guest">{{ guest.name }}</div>
        <div class="flag">
          <img :src="urlImg + guest.flag" alt="">
        </div>
      </div>
    </div>

    <div class="btn_admin_block" v-if="role === 'admin'">
      <div class="title">Выбран {{ admin ? 'Админский' : 'Простой' }} режим</div>
      <div class="btn_block">
        <div class="btn" v-if="admin" @click="admin = false">Простой</div>
        <div class="btn" v-else @click="admin = true">Админ</div>
      </div>
    </div>

    <div v-if="admin">
      <div class="block_gap">
        <FootballAdminSetResult
            :id="arMatch.id"
            :stage = "matchR.stage"
            :result="matchR"
            >
        </FootballAdminSetResult>
      </div>

    </div>

    <div v-else>
      <div class="match_record_wrapper" v-if="arMatch.active === 'Y'">
        <div class="prognosis_block">
          <div class="time_send" v-if="prognosis?.time_send">
            <div class="title_block">
              Заполнено: {{prognosis.time_send}}
            </div>
          </div>
          <div class="part_block">
            <div class="title_block">
              <div class="item icon">{{ icons[1] }}</div>
              <div class="item title">{{ title[1] }}:</div>
            </div>
            <div class="value_block">
              <div class="goal_block">
                <div class="zero goal_btn" @click="setGoals('zero', 15)">0</div>
                <div class="minus goal_btn" @click="setGoals('minus', 15)">-</div>
                <div class="value">{{ data[15] }}</div>
                <div class="plus goal_btn" @click="setGoals('plus', 15)">+</div>
                <div class="two goal_btn" @click="setGoals('two', 15)">2</div>
              </div>
              <div class="dash">–</div>
              <div class="goal_block">
                <div class="zero goal_btn" @click="setGoals('zero', 16)">0</div>
                <div class="minus goal_btn" @click="setGoals('minus', 16)">-</div>
                <div class="value">{{ data[16] }}</div>
                <div class="plus goal_btn" @click="setGoals('plus', 16)">+</div>
                <div class="two goal_btn" @click="setGoals('two', 16)">2</div>
              </div>
            </div>

          </div>
          <div class="prognosis_dash_line"></div>

          <div class="part_block">
            <div class="title_block auto_block_title">
              <div class="item icon">Заполняется автоматически</div>
              <div class="more_btn" @click="autoBlock = !autoBlock"><span
                  :class="{'close' : !autoBlock, 'open' : autoBlock}"> > </span></div>
            </div>
          </div>


          <div class="auto_block" v-if="autoBlock">
            <div class="prognosis_dash_line"></div>
            <div class="part_block">
              <div class="title_block block_absolute">
                <div class="item icon">{{ icons[18] }}</div>
                <div class="item title">{{ title[18] }}:</div>
              </div>
              <div class="value_block">
                <div class="value_box">
                  <div class="match_result_el" :class="{'active' : data[18] === 'п1'}" @click="setResult('п1')">п1</div>
                  <div class="match_result_el" :class="{'active' : data[18] === 'н'}" @click="setResult('н')">н</div>
                  <div class="match_result_el" :class="{'active' : data[18] === 'п2'}" @click="setResult('п2')">п2</div>
                </div>
              </div>
            </div>
            <div class="prognosis_dash_line"></div>

            <div class="part_block">
              <div class="title_block block_absolute">
                <div class="item icon">{{ icons[28] }}</div>
                <div class="item title">{{ title[28] }}:</div>
              </div>
              <div class="value_block">
                <div class="minus math_btn" @click="setMath('minus', 28, 'sum')">-</div>
                <div class="value">{{ data[28] }}</div>
                <div class="plus math_btn" @click="setMath('plus', 28, 'sum')">+</div>
              </div>
            </div>

            <div class="prognosis_dash_line"></div>

            <div class="part_block">
              <div class="title_block block_absolute">
                <div class="item icon">{{ icons[19] }}</div>
                <div class="item title">{{ title[19] }}:</div>
              </div>
              <div class="value_block">
                <div class="minus math_btn" @click="setMath('minus', 19)">-</div>
                <div class="value">{{ data[19] }}</div>
                <div class="plus math_btn" @click="setMath('plus', 19)">+</div>
              </div>
            </div>

          </div>

          <div class="prognosis_dash_line"></div>

          <div class="part_block">
            <div class="title_block">
              <div class="item icon">{{ icons[32] }}</div>
              <div class="item title">{{ title[32] }}:</div>
            </div>
            <div class="value_block">
              <div class="match_domination_box">
                <div class="minus math_btn" @click="setRangeBtn('plus')">+</div>
                <div class="value left">{{ data[32] }}</div>
                <input class="domination_range" type="range" ref="iRange" :value="data[32]" @change="rangeChange()">
                <div class="value right">{{ 100 - data[32] }}</div>
                <div class="plus math_btn" @click="setRangeBtn('minus')">+</div>
                <div class="plus math_btn" @click="setRangeBtn('half')">50</div>
              </div>
            </div>
          </div>
          <div class="prognosis_dash_line"></div>
          <div class="part_block yellow">
            <div class="title_block block_absolute">
              <div class="item icon">{{ icons[21] }}</div>
              <div class="item title">{{ title[21] }}:</div>
            </div>
            <div class="value_block">
              <div class="box">
                <div class="btn" @click="setValue('zero', 21)">0</div>
                <div class="btn" @click="setValue('minus', 21)">-</div>
                <div class="value">{{ data[21] }}</div>
                <div class="btn" @click="setValue('plus', 21)">+</div>
                <div class="btn" @click="setValue('five', 21)">5</div>
              </div>
            </div>
          </div>
          <div class="prognosis_dash_line"></div>
          <div class="part_block red">
            <div class="title_block block_absolute">
              <div class="item icon">{{ icons[22] }}</div>
              <div class="item title">{{ title[22] }}:</div>
            </div>
            <div class="value_block">
              <div class="box">
                <div class="btn" @click="setValue('zero', 22)">0</div>
                <div class="btn" @click="setValue('minus', 22)">-</div>
                <div class="value">{{ data[22] }}</div>
                <div class="btn" @click="setValue('plus', 22)">+</div>
                <div class="btn" @click="setValue('one', 22)">1</div>
              </div>
            </div>
          </div>
          <div class="prognosis_dash_line"></div>
          <div class="part_block">
            <div class="title_block block_absolute">
              <div class="item icon">{{ icons[20] }}</div>
              <div class="item title">{{ title[20] }}:</div>
            </div>
            <div class="value_block">
              <div class="box">
                <div class="btn" @click="setValue('zero', 20)">0</div>
                <div class="btn" @click="setValue('minus', 20)">-</div>
                <div class="value">{{ data[20] }}</div>
                <div class="btn" @click="setValue('plus', 20)">+</div>
                <div class="btn" @click="setValue('six', 20)">6</div>
                <div class="btn" @click="setValue('twelve', 20)">12</div>
              </div>
            </div>
          </div>
          <div class="prognosis_dash_line"></div>
          <div class="part_block">
            <div class="title_block block_absolute">
              <div class="item icon">{{ icons[23] }}</div>
              <div class="item title">{{ title[23] }}:</div>
            </div>
            <div class="value_block">
              <div class="box">
                <div class="btn" @click="setValue('zero', 23)">0</div>
                <div class="btn" @click="setValue('minus', 23)">-</div>
                <div class="value">{{ data[23] }}</div>
                <div class="btn" @click="setValue('plus', 23)">+</div>
                <div class="btn" @click="setValue('one', 23)">1</div>
              </div>
            </div>
          </div>

          <div class="prognosis_dash_line"></div>

          <div class="play_off_block" v-if="matchR.stage==='Плей-офф'">
            <div class="part_block">
              <div class="title_block block_absolute">
                <div class="item icon">{{ icons[45] }}</div>
                <div class="item title">{{ title[45] }}:</div>
              </div>
              <div class="value_block">
                <div class="box">
                  <div class="match_result_el play_off_el" :class="{'active' : data[45] === 'Будет'}" @click="setPlayOffResult(45,'Будет')">Будет</div>
                  <div class="match_result_el play_off_el" :class="{'active' : data[45] === 'Не будет'}" @click="setPlayOffResult(45, 'Не будет')">Не будет</div>
                </div>
              </div>
            </div>

            <div class="prognosis_dash_line"></div>

            <div class="part_block">
              <div class="title_block block_absolute">
                <div class="item icon">{{ icons[46] }}</div>
                <div class="item title">{{ title[46] }}:</div>
              </div>
              <div class="value_block">
                <div class="box">
                  <div class="match_result_el play_off_el" :class="{'active' : data[46] === 'Будет'}" @click="setPlayOffResult(46,'Будет')">Будет</div>
                  <div class="match_result_el play_off_el" :class="{'active' : data[46] === 'Не будет'}" @click="setPlayOffResult(46,'Не будет')">Не будет</div>
                </div>
              </div>
            </div>
            <div class="prognosis_dash_line"></div>
          </div>


          <div class="btns_block">

            <div class="annotation_btn" @click="annotationVis = !annotationVis">Расшифровка
              <span class="annotation_arrow" :class="{'up' : annotationVis === true}">v</span>
            </div>
            <div class="btn_send" @click="sendPrognosis" v-if="!prognosis?.result">Отправить</div>
            <div class="btn_send rewrite" @click="sendPrognosis" v-else>Изменить</div>

          </div>
        </div>

        <div class="error_message" v-if="error">{{ error }}</div>
      </div>

      <div class="match_result_wrapper" v-else>

        <FootballResultTable
            :match="arMatch"
        ></FootballResultTable>

      </div>

      <div class="annotation_block" v-if="annotationVis">
        <div class="header">
          <div class="title">Расшифровка обозначений</div>
          <div class="close" @click="annotationVis = false">x</div>
        </div>
        <div class="annotation_elem" v-for="(icon, index) in icons"
             :key="index">
          <div class="annotation_title" :class="{'yellow_t' : index == 21, 'red_t' : index == 22}">{{ icon }}</div>
          <div class="annotation_description">{{ description[index] }}</div>
        </div>
      </div>

      <div class="btn_select_other_wrapper">
        <div class="other_match_btn" v-if="$route.params.number>1"
             @click="$router.push(prevLink).then(() => { this.$router.go() })">
          <img src="@/assets/icon/pagination/left.svg" alt=""><span>Предыдущий</span>
        </div>
        <div class="other_match_btn inactive" v-else>
          <img src="@/assets/icon/pagination/left.svg" alt=""><span>Предыдущий</span>
        </div>

        <div class="other_match_btn" v-if="$route.params.number<arMatch.max"
             @click="$router.push(nextLink).then(() => { this.$router.go() })">
          <span>Следующий</span><img src="@/assets/icon/pagination/right.svg" alt="">
        </div>
        <div class="other_match_btn inactive" v-else>
          <span>Следующий</span><img src="@/assets/icon/pagination/right.svg" alt="">
        </div>
      </div>
    </div>

  </div>

</template>

<script>

import PageHeader from "@/components/main/PageHeader";
import {mapActions, mapState} from "vuex";
import PreLoader from "@/components/main/PreLoader";
import SendSuccess from "@/components/main/SendSuccess";
import FootballAdminSetResult from "@/components/football/FootballAdminSetResult";
import ActionFailure from "@/components/main/ActionFailure";
import FootballResultTable from "@/components/football/FootballResultTable";


export default {
  name: "FootballPage",
  components: {
    ActionFailure,
    FootballAdminSetResult,
    PageHeader,
    PreLoader,
    SendSuccess,
    FootballResultTable
  },

  data() {
    return {
      admin: false,

      prognosisLoader: false,
      prognosisSuccess: false,
      actionFailure: false,
      moreInfo: false,

      autoBlock: false,

      urlImg: 'https://prognos9ys.ru/',
      ball: '⚽',
      prevLink: '',
      nextLink: '',
      error: '',
      annotationVis: false,
      data: {
        30: this.$route.params.number, //number
        17: '', //matchId
        15: 0, // goals_home
        16: 0, // goals_guest
        18: '', // Исход матча

        19: '', // Разница мячей
        28: '', // Сумма голов
        32: 50, // Владение
        21: '', // желтых
        22: '', // красных

        20: '', // угловых
        23: '', // пенальти
        52: this.$route.params.event, // Событие
        45: '', // m_otime
        46: '', // m_spenalty

        29: '', // m_offside
      },

      icons: {
        1: '0-0',
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
      },

      title: {
        1: 'Счет матча',
        18: 'Исход матча',
        28: 'Разница мячей',
        19: 'Сумма мячей',
        32: 'Процент владения',
        21: 'Количество желтых',
        22: 'Количество красных',
        20: 'Количество угловых',
        23: 'Количество пенальти',
        45: 'Дополнительное время',
        46: 'Серия пенальти',
      },

      description: {
        1: 'Счет матча',
        18: 'Исход матча (п1 - победа первой команды, н - ничья, п2 - победа второй)',
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
    this.fillMatchElem()
    this.setOtherLink()
  },

  methods: {
    ...mapActions({
      getMatchRequest: 'football/getMatchRequest',
      sendUserPrognosis: 'football/sendUserPrognosis',
    }),

    setOtherLink() {
      this.prevLink = '/football/' + this.$route.params.event + '/' + String(Number(this.$route.params.number) - 1)
      this.nextLink = '/football/' + this.$route.params.event + '/' + String(Number(this.$route.params.number) + 1)
    },

    async sendPrognosis() {
      this.prognosisLoader = true
      this.error = ''
      this.actionFailure = false

      if (!this.data[18]) {
        this.error = 'Укажите счёт матча (кнопки +/-) или исход (п1 / н / п2)'
        this.prognosisLoader = false
        return
      }

      if (!this.arMatch?.id) {
        this.error = 'Матч ещё не загружен, попробуйте снова'
        this.prognosisLoader = false
        return
      }

      try {
        this.queryPrognosis.userToken = this.token
        this.data[17] = this.arMatch.id
        this.data[30] = this.$route.params.number
        this.data[52] = this.$route.params.event
        this.queryPrognosis.fields = { ...this.data }

        const result = await this.sendUserPrognosis()

        if (result?.ok) {
          this.prognosisSuccess = true
          await this.getMatchRequest()
          this.syncFormFromPrognosis()
        } else {
          this.actionFailure = true
        }
      } finally {
        this.prognosisLoader = false
      }
    },

    closeSuccess() {
      this.prognosisSuccess = false
      this.actionFailure = false
    },

    syncScoreFromGoals() {
      this.data[28] = Number(this.data[15]) + Number(this.data[16])
      this.data[19] = Number(this.data[15]) - Number(this.data[16])

      if (this.data[19] > 0) this.data[18] = 'п1'
      else if (this.data[19] === 0) this.data[18] = 'н'
      else if (this.data[19] < 0) this.data[18] = 'п2'
    },

    syncFormFromPrognosis() {
      const prognosis = this.arMatch?.prognosis ?? {}

      this.data[15] = prognosis.goal_home ?? 0
      this.data[16] = prognosis.goal_guest ?? 0
      this.data[18] = prognosis.result ?? ''
      this.data[19] = prognosis.diff ?? ''
      this.data[28] = prognosis.sum ?? ''
      this.data[32] = prognosis.domination ?? 50
      this.data[21] = prognosis.yellow ?? ''
      this.data[22] = prognosis.red ?? ''
      this.data[20] = prognosis.corner ?? ''
      this.data[23] = prognosis.penalty ?? ''
      this.data[45] = prognosis.otime ?? ''
      this.data[46] = prognosis.spenalty ?? ''
    },

    setGoals(type, id) {

      if (type === 'minus') {
        if (this.data[id] > 0) this.data[id]--
      }

      if (type === 'plus') this.data[id]++

      if (type === 'zero') this.data[id] = 0
      if (type === 'two') this.data[id] = 2
      if (type === 'five') this.data[id] = 5

      this.syncScoreFromGoals()
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
      this.data[18] = res
    },

    setPlayOffResult(id, res){
      this.data[id] = res
    },

    rangeChange() {
      this.data[32] = this.$refs.iRange.value
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
        if (this.data[32] > 0) this.data[32]--
      }

      if (type === 'plus') {
        if (this.data[32] < 101) this.data[32]++
      }

      if (type === 'half') this.data[32] = 50
    },

    async fillMatchElem() {
      this.prognosisLoader = true

      this.queryMatch.number = this.$route.params.number
      this.queryMatch.eventId = this.$route.params.event
      this.queryMatch.userToken = this.token

      await this.getMatchRequest()
      this.syncFormFromPrognosis()

      this.prognosisLoader = false
    }
  },
  computed: {
    ...mapState({
      arMatch: state => state.football.match,
      home: state => state.football.match.home,
      guest: state => state.football.match.guest,
      queryMatch: state => state.football.queryMatch,
      queryPrognosis: state => state.football.queryPrognosis,
      prognosisSuccess: state => state.football.prognosisSuccess,
      token: state => state.auth.authData.token,
      prognosis: state => state.football.match.prognosis,
      matchR: state => state.football.match.match_result,
      progR: state => state.football.match.prog_result,

      role: state => state.auth.userInfo.role,
      errors: state => state.football.errors
    }),
    errorMessage() {
      if (this.errors?.mes) {
        return this.errors.mes
      }

      if (typeof this.errors === 'string' && this.errors) {
        return this.errors
      }

      return 'Не удалось сохранить прогноз'
    },
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.match_title {
  background: @DarkColorBG;
  color: @colorText;
  display: flex;
  flex-direction: row;
  justify-content: flex-start;

  padding: 4px;
  border-radius: 5px;

  gap: 4px;

  margin-bottom: 4px;

  .title_cell {
    .shadow_inset;
  }

}

.teams_block {
  background: @DarkColorBG;
  color: @colorText;
  display: flex;
  flex-direction: row;
  justify-content: space-between;

  padding: 4px;
  border-radius: 5px;

  gap: 4px;

  margin-bottom: 4px;

  .team {
    max-width: 46%;
    display: flex;
    flex-direction: row;
    gap: 4px;

    .flag {
      max-width: 24px;
      .shadow_inset;
      padding: 3px;
      .flex_center;

      img {
        width: 98%;
        max-width: 20px;
        border-radius: 3px;
      }
    }

    .name {
      max-width: 85%;
      width: 180px;
      .shadow_inset;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;

      &.name_home {
        text-align: left;
      }

      &.name_guest {
        text-align: right;
      }
    }
  }

}

.dash {
  .shadow_inset;
}

.prognosis_btn {
  background: @YesWrite;
  .shadow_template;
  .flex_center;
  background: @colorText2;
  color: @colorText;
  cursor: pointer;

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

.prognosis_block {
  position: relative;
  background: @DarkColorBG;
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
      width: 26px;
      height: 26px;
      border: 2px solid @YesWrite;
      padding: 2px 2px;
      font-size: 14px;
      border-radius: 3px;
    }

    .goal_btn {
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
    .flex_center;
    .shadow_template;
    width: 26px;
    height: 26px;
    background: @YesWrite;

    background: @colorText2;
    color: @colorText;
    cursor: pointer;
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
      width: 204px;
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
      width: 26px;
      height: 26px;
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
          transform: rotate(-180deg);
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

.btn_select_other_wrapper {
  background: @DarkColorBG;
  color: @colorText;
  display: flex;
  flex-direction: row;
  justify-content: space-between;

  padding: 4px;
  border-radius: 5px;

  gap: 4px;

  margin-top: 4px;

  .other_match_btn {
    .prognosis_btn;
    display: flex;
    flex-direction: row;
    gap: 4px;
    width: 140px;
    max-width: 40%;
  }
}

.annotation_block {
  background: @DarkColorBG;
  color: @colorBlur;
  display: flex;
  flex-direction: column;

  padding: 4px;
  border-radius: 5px;

  gap: 4px;

  margin-top: 4px;

  .header {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    gap: 4px;

    .title {
      width: 95%;
      .shadow_inset;
      text-align: left;

    }

    .close {
      width: 27px;
      .prognosis_btn;
      background: @boks;
    }
  }


  .annotation_elem {
    display: flex;
    flex-direction: row;
    gap: 4px;
    font-size: 13px;
    text-align: left;

    .annotation_title {
      max-width: 35px;
      width: 9%;
      .shadow_inset;
      .flex_center;
      font-size: 16px;
    }

    .annotation_description {
      max-width: 355px;
      width: 90%;
      .shadow_inset;
    }

  }
}

.yellow_t {
  color: @maxYellow;
}

.red_t {
  color: @maxred;
}
.inactive{
  background: @colorBlur!important;
}

.btn_admin_block {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  gap: 4px;
  background: @red;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;

  .title {
    .shadow_inset;
    .flex_center;
  }

  .btn_block {
    display: flex;
    flex-direction: row;
    gap: 4px;
    justify-content: flex-end;

    .btn {
      .shadow_inset;
    }
  }
}

.play_off_block{
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.time_send{
  position: absolute;
  width: 100%;
  text-align: right;
  font-size: 11px;
  color: @NoWrite;
  top: 5px;
  right: 5px;
}

.auto_block{
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.more_btn {
  display: flex;
  flex-direction: column;
  justify-content: center;
  max-width: 24px;
  height: 24px;
  width: 24%;
  background: @valleyball;
  padding: 2px 2px;
  border-radius: 3px;
  cursor: pointer;
  .shadow_template;

  .close {
    transform: rotate(90deg);
  }

  .open {
    transform: rotate(-90deg);
  }

  &:hover {
    background: @colorText;
    color: @valleyball;
    border: 1px solid @valleyball;
  }
}
</style>