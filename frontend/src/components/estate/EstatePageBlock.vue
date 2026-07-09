<template>
  <div class="estate_page">
    <div v-if="loading" class="hint">Загрузка карты поселений…</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <template v-else>
      <p class="hint intro">
        Карта пангеи ЧМ-26: выберите регион, затем поселение.
        Пунктир — план или стройка, сплошная рамка — открытый город.
      </p>

      <div class="section_card">
        <div class="section_title">Карта поселений</div>
        <EstateWorldMap
          v-if="view === 'world'"
          :map="map"
          :selected-region-id="selectedRegionId"
          @select-region="onSelectRegion"
          @open-city="onOpenCity"
        />
        <EstateRegionMap
          v-else-if="view === 'region' && selectedRegion"
          :region="selectedRegion"
          :selected-slug="selectedSlug"
          @back="onBackToWorld"
          @select-city="onSelectCity"
        />
      </div>

      <div class="section_card section_card_city" v-if="selectedSlug">
        <div class="section_title_row">
          <button
            v-if="view === 'street'"
            type="button"
            class="back_btn"
            @click="onBackToRegion"
          >
            ← {{ selectedRegion?.label || 'Регион' }}
          </button>
          <div class="section_title">Улица города</div>
        </div>
        <p class="hint street_hint">
          20 усадеб: по 5 сверху и снизу слева и справа. В центре — биржа, банк и широкая управа.
          Листайте вбок на узком экране. Зелёный — свободен, коричневый — занят, оранжевый — ваш.
        </p>
        <div v-if="cityMessage" class="message">{{ cityMessage }}</div>
        <div v-if="cityLoading" class="hint">Загрузка улицы…</div>
        <div v-else-if="cityError" class="error">{{ cityError }}</div>
        <EstateCityStreetMap
          v-else-if="cityMap"
          :city="cityMap"
          :loading="cityActionLoading"
          @claim-plot="onClaimPlot"
          @plot-info="onPlotInfo"
          @plot-view="onPlotView"
          @donate-component="onDonateComponent"
          @donate-project-all="onDonateProjectAll"
          @build-project="onBuildProject"
          @order-component="onOrderComponent"
          @order-project-all="onOrderProjectAll"
          @cancel-component-orders="onCancelComponentOrders"
          @cancel-project-orders="onCancelProjectOrders"
          @bank-branches="onBankBranches"
        />
      </div>
      <div class="section_card" v-else-if="view === 'region'">
        <p class="hint">Выберите город на карте региона.</p>
      </div>
    </template>

    <div
      v-if="estateModal"
      class="estate_modal_overlay"
      @click.self="closeEstateModal"
    >
      <div class="estate_modal" role="dialog">
        <div class="estate_modal_title">{{ estateModal.title }}</div>
        <EstatePlotPreview
          v-if="estateModal.plotView"
          :stage="estateModal.plotView.stage"
          class="estate_modal_preview"
        />
        <p v-if="estateModal.message" class="estate_modal_message">{{ estateModal.message }}</p>
        <div v-if="estateModal.kind === 'bank-branches'" class="estate_modal_branches">
          <p v-if="estateModal.bankBranchesLoading" class="estate_modal_meta">Загрузка филиалов…</p>
          <template v-else>
            <p v-if="!estateModal.buildingComplete" class="estate_modal_meta">
              Госздание «Филиал банка» в этом городе ещё не построено.
            </p>
            <ul v-else-if="estateModal.bankBranches?.length" class="bank_branches_list">
              <li
                v-for="branch in estateModal.bankBranches"
                :key="branch.bank_id"
                class="bank_branch_row"
              >
                <span class="bank_branch_name">{{ branch.owner_name }}</span>
                <span class="bank_branch_meta">банк #{{ branch.bank_id }}</span>
              </li>
            </ul>
            <p v-else class="estate_modal_meta">Пока нет банков с филиалом в этом городе.</p>
            <p
              v-if="estateModal.buildingComplete && estateModal.pendingCount > 0"
              class="estate_modal_meta"
            >
              Без филиала: {{ estateModal.pendingCount }} банк(ов).
            </p>
          </template>
        </div>
        <div
          v-if="estateModal.showQtyPicker && estateModal.mode === 'confirm'"
          class="estate_modal_qty_row"
        >
          <label class="estate_modal_qty_label" for="estate-order-qty">Количество</label>
          <div class="estate_modal_qty_controls">
            <button
              type="button"
              class="estate_modal_qty_btn"
              title="Минимум"
              :disabled="estateModal.loading"
              @click="setOrderModalQtyMin"
            >
              min
            </button>
            <input
              id="estate-order-qty"
              v-model.number="estateModal.orderQty"
              type="number"
              min="1"
              :max="estateModal.orderQtyMax"
              class="estate_modal_qty_input"
              :disabled="estateModal.loading"
            />
            <button
              type="button"
              class="estate_modal_qty_btn"
              title="Максимум"
              :disabled="estateModal.loading"
              @click="setOrderModalQtyMax"
            >
              max
            </button>
          </div>
          <span class="estate_modal_qty_hint">из {{ estateModal.orderQtyMax }}</span>
          <p v-if="orderModalReserve" class="estate_modal_reserve">
            Будет зарезервировано: {{ orderModalReserve }} 🪙
          </p>
        </div>
        <p v-if="estateModal.meta" class="estate_modal_meta">{{ estateModal.meta }}</p>
        <p v-if="estateModal.error" class="estate_modal_error">{{ estateModal.error }}</p>
        <p v-if="estateModal.success" class="estate_modal_success">{{ estateModal.success }}</p>

        <div class="estate_modal_actions">
          <button
            v-if="estateModal.kind === 'bank-branches' && canImpersonate && estateModal.buildingComplete"
            type="button"
            class="modal_btn primary"
            :disabled="estateModal.loading || estateModal.bankBranchesLoading || !estateModal.pendingCount"
            @click="confirmAdminOpenBranches"
          >
            {{
              estateModal.loading
                ? 'Подождите…'
                : `Открыть филиалы (${estateModal.pendingCount || 0})`
            }}
          </button>
          <button
            v-if="estateModal.kind === 'bank-branches' && !estateModal.loading"
            type="button"
            class="modal_btn"
            :class="canImpersonate && estateModal.buildingComplete ? 'secondary' : 'primary'"
            @click="closeEstateModal"
          >
            Закрыть
          </button>
          <button
            v-if="estateModal.mode === 'confirm' && !estateModal.loading"
            type="button"
            class="modal_btn secondary"
            @click="closeEstateModal"
          >
            Отмена
          </button>
          <button
            v-if="estateModal.mode === 'confirm'"
            type="button"
            class="modal_btn"
            :class="estateModal.confirmClass || 'primary'"
            :disabled="estateModal.loading"
            @click="confirmEstateModal"
          >
            {{ estateModal.loading ? 'Подождите…' : (estateModal.confirmLabel || 'Подтвердить') }}
          </button>
          <button
            v-if="estateModal.mode === 'alert'"
            type="button"
            class="modal_btn primary"
            @click="closeEstateModal"
          >
            Понятно
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex';
import { apiActions } from '@/api/bitrixClient';
import EstateWorldMap from '@/components/estate/EstateWorldMap.vue';
import EstateRegionMap from '@/components/estate/EstateRegionMap.vue';
import EstateCityStreetMap from '@/components/estate/EstateCityStreetMap.vue';
import EstatePlotPreview from '@/components/estate/EstatePlotPreview.vue';

