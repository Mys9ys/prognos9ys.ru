<template>
  <div class="cs2_map_picker">
    <div class="map_row" v-for="(map, index) in localScores" :key="map.slot">
      <div class="map_slot">Карта {{ map.slot }}</div>
      <div class="map_tiles">
        <button
          v-for="poolMap in maps"
          :key="poolMap.code"
          type="button"
          class="map_tile"
          :class="{ active: localScores[index].map_code === poolMap.code }"
          :title="poolMap.description || poolMap.name"
          @click="selectMap(index, poolMap)"
        >
          <img v-if="poolMap.image" :src="baseUrl + poolMap.image" :alt="poolMap.name">
          <span v-else class="map_label">{{ poolMap.name }}</span>
        </button>
        <button
          v-if="localScores[index].map_code"
          type="button"
          class="map_clear"
          @click="clearMap(index)"
        >×</button>
      </div>
      <div v-if="showPickBy" class="pick_by">
        <button
          type="button"
          class="pick_btn"
          :class="{ active: localScores[index].pick_by === 'home' }"
          @click="setPickBy(index, 'home')"
        >Пик 1</button>
        <button
          type="button"
          class="pick_btn"
          :class="{ active: localScores[index].pick_by === 'guest' }"
          @click="setPickBy(index, 'guest')"
        >Пик 2</button>
        <button
          type="button"
          class="pick_btn"
          :class="{ active: localScores[index].pick_by === 'decider' }"
          @click="setPickBy(index, 'decider')"
        >Десайдер</button>
      </div>
      <div class="rounds_row">
        <div class="goal_block">
          <div class="minus goal_btn" @click="bump(index, 'home', -1)">-</div>
          <div class="value">{{ map.rounds_home }}</div>
          <div class="plus goal_btn" @click="bump(index, 'home', 1)">+</div>
        </div>
        <div class="dash">–</div>
        <div class="goal_block">
          <div class="minus goal_btn" @click="bump(index, 'guest', -1)">-</div>
          <div class="value">{{ map.rounds_guest }}</div>
          <div class="plus goal_btn" @click="bump(index, 'guest', 1)">+</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { normalizeMapScores } from '@/utils/cs2Format';

export default {
  name: 'Cs2MapPicker',
  props: {
    maps: { type: Array, default: () => [] },
    value: { type: Array, default: () => [] },
    slotCount: { type: Number, default: 3 },
    showPickBy: { type: Boolean, default: false },
    baseUrl: { type: String, default: '' },
  },
  data() {
    return {
      localScores: normalizeMapScores(this.value, this.slotCount),
    };
  },
  watch: {
    value: {
      deep: true,
      handler(next) {
        this.localScores = normalizeMapScores(next, this.slotCount);
      },
    },
    slotCount(count) {
      this.localScores = normalizeMapScores(this.localScores, count);
    },
  },
  methods: {
    emitChange() {
      this.$emit('input', this.localScores.map(item => ({ ...item })));
    },
    selectMap(index, poolMap) {
      this.localScores[index].map_id = poolMap.id || 0;
      this.localScores[index].map_code = poolMap.code;
      this.emitChange();
    },
    clearMap(index) {
      this.localScores[index].map_id = 0;
      this.localScores[index].map_code = '';
      this.emitChange();
    },
    setPickBy(index, value) {
      this.localScores[index].pick_by = this.localScores[index].pick_by === value ? '' : value;
      this.emitChange();
    },
    bump(index, side, delta) {
      const key = side === 'home' ? 'rounds_home' : 'rounds_guest';
      this.localScores[index][key] = Math.max(0, Number(this.localScores[index][key]) + delta);
      this.emitChange();
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.cs2_map_picker {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.map_row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-bottom: 6px;
  border-bottom: 1px dashed fade(@colorBlur, 25%);
}

.map_slot {
  font-size: 11px;
  color: @YesWrite;
}

.map_tiles {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  align-items: center;
}

.map_tile {
  width: 52px;
  height: 34px;
  border: 1px solid fade(@colorBlur, 35%);
  border-radius: 4px;
  background: fade(@DarkColorBG, 80%);
  padding: 2px;
  cursor: pointer;

  &.active {
    border-color: @YesWrite;
    box-shadow: 0 0 0 1px fade(@YesWrite, 40%);
  }

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 2px;
  }

  .map_label {
    font-size: 9px;
    line-height: 30px;
    display: block;
    text-align: center;
    color: @YesWrite;
  }
}

.map_clear {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 1px solid fade(@colorBlur, 35%);
  background: transparent;
  color: @colorBlur;
  cursor: pointer;
}

.pick_by {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.pick_btn {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
  border: 1px solid fade(@colorBlur, 35%);
  background: transparent;
  color: @YesWrite;
  cursor: pointer;

  &.active {
    border-color: @YesWrite;
    color: @YesWrite;
  }
}

.rounds_row {
  display: flex;
  gap: 4px;
  justify-content: flex-end;
  align-items: center;
}
</style>
