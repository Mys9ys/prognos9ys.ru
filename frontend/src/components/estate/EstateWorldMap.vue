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

    <div class="pangaea_frame" v-if="map?.regions?.length">
      <img
        class="pangaea_bg"
        :src="pangaeaImage"
        alt="Карта пангеи"
        draggable="false"
      >

      <button
        v-for="region in map.regions"
        :key="'pin-' + region.id"
        type="button"
        class="region_pin"
        :class="{
          active: selectedRegionId === region.id,
          has_open: region.open_count > 0,
          has_mine: regionHasMine(region),
        }"
        :style="pinStyle(region)"
        :title="regionTitle(region)"
        @click="onRegionClick(region.id)"
      >
        <span class="pin_dot" />
        <span class="pin_label">{{ shortLabel(region.label) }}</span>
        <span class="pin_stat">{{ region.open_count }}/{{ region.city_count }}</span>
      </button>
    </div>

    <div class="region_chips" v-if="map?.regions?.length">
      <button
        v-for="region in map.regions"
        :key="'chip-' + region.id"
        type="button"
        class="region_chip"
        :class="{ active: selectedRegionId === region.id, has_open: region.open_count > 0 }"
        @click="onRegionClick(region.id)"
      >
        {{ region.label }}
        <span class="chip_stat">{{ region.open_count }}/{{ region.city_count }}</span>
      </button>
    </div>

    <div class="my_plots_block" v-if="myPlots.length">
      <div class="my_plots_title">Мои участки</div>
      <button
        v-for="plot in myPlots"
        :key="plot.slug"
        type="button"
        class="my_plot_link"
        @click="onMyPlotClick(plot)"
      >
        <span class="my_plot_main">{{ plot.cityName }} · участок №{{ plot.plotNumber }}</span>
        <span class="my_plot_meta">{{ plot.regionLabel }}</span>
      </button>
    </div>

    <p class="map_hint">Метка на карте или кнопка региона ниже — откроет города зоны.</p>
  </div>
</template>

<script>
import pangaeaImage from '@/assets/estate/pangaea_world.png';

export default {
  name: 'EstateWorldMap',
  props: {
    map: {
      type: Object,
      default: null,
    },
    selectedRegionId: {
      type: String,
      default: '',
    },
  },
  emits: ['select-region', 'open-city'],
  data() {
    return {
      pangaeaImage,
    };
  },
  computed: {
    myPlots() {
      if (!Array.isArray(this.map?.regions)) {
        return [];
      }

      const rows = [];
      this.map.regions.forEach((region) => {
        (region.cities || []).forEach((city) => {
          const plotNumber = Number(city.user_plot_number || 0);
          if (plotNumber <= 0) {
            return;
          }

          rows.push({
            slug: city.slug,
            regionId: region.id,
            cityName: city.city_name || city.slug,
            regionLabel: region.label || '',
            plotNumber,
          });
        });
      });

      return rows.sort((a, b) => a.cityName.localeCompare(b.cityName, 'ru'));
    },
  },
  methods: {
    pinStyle(region) {
      const x = Number(region?.world?.x) || 50;
      const y = Number(region?.world?.y) || 50;

      return {
        left: `${x}%`,
        top: `${y}%`,
      };
    },
    shortLabel(label) {
      const text = String(label || '');
      if (text.length <= 14) {
        return text;
      }

      return `${text.slice(0, 13)}…`;
    },
    regionHasMine(region) {
      return Array.isArray(region.cities)
        && region.cities.some((city) => city.user_plot_number);
    },
    regionTitle(region) {
      const parts = [
        region.label,
        `открыто ${region.open_count}/${region.city_count}`,
      ];
      if (region.founded_count > region.open_count) {
        parts.push(`стройка ${region.founded_count - region.open_count}`);
      }

      return parts.join(' · ');
    },
    onRegionClick(regionId) {
      this.$emit('select-region', regionId);
    },
    onMyPlotClick(plot) {
      this.$emit('open-city', {
        slug: plot.slug,
        regionId: plot.regionId,
      });
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
.map_legend,
.map_hint {
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

.pangaea_frame {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid fade(@colorBlur, 35%);
  background: #0a1628;
  aspect-ratio: 16 / 9;
}

.pangaea_bg {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  user-select: none;
}

.region_pin {
  position: absolute;
  z-index: 2;
  transform: translate(-50%, -100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;
  max-width: 88px;
  padding: 0;
  border: 0;
  background: transparent;
  color: @colorText;
  cursor: pointer;
  text-shadow: 0 1px 3px fade(#000, 85%);

  &:hover .pin_dot,
  &.active .pin_dot {
    background: @orange;
    box-shadow: 0 0 0 2px fade(@orange, 45%);
  }

  &.has_open .pin_dot {
    background: #8ecf8e;
  }

  &.has_mine .pin_dot {
    box-shadow: 0 0 0 2px fade(#7fd67f, 75%);
  }
}

.pin_dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: fade(@colorText, 88%);
  border: 1px solid fade(#000, 55%);
  flex-shrink: 0;
}

.pin_label {
  font-size: 9px;
  line-height: 1.1;
  font-weight: 600;
  text-align: center;
  pointer-events: none;
}

.pin_stat {
  font-size: 8px;
  line-height: 1;
  color: fade(@colorBlur, 95%);
  pointer-events: none;
}

.region_chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.region_chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: 999px;
  border: 1px solid fade(@colorBlur, 40%);
  background: fade(@DarkColorBG, 72%);
  color: @colorText;
  font-size: 11px;
  cursor: pointer;

  &.has_open {
    border-color: fade(#8ecf8e, 55%);
  }

  &.active {
    border-color: @orange;
    background: fade(@orange, 16%);
  }
}

.chip_stat {
  font-size: 10px;
  color: @colorBlur;
}

.my_plots_block {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 4px;
  border-top: 1px dashed fade(@colorBlur, 30%);
}

.my_plots_title {
  font-size: 12px;
  font-weight: 600;
  color: @colorText;
  text-align: left;
}

.my_plot_link {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  width: 100%;
  padding: 6px 8px;
  border-radius: 5px;
  border: 1px solid fade(#7fd67f, 55%);
  background: fade(#7fd67f, 8%);
  color: @colorText;
  cursor: pointer;
  text-align: left;

  &:hover {
    border-color: fade(#9ee09e, 80%);
    background: fade(#9ee09e, 12%);
  }
}

.my_plot_main {
  font-size: 12px;
  color: #9ee09e;
}

.my_plot_meta {
  font-size: 10px;
  color: @colorBlur;
}
</style>
