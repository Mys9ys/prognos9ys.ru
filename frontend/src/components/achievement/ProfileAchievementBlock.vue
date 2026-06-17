<template>
  <div class="achievement_block">
    <div class="summary" v-if="loaded">
      Получено: {{ unlockedCount }} / {{ totalCount }}
    </div>
    <div class="msg error" v-if="error">{{ error }}</div>
    <div class="loading" v-if="loading">Загрузка…</div>
    <div class="groups" v-if="loaded">
      <div class="group" v-for="group in groups" :key="group.id">
        <div class="group_title">{{ group.title }}</div>
        <div class="items">
          <div
            class="item"
            v-for="item in group.items"
            :key="item.code"
            :class="{ unlocked: item.unlocked, locked: !item.unlocked }"
          >
            <div class="icon">{{ item.unlocked ? '🏅' : '🔒' }}</div>
            <div class="body">
              <div class="name">{{ item.title }}</div>
              <div class="desc">{{ item.description }}</div>
              <div class="progress" v-if="item.target > 1">
                {{ Math.min(item.progress, item.target) }} / {{ item.target }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { apiActions } from '@/api/bitrixClient';
import { mapState } from 'vuex';

const GROUP_TITLES = {
  welcome: 'Старт',
  prognosis: 'Прогнозы',
  chm: 'ЧМ-2026',
};

export default {
  name: 'ProfileAchievementBlock',
  data() {
    return {
      loading: false,
      loaded: false,
      error: '',
      items: [],
      unlockedCount: 0,
      totalCount: 0,
    };
  },
  computed: {
    ...mapState('auth', ['authData']),
    groups() {
      const map = {};
      this.items.forEach((item) => {
        const id = item.group || 'other';
        if (!map[id]) {
          map[id] = {
            id,
            title: GROUP_TITLES[id] || id,
            items: [],
          };
        }
        map[id].items.push(item);
      });

      return Object.values(map);
    },
  },
  created() {
    this.load();
  },
  methods: {
    async load() {
      if (!this.authData?.token) {
        return;
      }

      this.loading = true;
      this.error = '';
      try {
        const data = await apiActions.game.getAchievements(this.authData.token);
        this.items = data.items || [];
        this.unlockedCount = data.unlocked_count || 0;
        this.totalCount = data.total_count || 0;
        this.loaded = true;
      } catch (e) {
        this.error = e.message || 'Не удалось загрузить ачивки';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.achievement_block {
  text-align: left;
  padding: 4px 0 12px;
}

.summary {
  font-size: 13px;
  color: @colorBlur;
  margin-bottom: 10px;
  text-align: center;
}

.loading {
  font-size: 13px;
  color: @colorBlur;
  text-align: center;
  padding: 12px;
}

.group {
  margin-bottom: 12px;
}

.group_title {
  font-size: 13px;
  color: @orange;
  margin-bottom: 6px;
}

.items {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.item {
  display: flex;
  gap: 10px;
  padding: 8px;
  border-radius: 5px;
  .shadow_inset;

  &.unlocked {
    background: fade(@orange, 12%);
  }

  &.locked {
    opacity: 0.65;
  }
}

.icon {
  font-size: 22px;
  line-height: 1;
  flex-shrink: 0;
}

.body {
  flex: 1;
  min-width: 0;
}

.name {
  font-size: 14px;
  font-weight: 500;
}

.desc {
  font-size: 12px;
  color: @colorBlur;
  margin-top: 2px;
}

.progress {
  font-size: 11px;
  color: @orange;
  margin-top: 4px;
}

.msg.error {
  font-size: 12px;
  color: #f88;
  padding: 6px;
  margin-bottom: 8px;
  background: rgba(200, 60, 60, 0.2);
  border-radius: 4px;
}
</style>
