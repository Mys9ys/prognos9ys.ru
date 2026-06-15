<template>
  <div class="menu_wrapper">
    <div class="menu_item_wrapper" v-for="(btn, index) in menu" :key="index">
      <div class="menu_item" @click="$router.push('/'+index)" :class="{'active': active === index}">
        <div class="icon">
          <img class="icon_img" :src="btn.img_a" alt="" v-if="active === index">
          <img class="icon_img" :src="btn.img" alt="" v-else>
        </div>
        <div class="title">
          {{btn.title}}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "NavbarMenu",
  data(){
    return{
      active: '',
      menu: {
        main: { img: require('@/assets/icon/menu/home.svg'), img_a: require('@/assets/icon/menu/home_a.svg'), title: 'Главная'},
        catalog: {img: require('@/assets/icon/menu/catalog.svg'),img_a: require('@/assets/icon/menu/catalog_a.svg'), title: 'События'},
        ratings: {img: require('@/assets/icon/menu/ratings.svg'), img_a: require('@/assets/icon/menu/ratings_a.svg'), title: 'Рейтинги'},
        profile: {img: require('@/assets/icon/menu/profile.svg'), img_a: require('@/assets/icon/menu/profile_a.svg'), title: 'Профиль'},
        faq: {img: require('@/assets/icon/menu/faq.svg'), img_a: require('@/assets/icon/menu/faq_a.svg'), title: 'Правила'}
      }
    }
  },
  watch: {
    $route(){
      this.active = this.$route.path.replace('/', '')
    }
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.menu_wrapper{
  width: 400%;
  max-width: 374px;
  display: flex;
  flex-direction: row;
  gap: 4px;
  justify-content: space-around;
  padding: 4px;
  background: @DarkColorBG;
  border-radius: 15px;
  .shadow_template;
  .menu_item_wrapper{
    display: flex;
    flex-direction: column;
    gap: 4px;
    //background: @colorBlur;
  }
  .menu_item{
    position: relative;
    .icon{
      color: @YesWrite;
      .icon_img{
        width: 24px;
        height: 24px;
      }
    }
    .title{
      color: @colorText;
      font-size: 13px;
      padding: 3px 5px;
    }
  }
  .active{
    .title {
      color: @YesWrite;
    }
  }
}
</style>