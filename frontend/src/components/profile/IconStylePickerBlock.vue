<template>
  <div class="icon_style_block">
    <div class="hint">
      Пример одной иконки (мяч) в трёх стилях. Выбранный стиль сохранится и будет использоваться для всего набора.
    </div>
    <div class="grid">
      <button
        v-for="style in styles"
        :key="style.id"
        type="button"
        class="card"
        :class="{ active: selected === style.id }"
        @click="select(style.id)"
      >
        <div class="preview_wrap">
          <img class="preview" :src="style.preview" :alt="style.title">
        </div>
        <div class="card_title">{{ style.title }}</div>
        <div class="card_desc">{{ style.description }}</div>
      </button>
    </div>
    <div class="selected_line" v-if="currentStyle">
      Выбрано: <strong>{{ currentStyle.title }}</strong>
    </div>
  </div>
</template>

<script>
import {
  ICON_STYLES,
  getStoredIconStyle,
  setStoredIconStyle,
} from '@/config/iconStyles';

export default {
  name: 'IconStylePickerBlock',
  data() {
    return {
      selected: getStoredIconStyle(),
      styles: Object.values(ICON_STYLES),
    };
  },
  computed: {
    currentStyle() {
      return ICON_STYLES[this.selected] || null;
    },
  },
  methods: {
    select(styleId) {
      this.selected = styleId;
      setStoredIconStyle(styleId);
      this.$emit('change', styleId);
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.icon_style_block {
  text-align: left;
  padding: 4px 0 8px;
}

.hint {
  font-size: 12px;
  color: @colorBlur;
  margin-bottom: 10px;
  line-height: 1.35;
}

.grid {
  display: flex;
  flex-direction: row;
  gap: 8px;
  flex-wrap: wrap;
}

.card {
  flex: 1 1 calc(33.33% - 6px);
  min-width: 92px;
  background: @darkbg;
  border: 2px solid transparent;
  border-radius: 6px;
  padding: 8px 6px;
  cursor: pointer;
  color: @colorText;
  text-align: center;

  &.active {
    border-color: @orange;
    background: fade(@orange, 10%);
  }
}

.preview_wrap {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 72px;
  margin-bottom: 6px;
}

.preview {
  width: 56px;
  height: 56px;
  object-fit: contain;
}

.card_title {
  font-size: 13px;
  font-weight: 600;
  color: @orange;
}

.card_desc {
  font-size: 10px;
  color: @colorBlur;
  margin-top: 4px;
  line-height: 1.25;
}

.selected_line {
  margin-top: 10px;
  font-size: 12px;
  color: @colorBlur;

  strong {
    color: @colorText;
  }
}
</style>
