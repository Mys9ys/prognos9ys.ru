<template>
  <div class="bulk_overlay" v-if="visible">
    <div class="bulk_panel">
      <div class="bulk_panel_title">{{ title }}</div>
      <div class="bulk_progress_meta" v-if="total > 0">
        {{ current }} / {{ total }}
      </div>
      <div class="bulk_progress_track" v-if="total > 0">
        <div class="bulk_progress_fill" :style="{ width: progressPercent + '%' }"></div>
      </div>
      <div class="bulk_log" ref="log">
        <div
            v-for="(line, index) in lines"
            :key="index"
            class="bulk_log_line"
            :class="line.status"
        >{{ line.text }}</div>
      </div>
      <button
          v-if="done"
          type="button"
          class="bulk_close_btn"
          @click="$emit('close')"
      >Закрыть</button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'BulkActionProgress',
  props: {
    visible: { type: Boolean, default: false },
    title: { type: String, default: 'Обработка…' },
    lines: { type: Array, default: () => [] },
    current: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    done: { type: Boolean, default: false },
  },
  computed: {
    progressPercent() {
      if (this.total <= 0) {
        return 0;
      }
      return Math.min(100, Math.round((this.current / this.total) * 100));
    },
  },
  watch: {
    lines() {
      this.$nextTick(() => {
        const el = this.$refs.log;
        if (el) {
          el.scrollTop = el.scrollHeight;
        }
      });
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.bulk_overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  background: rgba(0, 0, 0, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
}

.bulk_panel {
  width: 100%;
  max-width: 400px;
  max-height: 85vh;
  background: @DarkColorBG;
  border: 2px solid @orange;
  border-radius: 8px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.bulk_panel_title {
  font-size: 14px;
  font-weight: 700;
  color: @orange;
  text-align: center;
}

.bulk_progress_meta {
  font-size: 12px;
  color: @colorBlur;
  text-align: center;
}

.bulk_progress_track {
  height: 6px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  overflow: hidden;
}

.bulk_progress_fill {
  height: 100%;
  background: @YesWrite;
  border-radius: 4px;
  transition: width 0.2s ease;
}

.bulk_log {
  flex: 1;
  min-height: 120px;
  max-height: 50vh;
  overflow-y: auto;
  background: rgba(0, 0, 0, 0.25);
  border-radius: 4px;
  padding: 6px 8px;
  font-size: 11px;
  line-height: 1.35;
}

.bulk_log_line {
  margin-bottom: 3px;
  color: @colorText;

  &.ok {
    color: @YesWrite;
  }

  &.skip {
    color: @colorBlur;
  }

  &.fail {
    color: #f88;
  }

  &.pending {
    color: @yellow;
  }
}

.bulk_close_btn {
  background: @orange;
  color: #fff;
  border: none;
  border-radius: 4px;
  padding: 8px 12px;
  font-size: 13px;
  cursor: pointer;
}
</style>