export default {
  name: 'EstatePageBlock',
  components: { EstateWorldMap, EstateRegionMap, EstateCityStreetMap, EstatePlotPreview },
  data() {
    return {
      loading: false,
      error: '',
      map: null,
      view: 'world',
      selectedRegionId: '',
      selectedSlug: '',
      cityMap: null,
      cityLoading: false,
      cityActionLoading: false,
      cityError: '',
      cityMessage: '',
      estateModal: null,
    };
  },
  computed: {
    ...mapState('auth', ['authData']),
    ...mapGetters('auth', ['canImpersonate']),
    selectedRegion() {
      if (!this.map?.regions || !this.selectedRegionId) {
        return null;
      }

      return this.map.regions.find((region) => region.id === this.selectedRegionId) || null;
    },
    orderModalReserve() {
      const modal = this.estateModal;
      if (!modal?.showQtyPicker) {
        return '';
      }

      const max = Math.max(1, Number(modal.orderQtyMax) || 1);
      const qty = Math.max(1, Math.min(max, Number(modal.orderQty) || 1));
      const pay = Number(modal.orderPayPerUnit) || 0;
      if (!pay) {
        return '';
      }

      return (qty * pay).toFixed(1).replace(/\.0$/, '');
    },
  },
  mounted() {
    this.loadMap();
  },
  methods: {
    ...mapActions('auth', ['setUserInfo']),

    applyGame(game) {
      if (!game) {
        return;
      }
      const prev = this.$store.state.auth.userInfo?.game_info || {};
      this.setUserInfo({
        ...this.$store.state.auth.userInfo,
        game_info: { ...prev, ...game },
      });
    },

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

    onSelectRegion(regionId) {
      this.selectedRegionId = regionId;
      this.selectedSlug = '';
      this.cityMap = null;
      this.cityError = '';
      this.view = 'region';
    },

    onBackToWorld() {
      this.view = 'world';
      this.selectedRegionId = '';
      this.selectedSlug = '';
      this.cityMap = null;
      this.cityError = '';
    },

    onBackToRegion() {
      this.view = 'region';
      this.selectedSlug = '';
      this.cityMap = null;
      this.cityError = '';
    },

    async onSelectCity(slug) {
      this.selectedSlug = slug;
      this.view = 'street';
      await this.loadCityMap(slug);
    },

    async onOpenCity({ slug, regionId }) {
      if (!slug) {
        return;
      }

      if (regionId) {
        this.selectedRegionId = regionId;
      } else {
        const region = (this.map?.regions || []).find((row) => (
          Array.isArray(row.cities) && row.cities.some((city) => city.slug === slug)
        ));
        if (region?.id) {
          this.selectedRegionId = region.id;
        }
      }

      this.selectedSlug = slug;
      this.view = 'street';
      this.cityError = '';
      await this.loadCityMap(slug);
    },

    async loadCityMap(slug) {
      const token = this.authData?.token;
      if (!token || !slug) {
        return;
      }

      this.cityLoading = true;
      this.cityError = '';
      this.cityMessage = '';
      if (!this.cityActionLoading) {
        this.cityMap = null;
      }
      try {
        const data = await apiActions.game.getEstateCityMap(token, slug);
        if (data?.status !== 'ok') {
          throw new Error(data?.message || 'Не удалось загрузить улицу');
        }
        this.cityMap = data.city || null;
        if (data.city?.region_id && !this.selectedRegionId) {
          this.selectedRegionId = data.city.region_id;
        }
      } catch (e) {
        this.cityError = e?.message || 'Ошибка загрузки улицы';
      } finally {
        this.cityLoading = false;
      }
    },
    async onClaimPlot({ plotNumber }) {
      if (!this.authData?.token || !this.selectedSlug || !plotNumber || this.cityActionLoading) {
        return;
      }

      this.openEstateModal({
        mode: 'confirm',
        title: `Участок №${plotNumber}`,
        message: 'Подтвердите выбор участка для усадьбы.',
        meta: 'Будет списана 1 лицензия cert_estate.',
        confirmLabel: 'Занять участок',
        onConfirm: () => this.executeClaimPlot(plotNumber),
      });
    },
    async executeClaimPlot(plotNumber) {
      const token = this.authData?.token;
      if (!token || !this.selectedSlug || !plotNumber) {
        return;
      }

      this.cityActionLoading = true;
      this.cityError = '';
      this.cityMessage = '';
      try {
        const data = await apiActions.game.claimEstatePlot(token, this.selectedSlug, Number(plotNumber));
        if (data?.status !== 'ok') {
          throw new Error(data?.message || 'Не удалось занять участок');
        }
        this.cityMap = data.city || this.cityMap;
        this.cityMessage = `Участок №${plotNumber} успешно закреплён за вами`;
        await this.loadMap();
        const shouldAskHomeMove = Boolean(data?.result?.claimed_now)
          && data?.result?.home_estate_auto_set !== true
          && data?.result?.home_estate_before
          && (
            String(data?.result?.home_estate_before?.city_slug || '') !== String(this.selectedSlug)
            || Number(data?.result?.home_estate_before?.plot_number || 0) !== Number(plotNumber)
          );
        if (shouldAskHomeMove) {
          this.openEstateModal({
            mode: 'confirm',
            title: 'Перенос прописки',
            message: `Участок №${plotNumber} закреплён. Перенести сюда прописку?`,
            confirmLabel: 'Перенести',
            onConfirm: () => this.executeSetHomeEstate(plotNumber),
          });
        } else {
          this.setModalSuccess(`Участок №${plotNumber} закреплён за вами.`);
        }
      } catch (e) {
        this.setModalError(e?.message || 'Ошибка захвата участка');
      } finally {
        this.cityActionLoading = false;
      }
    },
    onPlotInfo(payload = {}) {
      const message = payload?.message || 'Участок недоступен';
      this.cityMessage = message;
      this.openEstateModal({
        mode: 'alert',
        title: 'Участок недоступен',
        message,
      });
    },
    onPlotView(payload = {}) {
      const plotNumber = Number(payload?.plotNumber || 0);
      const owner = payload?.ownerName || 'игрок';
      const isMine = Boolean(payload?.isMine);
      const isHome = Boolean(payload?.isHome);
      this.openEstateModal({
        mode: isMine && !isHome ? 'confirm' : 'alert',
        title: `Участок №${plotNumber}`,
        message: isMine
          ? (isHome ? 'Это ваша главная усадьба (прописка).' : 'Ваша усадьба на этой улице')
          : `Усадьба: ${owner}`,
        meta: isMine && !isHome ? 'Сделать этот участок вашей пропиской?' : '',
        confirmLabel: isMine && !isHome ? 'Сделать пропиской' : '',
        onConfirm: isMine && !isHome ? () => this.executeSetHomeEstate(plotNumber) : null,
        plotView: {
          stage: payload?.stage || 'claimed',
        },
      });
    },
    async executeSetHomeEstate(plotNumber) {
      const token = this.authData?.token;
      if (!token || !this.selectedSlug || !plotNumber) {
        return;
      }

      this.cityActionLoading = true;
      this.cityError = '';
      this.cityMessage = '';
      try {
        const data = await apiActions.game.setHomeEstate(token, this.selectedSlug, Number(plotNumber));
        if (data?.status !== 'ok') {
          throw new Error(data?.message || 'Не удалось изменить прописку');
        }
        this.cityMap = data.city || this.cityMap;
        this.map = data.map || this.map;
        this.cityMessage = `Прописка обновлена: участок №${plotNumber}`;
        this.setModalSuccess(`Прописка перенесена на участок №${plotNumber}`);
      } catch (e) {
        this.setModalError(e?.message || 'Ошибка смены прописки');
      } finally {
        this.cityActionLoading = false;
      }
    },
    onBuildProject(payload) {
      if (this.cityActionLoading) {
        return;
      }

      this.openEstateModal({
        mode: 'confirm',
        title: 'Построить',
        message: payload?.projectLabel || payload?.projectCode,
        meta: 'Материалы будут списаны со стройки. Этап нельзя отменить.',
        confirmLabel: 'Построить',
        confirmClass: 'build',
        onConfirm: () => this.executeBuildProject(payload),
      });
    },
    async executeBuildProject({ plotNumber, projectCode, projectLabel }) {
      const token = this.authData?.token;
      if (!token || !this.selectedSlug || !plotNumber || !projectCode) {
        return;
      }

      this.cityActionLoading = true;
      this.cityError = '';
      this.cityMessage = '';
      try {
        const data = await apiActions.game.completeEstateBuildProject(
          token,
          this.selectedSlug,
          Number(plotNumber),
          projectCode,
        );
        if (data?.status !== 'ok') {
          throw new Error(data?.message || 'Не удалось построить');
        }
        this.cityMap = data.city || this.cityMap;
        const label = projectLabel || projectCode;
        this.cityMessage = `Построено: ${label}`;
        await this.loadMap();
        this.setModalSuccess(`Построено: ${label}`);
      } catch (e) {
        this.setModalError(e?.message || 'Ошибка постройки');
      } finally {
        this.cityActionLoading = false;
      }
    },
    onDonateComponent(payload) {
      if (this.cityActionLoading) {
        return;
      }

      const project = this.findProject(payload?.projectCode);
      const item = this.findProjectItem(project, payload?.componentCode);
      const qty = Math.max(1, Number(payload?.qty || 1));
      const have = Number(item?.user_have ?? project?.inventory?.[payload?.componentCode] ?? 0);
      const left = Number(project?.remaining?.[payload?.componentCode] || 0);
      const donateQty = Math.min(qty, left, have);

      this.openEstateModal({
        mode: 'confirm',
        title: 'Сдать компонент',
        message: `${item?.label || payload?.componentCode} ×${donateQty}`,
        meta: `В инвентаре: ${have} · осталось сдать: ${left}`,
        confirmLabel: 'Сдать',
        confirmClass: 'donate',
        onConfirm: () => this.executeDonateComponent({
          ...payload,
          qty: donateQty,
          componentLabel: item?.label || payload?.componentCode,
        }),
      });
    },
    async executeDonateComponent({ plotNumber, projectCode, componentCode, qty, componentLabel }) {
      const token = this.authData?.token;
      if (
        !token
        || !this.selectedSlug
        || !plotNumber
        || !projectCode
        || !componentCode
      ) {
        return;
      }

      this.cityActionLoading = true;
      this.cityError = '';
      this.cityMessage = '';
      try {
        const data = await apiActions.game.submitEstateBuildComponent(
          token,
          this.selectedSlug,
          Number(plotNumber),
          projectCode,
          componentCode,
          qty || 1,
        );
        if (data?.status !== 'ok') {
          throw new Error(data?.message || 'Не удалось сдать компонент');
        }
        this.cityMap = data.city || this.cityMap;
        const label = componentLabel || componentCode;
        this.cityMessage = `Сдано: ${label} ×${qty || 1}`;
        await this.loadMap();
        this.setModalSuccess(`Сдано: ${label} ×${qty || 1}`);
      } catch (e) {
        this.setModalError(e?.message || 'Ошибка сдачи компонента');
      } finally {
        this.cityActionLoading = false;
      }
    },
    onDonateProjectAll(payload) {
      if (this.cityActionLoading) {
        return;
      }

      const project = this.findProject(payload?.projectCode);
      const lines = this.donatableLines(project);
      if (!lines.length) {
        this.openEstateModal({
          mode: 'alert',
          title: 'Сдать все',
          message: 'Нет компонентов в инвентаре для сдачи.',
        });
        return;
      }

      this.openEstateModal({
        mode: 'confirm',
        title: `Сдать все: ${project?.label || payload?.projectCode}`,
        message: lines.map((row) => `${row.label} ×${row.qty}`).join('\n'),
        meta: 'Будут сданы только те позиции, что есть в инвентаре.',
        confirmLabel: 'Сдать все',
        confirmClass: 'donate',
        onConfirm: () => this.executeDonateProjectAll(payload, lines),
      });
    },
    async executeDonateProjectAll({ plotNumber, projectCode }, lines) {
      if (!this.authData?.token || !this.selectedSlug || !plotNumber || !projectCode || !lines.length) {
        return;
      }

      this.cityActionLoading = true;
      this.cityError = '';
      this.cityMessage = '';
      try {
        for (const row of lines) {
          await apiActions.game.submitEstateBuildComponent(
            this.authData.token,
            this.selectedSlug,
            Number(plotNumber),
            projectCode,
            row.code,
            row.qty,
          );
        }
        await this.loadCityMap(this.selectedSlug);
        await this.loadMap();
        this.cityMessage = `Проект ${projectCode}: сданы доступные компоненты`;
        this.setModalSuccess('Сданы все доступные компоненты из инвентаря.');
      } catch (e) {
        this.setModalError(e?.message || 'Ошибка массовой сдачи');
      } finally {
        this.cityActionLoading = false;
      }
    },
    onOrderComponent(payload) {
      if (this.cityActionLoading) {
        return;
      }

      const project = this.findProject(payload?.projectCode);
      const item = this.findProjectItem(project, payload?.componentCode);
      const left = this.orderableQty(project, payload?.componentCode);
      if (left <= 0) {
        return;
      }
      const payPerUnit = Number(item?.order_pay_per_unit || 0);

      this.openEstateModal({
        mode: 'confirm',
        title: 'Заказ на производство',
        message: item?.label || payload?.componentCode,
        meta: `Осталось для стройки: ${left} · заказ на бирже (вкладка «Усадьбы»). Готовое пойдёт на стройку.`,
        showQtyPicker: true,
        orderQty: 1,
        orderQtyMax: left,
        orderPayPerUnit: payPerUnit,
        confirmLabel: 'Разместить заказ',
        confirmClass: 'order',
        onConfirm: () => this.executeOrderComponent({
          ...payload,
          qty: this.resolveOrderModalQty(),
          componentLabel: item?.label || payload?.componentCode,
        }),
      });
    },
    async executeOrderComponent({ componentCode, qty, componentLabel, plotNumber, projectCode }) {
      const token = this.authData?.token;
      if (!token || !componentCode || !qty) {
        return;
      }

      this.cityActionLoading = true;
      this.cityError = '';
      this.cityMessage = '';
      try {
        const data = await apiActions.exchange.createEstateProductionOrder(
          token,
          componentCode,
          Number(qty),
          this.selectedSlug || '',
          Number(plotNumber || this.cityMap?.my_plot_number || 0),
          projectCode || '',
        );
        if (data?.status !== 'ok') {
          throw new Error(data?.message || 'Не удалось разместить заказ');
        }
        const label = componentLabel || componentCode;
        const order = data?.order || {};
        const reserved = order.pay_total_reserved || order.coin_escrow || '';
        this.cityMessage = `Заказ на производство: ${label} ×${qty}`;
        await this.loadCityMap(this.selectedSlug);
        this.setModalSuccess(
          `Заказ размещён: ${label} ×${qty}`
          + (reserved ? ` · зарезервировано ${reserved} 🪙` : ''),
        );
      } catch (e) {
        this.setModalError(e?.message || 'Ошибка заказа');
      } finally {
        this.cityActionLoading = false;
      }
    },
    onOrderProjectAll(payload) {
      if (this.cityActionLoading) {
        return;
      }

      const project = this.findProject(payload?.projectCode);
      const lines = this.orderableLines(project);
      if (!lines.length) {
        return;
      }

      this.openEstateModal({
        mode: 'confirm',
        title: `Заказать все: ${project?.label || payload?.projectCode}`,
        message: lines.map((row) => `${row.label} ×${row.qty}`).join('\n'),
        meta: 'Заказы на производство на бирже (вкладка «Усадьбы»). 🪙 резервируются под оплату; готовое — на стройку.',
        confirmLabel: 'Разместить заказы',
        confirmClass: 'order',
        onConfirm: () => this.executeOrderProjectAll(payload, lines),
      });
    },
    async executeOrderProjectAll(payload, lines) {
      if (!this.authData?.token || !lines.length) {
        return;
      }

      this.cityActionLoading = true;
      this.cityError = '';
      this.cityMessage = '';
      try {
        for (const row of lines) {
          await apiActions.exchange.createEstateProductionOrder(
            this.authData.token,
            row.code,
            row.qty,
            this.selectedSlug || '',
            Number(payload?.plotNumber || this.cityMap?.my_plot_number || 0),
            payload?.projectCode || '',
          );
        }
        this.cityMessage = 'Размещены заказы на производство недостающих компонентов';
        await this.loadCityMap(this.selectedSlug);
        this.setModalSuccess('Заказы на бирже размещены. Смотрите вкладку «Усадьбы».');
      } catch (e) {
        this.setModalError(e?.message || 'Ошибка массового заказа');
      } finally {
        this.cityActionLoading = false;
      }
    },
    onCancelComponentOrders(payload) {
      if (this.cityActionLoading || !Array.isArray(payload?.orderIds) || !payload.orderIds.length) {
        return;
      }

      const label = payload?.componentLabel || payload?.componentCode || 'компонент';
      const refundHint = this.estimateCancelRefund(payload.orderIds, payload?.projectCode);

      this.openEstateModal({
        mode: 'confirm',
        title: `Снять заказ: ${label}`,
        message: `Снять заказ на бирже по «${label}»?`,
        meta: refundHint
          ? `Заказ исчезнет из биржи, неиспользованный резерв вернётся в кошелёк (≈${refundHint} 🪙).`
          : 'Заказ исчезнет из биржи, неиспользованный резерв вернётся в кошелёк.',
        confirmLabel: 'Снять',
        confirmClass: 'danger',
        onConfirm: () => this.executeCancelOrders(payload.orderIds, `Заказ снят: ${label}`),
      });
    },
    onCancelProjectOrders(payload) {
      if (this.cityActionLoading || !Array.isArray(payload?.orderIds) || !payload.orderIds.length) {
        return;
      }

      const refundHint = this.estimateCancelRefund(payload.orderIds, payload?.projectCode);

      this.openEstateModal({
        mode: 'confirm',
        title: `Снять все: ${payload?.projectLabel || payload?.projectCode}`,
        message: `Снять ${payload.orderIds.length} заказ(ов) на бирже по этому этапу стройки?`,
        meta: refundHint
          ? `Неиспользованный резерв вернётся в кошелёк (≈${refundHint} 🪙).`
          : 'Неиспользованный резерв вернётся в кошелёк.',
        confirmLabel: 'Снять все',
        confirmClass: 'danger',
        onConfirm: () => this.executeCancelOrders(
          payload.orderIds,
          'Заказы на бирже сняты',
        ),
      });
    },
    estimateCancelRefund(orderIds, projectCode) {
      const project = this.findProject(projectCode);
      const orders = Array.isArray(project?.open_orders) ? project.open_orders : [];
      const idSet = new Set((orderIds || []).map((id) => Number(id)));
      const total = orders
        .filter((row) => idSet.has(Number(row.id)))
        .reduce((sum, row) => sum + Number(row.coin_escrow || 0), 0);
      if (total <= 0) {
        return '';
      }
      return total.toFixed(1).replace(/\.0$/, '');
    },
    async executeCancelOrders(orderIds, successMessage) {
      const token = this.authData?.token;
      if (!token || !Array.isArray(orderIds) || !orderIds.length) {
        return;
      }

      this.cityActionLoading = true;
      this.cityError = '';
      this.cityMessage = '';
      try {
        for (const orderId of orderIds) {
          const data = await apiActions.exchange.cancelEstateOrder(token, Number(orderId));
          if (data?.status !== 'ok') {
            throw new Error(data?.message || 'Не удалось снять заказ');
          }
          this.applyGame(data.game);
        }
        await this.loadCityMap(this.selectedSlug);
        this.cityMessage = successMessage;
        this.setModalSuccess(successMessage);
      } catch (e) {
        this.setModalError(e?.message || 'Ошибка снятия заказа');
      } finally {
        this.cityActionLoading = false;
      }
    },
    findProject(projectCode) {
      return (this.cityMap?.my_estate_projects || []).find(
        (row) => String(row.recipe_code || '') === String(projectCode || ''),
      ) || null;
    },
    findProjectItem(project, componentCode) {
      const items = project?.needed_items || [];
      return items.find((row) => String(row.code || '') === String(componentCode || '')) || null;
    },
    orderedQty(project, componentCode) {
      const item = this.findProjectItem(project, componentCode);
      if (item && Number.isFinite(Number(item.ordered_qty))) {
        return Number(item.ordered_qty);
      }
      return Number(project?.ordered?.[componentCode] || 0);
    },
    orderableQty(project, componentCode) {
      const need = Number(project?.remaining?.[componentCode] || 0);
      return Math.max(0, need - this.orderedQty(project, componentCode));
    },
    donatableLines(project) {
      const remaining = project?.remaining || {};
      const inventory = project?.inventory || {};
      const lines = [];

      Object.keys(remaining).forEach((code) => {
        const need = Number(remaining[code] || 0);
        const have = Number(inventory[code] || 0);
        const qty = Math.min(need, have);
        if (qty <= 0) {
          return;
        }
        const item = this.findProjectItem(project, code);
        lines.push({
          code,
          label: item?.label || code,
          qty,
        });
      });

      return lines;
    },
    orderableLines(project) {
      const remaining = project?.remaining || {};
      const lines = [];

      Object.keys(remaining).forEach((code) => {
        const qty = this.orderableQty(project, code);
        if (qty <= 0) {
          return;
        }
        const item = this.findProjectItem(project, code);
        lines.push({
          code,
          label: item?.label || code,
          qty,
        });
      });

      return lines;
    },
    openEstateModal({
      mode,
      kind = '',
      title,
      message = '',
      meta = '',
      confirmLabel = '',
      confirmClass = 'primary',
      showQtyPicker = false,
      orderQty = 1,
      orderQtyMax = 1,
      orderPayPerUnit = 0,
      onConfirm = null,
      bankBranches = [],
      bankBranchesLoading = false,
      pendingCount = 0,
      buildingComplete = false,
    }) {
      this.estateModal = {
        kind,
        mode,
        title,
        message,
        meta,
        confirmLabel,
        confirmClass,
        showQtyPicker,
        orderQty: Math.max(1, Number(orderQty) || 1),
        orderQtyMax: Math.max(1, Number(orderQtyMax) || 1),
        orderPayPerUnit: Number(orderPayPerUnit) || 0,
        onConfirm,
        loading: false,
        error: '',
        success: '',
        bankBranches,
        bankBranchesLoading,
        pendingCount,
        buildingComplete,
      };
    },
    resolveOrderModalQty() {
      const modal = this.estateModal;
      if (!modal) {
        return 1;
      }

      const max = Math.max(1, Number(modal.orderQtyMax) || 1);
      return Math.max(1, Math.min(max, Number(modal.orderQty) || 1));
    },
    setOrderModalQtyMin() {
      if (!this.estateModal) {
        return;
      }
      this.estateModal.orderQty = 1;
    },
    setOrderModalQtyMax() {
      if (!this.estateModal) {
        return;
      }
      this.estateModal.orderQty = Math.max(1, Number(this.estateModal.orderQtyMax) || 1);
    },
    closeEstateModal() {
      if (this.estateModal?.loading) {
        return;
      }
      this.estateModal = null;
    },
    setModalError(message) {
      if (!this.estateModal) {
        this.cityError = message;
        return;
      }
      if (this.estateModal.kind !== 'bank-branches') {
        this.estateModal.mode = 'alert';
      }
      this.estateModal.error = message;
      this.estateModal.success = '';
      this.estateModal.loading = false;
    },
    setModalSuccess(message) {
      if (!this.estateModal) {
        this.cityMessage = message;
        return;
      }
      if (this.estateModal.kind !== 'bank-branches') {
        this.estateModal.mode = 'alert';
      }
      this.estateModal.success = message;
      this.estateModal.error = '';
      this.estateModal.loading = false;
    },
    async confirmEstateModal() {
      if (!this.estateModal?.onConfirm || this.estateModal.loading) {
        return;
      }

      this.estateModal.loading = true;
      this.estateModal.error = '';
      this.estateModal.success = '';
      try {
        await this.estateModal.onConfirm();
      } catch (e) {
        this.setModalError(e?.message || 'Ошибка выполнения');
      } finally {
        if (this.estateModal) {
          this.estateModal.loading = false;
        }
      }
    },
    async onBankBranches() {
      const token = this.authData?.token;
      if (!token || !this.selectedSlug) {
        return;
      }

      const cityName = this.cityMap?.city_name || this.selectedSlug;
      this.openEstateModal({
        kind: 'bank-branches',
        mode: 'bank-branches',
        title: 'Филиалы банков',
        message: cityName,
        bankBranches: [],
        bankBranchesLoading: true,
        pendingCount: 0,
        buildingComplete: false,
      });

      try {
        const data = await apiActions.game.getCityBankBranches(token, this.selectedSlug);
        if (data?.status !== 'ok') {
          throw new Error(data?.message || 'Не удалось загрузить филиалы');
        }
        if (!this.estateModal || this.estateModal.kind !== 'bank-branches') {
          return;
        }
        this.estateModal.bankBranches = Array.isArray(data.branches) ? data.branches : [];
        this.estateModal.pendingCount = Number(data.pending_count) || 0;
        this.estateModal.buildingComplete = Boolean(data.building_complete);
        this.estateModal.bankBranchesLoading = false;
      } catch (e) {
        if (this.estateModal?.kind === 'bank-branches') {
          this.setModalError(e?.message || 'Ошибка загрузки филиалов');
          this.estateModal.bankBranchesLoading = false;
        }
      }
    },
    async confirmAdminOpenBranches() {
      const token = this.authData?.token;
      if (!token || !this.selectedSlug || !this.canImpersonate || !this.estateModal) {
        return;
      }

      this.estateModal.loading = true;
      this.estateModal.error = '';
      this.estateModal.success = '';
      try {
        const data = await apiActions.game.adminOpenCityBankBranches(token, this.selectedSlug);
        if (data?.status !== 'ok') {
          throw new Error(data?.message || 'Не удалось открыть филиалы');
        }
        this.estateModal.bankBranches = Array.isArray(data.branches) ? data.branches : [];
        this.estateModal.pendingCount = Number(data.pending_count) || 0;
        const opened = Number(data.opened_count) || 0;
        const failed = Array.isArray(data.failed) ? data.failed.length : 0;
        let message = `Открыто филиалов: ${opened}`;
        if (failed > 0) {
          message += `, ошибок: ${failed}`;
        }
        this.estateModal.success = message;
      } catch (e) {
        this.setModalError(e?.message || 'Ошибка массового открытия');
      } finally {
        if (this.estateModal) {
          this.estateModal.loading = false;
        }
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.estate_page {
  text-align: left;
}

.intro {
  margin-bottom: 10px;
}

.section_card {
  background: fade(@DarkColorBG, 55%);
  border-radius: 6px;
  padding: 10px;
  margin-bottom: 10px;
}

.section_title_row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.section_title {
  font-size: 13px;
  font-weight: 600;
  color: @colorText;
}

.back_btn {
  border: 1px solid fade(@colorBlur, 40%);
  background: fade(@DarkColorBG, 70%);
  color: @colorText;
  border-radius: 4px;
  padding: 3px 8px;
  font-size: 11px;
  cursor: pointer;
}

.street_hint {
  margin-bottom: 8px;
}

.hint {
  color: @colorBlur;
  font-size: 12px;
}

.error {
  color: #f08080;
  font-size: 12px;
}

.message {
  color: #9ee09e;
  font-size: 12px;
  margin-bottom: 8px;
}

.estate_modal_overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
  background: fade(#000, 55%);
}

.estate_modal {
  width: 100%;
  max-width: 360px;
  padding: 12px;
  border-radius: 6px;
  background: @DarkColorBG;
  border: 1px solid fade(@orange, 45%);
  color: @colorText;
}

.estate_modal_title {
  font-size: 14px;
  font-weight: 700;
  color: @orange;
  margin-bottom: 6px;
}

.estate_modal_message {
  font-size: 12px;
  color: @colorText;
  line-height: 1.4;
  margin: 0 0 8px;
  white-space: pre-line;
}

.estate_modal_preview {
  margin-bottom: 10px;
}

.estate_modal_meta {
  font-size: 11px;
  color: @colorBlur;
  line-height: 1.35;
  margin: 0 0 10px;
}

.estate_modal_branches {
  margin-bottom: 8px;
}

.bank_branches_list {
  list-style: none;
  margin: 0 0 8px;
  padding: 0;
  max-height: 220px;
  overflow-y: auto;
}

.bank_branch_row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border: 1px solid fade(@colorBlur, 25%);
  border-radius: 4px;
  margin-bottom: 4px;
  font-size: 12px;
}

.bank_branch_name {
  color: @colorText;
}

.bank_branch_meta {
  color: @colorBlur;
  font-size: 10px;
  white-space: nowrap;
}

.estate_modal_qty_row {
  margin: 0 0 10px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.estate_modal_qty_label {
  font-size: 12px;
  color: @colorText;
}

.estate_modal_qty_controls {
  display: flex;
  align-items: center;
  gap: 4px;
}

.estate_modal_qty_btn {
  border: 1px solid fade(@colorBlur, 40%);
  border-radius: 4px;
  background: fade(@DarkColorBG, 70%);
  color: @colorBlur;
  font-size: 10px;
  font-weight: 600;
  line-height: 1;
  padding: 6px 7px;
  cursor: pointer;
  text-transform: lowercase;

  &:disabled {
    opacity: 0.5;
    cursor: default;
  }
}

.estate_modal_qty_input {
  width: 72px;
  padding: 5px 8px;
  border: 1px solid fade(@colorBlur, 40%);
  border-radius: 4px;
  background: fade(@DarkColorBG, 70%);
  color: @colorText;
  font-size: 12px;
}

.estate_modal_qty_hint {
  font-size: 11px;
  color: @colorBlur;
}

.estate_modal_reserve {
  width: 100%;
  margin: 2px 0 0;
  font-size: 11px;
  color: @colorText;
}

.estate_modal_error {
  font-size: 12px;
  color: #f08080;
  margin: 0 0 10px;
}

.estate_modal_success {
  font-size: 12px;
  color: #9ee09e;
  margin: 0 0 10px;
}

.estate_modal_actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.modal_btn {
  border-radius: 4px;
  font-size: 11px;
  padding: 5px 10px;
  cursor: pointer;
  border: 1px solid fade(@colorBlur, 40%);
  background: fade(@DarkColorBG, 70%);
  color: @colorText;

  &.primary {
    border-color: fade(@orange, 60%);
    background: fade(@orange, 16%);
  }

  &.donate {
    border-color: fade(@orange, 60%);
    background: fade(@orange, 16%);
  }

  &.withdraw {
    border-color: fade(#a67c52, 60%);
    background: fade(#6b4428, 22%);
  }

  &.build {
    border-color: fade(@orange, 75%);
    background: fade(@orange, 28%);
  }

  &.order {
    border-color: fade(@YesWrite, 60%);
    background: fade(@YesWrite, 15%);
  }

  &.danger {
    border-color: fade(#f08080, 65%);
    background: fade(#f08080, 14%);
  }

  &.secondary {
    color: @colorBlur;
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}
</style>
