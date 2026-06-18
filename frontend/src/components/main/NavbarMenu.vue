<template>
  <div class="menu_wrapper">
    <div class="menu_item_wrapper" v-for="(btn, index) in menuItems" :key="index">
      <div class="menu_item" @click="onMenuClick(index)" :class="{'active': active === index}">
        <div class="icon">
          <img class="icon_img" :src="btn.img_a" alt="" v-if="active === index">
          <img class="icon_img" :src="btn.img" alt="" v-else>
        </div>
        <div class="title">
          {{ btn.title }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { authRoute } from '@/utils/authRedirect';

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
  computed: {
    ...mapState({
      token: state => state.auth.authData.token,
    }),
    menuItems() {
      if (this.token) {
        return this.menu;
      }

      return {
        ...this.menu,
        profile: {
          ...this.menu.profile,
          title: 'Вход',
        },
      };
    },
  },
  watch: {
    $route: {
      immediate: true,
      handler(route) {
        this.active = this.resolveActive(route.path);
      },
    },
  },
  methods: {
    resolveActive(path) {
      if (path.startsWith('/football') || path.startsWith('/championship')) {
        return 'catalog';
      }

      if (path.startsWith('/profile')) {
        return 'profile';
      }

      const segment = path.split('/').filter(Boolean)[0] || 'catalog';
      return segment;
    },
    onMenuClick(index) {
      if (index === 'profile' && !this.token) {
        this.$router.push(authRoute(this.$route.fullPath));
        return;
      }

      this.$router.push('/' + index);
    },
  },
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
    cursor: pointer;
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
