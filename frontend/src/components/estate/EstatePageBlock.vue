<template>
  <div class="estate_page">
    <div v-if="loading" class="hint">Загрузка карты поселений…</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <template v-else>
      <p class="hint intro">
        Карта пангеи ЧМ-26: выберите регион, затем поселение.
        Пунктир — план или стройка, сплошная рамка — открытый город.
      </p>

      <div class="section_card">
        <div class="section_title">Карта поселений</div>
        <EstateWorldMap
          v-if="view === 'world'"
          :map="map"
          :selected-region-id="selectedRegionId"
          @select-region="onSelectRegion"
        />
        <EstateRegionMap
          v-else-if="view === 'region' && selectedRegion"
          :region="selectedRegion"
          :selected-slug="selectedSlug"
          @back="onBackToWorld"
          @select-city="onSelectCity"
        />
      </div>

      <div class="section_card section_card_city" v-if="selectedSlug">
        <div class="section_title_row">
          <button
            v-if="view === 'street'"
            type="button"
            class="back_btn"
            @click="onBackToRegion"
          >
            ← {{ selectedRegion?.label || 'Регион' }}
          </button>
          <div class="section_title">Улица города</div>
        </div>
        <p class="hint street_hint">
          20 усадеб: по 5 сверху и снизу слева и справа. В центре — биржа, банк и широкая управа.
          Листайте вбок на узком экране.
        </p>
        <div v-if="cityLoading" class="hint">Загрузка улицы…</div>
        <div v-else-if="cityError" class="error">{{ cityError }}</div>
        <EstateCityStreetMap v-else-if="cityMap" :city="cityMap" />
      </div>
      <div class="section_card" v-else-if="view === 'region'">
        <p class="hint">Выберите город на карте региона.</p>
      </div>
    </template>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { apiActions } from '@/api/bitrixClient';
import EstateWorldMap from '@/components/estate/EstateWorldMap.vue';
import EstateRegionMap from '@/components/estate/EstateRegionMap.vue';
import EstateCityStreetMap from '@/components/estate/EstateCityStreetMap.vue';

export default {
  name: 'EstatePageBlock',
  components: { EstateWorldMap, EstateRegionMap, EstateCityStreetMap },
  data() {
    return {
      loading: false,
      error: '',
      map: null,
      view: 'world',
      selectedRegionId: '',
      selectedSlug: '',
      cityMap: null,
      cityLoading: false,
      cityError: '',
    };
  },
  computed: {
    ...mapState('auth', ['authData']),
    selectedRegion() {
      if (!this.map?.regions || !this.selectedRegionId) {
        return null;
      }

      return this.map.regions.find((region) => region.id === this.selectedRegionId) || null;
    },
  },
  mounted() {
    this.loadMap();
  },
  methods: {
    async loadMap() {
      const token = this.authData?.token;
      if (!token) {
        this.error = 'Нужна авторизация';
        return;
      }

      this.loading = true;
      this.error = '';
      try {
        const data = await apiActions.game.getEstateMapState(token);
        if (data?.status !== 'ok') {
          throw new Error(data?.message || 'Не удалось загрузить карту');
        }
        this.map = data.map || null;
      } catch (e) {
        this.error = e?.message || 'Ошибка загрузки карты';
      } finally {
        this.loading = false;
      }
    },

    onSelectRegion(regionId) {
      this.selectedRegionId = regionId;
      this.selectedSlug = '';
      this.cityMap = null;
      this.cityError = '';
      this.view = 'region';
    },

    onBackToWorld() {
      this.view = 'world';
      this.selectedRegionId = '';
      this.selectedSlug = '';
      this.cityMap = null;
      this.cityError = '';
    },

    onBackToRegion() {
      this.view = 'region';
      this.selectedSlug = '';
      this.cityMap = null;
      this.cityError = '';
    },

    async onSelectCity(slug) {
      this.selectedSlug = slug;
      this.view = 'street';
      await this.loadCityMap(slug);
    },

    async loadCityMap(slug) {
      const token = this.authData?.token;
      if (!token || !slug) {
        return;
      }

      this.cityLoading = true;
      this.cityError = '';
      this.cityMap = null;
      try {
        const data = await apiActions.game.getEstateCityMap(token, slug);
        if (data?.status !== 'ok') {
          throw new Error(data?.message || 'Не удалось загрузить улицу');
        }
        this.cityMap = data.city || null;
        if (data.city?.region_id && !this.selectedRegionId) {
          this.selectedRegionId = data.city.region_id;
        }
      } catch (e) {
        this.cityError = e?.message || 'Ошибка загрузки улицы';
      } finally {
        this.cityLoading = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.estate_page {
  text-align: left;
}

.intro {
  margin-bottom: 10px;
}

.section_card {
  background: fade(@DarkColorBG, 55%);
  border-radius: 6px;
  padding: 10px;
  margin-bottom: 10px;
}

.section_title_row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.section_title {
  font-size: 13px;
  font-weight: 600;
  color: @colorText;
}

.back_btn {
  border: 1px solid fade(@colorBlur, 40%);
  background: fade(@DarkColorBG, 70%);
  color: @colorText;
  border-radius: 4px;
  padding: 3px 8px;
  font-size: 11px;
  cursor: pointer;
}

.street_hint {
  margin-bottom: 8px;
}

.hint {
  color: @colorBlur;
  font-size: 12px;
}

.error {
  color: #f08080;
  font-size: 12px;
}
</style>
