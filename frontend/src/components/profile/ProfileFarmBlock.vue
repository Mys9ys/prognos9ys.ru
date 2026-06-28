<template>
  <div class="farm_block">
    <div class="msg error" v-if="error">{{ error }}</div>
    <div class="msg ok" v-if="message">{{ message }}</div>

    <PreLoader v-if="loading && !farm" />

    <template v-else-if="farm">
      <div class="farm_tabs" v-if="showFarmTabs">
        <button
          type="button"
          class="farm_tab"
          :class="{ active: activeFarmTab === 'professions' }"
          @click="activeFarmTab = 'professions'"
        >Профессии</button>
        <button
          type="button"
          class="farm_tab"
          :class="{ active: activeFarmTab === 'work' }"
          @click="activeFarmTab = 'work'"
        >Работа</button>
      </div>

      <div
        class="section"
        v-if="(activeFarmTab === 'professions' || farm.slots?.needs_pick) && showProfessionSection"
      >
        <div class="section_title">Мои профессии</div>
        <p class="hint" v-if="farm.slots?.needs_pick">
          Выберите {{ pickMin }}–{{ pickMax }} профессии (слотов всего: {{ farm.slots?.max || 2 }}).
          Можно начать с одной и добавить ещё позже.
        </p>
        <p class="hint" v-else-if="farm.slots?.can_add_profession">
          Свободных слотов: {{ farm.slots?.available || 0 }} из {{ farm.slots?.max || 2 }}.
          Отметьте до {{ pickMax }} {{ pickMax === 1 ? 'профессии' : 'профессий' }}.
        </p>
        <p class="hint" v-else-if="farm.slots?.slots_full">
          Все слоты заняты ({{ farm.slots?.used || 0 }}/{{ farm.slots?.max || 2 }}).
          Дополнительный слот — позже с сертификатом из сундука.
        </p>
        <p class="hint" v-else-if="farm.economy?.profession_level_cap">
          Уровень профессии не выше вашего ({{ farm.economy.profession_level_cap }}).
          Прокачивайте уровень игрока через прогнозы и банки опыта.
        </p>

        <div class="prof_cards">
          <div
            v-for="card in professionCards"
            :key="card.code"
            class="prof_card"
            :class="{
              owned: card.owned,
              selected: card.selected,
              muted: card.muted,
            }"
            @click="onProfessionCardClick(card)"
          >
            <div class="prof_card_head">
              <div class="prof_card_info">
                <span class="pick_label">{{ card.label }}</span>
                <span class="pick_meta">{{ card.output_label }} · {{ card.premium_label }}</span>
              </div>
              <span v-if="card.locked" class="prof_locked" title="Нет свободных слотов">🔒</span>
              <label
                v-if="card.pickable"
                class="prof_check"
                :class="{ disabled: card.checkboxDisabled }"
                @click.stop
              >
                <input
                  type="checkbox"
                  :value="card.code"
                  v-model="pickCodes"
                  :disabled="card.checkboxDisabled"
                >
              </label>
            </div>
            <div class="prof_card_stats">
              <div class="prof_xp_row">
                <span>
                  ур. {{ card.level }}<span v-if="card.is_capped" class="cap_hint"> / {{ card.level_cap }}</span>
                </span>
                <span class="xp_val" v-if="card.owned">{{ formatXpProgress(card) }}</span>
              </div>
              <div class="prof_progress_track" v-if="card.owned">
                <div
                  class="prof_progress_fill"
                  :style="{ width: Math.min(100, Math.max(0, card.progress_percent)) + '%' }"
                />
              </div>
              <div class="prof_yield" v-if="card.owned">
                <span class="yield_label">Добыто:</span>
                <span class="yield_item">
                  <strong>{{ card.normal_yield }}</strong>
                  <span class="yield_emoji">{{ card.output_emoji }}</span>
                  {{ card.output_label }}
                </span>
                <span class="yield_sep">·</span>
                <span class="yield_item" :class="{ highlight: card.premium_yield > 0 }">
                  <strong>{{ card.premium_yield }}</strong>
                  <span class="yield_emoji">{{ card.premium_emoji }}</span>
                  {{ card.premium_label }}<span class="premium_badge"> ★</span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <button
          v-if="showPickButton"
          class="btn"
          :disabled="actionLoading || !canSavePick"
          @click="onPickProfessions"
        >
          {{ farm.slots?.needs_pick ? 'Сохранить выбор' : 'Добавить' }}
        </button>
      </div>

      <template v-if="!farm.slots?.needs_pick && activeFarmTab === 'work'">
        <div class="section" v-if="farm.session">
          <div class="section_title">Смена</div>
          <p class="hint">
            {{ farm.session.profession_label }}
            · {{ workModeLabel(farm.session.work_mode) }}
            · {{ farm.session.iterations_total }} {{ cycleLabel(farm.session.iterations_total) }} за смену
          </p>
          <p class="timer" v-if="countdownSeconds > 0">
            До конца смены: {{ formatTimer(countdownSeconds) }}
          </p>
          <p class="last_result" v-if="farm.session.last_result?.message">{{ farm.session.last_result.message }}</p>
          <div
            v-if="farm.session.last_result?.profession_level_rewards?.length"
            class="level_rewards"
          >
            <div
              v-for="(reward, idx) in farm.session.last_result.profession_level_rewards"
              :key="idx"
            >
              {{ formatProfessionLevelReward(reward) }}
            </div>
          </div>
          <button class="btn secondary" :disabled="actionLoading" @click="onCancelWork">Остановить смену</button>
        </div>

        <div class="section shift_done" v-else-if="farm.last_shift?.last_result?.message">
          <div class="section_title">Смена завершена</div>
          <p class="hint">
            {{ farm.last_shift.profession_label }}
            · {{ workModeLabel(farm.last_shift.work_mode) }}
            · {{ farm.last_shift.iterations_total }} {{ cycleLabel(farm.last_shift.iterations_total) }}
          </p>
          <p class="last_result">{{ farm.last_shift.last_result.message }}</p>
          <div
            v-if="farm.last_shift.last_result?.profession_level_rewards?.length"
            class="level_rewards"
          >
            <div
              v-for="(reward, idx) in farm.last_shift.last_result.profession_level_rewards"
              :key="idx"
            >
              {{ formatProfessionLevelReward(reward) }}
            </div>
          </div>
        </div>

        <div class="section" v-else>
          <div class="section_title">Начать работу</div>
          <select v-model="selectedProfession" class="field_select">
            <option v-for="p in farm.professions" :key="p.code" :value="p.code">{{ p.label }}</option>
          </select>
          <div class="work_mode_tabs">
            <button
              type="button"
              class="work_mode_tab"
              :class="{ active: workMode === 'treasury' }"
              @click="workMode = 'treasury'"
            >На казну (+{{ farm.economy?.pay_treasury }} 🪙)</button>
            <button
              type="button"
              class="work_mode_tab"
              :class="{ active: workMode === 'self' }"
              @click="workMode = 'self'"
            >Для себя (−{{ farm.economy?.fee_self }} 🪙)</button>
          </div>
          <div class="iter_row">
            <span class="iter_label">Циклов за смену</span>
            <div class="iter_picker">
              <button
                v-for="n in iterationOptions"
                :key="n"
                type="button"
                class="iter_btn"
                :class="{ active: selectedIterations === n }"
                @click="selectedIterations = n"
              >{{ n }}</button>
            </div>
          </div>
          <p class="hint">
            Один цикл {{ farm.economy?.iteration_minutes }} мин.
            Несколько циклов — одна смена: таймер до конца, расчёт и выплата разом.
            На казну — 1 ресурс за цикл и базовая оплата; комбо и премиум остаются вам.
          </p>
          <button class="btn" :disabled="actionLoading || !selectedProfession" @click="onStartWork">
            Начать смену
          </button>
        </div>

        <div class="section" v-if="farm.materials?.length">
          <div class="section_title">Материалы</div>
          <div class="mat_row" v-for="m in farm.materials" :key="m.code + (m.is_premium ? 'p' : '')">
            <span class="mat_label">
              <span class="mat_emoji">{{ m.emoji || '📦' }}</span>
              {{ m.label }}<span v-if="m.is_premium" class="premium_badge"> ★</span>
            </span>
            <span>{{ m.qty }}</span>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import PreLoader from '@/components/main/PreLoader';
