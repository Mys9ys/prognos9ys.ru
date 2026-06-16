<template>
  <div class="impersonation_panel" v-if="canImpersonate && !impersonation.active">
    <div class="panel_title">Вход за пользователя</div>
    <div class="panel_hint">Для тестов и отладки</div>

    <input
        class="search_input"
        v-model="query"
        type="text"
        placeholder="Email, имя или ID"
        @input="onSearchInput"
    >

    <div class="search_error" v-if="error">{{ error }}</div>

    <div class="users_list" v-if="users.length">
      <div
          class="user_row"
          v-for="user in users"
          :key="user.id"
          @click="loginAs(user)"
      >
        <div class="user_name">{{ user.name }}</div>
        <div class="user_meta">#{{ user.id }} · {{ user.email || 'без email' }}</div>
      </div>
    </div>

    <div class="search_empty" v-else-if="query.length >= 2 && !loading">
      Ничего не найдено
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex'

export default {
  name: 'ImpersonationPanel',
  data() {
    return {
      query: '',
      users: [],
      loading: false,
      error: '',
      searchTimer: null,
    }
  },
  computed: {
    ...mapState({
      impersonation: state => state.auth.impersonation,
      userInfo: state => state.auth.userInfo,
    }),
    canImpersonate() {
      const role = this.userInfo?.role
      return !!this.userInfo?.can_impersonate
          || role === 'admin'
          || role === 'super_moder'
    },
  },
  methods: {
    ...mapActions({
      searchImpersonationUsers: 'auth/searchImpersonationUsers',
      impersonateStart: 'auth/impersonateStart',
    }),
    onSearchInput() {
      clearTimeout(this.searchTimer)
      this.error = ''

      if (this.query.trim().length < 2) {
        this.users = []
        return
      }

      this.searchTimer = setTimeout(() => {
        this.runSearch()
      }, 300)
    },
    async runSearch() {
      this.loading = true
      try {
        this.users = await this.searchImpersonationUsers(this.query.trim())
      } catch (e) {
        this.error = e.message || 'Ошибка поиска'
        this.users = []
      } finally {
        this.loading = false
      }
    },
    async loginAs(user) {
      this.error = ''
      try {
        await this.impersonateStart(user.id)
        this.$router.push('/').then(() => { this.$router.go() })
      } catch (e) {
        this.error = e.message || 'Не удалось войти'
      }
    },
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.impersonation_panel {
  margin-top: 12px;
  padding: 8px;
  border-radius: 5px;
  background: @DarkColorBG;
  color: @colorText;
  text-align: left;
}

.panel_title {
  font-size: 13px;
  margin-bottom: 2px;
}

.panel_hint {
  font-size: 11px;
  color: @colorBlur;
  margin-bottom: 8px;
}

.search_input {
  width: 100%;
  padding: 6px 8px;
  border-radius: 4px;
  border: 1px solid @colorBlur;
  background: @colorText2;
  color: @colorText;
  font-size: 12px;
}

.search_error {
  margin-top: 6px;
  color: @NoWrite;
  font-size: 11px;
}

.users_list {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.user_row {
  padding: 6px 8px;
  border-radius: 4px;
  background: @colorText2;
  cursor: pointer;

  &:hover {
    background: @YesWrite;
    color: @DarkColorBG;
  }
}

.user_name {
  font-size: 12px;
}

.user_meta {
  font-size: 10px;
  opacity: 0.85;
}

.search_empty {
  margin-top: 8px;
  font-size: 11px;
  color: @colorBlur;
}
</style>
