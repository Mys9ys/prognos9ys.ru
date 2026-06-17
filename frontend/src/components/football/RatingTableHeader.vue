<template>
  <div class="rating_table_header">
    <div class="left_badge">
      <div class="podium">
        <span class="place place_2">2</span>
        <span class="place place_1">1</span>
        <span class="place place_3">3</span>
      </div>
      <div class="icon" :class="iconClass">{{ glyph }}</div>
    </div>

    <div class="right_controls">
      <div class="title">{{ title }}</div>
      <select class="match_select" v-model="model">
        <option v-for="n in sortedNumbers" :key="n" :value="String(n)">
          №{{ n }} — {{ matchTitle(n) }}
        </option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RatingTableHeader',
  props: {
    iconKey: { type: [String, Number], default: 1 },
    glyph: { type: String, default: '♛' },
    title: { type: String, default: 'Рейтинг' },
    matchNumbers: { type: Array, default: () => [] },
    modelValue: { type: [String, Number], default: '' },
    matchTitles: { type: Object, default: () => ({}) },
  },
  emits: ['update:modelValue'],
  computed: {
    iconClass() {
      const key = Number(this.iconKey);
      return {
        icon_yellow: key === 21,
        icon_red: key === 22,
      };
    },
    sortedNumbers() {
      return [...(this.matchNumbers || [])]
        .map((x) => Number(x))
        .filter((n) => n > 0)
        .sort((a, b) => b - a);
    },
    model: {
      get() {
        return String(this.modelValue ?? '');
      },
      set(v) {
        this.$emit('update:modelValue', v);
      },
    },
  },
  methods: {
    matchTitle(n) {
      const key = String(n);
      return this.matchTitles?.[key] || this.matchTitles?.[n] || 'матч';
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.rating_table_header {
  display: flex;
  align-items: center;
  gap: 8px;
  background: @DarkColorBG;
  border-radius: 5px;
  padding: 6px;
  margin-top: 4px;
}

.left_badge {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.podium {
  display: flex;
  align-items: flex-end;
  gap: 2px;

  .place {
    width: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px 4px 0 0;
    font-size: 9px;
    font-weight: 700;
    color: @DarkColorBG;
    .shadow_inset;
    padding: 0;
  }
  .place_1 { height: 18px; background: @maxYellow; }
  .place_2 { height: 14px; background: @colorBlur; }
  .place_3 { height: 12px; background: fade(@maxred, 85%); }
}

.icon {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: @colorText;
  background: fade(@darkbg, 60%);
  box-shadow: 0 0 0 2px fade(@YesWrite, 20%);

  &.icon_yellow { color: @maxYellow; box-shadow: 0 0 0 2px fade(@maxYellow, 20%); }
  &.icon_red { color: @maxred; box-shadow: 0 0 0 2px fade(@maxred, 20%); }
}

.right_controls {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.title {
  .shadow_inset;
  padding: 6px 8px;
  font-weight: 700;
  font-size: 12px;
  color: @colorText;
}

.match_select {
  .shadow_inset;
  width: 100%;
  border: none;
  outline: none;
  background: transparent;
  color: @pearl;
  font-size: 12px;
  padding: 6px 8px;
  border-radius: 5px;
  font-family: 'Roboto', sans-serif;

  option {
    color: @darkbg;
  }
}
</style>

