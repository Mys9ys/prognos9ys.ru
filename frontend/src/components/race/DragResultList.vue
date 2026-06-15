<template>
  <div class="item_block" v-if="item" :class="{'hidden' : hidden}">
    <div class="place">{{ place + 1 }}</div>
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
    <div class="btn_del" @click="deleteElement(place, item.ID)">
      x
    </div>
  </div>
</template>

<script>
export default {
  name: "DragResultList",
  props: {
    item: {
      type: Object
    },
    place: {
      type: Number
    },
    deleteElement: {
      type: Function
    }
  },
  data() {
    return {
      urlImg: 'https://prognos9ys.ru/',
      hidden: false
    }
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.item_block {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 3px;
  border-radius: 5px;
  padding: 0 2px;

  background: @hockei;

  .shadow_template;

  .place {
    .shadow_inset;
    .flex_center;
    font-size: 13px;
    min-width: 24px;
  }
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
    font-size: 12px;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .btn_del {
    width: 20px;
    height: 20px;
    .shadow_template;
    border-radius: 5px;
    .flex_center;
    cursor: pointer;
  }
}
</style>