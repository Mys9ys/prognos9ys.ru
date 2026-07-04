<template>
  <div class="equipment_block" v-if="game">
    <div class="equipment_bonuses" v-if="hasBodyBonus">
      <div class="bonus_title">Бонусы кафтана на ферме</div>
      <div class="bonus_row">
        <span>Комбо ×2</span>
        <span>+{{ equipment.combo_x2_bonus_pp || 0 }} п.п.</span>
      </div>
      <div class="bonus_row">
        <span>Комбо ×3</span>
        <span>+{{ equipment.combo_x3_bonus_pp || 0 }} п.п.</span>
      </div>
      <div class="bonus_row">
        <span>Премиум-материал</span>
        <span>+{{ equipment.premium_bonus_pp || 0 }} п.п.</span>
      </div>
    </div>

    <div class="paper_doll">
      <div class="doll_row doll_row_top">
        <EquipmentSlotCell
          :equip-slot="slotById('head')"
          :icon-src="null"
          @action="onSlotAction"
        />
      </div>

      <div class="doll_row doll_row_upper">
        <EquipmentSlotCell
          :equip-slot="slotById('ring_left')"
          :icon-src="null"
          @action="onSlotAction"
        />
        <EquipmentSlotCell
          :equip-slot="slotById('amulet')"
          :icon-src="null"
          @action="onSlotAction"
        />
        <EquipmentSlotCell
          :equip-slot="slotById('ring_right')"
          :icon-src="null"
          @action="onSlotAction"
        />
      </div>

      <div class="doll_row doll_row_mid">
        <EquipmentSlotCell
          :equip-slot="slotById('gloves')"
          :icon-src="null"
          @action="onSlotAction"
        />
        <EquipmentSlotCell
          :equip-slot="bodySlot"
          :icon-src="bodyIconSrc"
          :highlight="true"
          @action="onSlotAction"
        />
        <EquipmentSlotCell
          :equip-slot="slotById('cloak')"
          :icon-src="null"
          @action="onSlotAction"
        />
      </div>

      <div class="doll_row doll_row_lower">
        <EquipmentSlotCell
          :equip-slot="slotById('belt')"
          :icon-src="null"
          @action="onSlotAction"
        />
        <EquipmentSlotCell
          :equip-slot="slotById('boots')"
          :icon-src="null"
          @action="onSlotAction"
        />
      </div>

      <div class="doll_silhouette" aria-hidden="true">🧍</div>
    </div>

    <div class="bag_section">
      <div class="section_title">Сумка — кафтаны</div>
      <div v-if="!bagCaftans.length" class="hint">Скрафти кафтан у ткача или найди в сундуках профессий.</div>
      <div v-else class="bag_grid">
        <button
          v-for="item in bagCaftans"
          :key="item.code"
          type="button"
          class="bag_item"
          :class="{ equipped: item.equipped, busy: loading }"
          :disabled="loading || item.equipped"
          @click="equipItem(item)"
        >
          <img v-if="item.iconSrc" :src="item.iconSrc" :alt="item.label" class="bag_icon">
          <span v-else class="bag_emoji">🥋</span>
          <span class="bag_label">{{ item.shortLabel }}</span>
          <span class="bag_count">×{{ item.count }}</span>
          <span class="bag_action">{{ item.equipped ? 'Надет' : 'Надеть' }}</span>
        </button>
      </div>
    </div>

    <div class="equipment_actions" v-if="equipment.equipped_caftan">
      <button type="button" class="btn secondary" :disabled="loading" @click="unequipBody">
        Снять кафтан
      </button>
    </div>

    <div class="msg error" v-if="error">{{ error }}</div>
    <div class="msg ok" v-if="message">{{ message }}</div>

    <div class="future_hint">
      Слоты с пометкой «скоро» — обувь, кольца, перчатки и другое появятся в крафте и сундуках.
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import EquipmentSlotCell from '@/components/profile/EquipmentSlotCell.vue';
import { getCraftProductIconSrc } from '@/config/craftIcons';
import { apiActions } from '@/api/bitrixClient';

const EMPTY_SLOT = {
  id: '',
  label: '',
  enabled: false,
  equipped_code: null,
  equipped_label: null,
};

