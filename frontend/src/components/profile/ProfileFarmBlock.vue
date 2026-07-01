<template>
  <div class="farm_block">
    <div class="msg error" v-if="error">{{ error }}</div>
    <div class="msg ok" v-if="message">{{ message }}</div>

    <PreLoader v-if="loading && !farm" />

    <template v-else-if="farm">
      <div class="farm_tabs" v-if="showFarmTabs" :class="{ farm_tabs_three: showQueueLogTab }">
        <button
          type="button"
          class="farm_tab"
          :class="{ active: activeFarmTab === 'work' }"
          @click="activeFarmTab = 'work'"
        >Запуски</button>
        <button
          type="button"
          class="farm_tab"
          :class="{ active: activeFarmTab === 'professions' }"
          @click="activeFarmTab = 'professions'"
        >Профессии</button>
        <button
          v-if="showQueueLogTab"
          type="button"
          class="farm_tab"
          :class="{ active: activeFarmTab === 'log' }"
          @click="activeFarmTab = 'log'"
        >Журнал</button>
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
              <div class="prof_card_title_row">
                <span class="pick_label">{{ card.label }}</span>
                <span class="pick_meta">{{ professionMetaLine(card) }}</span>
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
            <div v-if="card.owned" class="prof_chances_wrap" @click.stop>
              <button
                type="button"
                class="chances_btn"
                :class="{ open: expandedChancesCode === card.code }"
                @click="toggleChances(card.code)"
              >
                Шансы
                <span class="chances_chevron">{{ expandedChancesCode === card.code ? '▲' : '▼' }}</span>
              </button>
              <div v-if="expandedChancesCode === card.code" class="chances_panel">
                <div class="chances_row">
                  <span>Комбо ×2 за цикл</span>
                  <strong>{{ formatChancePercent(card.combo_x2_percent) }}</strong>
                </div>
                <div class="chances_row">
                  <span>Комбо ×3 за цикл</span>
                  <strong>{{ formatChancePercent(card.combo_x3_percent) }}</strong>
                </div>
                <div class="chances_row">
                  <span>Премиум {{ card.premium_label }}</span>
                  <strong v-if="card.premium_percent > 0">{{ formatChancePercent(card.premium_percent, 3) }}</strong>
                  <strong v-else class="chances_na">с ур. {{ card.premium_min_level || 2 }}</strong>
                </div>
                <p class="chances_hint">{{ chancesHint(card) }}</p>
              </div>
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
                <span class="yield_label">{{ card.type === 'process' ? 'Изготовлено' : 'Добыто' }}:</span>
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
        <div class="section premium_queue_section" v-if="workQueue.premium_active">
          <div class="section_title">Очередь Premium ★</div>
          <p class="hint">
            Задачи идут по одной, офлайн. Не хватает ресурсов — пропуск с записью в журнал.
            Добавить ещё — формы ниже (смена, крафт, сдача добычи). История — вкладка «Журнал».
          </p>
          <p v-if="workQueue.eta_label" class="hint queue_eta">
            Смен в очереди: {{ workQueue.eta_cycles }}
            · ориентировочно {{ workQueue.eta_label }}
          </p>
          <p
            v-if="workQueue.premium_active && Number(farm.queue_projection?.reserved_prognobaks) > 0"
            class="hint"
          >
            Зарезервировано под очередь: {{ formatMoney(farm.queue_projection.reserved_prognobaks) }} 🪙
            · доступно для «для себя»: {{ formatMoney(farm.queue_projection.wallet_available_self_farm) }} 🪙
          </p>

          <div v-if="orderedQueueItems.length" class="queue_list">
            <div
              v-for="item in orderedQueueItems"
              :key="'q' + item.id"
              class="queue_row"
              :class="{ running: item.status === 'active' }"
            >
              <span class="queue_num">{{ item.queuePosition }}</span>
              <span class="queue_label">{{ item.label }}</span>
              <span
                class="queue_status"
                :class="item.status === 'active' ? 'run' : 'wait'"
              >
                {{ item.status === 'active' ? 'выполняется' : 'ожидает' }}
              </span>
              <label
                v-if="item.status !== 'active' && item.task_type === 'exchange_list'"
                class="queue_sell_mode"
              >
                <input
                  type="checkbox"
                  :checked="item.payload?.sell_mode === 'consign'"
                  :disabled="actionLoading"
                  @change="onToggleQueueSellMode(item, $event)"
                />
                комиссия
              </label>
              <button
                v-if="item.status === 'active' && item.task_type === 'farm'"
                type="button"
                class="btn secondary mini"
                :disabled="actionLoading"
                @click="onCancelPremiumWork(item.id)"
              >
                Стоп
              </button>
              <button
                v-else-if="item.status !== 'active'"
                type="button"
                class="btn secondary mini"
                :disabled="actionLoading"
                @click="onCancelPremiumWork(item.id)"
              >
                ✕
              </button>
            </div>
          </div>

          <p
            class="hint"
            v-if="!orderedQueueItems.length"
          >Очередь пуста — добавьте задачи кнопкой «★ В очередь».</p>
        </div>

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

        <div class="section" v-if="showWorkPlannerSection">
          <div class="section_title">
            {{ farm.session && workQueue.premium_active ? 'Добавить в очередь' : 'Начать работу' }}
          </div>
          <p class="hint" v-if="farm.session && workQueue.premium_active">
            Смена идёт — новые задачи встанут в очередь и стартуют после неё.
          </p>
          <select v-model="selectedProfession" class="field_select">
            <option v-for="p in farm.professions" :key="p.code" :value="p.code">{{ p.label }}</option>
          </select>
          <div class="work_mode_tabs">
            <button
              type="button"
              class="work_mode_tab"
              :class="{ active: workMode === 'self' }"
              @click="workMode = 'self'"
            >Для себя (−{{ farm.economy?.fee_self }} 🪙/цикл, после смены)</button>
            <button
              type="button"
              class="work_mode_tab"
              :class="{ active: workMode === 'treasury', disabled: !treasuryModeAvailable }"
              :disabled="!treasuryModeAvailable"
              :title="treasuryModeTitle"
              @click="selectTreasuryMode"
            >На казну (+{{ farm.economy?.pay_treasury }} 🪙)</button>
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
                :disabled="n > maxIterationsForWork"
                @click="selectedIterations = n"
              >{{ n }}</button>
            </div>
          </div>
          <p class="hint" v-if="selectedProfessionDef?.type === 'process' && selectedProfessionDef?.input">
            Крафт 1:1 — на {{ selectedIterations }}
            {{ cycleLabel(selectedIterations) }}
            нужно {{ selectedIterations }} {{ selectedProfessionDef.input_label }}
            <template v-if="workMode === 'self'">
              в инвентаре (есть: {{ craftInputAvailable }}).
            </template>
            <template v-else>
              на госскладе (есть: {{ craftInputAvailable }}).
            </template>
          </p>
          <p class="hint">{{ workModeHint }}</p>
          <div class="work_actions_row">
            <button
              v-if="!farm.session"
              type="button"
              class="btn"
              :disabled="actionLoading || !selectedProfession || maxIterationsForWork <= 0"
              @click="onStartWork"
            >
              Начать смену
            </button>
            <button
              v-if="workQueue.premium_active"
              type="button"
              class="btn secondary"
              :disabled="actionLoading || !selectedProfession || maxIterationsForWork <= 0"
              @click="onEnqueueFarmWork"
            >
              ★ В очередь
            </button>
            <button
              v-if="workQueue.premium_active && selectedProfession"
              type="button"
              class="btn macro"
              :disabled="actionLoading || !selectedProfession || macroOutputQty <= 0"
              @click="onEnqueueProfessionMacro"
            >
              {{ professionMacroLabel }}
            </button>
          </div>
        </div>

        <div class="section" v-if="showAlbumCraftSection">
          <div class="section_title">Крафт альбомов</div>
          <p class="hint">
            {{ farm.album_craft.plank_need }} доски + {{ farm.album_craft.cloth_need }} ткани →
            {{ farm.album_craft.output_count }} альбома (из инвентаря)
          </p>
          <p class="hint">
            На складе: доска {{ farm.album_craft.plank_have ?? 0 }},
            ткань {{ farm.album_craft.cloth_have ?? 0 }}
          </p>
          <p class="hint">
            Опыт профессии «{{ albumCraftProfessionLabel }}»: +{{ farm.album_craft.xp_gain }}
          </p>
          <div v-if="workQueue.premium_active" class="album_macro_controls">
            <label class="macro_batches">
              Партий за клик:
              <select v-model.number="albumMacroBatches" :disabled="actionLoading">
                <option v-for="n in 5" :key="'ab' + n" :value="n">{{ n }}</option>
              </select>
            </label>
            <p class="hint">
              Макрос: добыча/крафт материалов (для себя) → {{ albumMacroBatches }}
              {{ albumMacroBatches === 1 ? 'крафт' : 'крафта' }}
              → {{ albumMacroOutputQty }} альбома на биржу
            </p>
            <label class="macro_consign">
              <input
                v-model="albumMacroConsign"
                type="checkbox"
                :disabled="actionLoading"
              />
              Сдать в комиссию банка (иначе — листинг на бирже)
            </label>
          </div>
          <div class="work_actions_row">
            <button
              v-if="!farm.session"
              type="button"
              class="btn"
              :disabled="actionLoading || !farm.album_craft.can_craft || !albumCraftProfessionCode"
              @click="onCraftAlbums"
            >
              Скрафтить альбомы
            </button>
            <button
              v-if="workQueue.premium_active"
              type="button"
              class="btn secondary"
              :disabled="actionLoading || !albumCraftProfessionCode"
              @click="onEnqueueAlbumCraft"
            >
              ★ В очередь
            </button>
            <button
              v-if="workQueue.premium_active"
              type="button"
              class="btn macro"
              :disabled="actionLoading || !albumCraftProfessionCode"
              @click="onEnqueueAlbumMacro"
            >
              Собрать ресурсы и изготовить
            </button>
          </div>
        </div>

        <div class="section" v-if="workQueue.premium_active && queueMaterialSellable.length">
          <div class="section_title">Сдать добычу на биржу</div>
          <p class="hint">
            Только материалы с фарма — в очередь Premium, чтобы не кончились 🪙 на смены «для себя».
            Сундуки и сувениры — вручную на бирже.
          </p>

          <div class="exchange_sell_list">
            <div
              v-for="(item, index) in queueMaterialSellable"
              :key="exchangeSellKey(item, index)"
              class="exchange_sell_row"
            >
              <div class="exchange_sell_main">
                <div class="exchange_sell_label">{{ item.label }}</div>
                <div class="exchange_sell_meta">
                  В инвентаре: {{ item.available }} · номинал {{ item.nominal }}–{{ item.max_price }} 🪙
                  · макс. {{ item.pallet_limit }}/лот
                </div>
              </div>
              <button
                type="button"
                class="btn secondary mini"
                :disabled="actionLoading"
                @click="openExchangeSellModal(item)"
              >★ В очередь</button>
            </div>
          </div>
        </div>

        <div class="section" v-if="farm.materials?.length">
          <div class="section_title">Инвентарь материалов</div>
          <div class="mat_row" v-for="m in farm.materials" :key="m.code + (m.is_premium ? 'p' : '')">
            <span class="mat_label">
              <span class="mat_emoji">{{ m.emoji || '📦' }}</span>
              {{ m.label }}<span v-if="m.is_premium" class="premium_badge"> ★</span>
            </span>
            <span>{{ m.qty }}</span>
          </div>
        </div>
      </template>

      <template v-if="!farm.slots?.needs_pick && activeFarmTab === 'log' && showQueueLogTab">
        <div class="section premium_queue_section">
          <div class="section_title">Журнал очереди Premium ★</div>
          <p class="hint">
            Завершённые, отменённые и пропущенные задачи. Новые записи появляются сверху.
          </p>

          <div v-if="workQueue.log?.length" class="queue_list queue_log">
            <div v-for="item in workQueue.log" :key="'l' + item.id" class="queue_row log">
              <div class="queue_log_main">
                <span class="queue_label">{{ item.label }}</span>
                <span class="queue_status" :class="item.status">{{ queueStatusLabel(item.status) }}</span>
                <span class="queue_meta">{{ item.finished_at }}</span>
              </div>
              <div class="queue_log_detail" v-if="item.error">{{ item.error }}</div>
              <div class="queue_log_detail ok" v-else-if="queueResultText(item)">{{ queueResultText(item) }}</div>
            </div>
          </div>

          <p v-else class="hint">Пока пусто — здесь появятся результаты очереди.</p>
        </div>
      </template>
    </template>

    <div class="sell_modal_overlay" v-if="exchangeSellModalItem" @click.self="closeExchangeSellModal">
      <div class="sell_modal" role="dialog">
        <div class="sell_modal_title">Сдать в очередь: {{ exchangeSellModalItem.label }}</div>
        <p class="sell_modal_meta">
          В инвентаре: {{ exchangeSellModalItem.available }}
          · номинал {{ exchangeSellModalItem.nominal }}–{{ exchangeSellModalItem.max_price }} 🪙
          · макс. {{ exchangeSellModalItem.pallet_limit }}/лот
        </p>

        <label class="sell_field_label">Количество</label>
        <input
          v-model.number="exchangeSellModalForm.qty"
          type="number"
          min="1"
          :max="exchangeSellModalItem.available"
          class="sell_field_input"
        >

        <label class="sell_field_label">Цена за шт., 🪙</label>
        <input
          v-model.number="exchangeSellModalForm.price"
          type="number"
          step="0.1"
          :min="exchangeSellModalItem.nominal"
          :max="exchangeSellModalItem.max_price"
          class="sell_field_input"
        >

        <div class="sell_modal_actions">
          <button type="button" class="btn secondary" :disabled="actionLoading" @click="closeExchangeSellModal">
            Отмена
          </button>
          <button
            type="button"
            class="btn"
            :disabled="actionLoading || !exchangeSellModalValid"
            @click="onEnqueueExchangeList"
          >
            ★ В очередь
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapMutations, mapState } from 'vuex';
import PreLoader from '@/components/main/PreLoader';
import { apiActions } from '@/api/bitrixClient';