import { apiActions } from '@/api/bitrixClient';

const MATERIAL_EMOJI = {
  log: '🪵',
  stone: '🪨',
  ore: '⛏️',
  sand: '🏖️',
  cotton: '🧵',
  amber: '🟠',
  marble: '⚪',
  gold_nugget: '🥇',
  quartz: '🔮',
  silk: '🎀',
  plank: '🪚',
  block: '🧱',
  ingot: '🔩',
  glass: '🫙',
  cloth: '🧶',
  fine_plank: '🌲',
  fine_block: '🏛️',
  fine_ingot: '✨',
  fine_glass: '🥂',
  fine_cloth: '👑',
};

function materialEmoji(code) {
  return MATERIAL_EMOJI[code] || '📦';
}

export default {
  name: 'ProfileFarmBlock',
  components: { PreLoader },
  data() {
    return {
      loading: false,
      actionLoading: false,
      farm: null,
      pickCodes: [],
      selectedProfession: '',
      workMode: 'treasury',
      selectedIterations: 1,
      activeFarmTab: 'professions',
      error: '',
      message: '',
      pollTimer: null,
      countdownSeconds: 0,
      countdownTimer: null,
      tickRefreshInFlight: false,
      lastCompletedShiftId: 0,
    };
  },
  computed: {
    ...mapState({
      authData: state => state.auth.authData,
    }),
    pickMin() {
      return Number(this.farm?.slots?.pick_min) || 1;
    },
    pickMax() {
      return Number(this.farm?.slots?.pick_max) || Number(this.farm?.slots?.pick_count) || 2;
    },
    canSavePick() {
      const count = this.pickCodes.length;
      return count >= this.pickMin && count <= this.pickMax;
    },
    showProfessionSection() {
      return Boolean(
        this.farm?.slots?.needs_pick
        || (this.farm?.professions?.length)
        || this.farm?.slots?.can_add_profession
        || (this.farm?.catalog?.length),
      );
    },
    showPickButton() {
      return Boolean(this.farm?.slots?.needs_pick || this.farm?.slots?.can_add_profession);
    },
    showFarmTabs() {
      return !this.farm?.slots?.needs_pick;
    },
    iterationOptions() {
      const max = Math.min(5, Number(this.farm?.economy?.max_iterations) || 5);
      return Array.from({ length: max }, (_, i) => i + 1);
    },
    professionCards() {
      const ownedMap = {};
      (this.farm?.professions || []).forEach((p) => {
        ownedMap[p.code] = p;
      });

      const canPick = Boolean(this.farm?.slots?.needs_pick || this.farm?.slots?.can_add_profession);
      const levelCap = Number(this.farm?.economy?.profession_level_cap) || 0;

      return (this.farm?.catalog || []).map((item) => {
        const owned = ownedMap[item.code];
        const pickable = !owned && canPick;
        const selected = this.pickCodes.includes(item.code);
        const atPickLimit = this.pickCodes.length >= this.pickMax;
        const checkboxDisabled = pickable && !selected && atPickLimit;
        const slotsFull = Boolean(this.farm?.slots?.slots_full);

        return {
          code: item.code,
          label: item.label,
          output_label: item.output_label,
          premium_label: item.premium_label,
          owned: Boolean(owned),
          pickable,
          selected,
          checkboxDisabled,
          muted: (pickable && checkboxDisabled) || (!owned && slotsFull),
          locked: !owned && slotsFull,
          level: owned?.level ?? 0,
          level_cap: owned?.level_cap ?? levelCap,
          is_capped: owned?.is_capped ?? false,
          xp: owned?.xp ?? 0,
          progress_percent: owned?.progress_percent ?? 0,
          xp_in_level: owned?.xp_in_level ?? 0,
          xp_level_total: owned?.xp_level_total ?? null,
          next_profession_level: owned?.next_profession_level ?? null,
          normal_yield: owned?.normal_yield ?? 0,
          premium_yield: owned?.premium_yield ?? 0,
          output_emoji: owned?.output_emoji || materialEmoji(item.output),
          premium_emoji: owned?.premium_emoji || materialEmoji(item.premium),
        };
      });
    },
  },
  created() {
    this.refresh();
  },
  beforeUnmount() {
    this.clearPoll();
    this.clearCountdown();
  },
  methods: {
    ...mapActions('auth', ['refreshGameInfo']),

    async refresh(silent = false) {
      const token = this.authData?.token;
      if (!token) {
        return;
      }

      if (!silent) {
        this.loading = true;
      }
      this.error = '';

      try {
        const data = await apiActions.game.getFarmState(token);
        if (data?.status === 'ok') {
          this.farm = data.farm || null;
          const completedId = Number(this.farm?.last_shift?.session_id ?? 0);
          if (silent && completedId > 0 && completedId !== this.lastCompletedShiftId) {
            this.lastCompletedShiftId = completedId;
            this.refreshGameInfo().catch(() => {});
          }
          if (!this.selectedProfession && this.farm?.professions?.length) {
            this.selectedProfession = this.farm.professions[0].code;
          }
          if (this.farm?.slots?.needs_pick) {
            this.activeFarmTab = 'professions';
          } else if (this.farm?.session) {
            this.activeFarmTab = 'work';
          }
          this.schedulePoll();
          this.syncCountdown();
        } else {
          this.error = data?.message || 'Не удалось загрузить фарм';
        }
      } catch (e) {
        this.error = e.message || 'Не удалось загрузить фарм';
      } finally {
        if (!silent) {
          this.loading = false;
        }
      }
    },

    schedulePoll() {
      this.clearPoll();
      const seconds = Number(this.farm?.session?.seconds_left ?? 0);
      if (seconds > 0 && !this.tickRefreshInFlight) {
        const delay = Math.min(Math.max(seconds * 1000 + 500, 3000), 300000);
        this.pollTimer = window.setTimeout(() => this.requestTickRefresh(), delay);
      }
    },

    clearPoll() {
      if (this.pollTimer) {
        window.clearTimeout(this.pollTimer);
        this.pollTimer = null;
      }
    },

    syncCountdown() {
      this.clearCountdown();
      const seconds = Number(this.farm?.session?.seconds_left ?? 0);
      this.countdownSeconds = Math.max(0, seconds);
      if (this.countdownSeconds <= 0) {
        return;
      }

      this.countdownTimer = window.setInterval(() => {
        if (this.countdownSeconds <= 1) {
          this.countdownSeconds = 0;
          this.clearCountdown();
          this.clearPoll();
          this.requestTickRefresh();
          return;
        }
        this.countdownSeconds -= 1;
      }, 1000);
    },

    requestTickRefresh() {
      if (this.tickRefreshInFlight) {
        return;
      }

      this.tickRefreshInFlight = true;
      this.refresh(true).finally(() => {
        this.tickRefreshInFlight = false;
      });
    },

    clearCountdown() {
      if (this.countdownTimer) {
        window.clearInterval(this.countdownTimer);
        this.countdownTimer = null;
      }
    },

    workModeLabel(mode) {
      return mode === 'self' ? 'для себя' : 'на казну';
    },

    cycleLabel(count) {
      const n = Number(count) || 0;
      const mod10 = n % 10;
      const mod100 = n % 100;
      if (mod100 >= 11 && mod100 <= 14) {
        return 'циклов';
      }
      if (mod10 === 1) {
        return 'цикл';
      }
      if (mod10 >= 2 && mod10 <= 4) {
        return 'цикла';
      }
      return 'циклов';
    },

    formatTimer(seconds) {
      const s = Math.max(0, Number(seconds) || 0);
      const m = Math.floor(s / 60);
      const r = s % 60;
      return `${m}:${String(r).padStart(2, '0')}`;
    },

    formatXp(value) {
      const n = Number(value) || 0;
      return Number.isInteger(n) ? String(n) : n.toFixed(1);
    },

    formatXpProgress(card) {
      if (card.is_capped) {
        return 'макс.';
      }
      if (!card.xp_level_total) {
        return `${this.formatXp(card.xp)} XP`;
      }
      const current = Math.max(0, Number(card.xp_in_level) || 0);
      const total = Number(card.xp_level_total);
      return `${this.formatXp(current)} из ${this.formatXp(total)}`;
    },

    onProfessionCardClick(card) {
      if (!card.pickable || card.checkboxDisabled) {
        return;
      }
      const idx = this.pickCodes.indexOf(card.code);
      if (idx >= 0) {
        this.pickCodes.splice(idx, 1);
      } else if (this.pickCodes.length < this.pickMax) {
        this.pickCodes.push(card.code);
      }
    },

    formatProfessionLevelReward(reward) {
      if (!reward) {
        return '';
      }
      const bits = [`${reward.profession_label || 'Профессия'} ур. ${reward.level}`];
      if (Number(reward.prognobaks) > 0) {
        bits.push(`+${reward.prognobaks} 🪙`);
      }
      if (Number(reward.rublius) > 0) {
        bits.push(`+${reward.rublius} 💎`);
      }
      if (Number(reward.material_qty) > 0) {
        bits.push(`+${reward.material_qty} ${reward.material_label || 'рес.'}`);
      }
      if (Number(reward.chests) > 0) {
        bits.push(`+${reward.chests} сунд. проф.`);
      }
      if (reward.title) {
        bits.push(reward.title);
      }
      return bits.join(' · ');
    },

    async onPickProfessions() {
      const token = this.authData?.token;
      if (!token || !this.canSavePick) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        const data = await apiActions.game.pickFarmProfessions(token, this.pickCodes);
        if (data?.status === 'ok') {
          this.farm = data.farm;
          this.pickCodes = [];
          this.message = this.farm?.slots?.can_add_profession
            ? 'Профессия добавлена'
            : 'Профессии выбраны';
          if (this.farm?.professions?.length) {
            this.selectedProfession = this.farm.professions[0].code;
          }
        } else {
          this.error = data?.message || 'Ошибка выбора';
        }
      } catch (e) {
        this.error = e.message || 'Ошибка выбора';
      } finally {
        this.actionLoading = false;
      }
    },

    async onStartWork() {
      const token = this.authData?.token;
      if (!token || !this.selectedProfession) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        const data = await apiActions.game.startFarmWork(
          token,
          this.selectedProfession,
          this.workMode,
          this.selectedIterations,
        );
        if (data?.status === 'ok') {
          this.farm = data.farm;
          this.message = 'Смена начата';
          this.activeFarmTab = 'work';
          this.schedulePoll();
          this.syncCountdown();
        } else {
          this.error = data?.message || 'Не удалось начать смену';
        }
      } catch (e) {
        this.error = e.message || 'Не удалось начать смену';
      } finally {
        this.actionLoading = false;
      }
    },

    async onCancelWork() {
      const token = this.authData?.token;
      if (!token) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        const data = await apiActions.game.cancelFarmWork(token);
        if (data?.status === 'ok') {
          this.farm = data.farm;
          this.message = 'Смена остановлена';
          this.clearPoll();
          this.clearCountdown();
          this.countdownSeconds = 0;
        } else {
          this.error = data?.message || 'Ошибка';
        }
      } catch (e) {
        this.error = e.message || 'Ошибка';
      } finally {
        this.actionLoading = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.farm_block {
  text-align: left;
}

.farm_tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 10px;
}

