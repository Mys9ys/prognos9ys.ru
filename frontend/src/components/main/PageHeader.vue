<template>
  <div class="header_wrapper">
    <div class="header_absolute">
      <div class="header_block">
        <div class="block_title">
          <slot></slot>
        </div>
        <div v-if="path" class="btn_prev" @click="$router.push(path).then(() => { this.$router.go() })">Назад</div>
        <div v-else class="btn_prev" @click="$router.go(-1)">Назад</div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "PageHeader",
  props: {
    path: {
      type: String
    }
  }
}
</script>

<style lang="less" scoped>
@import "../../assets/css/variables.less";
.header_wrapper{
  position: relative;
  height: 25px;
  z-index: 0;
  .header_absolute{
    position: absolute;
    top:-20px;
    width: 100%;
    z-index: 0;
  }
}
  .header_block{
    width: 100%;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;

    z-index: 0;

    .block_title{
      font-weight: 500;
      font-size: 16px;
      line-height: 22px;
      /* identical to box height, or 138% */

      /* Черный */
      color: @darkbg;
    }
    .btn_prev{
      font-weight: 500;
      font-size: 14px;
      line-height: 22px;
      /* identical to box height, or 157% */

      text-align: right;

      /* Серый */
      color: @colorBack
    }
  }
</style>