<template>
  <span
    v-if="src && badge"
    class="football_metric_badge"
    :class="{ active }"
    :style="badgeStyle"
  >
    <img
      class="football_metric_icon football_metric_icon_fill"
      :src="src"
      alt=""
      role="img"
    >
  </span>
  <img
    v-else-if="src"
    class="football_metric_icon"
    :class="iconClass"
    :src="src"
    :style="boxStyle"
    alt=""
    role="img"
  >
</template>

<script>
import { getFootballMetricIconByKey, getFootballMetricIconSrc } from '@/config/footballMetricIcons';

export default {
  name: 'FootballMetricIcon',
  props: {
    metric: {
      type: String,
      default: '',
    },
    context: {
      type: String,
      default: '',
    },
    fieldId: {
      type: [Number, String],
      default: null,
    },
    size: {
      type: [Number, String],
      default: 22,
    },
    iconClass: {
      type: String,
      default: '',
    },
    badge: {
      type: Boolean,
      default: false,
    },
    active: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    src() {
      if (this.metric) {
        return getFootballMetricIconByKey(this.metric);
      }
      if (this.context && this.fieldId != null && this.fieldId !== '') {
        return getFootballMetricIconSrc(this.context, this.fieldId);
      }
      return null;
    },
    sizePx() {
      return typeof this.size === 'number' ? `${this.size}px` : this.size;
    },
    boxStyle() {
      return {
        width: this.sizePx,
        height: this.sizePx,
        minWidth: this.sizePx,
        minHeight: this.sizePx,
      };
    },
    badgeStyle() {
      return {
        width: this.sizePx,
        height: this.sizePx,
        minWidth: this.sizePx,
        minHeight: this.sizePx,
      };
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.football_metric_badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  box-sizing: border-box;
  background: @colorBlur;
  border-radius: 3px;
  overflow: hidden;
  flex-shrink: 0;
  line-height: 0;
  box-shadow: inset 0 2px 10px 1px rgba(0, 0, 0, .3), inset 0 0 0 200px rgba(0, 0, 0, .25), 0 1px rgba(255, 255, 255, .08);

  &.active {
    background: @YesWrite;
    box-shadow: inset 0 2px 10px 1px rgba(0, 0, 0, .25), inset 0 0 0 200px rgba(0, 0, 0, .12), 0 1px rgba(255, 255, 255, .12);
  }
}

.football_metric_icon {
  display: block;
  object-fit: contain;
  object-position: center;
  flex-shrink: 0;
}

.football_metric_icon_fill {
  width: 100%;
  height: 100%;
  min-width: 0;
  min-height: 0;
}
</style>