.farm_tab {
  flex: 1;
  background: @darkbg;
  color: @colorText;
  border: 1px solid transparent;
  border-radius: 4px;
  padding: 8px 12px;
  font-size: 12px;
  cursor: pointer;

  &.active {
    background: @orange;
    color: #fff;
  }
}

.shift_done {
  .last_result {
    margin-top: 4px;
  }
}

.section {
  .shadow_inset;
  padding: 8px;
  border-radius: 4px;
  margin-bottom: 10px;
}

.section_title {
  font-size: 13px;
  color: @orange;
  margin-bottom: 6px;
}

.hint {
  font-size: 12px;
  color: @colorBlur;
  line-height: 1.35;
  margin: 0 0 8px;
}

.prof_cards {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 8px;
}

.prof_card {
  padding: 8px 10px;
  border-radius: 4px;
  background: fade(@darkbg, 60%);
  border: 1px solid transparent;
  cursor: default;

  &.owned {
    border-color: fade(@orange, 35%);
  }

  &.selected {
    border-color: @orange;
    outline: 1px solid fade(@orange, 40%);
  }

  &.muted {
    opacity: 0.45;
  }

  &:not(.owned):not(.muted) {
    cursor: pointer;
  }
}

.prof_card_head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 6px;
}

.prof_card_info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.prof_check {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  margin: 0;
  cursor: pointer;

  &.disabled {
    opacity: 0.35;
    cursor: default;
  }

  input {
    width: 18px;
    height: 18px;
    margin: 0;
  }
}

