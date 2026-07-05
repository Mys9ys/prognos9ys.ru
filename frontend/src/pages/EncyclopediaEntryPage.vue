<template>
  <div class="ency_entry_page">
    <PageHeader>Энциклопедия</PageHeader>

    <div v-if="loading" class="ency_hint">Загрузка…</div>
    <div v-else-if="error" class="ency_error">{{ error }}</div>
    <div v-else-if="!entry" class="ency_error">Запись не найдена</div>

    <EncyclopediaEntryDetail
      v-else
      :section-id="sectionId"
      :section-label="sectionLabel"
      :entry="entry"
      :code-index="codeIndex"
      :can-grant="canGrant"
      :grant-sections="grantSections"
      :user-token="token"
    />
  </div>
</template>

<script>
import { mapState } from 'vuex';
import PageHeader from '@/components/main/PageHeader.vue';
import EncyclopediaEntryDetail from '@/components/encyclopedia/EncyclopediaEntryDetail.vue';
import { apiActions } from '@/api/bitrixClient';

export default {
  name: 'EncyclopediaEntryPage',
  components: {
    PageHeader,
    EncyclopediaEntryDetail,
  },
  props: {
    section: {
      type: String,
      required: true,
    },
    code: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      loading: true,
      error: '',
      encyclopedia: null,
      canGrant: false,
      grantSections: [],
    };
  },
  computed: {
    ...mapState({
      token: (state) => state.auth.authData.token,
    }),
    sectionId() {
      return this.section;
    },
    entry() {
      if (!this.encyclopedia) return null;
      const section = this.encyclopedia.sections.find((s) => s.id === this.sectionId);
      if (!section) return null;
      const decoded = decodeURIComponent(this.code);
      return section.entries.find((item) => item.code === decoded) || null;
    },
    sectionLabel() {
      if (!this.encyclopedia) return 'К списку';
      const section = this.encyclopedia.sections.find((s) => s.id === this.sectionId);
      return section ? section.label : 'К списку';
    },
    codeIndex() {
      const index = {};
      if (!this.encyclopedia) return index;

      this.encyclopedia.sections.forEach((section) => {
        section.entries.forEach((entry) => {
          index[entry.code] = `/encyclopedia/${section.id}/${encodeURIComponent(entry.code)}`;
        });
      });

      return index;
    },
  },
  async created() {
    try {
      const data = await apiActions.game.getEncyclopedia(this.token || '');
      if (data.status !== 'ok' || !data.encyclopedia) {
        throw new Error(data.message || 'Не удалось загрузить энциклопедию');
      }
      this.encyclopedia = data.encyclopedia;
      this.canGrant = Boolean(data.viewer && data.viewer.can_grant);
      this.grantSections = (data.viewer && data.viewer.grant_sections) || [];
    } catch (err) {
      this.error = err.message || 'Ошибка загрузки';
    } finally {
      this.loading = false;
    }
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.ency_entry_page {
  padding: 0 4px 12px;
}

.ency_hint,
.ency_error {
  font-size: 13px;
  padding: 8px 0;
}

.ency_error {
  color: @NoWrite;
}
</style>
