<template>
  <div
    class="ach_card"
    :class="[rankClass, { claimable, locked, done: allClaimed }]"
  >
    <div class="ach_plaque_wrap">
      <div class="ach_plaque">
        <div class="ach_spark ach_spark_top" aria-hidden="true" />

        <div class="ach_icon_sq">
          <img v-if="iconSrc" class="ach_icon_img" :src="iconSrc" alt="" />
          <span v-else class="ach_icon">{{ icon }}</span>
        </div>

        <div class="ach_progress">
          <div class="ach_progress_track">
            <div class="ach_progress_fill" :style="{ width: progressPct + '%' }" />
          </div>
          <span class="ach_progress_val">{{ displayProgress }}/{{ target }}</span>
        </div>

        <div v-if="levelCount > 1" class="ach_dashes">
          <span
            v-for="(lvl, idx) in levelCount"
            :key="idx"
            class="ach_dash"
            :class="dashClasses(idx)"
          />
        </div>
        <div v-else class="ach_dashes ach_dashes--single">
          <span class="ach_dash ach_dash--full" />
        </div>

        <div class="ach_title" :title="displayTitle">{{ displayTitle }}</div>

        <div class="ach_spark ach_spark_bottom" aria-hidden="true" />
      </div>
    </div>

    <button
      type="button"
      class="ach_claim_btn"
      :class="{ active: claimable }"
      :disabled="!claimable || claiming"
      @click.stop="onClaimClick"
    >
      Забрать
    </button>
  </div>
</template>

<script>
import { getAchievementIconSrc } from '@/config/footballMetricIcons';

const RANKS = ['bronze', 'silver', 'gold', 'platinum', 'mythic'];

export default {
  name: 'AchievementCard',
  props: {
    title: { type: String, default: '' },
    icon: { type: String, default: '🏅' },
    progress: { type: Number, default: 0 },
    target: { type: Number, default: 1 },
    rankIndex: { type: Number, default: 0 },
    levelThresholds: { type: Array, default: () => [] },
    claimedThreshold: { type: Number, default: 0 },
    claimable: { type: Boolean, default: false },
    locked: { type: Boolean, default: false },
    allClaimed: { type: Boolean, default: false },
    claiming: { type: Boolean, default: false },
  },
  emits: ['claim'],
  computed: {
    rankClass() {
      const idx = Math.max(0, Math.min(this.rankIndex, RANKS.length - 1));
      return `rank_${RANKS[idx]}`;
    },
    levelCount() {
      return this.levelThresholds.length;
    },
    displayTitle() {
      return String(this.title || '')
        .replace(/^Метрика:\s*/i, '')
        .replace(/^метрика\s+/i, '')
        .trim();
    },
    displayProgress() {
      const t = Number(this.target) || 1;
      const p = Number(this.progress) || 0;
      return Math.min(p, t);
    },
    progressPct() {
      const t = Number(this.target) || 1;
      return Math.max(0, Math.min(100, Math.round((this.displayProgress / t) * 100)));
    },
    iconSrc() {
      const key = String(this.icon || '').trim();
      if (!key) {
        return null;
      }
      return getAchievementIconSrc(key) || null;
    },
  },
  methods: {
    dashClasses(idx) {
      const rank = RANKS[Math.min(idx, RANKS.length - 1)];
      return {
        [`dash_${rank}`]: true,
        ok: this.progress >= this.levelThresholds[idx],
        claimed: this.claimedThreshold >= this.levelThresholds[idx],
      };
    },
    onClaimClick() {
      if (this.claimable && !this.claiming) {
        this.$emit('claim');
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

@ach-bg: #1a2834;
@ach-bg-deep: #121c26;
@ach-gold: #d4b86a;
@ach-gold-hi: #f2e4b8;
@ach-gold-lo: #8a7340;
@spark-gap: 7px;

@rank-bronze: #8c6239;
@rank-silver: #aeb8c2;
@rank-gold: #e0b84e;
@rank-platinum: #7ec8ff;
@rank-mythic: #c77dff;

.ach_card {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 6px;
  min-width: 0;
  padding: 0 0 4px;
}

.ach_plaque_wrap {
  width: 100%;
  filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.4));
}

.ach_plaque {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  box-sizing: border-box;
  padding: @spark-gap 8px @spark-gap;
  gap: @spark-gap;
  border-radius: 10px 10px 8px 8px;
  border: 1px solid @ach-gold;
  box-shadow:
    inset 0 0 0 2px fade(@ach-gold-lo, 70%),
    inset 0 0 0 3px fade(@ach-bg-deep, 90%),
    inset 0 10px 20px rgba(0, 0, 0, 0.4);
  color: @ach-gold-hi;
  overflow: visible;
  --rank-color: @rank-gold;
  background: linear-gradient(165deg, lighten(@ach-bg, 4%) 0%, @ach-bg 45%, @ach-bg-deep 100%);

  &::before {
    content: '';
    position: absolute;
    top: 7%;
    left: -3px;
    width: 9px;
    height: 13px;
    background: linear-gradient(180deg, @ach-bg, @ach-bg-deep);
    border: 1px solid @ach-gold;
    border-radius: 2px;
    box-shadow: inset 0 1px 0 fade(@ach-gold-hi, 20%);
    transform: rotate(-10deg);
    z-index: 2;
  }
}

.ach_card.locked .ach_plaque {
  filter: grayscale(0.8) brightness(0.78);

  .ach_icon {
    opacity: 0.6;
  }

  .ach_title {
    opacity: 0.65;
  }
}

.ach_card.claimable .ach_plaque {
  box-shadow:
    0 0 0 1px var(--rank-color),
    0 4px 12px rgba(0, 0, 0, 0.32),
    inset 0 0 0 2px fade(@ach-gold-lo, 70%),
    inset 0 0 0 3px fade(@ach-bg-deep, 90%),
    inset 0 10px 20px rgba(0, 0, 0, 0.4);
}

.ach_spark {
  flex-shrink: 0;
  width: 10px;
  height: 10px;
  pointer-events: none;

  &::before {
    content: '';
    display: block;
    width: 100%;
    height: 100%;
    background: linear-gradient(145deg, @ach-gold-hi, @ach-gold-lo);
    transform: rotate(45deg);
    border: 1px solid fade(@ach-gold-hi, 90%);
    box-shadow: 0 0 6px fade(@ach-gold, 75%);
  }
}

.ach_icon_sq {
  width: 58%;
  flex-shrink: 0;
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 2px;
  border: 1px solid fade(@ach-gold, 45%);
  box-shadow:
    inset 0 2px 6px rgba(0, 0, 0, 0.45),
    0 1px 0 fade(#fff, 10%);
  background: linear-gradient(145deg, lighten(@ach-bg, 6%) 0%, @ach-bg-deep 100%);
}

.ach_icon {
  font-size: clamp(28px, 9vw, 40px);
  line-height: 1;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.5));
}

.ach_icon_img {
  width: 72%;
  height: 72%;
  object-fit: contain;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.45));
}