.prof_locked {
  flex-shrink: 0;
  font-size: 14px;
  line-height: 1;
  opacity: 0.55;
}

.prof_card_stats {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.prof_xp_row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  color: @colorBlur;
}

.xp_val {
  color: @colorText;
}

.prof_progress_track {
  height: 6px;
  border-radius: 3px;
  background: fade(@darkbg, 90%);
  overflow: hidden;
}

.prof_progress_fill {
  height: 100%;
  background: linear-gradient(90deg, fade(@orange, 70%), @orange);
  border-radius: 3px;
  transition: width 0.25s ease;
}

.prof_yield {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: @colorBlur;

  strong {
    color: @colorText;
    font-weight: 600;
  }
}

.yield_label {
  margin-right: 2px;
}

.yield_item {
  display: inline-flex;
  align-items: center;
  gap: 3px;

  &.highlight {
    color: @yellow;

    strong {
      color: @yellow;
    }
  }
}

.yield_emoji {
  font-size: 13px;
  line-height: 1;
}

.yield_sep {
  opacity: 0.5;
}

.pick_label {
  font-size: 13px;
  color: @colorText;
}

.pick_meta {
  font-size: 11px;
  color: @colorBlur;
}

.mat_row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  padding: 3px 0;
}

.mat_label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.mat_emoji {
  font-size: 16px;
  line-height: 1;
}

