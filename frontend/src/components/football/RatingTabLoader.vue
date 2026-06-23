<template>
  <div class="rating_tab_loader">
    <div class="loader_stage">
      <div class="orbit orbit_a"></div>
      <div class="orbit orbit_b"></div>
      <div class="podium">
        <span class="place place_2">2</span>
        <span class="place place_1">1</span>
        <span class="place place_3">3</span>
      </div>
      <div class="loader_icon" :class="iconClass">
        <FootballMetricIcon context="rating" :field-id="iconKey" :size="28" badge />
      </div>
    </div>

    <p class="loader_title">{{ title }}</p>
    <p class="loader_phrase">{{ phrase }}</p>

    <div class="skeleton_table">
      <div class="skeleton_row" v-for="n in 5" :key="n" :style="{ animationDelay: `${n * 0.12}s` }">
        <span class="sk_place"></span>
        <span class="sk_diff"></span>
        <span class="sk_user"></span>
        <span class="sk_score"></span>
      </div>
    </div>
  </div>
</template>

<script>
import FootballMetricIcon from '@/components/football/FootballMetricIcon.vue';

const PHRASES = {
  1: ['Сводим общий зачёт…', 'Суммируем все категории…', 'Корона почти готова…'],
  2: ['Считаем точные счёта…', 'Сверяем голы…', 'Таблица 0:0 оживает…'],
  18: ['Определяем исходы…', 'п1, н или п2?..', 'Считаем победителей…'],
  28: ['Считаем разницу мячей…', 'Δ на кону…', 'Кто точнее угадал…'],
  19: ['Складываем голы…', 'Σ растёт…', 'Суммарный счёт…'],
  32: ['Владение мячом…', 'Проценты на линии…', 'Кто ближе к реальности…'],
  21: ['Жёлтые карточки…', '▮ копятся…', 'Дисциплина в рейтинге…'],
  22: ['Красные карточки…', '▮ решают всё…', 'Строгий зачёт…'],
  20: ['Угловые летят…', '🡬 с угла поля…', 'Флажки на местах…'],
  23: ['Пенальти на весах…', '◒ решают судьбу…', 'Точность с точки…'],
  45: ['Доп. время…', '+◔ тикают…', 'Продления учтены…'],
  46: ['Серия пенальти…', '+◒ нервы на пределе…', 'После матча…'],
  100: ['Лучшие прогнозы…', '♚ выбирает чемпионов…', 'Только >30 баллов…'],
};

export default {
  name: 'RatingTabLoader',
  components: { FootballMetricIcon },
  props: {
    iconKey: {
      type: [String, Number],
      default: 1,
    },
    title: {
      type: String,
      default: 'Загрузка рейтинга…',
    },
  },
  data() {
    return {
      phraseIndex: 0,
      phraseTimer: null,
    };
  },
  computed: {
    iconClass() {
      const key = Number(this.iconKey);
      return {
        icon_yellow: key === 21,
        icon_red: key === 22,
      };
    },
    phrases() {
      return PHRASES[this.iconKey] || PHRASES[1];
    },
    phrase() {
      return this.phrases[this.phraseIndex % this.phrases.length];
    },
  },
  mounted() {
    this.phraseTimer = setInterval(() => {
      this.phraseIndex = (this.phraseIndex + 1) % this.phrases.length;
    }, 1400);
  },
  beforeUnmount() {
    if (this.phraseTimer) {
      clearInterval(this.phraseTimer);
    }
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.rating_tab_loader {
  margin-top: 4px;
  padding: 12px 8px 10px;
  border-radius: 5px;
  background: @DarkColorBG;
  text-align: center;
  overflow: hidden;
}

.loader_stage {
  position: relative;
  width: 120px;
  height: 88px;
  margin: 0 auto 8px;
}

.orbit {
  position: absolute;
  inset: 0;
  border: 2px dashed fade(@YesWrite2, 35%);
  border-radius: 50%;
  animation: orbit_spin 4s linear infinite;

  &.orbit_b {
    inset: 12px;
    animation-direction: reverse;
    animation-duration: 2.8s;
    border-color: fade(@pearl, 30%);
  }
}

.podium {
  position: absolute;
  left: 50%;
  bottom: 4px;
  transform: translateX(-50%);
  display: flex;
  align-items: flex-end;
  gap: 4px;
  z-index: 1;

  .place {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    border-radius: 4px 4px 0 0;
    font-size: 11px;
    font-weight: 700;
    color: @DarkColorBG;
    animation: podium_bounce 1.1s ease-in-out infinite;
    .shadow_inset;
  }

  .place_1 {
    height: 28px;
    background: @maxYellow;
    animation-delay: 0s;
  }

  .place_2 {
    height: 22px;
    background: @colorBlur;
    animation-delay: 0.15s;
  }

  .place_3 {
    height: 18px;
    background: fade(@maxred, 85%);
    animation-delay: 0.3s;
  }
}

.loader_icon {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -58%);
  z-index: 2;
  min-width: 42px;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: transparent;
  box-shadow: none;
  animation: icon_pulse 1.4s ease-in-out infinite;
}

.loader_title {
  margin: 0 0 4px;
  font-size: 13px;
  font-weight: 700;
  color: @colorText;
}

.loader_phrase {
  margin: 0 0 12px;
  min-height: 18px;
  font-size: 12px;
  color: @pearl;
  animation: phrase_fade 1.4s ease-in-out infinite;
}

.skeleton_table {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.skeleton_row {
  display: grid;
  grid-template-columns: 28px 24px 1fr 40px;
  gap: 6px;
  align-items: center;
  animation: row_shimmer 1.2s ease-in-out infinite;

  span {
    display: block;
    height: 14px;
    border-radius: 4px;
    background: linear-gradient(
      90deg,
      fade(@colorBlur, 25%) 0%,
      fade(@colorText, 18%) 50%,
      fade(@colorBlur, 25%) 100%
    );
    background-size: 200% 100%;
    animation: shimmer_slide 1.1s linear infinite;
  }

  .sk_place { width: 100%; }
  .sk_diff { width: 100%; }
  .sk_user { width: 100%; }
  .sk_score { width: 100%; }
}

@keyframes orbit_spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes podium_bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}

@keyframes icon_pulse {
  0%, 100% { transform: translate(-50%, -58%) scale(1); }
  50% { transform: translate(-50%, -58%) scale(1.08); }
}

@keyframes phrase_fade {
  0%, 100% { opacity: 0.55; }
  50% { opacity: 1; }
}

@keyframes row_shimmer {
  0%, 100% { opacity: 0.45; }
  50% { opacity: 0.9; }
}

@keyframes shimmer_slide {
  from { background-position: 100% 0; }
  to { background-position: -100% 0; }
}
</style>
