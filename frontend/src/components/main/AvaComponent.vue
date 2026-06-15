<template>
  <div class="ava_wrapper">
    <div class="myModal" v-if="error">
      <div class="close" @click="errorClear()"><span>+</span></div>
      <div class="body">{{errorText}}</div>
    </div>
    <div class="ava_block">
      <div class="background" :class="{'error': error}">
        <img v-if="img" class="icon_temp icon" :src="url+img">
        <img v-else class="icon" src="@/assets/img/ava_no_img.jpg">
      </div>
      <input class="file_container"
             type="file" id="file"
             ref="file" accept="image/png, image/gif, image/jpeg"
             @change="handleFileUpload()"/>
      <div class="plus" @click="loadFile()">+</div>
    </div>
  </div>
</template>

<script>
import {mapActions, mapMutations, mapState} from "vuex";

export default {
  name: "AvaComponent",
  data() {
    return {
      // url:  window.location.href.includes('localhost') ? 'http://prog.work' : 'https://prognos9ys.ru',
      url: 'https://prognos9ys.ru',
      // error: null
      errorText: 'загружаемое изображение превышает 500кб',
      error: false
    }
  },
  props: {
    img: {
      type: String
    },
    pageType: {
      type: String
    }
  },

  computed: {
    ...mapState({
      token: state => state.auth.authData.token,
    })
  },

  methods: {
    ...mapMutations({
      setAvaFile: 'auth/setAvaFile',
      setAvaFileReg: 'reg/setAvaFileReg',
    }),
    ...mapActions({
      avaSetRequest: 'auth/avaSetRequest',
      avaLoadRequest: 'reg/avaLoadRequest',
    }),

    errorClear(){
      this.error = false
    },

    loadFile(){
      console.log('btn load')
      this.$refs.file.click();
    },

    async handleFileUpload(){

      if(this.$refs.file.files[0].size > 500000){
        this.error = true
        setInterval(() => {
          this.error = false
        }, 1800)
      } else {

        if(this.token) {

          // замена аватарки в профиле
          this.setAvaFile(this.$refs.file.files[0])
          await this.avaSetRequest()
          location.reload()

        } else {

          // загрузка аватарки при регистрации
          this.setAvaFileReg(this.$refs.file.files[0])
          await this.avaLoadRequest()

        }
      }
    },
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.ava_wrapper {
  width: 110px;
  height: 110px;
  position: relative;
  display: inline-block;
  margin: 0 auto;
  z-index: 25;

  .myModal{
    z-index: 40;
    position: absolute;
    left: 50%;
    top:25px;
    transform: translateX(-50%);
    width: 200px;
    color: @darkbg;
    background: rgba(255,92,92,0.7);
    border-radius: 20px;
    padding: 8px;
    padding-right: 30px;
    .close{
      position: absolute;
      top: 50%;
      right: 10px;
      font-size: 40px;
      font-weight: 700;
      transform: translateY(-50%);
      span{
        display: block;
        transform: rotate(45deg);
      }
    }
  }

  .file_container{
    display: none;
  }

  .ava_block{
    position: relative;
    left: 50%;
    transform: translateX(-50%);
    width: 120px;
    height: 120px;
  }

  .background {
    width: 120px;
    height: 120px;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;

    /* Согласие, кнопка прозр */
    background: @BackGreenColor;
    border-radius: 100px;
    overflow: hidden;
    border: 4px solid @DarkColorBG;
    &.error{
      filter: grayscale(100%);
    }

    .icon{
      width: 120px;
      height: 120px;
      overflow: hidden;
    }
  }

  .plus {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    position: absolute;
    font-size: 20px;
    bottom: 8px;
    right: 8px;
    width: 24px;
    height: 24px;
    background: @DarkColorBG;

    cursor: pointer;
    /* Белый */

    border: 3px solid @DarkColorBG;
    border-radius: 100px;

    color: #FFFFFF;
    z-index: 35;
  }
}
</style>