.mat_row span:last-child {
  color: @colorBlur;
}

.cap_hint {
  color: @yellow;
}

.field_select {
  width: 100%;
  margin-bottom: 8px;
  background: @darkbg;
  color: @colorText;
  border: 1px solid fade(@colorBlur, 40%);
  border-radius: 4px;
  padding: 6px 8px;
  font-size: 12px;
}

.work_mode_tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}

.work_mode_tab {
  flex: 1;
  min-width: 120px;
  background: @darkbg;
  color: @colorText;
  border: 1px solid transparent;
  border-radius: 4px;
  padding: 7px 8px;
  font-size: 11px;
  cursor: pointer;

  &.active {
    background: @orange;
    color: #fff;
  }
}

.iter_row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.iter_label {
  font-size: 12px;
  color: @colorBlur;
  flex-shrink: 0;
}

.iter_picker {
  display: flex;
  gap: 4px;
}

.iter_btn {
  min-width: 32px;
  height: 32px;
  padding: 0 8px;
  background: @darkbg;
  color: @colorText;
  border: 1px solid transparent;
  border-radius: 4px;
  font-size: 12px;
  cursor: pointer;

  &.active {
    background: @orange;
    color: #fff;
  }
}

.timer {
  font-size: 13px;
  color: @yellow;
  margin: 0 0 8px;
}

.last_result {
  font-size: 12px;
  color: @colorText;
  margin: 0 0 8px;
}

.level_rewards {
  font-size: 12px;
  color: @yellow;
  margin: 0 0 8px;
  line-height: 1.4;
}

.premium_badge {
  color: @yellow;
}

.btn {
  background: @orange;
  color: #fff;
  border: none;
  border-radius: 4px;
  padding: 7px 12px;
  font-size: 12px;
  cursor: pointer;

  &.secondary {
    background: fade(@colorBlur, 50%);
  }

  &:disabled {
    opacity: 0.5;
    cursor: default;
  }
}

.msg {
  font-size: 12px;
  padding: 6px;
  border-radius: 4px;
  margin-bottom: 8px;

  &.error {
    background: rgba(200, 60, 60, 0.2);
    color: #f88;
  }

  &.ok {
    background: rgba(60, 160, 80, 0.2);
    color: #8f8;
  }
}
</style>