const ALBUM_CRAFT_PROFESSION_CODES = new Set(['carpenter', 'weaver']);

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
      workMode: 'self',
      selectedIterations: 1,
      activeFarmTab: 'work',
      error: '',
      message: '',
      pollTimer: null,
      countdownSeconds: 0,
      countdownTimer: null,
      countdownDeadlineTs: 0,
      visibilityHandler: null,
      expandedChancesCode: '',
      tickRefreshInFlight: false,
      lastCompletedShiftId: 0,
      exchangeState: null,
      exchangeSellLoaded: false,
      exchangeSellModalItem: null,
      exchangeSellModalForm: { qty: 1, price: 0 },
      albumMacroBatches: 1,
      albumMacroConsign: false,
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
    workQueue() {
      return this.farm?.work_queue || {};
    },
    orderedQueueItems() {
      const active = Array.isArray(this.workQueue.active) ? this.workQueue.active : [];
      const pending = Array.isArray(this.workQueue.pending) ? this.workQueue.pending : [];
      return [...active, ...pending].map((item, index) => ({
        ...item,
        queuePosition: index + 1,
      }));
    },
    showFarmTabs() {
      return !this.farm?.slots?.needs_pick;
    },
    showQueueLogTab() {
      return Boolean(this.workQueue.premium_active);
    },
    sessionMaxIterations() {
      return Math.max(1, Number(this.farm?.economy?.max_iterations) || 6);
    },
    iterationOptions() {
      const max = this.sessionMaxIterations;
      const cap = Math.min(max, this.maxIterationsForWork || max);
      return Array.from({ length: cap }, (_, i) => i + 1);
    },
    selectedProfessionDef() {
      const code = this.selectedProfession;
      if (!code) {
        return null;
      }
      return (this.farm?.professions || []).find(p => p.code === code)
        || (this.farm?.catalog || []).find(p => p.code === code)
        || null;
    },
    treasuryLaborOpenMap() {
      return this.farm?.treasury_labor_open || {};
    },
    treasuryModeAvailable() {
      const code = this.selectedProfession;
      if (!code) {
        return false;
      }
      return Boolean(this.treasuryLaborOpenMap[code]);
    },
    treasuryModeTitle() {
      if (this.treasuryModeAvailable) {
        return '';
      }
      return 'Нет открытых заказов казны на бирже для этой профессии';
    },
    craftInputAvailable() {
      const prof = this.selectedProfessionDef;
      if (!prof || prof.type !== 'process' || !prof.input) {
        return 0;
      }
      if (this.workQueue.premium_active && this.farm?.queue_projection) {
        const projection = this.farm.queue_projection;
        if (this.workMode === 'treasury') {
          return Number(projection.materials_gov?.[prof.input] ?? 0);
        }
        return Number(projection.materials_self?.[prof.input] ?? 0);
      }
      if (this.workMode === 'treasury') {
        const row = (this.farm?.gov_materials || []).find(m => m.code === prof.input);
        return Number(row?.qty ?? 0);
      }
      const row = (this.farm?.materials || []).find(m => m.code === prof.input && !m.is_premium);
      return Number(row?.qty ?? 0);
    },
    maxIterationsForWork() {
      const max = this.sessionMaxIterations;
      const prof = this.selectedProfessionDef;
      if (!prof || prof.type !== 'process' || !prof.input) {
        return max;
      }
      const available = this.craftInputAvailable;
      if (available <= 0) {
        return 0;
      }
      return Math.min(max, available);
    },
    showWorkPlannerSection() {
      return Boolean(
        (this.farm?.professions?.length)
        && (!this.farm?.session || this.workQueue.premium_active),
      );
    },
    showAlbumCraftSection() {
      const craft = this.farm?.album_craft;
      const eligible = craft?.profession_codes || [];
      if (!craft?.recipe_learned || !eligible.length) {
        return false;
      }
      if (this.workQueue.premium_active) {
        return true;
      }
      const code = this.selectedProfession;
      return Boolean(
        !this.farm?.session
        && ALBUM_CRAFT_PROFESSION_CODES.has(code)
        && eligible.includes(code),
      );
    },
    albumCraftProfessionCode() {
      const eligible = this.farm?.album_craft?.profession_codes || [];
      const code = this.selectedProfession;
      if (ALBUM_CRAFT_PROFESSION_CODES.has(code) && eligible.includes(code)) {
        return code;
      }
      if (this.workQueue.premium_active && eligible.length) {
        return eligible[0];
      }
      return '';
    },
    albumCraftProfessionLabel() {
      const code = this.albumCraftProfessionCode;
      if (!code) {
        return '';
      }
      const prof = (this.farm?.professions || []).find(p => p.code === code);
      return prof?.label || code;
    },
    albumMacroOutputQty() {
      const perCraft = Number(this.farm?.album_craft?.output_count) || 2;
      return perCraft * (Number(this.albumMacroBatches) || 1);
    },
    macroOutputQty() {
      return Math.max(1, Math.min(this.sessionMaxIterations, Number(this.selectedIterations) || 1));
    },
    professionMacroLabel() {
      const prof = this.selectedProfessionDef;
      if (!prof) {
        return 'Собрать и изготовить';
      }
      return prof.type === 'process' ? 'Собрать и изготовить' : 'Собрать ресурсы';
    },
    workModeHint() {
      const minutes = Number(this.farm?.economy?.iteration_minutes) || 5;
      const prof = this.selectedProfessionDef;
      const isCraft = prof?.type === 'process';
      if (this.workMode === 'treasury') {
        return isCraft
          ? `Один цикл ${minutes} мин. Крафт на казну: сырьё с госсклада, продукт на госсклад, +${this.farm?.economy?.pay_treasury || 2} 🪙/цикл. Комбо — вам.`
          : `Один цикл ${minutes} мин. Добыча на казну: +${this.farm?.economy?.pay_treasury || 2} 🪙/цикл, ресурс на госсклад; комбо и премиум — вам.`;
      }
      return isCraft
        ? `Один цикл ${minutes} мин. Крафт для себя: сырьё из инвентаря, продукт вам; ${this.farm?.economy?.fee_self || 0.5} 🪙/цикл списывается после успешной смены.`
        : `Один цикл ${minutes} мин. Добыча для себя: ресурс в инвентарь; ${this.farm?.economy?.fee_self || 0.5} 🪙/цикл после смены.`;
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
          type: item.type || 'gather',
          input: item.input || '',
          input_label: item.input_label || '',
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
          combo_x2_percent: owned?.combo_x2_percent ?? 0,
          combo_x3_percent: owned?.combo_x3_percent ?? 0,
          premium_percent: owned?.premium_percent ?? 0,
          has_premium_drop: owned?.has_premium_drop ?? true,
          premium_min_level: owned?.premium_min_level ?? 2,
        };
      });
    },
    exchangeSellable() {
      return Array.isArray(this.exchangeState?.sellable) ? this.exchangeState.sellable : [];
    },
    queueMaterialSellable() {
      return this.exchangeSellable.filter((item) => item.kind === 'material' && Number(item.available) > 0);
    },
    exchangeSellModalValid() {
      const item = this.exchangeSellModalItem;
      if (!item) {
        return false;
      }
      const qty = Number(this.exchangeSellModalForm.qty || 0);
      const price = Number(this.exchangeSellModalForm.price || 0);
      const maxQty = Number(item.available || 0);
      const minPrice = Number(item.nominal || 0);
      const maxPrice = Number(item.max_price || 0);
      return qty >= 1 && qty <= maxQty && price >= minPrice && price <= maxPrice;
    },
  },
  watch: {
    maxIterationsForWork(max) {
      if (max > 0 && this.selectedIterations > max) {
        this.selectedIterations = max;
      }
    },
    workMode() {
      if (this.maxIterationsForWork > 0 && this.selectedIterations > this.maxIterationsForWork) {
        this.selectedIterations = this.maxIterationsForWork;
      }
    },
    selectedProfession() {
      if (this.maxIterationsForWork > 0 && this.selectedIterations > this.maxIterationsForWork) {
        this.selectedIterations = this.maxIterationsForWork;
      } else if (this.maxIterationsForWork > 0 && this.selectedIterations < 1) {
        this.selectedIterations = 1;
      }
      this.ensureWorkModeValid();
    },
    treasuryModeAvailable() {
      this.ensureWorkModeValid();
    },
    activeFarmTab(tab) {
      if (tab === 'work' && this.workQueue.premium_active) {
        this.loadExchangeSellState();
      }
    },
    'workQueue.premium_active'(active) {
      if (active && this.activeFarmTab === 'work') {
        this.loadExchangeSellState();
      }
    },
  },
  created() {
    this.visibilityHandler = () => this.onVisibilityChange();
    document.addEventListener('visibilitychange', this.visibilityHandler);
    this.farmRefreshHandler = () => this.refresh(true);
    window.addEventListener('prognos9ys:farm-refresh', this.farmRefreshHandler);
    this.refresh();
  },
  beforeUnmount() {
    if (this.visibilityHandler) {
      document.removeEventListener('visibilitychange', this.visibilityHandler);
    }
    if (this.farmRefreshHandler) {
      window.removeEventListener('prognos9ys:farm-refresh', this.farmRefreshHandler);
    }
    this.clearPoll();
    this.clearCountdown();
  },
  methods: {
    ...mapActions('auth', ['refreshGameInfo']),
    ...mapMutations('auth', ['setUserInfo']),

    formatMoney(value) {
      return Number(value ?? 0).toFixed(1).replace(/\.0$/, '');
    },

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
            this.notifyShiftCompletion();
            this.refreshGameInfo().catch(() => {});
          }
          if (!this.selectedProfession && this.farm?.professions?.length) {
            this.selectedProfession = this.farm.professions[0].code;
          }
          this.ensureWorkModeValid();
          if (this.farm?.slots?.needs_pick) {
            this.activeFarmTab = 'professions';
          } else if (this.farm?.slots?.can_add_profession) {
            this.activeFarmTab = 'professions';
          } else if (this.farm?.session) {
            this.activeFarmTab = 'work';
          }
          if (this.activeFarmTab === 'log' && !this.farm?.work_queue?.premium_active) {
            this.activeFarmTab = 'work';
          }
          this.schedulePoll();
          this.syncCountdown();
          if (this.farm?.work_queue?.premium_active && this.activeFarmTab === 'work') {
            this.loadExchangeSellState(true);
          }
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
      if (this.tickRefreshInFlight) {
        return;
      }

      const msLeft = this.countdownDeadlineTs > 0
        ? this.countdownDeadlineTs - Date.now() + 500
        : Number(this.farm?.session?.seconds_left ?? 0) * 1000 + 500;

      if (msLeft <= 0) {
        if (this.farm?.session) {
          this.requestTickRefresh();
        }
        return;
      }

      const delay = Math.min(Math.max(msLeft, 3000), 300000);
      this.pollTimer = window.setTimeout(() => this.requestTickRefresh(), delay);
    },

    clearPoll() {
      if (this.pollTimer) {
        window.clearTimeout(this.pollTimer);
        this.pollTimer = null;
      }
    },

    resolveCountdownDeadline() {
      const nextTick = Number(this.farm?.session?.next_tick_at ?? 0);
      if (nextTick > 0) {
        return nextTick * 1000;
      }
      const seconds = Number(this.farm?.session?.seconds_left ?? 0);
      if (seconds > 0) {
        return Date.now() + seconds * 1000;
      }
      return 0;
    },

    updateCountdownFromDeadline() {
      if (!this.countdownDeadlineTs) {
        this.countdownSeconds = 0;
        return;
      }
      this.countdownSeconds = Math.max(
        0,
        Math.ceil((this.countdownDeadlineTs - Date.now()) / 1000),
      );
    },

    onVisibilityChange() {
      if (document.visibilityState !== 'visible' || !this.farm?.session) {
        return;
      }
      this.updateCountdownFromDeadline();
      if (this.countdownSeconds <= 0) {
        this.clearCountdown();
        this.clearPoll();
        this.requestTickRefresh();
        return;
      }
      this.schedulePoll();
    },

    syncCountdown() {
      this.clearCountdown();
      this.countdownDeadlineTs = this.resolveCountdownDeadline();
      this.updateCountdownFromDeadline();
      if (this.countdownSeconds <= 0) {
        return;
      }

      this.countdownTimer = window.setInterval(() => {
        this.updateCountdownFromDeadline();
        if (this.countdownSeconds <= 0) {
          this.clearCountdown();
          this.clearPoll();
          this.requestTickRefresh();
        }
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

    professionMetaLine(card) {
      if (card.type === 'process' && card.input_label) {
        return `${card.input_label} → ${card.output_label}`;
      }
      return `${card.output_label} · ${card.premium_label}`;
    },

    toggleChances(code) {
      this.expandedChancesCode = this.expandedChancesCode === code ? '' : code;
    },

    formatChancePercent(value, digits = 2) {
      const n = Number(value);
      if (!Number.isFinite(n)) {
        return '0%';
      }
      const text = n.toFixed(digits).replace(/\.?0+$/, '');
      return `${text}%`;
    },

    chancesHint(card) {
      const lvl = Math.max(1, card.level);
      if (card.type === 'process') {
        return `Ур. ${lvl}. Крафт: комбо и премиум (${card.premium_label}) — как у добычи; на казну премиум вам.`;
      }
      return `Ур. ${lvl}. Добыча: комбо даёт доп. ${card.output_label}; премиум с ур. ${card.premium_min_level || 2}.`;
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

    notifyShiftCompletion() {
      const shift = this.farm?.last_shift;
      if (!shift) {
        return;
      }

      this.activeFarmTab = 'work';

      const rewards = shift.last_result?.profession_level_rewards;
      if (Array.isArray(rewards) && rewards.length) {
        this.message = `Смена завершена · ${rewards.map((r) => this.formatProfessionLevelReward(r)).join(' · ')}`;
        return;
      }

      if (shift.last_result?.message) {
        this.message = 'Смена завершена';
      }
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

    selectTreasuryMode() {
      if (this.treasuryModeAvailable) {
        this.workMode = 'treasury';
      }
    },

    ensureWorkModeValid() {
      if (this.workMode === 'treasury' && !this.treasuryModeAvailable) {
        this.workMode = 'self';
      }
    },

    resolveWorkMode() {
      this.ensureWorkModeValid();
      return this.workMode === 'treasury' && !this.treasuryModeAvailable ? 'self' : this.workMode;
    },

    async onEnqueueFarmWork() {
      const token = this.authData?.token;
      if (!token || !this.selectedProfession) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        const data = await apiActions.game.enqueuePremiumWork(token, 'farm', {
          profession_code: this.selectedProfession,
          work_mode: this.resolveWorkMode(),
          iterations: this.selectedIterations,
        });
        if (data?.status === 'ok') {
          this.applyPremiumWorkResponse(data, 'Задача добавлена в очередь Premium');
        } else {
          this.error = data?.message || 'Не удалось добавить в очередь';
        }
      } catch (e) {
        this.error = e.message || 'Не удалось добавить в очередь';
      } finally {
        this.actionLoading = false;
      }
    },

    async onEnqueueAlbumCraft() {
      const token = this.authData?.token;
      const professionCode = this.albumCraftProfessionCode;
      if (!token || !professionCode) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        const data = await apiActions.game.enqueuePremiumWork(token, 'album_craft', {
          profession_code: professionCode,
        });
        if (data?.status === 'ok') {
          this.applyPremiumWorkResponse(data, 'Крафт альбомов добавлен в очередь');
          window.dispatchEvent(new CustomEvent('prognos9ys:album-refresh'));
        } else {
          this.error = data?.message || 'Не удалось добавить в очередь';
        }
      } catch (e) {
        this.error = e.message || 'Не удалось добавить в очередь';
      } finally {
        this.actionLoading = false;
      }
    },

    async onEnqueueAlbumMacro() {
      const token = this.authData?.token;
      if (!token || !this.albumCraftProfessionCode) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        const data = await apiActions.game.enqueuePremiumMacro(token, 'album', {
          batches: Number(this.albumMacroBatches) || 1,
          sell: true,
          sell_mode: this.albumMacroConsign ? 'consign' : 'listing',
        });
        if (data?.status === 'ok') {
          const count = Number(data.queued) || 0;
          this.applyPremiumWorkResponse(
            data,
            `Макрос добавлен: ${count} ${count === 1 ? 'задача' : 'задач'} в очередь`,
          );
          window.dispatchEvent(new CustomEvent('prognos9ys:album-refresh'));
        } else {
          this.error = data?.message || 'Не удалось запустить макрос';
        }
      } catch (e) {
        this.error = e.message || 'Не удалось запустить макрос';
      } finally {
        this.actionLoading = false;
      }
    },

    async onEnqueueProfessionMacro() {
      const token = this.authData?.token;
      if (!token || !this.selectedProfession) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        const data = await apiActions.game.enqueuePremiumMacro(token, 'profession', {
          profession_code: this.selectedProfession,
          output_qty: this.macroOutputQty,
        });
        if (data?.status === 'ok') {
          const count = Number(data.queued) || 0;
          this.applyPremiumWorkResponse(
            data,
            `Макрос добавлен: ${count} ${count === 1 ? 'задача' : 'задач'} в очередь`,
          );
        } else {
          this.error = data?.message || 'Не удалось запустить макрос';
        }
      } catch (e) {
        this.error = e.message || 'Не удалось запустить макрос';
      } finally {
        this.actionLoading = false;
      }
    },

    async onToggleQueueSellMode(item, event) {
      const token = this.authData?.token;
      if (!token || !item?.id || item.status === 'active') {
        return;
      }

      const sellMode = event?.target?.checked ? 'consign' : 'listing';
      this.actionLoading = true;
      this.error = '';
      try {
        const data = await apiActions.game.updatePremiumWorkSellMode(token, item.id, sellMode);
        if (data?.status === 'ok') {
          this.applyPremiumWorkResponse(data, sellMode === 'consign' ? 'Комиссия банка' : 'Листинг на бирже');
        } else {
          this.error = data?.message || 'Не удалось обновить режим';
          if (event?.target) {
            event.target.checked = item.payload?.sell_mode === 'consign';
          }
        }
      } catch (e) {
        this.error = e.message || 'Не удалось обновить режим';
        if (event?.target) {
          event.target.checked = item.payload?.sell_mode === 'consign';
        }
      } finally {
        this.actionLoading = false;
      }
    },

    async onCancelPremiumWork(taskId) {
      const token = this.authData?.token;
      if (!token || !taskId) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      try {
        const data = await apiActions.game.cancelPremiumWork(token, taskId);
        if (data?.status === 'ok') {
          this.applyPremiumWorkResponse(data, 'Задача отменена');
        } else {
          this.error = data?.message || 'Не удалось отменить';
        }
      } catch (e) {
        this.error = e.message || 'Не удалось отменить';
      } finally {
        this.actionLoading = false;
      }
    },

    applyPremiumWorkResponse(data, fallbackMessage) {
      if (data.farm) {
        this.farm = data.farm;
      }
      if (data.game) {
        this.setUserInfo({
          ...this.$store.state.auth.userInfo,
          game_info: data.game,
        });
      }
      this.message = fallbackMessage;
      this.activeFarmTab = 'work';
      if (this.farm?.session) {
        this.schedulePoll();
        this.syncCountdown();
      }
      if (this.workQueue.premium_active) {
        this.loadExchangeSellState(true);
      }
    },

    exchangeItemKey(item) {
      return [item.kind, item.code, item.category, item.event_id, item.team_code].join(':');
    },

    exchangeSellKey(item, index) {
      return this.exchangeItemKey(item) + ':' + index;
    },

    openExchangeSellModal(item) {
      this.exchangeSellModalItem = item;
      this.exchangeSellModalForm = {
        qty: Math.min(Number(item.available || 1), Number(item.pallet_limit || 1)),
        price: Number(item.nominal || 0),
      };
    },

    closeExchangeSellModal() {
      if (this.actionLoading) {
        return;
      }
      this.exchangeSellModalItem = null;
    },

    async loadExchangeSellState(silent = false) {
      const token = this.authData?.token;
      if (!token || !this.workQueue.premium_active) {
        return;
      }

      if (!silent) {
        this.exchangeSellLoaded = false;
      }

      try {
        const data = await apiActions.exchange.getState(token);
        if (data?.status === 'ok') {
          this.exchangeState = data;
        }
      } catch (e) {
        if (!silent) {
          this.error = e.message || 'Не удалось загрузить биржу';
        }
      } finally {
        this.exchangeSellLoaded = true;
      }
    },

    async onEnqueueExchangeList() {
      const token = this.authData?.token;
      const item = this.exchangeSellModalItem;
      if (!token || !item || !this.exchangeSellModalValid) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        const data = await apiActions.game.enqueuePremiumWork(token, 'exchange_list', {
          kind: item.kind,
          code: item.code,
          qty: Number(this.exchangeSellModalForm.qty || 0),
          price_per_unit: Number(this.exchangeSellModalForm.price || 0),
          category: item.category || '',
          event_id: item.event_id || 0,
          team_code: item.team_code || '',
        });
        if (data?.status === 'ok') {
          this.closeExchangeSellModal();
          this.applyPremiumWorkResponse(data, 'Материалы добавлены в очередь Premium');
        } else {
          this.error = data?.message || 'Не удалось добавить в очередь';
        }
      } catch (e) {
        this.error = e.message || 'Не удалось добавить в очередь';
      } finally {
        this.actionLoading = false;
      }
    },

    queueStatusLabel(status) {
      if (status === 'completed') {
        return 'готово';
      }
      if (status === 'failed') {
        return 'ошибка';
      }
      if (status === 'cancelled') {
        return 'отмена';
      }
      return status;
    },

    queueResultText(item) {
      const result = item?.result || {};
      if (result.message) {
        return result.message;
      }
      if (Array.isArray(result.lines)) {
        return result.lines.map((line) => line.text).filter(Boolean).join(' · ');
      }
      if (result.listing) {
        return 'Лот выставлен';
      }
      return '';
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
          this.resolveWorkMode(),
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

    async onCraftAlbums() {
      const token = this.authData?.token;
      const professionCode = this.albumCraftProfessionCode;
      if (!token || !professionCode || !this.farm?.album_craft?.can_craft) {
        return;
      }

      this.actionLoading = true;
      this.error = '';
      this.message = '';
      try {
        const data = await apiActions.game.craftAlbums(token, professionCode);
        if (data?.status === 'ok') {
          if (data.farm) {
            this.farm = data.farm;
          }
          const lineText = (data.lines || []).map((line) => line.text).filter(Boolean).join(' · ');
          this.message = lineText || 'Альбомы скрафчены';
          if (data.game) {
            this.setUserInfo({
              ...this.$store.state.auth.userInfo,
              game_info: data.game,
            });
          }
          window.dispatchEvent(new CustomEvent('prognos9ys:album-refresh'));
        } else {
          this.error = data?.message || 'Не удалось скрафтить альбомы';
        }
      } catch (e) {
        this.error = e.message || 'Не удалось скрафтить альбомы';
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

  &.farm_tabs_three .farm_tab {
    padding: 8px 6px;
    font-size: 11px;
  }
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
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 4px;
}

.prof_card_title_row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.prof_chances_wrap {
  margin-bottom: 6px;
}

.chances_btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  font-size: 11px;
  color: @colorText;
  background: fade(@darkbg, 80%);
  border: 1px solid fade(@orange, 35%);
  border-radius: 4px;
  cursor: pointer;

  &.open {
    border-color: @orange;
    color: @orange;
  }
}

.chances_chevron {
  font-size: 9px;
  opacity: 0.8;
}

.chances_panel {
  margin-top: 6px;
  padding: 6px 8px;
  border-radius: 4px;
  background: fade(@darkbg, 50%);
  border: 1px solid fade(@orange, 20%);
}

.chances_row {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 11px;
  line-height: 1.5;
  color: @colorBlur;

  strong {
    color: @colorText;
    font-weight: 600;

    &.chances_na {
      color: @colorBlur;
      font-weight: 500;
    }
  }
}

.chances_hint {
  margin: 6px 0 0;
  font-size: 10px;
  line-height: 1.35;
  color: fade(@colorBlur, 85%);
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
  font-weight: 600;
  color: @colorText;
  flex-shrink: 0;
}

.pick_meta {
  font-size: 11px;
  color: @colorBlur;
  text-align: right;
  flex-shrink: 1;
  min-width: 0;
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

  &.disabled,
  &:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }
}

