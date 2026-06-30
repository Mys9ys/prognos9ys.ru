<template>
<!--  <SubscribeBtn></SubscribeBtn>-->
  <div class="header_wrapper" :class="{'header_impersonating': impersonation.active}">
    <div class="h_main_block">
      <div class="hm_left_block">
        <div class="hm_achieve_block">
          <div class="hm_level_block hm_box" v-if="displayGameProgress">
            <div class="hm_level_top">
              <div class="hm_level_title">
                <span class="hm_level_word">Ур.</span>
                <span class="hm_level_num">{{ displayGameProgress.level }}</span>
              </div>
              <div class="hm_level_bar_wrap">
                <div class="hm_level_bar">
                  <div
                      class="hm_level_fill"
                      :style="{ width: displayGameProgress.progress_percent + '%' }"
                  ></div>
                </div>
                <div class="hm_level_xp_line" v-if="displayGameProgress.next_min_xp">
                  {{ displayGameProgress.xp }}/{{ displayGameProgress.next_min_xp }}
                </div>
                <div class="hm_level_xp_line" v-else>{{ displayGameProgress.xp }} XP</div>
              </div>
            </div>
          </div>
          <div class="hm_achieve_box hm_box rank" v-else>
            Ур. 0
          </div>
          <div class="hm_money_box hm_box">
            <AppIcon name="prognobak" :size="16" class="hm_money_icon" />
            <span class="hm_money_value">{{ displayWallet.prognobaks }}</span>
          </div>
          <div class="hm_premium_box hm_box" v-if="!isGuest">
            <button
              v-if="canActivatePremiumScroll"
              type="button"
              class="hm_premium_plus"
              :disabled="activatingPremium"
              title="Активировать свиток премиума"
              @click="activatePremiumFromHeader"
            >+</button>
            <span class="hm_premium_icon" title="Премиум">⭐</span>
            <span
              class="hm_premium_value"
              :class="{ active: premiumInfo.active }"
              :title="premiumUntilTitle"
            >{{ premiumTimeLabel }}</span>
          </div>
        </div>
        <div class="hm_btn_block">
<!--          <BtnMini v-for="(btn, index) in l_btns"-->
<!--                   :key="index"-->
<!--                   @click="$router.push('/' + btn.link)"-->
<!--                   :img="btn.img"></BtnMini>-->
        </div>
      </div>
      <AvaComponent v-if="$router.currentRoute.value.path === '/register'" class="hm_ava_block"
                    :pageType="'reg'"
                    :img="$store.state.reg.avaLink"
      ></AvaComponent>

      <div v-else class="hm_ava_block" :class="{'hm_ava_guest': isGuest}" @click="onGuestHeaderClick">
        <AvaComponent
            :img="displayAva"
            :readonly="isGuest"
        ></AvaComponent>
      </div>

      <div class="hm_right_block">
        <div class="hm_user_block">
          <div class="hm_nick_box hm_box nickname"
               :class="nickSizeClass"
               :style="nickFontStyle"
               @click="onGuestHeaderClick">
            {{ displayNick }}
          </div>
          <div class="hm_rublius_box hm_box">
            <AppIcon name="rublius" :size="16" class="hm_money_icon" />
            <span class="hm_money_value">{{ displayWallet.rublius }}</span>
          </div>
          <button
              v-if="showClaimXpBtn"
              type="button"
              class="hm_claim_xp_btn"
              :disabled="claimingAllXp"
              @click="claimAllExperience"
          >
            <AppIcon name="xp" :size="14" class="hm_claim_xp_icon" />
            <span class="hm_claim_xp_points">{{ claimingAllXp ? '...' : `+${pendingXpDisplay}` }}</span>
          </button>
        </div>
      </div>
    </div>
    <ImpersonationBanner v-if="impersonation.active" class="hm_impersonation"></ImpersonationBanner>
  </div>
</template>
<script>
import {mapActions, mapState} from "vuex";
import AvaComponent from "@/components/main/AvaComponent";
import ImpersonationBanner from "@/components/profile/ImpersonationBanner";
import AppIcon from '@/components/ui/AppIcon.vue';
import {authRoute} from '@/utils/authRedirect';
import { apiActions } from '@/api/bitrixClient';
// import BtnMini from "@/components/ui/btn/BtnMini";

