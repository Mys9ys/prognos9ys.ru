<template>
  <div class="achievement_block">
    <div class="ach_tabs">
      <button
        type="button"
        class="ach_tab"
        :class="{ active: activeTab === 'general' }"
        @click="activeTab = 'general'"
      >
        Общее
        <span v-if="generalCount" class="ach_tab_count">({{ generalCount }})</span>
      </button>
      <button
        type="button"
        class="ach_tab"
        :class="{ active: activeTab === 'profession' }"
        @click="activeTab = 'profession'"
      >
        Профессии
        <span v-if="professionCount" class="ach_tab_count">({{ professionCount }})</span>
      </button>
      <button
        type="button"
        class="ach_tab"
        :class="{ active: activeTab === 'production' }"
        @click="activeTab = 'production'"
      >
        Производство
        <span v-if="productionCount" class="ach_tab_count">({{ productionCount }})</span>
      </button>
      <button
        type="button"
        class="ach_tab"
        :class="{ active: activeTab === 'potion' }"
        @click="activeTab = 'potion'"
      >
        Зелья
        <span v-if="potionCount" class="ach_tab_count">({{ potionCount }})</span>
      </button>
      <button
        type="button"
        class="ach_tab"
        :class="{ active: activeTab === 'exchange' }"
        @click="activeTab = 'exchange'"
      >
        Биржа
        <span v-if="exchangeCount" class="ach_tab_count">({{ exchangeCount }})</span>
      </button>
      <button
        type="button"
        class="ach_tab"
        :class="{ active: activeTab === 'football' }"
        @click="activeTab = 'football'"
      >
        Футбол
        <span v-if="footballCount" class="ach_tab_count">({{ footballCount }})</span>
      </button>
    </div>

    <div class="msg error" v-if="error">{{ error }}</div>
    <div class="loading" v-if="loading">Загрузка…</div>

    <div v-if="loaded">
      <div class="ach_subtabs" v-if="activeTab === 'profession'">
        <button
          type="button"
          class="ach_subtab"
          :class="{ active: professionStageTab === 1 }"
          @click="professionStageTab = 1"
        >
          Этап 1
        </button>
        <button
          type="button"
          class="ach_subtab"
          :class="{ active: professionStageTab === 2 }"
          @click="professionStageTab = 2"
        >
          Этап 2
        </button>
      </div>

      <div class="ach_subtabs" v-if="activeTab === 'production'">
        <button
          type="button"
          class="ach_subtab"
          :class="{ active: productionStageTab === 1 }"
          @click="productionStageTab = 1"
        >
          Этап 1
        </button>
        <button
          type="button"
          class="ach_subtab"
          :class="{ active: productionStageTab === 2 }"
          @click="productionStageTab = 2"
        >
          Этап 2
        </button>
      </div>

      <div class="empty_text" v-if="!filteredItems.length">
        {{ emptyTabText }}
      </div>
      <div class="grid" v-else>
        <AchievementCard
          v-for="item in filteredItems"
          :key="item.code"
          :title="item.title"
          :description="item.description || ''"
          :icon="item.icon || '🏅'"
          :progress="item.progress"
          :target="nextTarget(item)"
          :rank-index="rankIndex(item)"
          :level-thresholds="levelThresholds(item)"
          :levels="item.levels || []"
          :claimed-threshold="item.claimed_threshold || 0"
          :claimable="item.next_claimable_threshold > 0"
          :locked="item.max_unlocked_threshold <= 0"
          :all-claimed="isAllClaimed(item)"
          :claiming="claimingCode === item.code"
          @claim="claim(item.code)"
        />
      </div>
    </div>

    <AchievementRewardToast
      :visible="rewardToast.visible"
      :title="rewardToast.title"
      :threshold="rewardToast.threshold"
      :reward="rewardToast.reward"
      @close="closeRewardToast"
    />
  </div>
</template>

<script>
import { apiActions } from '@/api/bitrixClient';
import { mapState } from 'vuex';
import AchievementCard from '@/components/achievement/AchievementCard.vue';
import AchievementRewardToast from '@/components/achievement/AchievementRewardToast.vue';
import { hasAchievementReward } from '@/utils/formatAchievementReward';

