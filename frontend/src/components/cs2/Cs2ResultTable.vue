<template>
  <div class="header_line"></div>
  <table class="table table-dark table-hover football_table_box">
    <thead>
      <tr>
        <th class="pr_table_col" v-for="(icon, index) in icons" :key="index">{{ icon }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-if="match.match_result" class="match_result_row">
        <td class="pr_table_col" v-for="(selector, index) in selectors" :key="index">
          {{ displayValue(match.match_result, selector) }}
        </td>
        <td class="pr_table_col"></td>
      </tr>

      <tr v-if="hasPrognosis" class="prognosis_row">
        <td class="pr_table_col" v-for="(selector, index) in selectors" :key="index">
          {{ displayValue(match.prognosis, selector) }}
        </td>
        <td class="pr_table_col"></td>
      </tr>

      <tr v-else-if="showNoPrognosisHint" class="no_prognosis_row">
        <td :colspan="columnCount" class="no_prognosis_cell">Прогноз не заполнен</td>
      </tr>

      <tr v-if="hasProgResult" class="prog_r">
        <td
          class="pr_table_col"
          :class="{ green: match.prog_result[selector] > 0 }"
          v-for="(selector, index) in selectors"
          :key="index"
        >
          <span v-if="selector !== 'opening2'">{{ displayValue(match.prog_result, selector) }}</span>
          <span v-else :class="{ green: match.prog_result.opening && match.prog_result.opening !== 0 }">
            {{ displayValue(match.prog_result, 'domination') }}
          </span>
        </td>
        <td class="pr_table_col" :class="{ green: match.prog_result.all > 0 }">
          {{ displayValue(match.prog_result, 'all') }}
        </td>
      </tr>
    </tbody>
  </table>

  <div v-if="mapRows.length" class="map_scores_block">
    <div class="map_scores_title">Счёт по картам</div>
    <div class="map_scores_row" v-for="row in mapRows" :key="row.slot">
      <span class="map_label">M{{ row.slot }}</span>
      <span class="map_prog" v-if="row.prog">{{ row.prog }}</span>
      <span class="map_fact" v-if="row.fact">{{ row.fact }}</span>
    </div>
  </div>

  <div class="desc_footer">
    <div class="desc_block">
      <div class="cell match_res">Результат</div>
      <div class="cell prognosis">Прогноз</div>
      <div class="cell"><span class="empty">Мимо/</span><span class="ball">Баллы</span></div>
    </div>
    <div class="bet_reward" v-if="showMoneyReward">
      <span class="bet_reward_text">Выигрыш +{{ moneyPayout }} 💵</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Cs2ResultTable',
  props: {
    match: {
      type: Object,
      default: () => ({}),
    },
  },
  data() {
    return {
      icons: {
        1: '0-0',
        2: '✓',
        3: 'Δ',
        4: 'Σ',
        5: '⚔',
        6: '🔫',
        7: '🎯₁',
        8: '🎯₂',
        9: 'all',
      },
      selectors: {
        1: 'maps_score',
        2: 'result',
        3: 'diff',
        4: 'sum',
        5: 'opening_pct',
        6: 'pistol_pct',
        7: 'clutches_home',
        8: 'clutches_guest',
      },
    };
  },
  computed: {
    columnCount() {
      return Object.keys(this.selectors).length + 1;
    },
    hasPrognosis() {
      return Boolean(this.match?.prognosis?.result);
    },
    hasProgResult() {
      return Boolean(this.match?.prog_result);
    },
    showNoPrognosisHint() {
      return this.match?.active === 'N' && !this.hasPrognosis;
    },
    showMoneyReward() {
      const reward = this.match?.bet_reward;
      return reward?.status === 'won' && Number(reward?.payout) > 0;
    },
    moneyPayout() {
      return Number(this.match?.bet_reward?.payout ?? 0);
    },
    mapRows() {
      const progMaps = this.match?.prognosis?.map_scores || [];
      const factMaps = this.match?.match_result?.map_scores || [];
      const count = Math.max(progMaps.length, factMaps.length);
      const rows = [];

      for (let i = 0; i < count; i++) {
        const p = progMaps[i] || {};
        const f = factMaps[i] || {};
        rows.push({
          slot: i + 1,
          prog: this.formatMap(p),
          fact: this.formatMap(f),
        });
      }

      return rows;
    },
  },
  methods: {
    displayValue(source, selector) {
      if (!source) return '';
      if (selector === 'maps_score') {
        return source.maps_score || source.goal_score || '';
      }
      if (selector === 'opening_pct' && source.opening_pct != null) {
        return `${source.opening_pct} - ${source.opening_pct_guest ?? (100 - source.opening_pct)}`;
      }
      if (selector === 'pistol_pct' && source.pistol_pct != null) {
        return `${source.pistol_pct} - ${source.pistol_pct_guest ?? (100 - source.pistol_pct)}`;
      }
      return source[selector] ?? '';
    },
    formatMap(map) {
      const home = map.rounds_home ?? map.home;
      const guest = map.rounds_guest ?? map.guest;
      if (home == null && guest == null) return '';
      return `${home ?? 0}-${guest ?? 0}`;
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.football_table_box {
  margin: 0;
  border-radius: 5px;

  th, td {
    padding: 2px;
    font-size: 11px;
  }

  .match_result_row td {
    color: @orange;
    font-weight: 600;
  }

  .prognosis_row td {
    color: @NoWrite;
  }

  .no_prognosis_row td {
    color: @colorBlur;
    font-style: italic;
    text-align: center;
    padding: 4px 2px;
  }

  .prog_r {
    td {
      color: @colorBlur;
    }

    .green {
      color: @YesWrite;
    }
  }
}

.header_line {
  background: @DarkColorBG;
  padding: 3px;
  border-radius: 5px 5px 0 0;
}

.map_scores_block {
  background: @DarkColorBG;
  color: @colorText;
  padding: 6px;
  font-size: 12px;

  .map_scores_title {
    font-weight: 700;
    margin-bottom: 4px;
  }

  .map_scores_row {
    display: flex;
    gap: 8px;
    margin-bottom: 2px;

    .map_label {
      min-width: 24px;
      color: @YesWrite;
    }

    .map_prog {
      color: @NoWrite;
    }

    .map_fact {
      color: @orange;
    }
  }
}

.desc_footer {
  position: relative;
  background: @DarkColorBG;
  border-radius: 0 0 5px 5px;
  min-height: 34px;
  padding-bottom: 4px;
}

.desc_block {
  padding: 4px;
  font-size: 14px;
  display: flex;
  flex-direction: row;
  gap: 4px;

  .cell {
    .shadow_inset;
  }

  .match_res {
    color: @orange;
  }

  .prognosis {
    color: @NoWrite;
  }

  .empty {
    color: @colorBlur;
  }

  .ball {
    color: @YesWrite;
  }
}

.bet_reward {
  position: absolute;
  right: 6px;
  bottom: 4px;
  padding: 3px 6px;

  .bet_reward_text {
    font-size: 11px;
    font-weight: 700;
    color: @YesWrite2;
  }
}
</style>
