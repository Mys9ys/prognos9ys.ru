<template>
  <div class="section_card season_awards" v-if="visible">
    <div class="sa_head">
      <div class="sa_title">
        Сезон ЧМ-26
        <span v-if="pendingCount" class="sa_pending">{{ pendingCount }}</span>
      </div>
      <button
        v-if="pendingCount > 1"
        type="button"
        class="sa_claim_all"
        :disabled="claimingAll || !!claimingId"
        @click="claimAll"
      >
        {{ claimingAll ? '…' : 'Забрать все' }}
      </button>
    </div>

    <div class="msg error" v-if="error">{{ error }}</div>
    <div class="loading" v-if="loading && !loaded">Загрузка…</div>

    <div class="sa_list" v-if="loaded && awards.length">
      <div
        v-for="item in awards"
        :key="item.id"
        class="sa_card"
        :class="{ claimed: !item.claimable, place1: item.place === 1, place2: item.place === 2, place3: item.place === 3 }"
      >
        <div class="sa_badge">{{ item.badge?.icon || placeIcon(item.place) }}</div>
        <div class="sa_body">
          <div class="sa_name">{{ item.title }}</div>
          <div class="sa_place">{{ placeLabel(item.place) }} · {{ formatScore(item.score) }} очк.</div>
          <div class="sa_reward">
            <span v-if="item.reward?.prognobaks" class="sa_bit">
              <AppIcon name="prognobak" :size="14" />
              {{ formatNum(item.reward.prognobaks) }}
            </span>
            <span v-if="item.reward?.chests" class="sa_bit">
              <AppIcon name="chest_wc2026" :size="14" />
              ×{{ item.reward.chests }}
            </span>
            <span v-if="item.reward?.premium_scroll_days" class="sa_bit">
              📜 {{ item.reward.premium_scroll_days }}д
            </span>
            <span v-if="item.reward?.cup || item.cup" class="sa_bit" :title="item.cup?.label || 'Кубок'">
              {{ item.cup?.icon || '🏆' }} Кубок
            </span>
          </div>
        </div>
        <button
          v-if="item.claimable"
          type="button"
          class="sa_claim"
          :disabled="claimingAll || claimingId === item.id"
          @click="claim(item)"
        >
          {{ claimingId === item.id ? '…' : 'Забрать' }}
        </button>
        <div v-else class="sa_done">✓</div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { apiActions } from '@/api/bitrixClient';
import AppIcon from '@/components/ui/AppIcon.vue';

export default {
  name: 'ProfileSeasonAwardsBlock',
  components: { AppIcon },
  data() {
    return {
      loading: false,
      loaded: false,
      error: '',
      awards: [],
      pendingCount: 0,
      claimingId: 0,
      claimingAll: false,
    };
  },
  computed: {
    ...mapState('auth', ['authData']),
    visible() {
      return this.loaded && this.awards.length > 0;
    },
  },
  created() {
    this.load();
  },
  methods: {
    placeIcon(place) {
      if (place === 1) return '🥇';
      if (place === 2) return '🥈';
      if (place === 3) return '🥉';
      return '🏅';
    },
    placeLabel(place) {
      return `${place}-е место`;
    },
    formatScore(score) {
      const n = Number(score) || 0;
      return Number.isInteger(n) ? String(n) : n.toFixed(1);
    },
    formatNum(value) {
      const n = Number(value) || 0;
      return Number.isInteger(n) ? String(n) : n.toFixed(1);
    },
    applyPayload(data) {
      const payload = data?.awards && !Array.isArray(data.awards)
        ? data.awards
        : data;
      this.awards = Array.isArray(payload?.awards) ? payload.awards : [];
      this.pendingCount = Number(payload?.pending_count || 0);
      if (data?.game) {
        this.$store.commit('auth/setUserInfo', {
          ...this.$store.state.auth.userInfo,
          game_info: data.game,
        });
      }
    },
    async load() {
      if (!this.authData?.token) {
        return;
      }
      this.loading = true;
      this.error = '';
      try {
        const data = await apiActions.game.getSeasonAwards(this.authData.token);
        this.applyPayload(data);
        this.loaded = true;
      } catch (e) {
        // HL ещё не установлен / нет наград — блок просто скрыт
        this.awards = [];
        this.pendingCount = 0;
        this.loaded = true;
        if (e?.message && !/HL|не найден|highload/i.test(e.message)) {
          this.error = e.message;
        }
      } finally {
        this.loading = false;
      }
    },
    async claim(item) {
      if (!this.authData?.token || !item?.id) {
        return;
      }
      this.error = '';
      this.claimingId = item.id;
      try {
        const data = await apiActions.game.claimSeasonAward(this.authData.token, {
          awardId: item.id,
        });
        this.applyPayload(data);
      } catch (e) {
        this.error = e.message || 'Не удалось забрать награду';
      } finally {
        this.claimingId = 0;
      }
    },
    async claimAll() {
      if (!this.authData?.token || this.pendingCount <= 0) {
        return;
      }
      this.error = '';
      this.claimingAll = true;
      try {
        const data = await apiActions.game.claimAllSeasonAwards(this.authData.token);
        this.applyPayload(data);
      } catch (e) {
        this.error = e.message || 'Не удалось забрать награды';
      } finally {
        this.claimingAll = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.season_awards {
  text-align: left;
}

.section_card {
  background: @DarkColorBG;
  padding: 8px;
  border-radius: 5px;
  margin-bottom: 8px;
}

.sa_head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.sa_title {
  color: @colorText;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
}

.sa_pending {
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background: @orange;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.sa_claim_all {
  border: 0;
  background: @YesWrite;
  color: #111;
  font-size: 11px;
  font-weight: 700;
  padding: 5px 8px;
  border-radius: 4px;
  cursor: pointer;

  &:disabled {
    opacity: 0.6;
    cursor: default;
  }
}

.sa_list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.sa_card {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  border-radius: 5px;
  background: @colorText2;
  .shadow_inset;

  &.claimed {
    opacity: 0.72;
  }

  &.place1 {
    border-left: 3px solid #d4af37;
  }

  &.place2 {
    border-left: 3px solid #c0c0c0;
  }

  &.place3 {
    border-left: 3px solid #cd7f32;
  }
}

.sa_badge {
  font-size: 22px;
  line-height: 1;
  flex-shrink: 0;
}

.sa_body {
  flex: 1;
  min-width: 0;
}

.sa_name {
  color: @colorText;
  font-size: 13px;
  font-weight: 700;
}

.sa_place {
  color: @colorBlur;
  font-size: 11px;
  margin-top: 1px;
}

.sa_reward {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 4px;
}

.sa_bit {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 11px;
  color: @colorText;
}

.sa_claim {
  flex-shrink: 0;
  border: 0;
  background: @orange;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  padding: 6px 10px;
  border-radius: 4px;
  cursor: pointer;

  &:disabled {
    opacity: 0.6;
    cursor: default;
  }
}

.sa_done {
  flex-shrink: 0;
  color: @YesWrite;
  font-size: 16px;
  font-weight: 700;
  padding: 0 6px;
}

.msg.error {
  color: #e57373;
  font-size: 12px;
  margin-bottom: 6px;
}

.loading {
  color: @colorBlur;
  font-size: 12px;
}
</style>
