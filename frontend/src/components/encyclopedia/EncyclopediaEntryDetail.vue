<template>
  <div class="ency_detail">
    <router-link class="ency_back" :to="listRoute">← {{ sectionLabel }}</router-link>

    <div class="ency_header">
      <img v-if="iconSrc" class="ency_icon" :src="iconSrc" alt="">
      <span v-else-if="entry.emoji" class="ency_emoji">{{ entry.emoji }}</span>
      <div>
        <h1 class="ency_title">{{ entry.label }}</h1>
        <div v-if="metaLine" class="ency_meta">{{ metaLine }}</div>
      </div>
    </div>

    <div v-if="showGrant" class="ency_grant">
      <button
        type="button"
        class="ency_grant_btn"
        :disabled="granting"
        @click="grantItem"
      >
        {{ granting ? 'Выдаём…' : 'Получить ×1' }}
      </button>
      <span v-if="grantMessage" class="ency_grant_msg" :class="{ error: grantError }">{{ grantMessage }}</span>
    </div>

    <p v-if="entry.lore" class="ency_lore">{{ entry.lore }}</p>
    <p v-else class="ency_lore_empty">Описание пока не добавлено — загляните позже.</p>

    <section v-if="entry.inputs && entry.inputs.length" class="ency_block">
      <h2>Нужно</h2>
      <ul>
        <li v-for="row in entry.inputs" :key="'in-' + row.code">
          <router-link
            v-if="linkForCode(row.code)"
            :to="linkForCode(row.code)"
          >{{ row.label }}</router-link>
          <span v-else>{{ row.label }}</span>
          × {{ row.qty }}
        </li>
      </ul>
    </section>

    <section v-if="entry.outputs && entry.outputs.length" class="ency_block">
      <h2>Получается</h2>
      <ul>
        <li v-for="row in entry.outputs" :key="'out-' + row.code">
          <router-link
            v-if="linkForCode(row.code)"
            :to="linkForCode(row.code)"
          >{{ row.label }}</router-link>
          <span v-else>{{ row.label }}</span>
          × {{ row.qty }}
        </li>
      </ul>
    </section>

    <section v-if="entry.components && entry.components.length" class="ency_block">
      <h2>Компоненты</h2>
      <ul>
        <li v-for="row in entry.components" :key="'cmp-' + row.code">
          <router-link
            v-if="linkForCode(row.code)"
            :to="linkForCode(row.code)"
          >{{ row.label }}</router-link>
          <span v-else>{{ row.label }}</span>
          × {{ row.qty }}
        </li>
      </ul>
    </section>

    <section v-if="entry.drops && entry.drops.length" class="ency_block">
      <h2>Возможное содержимое</h2>
      <ul>
        <li v-for="row in entry.drops" :key="'drop-' + row.code">
          {{ row.label }}
          <span class="ency_weight">(вес {{ row.weight }})</span>
        </li>
      </ul>
    </section>

    <section v-if="professionFacts.length" class="ency_block">
      <h2>Профессия</h2>
      <ul>
        <li v-for="fact in professionFacts" :key="fact">{{ fact }}</li>
      </ul>
    </section>

    <section v-if="equipmentFacts.length" class="ency_block">
      <h2>Бонусы на ферме</h2>
      <ul>
        <li v-for="fact in equipmentFacts" :key="fact">{{ fact }}</li>
      </ul>
    </section>

    <section v-if="buildingFacts.length" class="ency_block">
      <h2>Проект</h2>
      <ul>
        <li v-for="fact in buildingFacts" :key="fact">{{ fact }}</li>
      </ul>
    </section>
  </div>
</template>

<script>
import { getCraftProductIconSrc, getCraftRecipeIconSrc } from '@/config/craftIcons';
import { apiActions } from '@/api/bitrixClient';

const TIER_LABELS = {
  basic: 'базовый',
  embroidered: 'вышитый',
  grand: 'великий',
  advanced: 'продвинутый',
};

