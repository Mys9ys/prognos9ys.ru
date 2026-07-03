<template>
  <div class="estate_city_map" v-if="city">
    <div class="city_head">
      <div class="city_title">{{ city.city_name }}</div>
      <div class="city_meta">{{ city.country_label }} · {{ statusLabel }}</div>
    </div>

    <div class="street_scroll">
      <div
        class="street_wireframe"
        :class="{ open: city.is_open, ghost: !city.on_map }"
      >
        <div class="city_grid">
          <!-- Верх (напротив управы): нечётные -->
          <div
            v-for="(plot, index) in plotsOddTop"
            :key="'ot' + plot.number"
            class="cell plot"
            :class="plotClass(plot)"
            :style="plotStyle(1, index + 1)"
            :title="plotTitle(plot)"
          >
            <span class="plot_num">{{ plot.number }}</span>
          </div>
          <div
            v-for="(plot, index) in plotsOddBottom"
            :key="'otr' + plot.number"
            class="cell plot"
            :class="plotClass(plot)"
            :style="plotStyle(1, index + rightPlotCol)"
            :title="plotTitle(plot)"
          >
            <span class="plot_num">{{ plot.number }}</span>
          </div>

          <!-- Низ (сторона управы): чётные -->
          <div
            v-for="(plot, index) in plotsEvenTop"
            :key="'eb' + plot.number"
            class="cell plot"
            :class="plotClass(plot)"
            :style="plotStyle(3, index + 1)"
            :title="plotTitle(plot)"
          >
            <span class="plot_num">{{ plot.number }}</span>
          </div>
          <div
            v-for="(plot, index) in plotsEvenBottom"
            :key="'ebr' + plot.number"
            class="cell plot"
            :class="plotClass(plot)"
            :style="plotStyle(3, index + rightPlotCol)"
            :title="plotTitle(plot)"
          >
            <span class="plot_num">{{ plot.number }}</span>
          </div>

          <!-- Вертикальные дороги -->
          <div class="cell road road_v" style="grid-column: 6; grid-row: 1 / 4" />
          <div class="cell road road_v" style="grid-column: 9; grid-row: 1 / 4" />

          <!-- Горизонтальная дорога (улица) -->
          <div class="cell road road_h" style="grid-column: 1 / -1; grid-row: 2" />

          <!-- Филиалы -->
          <div
            class="cell civic"
            :class="civicClass('civic_exchange_branch')"
            style="grid-column: 7; grid-row: 1"
          >
            <span>{{ exchangeLabel }}</span>
          </div>
          <div
            class="cell civic"
            :class="civicClass('civic_bank_branch')"
            style="grid-column: 8; grid-row: 1"
          >
            <span>{{ bankLabel }}</span>
          </div>

          <!-- Управа — на всю ширину центра -->
          <div
            class="cell civic civic_hall"
            :class="civicClass('civic_city_hall')"
            style="grid-column: 7 / 9; grid-row: 3"
          >
            <span>{{ hallLabel }}</span>
          </div>
        </div>

        <div class="street_legend">
          <span>верх · нечётные</span>
          <span>тракт</span>
          <span>низ у управы · чётные</span>
        </div>
      </div>
    </div>

    <div class="section" v-if="city.buildings?.length">
      <div class="section_title">Госздания</div>
      <div
        v-for="building in city.buildings"
        :key="building.recipe_code"
        class="building_card"
      >
        <div class="building_head">
          <span>{{ building.label }}</span>
          <span>{{ building.progress_pct }}%</span>
        </div>
        <div class="progress_bar">
          <div class="progress_fill" :style="{ width: building.progress_pct + '%' }" />
        </div>
        <div class="components_list" v-if="building.needed_items?.length">
          <div
            v-for="item in building.needed_items"
            :key="building.recipe_code + '-' + item.code"
            class="component_row"
          >
            <span>{{ item.label }} ×{{ item.qty }}</span>
            <span class="recipe_hint" v-if="item.recipe_label">
              ← {{ item.recipe_label }}
            </span>
            <span class="recipe_hint warn" v-else>рецепт не найден</span>
          </div>
        </div>
      </div>
    </div>

    <div class="section" v-if="city.estate_projects?.length">
      <div class="section_title">Усадьба на участке (черновик)</div>
      <div
        v-for="project in city.estate_projects"
        :key="project.code"
        class="building_card estate"
      >
        <div class="building_head">
          <span>{{ project.label }}</span>
          <span>~{{ project.nominal_total }} 🪙</span>
        </div>
        <div class="components_list">
          <div
            v-for="item in project.components"
            :key="project.code + '-' + item.code"
            class="component_row"
          >
            <span>{{ item.label }} ×{{ item.qty }}</span>
            <span class="recipe_hint" v-if="item.recipe_label">
              ← {{ item.recipe_label }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
const HOUSES_PER_ROW = 5;
/** Первая колонка правого крыла: 5 слева + 1 дорога + 2 центр + 1 дорога + 1 = 10 */
const RIGHT_PLOT_COL = 10;

export default {
  name: 'EstateCityStreetMap',
  props: {
    city: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      rightPlotCol: RIGHT_PLOT_COL,
    };
  },
  computed: {
    statusLabel() {
      const map = {
        planned: 'ещё не основан',
        founding: 'госстройка',
        open: 'открыт — участки доступны',
      };
      return map[this.city?.status] || this.city?.status || '';
    },
    plotsOdd() {
      return Array.isArray(this.city?.plots_odd) ? this.city.plots_odd : [];
    },
    plotsEven() {
      return Array.isArray(this.city?.plots_even) ? this.city.plots_even : [];
    },
    plotsOddTop() {
      return this.plotsOdd.slice(0, HOUSES_PER_ROW);
    },
    plotsOddBottom() {
      return this.plotsOdd.slice(HOUSES_PER_ROW);
    },
    plotsEvenTop() {
      return this.plotsEven.slice(0, HOUSES_PER_ROW);
    },
    plotsEvenBottom() {
      return this.plotsEven.slice(HOUSES_PER_ROW);
    },
    exchangeLabel() {
      return this.civicLabel('civic_exchange_branch', 'Биржа');
    },
    bankLabel() {
      return this.civicLabel('civic_bank_branch', 'Банк');
    },
    hallLabel() {
      return this.civicLabel('civic_city_hall', 'Управа');
    },
    buildingByCode() {
      const map = {};
      (this.city?.buildings || []).forEach((row) => {
        map[row.recipe_code] = row;
      });
      return map;
    },
  },
  methods: {
    plotStyle(row, col) {
      return {
        gridRow: row,
        gridColumn: col,
      };
    },
    civicLabel(code, fallback) {
      const civic = Array.isArray(this.city?.civic) ? this.city.civic : [];
      const slot = civic.find((row) => row.code === code);
      return slot?.label || fallback;
    },
    plotClass(plot) {
      return {
        claimed: plot.claimed,
        mine: plot.is_mine,
        free: this.city?.is_open && !plot.claimed,
      };
    },
    plotTitle(plot) {
      if (plot.is_mine) {
        return `Ваш участок №${plot.number}`;
      }
      if (plot.claimed) {
        return `Занят №${plot.number}`;
      }
      if (this.city?.is_open) {
        return `Свободен №${plot.number}`;
      }
      return `Участок №${plot.number} (город закрыт)`;
    },
    civicClass(code) {
      const row = this.buildingByCode[code];
      if (!row) {
        return 'planned';
      }
      if (row.status === 'complete') {
        return 'complete';
      }
      if ((row.progress_pct || 0) > 0) {
        return 'building';
      }
      return 'planned';
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

@cell: 40px;
@road: 10px;

.estate_city_map {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.city_head {
  text-align: left;
}

.city_title {
  color: @colorText;
  font-size: 15px;
}

.city_meta {
  color: @colorBlur;
  font-size: 12px;
}

.street_scroll {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 6px;

  &::-webkit-scrollbar {
    height: 6px;
  }

  &::-webkit-scrollbar-thumb {
    background: fade(@colorBlur, 45%);
    border-radius: 3px;
  }
}

.street_wireframe {
  width: fit-content;
  min-width: 100%;
  padding: 10px;
  border-radius: 6px;
  border: 1px dashed fade(@colorBlur, 45%);
  background: fade(@darkbg, 25%);

  &.ghost {
    opacity: 0.72;
  }
}

/* 5 + дорога + 2 + дорога + 5 = 14 колонок; 3 ряда (верх / дорога / низ) */
.city_grid {
  display: grid;
  grid-template-columns:
    repeat(5, @cell)
    @road
    repeat(2, @cell)
    @road
    repeat(5, @cell);
  grid-template-rows: @cell @road @cell;
  width: (5 * 40px + 10px + 2 * 40px + 10px + 5 * 40px);
  margin: 0 auto;
}

.cell {
  box-sizing: border-box;
  min-width: 0;
  min-height: 0;
}

.plot {
  width: @cell;
  height: @cell;
  border: 1px solid fade(@colorBlur, 50%);
  border-radius: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  color: @colorBlur;
  background: fade(@colorText2, 40%);
  z-index: 1;

  &.free {
    border-color: fade(#9ee09e, 60%);
    color: #9ee09e;
  }

  &.claimed {
    border-color: fade(@colorText, 35%);
    background: fade(@colorText2, 55%);
  }

  &.mine {
    border-color: @orange;
    color: @orange;
    background: fade(@orange, 8%);
  }
}

.road {
  z-index: 0;
  pointer-events: none;
}

.road_v {
  background: fade(@colorBlur, 12%);
  border-left: 1px dashed fade(@colorBlur, 30%);
  border-right: 1px dashed fade(@colorBlur, 30%);
}

.road_h {
  background: fade(@colorBlur, 12%);
  border-top: 1px dashed fade(@colorBlur, 30%);
  border-bottom: 1px dashed fade(@colorBlur, 30%);
}

.civic {
  width: @cell;
  height: @cell;
  border: 1px solid fade(@orange, 55%);
  border-radius: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2px;
  font-size: 8px;
  line-height: 1.1;
  text-align: center;
  color: fade(@orange, 90%);
  background: fade(@orange, 6%);
  z-index: 1;

  &.building {
    border-style: solid;
  }

  &.complete {
    border-color: fade(#9ee09e, 75%);
    color: #9ee09e;
    background: fade(#9ee09e, 8%);
  }
}

.civic_hall {
  width: auto;
  font-size: 9px;
  font-weight: 600;
}

.street_legend {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  margin-top: 8px;
  font-size: 9px;
  color: fade(@colorBlur, 75%);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.section {
  text-align: left;
}

.section_title {
  color: @colorText;
  font-size: 13px;
  margin-bottom: 6px;
}

.building_card {
  padding: 6px;
  border-radius: 5px;
  border: 1px dashed fade(@colorBlur, 35%);
  margin-bottom: 6px;
  .shadow_inset;

  &.estate {
    border-color: fade(@colorText, 20%);
  }
}

.building_head {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: @colorText;
  margin-bottom: 4px;
}

.progress_bar {
  height: 6px;
  background: @darkbg;
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 6px;

  .progress_fill {
    height: 100%;
    background: @orange;
  }
}

.components_list {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.component_row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 11px;
  color: @colorBlur;
}

.recipe_hint {
  color: fade(@orange, 85%);

  &.warn {
    color: fade(#ff8f8f, 90%);
  }
}
</style>
