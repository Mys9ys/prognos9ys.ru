<template>
  <div class="btn">
    <slot></slot>
  </div>
</template>

<script>
export default {
  name: "FillBtn",
}
</script>

<style lang="less" scoped>

  .btn{
    position: relative;
    display: flex;
    flex-direction: row;
    justify-content: center;
    box-sizing: border-box;
    width: 100%;
    padding: 17px 18px;
    cursor: pointer;

    /* Корп цвет */
    background: #43BAC0;
    background: rgba(35,35,50);
    background: #2B2B40;
    background: #151521;
    border-radius: 20px;

    font-weight: 500;
    font-size: 16px;
    line-height: 22px;
    /* or 138% */

    letter-spacing: -0.408px;

    /* Белый */

    color: #FFFFFF;

}

</style>