.queue_eta {
  color: @orange;
  font-weight: 600;
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

.premium_queue_section {
  border: 1px solid fade(@yellow, 35%);
}

.section_subtitle {
  font-size: 12px;
  font-weight: 700;
  color: @colorText;
  margin: 10px 0 6px;
}

.queue_list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 8px;
}

.queue_sell_mode {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.75);
  cursor: pointer;
}

.album_macro_controls {
  margin: 8px 0 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.macro_batches select {
  margin-left: 6px;
}

.macro_consign {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  cursor: pointer;
}

.btn.macro {
  background: linear-gradient(135deg, #5c4d9e, #3d6e8f);
  border: none;
}

.work_actions_row .btn.macro {
  flex: 1 1 auto;
}

.queue_row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
  padding: 6px 8px;
  border-radius: 4px;
  background: fade(@darkbg, 70%);
  font-size: 12px;
  border: 1px solid transparent;

  &.running {
    border-color: fade(@yellow, 40%);
    background: fade(@yellow, 8%);
  }

  .btn.mini {
    flex-shrink: 0;
    align-self: center;
    margin: 0;
  }

  &.log {
    flex-direction: column;
    align-items: stretch;
  }
}

.queue_num {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  font-size: 11px;
  font-weight: 700;
  color: @colorText;
  background: fade(@colorBlur, 25%);
}

.queue_row.running .queue_num {
  background: fade(@yellow, 35%);
  color: @yellow;
}

.queue_log_main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
}

