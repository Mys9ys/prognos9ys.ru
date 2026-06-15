<template>
  <div class="wrapper_success">
    <div class="logo_block">
      <div class="loader_line">
        <p>Ошибка</p>
        <p><slot></slot></p>
      </div>
    </div>

    <FillBtn class="success_btn" @click="closeSuccessWindow()">Закрыть</FillBtn>

  </div>
</template>

<script>
import FillBtn from "@/components/ui/btn/FillBtn";

export default {
  name: "ActionFailure",
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
  background: @colorBlur;
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

  .loader_line{
    color: @red;
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