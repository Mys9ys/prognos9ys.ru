<template>
  <div class="estate_page">
    <div v-if="loading" class="hint">Загрузка карты поселений…</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <template v-else>
      <p class="hint intro">
        Черновик карты ЧМ-26: пунктир — план или стройка, сплошная рамка — открытый город.
        Нажмите поселение, чтобы увидеть улицу и привязку деталей к рецептам крафта.
      </p>

      <div class="section_card">
        <div class="section_title">Карта поселений</div>
        <EstateWorldMap
          :map="map"
          :selected-slug="selectedSlug"
          @select-city="onSelectCity"
        />
      </div>

      <div class="section_card section_card_city" v-if="selectedSlug">
        <div class="section_title">Улица города</div>
        <p class="hint street_hint">
          20 усадеб: по 5 сверху и снизу слева и справа. В центре — биржа, банк и широкая управа.
          Листайте вбок на узком экране.
        </p>
        <div v-if="cityLoading" class="hint">Загрузка улицы…</div>
        <div v-else-if="cityError" class="error">{{ cityError }}</div>
        <EstateCityStreetMap v-else-if="cityMap" :city="cityMap" />
      </div>
      <div class="section_card" v-else>
        <p class="hint">Выберите город на карте выше.</p>
      </div>
    </template>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { apiActions } from '@/api/bitrixClient';
import EstateWorldMap from '@/components/estate/EstateWorldMap.vue';
import EstateCityStreetMap from '@/components/estate/EstateCityStreetMap.vue';

export default {
  name: 'EstatePageBlock',
  components: { EstateWorldMap, EstateCityStreetMap },
  data() {
    return {
      loading: false,
      error: '',
      map: null,
      selectedSlug: '',
      cityMap: null,
      cityLoading: false,
      cityError: '',
    };
  },
  computed: {
    ...mapState('auth', ['authData']),
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

    async onSelectCity(slug) {
      this.selectedSlug = slug;
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
          throw new Error(data?.message || 'Не удалось загрузить город');
        }
        this.cityMap = data.city || null;
      } catch (e) {
        this.cityError = e?.message || 'Ошибка загрузки города';
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
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.section_card {
  background: @DarkColorBG;
  padding: 8px;
  border-radius: 5px;
}

.section_title {
  color: @colorText;
  font-size: 14px;
  margin-bottom: 8px;
  text-align: left;
}

.intro,
.hint {
  color: @colorBlur;
  font-size: 12px;
  text-align: left;
}

.street_hint {
  margin: -4px 0 8px;
  font-size: 11px;
}

.section_card_city {
  overflow: hidden;
}

.error {
  color: #ff8f8f;
  font-size: 12px;
  text-align: left;
}
</style>
