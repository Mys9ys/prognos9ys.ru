<template>
  <div class="game_block" v-if="game">
    <div class="game_row">
      <div class="label">Уровень</div>
      <div class="value">{{ progress.level }} — {{ progress.title }}</div>
    </div>
    <div class="game_row">
      <div class="label">Опыт</div>
      <div class="value">{{ progress.xp }}</div>
    </div>
    <div class="progress_bar" v-if="progress.next_min_xp">
      <div class="progress_fill" :style="{ width: progress.progress_percent + '%' }"></div>
    </div>
    <div class="game_row small" v-if="progress.next_min_xp">
      <div class="label">До {{ progress.next_level }} ур.</div>
      <div class="value">{{ progress.xp_to_next }} XP</div>
    </div>
    <div class="wallet_row">
      <div class="coin prognobaks">{{ wallet.prognobaks }} 🪙</div>
      <div class="coin rublius">{{ wallet.rublius }} 💎</div>
    </div>
    <div class="wallet_row">
      <div class="coin chest">Сокровища: {{ treasure.closed_chests }} 🎁</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ProfileGameBlock',
  props: {
    game: {
      type: Object,
      default: null,
    },
  },
  computed: {
    wallet() {
      return this.game?.wallet || { prognobaks: 0, rublius: 0 };
    },
    progress() {
      return this.game?.progress || {
        level: 0,
        title: 'Новичок',
        xp: 0,
        progress_percent: 0,
        xp_to_next: 100,
        next_level: 1,
      };
    },
    treasure() {
      return this.game?.treasure || { closed_chests: 0 };
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.game_block {
  background: @DarkColorBG;
  color: @colorText;
  padding: 8px;
  border-radius: 5px;
  margin: 8px 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  text-align: left;
}

.game_row {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  .shadow_inset;
  padding: 4px 6px;
  font-size: 13px;

  .label {
    color: @colorBlur;
  }

  &.small {
    font-size: 11px;
  }
}

.progress_bar {
  height: 8px;
  background: @darkbg;
  border-radius: 4px;
  overflow: hidden;

  .progress_fill {
    height: 100%;
    background: @orange;
    transition: width 0.3s ease;
  }
}

.wallet_row {
  display: flex;
  flex-direction: row;
  gap: 8px;

  .coin {
    flex: 1;
    .shadow_inset;
    padding: 6px;
    text-align: center;
    font-size: 14px;
  }
}
</style>
