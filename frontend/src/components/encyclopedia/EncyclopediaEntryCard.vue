<template>
  <router-link class="ency_card" :to="entryRoute">
    <div class="ency_card_media">
      <img v-if="iconSrc" class="ency_card_icon" :src="iconSrc" alt="">
      <span v-else-if="entry.emoji" class="ency_card_emoji">{{ entry.emoji }}</span>
      <span v-else class="ency_card_placeholder">?</span>
    </div>
    <div class="ency_card_body">
      <div class="ency_card_title">{{ entry.label }}</div>
      <div v-if="subtitle" class="ency_card_sub">{{ subtitle }}</div>
    </div>
  </router-link>
</template>

<script>
import { getCraftProductIconSrc, getCraftRecipeIconSrc } from '@/config/craftIcons';

const TIER_LABELS = {
  basic: 'базовый',
  embroidered: 'вышитый',
  grand: 'великий',
  advanced: 'продвинутый',
};

export default {
  name: 'EncyclopediaEntryCard',
  props: {
    sectionId: {
      type: String,
      required: true,
    },
    entry: {
      type: Object,
      required: true,
    },
  },
  computed: {
    entryRoute() {
      return `/encyclopedia/${this.sectionId}/${encodeURIComponent(this.entry.code)}`;
    },
    iconSrc() {
      if (this.sectionId === 'recipes') {
        return getCraftRecipeIconSrc(this.entry.code);
      }
      if (this.sectionId === 'equipment' || this.sectionId === 'materials') {
        return getCraftProductIconSrc(this.entry.code);
      }
      return null;
    },
    subtitle() {
      const parts = [];
      if (this.entry.nominal != null && this.sectionId !== 'buildings') {
        parts.push(`ном. ${this.formatNominal(this.entry.nominal)}`);
      }
      if (this.entry.profession_label) {
        parts.push(this.entry.profession_label);
      } else if (this.entry.type === 'gather') {
        parts.push('добыча');
      } else if (this.entry.type === 'process') {
        parts.push('переработка');
      }
      if (this.entry.tier && TIER_LABELS[this.entry.tier]) {
        parts.push(TIER_LABELS[this.entry.tier]);
      }
      if (this.sectionId === 'buildings' && this.entry.nominal_total) {
        parts.push(`смета ${this.formatNominal(this.entry.nominal_total)}`);
      }
      if (this.sectionId === 'packs') {
        if (this.entry.openable) parts.push('открывается');
        else if (this.entry.stub) parts.push('скоро');
      }
      return parts.join(' · ');
    },
  },
  methods: {
    formatNominal(value) {
      const num = Number(value);
      if (!Number.isFinite(num)) return String(value);
      return Number.isInteger(num) ? String(num) : num.toFixed(1);
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.ency_card {
  display: flex;
  gap: 8px;
  align-items: center;
  padding: 8px;
  border-radius: 6px;
  background: fade(@DarkColorBG, 85%);
  border: 1px solid fade(@colorBlur, 25%);
  text-decoration: none;
  text-align: left;

  &:hover {
    border-color: fade(@orange, 55%);
  }
}

.ency_card_media {
  flex: 0 0 36px;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ency_card_icon {
  width: 32px;
  height: 32px;
  object-fit: contain;
}

.ency_card_emoji {
  font-size: 24px;
  line-height: 1;
}

.ency_card_placeholder {
  font-size: 18px;
  color: @colorBlur;
}

.ency_card_title {
  font-size: 12px;
  font-weight: 700;
  color: @colorText;
  line-height: 1.25;
}

.ency_card_sub {
  margin-top: 2px;
  font-size: 10px;
  color: @colorBlur;
  line-height: 1.3;
}
</style>
