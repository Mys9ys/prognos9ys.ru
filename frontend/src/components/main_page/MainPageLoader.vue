<template>
  <div class="main_page_loader">
    <div class="loader_hero">
      <div class="pulse_ring ring_outer"></div>
      <div class="pulse_ring ring_inner"></div>
      <div class="home_icon">
        <img src="@/assets/icon/header/home.svg" alt="">
      </div>
    </div>

    <p class="loader_title">{{ title }}</p>
    <p class="loader_phrase">{{ phrase }}</p>

    <div class="day_tabs_sk">
      <span
        v-for="(label, index) in dayLabels"
        :key="label"
        class="day_sk"
        :class="{ active: index === 1 }"
      >{{ label }}</span>
    </div>

    <div class="cards_sk">
      <div
        class="card_sk"
        v-for="n in 3"
        :key="n"
        :style="{ animationDelay: `${n * 0.14}s` }"
      >
        <span class="sk_logo"></span>
        <span class="sk_lines">
          <span class="sk_line sk_line_wide"></span>
          <span class="sk_line"></span>
        </span>
        <span class="sk_match"></span>
      </div>
    </div>
  </div>
</template>

<script>
const PHRASES = [
  'Смотрим календарь…',
  'Ищем матчи на сегодня…',
  'Сверяем ваши прогнозы…',
  'Почти готово…',
];

export default {
  name: 'MainPageLoader',
  props: {
    title: {
      type: String,
      default: 'Загрузка главной…',
    },
  },
  data() {
    return {
      phraseIndex: 0,
      phraseTimer: null,
      dayLabels: ['Вчера', 'Сегодня', 'Завтра'],
    };
  },
  computed: {
    phrase() {
      return PHRASES[this.phraseIndex % PHRASES.length];
    },
  },
  mounted() {
    this.phraseTimer = setInterval(() => {
      this.phraseIndex = (this.phraseIndex + 1) % PHRASES.length;
    }, 1500);
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

.main_page_loader {
  margin-top: 8px;
  padding: 16px 10px 14px;
  border-radius: 5px;
  background: @billiard;
  text-align: center;
  overflow: hidden;
}

.loader_hero {
  position: relative;
  width: 96px;
  height: 96px;
  margin: 0 auto 10px;
}

.pulse_ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 2px solid fade(@YesWrite2, 45%);
  animation: ring_pulse 2.2s ease-out infinite;

  &.ring_inner {
    inset: 14px;
    border-color: fade(@colorText, 35%);
    animation-delay: 0.55s;
  }
}

.home_icon {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: fade(@darkbg, 55%);
  box-shadow: 0 0 0 3px fade(@YesWrite, 20%), 0 0 16px fade(@YesWrite2, 30%);

  img {
    width: 22px;
    height: 22px;
    filter: brightness(0) invert(1);
    animation: home_bob 1.6s ease-in-out infinite;
  }
}

.loader_title {
  margin: 0 0 4px;
  font-size: 13px;
  font-weight: 700;
  color: @colorText;
}

.loader_phrase {
  margin: 0 0 14px;
  min-height: 18px;
  font-size: 12px;
  color: @pearl;
  animation: phrase_fade 1.5s ease-in-out infinite;
}

.day_tabs_sk {
  display: flex;
  justify-content: center;
  gap: 6px;
  margin-bottom: 12px;
}

.day_sk {
  min-width: 72px;
  padding: 4px 6px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  color: fade(@colorText, 55%);
  background: fade(@colorBlur, 30%);
  .shadow_inset;

  &.active {
    color: @colorText;
    background: fade(@colorText2, 85%);
    animation: tab_glow 1.4s ease-in-out infinite;
  }
}

.cards_sk {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.card_sk {
  display: grid;
  grid-template-columns: 40px 1fr;
  grid-template-rows: auto auto;
  gap: 4px 8px;
  padding: 6px;
  border-radius: 5px;
  background: fade(@DarkColorBG, 35%);
  animation: card_fade 1.3s ease-in-out infinite;

  .sk_logo {
    grid-row: span 2;
    width: 40px;
    height: 40px;
    border-radius: 4px;
    background: linear-gradient(
      90deg,
      fade(@colorBlur, 25%) 0%,
      fade(@colorText, 16%) 50%,
      fade(@colorBlur, 25%) 100%
    );
    background-size: 200% 100%;
    animation: shimmer_slide 1.1s linear infinite;
  }

  .sk_lines {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-self: center;
  }

  .sk_line {
    display: block;
    height: 10px;
    width: 70%;
    border-radius: 3px;
    background: linear-gradient(
      90deg,
      fade(@colorBlur, 25%) 0%,
      fade(@colorText, 16%) 50%,
      fade(@colorBlur, 25%) 100%
    );
    background-size: 200% 100%;
    animation: shimmer_slide 1.1s linear infinite;

    &.sk_line_wide {
      width: 92%;
      height: 12px;
    }
  }

  .sk_match {
    grid-column: 2;
    height: 28px;
    border-radius: 4px;
    background: linear-gradient(
      90deg,
      fade(@colorBlur, 20%) 0%,
      fade(@colorText, 12%) 50%,
      fade(@colorBlur, 20%) 100%
    );
    background-size: 200% 100%;
    animation: shimmer_slide 1.1s linear infinite;
  }
}

@keyframes ring_pulse {
  0% { transform: scale(0.82); opacity: 0.9; }
  70% { transform: scale(1.08); opacity: 0.15; }
  100% { transform: scale(1.08); opacity: 0; }
}

@keyframes home_bob {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-3px); }
}

@keyframes phrase_fade {
  0%, 100% { opacity: 0.55; }
  50% { opacity: 1; }
}

@keyframes tab_glow {
  0%, 100% { box-shadow: inset 0 0 0 0 transparent; }
  50% { box-shadow: inset 0 0 8px fade(@YesWrite2, 35%); }
}

@keyframes card_fade {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 0.95; }
}

@keyframes shimmer_slide {
  from { background-position: 100% 0; }
  to { background-position: -100% 0; }
}
</style>