export default {
  name: 'ProfileEquipmentBlock',
  components: { EquipmentSlotCell },
  props: {
    game: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      loading: false,
      error: '',
      message: '',
    };
  },
  computed: {
    ...mapState('auth', ['authData']),
    equipment() {
      return this.game?.equipment || {};
    },
    slots() {
      return Array.isArray(this.equipment.slots) ? this.equipment.slots : [];
    },
    bodySlot() {
      return this.slotById('body');
    },
    bodyIconSrc() {
      const code = this.equipment.equipped_caftan;
      return code ? getCraftProductIconSrc(code) : null;
    },
    hasBodyBonus() {
      return Boolean(this.equipment.equipped_caftan);
    },
    bagCaftans() {
      const equipped = this.equipment.equipped_caftan || '';
      const items = Array.isArray(this.game?.inventory_items) ? this.game.inventory_items : [];

      return items
        .filter((item) => item.category === 'equipment' && String(item.code || '').startsWith('caftan_'))
        .map((item) => ({
          code: item.code,
          label: item.label || item.code,
          shortLabel: String(item.label || item.code).replace(/^Кафтан\s+[^:]+:\s*/i, '').replace(/^Кафтан\s*/i, ''),
          count: Number(item.count) || 0,
          equipped: equipped === item.code,
          iconSrc: getCraftProductIconSrc(item.code),
        }))
        .sort((a, b) => a.label.localeCompare(b.label, 'ru'));
    },
  },
  methods: {
    ...mapActions('auth', ['refreshGameInfo']),
    slotById(id) {
      return this.slots.find((slot) => slot.id === id) || { ...EMPTY_SLOT, id, label: id };
    },
    onSlotAction(slot) {
      if (!slot?.enabled) {
        return;
      }
      if (slot.id === 'body' && slot.equipped_code) {
        this.unequipBody();
      }
    },
    async patchGameInfo(game) {
      if (game && this.$store.state.auth?.userInfo) {
        this.$store.commit('auth/setUserInfo', {
          ...this.$store.state.auth.userInfo,
          game_info: game,
        }, { root: true });
      } else {
        await this.refreshGameInfo();
      }
      window.dispatchEvent(new CustomEvent('prognos9ys:farm-refresh'));
    },
    async equipItem(item) {
      if (!this.authData?.token || this.loading || !item?.code || item.equipped) {
        return;
      }

      this.loading = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.game.equipCaftan(this.authData.token, item.code);
        if (data?.status === 'ok') {
          this.message = (data.lines || []).map((line) => line.text).filter(Boolean).join('. ')
            || `Надет: ${item.label}`;
          await this.patchGameInfo(data.game);
        } else {
          this.error = 'Не удалось надеть';
        }
      } catch (e) {
        this.error = e.message || 'Ошибка экипировки';
      } finally {
        this.loading = false;
      }
    },
    async unequipBody() {
      if (!this.authData?.token || this.loading) {
        return;
      }

      this.loading = true;
      this.error = '';
      this.message = '';

      try {
        const data = await apiActions.game.unequipCaftan(this.authData.token);
        if (data?.status === 'ok') {
          this.message = (data.lines || []).map((line) => line.text).filter(Boolean).join('. ')
            || 'Кафтан снят';
          await this.patchGameInfo(data.game);
        } else {
          this.error = 'Не удалось снять';
        }
      } catch (e) {
        this.error = e.message || 'Ошибка';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.equipment_block {
  display: flex;
  flex-direction: column;
  gap: 10px;
  color: @colorText;
}

.equipment_bonuses {
  .shadow_inset;
  padding: 8px;
  border-radius: 5px;
  font-size: 12px;
}

.bonus_title {
  color: @colorBlur;
  margin-bottom: 4px;
}

.bonus_row {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding: 2px 0;
}

.paper_doll {
  position: relative;
  .shadow_inset;
  border-radius: 8px;
  padding: 10px 8px 12px;
  overflow: hidden;
}

.doll_silhouette {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 72px;
  opacity: 0.08;
  pointer-events: none;
}

.doll_row {
  display: flex;
  justify-content: center;
  gap: 8px;
  position: relative;
  z-index: 1;
}

.doll_row_top {
  margin-bottom: 6px;
}

.doll_row_upper,
.doll_row_mid,
.doll_row_lower {
  margin-bottom: 8px;
}

.doll_row_lower {
  margin-bottom: 0;
}

.bag_section {
  .shadow_inset;
  padding: 8px;
  border-radius: 5px;
}

.section_title {
  font-size: 13px;
  margin-bottom: 6px;
}

.bag_grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
}

.bag_item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 6px 4px;
  border: none;
  border-radius: 5px;
  background: @colorText2;
  color: @colorText;
  cursor: pointer;
  min-height: 92px;

  &.equipped {
    outline: 2px solid @orange;
  }

  &:disabled {
    opacity: 0.65;
    cursor: default;
  }
}

.bag_icon {
  width: 40px;
  height: 40px;
  object-fit: contain;
}

.bag_emoji {
  font-size: 28px;
  line-height: 1;
}

.bag_label,
.bag_count,
.bag_action {
  font-size: 10px;
  text-align: center;
  line-height: 1.2;
}

.bag_action {
  color: @orange;
}

.equipment_actions {
  display: flex;
  justify-content: center;
}

.btn {
  border: none;
  border-radius: 5px;
  padding: 8px 12px;
  background: @orange;
  color: @colorText;
  cursor: pointer;

  &.secondary {
    background: @colorText2;
  }

  &:disabled {
    opacity: 0.6;
    cursor: default;
  }
}

.hint,
.future_hint {
  font-size: 11px;
  color: @colorBlur;
  text-align: center;
  line-height: 1.35;
}

.msg {
  font-size: 12px;
  text-align: center;

  &.error { color: #f88; }
  &.ok { color: #8f8; }
}
</style>
