<template>
  <div class="btn">
    <slot></slot>
  </div>
</template>

<script>
export default {
  name: "TransparentBtn"
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.btn{
  width: 100%;
  padding: 15px 0;
  cursor: pointer;

  /* Белый */

  background: transparent;
  /* Корп цвет */

  border: 2px solid @colorText;
  border-radius: 20px;

  font-weight: 500;
  font-size: 16px;
  line-height: 22px;
  /* or 138% */

  /* Черный */

  color: @colorText;

  &:hover{
    background: @colorText;
    color: @YesWrite;
  }
}
</style>