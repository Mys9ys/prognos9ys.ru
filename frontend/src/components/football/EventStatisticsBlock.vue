<template>
  <div class="event_stats" v-if="visible">
    <div class="stats_header" @click="expanded = !expanded">
      <span class="title">Статистика</span>
      <span class="meta" v-if="games.matches_count">
        {{ games.matches_count }} матч{{ matchesSuffix(games.matches_count) }}
      </span>
      <span class="toggle">{{ expanded ? '−' : '+' }}</span>
    </div>

    <div class="stats_body" v-if="expanded">
      <PreLoader v-if="loading" />

      <template v-else>
        <div class="msg error" v-if="error">{{ error }}</div>

        <div class="stats_tabs">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="tab_btn"
            :class="{ active: activeTab === tab.id }"
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
          </button>
        </div>

        <div class="stats_hint" v-if="activeTab === 'games'">
          Всего / среднее за матч
        </div>
        <div class="stats_hint" v-else>
          Точн. / угадано / баллы
          <span class="hint_sub">(точн. — для ачивок-счётчиков)</span>
        </div>

        <div v-if="activeTab === 'games'" class="stats_panel">
          <div class="stats_empty" v-if="!games.matches_count">
            Пока нет матчей с результатом
          </div>
          <div
            v-for="metric in games.metrics"
            :key="metric.key"
            class="stats_row"
          >
            <div class="row_label">
              <FootballMetricIcon :metric="metric.icon" :size="18" badge />
              <span>{{ metric.label }}</span>
            </div>
            <div class="row_value">
              {{ formatStat(metric.total) }} / {{ formatStat(metric.avg) }}
            </div>
          </div>
        </div>

        <div v-else class="stats_panel">
          <div class="stats_empty" v-if="!prognosis.logged_in">
            Войдите, чтобы увидеть статистику прогнозов
          </div>
          <div class="stats_empty" v-else-if="!prognosis.matches_count">
            Нет рассчитанных прогнозов по этому событию
          </div>
          <div
            v-for="metric in prognosis.metrics"
            :key="metric.key"
            class="stats_row"
          >
            <div class="row_label">
              <FootballMetricIcon :metric="metric.icon" :size="18" badge />
              <span>{{ metric.label }}</span>
            </div>
            <div class="row_value">
              <template v-if="usesExactHits(metric)">
                <span class="exact_hits">{{ metric.exact_hits }}</span>
                <span class="sep">/</span>
              </template>
              {{ metric.hits }} / {{ formatStat(metric.points) }}
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
import PreLoader from '@/components/main/PreLoader.vue';
import FootballMetricIcon from '@/components/football/FootballMetricIcon.vue';
import { apiActions } from '@/api/bitrixClient';

const EMPTY_GAMES = { matches_count: 0, metrics: [] };
const EMPTY_PROGNOSIS = { matches_count: 0, logged_in: false, metrics: [] };

export default {
  name: 'EventStatisticsBlock',
  components: { PreLoader, FootballMetricIcon },
  props: {
    eventId: {
      type: [String, Number],
      required: true,
    },
    userToken: {
      type: String,
      default: '',
    },
  },
  data() {
    return {
      expanded: true,
      loading: false,
      error: '',
      visible: true,
      activeTab: 'games',
      tabs: [
        { id: 'games', label: 'Игры' },
        { id: 'prognosis', label: 'Прогнозы' },
      ],
      games: { ...EMPTY_GAMES },
      prognosis: { ...EMPTY_PROGNOSIS },
    };
  },
  watch: {
    eventId: {
      immediate: true,
      handler() {
        this.loadStats();
      },
    },
    userToken() {
      this.loadStats();
    },
  },
  methods: {
    async loadStats() {
      if (!this.eventId) {
        return;
      }

      this.loading = true;
      this.error = '';

      try {
        const data = await apiActions.football.getEventStatistics(
          String(this.eventId),
          this.userToken || '',
        );

        if ((data?.status || '') !== 'ok') {
          throw new Error(data?.message || 'Не удалось загрузить статистику');
        }

        this.games = data.games || { ...EMPTY_GAMES };
        this.prognosis = data.prognosis || { ...EMPTY_PROGNOSIS };
        this.visible = true;
      } catch (err) {
        this.error = err?.message || 'Ошибка загрузки статистики';
        this.games = { ...EMPTY_GAMES };
        this.prognosis = { ...EMPTY_PROGNOSIS };
      } finally {
        this.loading = false;
      }
    },

    formatStat(value) {
      const num = Number(value);
      if (!Number.isFinite(num)) {
        return '0';
      }
      return Number.isInteger(num) ? String(num) : num.toFixed(1);
    },

    matchesSuffix(count) {
      const n = Math.abs(Number(count)) % 100;
      const n1 = n % 10;
      if (n > 10 && n < 20) return 'ей';
      if (n1 > 1 && n1 < 5) return 'а';
      if (n1 === 1) return '';
      return 'ей';
    },

    usesExactHits(metric) {
      return !['corners', 'yellow', 'possession'].includes(metric.key);
    },
  },
};
</script>

<style lang="less" scoped>
@import 'src/assets/css/variables.less';

.event_stats {
  margin-top: 10px;
  background: @DarkColorBG;
  border-radius: 5px;
  color: @colorText;
}

.stats_header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  cursor: pointer;
  user-select: none;

  .title {
    font-weight: 600;
    .shadow_inset;
    padding: 2px 6px;
  }

  .meta {
    font-size: 12px;
    opacity: 0.85;
  }

  .toggle {
    margin-left: auto;
    font-size: 18px;
    line-height: 1;
    width: 20px;
    text-align: center;
  }
}

.stats_body {
  padding: 0 10px 10px;
}

.stats_tabs {
  display: flex;
  gap: 4px;
  margin-bottom: 6px;
}

.tab_btn {
  flex: 1;
  border: none;
  border-radius: 4px;
  padding: 6px 8px;
  background: @colorText2;
  color: @colorText;
  cursor: pointer;
  font-size: 12px;
  .shadow_template;

  &.active {
    background: @football;
    color: @DarkColorBG;
  }
}

.stats_hint {
  font-size: 11px;
  opacity: 0.75;
  margin-bottom: 6px;
  text-align: right;

  .hint_sub {
    display: block;
    font-size: 10px;
    opacity: 0.85;
  }
}

.stats_panel {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stats_row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 4px 6px;
  border-radius: 4px;
  background: fade(@colorBlur, 35%);
}

.row_label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  text-align: left;
}

.row_value {
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  .shadow_inset;
  padding: 2px 8px;

  .exact_hits {
    color: @football;
  }

  .sep {
    opacity: 0.6;
    margin: 0 1px;
  }
}

.stats_empty,
.msg.error {
  padding: 10px;
  border-radius: 4px;
  font-size: 13px;
  text-align: center;
  .shadow_inset;
}

.msg.error {
  color: #ffb4b4;
  margin-bottom: 8px;
}
</style>