export default {
  name: 'EncyclopediaEntryDetail',
  props: {
    sectionId: {
      type: String,
      required: true,
    },
    sectionLabel: {
      type: String,
      default: '',
    },
    entry: {
      type: Object,
      required: true,
    },
    codeIndex: {
      type: Object,
      default: () => ({}),
    },
    canGrant: {
      type: Boolean,
      default: false,
    },
    grantSections: {
      type: Array,
      default: () => [],
    },
    userToken: {
      type: String,
      default: '',
    },
  },
  data() {
    return {
      granting: false,
      grantMessage: '',
      grantError: false,
    };
  },
  computed: {
    listRoute() {
      return `/encyclopedia?section=${encodeURIComponent(this.sectionId)}`;
    },
    showGrant() {
      return this.canGrant
        && this.userToken
        && this.grantSections.includes(this.sectionId);
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
    metaLine() {
      const parts = [];
      if (this.entry.code) parts.push(this.entry.code);
      if (this.entry.nominal != null && this.sectionId !== 'buildings') {
        parts.push(`номинал ${this.formatNominal(this.entry.nominal)}`);
      }
      if (this.entry.tier && TIER_LABELS[this.entry.tier]) {
        parts.push(TIER_LABELS[this.entry.tier]);
      }
      if (this.entry.work_cost) parts.push(`работа ${this.entry.work_cost}`);
      if (this.entry.craft_xp) parts.push(`XP ${this.entry.craft_xp}`);
      return parts.join(' · ');
    },
    professionFacts() {
      if (this.sectionId !== 'professions') return [];
      const facts = [];
      if (this.entry.output_label) {
        facts.push(`Основной продукт: ${this.entry.output_label}`);
      }
      if (this.entry.premium_label) {
        facts.push(`Премиум: ${this.entry.premium_label}`);
      }
      if (this.entry.input_label) {
        facts.push(`Сырьё: ${this.entry.input_label}`);
      }
      if (this.entry.intermediate_code) {
        facts.push(`Промежуточный материал: ${this.entry.intermediate_code}`);
      }
      if (this.entry.fine_code) {
        facts.push(`Тонкий материал: ${this.entry.fine_code}`);
      }
      return facts;
    },
    equipmentFacts() {
      if (this.sectionId !== 'equipment') return [];
      const facts = [];
      if (this.entry.profession_label) {
        facts.push(`Для профессии: ${this.entry.profession_label}`);
      }
      if (this.entry.combo_x2_bonus) {
        facts.push(`Бонус к комбо ×2: +${this.formatPercent(this.entry.combo_x2_bonus)}`);
      }
      if (this.entry.combo_x3_bonus) {
        facts.push(`Бонус к комбо ×3: +${this.formatPercent(this.entry.combo_x3_bonus)}`);
      }
      if (this.entry.premium_bonus) {
        facts.push(`Бонус к премиум-дропу: +${this.formatPercent(this.entry.premium_bonus)}`);
      }
      return facts;
    },
    buildingFacts() {
      if (this.sectionId !== 'buildings') return [];
      const facts = [];
      if (this.entry.kind) facts.push(`Тип: ${this.entry.kind}`);
      if (this.entry.progress_total) facts.push(`Объём работ: ${this.entry.progress_total}`);
      if (this.entry.nominal_total) facts.push(`Смета: ${this.formatNominal(this.entry.nominal_total)}`);
      if (this.entry.requires) facts.push(`Требует: ${this.entry.requires}`);
      if (this.entry.unlock) facts.push(`Открывает: ${this.entry.unlock}`);
      if (this.entry.opens_city_map) facts.push('Открывает карту города');
      return facts;
    },
  },
  methods: {
    formatNominal(value) {
      const num = Number(value);
      if (!Number.isFinite(num)) return String(value);
      return Number.isInteger(num) ? String(num) : num.toFixed(1);
    },
    formatPercent(value) {
      return `${(Number(value) * 100).toFixed(1)}%`;
    },
    linkForCode(code) {
      return this.codeIndex[code] || null;
    },
    async grantItem() {
      if (!this.showGrant || this.granting) {
        return;
      }

      this.granting = true;
      this.grantMessage = '';
      this.grantError = false;

      try {
        const data = await apiActions.game.grantEncyclopediaItem(
          this.userToken,
          this.sectionId,
          this.entry.code,
          1,
          Boolean(this.entry.is_premium),
        );
        if (data.status !== 'ok') {
          throw new Error(data.message || 'Не удалось выдать предмет');
        }
        const after = data.qty_after != null ? ` (в инвентаре: ${data.qty_after})` : '';
        this.grantMessage = `Выдано: ${data.label || this.entry.label}${after}`;
      } catch (err) {
        this.grantError = true;
        this.grantMessage = err.message || 'Ошибка выдачи';
      } finally {
        this.granting = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.ency_detail {
  text-align: left;
  padding-bottom: 12px;
}

.ency_back {
  display: inline-block;
  margin-bottom: 10px;
  font-size: 12px;
  color: @YesWrite;
  text-decoration: none;
}

.ency_header {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 10px;
}

.ency_icon {
  width: 48px;
  height: 48px;
  object-fit: contain;
}

.ency_emoji {
  font-size: 40px;
  line-height: 1;
}

.ency_title {
  margin: 0;
  font-size: 16px;
  color: @colorText;
}

.ency_meta {
  margin-top: 4px;
  font-size: 11px;
  color: @colorBlur;
}

.ency_grant {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
}

.ency_grant_btn {
  padding: 6px 12px;
  border-radius: 4px;
  border: 1px solid fade(@orange, 70%);
  background: fade(@orange, 25%);
  color: @colorText;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;

  &:disabled {
    opacity: 0.6;
    cursor: default;
  }
}

.ency_grant_msg {
  font-size: 11px;
  color: @YesWrite;

  &.error {
    color: @NoWrite;
  }
}

.ency_lore {
  margin: 0 0 12px;
  padding: 10px;
  border-radius: 6px;
  background: fade(@DarkColorBG, 80%);
  border: 1px solid fade(@colorBlur, 20%);
  font-size: 13px;
  line-height: 1.45;
  color: @colorText;
}

.ency_lore_empty {
  margin: 0 0 12px;
  font-size: 12px;
  color: @colorBlur;
  font-style: italic;
}

.ency_block {
  margin-bottom: 12px;
  padding: 8px;
  border-radius: 6px;
  background: @DarkColorBG;
  .shadow_inset;

  h2 {
    margin: 0 0 6px;
    font-size: 12px;
    color: @orange;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  ul {
    margin: 0;
    padding-left: 16px;
  }

  li {
    font-size: 12px;
    color: @colorText;
    line-height: 1.4;
    margin-bottom: 4px;
  }

  a {
    color: @YesWrite;
    text-decoration: none;
  }
}

.ency_weight {
  color: @colorBlur;
  font-size: 11px;
}
</style>
