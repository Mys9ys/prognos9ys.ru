<template>
  <PreLoader v-if="prognosisLoader"></PreLoader>
  <SendSuccess v-if="prognosisSuccess" :closeSuccess="closeSuccess"></SendSuccess>
  <ActionFailure v-if="actionFailure" :closeSuccess="closeSuccess">{{ errorMessage }}</ActionFailure>

  <div v-else class="match_wrapper">
    <PageHeader class="header" :path="'/cs2/' + $route.params.event">Игра № {{ $route.params.number }}</PageHeader>
    <div class="match_title">
      <div class="number title_cell"># {{ arMatch.number }}</div>
      <div class="date title_cell">&#128197; {{ arMatch.date }}</div>
      <div class="time title_cell">&#128344; {{ arMatch.time }}</div>
      <div class="stage title_cell">{{ boLabel }}</div>
      <div class="stage title_cell" v-if="arMatch.tur">Тур: {{ arMatch.tur }}</div>
    </div>

    <div class="teams_block" v-if="home">
      <div class="teams_names_row">
        <div class="team team_home">
          <div class="flag"><img :src="urlImg + home.flag" alt=""></div>
          <div class="name name_home">{{ home.name }}</div>
        </div>
        <div class="dash">–</div>
        <div class="team team_guest">
          <div class="name name_guest">{{ guest.name }}</div>
          <div class="flag"><img :src="urlImg + guest.flag" alt=""></div>
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

    <div v-if="admin" class="block_gap">
      <Cs2AdminSetResult :id="arMatch.id" :bo-format="boFormat" :result="matchR" :map-scores="mapScores" :maps="cs2Maps" />
    </div>

    <div v-else>
      <div class="guest_prognosis_block" v-if="arMatch.active === 'Y' && !token">
        <p class="guest_prognosis_text">Посмотрите матч и сделайте прогноз — для этого нужен аккаунт.</p>
        <div class="btn_send guest_cta" @click="goToAuthForPrognosis">Сделать прогноз</div>
      </div>

      <div class="match_record_wrapper" v-else-if="arMatch.active === 'Y'">
        <div class="prognosis_block">
          <div class="time_send" v-if="prognosis?.time_send">
            <div class="title_block">Заполнено: {{ prognosis.time_send }}</div>
          </div>

          <div class="part_block">
            <div class="title_block">
              <div class="item icon">{{ icons.maps }}</div>
              <div class="item title">{{ title.maps }}:</div>
            </div>
            <div class="value_block">
              <div class="goal_block">
                <div class="zero goal_btn" @click="setSeriesMaps('zero', 15)">0</div>
                <div class="minus goal_btn" @click="setSeriesMaps('minus', 15)">-</div>
                <div class="value">{{ data[15] }}</div>
                <div class="plus goal_btn" @click="setSeriesMaps('plus', 15)">+</div>
                <div class="two goal_btn" @click="setSeriesMaps('two', 15)">2</div>
              </div>
              <div class="dash">–</div>
              <div class="goal_block">
                <div class="zero goal_btn" @click="setSeriesMaps('zero', 16)">0</div>
                <div class="minus goal_btn" @click="setSeriesMaps('minus', 16)">-</div>
                <div class="value">{{ data[16] }}</div>
                <div class="plus goal_btn" @click="setSeriesMaps('plus', 16)">+</div>
                <div class="two goal_btn" @click="setSeriesMaps('two', 16)">2</div>
              </div>
            </div>
          </div>

          <div class="prognosis_dash_line"></div>

          <div class="part_block">
            <div class="title_block auto_block_title">
              <div class="item auto_title_text">Заполняется автоматически</div>
              <div class="more_btn" @click="autoBlock = !autoBlock">
                <span :class="{ close: !autoBlock, open: autoBlock }"> > </span>
              </div>
              <label class="bet_checkbox" :class="{ bet_checkbox_disabled: !canAffordBet }">
                <input class="bet_input" type="checkbox" v-model="withBet" :disabled="!canAffordBet" @change="withBetUserTouched = true">
                <span class="bet_checkbox_text">Ставка 10 <AppIcon name="prognobak" :size="14" class="bet_coin_icon" /></span>
              </label>
            </div>
          </div>

          <div class="auto_block" v-if="autoBlock">
            <div class="prognosis_dash_line"></div>
            <div class="part_block">
              <div class="title_block block_absolute">
                <div class="item icon">{{ icons.result }}</div>
                <div class="item title">{{ title.result }}:</div>
              </div>
              <div class="value_block">
                <div class="value_box">
                  <div class="match_result_el" :class="{ active: data[18] === 'п1' }" @click="setResult('п1')">п1</div>
                  <div class="match_result_el" :class="{ active: data[18] === 'п2' }" @click="setResult('п2')">п2</div>
                </div>
              </div>
            </div>
            <div class="prognosis_dash_line"></div>
            <div class="part_block">
              <div class="title_block block_absolute">
                <div class="item icon">{{ icons.sum }}</div>
                <div class="item title">{{ title.sum }}:</div>
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
                <div class="item icon">{{ icons.diff }}</div>
                <div class="item title">{{ title.diff }}:</div>
              </div>
              <div class="value_block">
                <div class="minus math_btn" @click="setMath('minus', 19)">-</div>
                <div class="value">{{ data[19] }}</div>
                <div class="plus math_btn" @click="setMath('plus', 19)">+</div>
              </div>
            </div>
          </div>

          <div class="prognosis_dash_line"></div>

          <div class="part_block maps_block">
            <div class="title_block">
              <div class="item icon">M</div>
              <div class="item title">{{ title.maps_detail }}:</div>
              <div class="more_btn" @click="mapsBlock = !mapsBlock">
                <span :class="{ close: !mapsBlock, open: mapsBlock }"> > </span>
              </div>
            </div>
            <div v-if="mapsBlock" class="maps_list">
              <Cs2MapPicker
                v-model="mapScores"
                :maps="cs2Maps"
                :slot-count="mapSlotCount"
                :base-url="urlImg"
              />
            </div>
          </div>

          <div class="prognosis_dash_line"></div>

          <div class="part_block">
            <div class="title_block">
              <div class="item icon">{{ icons.opening }}</div>
              <div class="item title">{{ title.opening }}:</div>
            </div>
            <div class="value_block">
              <div class="match_domination_box">
                <div class="minus math_btn" @click="setRangeBtn('opening', 'minus')">+</div>
                <div class="value left">{{ data[32] }}</div>
                <input class="domination_range" type="range" :value="data[32]" @input="rangeChange('opening', $event)">
                <div class="value right">{{ 100 - Number(data[32]) }}</div>
                <div class="plus math_btn" @click="setRangeBtn('opening', 'plus')">+</div>
                <div class="plus math_btn" @click="setRangeBtn('opening', 'half')">50</div>
              </div>
            </div>
          </div>

          <div class="prognosis_dash_line"></div>

          <div class="part_block">
            <div class="title_block">
              <div class="item icon">{{ icons.pistol }}</div>
              <div class="item title">{{ title.pistol }}:</div>
            </div>
            <div class="value_block">
              <div class="match_domination_box">
                <div class="minus math_btn" @click="setRangeBtn('pistol', 'minus')">+</div>
                <div class="value left">{{ data[20] }}</div>
                <input class="domination_range" type="range" :value="data[20]" @input="rangeChange('pistol', $event)">
                <div class="value right">{{ 100 - Number(data[20]) }}</div>
                <div class="plus math_btn" @click="setRangeBtn('pistol', 'plus')">+</div>
                <div class="plus math_btn" @click="setRangeBtn('pistol', 'half')">50</div>
              </div>
            </div>
          </div>

          <div class="prognosis_dash_line"></div>

          <div class="part_block yellow">
            <div class="title_block block_absolute">
              <div class="item icon">{{ icons.clutchHome }}</div>
              <div class="item title">{{ title.clutchHome }}:</div>
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
              <div class="item icon">{{ icons.clutchGuest }}</div>
              <div class="item title">{{ title.clutchGuest }}:</div>
            </div>
            <div class="value_block">
              <div class="box">
                <div class="btn" @click="setValue('zero', 22)">0</div>
                <div class="btn" @click="setValue('minus', 22)">-</div>
                <div class="value">{{ data[22] }}</div>
                <div class="btn" @click="setValue('plus', 22)">+</div>
                <div class="btn" @click="setValue('five', 22)">5</div>
              </div>
            </div>
          </div>

          <div class="btns_block">
            <div class="annotation_btn" @click="annotationVis = !annotationVis">
              Расшифровка <span class="annotation_arrow" :class="{ up: annotationVis }">v</span>
            </div>
            <div class="btn_send" @click="sendPrognosis" v-if="!prognosis?.result">Отправить</div>
            <div class="btn_send rewrite" @click="sendPrognosis" v-else>Изменить</div>
          </div>
        </div>

        <div class="error_message" v-if="error">{{ error }}</div>
      </div>

      <div class="match_result_wrapper" v-else>
        <Cs2ResultTable :match="arMatch" />
      </div>

      <div class="btn_select_other_wrapper">
        <div class="other_match_btn" v-if="$route.params.number > 1" @click="$router.push(prevLink).then(() => $router.go())">
          &#60; {{ Number($route.params.number) - 1 }}
        </div>
        <div class="other_match_btn inactive" v-else>&#60; {{ Number($route.params.number) - 1 }}</div>
        <div class="other_match_btn" v-if="Number($route.params.number) < arMatch.max" @click="$router.push(nextLink).then(() => $router.go())">
          {{ Number($route.params.number) + 1 }} >
        </div>
        <div class="other_match_btn inactive" v-else>{{ Number($route.params.number) + 1 }} ></div>
      </div>
    </div>
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader';
import { mapActions, mapState } from 'vuex';
import PreLoader from '@/components/main/PreLoader';
import SendSuccess from '@/components/main/SendSuccess';
import ActionFailure from '@/components/main/ActionFailure';
import Cs2AdminSetResult from '@/components/cs2/Cs2AdminSetResult';
import Cs2MapPicker from '@/components/cs2/Cs2MapPicker';
import Cs2ResultTable from '@/components/cs2/Cs2ResultTable';
import AppIcon from '@/components/ui/AppIcon.vue';
import { apiActions } from '@/api/bitrixClient';
import { authRoute } from '@/utils/authRedirect';
import {
  boFormatLabel,
  emptyMapScores,
  mapSlotsForFormat,
  maxMapsWin,
  normalizeMapScores,
} from '@/utils/cs2Format';