export default {
  name: 'ProfileAchievementBlock',
  components: { AchievementCard, AchievementRewardToast },
  data() {
    return {
      loading: false,
      loaded: false,
      error: '',
      items: [],
      claimingCode: '',
      activeTab: 'general',
      professionStageTab: 1,
      productionStageTab: 1,
      rewardToast: {
        visible: false,
        title: '',
        threshold: 0,
        reward: {},
      },
    };
  },
  computed: {
    ...mapState('auth', ['authData']),
    generalItems() {
      return this.items.filter((item) => item.group === 'welcome');
    },
    footballItems() {
      return this.items.filter((item) => {
        const group = item.group || '';
        return group !== 'welcome'
          && group !== 'profession'
          && group !== 'production'
          && group !== 'potion'
          && group !== 'exchange'
          && group !== 'collection';
      });
    },
    professionItems() {
      return this.items.filter((item) => item.group === 'profession');
    },
    productionItems() {
      return this.items.filter((item) => item.group === 'production');
    },
    potionItems() {
      return this.items.filter((item) => item.group === 'potion');
    },
    exchangeItems() {
      return this.items.filter((item) => item.group === 'exchange');
    },
    generalCount() {
      return this.generalItems.length;
    },
    footballCount() {
      return this.footballItems.length;
    },
    professionCount() {
      return this.professionItems.length;
    },
    productionCount() {
      return this.productionItems.length;
    },
    potionCount() {
      return this.potionItems.length;
    },
    exchangeCount() {
      return this.exchangeItems.length;
    },
    emptyTabText() {
      switch (this.activeTab) {
        case 'general':
          return 'Пока нет общих ачивок';
        case 'profession':
          return 'Пока нет ачивок профессий';
        case 'production':
          return 'Пока нет ачивок производства';
        case 'potion':
          return 'Пока нет ачивок за зелья';
        case 'exchange':
          return 'Пока нет биржевых ачивок';
        default:
          return 'Пока нет футбольных ачивок';
      }
    },
    filteredItems() {
      let list = this.generalItems;
      if (this.activeTab === 'football') {
        list = this.footballItems;
      } else if (this.activeTab === 'profession') {
        list = this.professionItems.filter((item) => Number(item.profession_stage || 1) === this.professionStageTab);
      } else if (this.activeTab === 'production') {
        list = this.productionItems.filter((item) => Number(item.profession_stage || 1) === this.productionStageTab);
      } else if (this.activeTab === 'potion') {
        list = this.potionItems;
      } else if (this.activeTab === 'exchange') {
        list = this.exchangeItems;
      }
      return this.sortAchievementItems(list);
    },
  },
  created() {
    this.load();
  },
  methods: {
    achievementSortRank(item) {
      if (item.next_claimable_threshold > 0) {
        return 0;
      }
      if (this.isAllClaimed(item)) {
        return 2;
      }
      return 1;
    },
    sortAchievementItems(items) {
      return items
        .map((item, index) => ({ item, index }))
        .sort((a, b) => {
          const rankDiff = this.achievementSortRank(a.item) - this.achievementSortRank(b.item);
          if (rankDiff !== 0) {
            return rankDiff;
          }
          return a.index - b.index;
        })
        .map(({ item }) => item);
    },
    levelThresholds(item) {
      return (item.levels || []).map((l) => l.threshold);
    },
    nextTarget(item) {
      const levels = item.levels || [];
      const claimed = item.claimed_threshold || 0;

      if (item.next_claimable_threshold > 0) {
        return item.next_claimable_threshold;
      }

      for (let i = 0; i < levels.length; i++) {
        const t = levels[i].threshold;
        if (t > claimed && item.progress < t) {
          return t;
        }
      }

      for (let i = 0; i < levels.length; i++) {
        const t = levels[i].threshold;
        if (item.progress < t) {
          return t;
        }
      }

      return levels.length ? levels[levels.length - 1].threshold : 1;
    },
    rankIndex(item) {
      const levels = item.levels || [];
      if (!levels.length) {
        return 0;
      }
      if (item.next_claimable_threshold > 0) {
        const idx = levels.findIndex((l) => l.threshold === item.next_claimable_threshold);
        if (idx >= 0) {
          return idx;
        }
      }
      let claimedIdx = 0;
      levels.forEach((l, i) => {
        if (item.claimed_threshold >= l.threshold) {
          claimedIdx = i;
        }
      });
      if (item.claimed_threshold > 0) {
        return claimedIdx;
      }
      for (let i = 0; i < levels.length; i++) {
        if (item.progress < levels[i].threshold) {
          return i;
        }
      }
      return levels.length - 1;
    },
    isAllClaimed(item) {
      const levels = item.levels || [];
      if (!levels.length) {
        return false;
      }
      const last = levels[levels.length - 1].threshold;
      return item.claimed_threshold >= last;
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
        if (!this.generalCount && this.footballCount) {
          this.activeTab = 'football';
        }
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
      const itemBefore = this.items.find((item) => item.code === code);
      try {
        const data = await apiActions.game.claimAchievement(this.authData.token, code);
        this.items = data.achievements?.items || this.items;
        if (data.game) {
          this.$store.commit('auth/setUserInfo', {
            ...this.$store.state.auth.userInfo,
            game_info: data.game,
          });
        }
        this.showRewardToast(data.claimed, itemBefore);
      } catch (e) {
        this.error = e.message || 'Не удалось забрать награду';
      } finally {
        this.claimingCode = '';
      }
    },
    showRewardToast(claimed, itemBefore) {
      if (!claimed) {
        return;
      }

      const reward = claimed.reward || {};
      if (!hasAchievementReward(reward)) {
        return;
      }

      const title = itemBefore?.title
        || this.items.find((item) => item.code === claimed.code)?.title
        || claimed.code
        || '';

      this.rewardToast = {
        visible: true,
        title,
        threshold: Number(claimed.threshold || 0),
        reward,
      };
    },
    closeRewardToast() {
      this.rewardToast = {
        ...this.rewardToast,
        visible: false,
      };
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

.ach_tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 10px;
}

.ach_tab {
  background: @darkbg;
  color: @colorText;
  border: 1px solid transparent;
  border-radius: 4px;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;

  &.active {
    background: @orange;
    color: #fff;
  }
}

.ach_tab_count {
  font-weight: 400;
  opacity: 0.9;
}

.ach_subtabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 10px;
}

.ach_subtab {
  background: rgba(255, 255, 255, 0.06);
  color: @colorText;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 4px;
  padding: 5px 10px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;

  &.active {
    background: rgba(255, 140, 0, 0.22);
    border-color: @orange;
    color: #fff;
  }
}

.loading,
.empty_text {
  font-size: 13px;
  color: @colorBlur;
  text-align: center;
  padding: 12px;
}

.grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px 8px;
  overflow: visible;
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
