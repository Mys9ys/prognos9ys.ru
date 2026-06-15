<template>
  <div class="rating_set_bar" v-if="eventId" ref="root">
    <div class="set_selector_row">
      <div class="set_selector_wrapper">
        <button type="button" class="set_selector_inner" @click.stop="toggleOpen">
          <span class="set_selector_label">{{ currentLabel }}</span>
          <span class="set_selector_chevron" :class="{ open: dropdownOpen }">▾</span>
        </button>
      </div>

      <div class="set_add_wrapper" v-if="token">
        <button
            type="button"
            class="set_add_inner"
            @click="$emit('create')"
        >+</button>
      </div>
    </div>

    <div class="set_dropdown" v-if="dropdownOpen">
      <template v-for="(item, index) in dropdownItems" :key="item.key || index">
        <div class="dropdown_section" v-if="item.kind === 'section'">{{ item.label }}</div>

        <div
            v-else
            class="dropdown_item"
            :class="{ active: isActiveItem(item) }"
            @click="selectItem(item)"
        >
          <span class="dropdown_item_label">{{ item.displayName }}</span>
          <span class="badge" v-if="item.visibilityLabel">{{ item.visibilityLabel }}</span>
          <span class="badge badge_member" v-else-if="item.isMember && !item.isOwner">участник</span>
          <span
              class="edit_icon"
              v-if="item.isOwner && item.id"
              @click.stop="$emit('edit', item)"
          >✎</span>
        </div>
      </template>

      <div class="dropdown_empty" v-if="dropdownItems.length <= 1 && token">
        Создайте свой сборник кнопкой «+»
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RatingSetBar',
  props: {
    eventId: [String, Number],
    token: String,
    mySets: {
      type: Array,
      default: () => [],
    },
    publicSets: {
      type: Array,
      default: () => [],
    },
    activeSetId: {
      type: [Number, String, null],
      default: null,
    },
    activeSet: {
      type: Object,
      default: null,
    },
  },
  emits: ['select', 'create', 'edit'],
  data() {
    return {
      dropdownOpen: false,
    };
  },
  computed: {
    currentLabel() {
      if (!this.activeSetId) {
        return 'Общий рейтинг';
      }

      if (this.activeSet?.displayName) {
        return this.activeSet.displayName;
      }

      const found = [...this.mySets, ...this.publicSets]
        .find((set) => String(set.id) === String(this.activeSetId));

      return found?.displayName || 'Сборник рейтингов';
    },

    dropdownItems() {
      const items = [{
        key: 'default',
        id: null,
        displayName: 'Общий рейтинг',
        kind: 'option',
      }];

      const myIds = new Set(this.mySets.map((set) => set.id));

      if (this.mySets.length) {
        items.push({ key: 'section-my', kind: 'section', label: 'Мои' });
        this.mySets.forEach((set) => {
          items.push({ key: `my-${set.id}`, kind: 'option', ...set });
        });
      }

      const publicOnly = this.publicSets.filter((set) => !myIds.has(set.id));

      if (publicOnly.length) {
        items.push({ key: 'section-public', kind: 'section', label: 'Открытые' });
        publicOnly.forEach((set) => {
          items.push({ key: `pub-${set.id}`, kind: 'option', ...set });
        });
      }

      return items;
    },
  },
  mounted() {
    this.onDocumentClick = (event) => {
      if (!this.dropdownOpen) {
        return;
      }

      if (this.$refs.root && !this.$refs.root.contains(event.target)) {
        this.dropdownOpen = false;
      }
    };

    document.addEventListener('click', this.onDocumentClick);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.onDocumentClick);
  },
  methods: {
    toggleOpen() {
      this.dropdownOpen = !this.dropdownOpen;
    },

    isActiveItem(item) {
      if (!item.id) {
        return !this.activeSetId;
      }

      return String(item.id) === String(this.activeSetId);
    },

    selectItem(item) {
      this.dropdownOpen = false;

      if (!item.id) {
        this.$emit('select', null);
        return;
      }

      this.$emit('select', item);
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.rating_set_bar{
  position: relative;
  margin: 12px 0 16px;
}

.set_selector_row{
  display: flex;
  align-items: stretch;
  gap: 4px;
}

.set_selector_wrapper{
  flex: 1;
  min-width: 0;
  .inset_panel_wrapper();
}

.set_selector_inner{
  .inset_panel_inner();
  justify-content: space-between;
  gap: 8px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  text-align: left;
}

.set_selector_label{
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: @colorText;
}

.set_selector_chevron{
  flex-shrink: 0;
  font-size: 14px;
  line-height: 1;
  color: @colorText;
  opacity: 0.85;
  transition: transform 0.15s ease;

  &.open{
    transform: rotate(180deg);
  }
}

.set_add_wrapper{
  flex-shrink: 0;
  width: 48px;
  .inset_panel_wrapper();
}

.set_add_inner{
  .inset_panel_inner();
  justify-content: center;
  padding: 0;
  min-height: 36px;
  background: @colorText2;
  color: @colorText;
  font-size: 22px;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
}

.set_dropdown{
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 52px;
  z-index: 8;
  background: @darkbg;
  border: 1px solid fade(@colorBlur, 35%);
  border-radius: 5px;
  padding: 6px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.45);
  max-height: min(50vh, 280px);
  overflow-y: auto;
}

.dropdown_section{
  padding: 8px 10px 4px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: @pearl;
}

.dropdown_item{
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px;
  margin-bottom: 2px;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
  text-align: left;
  color: @colorText;
  background: @DarkColorBG;
  .shadow_inset;

  &:hover{
    background: @colorBack;
    color: @colorText;

    .dropdown_item_label,
    .edit_icon{
      color: @colorText;
    }
  }

  &.active{
    background: @colorText2;
    color: @colorText;
    .shadow_template;

    .dropdown_item_label,
    .badge,
    .edit_icon{
      color: @colorText;
    }
  }

  .dropdown_item_label{
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: @colorText;
    font-weight: 500;
  }

  .badge{
    margin-left: auto;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    color: @pearl;
    background: fade(@colorBlur, 25%);
    flex-shrink: 0;
  }

  .edit_icon{
    flex-shrink: 0;
    color: @pearl;
    font-size: 14px;
    padding: 0 2px;
  }
}

.dropdown_empty{
  padding: 8px;
  font-size: 12px;
  color: @colorBlur;
}
</style>
