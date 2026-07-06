<template>
  <div class="estate_region_map" v-if="region">
    <div class="region_header">
      <button type="button" class="back_btn" @click="$emit('back')">← Карта мира</button>
      <div class="region_title_block">
        <div class="region_title">{{ region.label }}</div>
        <div class="region_meta">
          открыто {{ region.open_count }}/{{ region.city_count }}
          <span v-if="region.founded_count > region.open_count">
            · стройка {{ region.founded_count - region.open_count }}
          </span>
        </div>
      </div>
    </div>

    <div class="region_canvas">
      <div class="region_bg" :style="regionBgStyle" aria-hidden="true">
        <div class="region_bg_shade" />
        <div class="region_bg_vignette" />
      </div>

      <button
        v-for="city in region.cities"
        :key="city.slug"
        type="button"
        class="city_marker"
        :class="cityCellClass(city)"
        :style="markerStyle(city)"
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

    <p class="region_hint">Соседние города на карте мира смогут сходиться в битвах болельщиков.</p>
  </div>
</template>

<script>
import { getWc26PennantIconSrc } from '@/config/wc26PennantIcons';
import pangaeaImage from '@/assets/estate/pangaea_world.png';

/** Высота логической сетки мира в конфиге (ширина 100). */
const WORLD_MAP_LOGIC_HEIGHT = 62;

export default {
  name: 'EstateRegionMap',
  props: {
    region: {
      type: Object,
      default: null,
    },
    selectedSlug: {
      type: String,
      default: '',
    },
  },
  emits: ['back', 'select-city'],
  data() {
    return {
      pangaeaImage,
    };
  },
  computed: {
    regionBgStyle() {
      const region = this.region;
      if (!region) {
        return {};
      }

      const bbox = this.polygonBBoxOnImage(region.zone_polygon);
      const cx = Number(region.world?.x) || (bbox.minX + bbox.maxX) / 2;
      const cy = Number(region.world?.y) || (bbox.minY + bbox.maxY) / 2;
      const span = Math.max(bbox.width, bbox.height, 16);
      const zoom = Math.min(440, Math.max(170, Math.round((100 / span) * 94)));

      return {
        backgroundImage: `url(${this.pangaeaImage})`,
        backgroundSize: `${zoom}% auto`,
        backgroundPosition: `${cx}% ${cy}%`,
        backgroundRepeat: 'no-repeat',
      };
    },
  },
  methods: {
    polygonBBoxOnImage(polygon) {
      if (!Array.isArray(polygon) || !polygon.length) {
        return {
          minX: 0,
          minY: 0,
          maxX: 100,
          maxY: 100,
          width: 100,
          height: 100,
        };
      }

      const points = polygon.map((point) => ({
        x: Number(point.x) || 0,
        y: ((Number(point.y) || 0) / WORLD_MAP_LOGIC_HEIGHT) * 100,
      }));
      const xs = points.map((point) => point.x);
      const ys = points.map((point) => point.y);
      const minX = Math.min(...xs);
      const maxX = Math.max(...xs);
      const minY = Math.min(...ys);
      const maxY = Math.max(...ys);

      return {
        minX,
        minY,
        maxX,
        maxY,
        width: maxX - minX,
        height: maxY - minY,
      };
    },
    pennantSrc(slug) {
      return getWc26PennantIconSrc(`pennant_wc26_${slug}`);
    },
    shortCityName(name) {
      const text = String(name || '');
      return text.length > 12 ? `${text.slice(0, 11)}…` : text;
    },
    markerStyle(city) {
      const x = Number(city?.local?.x) || 50;
      const y = Number(city?.local?.y) || 50;

      return {
        left: `${x}%`,
        top: `${y}%`,
      };
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
      if (Array.isArray(city.neighbors) && city.neighbors.length) {
        parts.push(`соседи: ${city.neighbors.join(', ')}`);
      }
      return parts.filter(Boolean).join(' · ');
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.estate_region_map {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.region_header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 8px;
}

.back_btn {
  border: 1px solid fade(@colorBlur, 40%);
  background: fade(@DarkColorBG, 70%);
  color: @colorText;
  border-radius: 4px;
  padding: 4px 8px;
  font-size: 11px;
  cursor: pointer;
}

.region_title_block {
  flex: 1;
  min-width: 0;
  text-align: left;
}

.region_title {
  font-size: 14px;
  font-weight: 600;
  color: @colorText;
}

.region_meta {
  font-size: 11px;
  color: @colorBlur;
  margin-top: 2px;
}

.region_canvas {
  position: relative;
  min-height: 240px;
  aspect-ratio: 16 / 10;
  border-radius: 8px;
  border: 1px solid fade(@colorBlur, 35%);
  overflow: hidden;
  background: #0a1628;
}

.region_bg {
  position: absolute;
  inset: 0;
  z-index: 0;
}

.region_bg_shade {
  position: absolute;
  inset: 0;
  background: fade(#061018, 28%);
  pointer-events: none;
}

.region_bg_vignette {
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at center, transparent 35%, fade(#020810, 72%) 100%);
  pointer-events: none;
}

.city_marker {
  position: absolute;
  transform: translate(-50%, -50%);
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  min-width: 52px;
  max-width: 72px;
  padding: 4px 3px;
  border-radius: 6px;
  border: 1px dashed fade(@colorBlur, 45%);
  background: fade(@darkbg, 88%);
  color: @colorText;
  cursor: pointer;
  box-shadow: 0 2px 8px fade(#000, 45%);
  .shadow_inset;

  &.ghost {
    opacity: 0.62;
  }

  &.founding {
    border-color: fade(@orange, 75%);
  }

  &.open {
    border-style: solid;
    border-color: fade(@colorText, 35%);
  }

  &.mine {
    box-shadow: inset 0 0 0 1px fade(#7fd67f, 55%), 0 2px 8px fade(#000, 45%);
  }

  &.selected {
    border-color: @orange;
    border-style: solid;
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
  text-shadow: 0 1px 2px fade(#000, 80%);
}

.city_status_dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: fade(@colorBlur, 70%);
}

.city_marker.founding .city_status_dot {
  background: @orange;
}

.city_marker.open .city_status_dot {
  background: #9ee09e;
}

.city_marker.mine .city_status_dot {
  background: #7fd67f;
}

.region_hint {
  font-size: 11px;
  color: @colorBlur;
  text-align: left;
  margin: 0;
}
</style>
