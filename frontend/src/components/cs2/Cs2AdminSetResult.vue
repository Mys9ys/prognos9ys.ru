<template>
  <PreLoader v-if="loader"></PreLoader>
  <div class="prognosis_block">
    <div class="part_block">
      <div class="title_block">
        <div class="item icon">0-0</div>
        <div class="item title">Счёт серии (карты):</div>
      </div>
      <div class="value_block">
        <div class="goal_block">
          <div class="minus goal_btn" @click="setMaps('minus', 7)">-</div>
          <div class="value">{{ data[7] }}</div>
          <div class="plus goal_btn" @click="setMaps('plus', 7)">+</div>
        </div>
        <div class="dash">–</div>
        <div class="goal_block">
          <div class="minus goal_btn" @click="setMaps('minus', 8)">-</div>
          <div class="value">{{ data[8] }}</div>
          <div class="plus goal_btn" @click="setMaps('plus', 8)">+</div>
        </div>
      </div>
    </div>

    <div class="prognosis_dash_line"></div>

    <div class="part_block">
      <div class="title_block">
        <div class="item icon">⚔</div>
        <div class="item title">Опены, %:</div>
      </div>
      <div class="value_block">
        <div class="match_domination_box">
          <div class="value left">{{ data[10] }}</div>
          <input class="domination_range" type="range" v-model.number="data[10]" min="0" max="100">
          <div class="value right">{{ 100 - data[10] }}</div>
        </div>
      </div>
    </div>

    <div class="prognosis_dash_line"></div>

    <div class="part_block">
      <div class="title_block">
        <div class="item icon">🔫</div>
        <div class="item title">Пистолетки, %:</div>
      </div>
      <div class="value_block">
        <div class="match_domination_box">
          <div class="value left">{{ data[11] }}</div>
          <input class="domination_range" type="range" v-model.number="data[11]" min="0" max="100">
          <div class="value right">{{ 100 - data[11] }}</div>
        </div>
      </div>
    </div>

    <div class="prognosis_dash_line"></div>

    <div class="part_block yellow">
      <div class="title_block">
        <div class="item icon">🎯₁</div>
        <div class="item title">Клатчи 1:</div>
      </div>
      <div class="value_block">
        <div class="box">
          <div class="btn" @click="bump(12, -1)">-</div>
          <div class="value">{{ data[12] }}</div>
          <div class="btn" @click="bump(12, 1)">+</div>
        </div>
      </div>
    </div>

    <div class="prognosis_dash_line"></div>

    <div class="part_block red">
      <div class="title_block">
        <div class="item icon">🎯₂</div>
        <div class="item title">Клатчи 2:</div>
      </div>
      <div class="value_block">
        <div class="box">
          <div class="btn" @click="bump(13, -1)">-</div>
          <div class="value">{{ data[13] }}</div>
          <div class="btn" @click="bump(13, 1)">+</div>
        </div>
      </div>
    </div>

    <div class="prognosis_dash_line"></div>

    <div class="part_block maps_block">
      <div class="title_block">
        <div class="item icon">M</div>
        <div class="item title">Счёт по картам (пики для статистики):</div>
      </div>
      <Cs2MapPicker
        v-model="localMapScores"
        :maps="maps"
        :slot-count="mapSlotCount"
        :show-pick-by="true"
      />
    </div>

    <div class="btns_block">
      <div class="btn_send btn_send_primary" @click="sendAndCalc">Сохранить и пересчитать</div>
      <div class="btn_send" @click="sendResult">Только сохранить</div>
    </div>

    <div class="error_message" v-if="error">{{ error }}</div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import PreLoader from '@/components/main/PreLoader';
import Cs2MapPicker from '@/components/cs2/Cs2MapPicker';
import { emptyMapScores, mapSlotsForFormat, maxMapsWin, normalizeMapScores } from '@/utils/cs2Format';

export default {
  name: 'Cs2AdminSetResult',
  components: { PreLoader, Cs2MapPicker },
  props: {
    id: Number,
    boFormat: { type: String, default: 'bo3' },
    result: { type: Object, default: () => ({}) },
    mapScores: { type: Array, default: () => [] },
    maps: { type: Array, default: () => [] },
  },
  data() {
    return {
      loader: false,
      error: '',
      localMapScores: emptyMapScores(mapSlotsForFormat(this.boFormat)),
      data: {
        7: this.result.maps_home ?? this.result.goal_home ?? 0,
        8: this.result.maps_guest ?? this.result.goal_guest ?? 0,
        10: this.result.opening_pct ?? this.result.domination ?? 50,
        11: this.result.pistol_pct ?? this.result.corner ?? 50,
        12: this.result.clutches_home ?? this.result.yellow ?? 0,
        13: this.result.clutches_guest ?? this.result.red ?? 0,
      },
    };
  },
  created() {
    this.localMapScores = normalizeMapScores(
      this.mapScores.length ? this.mapScores : this.result.map_scores,
      mapSlotsForFormat(this.boFormat),
    );
  },
  computed: {
    ...mapState({
      token: state => state.auth.authData.token,
      role: state => state.auth.userInfo.role,
    }),
    mapSlotCount() {
      return mapSlotsForFormat(this.boFormat);
    },
  },
  watch: {
    boFormat(format) {
      this.localMapScores = normalizeMapScores(this.localMapScores, mapSlotsForFormat(format));
    },
  },
  methods: {
    ...mapActions({
      setCs2Result: 'admin/setCs2Result',
      calcCs2Result: 'admin/calcCs2Result',
    }),

    syncDerived() {
      const home = Number(this.data[7]);
      const guest = Number(this.data[8]);
      this.data[26] = home + guest;
      this.data[25] = home - guest;
      if (this.data[25] > 0) this.data[9] = 'п1';
      else if (this.data[25] < 0) this.data[9] = 'п2';
      else this.data[9] = '';
    },

    setMaps(type, id) {
      const maxWin = maxMapsWin(this.boFormat);
      if (type === 'minus' && this.data[id] > 0) this.data[id]--;
      if (type === 'plus' && this.data[id] < maxWin) this.data[id]++;
      this.syncDerived();
    },

    bump(field, delta) {
      this.data[field] = Math.max(0, Number(this.data[field]) + delta);
    },

    buildPayload() {
      this.syncDerived();
      const maps = this.localMapScores
        .filter(item => item.rounds_home > 0 || item.rounds_guest > 0 || item.map_code)
        .map(item => ({
          slot: item.slot,
          map_id: Number(item.map_id || 0),
          map_code: item.map_code,
          rounds_home: Number(item.rounds_home),
          rounds_guest: Number(item.rounds_guest),
          pick_by: item.pick_by || '',
        }));

      return {
        matchId: this.id,
        userToken: this.token,
        role: this.role,
        data: {
          ...this.data,
          map_scores_json: JSON.stringify(maps),
        },
      };
    },

    async sendResult() {
      this.loader = true;
      this.error = '';
      this.$store.state.admin.queryEvent = this.buildPayload();
      await this.setCs2Result();
      this.loader = false;
    },

    async calcResult() {
      this.loader = true;
      this.$store.state.admin.queryEvent = { matchId: this.id, userToken: this.token, role: this.role };
      await this.calcCs2Result();
      this.loader = false;
    },

    async sendAndCalc() {
      await this.sendResult();
      await this.calcResult();
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
@import "src/assets/css/match-page.less";
</style>
