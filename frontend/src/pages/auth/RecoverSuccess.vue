<template>
  <div class="wrapper">
    <div class="info">Вы успешно изменили пароль <br>
      Теперь вы сможете войти с новым паролем</div>
    <TransparentBtn @click="$router.push('/auth')">Войти</TransparentBtn>
  </div>
</template>

<script>

import TransparentBtn from "@/components/ui/btn/TransparentBtn";

export default {
  name: "RecoverSuccess",
  components: {
    TransparentBtn
  },

  mounted() {
    setTimeout(() => {
      this.$router.push('/auth')
    }, 2800)
    // this.loginRequest()
  },

}
</script>

<style lang="less" scoped>
@import "@/assets/css/variables.less";
.wrapper {
  .wrapper_template;
  display: flex;
  flex-direction: column;
  align-items: center;

  .info{
    width: 100%;
    margin-top: 322px;
    font-weight: 500;
    font-size: 14px;
    line-height: 22px;
    text-align: center;
    margin-bottom: 19px;
    /* or 157% */



    /* Черный */
    color: #000000;
  }
}
</style>