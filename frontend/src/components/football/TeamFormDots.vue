<template>
  <div v-if="items.length" class="team_form" :class="alignClass">
    <span
      v-for="(item, index) in items"
      :key="index"
      class="team_form_dot"
      :class="dotClass(item)"
      :title="item"
    >{{ item }}</span>
  </div>
</template>

<script>
export default {
  name: 'TeamFormDots',
  props: {
    items: {
      type: Array,
      default: () => [],
    },
    align: {
      type: String,
      default: 'left',
    },
  },
  computed: {
    alignClass() {
      return this.align === 'right' ? 'team_form_right' : 'team_form_left';
    },
  },
  methods: {
    dotClass(letter) {
      if (letter === 'В') {
        return 'team_form_dot_win';
      }
      if (letter === 'Н') {
        return 'team_form_dot_draw';
      }
      if (letter === 'П') {
        return 'team_form_dot_loss';
      }

      return '';
    },
  },
};
</script>

<style scoped lang="less">
.team_form {
  display: flex;
  flex-wrap: wrap;
  gap: 3px;
}

.team_form_left {
  justify-content: flex-start;
}

.team_form_right {
  justify-content: flex-end;
}

.team_form_dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  font-size: 9px;
  font-weight: 700;
  line-height: 16px;
  text-align: center;
  color: #fff;
}

.team_form_dot_win {
  background: #2f9e44;
}

.team_form_dot_draw {
  background: #868e96;
}

.team_form_dot_loss {
  background: #e03131;
}
</style>
