<template>
  <div class="set_modal_overlay" v-if="visible" @click.self="close">
    <div class="set_modal">
      <div class="set_modal_title">{{ editSet ? 'Редактировать сборник' : 'Новый сборник' }}</div>

      <label class="field_label">Название (необязательно)</label>
      <input
          class="field_input"
          v-model="form.title"
          type="text"
          maxlength="100"
          placeholder="Например: Любимки"
      >

      <label class="field_label">Видимость</label>
      <div class="visibility_row">
        <div
            class="visibility_btn"
            :class="{ active: form.visibility === 'open' }"
            @click="form.visibility = 'open'"
        >Открытая</div>
        <div
            class="visibility_btn"
            :class="{ active: form.visibility === 'closed' }"
            @click="form.visibility = 'closed'"
        >Закрытая</div>
        <div
            class="visibility_btn"
            :class="{ active: form.visibility === 'private' }"
            @click="form.visibility = 'private'"
        >Приватная</div>
      </div>
      <div class="visibility_hint">{{ visibilityHint }}</div>

      <label class="field_label checkbox_row" v-if="eventId">
        <input type="checkbox" v-model="form.bindEvent">
        <span>Только для текущего события</span>
      </label>

      <label class="field_label">Участники (минимум 2)</label>
      <div class="users_box" v-if="availableUsers.length">
        <label
            class="user_row"
            v-for="user in availableUsers"
            :key="user.id"
        >
          <input type="checkbox" :value="user.id" v-model="form.userIds">
          <span class="user_name">{{ user.name }}</span>
        </label>
      </div>
      <div class="users_empty" v-else>Сначала дождитесь загрузки общего рейтинга</div>

      <div class="actions_row">
        <button type="button" class="btn_cancel" @click="close">Отмена</button>
        <button
            type="button"
            class="btn_save"
            :disabled="saving || form.userIds.length < 2"
            @click="save"
        >{{ saving ? 'Сохранение…' : 'Сохранить' }}</button>
      </div>

      <button
          type="button"
          class="btn_delete"
          v-if="editSet && editSet.isOwner !== false"
          :disabled="saving"
          @click="remove"
      >Удалить сборник</button>

      <div class="error_text" v-if="error">{{ error }}</div>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';

