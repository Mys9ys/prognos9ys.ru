<template>
  <div class="estate_world_map">
    <div class="map_summary" v-if="map">
      Основано <strong>{{ map.founded_count }}</strong> · открыто
      <strong>{{ map.open_count }}</strong> из {{ map.total_cities }}
    </div>

    <div class="map_legend">
      <span class="legend_item planned">план</span>
      <span class="legend_item founding">стройка</span>
      <span class="legend_item open">открыт</span>
      <span class="legend_item mine">ваш участок</span>
    </div>

    <div class="groups_grid" v-if="map?.groups?.length">
      <div
        v-for="group in map.groups"
        :key="group.id"
        class="group_column"
      >
        <div class="group_label">Гр. {{ group.id }}</div>
        <button
          v-for="city in group.cities"
          :key="city.slug"
          type="button"
          class="city_cell"
          :class="cityCellClass(city)"
          :title="cityTitle(city)"
          @click="$emit('select-city', city.slug)"
        >
          <img
            v-if="pennantSrc(city.slug)"
            :src="pennantSrc(city.slug)"
            class="city_pennant"
            alt=""
          >
          <span class="city_name">{{ shortCityName(city.city_name) }}</span>
          <span class="city_status_dot" />
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { getWc26PennantIconSrc } from '@/config/wc26PennantIcons';

export default {
  name: 'EstateWorldMap',
  props: {
    map: {
      type: Object,
      default: null,
    },
    selectedSlug: {
      type: String,
      default: '',
    },
  },
  emits: ['select-city'],
  methods: {
    pennantSrc(slug) {
      return getWc26PennantIconSrc(`pennant_wc26_${slug}`);
    },
    shortCityName(name) {
      const text = String(name || '');
      return text.length > 11 ? `${text.slice(0, 10)}…` : text;
    },
    cityCellClass(city) {
      const classes = [city.status || 'planned'];
      if (!city.on_map) {
        classes.push('ghost');
      }
      if (city.is_open) {
        classes.push('open');
      }
      if (city.user_plot_number) {
        classes.push('mine');
      }
      if (this.selectedSlug === city.slug) {
        classes.push('selected');
      }
      return classes;
    },
    cityTitle(city) {
      const parts = [
        city.city_name,
        city.country_label,
        city.status === 'open' ? 'открыт' : (city.status === 'founding' ? 'стройка' : 'план'),
      ];
      if (city.is_open) {
        parts.push(`участки ${city.plots_claimed}/${city.plots_total}`);
      }
      if (city.user_plot_number) {
        parts.push(`ваш №${city.user_plot_number}`);
      }
      return parts.filter(Boolean).join(' · ');
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.estate_world_map {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.map_summary,
.map_legend {
  color: @colorBlur;
  font-size: 12px;
  text-align: left;
}

.map_legend {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.legend_item {
  padding: 2px 6px;
  border-radius: 4px;
  border: 1px dashed fade(@colorBlur, 55%);

  &.founding {
    border-color: fade(@orange, 70%);
    color: @orange;
  }

  &.open {
    border-style: solid;
    border-color: fade(@colorText, 45%);
  }

  &.mine {
    border-color: fade(#7fd67f, 80%);
    color: #9ee09e;
  }
}

.groups_grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 6px;
  overflow-x: auto;
}

@media (min-width: 520px) {
  .groups_grid {
    grid-template-columns: repeat(6, minmax(0, 1fr));
  }
}

@media (min-width: 760px) {
  .groups_grid {
    grid-template-columns: repeat(12, minmax(0, 1fr));
  }
}

.group_column {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.group_label {
  font-size: 10px;
  color: @colorBlur;
  text-align: center;
}

.city_cell {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  min-height: 58px;
  padding: 4px 2px;
  border-radius: 5px;
  border: 1px dashed fade(@colorBlur, 45%);
  background: fade(@darkbg, 35%);
  color: @colorText;
  cursor: pointer;
  .shadow_inset;

  &.ghost {
    opacity: 0.55;
  }

  &.founding {
    border-color: fade(@orange, 75%);
    border-style: dashed;
  }

  &.open {
    border-style: solid;
    border-color: fade(@colorText, 35%);
  }

  &.mine {
    box-shadow: inset 0 0 0 1px fade(#7fd67f, 55%);
  }

  &.selected {
    border-color: @orange;
    border-style: solid;
  }

  &:active {
    opacity: 0.9;
  }
}

.city_pennant {
  width: 22px;
  height: 22px;
  object-fit: contain;
}

.city_name {
  font-size: 9px;
  line-height: 1.15;
  text-align: center;
  max-width: 100%;
}

.city_status_dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: fade(@colorBlur, 70%);
}

.city_cell.founding .city_status_dot {
  background: @orange;
}

.city_cell.open .city_status_dot {
  background: #9ee09e;
}

.city_cell.mine .city_status_dot {
  background: #7fd67f;
}
</style>
