<template>
  <div class="body_wrapper">
    <RatingTableHeader
        :icon-key="icon"
        :title="title[icon]"
        :match-numbers="resolvedMatchNumbers"
        :model-value="selectedMatch"
        :match-titles="matchTitles"
        @update:modelValue="onMatchChange"
    />
    <SelectBlockRating
        v-if="hasRenderableData"
        :arRating="arRating"
        :selected="selectedMatch"
    ></SelectBlockRating>
    <RatingTabLoader
        v-else-if="loading || !hasData"
        :icon-key="icon"
        :title="title[icon]"
    />
    <div v-else class="empty_state">Данных пока нет</div>

  </div>
</template>

<script>
import SelectBlockRating from "@/components/football/SelectBlockRating";
import RatingTabLoader from "@/components/football/RatingTabLoader";
import RatingTableHeader from "@/components/football/RatingTableHeader";

export default {
  name: "FootballRatingBody",
  components: { SelectBlockRating, RatingTabLoader, RatingTableHeader },
  emits: ['update:selectedMatch'],

  props: {
    arRating: {
      type: Object
    },
    icon: {
      type: [String, Number]
    },
    loading: {
      type: Boolean,
      default: false,
    },
    hasData: {
      type: Boolean,
      default: false,
    },
    matchTitles: {
      type: Object,
      default: () => ({}),
    },
    matchNumbers: {
      type: Array,
      default: () => [],
    },
    selectedMatch: {
      type: [String, Number],
      default: '',
    },
  },
  data(){
    return{
      title: {
        1: 'Сводный рейтинг (сумма остальных)',
        2: 'Счет матча',
        18: 'Исход матча (победа(п1,п2)/ничья)',
        28: 'Разница голов (п1-п2)',
        19: 'Сумма голов (п1+п2)',
        32: 'Владение мячом (%)',
        21: 'Желтые карточки (сумма)',
        22: 'Красные карточки (сумма)',
        20: 'Угловые (сумма)',
        23: 'Пенальти (сумма)',
        45: 'Доп. время (есть/нет)',
        46: 'Серия пенальти (есть/нет)',
        100: 'Лучшие прогнозы (>30 баллов)',
      }
    }
  },
  computed: {
    hasRenderableData() {
      if (!this.arRating || !this.selectedMatch) {
        return false;
      }
      const key = String(this.selectedMatch);
      return !!(this.arRating[key] || this.arRating[Number(key)]);
    },
    resolvedMatchNumbers() {
      if (Array.isArray(this.matchNumbers) && this.matchNumbers.length) {
        return this.matchNumbers.map(Number).filter((n) => n > 0);
      }
      const fromTitles = Object.keys(this.matchTitles || {}).map(Number).filter((n) => n > 0);
      if (fromTitles.length) {
        return fromTitles;
      }
      return Object.keys(this.arRating || {}).map(Number).filter((n) => n > 0);
    },
  },
  methods: {
    onMatchChange(value) {
      this.$emit('update:selectedMatch', value);
    },
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.body_wrapper{
  width: 100%;
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;
  margin-top: 4px;
}

.empty_state {
  .shadow_inset;
  min-height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: @pearl;
  font-size: 12px;
}
</style>
