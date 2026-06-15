<template>
  <div v-if="arElements">
    <div class="past_title_wrapper">
      <div class="past_title">{{arElements.info.title}}</div>
      <div class="title_count">{{arElements.info.count}}</div>
      <div class="more_btn" @click="visible = !visible">
        <span :class="{'close' : !visible, 'open' : visible}"> > </span>
      </div>
    </div>
    <div class="past_box match_box" v-if="visible">
      <div class="date_match_block"
           v-for="(items, index) in arElements.items"
           :key="index"
      >
        <div class="date_title_wrapper">
          <div class="date_title">{{index}}</div>
        </div>

        <EventRace
            v-for="(el, index) in items"
            :key="index"
            :element="el"
        ></EventRace>
        <div class="doted_line"></div>
      </div>
    </div>

  </div>

</template>

<script>

import EventRace from "@/components/race/EventRace";

export default {
  name: "SectionRace",
  components: {EventRace},
  props: {
    arElements: {
      type: Object
    }
  },
  data() {
    return {
      visible: this.arElements.info.visible,
    }
  },

}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.past_title_wrapper{
  position: relative;
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;
  text-align: left;
  margin-bottom: 6px;
  margin-top: 26px;
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  .past_title{
    display: inline-block;
    .shadow_inset;
  }
  .title_count{
    position: absolute;
    right: 32px;
    .shadow_inset;
    .flex_center;
    width: 24px;
    height: 24px;
    font-size: 12px;
    padding: 0;
    border: 2px solid @kvn;
    color: @kvn;
  }
  .more_btn {
    display: flex;
    flex-direction: column;
    justify-content: center;
    max-width: 24px;
    height: 24px;
    width: 24%;
    background: @valleyball;
    padding: 2px 2px;
    border-radius: 3px;
    cursor: pointer;
    box-shadow: 0 2px 3px rgba(0, 0, 0, .4), 0 -1px 0 rgba(0, 0, 0, .2);

    span{
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }

    .close {
      transform: rotate(90deg);
    }

    .open {
      transform: rotate(-90deg);
    }

    &:hover {
      background: @colorText;
      color: @valleyball;
      border: 1px solid @valleyball;
    }
  }
}

.match_box{
  background: @BackGreenColor;
  .doted_line{
    width: 100%;
    border-bottom: 2px dotted @darkbg;
  }
  .date_title_wrapper{
    width: 20%;
    max-width: 75px;
    background: @DarkColorBG;
    color: @colorText;
    padding: 4px;
    border-radius: 5px;
    display: inline-block;
    .date_title{
      .shadow_inset;
      font-size: 12px;
    }
  }

  .date_match_block{
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 16px;
  }
}

</style>