<template>
  <Transition name="ach_reward_fade">
    <div v-if="visible" class="ach_reward_overlay" @click.self="close">
      <div class="ach_reward_toast" role="dialog" aria-live="polite">
        <div class="ach_reward_spark ach_reward_spark_top" aria-hidden="true" />
        <div class="ach_reward_head">Награда получена!</div>
        <div v-if="title" class="ach_reward_title">{{ title }}</div>
        <div v-if="threshold > 0" class="ach_reward_level">Уровень {{ threshold }}</div>

        <div v-if="rewardBits.length" class="ach_reward_items">
          <div
            v-for="bit in rewardBits"
            :key="bit.key"
            class="ach_reward_bit"
          >
            <AppIcon :name="bit.icon" :size="22" icon-class="ach_reward_icon" />
            <span v-if="bit.amount !== undefined" class="ach_reward_amount">+{{ bit.amount }}</span>
            <span v-if="bit.label" class="ach_reward_label">{{ bit.label }}</span>
          </div>
        </div>

        <div v-else class="ach_reward_empty">Награда зачислена</div>

        <button type="button" class="ach_reward_ok" @click="close">Отлично</button>
        <div class="ach_reward_spark ach_reward_spark_bottom" aria-hidden="true" />
      </div>
    </div>
  </Transition>
</template>

<script>
import AppIcon from '@/components/ui/AppIcon.vue';
import { buildAchievementRewardBits } from '@/utils/formatAchievementReward';

export default {
  name: 'AchievementRewardToast',
  components: { AppIcon },
  props: {
    visible: {
      type: Boolean,
      default: false,
    },
    title: {
      type: String,
      default: '',
    },
    threshold: {
      type: Number,
      default: 0,
    },
    reward: {
      type: Object,
      default: () => ({}),
    },
  },
  emits: ['close'],
  computed: {
    rewardBits() {
      return buildAchievementRewardBits(this.reward);
    },
  },
  watch: {
    visible(value) {
      if (value) {
        this.scheduleAutoClose();
      } else {
        this.clearAutoClose();
      }
    },
  },
  beforeUnmount() {
    this.clearAutoClose();
  },
  methods: {
    close() {
      this.clearAutoClose();
      this.$emit('close');
    },
    scheduleAutoClose() {
      this.clearAutoClose();
      this.autoCloseTimer = window.setTimeout(() => {
        this.close();
      }, 4500);
    },
    clearAutoClose() {
      if (this.autoCloseTimer) {
        window.clearTimeout(this.autoCloseTimer);
        this.autoCloseTimer = null;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.ach_reward_overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(0, 0, 0, 0.55);
  box-sizing: border-box;
}

.ach_reward_toast {
  position: relative;
  width: min(100%, 260px);
  padding: 12px 12px 10px;
  border-radius: 10px;
  border: 2px solid #d4af37;
  background: linear-gradient(180deg, #1a2744 0%, #0f1728 100%);
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.45), inset 0 0 0 1px rgba(212, 175, 55, 0.25);
  text-align: center;
}

.ach_reward_spark {
  flex-shrink: 0;
  width: 10px;
  height: 10px;
  margin: 0 auto;
  pointer-events: none;

  &::before {
    content: '';
    display: block;
    width: 100%;
    height: 100%;
    background: linear-gradient(145deg, #f2e4b8, #8a7340);
    transform: rotate(45deg);
    border: 1px solid fade(#f2e4b8, 90%);
    box-shadow: 0 0 6px fade(#d4b86a, 75%);
  }
}

.ach_reward_spark_top {
  margin-bottom: 6px;
}

.ach_reward_spark_bottom {
  margin-top: 8px;
  transform: rotate(180deg);
}

.ach_reward_head {
  color: #f0c85a;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: 4px;
}

.ach_reward_title {
  color: @colorText;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.25;
  margin-bottom: 2px;
}

.ach_reward_level {
  color: @colorBlur;
  font-size: 11px;
  margin-bottom: 8px;
}

.ach_reward_items {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 8px 12px;
  margin: 4px 0 10px;
}

.ach_reward_bit {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  max-width: 100%;
}

:deep(.ach_reward_icon) {
  width: 22px !important;
  height: 22px !important;
  min-width: 22px !important;
  min-height: 22px !important;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.35));
}

.ach_reward_amount {
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  line-height: 1;
}

.ach_reward_label {
  color: @colorText2;
  font-size: 11px;
  line-height: 1.2;
  max-width: 110px;
  text-align: left;
}

.ach_reward_empty {
  color: @colorBlur;
  font-size: 12px;
  margin: 8px 0 12px;
}

.ach_reward_ok {
  width: 100%;
  border: 0;
  border-radius: 6px;
  padding: 8px 12px;
  background: linear-gradient(180deg, #f0b429 0%, #d48806 100%);
  color: #1a1200;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.ach_reward_fade-enter-active,
.ach_reward_fade-leave-active {
  transition: opacity 0.2s ease;
}

.ach_reward_fade-enter-active .ach_reward_toast,
.ach_reward_fade-leave-active .ach_reward_toast {
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.ach_reward_fade-enter-from,
.ach_reward_fade-leave-to {
  opacity: 0;
}

.ach_reward_fade-enter-from .ach_reward_toast,
.ach_reward_fade-leave-to .ach_reward_toast {
  transform: scale(0.92);
  opacity: 0;
}
</style>
