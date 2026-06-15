<template>
  <PreLoader v-if="profileLoader"></PreLoader>
  <PageHeader class="header">{{profileData.info.NAME}}</PageHeader>
  <div class="user_block">
    <div class="ava_block">
      <img class="ava" :src="url+profileData.info.img" alt="" v-if="profileData.info.img">
      <img class="ava" src="@/assets/img/ava_no_img.jpg" alt="" v-else>
    </div>
    <div class="right_block">
      <div class="right_el"><div class="title">Ник:</div> <span> {{profileData.info.NAME}}</span></div>
      <div class="right_el"><div class="title">Рег. дата:</div> <span>{{profileData.info.reg}}</span></div>
      <div class="right_el"><div class="title">Звание:</div> <span>{{profileData.rank_info.rank.name}}</span></div>
      <div class="right_el"><div class="title">Прогнозов:</div> <span>{{profileData.rank_info.count}}</span></div>
    </div>
  </div>
  <div class="prognosis_block">
    <div class="title_wrapper">
      <div class="title">
        Прогнозы
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
  </div>
</template>

<script>
import {mapActions, mapState} from "vuex";
import PreLoader from "@/components/main/PreLoader";
import PageHeader from "@/components/main/PageHeader";
import ProfileEventBody from "@/components/football/ProfileEventBody";
import ProfileTitle from "@/components/profile/ProfileTitle";

export default {
  name: "ProfilePage",
  components: {
    PreLoader,
    PageHeader,
    ProfileTitle,
    ProfileEventBody
  },
  data() {
    return {
      url:  'https://prognos9ys.ru',
      profileLoader: false,
      activeEvent: ''
    }
  },

  created() {
    this.profileLoader = true

    this.fillProfile()
  },

  methods: {
    ...mapActions({
      getProfileInfo: 'profile/getProfileData',
    }),

    setActiveEvent(id){
      this.activeEvent = id
    },

    async fillProfile() {
      if(this.$route.params.id){
        this.profileRequest['userId'] = this.$route.params.id
      }
      await this.getProfileInfo()
      this.profileLoader = false

    }
  },

  computed: {
    ...mapState({
      profileData: state => state.profile.profileData,
      profileRequest: state => state.profile.profileRequest,
    })
  },

}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
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
  .football_title_block{
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 4px;
  }
}

</style>