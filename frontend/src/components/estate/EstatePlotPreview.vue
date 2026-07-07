<template>
  <div class="estate_plot_preview" :class="stage">
    <div class="scene">
      <div class="sky" />
      <div class="ground" />
      <div class="fence" v-if="showFence">
        <span v-for="n in 5" :key="'f' + n" class="fence_post" />
      </div>
      <div class="house" v-if="showHouse">
        <div class="roof" />
        <div class="walls" />
        <div class="door" />
      </div>
      <div class="scaffold" v-if="showScaffold" />
      <div class="foundation" v-if="showFoundation" />
    </div>
    <div class="stage_label">{{ stageLabel }}</div>
  </div>
</template>

<script>
const STAGE_LABELS = {
  claimed: 'Участок занят — стройка начинается',
  fence_building: 'Сбор материалов и стройка забора',
  fence_ready: 'Материалы забора собраны — можно строить',
  house_building: 'Забор готов — строится дом',
  complete: 'Усадьба полностью построена',
};

export default {
  name: 'EstatePlotPreview',
  props: {
    stage: {
      type: String,
      default: 'claimed',
    },
  },
  computed: {
    stageLabel() {
      return STAGE_LABELS[this.stage] || STAGE_LABELS.claimed;
    },
    showFoundation() {
      return ['claimed', 'fence_building', 'fence_ready'].includes(this.stage);
    },
    showFence() {
      return ['fence_ready', 'house_building', 'complete'].includes(this.stage);
    },
    showHouse() {
      return this.stage === 'house_building' || this.stage === 'complete';
    },
    showScaffold() {
      return this.stage === 'fence_building' || this.stage === 'house_building';
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.estate_plot_preview {
  text-align: center;
}

.scene {
  position: relative;
  height: 120px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid fade(@colorText, 18%);
  background: linear-gradient(180deg, #5a7a4a 0%, #3d5c32 55%, #6b4a2a 100%);
}

.sky {
  position: absolute;
  inset: 0 0 45%;
  background: linear-gradient(180deg, fade(#87b8e8, 55%) 0%, fade(#5a8a6a, 25%) 100%);
}

.ground {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 42%;
  background: linear-gradient(180deg, #4a7a38 0%, #355c28 100%);
}

.foundation {
  position: absolute;
  left: 50%;
  bottom: 28%;
  width: 72px;
  height: 10px;
  margin-left: -36px;
  background: fade(@colorBlur, 55%);
  border: 1px dashed fade(@colorText, 35%);
  border-radius: 2px;
}

.fence {
  position: absolute;
  left: 50%;
  bottom: 30%;
  width: 110px;
  height: 22px;
  margin-left: -55px;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.fence_post {
  width: 8px;
  height: 18px;
  background: linear-gradient(180deg, #c9a06a 0%, #8b5a2b 100%);
  border-radius: 1px;
  box-shadow: 0 1px 0 fade(#000, 25%);

  &::before {
    content: "";
    display: block;
    width: 14px;
    height: 3px;
    margin: 4px 0 0 -3px;
    background: #a67c52;
  }
}

.house {
  position: absolute;
  left: 50%;
  bottom: 30%;
  width: 56px;
  height: 44px;
  margin-left: -28px;
}

.roof {
  position: absolute;
  top: 0;
  left: -6px;
  right: -6px;
  height: 18px;
  background: linear-gradient(180deg, #c45c3a 0%, #8b3a22 100%);
  clip-path: polygon(50% 0%, 100% 100%, 0% 100%);
}

.walls {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 28px;
  background: linear-gradient(180deg, #e8dcc8 0%, #c4b49a 100%);
  border: 1px solid fade(#000, 15%);
}

.door {
  position: absolute;
  bottom: 0;
  left: 50%;
  width: 12px;
  height: 16px;
  margin-left: -6px;
  background: #6b4428;
  border-radius: 1px 1px 0 0;
}

.scaffold {
  position: absolute;
  left: 50%;
  bottom: 28%;
  width: 80px;
  height: 36px;
  margin-left: -40px;
  border: 2px dashed fade(@orange, 70%);
  border-radius: 2px;
  opacity: 0.85;
}

.fence_building .scaffold,
.house_building .scaffold {
  animation: pulse 1.6s ease-in-out infinite;
}

.house_building .house .walls {
  background: linear-gradient(180deg, #f0e6d0 0%, #d8c8a8 100%);
}

.house_building .roof {
  opacity: 0.75;
}

.complete .house .roof {
  filter: saturate(1.1);
}

.stage_label {
  margin-top: 8px;
  font-size: 12px;
  color: fade(@colorText, 88%);
  line-height: 1.35;
}

@keyframes pulse {
  0%, 100% { opacity: 0.55; }
  50% { opacity: 1; }
}
</style>