export default {
  name: 'Cs2Page',
  components: {
    ActionFailure,
    Cs2AdminSetResult,
    Cs2MapPicker,
    PageHeader,
    PreLoader,
    SendSuccess,
    Cs2ResultTable,
    AppIcon,
  },
  data() {
    return {
      admin: false,
      prognosisLoader: false,
      prognosisSuccess: false,
      actionFailure: false,
      autoBlock: false,
      mapsBlock: true,
      annotationVis: false,
      urlImg: 'https://prognos9ys.ru/',
      prevLink: '',
      nextLink: '',
      error: '',
      withBet: true,
      withBetUserTouched: false,
      mapScores: emptyMapScores(3),
      cs2Maps: [],
      data: {
        30: this.$route.params.number,
        17: '',
        15: 0,
        16: 0,
        18: '',
        19: '',
        28: '',
        32: 50,
        20: 50,
        21: 0,
        22: 0,
        52: this.$route.params.event,
        29: '',
      },
      icons: {
        maps: '0-0',
        result: '✓',
        sum: 'Σ',
        diff: 'Δ',
        opening: '⚔',
        pistol: '🔫',
        clutchHome: '🎯₁',
        clutchGuest: '🎯₂',
      },
      title: {
        maps: 'Счёт серии (карты)',
        result: 'Исход серии',
        sum: 'Сумма карт',
        diff: 'Разница карт',
        maps_detail: 'Счёт по картам (раунды)',
        opening: 'Опены (серия), %',
        pistol: 'Пистолетки (серия), %',
        clutchHome: 'Клатчи команды 1',
        clutchGuest: 'Клатчи команды 2',
      },
    };
  },
  created() {
    this.loadMaps();
    this.fillMatchElem();
    this.setOtherLink();
  },
  watch: {
    canAffordBet(afford) {
      this.applyDefaultWithBet(afford);
    },
    boFormat(format) {
      this.mapScores = normalizeMapScores(this.mapScores, mapSlotsForFormat(format));
      this.serializeMapScores();
    },
    mapScores: {
      deep: true,
      handler() {
        this.serializeMapScores();
      },
    },
  },
  computed: {
    ...mapState({
      arMatch: state => state.cs2.match,
      home: state => state.cs2.match.home,
      guest: state => state.cs2.match.guest,
      queryMatch: state => state.cs2.queryMatch,
      queryPrognosis: state => state.cs2.queryPrognosis,
      token: state => state.auth.authData.token,
      prognosis: state => state.cs2.match.prognosis,
      matchR: state => state.cs2.match.match_result,
      role: state => state.auth.userInfo.role,
      userInfo: state => state.auth.userInfo,
      errors: state => state.cs2.errors,
    }),
    boFormat() {
      return this.arMatch?.bo_format || 'bo3';
    },
    boLabel() {
      return boFormatLabel(this.boFormat);
    },
    mapSlotCount() {
      return mapSlotsForFormat(this.boFormat);
    },
    canAffordBet() {
      return Number(this.userInfo?.game_info?.wallet?.prognobaks ?? 0) >= 10;
    },
    errorMessage() {
      return this.errors?.mes || (typeof this.errors === 'string' ? this.errors : '') || 'Не удалось сохранить прогноз';
    },
  },
  methods: {
    ...mapActions({
      getMatchRequest: 'cs2/getMatchRequest',
      sendUserPrognosis: 'cs2/sendUserPrognosis',
      refreshGameInfo: 'auth/refreshGameInfo',
    }),

    setOtherLink() {
      const event = this.$route.params.event;
      const num = this.$route.params.number;
      this.prevLink = `/cs2/${event}/${Number(num) - 1}`;
      this.nextLink = `/cs2/${event}/${Number(num) + 1}`;
    },

    syncSeriesFromMaps() {
      this.data[28] = Number(this.data[15]) + Number(this.data[16]);
      this.data[19] = Number(this.data[15]) - Number(this.data[16]);
      if (this.data[19] > 0) this.data[18] = 'п1';
      else if (this.data[19] < 0) this.data[18] = 'п2';
      else this.data[18] = '';
    },

    setSeriesMaps(type, id) {
      const maxWin = maxMapsWin(this.boFormat);
      const maxTotal = mapSlotsForFormat(this.boFormat);
      const otherId = id === 15 ? 16 : 15;

      if (type === 'minus' && this.data[id] > 0) this.data[id]--;
      if (type === 'plus' && this.data[id] < maxWin) this.data[id]++;
      if (type === 'zero') this.data[id] = 0;
      if (type === 'two' && maxWin >= 2) this.data[id] = 2;
      if (type === 'five' && maxWin >= 3) this.data[id] = 3;

      const total = Number(this.data[15]) + Number(this.data[16]);
      if (total > maxTotal) {
        this.data[id] = Math.max(0, this.data[id] - (total - maxTotal));
      }

      const winnerMaps = Math.max(this.data[15], this.data[16]);
      const loserMaps = Math.min(this.data[15], this.data[16]);
      if (winnerMaps >= maxWin && loserMaps > 0 && type !== 'minus') {
        this.data[otherId] = Math.min(this.data[otherId], maxWin - 1);
      }

      this.syncSeriesFromMaps();
    },

    setMapRounds(index, side, delta) {
      const key = side === 'home' ? 'rounds_home' : 'rounds_guest';
      const next = Math.max(0, Number(this.mapScores[index][key]) + delta);
      this.mapScores[index][key] = next;
      this.serializeMapScores();
    },

    serializeMapScores() {
      const payload = this.mapScores
        .filter(item => item.rounds_home > 0 || item.rounds_guest > 0 || item.map_code)
        .map(item => ({
          slot: item.slot,
          map_id: Number(item.map_id || 0),
          map_code: item.map_code,
          rounds_home: Number(item.rounds_home),
          rounds_guest: Number(item.rounds_guest),
        }));
      this.data[29] = JSON.stringify(payload);
    },

    async loadMaps() {
      try {
        const res = await apiActions.cs2.getMaps();
        this.cs2Maps = res.maps || [];
      } catch (e) {
        this.cs2Maps = this.arMatch?.maps || [];
      }
    },

    syncFormFromPrognosis() {
      const p = this.arMatch?.prognosis ?? {};
      this.data[15] = p.maps_home ?? p.goal_home ?? 0;
      this.data[16] = p.maps_guest ?? p.goal_guest ?? 0;
      this.data[18] = p.result ?? '';
      this.data[19] = p.diff ?? '';
      this.data[28] = p.sum ?? '';
      this.data[32] = p.opening_pct ?? p.domination ?? 50;
      this.data[20] = p.pistol_pct ?? p.corner ?? 50;
      this.data[21] = p.clutches_home ?? p.yellow ?? 0;
      this.data[22] = p.clutches_guest ?? p.red ?? 0;
      this.mapScores = normalizeMapScores(p.map_scores, mapSlotsForFormat(this.boFormat));
      this.serializeMapScores();
    },

    setMath(operation, id, type = '') {
      if (operation === 'minus') {
        if (type === 'sum') {
          if (this.data[id] > 0) this.data[id]--;
        } else {
          this.data[id]--;
        }
      }
      if (operation === 'plus') this.data[id]++;
    },

    setResult(res) {
      this.data[18] = res;
    },

    setValue(type, id) {
      if (type === 'minus' && this.data[id] > 0) this.data[id]--;
      if (type === 'plus') this.data[id]++;
      if (type === 'zero') this.data[id] = 0;
      if (type === 'five') this.data[id] = 5;
    },

    rangeChange(kind, event) {
      const value = Number(event.target.value);
      if (kind === 'opening') this.data[32] = value;
      if (kind === 'pistol') this.data[20] = value;
    },

    setRangeBtn(kind, type) {
      const field = kind === 'opening' ? 32 : 20;
      if (type === 'minus' && this.data[field] > 0) this.data[field]--;
      if (type === 'plus' && this.data[field] < 100) this.data[field]++;
      if (type === 'half') this.data[field] = 50;
    },

    applyDefaultWithBet(afford = this.canAffordBet) {
      if (!this.token || this.arMatch?.active !== 'Y') return;
      if (!afford) {
        this.withBet = false;
        return;
      }
      if (!this.withBetUserTouched) this.withBet = true;
    },

    async sendPrognosis() {
      if (!this.token) {
        this.goToAuthForPrognosis();
        return;
      }

      this.prognosisLoader = true;
      this.error = '';
      this.actionFailure = false;
      this.serializeMapScores();

      if (!this.data[18]) {
        this.error = 'Укажите счёт серии по картам';
        this.prognosisLoader = false;
        return;
      }

      try {
        this.queryPrognosis.userToken = this.token;
        this.data[17] = this.arMatch.id;
        this.data[30] = this.$route.params.number;
        this.data[52] = this.$route.params.event;
        this.queryPrognosis.fields = { ...this.data };
        this.queryPrognosis.map_scores_json = this.data[29];
        this.queryPrognosis.withBet = this.withBet;

        const result = await this.sendUserPrognosis();
        if (result?.ok) {
          this.prognosisSuccess = true;
          await this.getMatchRequest();
          this.syncFormFromPrognosis();
        } else {
          this.actionFailure = true;
        }
      } finally {
        this.prognosisLoader = false;
      }
    },

    closeSuccess() {
      this.prognosisSuccess = false;
      this.actionFailure = false;
    },

    goToAuthForPrognosis() {
      this.$router.push(authRoute(this.$route.fullPath));
    },

    async fillMatchElem() {
      this.prognosisLoader = true;
      this.withBetUserTouched = false;
      this.queryMatch.number = this.$route.params.number;
      this.queryMatch.eventId = this.$route.params.event;
      this.queryMatch.userToken = this.token;
      if (this.token) await this.refreshGameInfo();
      await this.getMatchRequest();
      this.mapScores = normalizeMapScores([], mapSlotsForFormat(this.boFormat));
      this.syncFormFromPrognosis();
      this.applyDefaultWithBet();
      this.prognosisLoader = false;
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
@import "src/assets/css/match-page.less";

.maps_list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 6px;
}

.map_row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  justify-content: flex-end;

  .map_slot {
    font-size: 11px;
    min-width: 52px;
    text-align: left;
    color: @YesWrite;
  }

  .map_name {
    width: 72px;
    font-size: 11px;
    padding: 2px 4px;
    border-radius: 3px;
    border: 1px solid @maxdarkgrey;
    background: @darkbg;
    color: @colorText;
  }
}
</style>
