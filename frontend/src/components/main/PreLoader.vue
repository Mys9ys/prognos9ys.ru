<template>
  <div class="wrapper_loader">
    <div class="logo_block">
      <div class="logo">
        <img class="logo_img" src="@/assets/icon/football/ballf.svg">
      </div>
      <div class="loader_line">
        <div class="loader_fill_line"></div>
      </div>
    </div>

    <div class="footer_title_box">
      <div class="title_rus">Прогносяус</div>
      <div class="title_eng">Сайт прогнозов</div>
    </div>
  </div>
</template>

<script>

export default {
  name: "PreLoader",
  mounted() {
    setTimeout(() => {
      console.log('fdfsdgf loader')
    }, 1100)
    // this.loginRequest()
  },

}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.wrapper_loader{
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  background:@YesWrite;
  width: 100%;
  max-width: 400px;
  margin: 0 auto;
  height: 100vh;
  text-align: center;
  z-index: 3;

  .logo_block{
    display: block;
    margin-top: 46%;
  }
  .logo{
    .logo_img{
      width: 100px;
      animation-name: rotation;
      animation-duration: 5s;
      animation-iteration-count: infinite;
      animation-timing-function: linear;
    }
  }
  .loader_line{
    margin: 0 auto;
    position: relative;
    margin-top: 20px;
    width: 60%;
    height: 6px;

    /* Бледно-серый */
    background: #E7E7E7;
    border-radius: 10px;
    .loader_fill_line{
      position: absolute;
      top: 0;
      left: 0;
      width: 15%;
      height: 6px;

      /* Корп цвет */
      background: @YesWrite2;
      border-radius: 10px;
      animation: animateThis 1s ease-in;
      -webkit-animation: animateThis 1s ease-in;
      -webkit-animation-fill-mode: forwards;
    }
  }
  .footer_title_box{
    margin-top: 60px;
    display: flex;
    flex-direction: column;
    align-items: center;
    .title_rus{
      width: 100%;
      height: 18px;
      font-weight: 700;
      font-size: 16px;
      line-height: 18px;
      /* identical to box height, or 138% */

      text-align: center;
      letter-spacing: -0.408px;

      /* Серый */
      color: @YesWrite2;
    }
    .title_eng{
      width: 100%;
      height: 16px;

      font-weight: 500;
      font-size: 12px;
      line-height: 16px;
      /* identical to box height, or 183% */

      text-align: center;
      //letter-spacing: -0.408px;

      /* Серый */

      color: #146c43;
    }
  }

  @keyframes animateThis {
    0% {
      width: 15%;
    }
    100% {
      width: 100%;
    }
  }
  @keyframes rotation {
    0% {
      transform:rotate(0deg);
    }
    100% {
      transform:rotate(360deg);
    }
  }
}
</style>