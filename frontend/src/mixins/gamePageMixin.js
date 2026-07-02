import { mapActions, mapState } from 'vuex';

/**
 * Подгружает game_info в store, если странице нужен кошелёк/инвентарь.
 * На странице: skipGameBootstrap: true — чтобы не вызывать полный getState.
 */
export default {
  computed: {
    ...mapState({
      gameInfo: (state) => state.auth.userInfo?.game_info || null,
      token: (state) => state.auth.authData.token,
    }),
  },
  created() {
    if (this.$options.skipGameBootstrap) {
      return;
    }
    if (this.token && !this.gameInfo) {
      this.refreshGameInfo();
    }
  },
  methods: {
    ...mapActions('auth', ['refreshGameInfo']),
  },
};
