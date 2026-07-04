<template>
  <button
    type="button"
    class="equip_slot"
    :class="{
      enabled: equipSlot.enabled,
      equipped: Boolean(equipSlot.equipped_code),
      highlight,
      disabled: !equipSlot.enabled,
    }"
    :disabled="!equipSlot.enabled"
    :title="slotTitle"
    @click="$emit('action', equipSlot)"
  >
    <img v-if="iconSrc" :src="iconSrc" :alt="equipSlot.label" class="slot_icon">
    <span v-else class="slot_placeholder">{{ equipSlot.enabled ? '＋' : '🔒' }}</span>
    <span class="slot_label">{{ equipSlot.label }}</span>
    <span v-if="equipSlot.equipped_label" class="slot_item">{{ shortLabel }}</span>
    <span v-else-if="!equipSlot.enabled" class="slot_hint">скоро</span>
  </button>
</template>

<script>
export default {
  name: 'EquipmentSlotCell',
  props: {
    equipSlot: {
      type: Object,
      required: true,
    },
    iconSrc: {
      type: String,
      default: null,
    },
    highlight: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['action'],
  computed: {
    shortLabel() {
      const label = String(this.equipSlot.equipped_label || '');
      return label.replace(/^Кафтан\s*/i, '').trim() || label;
    },
    slotTitle() {
      if (!this.equipSlot.enabled) {
        return `${this.equipSlot.label} — скоро`;
      }
      if (this.equipSlot.equipped_label) {
        return `${this.equipSlot.label}: ${this.equipSlot.equipped_label}`;
      }
      return this.equipSlot.label;
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.equip_slot {
  width: 78px;
  min-height: 78px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  padding: 4px;
  border: 1px dashed fade(@colorBlur, 45%);
  border-radius: 6px;
  background: fade(@darkbg, 55%);
  color: @colorText;
  cursor: default;

  &.enabled {
    border-style: solid;
    border-color: fade(@orange, 55%);
    cursor: pointer;
  }

  &.equipped {
    border-color: @orange;
    background: fade(@orange, 12%);
  }

  &.highlight {
    width: 96px;
    min-height: 96px;
  }
}

.slot_icon {
  width: 42px;
  height: 42px;
  object-fit: contain;
}

.slot_placeholder {
  font-size: 18px;
  line-height: 1;
  opacity: 0.7;
}

.slot_label {
  font-size: 9px;
  color: @colorBlur;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.slot_item {
  font-size: 10px;
  text-align: center;
  line-height: 1.15;
  color: @colorText;
}

.slot_hint {
  font-size: 9px;
  color: @colorBlur;
}
</style>