.ach_progress {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 2px;
  align-items: stretch;
}

.ach_progress_track {
  height: 5px;
  border-radius: 2px;
  background: rgba(0, 0, 0, 0.45);
  overflow: hidden;
  box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.5);
}

.ach_progress_fill {
  height: 100%;
  border-radius: 2px;
  background: linear-gradient(90deg, var(--rank-color), lighten(@ach-gold-hi, 5%));
  box-shadow: 0 0 4px fade(@ach-gold, 35%);
  transition: width 0.25s ease;
}

.ach_progress_val {
  font-size: 10px;
  text-align: center;
  color: fade(@ach-gold-hi, 75%);
  font-weight: 600;
}

.ach_dashes {
  display: flex;
  gap: 3px;
  width: 100%;
  align-items: center;
}

.ach_dashes--single {
  .ach_dash--full {
    flex: 1;
    height: 3px;
    border-radius: 2px;
    background: linear-gradient(90deg, transparent, var(--rank-color), transparent);
    opacity: 0.85;
  }
}

.ach_dash {
  flex: 1;
  height: 3px;
  border-radius: 2px;
  background: fade(@ach-gold-lo, 28%);

  &.ok.dash_bronze,
  &.claimed.dash_bronze { background: @rank-bronze; }
  &.ok.dash_silver,
  &.claimed.dash_silver { background: @rank-silver; }
  &.ok.dash_gold,
  &.claimed.dash_gold { background: @rank-gold; }
  &.ok.dash_platinum,
  &.claimed.dash_platinum { background: @rank-platinum; }
  &.ok.dash_mythic,
  &.claimed.dash_mythic { background: @rank-mythic; }

  &.ok:not(.claimed) {
    opacity: 0.72;
  }
}

.ach_title {
  width: 100%;
  font-size: 10px;
  font-weight: 700;
  line-height: 1.25;
  text-align: center;
  color: @ach-gold-hi;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.ach_claim_btn {
  width: 100%;
  padding: 5px 8px;
  border: 0;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 700;
  line-height: 1.2;
  color: fade(#fff, 55%);
  background: #5a5f66;
  cursor: default;

  &.active {
    color: #1a1a1a;
    background: var(--rank-color);
    cursor: pointer;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);

    &:disabled {
      opacity: 0.65;
      cursor: default;
    }
  }
}

.rank_bronze {
  --rank-color: @rank-bronze;

  .ach_icon_sq {
    border-color: fade(@rank-bronze, 75%);
  }
}

.rank_silver {
  --rank-color: @rank-silver;

  .ach_icon_sq {
    border-color: fade(@rank-silver, 78%);
  }
}

.rank_gold {
  --rank-color: @rank-gold;

  .ach_icon_sq {
    border-color: fade(@rank-gold, 80%);
  }
}

.rank_platinum {
  --rank-color: @rank-platinum;

  .ach_icon_sq {
    border-color: fade(@rank-platinum, 78%);
  }
}

.rank_mythic {
  --rank-color: @rank-mythic;

  .ach_icon_sq {
    border-color: fade(@rank-mythic, 82%);
  }
}
</style>
