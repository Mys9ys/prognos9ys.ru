<template>
  <div class="title_box">
    <ProfileTitle v-for="(arr, index) in events"
                  @click="setActiveEvent(index)"
                  :info="arr.info"
                  :active="index === activeEvent"
                  :count="Object.keys(arr.items).length"
                  :class="{'active': index === activeEvent}"
                  :key="index"></ProfileTitle>
  </div>
  <div class="body_block" v-for="(arr, index) in events"
       :key="index">
    <div class="body_item" v-if="index == activeEvent">
      <div class="title_wrapper">
        <div class="title">{{ arr.info.NAME }}</div>
      </div>
      <ProfileRaceBody v-for="(item, index) in arr.items"
                       :key="index"
                       :racers="racers"
                       :item="item"
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
      type: Object
    },
    racers: {
      type: Object
    },
  },
  data() {
    return {
      activeEvent: ''
    }
  },

  methods: {

    setActiveEvent(id) {
      this.activeEvent = id
    },
  }
}
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