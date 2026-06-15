<template>
  <div class="item_block" v-if="item" :class="score">
    <div class="img_block">
      <div class="foto img">
        <img :src="urlImg + item.img" alt="">
      </div>
      <div class="country img">
        <img :src="urlImg + item.team.img" alt="">
      </div>
    </div>
    <div class="name">
      {{ item.short_name}}
    </div>
  </div>
</template>

<script>
export default {
  name: "RacerItem",
  data() {
    return {
      urlImg: 'https://prognos9ys.ru/',
    }
  },
  props: {
    item: {
      type: Object
    },
    place:{
      type: Number
    },
    score: {
      type: String
    }
  }

}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.item_block {
  max-width: 160px;
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 3px;
  border-radius: 5px;
  padding: 0 2px;
  background: @hockei;
  .shadow_template;
  .img_block{
    display: flex;
    flex-direction: row;
    gap: 2px;
  }
  .img {
    display: flex;
    flex-direction: row;
    align-items: center;
    width: 24px;
    height: 24px;

    padding: 2px;

    img {
      width: 100%;
      border-radius: 5px;
    }
  }

  .name {
    width: 80%;
    font-size: 11px;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}
.qual0-5, .sprint0-5, .race0-5{
  background: @yellowblur;
  color: @hockei!important;
}
.qual0, .sprint0, .race0{
  filter: grayscale(100%);
}
.qual1, .sprint1, .race1{
  background: @greenblur;
  color: @hockei;
}
.qual2, .sprint2, .race3{
  background: @colorText2;
}
.qual3, .sprint3, .race5, .best_lap5{
  background: @green;
}

</style>