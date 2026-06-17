<template>
  <div class="title_box">
    <ProfileTitle v-for="(arr, eventId) in events"
                  @click="setActiveEvent(eventId)"
                  :info="arr.info"
                  :active="String(eventId) === String(activeEvent)"
                  :count="Object.keys(arr.items).length"
                  :class="{'active': String(eventId) === String(activeEvent)}"
                  :key="eventId"></ProfileTitle>
  </div>
  <div class="body_block" v-for="(arr, eventId) in events"
       :key="eventId">
    <div class="body_item" v-if="String(eventId) === String(activeEvent)">
      <div class="title_wrapper">
        <div class="title">{{ arr.info.NAME }}</div>
      </div>
      <ProfileRaceBody v-for="(raceItem, raceNumber) in arr.items"
                       :key="raceNumber"
                       :racers="racers"
                       :item="raceItem"
      ></ProfileRaceBody>
    </div>
  </div>
</template>

<script>

import ProfileRaceBody from "@/components/profile/ProfileRaceBody";
import ProfileTitle from "@/components/profile/ProfileTitle";

export default {
  name: "ProfileRaceBlock",
  components: {
    ProfileTitle,
    ProfileRaceBody
  },
  props: {
    events: {
      type: Object,
      default: () => ({}),
    },
    racers: {
      type: Object,
      default: () => ({}),
    },
  },
  data() {
    return {
      activeEvent: '',
    };
  },

  watch: {
    events: {
      deep: true,
      immediate: true,
      handler() {
        this.ensureActiveEvent();
      },
    },
  },

  methods: {
    setActiveEvent(id) {
      this.activeEvent = String(id);
    },

    ensureActiveEvent() {
      const keys = Object.keys(this.events || {});
      if (!keys.length) {
        this.activeEvent = '';
        return;
      }
      if (!keys.includes(String(this.activeEvent))) {
        this.activeEvent = String(keys[0]);
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.title_wrapper {
  background: @DarkColorBG;
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  margin-top: 8px;

  .title {
    .shadow_inset;
  }
}

.title_box {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  gap: 4px;
}
.body_item{
  display: flex;
  flex-direction: column;
  gap: 4px;
}
</style>
