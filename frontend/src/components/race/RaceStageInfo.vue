<template>
  <div class="event_box" :class="{'small' : !item.info}">
    <div class="info_block" v-if="item.info">
      <div class="date">{{ item.info.date }}</div>
      <div class="time">{{ item.info.time }}</div>
    </div>

    <div class="title">{{item.name}}</div>
    <div class="score_wrapper" v-if="item.sum>-1" :class="{'green': item.name === 'Итого'}">
      <div class="score">{{ item.sum }}</div>
    </div>
  </div>

</template>

<script>
export default {
  name: "RaceStageInfo",
  props: {
    item: {
      type: Object
    }
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.event_box {
  width: 100%;
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  gap: 4px;
  font-size: 12px;

  .date, .time, .title {
    .shadow_inset;
  }
  .date, .time{
    .flex_center;
  }
}

.info_block{
  display: flex;
  flex-direction: row;
  gap: 4px;
}
.title{
  display: flex;
  flex-direction: row;
  align-items: center;
  width: 100%;
  text-align: left;
}

.small{
  flex:0 0 50%;
}

.score_wrapper{
  background: @pearl;
  color: @colorText;
  padding: 2px;
  border-radius: 5px;

  .flex_center;
  .score{
    .shadow_inset;
    .flex_center;
    min-width: 35px;
    font-size: 11px;
  }
}

.green{
  background: @YesWrite;
}
</style>