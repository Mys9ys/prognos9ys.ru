<template>
  <div class="el_event" :class="[code]">
    <div class="img_box">
      <div class="lamp" :class=[element.status]></div>
      <img :src="url+element.img" alt="" v-if="element">
    </div>

    <div class="body_block">
      <div class="name">{{element.NAME}}</div>
      <div class="btn_block">
        <div v-if="element.table && element.table !== 'Нет' " class="btn" @click="$router.push('/championship/'+code +'/' +element.ID)">
          <span class="text">Таблица</span>
          <img class="arrow" src="@/assets/icon/pagination/right.svg" alt="">
        </div>
        <div class="btn" @click="$router.push('/'+code+ '/' +element.ID)">
          <span class="text">Список</span>
          <img class="arrow" src="@/assets/icon/pagination/right.svg" alt="">
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "CatalogElement",
  data() {
    return {
      url: 'https://prognos9ys.ru/',
    }
  },
  props: {
    element: {
      type: Object
    },
    code: {
      type: String
    }
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.el_event {
  width: 100%;
  display: flex;
  flex-direction: row;
  padding: 4px;
  color: @color;
  border-radius: 5px;
  text-align: left;
  gap: 4px;

  .img_box{
    position: relative;
    .shadow_inset;
    .flex_center;
    max-width: 72px;
    height: 72px;
    background: @colorText;
    opacity: 0.8;
    img{
      width: 100%;
    }
    padding: 4px;
  }
  .body_block{
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 2px;
    justify-content: space-between;
  }
  .name{
    font-size: 14px;
    height: 45px;
    .shadow_inset;
    .flex_center;
    width: 100%;
    justify-content: left;
  }
  .btn_block{
    display: flex;
    flex-direction: row;
    gap: 4px;
    justify-content: flex-end;
    .btn{
      min-width: 70px;
      display: flex;
      flex-direction: row;
      justify-content: space-between;
      gap: 3px;
      font-size: 12px;
      color: @colorText;
      .shadow_template;
      padding: 2px;
      .text{
        width: 80%;
      }
      .arrow{
        width: 10px;
      }
    }
  }

}
.lamp{
  position: absolute;
  left: 3px;
  top: 50%;
  transform: translateY(-50%);
  width: 5px;
  height: 5px;
  box-shadow: inset 0 2px 10px 1px rgba(0, 0, 0, .3), 0 1px rgba(255, 255, 255, .08);
  background: @green;
  border-radius: 50%;

  &.old{
    background: @red;
  }
}
.football {
  background: @YesWrite2;
}

.kvn {
  background: @kvn;
}

.race {
  background: @cubersport;
}
</style>