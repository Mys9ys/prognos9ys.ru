<template>
  <li>
    <div class="dropdown-item" @click="logout()">
      <AppIcon name="exit_door" :size="16" />
      <slot></slot>
    </div>
  </li>
</template>

<script>
import {mapActions} from "vuex";
import AppIcon from '@/components/ui/AppIcon.vue';

export default {
  name: "LogoutBtn",
  components: { AppIcon },
  methods: {
    ...mapActions({
      logoutVue: 'auth/logoutVue',
    }),
    logout($router, $store) {
      this.logoutVue()
      if (!$store.state.auth.isAuth) $router.push('/')
    },
  }
}
</script>

<style scoped>

</style>
