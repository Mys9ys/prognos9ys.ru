<template>
  <div class="item_block" >
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
    <div class="btn_drag" @click="onMoveRight($event.$parent, item.ID)">
      >
    </div>
  </div>
</template>

<script>
export default {
  name: "RaceListItem",
  props: {
    item: {
      type: Object
    },
    onMoveRight:{
      type: Function
    }
  },
  data(){
    return{
      urlImg: 'https://prognos9ys.ru/',
    }
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.item_block{
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  gap: 3px;
  border-radius: 5px;

  background: @hockei;
  padding-right: 2px;

  .shadow_template;

  .img_block{
    display: flex;
    flex-direction: row;
    gap: 2px;
  }
  .img{
    display: flex;
    flex-direction: row;
    align-items: center;
    width: 24px;
    height: 24px;

    padding: 2px;

    img{
      width: 100%;
      border-radius: 5px;
    }
  }

  .name{
    width: 80%;
    font-size: 12px;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .btn_drag{
    width: 20px;
    height: 20px;
    .shadow_template;
    border-radius: 5px;
    .flex_center;
    cursor: pointer;
  }
}
</style>