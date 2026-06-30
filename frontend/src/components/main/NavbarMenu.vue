<template>
  <div class="navbar_double">
    <div class="menu_row menu_row_top">
      <div
        v-for="item in topRow"
        :key="item.id"
        class="menu_item"
        :class="{ active: isActive(item.id) }"
        @click="onNav(item)"
      >
        <div class="icon_wrap">
          <img v-if="item.img" class="icon_img" :src="isActive(item.id) ? item.img_a : item.img" alt="">
          <AppIcon v-else-if="item.appIcon" :name="item.appIcon" :size="24" />
          <span v-else-if="item.emoji" class="emoji_icon">{{ item.emoji }}</span>
        </div>
        <div class="title">{{ item.title }}</div>
      </div>
    </div>

    <div class="menu_row menu_row_bottom">
      <div
        v-for="item in bottomRow"
        :key="item.id"
        class="menu_item menu_item_inverted"
        :class="{ active: isActive(item.id) }"
        @click="onNav(item)"
      >
        <div class="title">{{ item.title }}</div>
        <div class="icon_wrap">
          <img v-if="item.img" class="icon_img" :src="isActive(item.id) ? item.img_a : item.img" alt="">
          <AppIcon v-else-if="item.appIcon" :name="item.appIcon" :size="24" />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import AppIcon from '@/components/ui/AppIcon.vue';
import { authRoute } from '@/utils/authRedirect';

export default {
  name: 'NavbarMenu',
  components: { AppIcon },
  data() {
    return {
      activeId: '',
      topRow: [
        { id: 'main', title: 'Главная', img: require('@/assets/icon/menu/home.svg'), img_a: require('@/assets/icon/menu/home_a.svg'), route: '/main' },
        { id: 'catalog', title: 'События', img: require('@/assets/icon/menu/catalog.svg'), img_a: require('@/assets/icon/menu/catalog_a.svg'), route: '/catalog' },
        { id: 'profile', title: 'Профиль', img: require('@/assets/icon/menu/profile.svg'), img_a: require('@/assets/icon/menu/profile_a.svg'), route: '/profile' },
        { id: 'inventory', title: 'Инвентарь', emoji: '🎒', route: { path: '/profile', query: { tab: 'inventory' } }, auth: true },
        { id: 'ratings', title: 'Рейтинги', img: require('@/assets/icon/menu/ratings.svg'), img_a: require('@/assets/icon/menu/ratings_a.svg'), route: '/ratings' },
      ],
      bottomRow: [
        { id: 'bank', title: 'Банки', appIcon: 'bank', route: { path: '/profile', query: { tab: 'economy', eco: 'bank' } }, auth: true },
        { id: 'exchange', title: 'Биржа', appIcon: 'rublius', route: { path: '/profile', query: { tab: 'economy', eco: 'exchange' } }, auth: true },
        { id: 'farm', title: 'Работа', appIcon: 'xp', route: { path: '/profile', query: { tab: 'economy', eco: 'farm' } }, auth: true },
        { id: 'treasury', title: 'Казна', appIcon: 'chest_wc2026', route: { path: '/profile', query: { tab: 'economy', eco: 'treasury' } }, auth: true },
        { id: 'faq', title: 'Как играть', img: require('@/assets/icon/menu/faq.svg'), img_a: require('@/assets/icon/menu/faq_a.svg'), route: '/faq' },
      ],
    };
  },
  computed: {
    ...mapState({
      token: (state) => state.auth.authData.token,
    }),
  },
  watch: {
    $route: {
      immediate: true,
      handler(route) {
        this.activeId = this.resolveActive(route);
      },
    },
  },
  methods: {
    resolveActive(route) {
      const path = route.path || '';
      const tab = route.query?.tab || '';
      const eco = route.query?.eco || '';

      if (tab === 'inventory') {
        return 'inventory';
      }
      if (tab === 'economy') {
        if (eco === 'bank') return 'bank';
        if (eco === 'exchange') return 'exchange';
        if (eco === 'farm') return 'farm';
        if (eco === 'treasury') return 'treasury';
      }

      if (path.startsWith('/football') || path.startsWith('/championship') || path.startsWith('/cs2') || path.startsWith('/race')) {
        return 'catalog';
      }
      if (path.startsWith('/profile')) {
        return tab === 'inventory' ? 'inventory' : 'profile';
      }
      if (path.startsWith('/ratings')) {
        return 'ratings';
      }
      if (path.startsWith('/faq')) {
        return 'faq';
      }
      if (path.startsWith('/main')) {
        return 'main';
      }

      const segment = path.split('/').filter(Boolean)[0] || 'catalog';
      return segment;
    },
    isActive(id) {
      return this.activeId === id;
    },
    onNav(item) {
      if (item.auth && !this.token) {
        this.$router.push(authRoute(this.$route.fullPath));
        return;
      }

      if (item.id === 'profile' && !this.token) {
        this.$router.push(authRoute(this.$route.fullPath));
        return;
      }

      const route = item.route || ('/' + item.id);
      this.$router.push(route);
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.navbar_double {
  width: 100%;
  max-width: 374px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 4px;
  background: @DarkColorBG;
  border-radius: 15px;
  .shadow_template;
}

.menu_row {
  display: flex;
  flex-direction: row;
  gap: 2px;
  justify-content: space-between;
}

.menu_item {
  flex: 1 1 0;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  cursor: pointer;
  padding: 2px 1px;

  .title {
    color: @colorText;
    font-size: 11px;
    line-height: 1.15;
    text-align: center;
    padding: 0 2px;
    word-break: break-word;
  }

  &.active .title {
    color: @YesWrite;
  }
}

.menu_item_inverted {
  flex-direction: column-reverse;
}

.icon_wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
}

.icon_img {
  width: 24px;
  height: 24px;
}

.emoji_icon {
  font-size: 20px;
  line-height: 1;
}
</style>
