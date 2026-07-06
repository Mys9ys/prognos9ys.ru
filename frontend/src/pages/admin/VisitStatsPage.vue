<template>
  <div class="visit_stats_page">
    <PageHeader>Статистика посещений</PageHeader>

    <div class="toolbar">
      <label class="period_label">
        Период
        <select v-model.number="days" class="period_select" @change="loadStats">
          <option :value="7">7 дней</option>
          <option :value="14">14 дней</option>
          <option :value="30">30 дней</option>
          <option :value="90">90 дней</option>
        </select>
      </label>
      <button type="button" class="refresh_btn" :disabled="loading" @click="loadStats">
        {{ loading ? 'Загрузка…' : 'Обновить' }}
      </button>
    </div>

    <div v-if="error" class="error_box">{{ error }}</div>

    <template v-else-if="stats">
      <p v-if="stats.truncated" class="hint warn">
        Показана выборка (лимит 100 000 записей) — для точных цифр сузьте период.
      </p>

      <div class="summary_grid">
        <div class="summary_card">
          <div class="summary_value">{{ stats.totals.visits }}</div>
          <div class="summary_label">Всего просмотров</div>
        </div>
        <div class="summary_card">
          <div class="summary_value">{{ stats.totals.guest_visits }}</div>
          <div class="summary_label">Гостевые</div>
        </div>
        <div class="summary_card">
          <div class="summary_value">{{ stats.totals.user_visits }}</div>
          <div class="summary_label">Авторизованные</div>
        </div>
        <div class="summary_card">
          <div class="summary_value">{{ guestShare }}%</div>
          <div class="summary_label">Доля гостей</div>
        </div>
      </div>

      <div class="section_card">
        <div class="section_title">По дням</div>
        <div v-if="!stats.daily.length" class="hint">Пока нет данных</div>
        <table v-else class="stats_table">
          <thead>
            <tr>
              <th>Дата</th>
              <th>Просмотры</th>
              <th>Уник. гости (IP)</th>
              <th>Уник. игроки</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in reversedDaily" :key="row.date">
              <td>{{ formatDate(row.date) }}</td>
              <td>{{ row.visits }}</td>
              <td>{{ row.unique_guests }}</td>
              <td>{{ row.unique_users }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="section_card">
        <div class="section_title">Топ экранов</div>
        <div v-if="!stats.top_screens.length" class="hint">Пока нет данных</div>
        <table v-else class="stats_table">
          <thead>
            <tr>
              <th>Экран</th>
              <th>Всего</th>
              <th>Гости</th>
              <th>Игроки</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in stats.top_screens" :key="row.screen">
              <td class="mono">{{ row.screen }}</td>
              <td>{{ row.visits }}</td>
              <td>{{ row.guest_visits }}</td>
              <td>{{ row.user_visits }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="section_card" v-if="stats.devices && stats.devices.length">
        <div class="section_title">Устройства</div>
        <div class="device_list">
          <div v-for="item in stats.devices" :key="item.device" class="device_row">
            <span>{{ deviceLabel(item.device) }}</span>
            <span>{{ item.visits }}</span>
          </div>
        </div>
      </div>
    </template>

    <div v-else-if="loading" class="hint">Загрузка статистики…</div>
  </div>
</template>

<script>
import { mapGetters, mapState } from 'vuex';
import PageHeader from '@/components/main/PageHeader.vue';
import { apiActions } from '@/api/bitrixClient';

const DEVICE_LABELS = {
  mobile: 'Мобильные',
  tablet: 'Планшеты',
  desktop: 'Десктоп',
  unknown: 'Неизвестно',
};

export default {
  name: 'VisitStatsPage',
  components: { PageHeader },
  data() {
    return {
      loading: false,
      error: '',
      stats: null,
      days: 30,
    };
  },
  computed: {
    ...mapState({
      token: (state) => state.auth.authData.token,
    }),
    ...mapGetters('auth', ['canImpersonate']),
    guestShare() {
      const total = Number(this.stats?.totals?.visits || 0);
      if (!total) {
        return 0;
      }
      const guests = Number(this.stats?.totals?.guest_visits || 0);
      return Math.round((guests / total) * 100);
    },
    reversedDaily() {
      return [...(this.stats?.daily || [])].reverse();
    },
  },
  async created() {
    if (!this.canImpersonate) {
      this.error = 'Раздел доступен только администраторам и супермодераторам';
      return;
    }
    await this.loadStats();
  },
  methods: {
    async loadStats() {
      if (!this.token || !this.canImpersonate) {
        return;
      }

      this.loading = true;
      this.error = '';

      try {
        const data = await apiActions.analytics.getVisitStats(this.token, this.days);
        if (data.status !== 'ok') {
          throw new Error(data.message || 'Не удалось загрузить статистику');
        }
        this.stats = data;
      } catch (err) {
        this.error = err.message || 'Ошибка загрузки';
        this.stats = null;
      } finally {
        this.loading = false;
      }
    },
    formatDate(iso) {
      if (!iso) {
        return '—';
      }
      const parts = iso.split('-');
      if (parts.length !== 3) {
        return iso;
      }
      return `${parts[2]}.${parts[1]}`;
    },
    deviceLabel(code) {
      return DEVICE_LABELS[code] || code;
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.visit_stats_page {
  padding-bottom: 12px;
}

.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
}

.period_label {
  font-size: 12px;
  color: @colorBlur;
}

.period_select {
  margin-left: 6px;
  background: @DarkColorBG;
  color: @colorText;
  border: 1px solid fade(@colorBlur, 40%);
  border-radius: 4px;
  padding: 4px 6px;
}

.refresh_btn {
  padding: 5px 10px;
  border-radius: 4px;
  border: 1px solid fade(@YesWrite, 50%);
  background: fade(@YesWrite, 15%);
  color: @colorText;
  font-size: 12px;
  cursor: pointer;

  &:disabled {
    opacity: 0.6;
    cursor: default;
  }
}

.summary_grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 10px;
}

.summary_card {
  background: @DarkColorBG;
  border-radius: 5px;
  padding: 10px;
  text-align: center;
}

.summary_value {
  font-size: 20px;
  font-weight: 700;
  color: @orange;
}

.summary_label {
  font-size: 11px;
  color: @colorBlur;
  margin-top: 4px;
}

.section_card {
  background: @DarkColorBG;
  border-radius: 5px;
  padding: 8px;
  margin-bottom: 8px;
}

.section_title {
  font-size: 13px;
  color: @orange;
  margin-bottom: 8px;
}

.stats_table {
  width: 100%;
  font-size: 11px;
  border-collapse: collapse;

  th,
  td {
    padding: 4px 6px;
    border-bottom: 1px solid fade(@colorBlur, 25%);
    text-align: left;
  }

  th {
    color: @colorBlur;
    font-weight: 600;
  }
}

.mono {
  font-family: monospace;
  word-break: break-all;
}

.device_list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.device_row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  padding: 4px 0;
  border-bottom: 1px solid fade(@colorBlur, 20%);
}

.hint {
  font-size: 12px;
  color: @colorBlur;

  &.warn {
    color: @orange;
    margin-bottom: 8px;
  }
}

.error_box {
  padding: 8px;
  border-radius: 4px;
  background: fade(@NoWrite, 15%);
  color: @NoWrite;
  font-size: 12px;
}
</style>
