<template>
  <div class="wrapper_success">
    <div class="logo_block">
      <div class="logo">
        <img class="logo_img" src="@/assets/icon/football/ballf.svg">
      </div>
      <div class="loader_line">
        <p>Прогноз успешно отправлен</p>
      </div>
    </div>

    <FillBtn class="success_btn" @click="closeSuccessWindow()">Закрыть</FillBtn>

  </div>
</template>

<script>
import FillBtn from "@/components/ui/btn/FillBtn";

export default {
  name: "SendSuccess",
  components: {FillBtn},
  props: {
    closeSuccess: {
      type: Function
    }
  },

  mounted() {
    setTimeout(() => {
      this.closeSuccessWindow()
    }, 2500)
  },
  methods: {
    closeSuccessWindow() {
      this.closeSuccess()
    }
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.wrapper_success {
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  background: @YesWrite;
  width: 100%;
  max-width: 400px;
  margin: 0 auto;
  height: 100vh;
  text-align: center;
  z-index: 3;
  padding: 0 20px;

  .logo_block {
    display: block;
    margin-top: 46%;
  }

  .logo {
    .logo_img {
      width: 100px;
    }
  }

  .success_btn{
    margin-top: 95px;
  }
}
</style>