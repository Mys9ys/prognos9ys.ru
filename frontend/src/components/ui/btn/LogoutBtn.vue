<template>
  <li>
    <div class="dropdown-item" @click="logout()">
      <img src="@/assets/icon/profile/exit.svg" alt="">
      <slot></slot>
    </div>
  </li>
</template>

<script>
import {mapActions} from "vuex";

export default {
  name: "LogoutBtn",
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