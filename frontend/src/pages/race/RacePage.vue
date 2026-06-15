<template>
  <PreLoader v-if="loader"></PreLoader>
  <PageHeader class="header" :path="'/race/' + $route.params.event">Гонка № {{ $route.params.number }}</PageHeader>

  <div class="item_wrapper" v-if="item">
    <div class="item_title">
      <div class="number title_cell"># {{ item.number }}</div>
      <div class="date title_cell">&#128197; {{ item.qual.date }}</div>
      <div class="time title_cell">&#128344; {{ item.qual.time }}</div>
    </div>

    <div class="item_name">
      <div class="box">
        <div class="name_cell">Гран-при</div>
        <div class="name_cell">{{ item.name }}</div>
      </div>
      <div class="box">
        <div class="name_cell">{{ item.country.NAME }}</div>
        <div class="name_cell flag_cell">
          <img :src="urlImg + item.country.flag" alt="">
        </div>
      </div>
    </div>

    <div class="btn_select_other_wrapper">
      <div class="other_match_btn" v-if="$route.params.number>1"
           @click="$router.push(prevLink).then(() => { this.$router.go() })">
        <img src="@/assets/icon/pagination/left.svg" alt=""><span>Назад</span>
      </div>
      <div class="other_match_btn inactive" v-else>
        <img src="@/assets/icon/pagination/left.svg" alt=""><span>Назад</span>
      </div>

      <div class="other_match_btn" v-if="$route.params.number"
           @click="$router.push(nextLink).then(() => { this.$router.go() })">
        <span>Вперед</span><img src="@/assets/icon/pagination/right.svg" alt="">
      </div>
      <div class="other_match_btn inactive" v-else>
        <span>Вперед</span><img src="@/assets/icon/pagination/right.svg" alt="">
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
        <RacerSelectBlock
            v-for="(el, index) in adminResult"
            :key="index"
            :dataBlock="el"
            :role="role"
            :racers="item.racers"
            :raceInfo="raceInfo">
        </RacerSelectBlock>
      </div>

      <div class="btn_set_result" @click="calcResult">Рассчитать результаты</div>

    </div>


    <div v-else>
      <div class="block_gap" v-if="item.active === 'Y'">
        <RacerSelectBlock
            v-for="(el, index) in progBlocks"
            :key="index"
            :dataBlock="el"
            :racers="item.racers"
            :raceInfo="raceInfo">
        </RacerSelectBlock>
      </div>
      <div class="result_race" v-else>
        <div class="cancelled_condition_wrapper" v-if="item.status === 'Отменен'">
          <div class="btn_admin_block">
            <div class="title">Гран-при {{ item.status }}</div>
          </div>
        </div>
        <div class="cancelled_condition_wrapper" v-else>
          <div v-if="item.result_race">
            <ResultRaceBlock
                v-for="(el, index) in progBlocks"
                :key="index"
                :dataBlock="el"
                :prognosis="item.prognosis[index]"
                :score="item.result_score[index]"
                :result="item.result_race[index]"
                :racers="item.racers">
            </ResultRaceBlock>
          </div>
          <div v-else>
            <div class="empty_result_wrapper">
              <div class="empty_result">Результаты заполняются</div>
            </div>
          </div>

        </div>
      </div>
    </div>

  </div>

</template>

<script>
import PageHeader from "@/components/main/PageHeader";
import {mapActions, mapState} from "vuex";
import PreLoader from "@/components/main/PreLoader";
import RacerSelectBlock from "@/components/race/RacerSelectBlock";
import ResultRaceBlock from "@/components/race/ResultRaceBlock";

export default {
  name: "RacePage",
  components: {
    RacerSelectBlock,
    PageHeader,
    PreLoader,
    ResultRaceBlock
  },
  data() {
    return {
      urlImg: 'https://prognos9ys.ru/',
      loader: true,

      admin: false,

      prevLink:'',
      nextLink:'',

      progBlocks: {
        qual_res: {title: 'Квалификация', type: 'qual', count: 10, active: true, exist: true},
        sprint_res: {title: 'Спринт', type: 'sprint', count: 8, active: true, exist: false},
        race_res: {title: 'Гонка', type: 'race', count: 10, active: true, exist: true},
        best_lap: {title: 'Лучший круг', type: 'best_lap', count: 1, active: true, exist: true},
      },

      adminResult: { // костыль - так как похоже по ссылке ставится если присвоить админке progBlock
        qual_res: {title: 'Квалификация', type: 'qual', count: 10, active: true, exist: true},
        sprint_res: {title: 'Спринт', type: 'sprint', count: 8, active: true, exist: false},
        race_res: {title: 'Гонка', type: 'race', count: 10, active: true, exist: true},
        best_lap: {title: 'Лучший круг', type: 'best_lap', count: 1, active: true, exist: true},
      },

      raceInfo: {},
    }
  },

  created() {
    this.fillElem()
    this.setOtherLink()
  },
  watch: {
    item() {
      this.loader = false

      this.raceInfo['race_id'] = this.item.id
      this.raceInfo['number'] = this.item.number
      this.raceInfo['events'] = this.item.event
      if (this.item.send_date) this.raceInfo['fill'] = this.item.send_date
      this.raceInfo['userToken'] = this.token

      if (this.item.sprint) {
        this.progBlocks.sprint_res.exist = true
        this.adminResult.sprint_res.exist = true
      }

      Object.keys(this.progBlocks).forEach((selector) => {

        if(this.item.result_race) this.adminResult[selector].data = this.item.result_race[selector] ?? []

        if(this.item.prognosis) this.progBlocks[selector].data = this.item.prognosis[selector] ?? []

      })

    }
  },

  methods: {
    ...mapActions({
      getOneElement: 'race/getOneElement',
      calcRaceResult: 'admin/calcRaceResult'
    }),

    async fillElem() {

      this.loader = true

      this.queryEvent.number = this.$route.params.number
      this.queryEvent.events = this.$route.params.event
      this.queryEvent.userToken = this.token

      await this.getOneElement()

    },

    async calcResult() {
      this.loader = true

      this.adminQueryEvent.race_id = this.raceInfo['race_id']

      await this.calcRaceResult()

      this.loader = false

    },

    setOtherLink() {
      this.prevLink = '/race/' + this.$route.params.event + '/' + String(Number(this.$route.params.number) - 1)
      this.nextLink = '/race/' + this.$route.params.event + '/' + String(Number(this.$route.params.number) + 1)
    },
  },



  computed: {
    ...mapState({
      token: state => state.auth.authData.token,
      queryEvent: state => state.race.queryEvent,
      item: state => state.race.oneRace,
      role: state => state.auth.userInfo.role,

      adminQueryEvent: state => state.admin.queryEvent,
      sensSuccess: state => state.admin.sendSuccess
    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.item_wrapper {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.item_title {
  display: flex;
  flex-direction: row;
  gap: 4px;
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;

  .title_cell {
    .shadow_inset;
    .flex_center;
  }
}

.item_name {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  gap: 4px;
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;

  .box {
    display: flex;
    flex-direction: row;
    gap: 4px;
  }

  .name_cell {
    .shadow_inset;
    .flex_center;
  }
}

.flag_cell {
  width: 24px;
  height: 24px;

  img {
    width: 100%;
  }
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

.block_gap {
  display: flex;
  flex-direction: column;
  gap: 8px;
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
    width: 100px;
    max-width: 40%;

    font-size: 12px;
  }
}

.empty_result_wrapper{
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;
  .empty_result{
    .shadow_inset;
  }
}
</style>