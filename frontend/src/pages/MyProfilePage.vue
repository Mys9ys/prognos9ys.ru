<template>
  <PageHeader class="header">Ваш профиль</PageHeader>

  <div class="title_block">
    <div class="title_wrapper" v-for="(el, index) in profileMenu" :key="index" @click="active = index" :class="{'active':active === index}">
      <div class="title">
        <div class="icon">
          <img class="icon_img" :src="el.img" alt="">
        </div>
        <div class="name" v-if="index === active">{{ el.title }}</div>
      </div>
    </div>
  </div>

  <div class="body_block">
    <div class="body_item" v-if="active === 'prognosis'">

      <div class="title_wrapper">
        <div class="title">
          Ваши прогнозы
        </div>
      </div>
      <div class="football_block" v-if="profileData.football">
        <div class="football_title_block">
          <ProfileTitle v-for="(arr, index) in profileData.football"
                        @click="setActiveEvent(index)"
                        :info="arr.info"
                        :active="index === activeEvent"
                        :count="Object.keys(arr.items).length"
                        :class="{'active': index === activeEvent}"
                        :key="index"></ProfileTitle>
        </div>
        <div class="football_body_block" v-for="(arr, index) in profileData.football"
             :key="index">

          <ProfileEventBody v-if="index == activeEvent"
                            :matches="arr.items"
                            :title="arr.info.NAME"
          ></ProfileEventBody>
        </div>
      </div>
      <div class="race_block" v-if="profileData.race">
        <ProfileRaceBlock
            :events="profileData.race"
            :racers="profileData.racers"
        ></ProfileRaceBlock>
      </div>
    </div>
    <div class="body_item" v-if="active === 'achievement'">
      <div class="title_wrapper">
        <div class="title">
          Ачивки
        </div>
      </div>
      <ProfileAchievementBlock></ProfileAchievementBlock>
    </div>
    <div class="body_item" v-if="active === 'settings'">

      <div class="title_wrapper">
        <div class="title">
          Настройки
        </div>
      </div>

      <div class="settings_item" @click="logoutProfile">
        <div class="icon">
          <img :src="require('@/assets/icon/header/exit.svg')" alt="">
        </div>
        <div class="title">Выйти из приложения</div>
      </div>
    </div>

  </div>
</template>

<script>
import PageHeader from "@/components/main/PageHeader";
import ProfileAchievementBlock from "@/components/achievement/ProfileAchievementBlock";
import {mapActions, mapState} from "vuex";

import ProfileEventBody from "@/components/football/ProfileEventBody";
import ProfileRaceBlock from "@/components/profile/ProfileRaceBlock";
import ProfileTitle from "@/components/profile/ProfileTitle";

export default {
  name: "MyProfilePage",
  components: {
    ProfileRaceBlock,
    PageHeader,
    ProfileAchievementBlock,
    ProfileTitle,
    ProfileEventBody
  },
  data() {
    return {
      url:  'https://prognos9ys.ru',
      profileLoader: false,
      activeEvent: '',

      active: 'prognosis',
      profileMenu: {
        prognosis: {title: 'Прогнозы', img: require('@/assets/icon/profile/prognosis.svg')},
        achievement: {title: 'Награды', img: require('@/assets/icon/profile/achievement.svg')},
        settings : {title: 'Настройки', img: require('@/assets/icon/profile/settings.svg')},
      }
    }
  },

  created() {
    this.profileLoader = true

    this.fillProfile()
  },

  methods: {
    ...mapActions({
      getProfileInfo: 'profile/getProfileData',
      logoutVue: 'auth/logoutVue'
    }),

    setActiveEvent(id){
      this.activeEvent = id
    },

    logoutProfile() {
      this.logoutVue()
      this.$router.push('/').then(() => { this.$router.go() })
    },

    async fillProfile() {
      this.profileRequest['userToken'] = this.token

      await this.getProfileInfo()
      this.profileLoader = false

    }
  },

  computed: {
    ...mapState({
      profileData: state => state.profile.profileData,
      profileRequest: state => state.profile.profileRequest,

      token: state => state.auth.authData.token,
    })
  },

}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.title_block {
  display: flex;
  flex-direction: row;
  gap: 4px;
}

.title_wrapper {
  background: @DarkColorBG;
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  margin-top: 8px;
  text-align: left;
  margin-bottom: 6px;

  .title {
    display: flex;
    flex-direction: row;
    gap: 4px;
    .shadow_inset;
    .icon{display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 2px;
      .icon_img{
        width: 20px;
        height: 20px;
      }
    }
  }
  &.active{
    background: @YesWrite;
  }
}
.user_block{
  display: flex;
  flex-direction: row;

  justify-content: space-between;
  gap: 4px;

  .ava_block{
    width: 45%;
    background: @DarkColorBG;
    padding: 4px;
    border-radius: 5px;
    .flex_center;
    img{
      border-radius: 50%;
      max-width: 135px;
      width: 100%;
      border: 2px solid @darkbg;
    }
  }

  .right_block{
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;

    background: @DarkColorBG;
    padding: 4px;
    border-radius: 5px;

    .right_el{
      display: flex;
      flex-direction: row;
      .shadow_inset;
      color: @colorText;
      justify-content: space-between;
      text-align: left;

      .title{
        display: inline-block;
        color: @colorBlur;
      }
    }
  }
}

.prognosis_block{
  .title_wrapper{
    background: @DarkColorBG;
    padding: 4px;
    border-radius: 5px;
    color: @colorText;
    margin-top: 25px;
    .title{
      .shadow_inset;
    }
  }
  margin-bottom: 20px;
}
.football_block{
  padding: 4px;
  .football_title_block{
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 4px;
  }
}
.race_block{
  padding: 4px;
}

.settings_item{
  display: flex;
  flex-direction: row;
  justify-content: flex-start;
  gap: 4px;
  color: @colorText;
}

</style>