.queue_label {
  flex: 1;
  min-width: 120px;
  color: @colorText;
  font-weight: 600;
}

.queue_status {
  font-size: 11px;
  font-weight: 700;

  &.wait { color: @colorBlur; }
  &.run { color: @yellow; }
  &.completed { color: @YesWrite; }
  &.failed { color: @NoWrite; }
  &.cancelled { color: @colorBlur; }
}

.queue_meta {
  color: @colorBlur;
  font-size: 10px;
}

.queue_log_detail {
  font-size: 11px;
  color: @NoWrite;
  line-height: 1.35;

  &.ok {
    color: @YesWrite2;
  }
}

.work_actions_row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: stretch;

  .btn {
    flex: 1 1 120px;
    min-height: 32px;
  }
}

.btn.mini {
  padding: 2px 8px;
  font-size: 11px;
}

.exchange_sell_list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.exchange_sell_row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 4px;
  background: fade(@darkbg, 70%);
}

.exchange_sell_main {
  flex: 1;
  min-width: 120px;
}

.exchange_sell_label {
  font-size: 12px;
  font-weight: 700;
  color: @colorText;
}

.exchange_sell_meta {
  font-size: 11px;
  color: @colorBlur;
  margin-top: 2px;
  line-height: 1.35;
}

.sell_modal_overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
  background: fade(#000, 55%);
}

.sell_modal {
  width: 100%;
  max-width: 360px;
  padding: 12px;
  border-radius: 6px;
  background: @DarkColorBG;
  border: 1px solid fade(@orange, 45%);
  color: @colorText;
}

.sell_modal_title {
  font-size: 14px;
  font-weight: 700;
  color: @orange;
  margin-bottom: 6px;
}

.sell_modal_meta {
  font-size: 11px;
  color: @colorBlur;
  line-height: 1.35;
  margin: 0 0 10px;
}

.sell_field_label {
  display: block;
  font-size: 11px;
  color: @colorText;
  margin-bottom: 4px;
}

.sell_field_input {
  width: 100%;
  box-sizing: border-box;
  padding: 6px 8px;
  margin-bottom: 10px;
  border-radius: 4px;
  border: 1px solid fade(@colorBlur, 40%);
  background: @darkbg;
  color: @colorText;
  font-size: 13px;
}

.sell_modal_actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;

  .btn {
    flex: 1 1 120px;
    min-height: 32px;
  }
}
</style>
