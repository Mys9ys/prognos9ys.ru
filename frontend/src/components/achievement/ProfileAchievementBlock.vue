<template>
  <div class="achievement_block">
    <div class="msg error" v-if="error">{{ error }}</div>
    <div class="loading" v-if="loading">Загрузка…</div>
    <div class="groups" v-if="loaded">
      <div class="group" v-for="group in groups" :key="group.id">
        <div class="group_title">{{ group.title }}</div>
        <div class="grid">
          <div
            class="item"
            v-for="item in group.items"
            :key="item.code"
            :class="{ claimable: item.next_claimable_threshold > 0, locked: item.max_unlocked_threshold <= 0 }"
          >
            <div class="icon">{{ item.icon || '🏅' }}</div>
            <div class="name">{{ item.title }}</div>
            <div class="desc">{{ item.description }}</div>
            <div class="progress">
              {{ item.progress }} / {{ nextTarget(item) }}
            </div>
            <div class="levels" v-if="item.levels && item.levels.length">
              <span
                class="lvl"
                v-for="lvl in item.levels"
                :key="lvl.threshold"
                :class="{ ok: item.progress >= lvl.threshold, claimed: item.claimed_threshold >= lvl.threshold }"
              />
            </div>
            <button
              v-if="item.next_claimable_threshold > 0"
              class="btn_claim"
              :disabled="claimingCode === item.code"
              @click="claim(item.code)"
            >
              Забрать
            </button>
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
  quality: 'Качество',
  luck: 'Удача',
};

export default {
  name: 'ProfileAchievementBlock',
  data() {
    return {
      loading: false,
      loaded: false,
      error: '',
      items: [],
      claimingCode: '',
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
    nextTarget(item) {
      if (item.next_claimable_threshold > 0) {
        return item.next_claimable_threshold;
      }
      // следующий порог после текущего прогресса (или последний)
      const levels = item.levels || [];
      for (let i = 0; i < levels.length; i++) {
        const t = levels[i].threshold;
        if (item.progress < t) return t;
      }
      return levels.length ? levels[levels.length - 1].threshold : 1;
    },
    async load() {
      if (!this.authData?.token) {
        return;
      }

      this.loading = true;
      this.error = '';
      try {
        const data = await apiActions.game.getAchievements(this.authData.token);
        this.items = data.items || [];
        this.loaded = true;
      } catch (e) {
        this.error = e.message || 'Не удалось загрузить ачивки';
      } finally {
        this.loading = false;
      }
    },
    async claim(code) {
      if (!this.authData?.token || !code) {
        return;
      }
      this.error = '';
      this.claimingCode = code;
      try {
        const data = await apiActions.game.claimAchievement(this.authData.token, code);
        this.items = data.achievements?.items || this.items;
        if (data.game) {
          this.$store.commit('auth/setUserInfo', {
            ...this.$store.state.auth.userInfo,
            game_info: data.game,
          });
        }
      } catch (e) {
        this.error = e.message || 'Не удалось забрать награду';
      } finally {
        this.claimingCode = '';
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

.grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.item {
  width: calc(50% - 4px);
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 8px;
  border-radius: 5px;
  .shadow_inset;
  position: relative;

  &.claimable { background: fade(@orange, 14%); }
  &.locked { opacity: 0.6; }
}

.icon {
  font-size: 22px;
  line-height: 1;
  text-align: left;
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

.levels {
  display: flex;
  gap: 3px;
  margin-top: 2px;
}

.lvl {
  width: 10px;
  height: 4px;
  border-radius: 2px;
  background: rgba(255,255,255,0.12);
  &.ok { background: fade(@orange, 55%); }
  &.claimed { background: @orange; }
}

.btn_claim {
  margin-top: 6px;
  padding: 6px 8px;
  border-radius: 4px;
  border: 0;
  background: @orange;
  color: #111;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  &:disabled { opacity: 0.6; cursor: default; }
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
