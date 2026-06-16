<template>
<!--  <SubscribeBtn></SubscribeBtn>-->
  <div class="header_wrapper">
    <div class="h_main_block">
      <div class="hm_left_block">
        <div class="hm_achieve_block">
          <div class="hm_level_block hm_box" v-if="gameProgress">
            <div class="hm_level_top">
              <div class="hm_level_title">
                <span class="hm_level_word">Ур.</span>
                <span class="hm_level_num">{{ gameProgress.level }}</span>
              </div>
              <div class="hm_level_bar_wrap">
                <div class="hm_level_bar">
                  <div
                      class="hm_level_fill"
                      :style="{ width: gameProgress.progress_percent + '%' }"
                  ></div>
                </div>
                <div class="hm_level_xp_line" v-if="gameProgress.next_min_xp">
                  {{ gameProgress.xp }}/{{ gameProgress.next_min_xp }}
                </div>
                <div class="hm_level_xp_line" v-else>{{ gameProgress.xp }} XP</div>
              </div>
            </div>
          </div>
          <div class="hm_achieve_box hm_box rank" v-else>
            Ур. 0
          </div>
          <div class="hm_money_box hm_box">
            <span class="hm_money_label">💵</span>
            <span class="hm_money_value">{{ wallet.prognobaks }}</span>
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

      <AvaComponent v-else class="hm_ava_block"
                    :img="$store.state.auth.userInfo.ava"
      ></AvaComponent>

      <div class="hm_right_block">
        <div class="hm_nick_wrap">
          <div class="hm_nick_box hm_box nickname" v-if="userInfo.NAME"
               :class="{'rank15' : userInfo.NAME.length >14, 'rank20' : userInfo.NAME.length >19}">
            {{ userInfo.NAME }}
          </div>
          <div class="hm_nick_box hm_box nickname" v-else>Гость</div>
          <div class="hm_rublius_box hm_box">
            <span class="hm_money_label">💎</span>
            <span class="hm_money_value">{{ wallet.rublius }}</span>
          </div>
        </div>


        <div class="hm_btn_block hm_right">


<!--          <BtnMini v-for="(btn, index) in r_btns"-->
<!--                   :key="index"-->
<!--                   @click="$router.push('/' + btn.link)"-->
<!--                   :img="btn.img"></BtnMini>-->
<!--          <BtnMini @click="logoutProfile"-->
<!--                   :img="require('@/assets/icon/header/exit.svg')"></BtnMini>  -->
<!--          <BtnMini :img="require('@/assets/icon/header/envelope.svg')"></BtnMini>-->
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
// import BtnMini from "@/components/ui/btn/BtnMini";

export default {
  name: "HeaderBlock",
  components: {
    AvaComponent,
    ImpersonationBanner,
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
      menu: false
    }
  },

  watch: {
    token(){
      this.checkAuth()
    }
  },


  mounted() {
    this.$nextTick(function () {
      if (this.token) {
        // проверка токена на актуальность
        this.checkAuth()
      }
    })
  },
  computed: {
    ...mapState({
      token: state => state.auth.authData.token,
      userInfo: state => state.auth.userInfo,
      impersonation: state => state.auth.impersonation,
    }),
    gameProgress() {
      return this.userInfo?.game_info?.progress || null;
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
  },


  methods: {
    ...mapActions({
      loginRequest: 'auth/loginRequest',
      logoutVue: 'auth/logoutVue'
    }),

    logoutProfile() {
      this.logoutVue()
      this.$router.push('/').then(() => { this.$router.go() })
    },

    async checkAuth() {
      await this.loginRequest()
      if (location.pathname === '/mob_app/') this.$router.push('/main')
    }
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.header_wrapper {
  position: relative;
  margin: 0 auto;
  max-width: 400px;
  width: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: @DarkColorBG;
  color: @colorText2;
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  flex-wrap: wrap;
  justify-content: space-between;
  height: 88px;

  margin-bottom: 44px;

  border-radius: 0 0 5px 5px;

  z-index: 15;

  .hm_impersonation {
    position: absolute;
    left: 0;
    right: 0;
    bottom: -40px;
    z-index: 14;
    margin: 0;
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
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-end;
    position: relative;
    //height: 60px;
    .hm_left_block {
      display: flex;
      flex-direction: column;
      justify-content: flex-end;

      .hm_achieve_block {
        width: 130px;
        display: flex;
        flex-direction: column;
        gap: 4px;
      }
    }

    .hm_right_block {
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      align-items: flex-end;

      .hm_nick_wrap {
        width: 130px;
        display: flex;
        flex-direction: column;
        gap: 4px;
      }

      .hm_nick_box {
        width: 130px;
        display: flex;
        flex-direction: row;
        text-align: right;
      }
    }

    .hm_btn_block {
      position: relative;
      display: flex;
      flex-direction: row;
      gap: 4px;
      width: 128px;
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
      top: 0px;
      transform: translateX(-50%);
      z-index: 5;
    }

    .hm_box {
      width: 130px;
      height: 28px;
      padding: 2px 4px;
      box-shadow: inset 0 2px 10px 1px rgba(0, 0, 0, .3), inset 0 0 0 60px rgba(0, 0, 0, .3), 0 1px rgba(255, 255, 255, .08);
      //background: linear-gradient(rgb(70,70,70), rgb(120,120,120));
    }
    .rank{
    .flex_center;
      justify-content: flex-start;
    }
    .hm_level_block {
      width: 130px;
      height: 28px;
      padding: 2px 4px;
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
        gap: 1px;
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
        font-size: 7px;
        line-height: 1;
        color: @colorBlur;
        text-align: right;
      }
    }
    .nickname{
      .flex_center;
      justify-content: flex-end;
    }
    .rank10{
      font-size: 15px;
    }
    .rank15{
      font-size: 13px;
    }
    .rank20{
      font-size: 12px;
    }
    .hm_achieve_box{
      text-align: left;
    }

    .hm_money_box,
    .hm_rublius_box {
      height: 22px;
      display: flex;
      flex-direction: row;
      align-items: center;
      gap: 4px;
      font-size: 11px;
      box-sizing: border-box;
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

    .hm_money_label {
      font-size: 11px;
      line-height: 1;
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