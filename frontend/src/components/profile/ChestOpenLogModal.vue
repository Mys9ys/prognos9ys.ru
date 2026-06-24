<template>
  <div class="log_overlay" v-if="visible" @click.self="$emit('close')">
    <div class="log_panel">
      <div class="log_panel_title">Журнал открытий сундуков</div>

      <div v-if="loading && !entries.length" class="log_hint">Загрузка…</div>
      <div v-else-if="error" class="log_error">{{ error }}</div>
      <div v-else-if="!events.length" class="log_hint">Пока нет открытых сундуков</div>

      <template v-else>
        <div class="log_filters" v-if="events.length > 1">
          <label class="log_filter_label">Событие</label>
          <select v-model="selectedEventId" class="log_select" @change="onFiltersChanged">
            <option v-for="event in events" :key="event.id" :value="event.id">
              {{ event.name }} ({{ event.opens_count }})
            </option>
          </select>
        </div>

        <div class="log_groups" v-if="activeGroups.length > 1">
          <button
            v-for="group in activeGroups"
            :key="group.key"
            type="button"
            class="log_group_btn"
            :class="{ active: selectedGroupKey === group.key }"
            @click="selectGroup(group.key)"
          >
            {{ group.label }} ({{ group.count }})
          </button>
        </div>

        <div class="log_meta" v-if="pagination.total > 0">
          Показано {{ entries.length }} из {{ pagination.total }}
        </div>

        <div class="log_list" ref="list">
          <div v-for="entry in entries" :key="entry.id" class="log_entry">
            <div class="log_entry_head">
              <span class="log_entry_date">{{ entry.created_at }}</span>
              <span class="log_entry_group">{{ entry.group_label }}</span>
            </div>
            <div class="log_entry_type">{{ entry.chest_type_label }}</div>
            <div class="log_entry_reward" v-if="entry.reward_line">{{ entry.reward_line }}</div>
            <div class="log_entry_lines" v-if="entry.lines && entry.lines.length">
              <div
                v-for="(line, index) in entry.lines"
                :key="index"
                class="log_entry_line"
              >{{ line.text }}</div>
            </div>
          </div>
        </div>

        <button
          v-if="pagination.has_more"
          type="button"
          class="log_more_btn"
          :disabled="loadingMore"
          @click="loadMore"
        >
          {{ loadingMore ? 'Загрузка…' : 'Показать ещё' }}
        </button>
      </template>

      <button type="button" class="log_close_btn" @click="$emit('close')">Закрыть</button>
    </div>
  </div>
</template>

<script>
import { apiActions } from '@/api/bitrixClient';

