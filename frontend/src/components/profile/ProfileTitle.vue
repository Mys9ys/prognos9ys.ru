<template>
    <div class="title_block" :class="{'active' : active}" >
      <div class="count_wrapper">
        <div class="count">{{count}}</div>
      </div>

      <div class="event_img">
        <img class="img" :src="url+info.img" alt="">
      </div>

      <div class="title_mini" v-if="active">{{info.PREVIEW_TEXT}} </div>
    </div>
</template>

<script>
export default {
  name: "ProfileTitle",
  props:{
    info: {
      type: Object
    },
    active: {
      type: Boolean
    },
    count: {
      type: Number
    }
  },
  data() {
    return {
      url:  'https://prognos9ys.ru/',
      profileLoader: false
    }
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
  .title_block{
    position: relative;
    background: @DarkColorBG;
    padding: 4px;
    border-radius: 5px;
    color: @colorText;
    margin-top: 5px;

    display: flex;
    flex-direction: row;
    justify-content: flex-start;
    gap: 4px;
    flex-wrap: nowrap;

    .event_img{
      .shadow_inset;
      width: 35px;
      height: 35px;
      background: @colorBlur;
      .flex_center;
      .img{
        width: 100%;
      }
    }
    .title_mini{
      .shadow_inset;
      .flex_center;
      font-size: 12px;
      word-break: break-all;
      line-height: 14px;
    }

  }

.active{
  background: @colorText2;
}
.count_wrapper{
  position: absolute;
  top: -7px;
  left: -7px;
  background: @colorText2;
  padding: 2px;
  border-radius: 5px;
  color: @colorText;
  margin-top: 5px;

  opacity: 0.85;

}
.count{
  padding: 2px;
  font-size: 11px;
  .shadow_inset;
}
</style>