export default {
  name: "HeaderBlock",
  components: {
    AvaComponent,
    ImpersonationBanner,
    AppIcon,
    // BtnMini
  },
  data() {
    return {
      l_btns: [
        {link:'main', img: require('@/assets/icon/header/home.svg'), name: 'Главная'},
        {link:'catalog', img: require('@/assets/icon/header/list.svg'), name: 'Каталог'},
        {link:'humor', img: require('@/assets/icon/header/fio.svg'), name: 'Шутки'},
      ],

      r_btns: [
        {link:'ratings', img: require('@/assets/icon/header/bonus.svg'), name: 'Рейтинги'},
        {link:'profile', img: require('@/assets/icon/header/profile.svg'), name: 'Профиль'},
        // {link:'logout', img: require('@/assets/icon/header/exit.svg'), name: 'Выход'},
      ],
      menu: false,
      claimingAllXp: false,
      claimXpError: '',
      activatingPremium: false,
    }
  },

  watch: {
    token(){
      this.checkAuth()
    },
    userId() {
      this.evaluateLevelBanner();
    },
    currentLevel() {
      this.evaluateLevelBanner();
    },
    'impersonation.active'(active) {
      if (active && this.token) {
        this.refreshGameInfo();
      }
    },
    '$route.path'() {
      if (this.token) {
        this.refreshGameInfo();
      }
    },
  },


  mounted() {
    this.$nextTick(function () {
      if (this.token) {
        // проверка токена на актуальность
        this.checkAuth()
      }
      this.evaluateLevelBanner()
    })
  },
  computed: {
    ...mapState({
      token: state => state.auth.authData.token,
      userInfo: state => state.auth.userInfo,
      impersonation: state => state.auth.impersonation,
    }),
    isGuest() {
      return !this.token;
    },
    displayNick() {
      if (this.isGuest) {
        return 'это вы';
      }
      return this.userInfo?.NAME || 'Гость';
    },
    nickSizeClass() {
      return {
        hm_guest_nick: this.isGuest,
      };
    },
    nickFontStyle() {
      const len = this.displayNick.length;
      let fontSize = 15;

      if (len > 18) {
        fontSize = 10;
      } else if (len > 15) {
        fontSize = 11;
      } else if (len > 12) {
        fontSize = 12;
      } else if (len > 10) {
        fontSize = 13;
      } else if (len > 8) {
        fontSize = 14;
      }

      return { fontSize: `${fontSize}px` };
    },
    displayGameProgress() {
      if (this.isGuest) {
        return {
          level: 0,
          progress_percent: 0,
          xp: 0,
          next_min_xp: null,
        };
      }
      return this.gameProgress;
    },
    displayWallet() {
      if (this.isGuest) {
        return { prognobaks: '0', rublius: '0' };
      }
      return this.wallet;
    },
    displayAva() {
      return this.isGuest ? '' : (this.userInfo?.ava || '');
    },
    gameProgress() {
      return this.userInfo?.game_info?.progress || null;
    },
    userId() {
      return Number(this.userInfo?.ID || 0);
    },
    currentLevel() {
      return Number(this.gameProgress?.level || 0);
    },
    wallet() {
      const wallet = this.userInfo?.game_info?.wallet || {};
      const prognobaks = Number(wallet.prognobaks ?? 0).toFixed(1).replace(/\.0$/, '');
      const rublius = Number(wallet.rublius ?? 0).toFixed(1).replace(/\.0$/, '');

      return {
        prognobaks,
        rublius,
      };
    },
    premiumInfo() {
      return this.userInfo?.game_info?.premium || {};
    },
    premiumTimeLabel() {
      if (this.premiumInfo.active) {
        const untilLabel = this.formatPremiumUntil(this.premiumInfo.until);
        if (untilLabel) {
          return untilLabel;
        }
        const seconds = Number(this.premiumInfo.remaining_seconds ?? 0);
        if (seconds > 0) {
          return this.formatPremiumDuration(seconds);
        }
      }
      if (Number(this.premiumInfo.scrolls_total ?? 0) > 0) {
        return 'свиток';
      }
      return '—';
    },
    premiumUntilTitle() {
      if (!this.premiumInfo.active) {
        return 'Премиум';
      }
      const untilLabel = this.formatPremiumUntil(this.premiumInfo.until, true);
      return untilLabel ? `Премиум ${untilLabel}` : 'Премиум активен';
    },
    canActivatePremiumScroll() {
      return Number(this.premiumInfo.scrolls_total ?? 0) > 0;
    },
    pendingXp() {
      const pending = this.userInfo?.game_info?.pending_xp || {};
      return {
        count: Number(pending.count ?? 0),
        points: Number(pending.points ?? 0),
      };
    },
    pendingXpDisplay() {
      const points = this.pendingXp.points;
      return Number.isInteger(points) ? points : points.toFixed(1);
    },
    showClaimXpBtn() {
      return !this.isGuest
        && !this.premiumInfo.active
        && (this.pendingXp.count > 0 || this.pendingXp.points > 0);
    },
  },


  methods: {
    ...mapActions({
      loginRequest: 'auth/loginRequest',
      logoutVue: 'auth/logoutVue',
      refreshGameInfo: 'auth/refreshGameInfo',
      claimAllXp: 'game/claimAllXp',
      getNearest: 'mainPage/getNearest',
      evaluateLevelBanner: 'game/evaluateLevelBanner',
      showBulkLevelBanner: 'game/showBulkLevelBanner',
    }),

    logoutProfile() {
      this.logoutVue()
      this.$router.push('/').then(() => { this.$router.go() })
    },

    async checkAuth() {
      if (
        this.userInfo?.game_info
        && this.userInfo?.UF_TOKEN
        && this.userInfo.UF_TOKEN === this.token
      ) {
        return;
      }

      await this.loginRequest()
      await this.refreshGameInfo()
      if (location.pathname === '/mob_app/') this.$router.push('/main')
    },
    onGuestHeaderClick() {
      if (!this.isGuest) {
        return;
      }
      this.$router.push(authRoute(this.$route.fullPath));
    },
    async claimAllExperience() {
      if (this.claimingAllXp || !this.showClaimXpBtn) {
        return;
      }

      this.claimingAllXp = true;
      this.claimXpError = '';

      try {
        const result = await this.claimAllXp();
        this.$store.state.mainPage.setToken.userToken = this.token;
        await Promise.all([
          this.getNearest(),
          this.refreshGameInfo(),
        ]);

        if (result?.level_up) {
          this.showBulkLevelBanner({
            oldLevel: result.old_level,
            newLevel: result.new_level,
            levelRewards: result.level_rewards,
          });
        } else {
          this.evaluateLevelBanner();
        }
      } catch (error) {
        this.claimXpError = error.message || 'Не удалось собрать опыт';
      } finally {
        this.claimingAllXp = false;
      }
    },

    formatPremiumUntil(until, full = false) {
      if (!until) {
        return '';
      }
      const normalized = String(until).trim().replace(' ', 'T');
      const date = new Date(normalized);
      if (Number.isNaN(date.getTime())) {
        return '';
      }
      const pad = (value) => String(value).padStart(2, '0');
      const day = pad(date.getDate());
      const month = pad(date.getMonth() + 1);
      const hours = pad(date.getHours());
      const minutes = pad(date.getMinutes());
      if (full) {
        return `до ${day}.${month}.${date.getFullYear()} ${hours}:${minutes}`;
      }
      return `до ${day}.${month} ${hours}:${minutes}`;
    },

    formatPremiumDuration(totalSeconds) {
      const seconds = Math.max(0, Number(totalSeconds) || 0);
      const days = Math.floor(seconds / 86400);
      const hours = Math.floor((seconds % 86400) / 3600);
      const minutes = Math.floor((seconds % 3600) / 60);
      if (days > 0) {
        return hours > 0 ? `${days}д ${hours}ч` : `${days}д`;
      }
      if (hours > 0) {
        return minutes > 0 ? `${hours}ч ${minutes}м` : `${hours}ч`;
      }
      if (minutes > 0) {
        return `${minutes}м`;
      }
      return '<1м';
    },

    async activatePremiumFromHeader() {
      if (!this.token || this.activatingPremium || !this.canActivatePremiumScroll) {
        return;
      }

      this.activatingPremium = true;
      try {
        const data = await apiActions.game.activatePremiumScroll(this.token, 0, false);
        if (data?.game) {
          const game = {
            ...data.game,
            premium: data.premium || data.game.premium,
          };
          this.$store.commit('auth/setUserInfo', {
            ...this.userInfo,
            game_info: game,
          });
        } else {
          await this.refreshGameInfo();
        }
      } catch (error) {
        console.log('activatePremiumFromHeader error', error);
      } finally {
        this.activatingPremium = false;
      }
    },
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

@hm-gap: 4px;
@hm-col-width: 138px;

.header_wrapper {
  position: relative;
  margin: 0 auto;
  max-width: 400px;
  width: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: @DarkColorBG;
  color: @colorText2;
  padding: 8px @hm-gap;
  display: flex;
  flex-direction: column;
  flex-wrap: wrap;
  justify-content: space-between;
  min-height: 88px;
  height: auto;

  margin-bottom: 44px;

  &.header_impersonating {
    margin-bottom: 58px;
  }

  border-radius: 0 0 5px 5px;

  z-index: 15;

  .hm_impersonation {
    position: absolute;
    left: 0;
    right: 0;
    bottom: -40px;
    z-index: 30;
    margin: 0;
  }

  &.header_impersonating .hm_impersonation {
    bottom: -54px;
  }

  .h_header_block {
    display: flex;
    flex-direction: column;
    flex-wrap: nowrap;

    .hh_box {
      margin-bottom: 10px;
      display: inline;
      padding: 2px 10px;
      box-shadow: inset 0 2px 10px 1px rgba(0, 0, 0, .3), inset 0 0 0 60px rgba(0, 0, 0, .3), 0 1px rgba(255, 255, 255, .08);
      //background: linear-gradient(rgb(70,70,70), rgb(120,120,120));
    }
  }

  .h_main_block {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    justify-content: space-between;
    align-items: flex-start;
    position: relative;
    gap: @hm-gap;
    .hm_left_block {
      display: flex;
      flex-direction: column;
      justify-content: flex-end;

      .hm_achieve_block {
        width: @hm-col-width;
        display: flex;
        flex-direction: column;
        gap: @hm-gap;
      }
    }

    .hm_right_block {
      flex: 0 0 @hm-col-width;
      width: @hm-col-width;
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      align-items: stretch;

      .hm_user_block {
        width: @hm-col-width;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: @hm-gap;
      }

      .hm_nick_box {
        width: 100%;
        display: flex;
        flex-direction: row;
        justify-content: flex-end;
        text-align: right;
      }
    }

    .hm_btn_block {
      position: relative;
      display: flex;
      flex-direction: row;
      gap: @hm-gap;
      width: @hm-col-width;
      //height: 45px;
      z-index: 15;

      &.hm_right{
        justify-content: flex-end;
      }

      .header_button {
        display: inline-block;
        background: @colorText2;
        color: @colorText;
        cursor: pointer;
        box-shadow: 0 2px 3px rgba(0, 0, 0, .4), 0 -1px 0 rgba(0, 0, 0, .2);
        padding: 3px;
        border-radius: 3px;
        min-width: 22px;
        text-align: center;
      }
    }

    .hm_ava_block {
      width: 120px;
      position: absolute;
      left: 50%;
      top: -2px;
      transform: translateX(-50%);
      z-index: 5;

      &.hm_ava_guest {
        cursor: pointer;
      }
    }

    .hm_box {
      width: @hm-col-width;
      height: 28px;
      padding: 2px @hm-gap;
      box-shadow: inset 0 2px 10px 1px rgba(0, 0, 0, .3), inset 0 0 0 60px rgba(0, 0, 0, .3), 0 1px rgba(255, 255, 255, .08);
      //background: linear-gradient(rgb(70,70,70), rgb(120,120,120));
    }
    .rank{
    .flex_center;
      justify-content: flex-start;
    }
    .hm_level_block {
      width: @hm-col-width;
      height: 28px;
      padding: 2px @hm-gap;
      display: flex;
      flex-direction: column;
      justify-content: center;
      box-sizing: border-box;

      .hm_level_top {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 6px;
        width: 100%;
      }

      .hm_level_bar_wrap {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0;
      }

      .hm_level_title {
        display: flex;
        flex-direction: row;
        align-items: baseline;
        gap: 3px;
        flex-shrink: 0;
      }

      .hm_level_word {
        font-size: 10px;
        color: @colorBlur;
        line-height: 1;
      }

      .hm_level_num {
        font-size: 14px;
        font-weight: 600;
        color: @orange;
        line-height: 1;
      }

      .hm_level_bar {
        width: 100%;
        height: 6px;
        flex-shrink: 0;
        background: @darkbg;
        border-radius: 3px;
        overflow: hidden;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, .4);
      }

      .hm_level_fill {
        height: 100%;
        background: @orange;
        border-radius: 3px;
        transition: width 0.3s ease;
        min-width: 2px;
      }

      .hm_level_xp_line {
        margin-top: 2px;
        font-size: 9px;
        line-height: 1;
        color: @colorBlur;
        text-align: right;
      }
    }
    .nickname{
      .flex_center;
      justify-content: flex-end;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      min-width: 0;
      max-width: 100%;
      font-size: 15px;
      line-height: 1.1;
      box-sizing: border-box;

      &.hm_guest_nick {
        cursor: pointer;
      }
    }
    .hm_achieve_box{
      text-align: left;
    }

    .hm_money_box,
    .hm_rublius_box,
    .hm_premium_box {
      width: @hm-col-width;
      height: 22px;
      display: flex;
      flex-direction: row;
      align-items: center;
      gap: @hm-gap;
      font-size: 11px;
      box-sizing: border-box;
    }

    .hm_premium_box {
      justify-content: flex-start;
      color: @colorBlur;
      text-align: left;
    }

    .hm_premium_icon {
      flex-shrink: 0;
      font-size: 10px;
      line-height: 1;
    }

    .hm_premium_value {
      flex: 1;
      min-width: 0;
      font-weight: 600;
      line-height: 1;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;

      &.active {
        color: @orange;
      }
    }

    .hm_premium_plus {
      flex-shrink: 0;
      width: 16px;
      height: 16px;
      margin-right: 1px;
      padding: 0;
      border: 1px solid rgba(247, 196, 23, 0.55);
      border-radius: 3px;
      background: @DarkColorBG;
      color: @orange;
      font-size: 11px;
      font-weight: 700;
      line-height: 1;
      cursor: pointer;

      &:disabled {
        opacity: 0.6;
        cursor: default;
      }
    }

    .hm_money_box {
      justify-content: flex-start;
      color: @YesWrite2;
      text-align: left;
    }

    .hm_rublius_box {
      justify-content: flex-end;
      color: @valleyball;
      text-align: right;
    }

    .hm_claim_xp_btn {
      display: inline-flex;
      flex-direction: row;
      align-items: center;
      justify-content: center;
      gap: 3px;
      width: auto;
      height: 22px;
      padding: 0 6px;
      border: 1px solid rgba(247, 196, 23, 0.55);
      border-radius: 4px;
      cursor: pointer;
      color: @DarkColorBG;
      background: @orange;
      font-size: 10px;
      font-weight: 700;
      box-sizing: border-box;
      flex-shrink: 0;

      &:disabled {
        opacity: 0.7;
        cursor: default;
      }
    }

    .hm_claim_xp_icon {
      flex-shrink: 0;
    }

    .hm_claim_xp_points {
      line-height: 1;
      color: @DarkColorBG;
      white-space: nowrap;
    }

    .hm_money_icon {
      flex-shrink: 0;
    }

    .hm_money_value {
      font-weight: 600;
      line-height: 1;
    }
  }
}

.hm_right {
  text-align: right;
}

.header_menu_icon {
  width: 16px;
  height: 16px;
}

.menu_events_btn {
  cursor: pointer;
  position: relative;

  .bi-caret {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    right: 16px;
  }
}

.menu_events_box {
  display: none;

  ul {
    margin-left: 0;
    padding-left: 0;

    li {
      list-style-type: none;
    }
  }
}
</style>