export default {
  name: 'ChestOpenLogModal',
  props: {
    visible: { type: Boolean, default: false },
    userToken: { type: String, default: '' },
  },
  data() {
    return {
      loading: false,
      loadingMore: false,
      error: '',
      events: [],
      entries: [],
      selectedEventId: 0,
      selectedGroupKey: 'all',
      pagination: {
        offset: 0,
        limit: 25,
        total: 0,
        has_more: false,
      },
    };
  },
  computed: {
    activeGroups() {
      const event = this.events.find((item) => Number(item.id) === Number(this.selectedEventId));
      return Array.isArray(event?.groups) ? event.groups : [];
    },
  },
  watch: {
    visible(value) {
      if (value) {
        this.bootstrap();
      } else {
        this.resetState();
      }
    },
  },
  methods: {
    resetState() {
      this.error = '';
      this.events = [];
      this.entries = [];
      this.selectedEventId = 0;
      this.selectedGroupKey = 'all';
      this.pagination = { offset: 0, limit: 25, total: 0, has_more: false };
    },

    async bootstrap() {
      if (!this.userToken) {
        this.error = 'Нужна авторизация';
        return;
      }

      this.loading = true;
      this.error = '';
      this.entries = [];

      try {
        const meta = await apiActions.game.getChestOpenLogMeta(this.userToken);
        this.events = Array.isArray(meta.events) ? meta.events : [];

        if (!this.events.length) {
          return;
        }

        this.selectedEventId = Number(this.events[0].id);
        this.selectedGroupKey = 'all';
        await this.loadEntries(true);
      } catch (e) {
        this.error = e.message || 'Не удалось загрузить журнал';
      } finally {
        this.loading = false;
      }
    },

    async onFiltersChanged() {
      this.selectedGroupKey = 'all';
      await this.loadEntries(true);
    },

    async selectGroup(groupKey) {
      if (this.selectedGroupKey === groupKey) {
        return;
      }
      this.selectedGroupKey = groupKey;
      await this.loadEntries(true);
    },

    async loadMore() {
      if (!this.pagination.has_more || this.loadingMore) {
        return;
      }
      this.loadingMore = true;
      try {
        await this.loadEntries(false);
      } finally {
        this.loadingMore = false;
      }
    },

    async loadEntries(reset) {
      const offset = reset ? 0 : this.entries.length;
      const data = await apiActions.game.getChestOpenLogs(
        this.userToken,
        Number(this.selectedEventId) || 0,
        this.selectedGroupKey || 'all',
        offset,
        this.pagination.limit
      );

      const chunk = Array.isArray(data.entries) ? data.entries : [];
      this.entries = reset ? chunk : [...this.entries, ...chunk];
      this.pagination = {
        offset: Number(data.pagination?.offset ?? offset),
        limit: Number(data.pagination?.limit ?? this.pagination.limit),
        total: Number(data.pagination?.total ?? 0),
        has_more: !!data.pagination?.has_more,
      };

      if (reset) {
        this.$nextTick(() => {
          const el = this.$refs.list;
          if (el) {
            el.scrollTop = 0;
          }
        });
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.log_overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  background: rgba(0, 0, 0, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
}

.log_panel {
  width: 100%;
  max-width: 420px;
  max-height: 88vh;
  background: @DarkColorBG;
  border: 2px solid @orange;
  border-radius: 8px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.log_panel_title {
  font-size: 14px;
  font-weight: 700;
  color: @orange;
  text-align: center;
}

.log_hint,
.log_error {
  font-size: 12px;
  text-align: center;
  color: @colorBlur;
  padding: 8px 4px;
}

.log_error {
  color: #f88;
}

.log_filters {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.log_filter_label {
  font-size: 11px;
  color: @colorBlur;
}

.log_select {
  width: 100%;
  background: darken(@darkbg, 3%);
  color: @colorText;
  border: 1px solid fade(@colorBlur, 35%);
  border-radius: 4px;
  padding: 6px 8px;
  font-size: 12px;
}

.log_groups {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.log_group_btn {
  border: 1px solid fade(@colorBlur, 35%);
  background: fade(@darkbg, 70%);
  color: @colorText;
  border-radius: 4px;
  padding: 4px 6px;
  font-size: 10px;
  cursor: pointer;

  &.active {
    border-color: fade(@orange, 80%);
    background: fade(@orange, 20%);
    color: @orange;
  }
}

.log_meta {
  font-size: 10px;
  color: @colorBlur;
  text-align: right;
}

.log_list {
  flex: 1;
  min-height: 140px;
  max-height: 48vh;
  overflow-y: auto;
  background: rgba(0, 0, 0, 0.22);
  border-radius: 4px;
  padding: 6px;
}

.log_entry {
  border-bottom: 1px solid fade(@colorBlur, 18%);
  padding: 6px 2px;

  &:last-child {
    border-bottom: none;
  }
}

.log_entry_head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 10px;
  color: @colorBlur;
}

.log_entry_group {
  color: @orange;
  white-space: nowrap;
}

.log_entry_type {
  font-size: 11px;
  font-weight: 700;
  color: @colorText;
  margin-top: 2px;
}

.log_entry_reward {
  font-size: 11px;
  color: @YesWrite;
  margin-top: 2px;
  line-height: 1.35;
}

.log_entry_lines {
  margin-top: 3px;
}

.log_entry_line {
  font-size: 10px;
  line-height: 1.3;
  color: fade(@colorText, 85%);
}

.log_more_btn,
.log_close_btn {
  border: none;
  border-radius: 4px;
  padding: 8px 12px;
  font-size: 13px;
  cursor: pointer;
}

.log_more_btn {
  background: fade(@YesWrite, 18%);
  color: @colorText;
  border: 1px solid fade(@YesWrite, 55%);

  &:disabled {
    opacity: 0.6;
    cursor: default;
  }
}

.log_close_btn {
  background: @orange;
  color: #fff;
}
</style>
