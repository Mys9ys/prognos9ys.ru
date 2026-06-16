<template>
    <HeaderBlock v-if="token"></HeaderBlock>
    <router-view></router-view>
    <div class="navbar_wrapper">
      <NavbarMenu v-if="token" class="menu_fixed"></NavbarMenu>
    </div>
</template>

<script>

import HeaderBlock from "@/components/main/HeaderBlock";
import {mapState} from "vuex";
import NavbarMenu from "@/components/main/NavbarMenu";


export default {
  name: 'App',
  components: {
    HeaderBlock,
    NavbarMenu,
  },
  // data(){
  //   return{
  //
  //   }
  // },
  created() {
  },
  metaInfo: {
    title: 'Прогносяус - сайт прогнозов на интерес',
    titleTemplate: '%s | vue-meta Example App'
  },

  computed: {
    ...mapState({
      token: state => state.auth.authData.token,
    })
  },
}
</script>

<style lang="less">
@import "src/assets/css/variables.less";
body{
  background: @BackGreenColor;
}




@font-face {
  font-family: 'Roboto';
  src: url('@/font/Roboto-Bold.ttf') format('truetype');
  font-weight: 700;
  font-style: normal;
}

@font-face {
  font-family: 'Roboto';
  src: url('@/font/Roboto-Light.ttf') format('truetype');
  font-weight: 300;
  font-style: normal;
}

@font-face {
  font-family: 'Roboto';
  src: url('@/font/Roboto-Medium.ttf') format('truetype');
  font-weight: 500;
  font-style: normal;
}

@font-face {
  font-family: 'Roboto';
  src: url('@/font/Roboto-Regular.ttf') format('truetype');
  font-weight: 400;
  font-style: normal;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

#app {
  position: relative;
  font-family: Roboto, Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
  width: 100vw;
  max-width: 400px;
  margin: 0 auto;
  height: 100vh;
  padding: 0 5px;
  background: @BackGreenColor;
  overflow-y: scroll;
  padding-bottom: 65px;
  .navbar_wrapper{
    width: 100%;
    //position: fixed;

    display: flex;
    flex-direction: row;
    justify-content: center;
  }
  .menu_fixed{
    position: fixed;
    bottom: 5px;
  }
}
</style>
