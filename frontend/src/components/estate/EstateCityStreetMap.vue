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
            @click="onPlotClick(plot)"
          >
            <span class="plot_num">{{ plot.number }}</span>
            <span v-if="plot.claimed" class="plot_estate_icon" :class="'stage_' + (plot.estate_stage || 'claimed')" />
          </div>
          <div
            v-for="(plot, index) in plotsOddBottom"
            :key="'otr' + plot.number"
            class="cell plot"
            :class="plotClass(plot)"
            :style="plotStyle(1, index + rightPlotCol)"
            :title="plotTitle(plot)"
            @click="onPlotClick(plot)"
          >
            <span class="plot_num">{{ plot.number }}</span>
            <span v-if="plot.claimed" class="plot_estate_icon" :class="'stage_' + (plot.estate_stage || 'claimed')" />
          </div>

          <!-- Низ (сторона управы): чётные -->
          <div
            v-for="(plot, index) in plotsEvenTop"
            :key="'eb' + plot.number"
            class="cell plot"
            :class="plotClass(plot)"
            :style="plotStyle(3, index + 1)"
            :title="plotTitle(plot)"
            @click="onPlotClick(plot)"
          >
            <span class="plot_num">{{ plot.number }}</span>
            <span v-if="plot.claimed" class="plot_estate_icon" :class="'stage_' + (plot.estate_stage || 'claimed')" />
          </div>
          <div
            v-for="(plot, index) in plotsEvenBottom"
            :key="'ebr' + plot.number"
            class="cell plot"
            :class="plotClass(plot)"
            :style="plotStyle(3, index + rightPlotCol)"
            :title="plotTitle(plot)"
            @click="onPlotClick(plot)"
          >
            <span class="plot_num">{{ plot.number }}</span>
            <span v-if="plot.claimed" class="plot_estate_icon" :class="'stage_' + (plot.estate_stage || 'claimed')" />
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
            :class="[civicClass('civic_bank_branch'), { clickable: isBankCellClickable }]"
            style="grid-column: 8; grid-row: 1"
            @click="onBankClick"
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
        <div
          class="building_head"
          :class="{ clickable: building.needed_items?.length || isBankBuildingClickable(building) }"
          @click="onBuildingHeadClick(building)"
        >
          <span>{{ building.label }}</span>
          <span class="building_head_meta">
            <span
              v-if="building.needed_items?.length"
              class="expand_hint"
            >
              {{ isBuildingExpanded(building.recipe_code) ? '▼' : '▶' }}
              {{ building.needed_items.length }}
            </span>
            <span>{{ building.progress_pct }}%</span>
          </span>
        </div>
        <div class="progress_bar">
          <div class="progress_fill" :style="{ width: building.progress_pct + '%' }" />
        </div>
        <div
          class="components_list"
          v-if="building.needed_items?.length && isBuildingExpanded(building.recipe_code)"
        >
          <div
            v-for="item in building.needed_items"
            :key="building.recipe_code + '-' + item.code"
            class="component_row"
          >
            <span>{{ item.label }} ×{{ item.qty }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="section" v-if="city.estate_projects?.length">
      <div class="section_title">Усадьба на участке</div>
      <p v-if="!city.my_plot_number" class="section_hint">
        Займите свободный участок (нужна лицензия) — потом откроется стройка.
      </p>
      <div
        v-for="project in activeEstateProjects"
        :key="project.code"
        class="building_card estate"
      >
        <div class="building_head">
          <span>{{ project.label || project.code }}</span>
          <div class="building_head_right">
            <span>{{ projectStatusLabel(project) }}</span>
            <div class="project_actions" v-if="canProjectActions(project)">
              <button
                v-if="canBuildProject(project)"
                type="button"
                class="build_btn"
                @click="onBuildProject(project)"
              >
                Построить
              </button>
              <button
                type="button"
                class="donate_btn"
                :disabled="!canDonateProjectAll(project)"
                @click="onDonateProjectAll(project)"
              >
                Сдать все
              </button>
              <button
                v-if="canOrderProjectAll(project)"
                type="button"
                class="order_btn"
                @click="onOrderProjectAll(project)"
              >
                Заказать все
              </button>
              <button
                v-if="canCancelProjectAll(project)"
                type="button"
                class="cancel_order_btn"
                @click="onCancelProjectAll(project)"
              >
                Снять все
              </button>
            </div>
          </div>
        </div>
        <div class="progress_bar" v-if="project.needed_items?.length">
          <div class="progress_fill" :style="{ width: (project.progress_pct || 0) + '%' }" />
        </div>
        <div class="components_list">
          <div
            v-for="item in (project.needed_items || project.components || [])"
            :key="project.code + '-' + item.code"
            class="component_row"
          >
            <span>{{ item.label }} ×{{ item.qty }}</span>
            <span v-if="project.remaining && project.remaining[item.code]" class="remain_badge">
              осталось {{ project.remaining[item.code] }}
            </span>
            <span v-if="canDonate(project, item)" class="inventory_badge" :class="{ empty: !hasInventory(project, item) }">
              в инвентаре {{ userHave(project, item) }}
            </span>
            <span
              v-if="orderedQty(project, item) > 0"
              class="ordered_badge"
              :title="orderStatusTitle(project, item)"
            >
              {{ orderStatusLabel(project, item) }}
            </span>
            <span v-if="stashHave(project, item) > 0 && project.status === 'building'" class="stash_badge">
              на стройке {{ stashHave(project, item) }}
            </span>
            <div class="row_actions">
              <button
                v-if="canDonate(project, item)"
                type="button"
                class="donate_btn"
                :disabled="!hasInventory(project, item)"
                :title="!hasInventory(project, item) ? 'Нет в инвентаре' : ''"
                @click="onDonate(project, item, 1)"
              >
                Сдать
              </button>
              <button
                v-if="canOrder(project, item)"
                type="button"
                class="order_btn"
                @click="onOrder(project, item, 1)"
              >
                Заказать
              </button>
              <button
                v-else-if="canCancelOrder(project, item)"
                type="button"
                class="cancel_order_btn"
                @click="onCancelOrder(project, item)"
              >
                Снять
              </button>
            </div>
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
  emits: [
    'claim-plot',
    'plot-info',
    'plot-view',
    'donate-component',
    'donate-project-all',
    'build-project',
    'order-component',
    'order-project-all',
    'cancel-component-orders',
    'cancel-project-orders',
    'bank-branches',
  ],
  props: {
    city: {
      type: Object,
      default: null,
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      rightPlotCol: RIGHT_PLOT_COL,
      expandedBuildings: {},
    };
  },
  watch: {
    'city.slug'() {
      this.expandedBuildings = {};
    },
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
    isBankCellClickable() {
      return this.isBankBuildingComplete();
    },
    buildingByCode() {
      const map = {};
      (this.city?.buildings || []).forEach((row) => {
        map[row.recipe_code] = row;
      });
      return map;
    },
    activeEstateProjects() {
      if (Array.isArray(this.city?.my_estate_projects) && this.city.my_estate_projects.length) {
        return this.city.my_estate_projects.map((row) => ({
          code: row.recipe_code,
          ...row,
        }));
      }

      return Array.isArray(this.city?.estate_projects) ? this.city.estate_projects : [];
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
      const claimed = Boolean(plot.claimed);
      const stage = String(plot.estate_stage || '');
      return {
        mine: plot.is_mine,
        occupied: claimed && !plot.is_mine,
        free: this.city?.is_open && !claimed,
        clickable: this.city?.is_open && !this.loading,
        estate_claimed: claimed && stage === 'claimed',
        estate_fence: stage === 'fence_building' || stage === 'fence_ready',
        estate_house: stage === 'house_building',
        estate_done: stage === 'complete',
      };
    },
    plotTitle(plot) {
      if (plot.is_mine) {
        return `Ваш участок №${plot.number} (клик: вид усадьбы)`;
      }
      if (plot.claimed) {
        return plot.owner_name
          ? `Участок №${plot.number}: ${plot.owner_name} (клик: вид усадьбы)`
          : `Занят №${plot.number} (клик: вид усадьбы)`;
      }
      if (this.city?.is_open) {
        return `Свободен №${plot.number} (клик: занять)`;
      }
      return `Участок №${plot.number} (город закрыт)`;
    },
    isBuildingExpanded(recipeCode) {
      return Boolean(this.expandedBuildings[String(recipeCode || '')]);
    },
    isBankBuildingComplete(building = null) {
      const row = building || this.buildingByCode.civic_bank_branch;
      if (!row) {
        return false;
      }
      return row.status === 'complete' || Number(row.progress_pct || 0) >= 100;
    },
    isBankBuildingClickable(building) {
      return String(building?.recipe_code || '') === 'civic_bank_branch'
        && this.isBankBuildingComplete(building);
    },
    onBuildingHeadClick(building) {
      if (this.isBankBuildingClickable(building)) {
        this.$emit('bank-branches');
        return;
      }
      this.toggleBuilding(building.recipe_code);
    },
    onBankClick() {
      if (!this.isBankCellClickable || this.loading) {
        return;
      }
      this.$emit('bank-branches');
    },
    toggleBuilding(recipeCode) {
      const key = String(recipeCode || '');
      if (!key || !this.city?.buildings?.length) {
        return;
      }

      const building = this.city.buildings.find((row) => String(row.recipe_code || '') === key);
      if (!building?.needed_items?.length) {
        return;
      }

      this.expandedBuildings = {
        ...this.expandedBuildings,
        [key]: !this.expandedBuildings[key],
      };
    },
    onPlotClick(plot) {
      if (!this.city?.is_open || this.loading || !plot) {
        return;
      }

      if (plot.claimed) {
        this.$emit('plot-view', {
          plotNumber: Number(plot.number),
          isMine: Boolean(plot.is_mine),
          isHome: Boolean(plot.is_home),
          ownerName: plot.owner_name || '',
          stage: plot.estate_stage || 'claimed',
        });
        return;
      }

      if (this.city?.my_plot_number && Number(this.city.my_plot_number) !== Number(plot.number)) {
        this.$emit('plot-info', {
          type: 'already_have',
          message: `У вас уже есть участок №${this.city.my_plot_number}. В одном городе доступна только одна усадьба.`,
        });
        return;
      }

      this.$emit('claim-plot', { plotNumber: Number(plot.number) });
    },
    projectStatusLabel(project) {
      const status = String(project?.status || '');
      if (status === 'complete') {
        return 'Построено';
      }
      if (status === 'ready' || project?.can_build) {
        return 'Собрано';
      }
      return `${project?.progress_pct || 0}%`;
    },
    canBuildProject(project) {
      return Boolean(project?.can_build) || String(project?.status || '') === 'ready';
    },
    onBuildProject(project) {
      if (!this.canBuildProject(project)) {
        return;
      }
      this.$emit('build-project', {
        plotNumber: Number(this.city.my_plot_number),
        projectCode: String(project.code || project.recipe_code || ''),
        projectLabel: project.label || project.code,
      });
    },
    canDonate(project, item) {
      if (this.loading || !this.city?.my_plot_number) {
        return false;
      }
      const status = String(project?.status || '');
      if (status === 'complete' || status === 'ready') {
        return false;
      }
      const left = Number(project?.remaining?.[item.code] || 0);
      return left > 0;
    },
    userHave(project, item) {
      if (item && Number.isFinite(Number(item.user_have))) {
        return Number(item.user_have);
      }
      return Number(project?.inventory?.[item.code] || 0);
    },
    hasInventory(project, item) {
      return this.userHave(project, item) > 0;
    },
    stashHave(project, item) {
      if (item && Number.isFinite(Number(item.stash_have))) {
        return Number(item.stash_have);
      }
      return Number(project?.stash?.[item.code] || 0);
    },
    orderedQty(project, item) {
      if (item && Number.isFinite(Number(item.ordered_qty))) {
        return Number(item.ordered_qty);
      }
      return Number(project?.ordered?.[item.code] || 0);
    },
    openOrdersForItem(project, item) {
      const code = String(item?.code || '');
      if (!code) {
        return [];
      }
      return (Array.isArray(project?.open_orders) ? project.open_orders : [])
        .filter((row) => String(row?.output_code || '') === code);
    },
    orderGap(project, item) {
      const left = Number(project?.remaining?.[item.code] || 0);
      return Math.max(0, left - this.orderedQty(project, item));
    },
    orderStatusLabel(project, item) {
      const ordered = this.orderedQty(project, item);
      const gap = this.orderGap(project, item);
      if (gap <= 0) {
        return `заказано ${ordered}`;
      }
      return `заказано ${ordered} · ещё ${gap}`;
    },
    orderStatusTitle(project, item) {
      const ordered = this.orderedQty(project, item);
      const gap = this.orderGap(project, item);
      if (gap <= 0) {
        return `На бирже уже заказано ${ordered} шт. — ждём исполнения`;
      }
      return `На бирже заказано ${ordered} шт., ещё можно заказать ${gap}`;
    },
    canDonateProjectAll(project) {
      const items = project.needed_items || project.components || [];
      return items.some((item) => this.canDonate(project, item) && this.hasInventory(project, item));
    },
    onDonate(project, item, qty = 1) {
      if (!this.canDonate(project, item)) {
        return;
      }
      this.$emit('donate-component', {
        plotNumber: Number(this.city.my_plot_number),
        projectCode: String(project.code || project.recipe_code || ''),
        componentCode: String(item.code || ''),
        qty: Math.max(1, Number(qty || 1)),
      });
    },
    canOrder(project, item) {
      return this.canDonate(project, item) && this.orderGap(project, item) > 0;
    },
    canCancelOrder(project, item) {
      return this.canProjectActions(project) && this.openOrdersForItem(project, item).length > 0;
    },
    onOrder(project, item, qty = 1) {
      if (!this.canOrder(project, item)) {
        return;
      }
      this.$emit('order-component', {
        plotNumber: Number(this.city.my_plot_number),
        projectCode: String(project.code || project.recipe_code || ''),
        componentCode: String(item.code || ''),
        qty: Math.max(1, Number(qty || 1)),
      });
    },
    canProjectActions(project) {
      return !this.loading && this.city?.my_plot_number && (project?.status || '') !== 'complete';
    },
    canOrderProjectAll(project) {
      const items = project.needed_items || project.components || [];
      return items.some((item) => this.canOrder(project, item));
    },
    canCancelProjectAll(project) {
      return this.canProjectActions(project) && Array.isArray(project?.open_orders) && project.open_orders.length > 0;
    },
    onDonateProjectAll(project) {
      if (!this.canProjectActions(project)) {
        return;
      }
      this.$emit('donate-project-all', {
        plotNumber: Number(this.city.my_plot_number),
        projectCode: String(project.code || project.recipe_code || ''),
      });
    },
    onOrderProjectAll(project) {
      if (!this.canProjectActions(project)) {
        return;
      }
      this.$emit('order-project-all', {
        plotNumber: Number(this.city.my_plot_number),
        projectCode: String(project.code || project.recipe_code || ''),
      });
    },
    onCancelOrder(project, item) {
      if (!this.canCancelOrder(project, item)) {
        return;
      }
      const orders = this.openOrdersForItem(project, item);
      this.$emit('cancel-component-orders', {
        plotNumber: Number(this.city.my_plot_number),
        projectCode: String(project.code || project.recipe_code || ''),
        componentCode: String(item.code || ''),
        componentLabel: item.label || item.code,
        orderIds: orders.map((row) => Number(row.id)).filter((id) => id > 0),
      });
    },
    onCancelProjectAll(project) {
      if (!this.canCancelProjectAll(project)) {
        return;
      }
      const orders = Array.isArray(project.open_orders) ? project.open_orders : [];
      this.$emit('cancel-project-orders', {
        plotNumber: Number(this.city.my_plot_number),
        projectCode: String(project.code || project.recipe_code || ''),
        projectLabel: project.label || project.code,
        orderIds: orders.map((row) => Number(row.id)).filter((id) => id > 0),
      });
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
      if ((row.status || '') === 'pending_fee') {
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
  border: 1px solid fade(@colorBlur, 45%);
  border-radius: 2px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1px;
  font-size: 10px;
  color: fade(@colorBlur, 85%);
  background: fade(@colorBlur, 16%);
  z-index: 1;
  position: relative;

  &.free {
    border-color: #6dbf6d;
    background: fade(#6dbf6d, 24%);
    color: #b8e8b8;
  }

  &.occupied,
  &.estate_claimed,
  &.estate_fence {
    border-color: #a67c52;
    background: fade(#6b4428, 48%);
    color: #d4b896;
  }

  &.estate_house {
    border-color: fade(@orange, 70%);
    background: fade(@orange, 18%);
    color: #f0d0a0;
  }

  &.estate_done {
    border-color: fade(@YesWrite, 70%);
    background: fade(@YesWrite, 22%);
    color: #d0f0c0;
  }

  &.mine {
    border-color: @orange;
    background: fade(@orange, 22%);
    color: @orange;
  }

  &.clickable {
    cursor: pointer;
  }
}

.plot_num {
  line-height: 1;
}

.plot_estate_icon {
  width: 10px;
  height: 8px;
  border-radius: 1px;
  opacity: 0.9;

  &.stage_claimed,
  &.stage_fence_building {
    background: fade(@orange, 55%);
    border: 1px dashed fade(@colorText, 40%);
  }

  &.stage_fence_ready {
    background: linear-gradient(180deg, #c9a06a 0%, #8b5a2b 100%);
  }

  &.stage_house_building {
    background: linear-gradient(180deg, #e8dcc8 40%, #c45c3a 40%);
  }

  &.stage_complete {
    background: linear-gradient(180deg, #c45c3a 0%, #e8dcc8 45%, #6dbf6d 45%);
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

  &.clickable {
    cursor: pointer;
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
  align-items: flex-start;
  font-size: 12px;
  color: @colorText;
  margin-bottom: 4px;

  &.clickable {
    cursor: pointer;
  }
}

.building_head_meta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.expand_hint {
  font-size: 10px;
  color: @colorBlur;
}

.building_head_right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
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
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: @colorBlur;
  border: 1px solid fade(@colorBlur, 20%);
  border-radius: 4px;
  padding: 4px 6px;
}

.section_hint {
  color: @colorBlur;
  font-size: 11px;
  margin-bottom: 6px;
}

.remain_badge {
  color: @orange;
}

.build_btn {
  border: 1px solid fade(@orange, 75%);
  background: fade(@orange, 28%);
  color: @colorText;
  border-radius: 4px;
  font-size: 10px;
  padding: 1px 8px;
  cursor: pointer;
  font-weight: 700;
}

.donate_btn {
  border: 1px solid fade(@orange, 60%);
  background: fade(@orange, 16%);
  color: @colorText;
  border-radius: 4px;
  font-size: 10px;
  padding: 1px 6px;
  cursor: pointer;

  &:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }
}

.order_btn {
  border: 1px solid fade(@YesWrite, 60%);
  background: fade(@YesWrite, 15%);
  color: @colorText;
  border-radius: 4px;
  font-size: 10px;
  padding: 1px 6px;
  cursor: pointer;

  &:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }
}

.cancel_order_btn {
  border: 1px solid fade(#f08080, 65%);
  background: fade(#f08080, 14%);
  color: @colorText;
  border-radius: 4px;
  font-size: 10px;
  padding: 1px 6px;
  cursor: pointer;
}

.inventory_badge {
  font-size: 10px;
  color: fade(@colorBlur, 90%);

  &.empty {
    color: fade(#f08080, 90%);
  }
}

.stash_badge {
  font-size: 10px;
  color: #d4b896;
}

.ordered_badge {
  font-size: 10px;
  color: #8fd0a8;
}

.row_actions,
.project_actions {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}
</style>