export default {
  name: 'RatingSetModal',
  props: {
    visible: Boolean,
    editSet: {
      type: Object,
      default: null,
    },
    eventId: {
      type: [String, Number],
      default: '',
    },
    sport: {
      type: String,
      default: 'football',
    },
    availableUsers: {
      type: Array,
      default: () => [],
    },
    userToken: {
      type: String,
      default: '',
    },
  },
  emits: ['close', 'saved', 'deleted'],
  data() {
    return {
      form: {
        title: '',
        visibility: 'closed',
        bindEvent: true,
        userIds: [],
      },
      saving: false,
      error: '',
    };
  },
  computed: {
    visibilityHint() {
      switch (this.form.visibility) {
        case 'open':
          return 'Виден всем участникам события';
        case 'closed':
          return 'Виден вам и выбранным участникам';
        case 'private':
          return 'Только вы; участники не увидят этот сборник';
        default:
          return '';
      }
    },
  },
  watch: {
    visible(value) {
      if (value) {
        this.resetForm();
      }
    },
    editSet: {
      immediate: true,
      handler() {
        if (this.visible) {
          this.resetForm();
        }
      },
    },
  },
  methods: {
    ...mapActions({
      createSet: 'ratingSet/createSet',
      updateSet: 'ratingSet/updateSet',
      deleteSet: 'ratingSet/deleteSet',
    }),

    resetForm() {
      this.error = '';
      this.saving = false;

      if (this.editSet) {
        this.form.title = this.editSet.title || '';
        this.form.visibility = this.editSet.visibility || 'closed';
        this.form.bindEvent = this.editSet.eventIds?.length > 0;
        this.form.userIds = [...(this.editSet.memberIds || [])];
        return;
      }

      this.form = {
        title: '',
        visibility: 'closed',
        bindEvent: Boolean(this.eventId),
        userIds: [],
      };
    },

    close() {
      this.$emit('close');
    },

    buildPayload() {
      const eventIds = this.form.bindEvent && this.eventId ? [Number(this.eventId)] : [];

      return {
        title: this.form.title || null,
        visibility: this.form.visibility,
        sport: this.sport,
        userIds: this.form.userIds.map(Number),
        eventIds,
      };
    },

    async save() {
      this.saving = true;
      this.error = '';

      try {
        const payload = this.buildPayload();
        let response;

        if (this.editSet?.id) {
          response = await this.updateSet({
            userToken: this.userToken,
            setId: this.editSet.id,
            payload,
            sport: this.sport,
            eventId: this.eventId ? Number(this.eventId) : null,
          });
        } else {
          response = await this.createSet({
            userToken: this.userToken,
            payload,
            sport: this.sport,
            eventId: this.eventId ? Number(this.eventId) : null,
          });
        }

        this.$emit('saved', response.set || this.editSet);
        this.close();
      } catch (e) {
        this.error = e?.message || 'Не удалось сохранить сборник';
      } finally {
        this.saving = false;
      }
    },

    async remove() {
      if (!this.editSet?.id || !confirm('Удалить этот сборник?')) {
        return;
      }

      this.saving = true;
      this.error = '';

      try {
        await this.deleteSet({
          userToken: this.userToken,
          setId: this.editSet.id,
          sport: this.sport,
          eventId: this.eventId ? Number(this.eventId) : null,
        });
        this.$emit('deleted');
        this.close();
      } catch (e) {
        this.error = e?.message || 'Не удалось удалить сборник';
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.set_modal_overlay{
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 20;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 24px 8px;
}

.set_modal{
  width: 100%;
  max-width: 400px;
  background: @DarkColorBG;
  border-radius: 8px;
  padding: 12px;
  color: @colorText;
  text-align: left;
}

.set_modal_title{
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 12px;
  .shadow_inset;
  padding: 4px 8px;
}

.field_label{
  display: block;
  font-size: 12px;
  margin: 8px 0 4px;
  color: @colorBlur;
}

.field_input{
  width: 100%;
  border: none;
  border-radius: 5px;
  padding: 6px 8px;
  .shadow_inset;
  background: transparent;
  color: @colorText;
}

.visibility_row{
  display: flex;
  gap: 4px;
}

.visibility_btn{
  flex: 1;
  text-align: center;
  padding: 6px 4px;
  border-radius: 5px;
  cursor: pointer;
  font-size: 11px;
  .shadow_inset;

  &.active{
    background: @colorText2;
    color: @colorText;
  }
}

.visibility_hint{
  margin-top: 4px;
  font-size: 11px;
  color: @colorBlur;
  line-height: 1.3;
}

.checkbox_row{
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.users_box{
  max-height: 220px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 4px;
  border-radius: 5px;
  .shadow_inset;
}

.user_row{
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  cursor: pointer;
}

.users_empty{
  font-size: 12px;
  padding: 8px;
  .shadow_inset;
}

.actions_row{
  display: flex;
  gap: 8px;
  margin-top: 14px;
}

.btn_cancel,
.btn_save,
.btn_delete{
  border: none;
  border-radius: 5px;
  padding: 8px 12px;
  cursor: pointer;
  font-size: 13px;
  .shadow_template;
}

.btn_cancel{
  background: @DarkColorBG;
  color: @colorText;
  flex: 1;
}

.btn_save{
  background: @colorText2;
  color: @colorText;
  flex: 2;

  &:disabled{
    opacity: 0.5;
    cursor: default;
  }
}

.btn_delete{
  width: 100%;
  margin-top: 10px;
  background: @maxred;
  color: @colorText;
}

.error_text{
  margin-top: 10px;
  color: @maxred;
  font-size: 12px;
}
</style>
