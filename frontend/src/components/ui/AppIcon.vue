<template>
  <span
    v-if="src"
    class="app_icon_box"
    :class="iconClass"
    :style="boxStyle"
    role="img"
    :aria-label="alt || name"
  >
    <img class="app_icon" :src="src" alt="">
  </span>
</template>

<script>
import { getIconSrc } from '@/config/iconPool';

export default {
  name: 'AppIcon',
  props: {
    name: {
      type: String,
      required: true,
    },
    size: {
      type: [Number, String],
      default: 24,
    },
    alt: {
      type: String,
      default: '',
    },
    iconClass: {
      type: String,
      default: '',
    },
    styleId: {
      type: String,
      default: '',
    },
  },
  computed: {
    src() {
      return getIconSrc(this.name, this.styleId || null);
    },
    boxStyle() {
      const px = typeof this.size === 'number' ? `${this.size}px` : this.size;
      return {
        width: px,
        height: px,
        minWidth: px,
        minHeight: px,
      };
    },
  },
};
</script>

<style lang="less" scoped>
.app_icon_box {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  vertical-align: middle;
  line-height: 0;
  overflow: hidden;
}

.app_icon {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position: center;
}
</style>
