<template>
  <PageHeader class="header">Ваш профиль</PageHeader>

  <div class="title_block panel_main">
    <div
      class="title_wrapper"
      v-for="(el, index) in profileMenu"
      :key="index"
      @click="active = index"
      :class="{'active': active === index}"
    >
      <div class="title">
        <div class="icon emoji_icon" v-if="el.emoji">{{ el.emoji }}</div>
        <div class="icon" v-else-if="el.icon">
          <AppIcon :name="el.icon" :size="20" />
        </div>
        <div class="icon" v-else>
          <img class="icon_img" :src="el.img" alt="">
        </div>
      </div>
    </div>
  </div>

  <div class="body_block">
    <div class="body_item" v-if="active === 'general'">
      <div class="title_wrapper">
        <div class="title">Общая информация</div>
      </div>
      <ProfileGameBlock :game="gameInfo" v-if="gameInfo"></ProfileGameBlock>
      <ProfileRulesBlock />
    </div>

    <div class="body_item" v-if="active === 'prognosis'">

      <div class="title_wrapper">
        <div class="title">
          Ваши прогнозы
        </div>
      </div>

      <div class="title_block panel_secondary prognosis_type_tabs">
        <div class="title_wrapper compact" :class="{'active': prognosisType === 'football'}" @click="setPrognosisType('football')">
          <div class="title">
            <div class="icon">
              <AppIcon name="football" :size="22" />
            </div>
          </div>
        </div>
        <div class="title_wrapper compact" :class="{'active': prognosisType === 'race'}" @click="setPrognosisType('race')">
          <div class="title">
            <div class="icon">
              <AppIcon name="f1_race" :size="22" />
            </div>
          </div>
        </div>
      </div>

      <div class="period_filter" v-if="prognosisActiveCount || prognosisPastCount">
        <div
          class="period_btn"
          v-if="prognosisActiveCount"
          :class="{ active: prognosisStatus === 'active' }"
          @click="setPrognosisStatus('active')"
        >
          <div class="period_btn_inner">
            Активные
            <span class="count" v-if="prognosisActiveCount">{{ prognosisActiveCount }}</span>
          </div>
        </div>
        <div
          class="period_btn"
          v-if="prognosisPastCount"
          :class="{ active: prognosisStatus === 'past' }"
          @click="setPrognosisStatus('past')"
        >
          <div class="period_btn_inner">
            Прошедшие
            <span class="count" v-if="prognosisPastCount">{{ prognosisPastCount }}</span>
          </div>
        </div>
      </div>

      <div class="football_block" v-if="prognosisType === 'football' && hasFootballFiltered">
        <div class="football_title_block">
          <ProfileTitle v-for="(arr, eventId) in filteredFootballEvents"
                        @click="setActiveFootballEvent(eventId)"
                        :info="arr.info"
                        :active="String(eventId) === String(activeFootballEvent)"
                        :count="Object.keys(arr.items).length"
                        :class="{'active': String(eventId) === String(activeFootballEvent)}"
                        :key="eventId"></ProfileTitle>
        </div>
        <div class="football_body_block" v-for="(arr, eventId) in filteredFootballEvents"
             :key="`${prognosisStatus}-${eventId}`">

          <ProfileEventBody v-if="String(eventId) === String(activeFootballEvent)"
                            :key="`${prognosisStatus}-${eventId}-body`"
                            :matches="arr.items"
                            :title="arr.info.NAME"
                            :event-img="arr.info.img"
          ></ProfileEventBody>
        </div>
      </div>
      <div class="race_block" v-if="prognosisType === 'race' && hasRaceFiltered">
        <ProfileRaceBlock
            :events="filteredRaceEvents"
            :racers="profileData.racers"
        ></ProfileRaceBlock>
      </div>
      <div class="empty_text" v-if="prognosisType === 'football' && !hasFootballFiltered">
        Нет {{ prognosisStatus === 'active' ? 'активных' : 'прошедших' }} футбольных прогнозов
      </div>
      <div class="empty_text" v-if="prognosisType === 'race' && !hasRaceFiltered">
        Нет {{ prognosisStatus === 'active' ? 'активных' : 'прошедших' }} прогнозов Ф1
      </div>
    </div>

    <div class="body_item" v-if="active === 'economy'">
      <div class="title_wrapper">
        <div class="title">Богатство и финансы</div>
      </div>
      <ProfileEconomyBlock :game="gameInfo" v-if="gameInfo" />
    </div>

    <div class="body_item" v-if="active === 'inventory'">
      <div class="title_wrapper">
        <div class="title">Инвентарь</div>
      </div>
      <ProfileInventoryBlock :game="gameInfo" v-if="gameInfo" />
    </div>

    <div class="body_item" v-if="active === 'collection'">
      <div class="title_wrapper">
        <div class="title">Коллекция</div>
      </div>
      <ProfileAlbumBlock v-if="gameInfo" />
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

      <ImpersonationPanel></ImpersonationPanel>

      <div class="settings_item" @click="logoutProfile">
        <div class="icon">
          <AppIcon name="exit_door" :size="20" />
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
import ProfileGameBlock from "@/components/profile/ProfileGameBlock";
import ProfileEconomyBlock from "@/components/profile/ProfileEconomyBlock";
import ProfileRulesBlock from "@/components/profile/ProfileRulesBlock";
import ProfileInventoryBlock from "@/components/profile/ProfileInventoryBlock";
import ProfileAlbumBlock from "@/components/profile/ProfileAlbumBlock";
import ImpersonationPanel from "@/components/profile/ImpersonationPanel";
import AppIcon from '@/components/ui/AppIcon.vue';

export default {
  name: "MyProfilePage",
  components: {
    ProfileRaceBlock,
    PageHeader,
    ProfileAchievementBlock,
    ProfileTitle,
    ProfileEventBody,
    ProfileGameBlock,
    ProfileEconomyBlock,
    ProfileRulesBlock,
    ProfileInventoryBlock,
    ProfileAlbumBlock,
    ImpersonationPanel,
    AppIcon,
  },
  data() {
    return {
      url:  'https://prognos9ys.ru',
      profileLoader: false,
      activeFootballEvent: '',

      active: 'prognosis',
      prognosisType: 'football',
      prognosisStatus: 'active',
      profileMenu: {
        general: {title: 'Общая', icon: 'profile_info'},
        prognosis: {title: 'Прогнозы', icon: 'prognosis'},
        economy: {title: 'Богатство', icon: 'wealth'},
        inventory: {title: 'Инвентарь', emoji: '🎒'},
        collection: {title: 'Коллекция', emoji: '📔'},
        achievement: {title: 'Награды', icon: 'achievement'},
        settings: {title: 'Настройки', icon: 'settings'},
      }
    }
  },

  created() {
    this.profileLoader = true

    this.fillProfile()
    this.$store.dispatch('auth/refreshGameInfo')
  },

  methods: {
    ...mapActions({
      getProfileInfo: 'profile/getProfileData',
      logoutVue: 'auth/logoutVue',
      refreshGameInfo: 'auth/refreshGameInfo',
    }),

    setActiveFootballEvent(id) {
      this.activeFootballEvent = String(id);
    },

    setPrognosisType(type) {
      this.prognosisType = type;
      this.ensurePrognosisStatus();
      this.ensureActiveFootballEvent();
    },

    setPrognosisStatus(status) {
      this.prognosisStatus = status;
      this.ensureActiveFootballEvent();
    },

    ensurePrognosisStatus() {
      if (!this.prognosisActiveCount && this.prognosisPastCount) {
        this.prognosisStatus = 'past';
      } else if (!this.prognosisPastCount && this.prognosisActiveCount) {
        this.prognosisStatus = 'active';
      }
    },

    ensureActiveFootballEvent() {
      const keys = Object.keys(this.filteredFootballEvents || {});
      if (!keys.length) {
        this.activeFootballEvent = '';
        return;
      }
      if (!keys.includes(String(this.activeFootballEvent))) {
        this.activeFootballEvent = String(keys[0]);
      }
    },

    isEventActive(event) {
      const active = event?.info?.ACTIVE;
      if (active === 'Y') {
        return true;
      }
      if (active === 'N') {
        return false;
      }
      return false;
    },

    filterEventsByCompetitionStatus(source, status) {
      const result = {};
      Object.keys(source || {}).forEach((eventId) => {
        const event = source[eventId] || {};
        const items = event.items || {};
        if (!Object.keys(items).length) {
          return;
        }

        const isActive = this.isEventActive(event);
        if ((status === 'active' && isActive) || (status === 'past' && !isActive)) {
          result[eventId] = {
            ...event,
            items: { ...items },
            info: {
              ...(event.info || {}),
              count: Object.keys(items).length,
            },
          };
        }
      });
      return result;
    },

    filterFootballByStatus(status) {
      return this.filterEventsByCompetitionStatus(this.profileData?.football, status);
    },

    filterRaceByStatus(status) {
      return this.filterEventsByCompetitionStatus(this.profileData?.race, status);
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
      userInfo: state => state.auth.userInfo,
      token: state => state.auth.authData.token,
    }),
    gameInfo() {
      return this.userInfo?.game_info || this.profileData?.game_info || null;
    },
    filteredFootballEvents() {
      return this.filterFootballByStatus(this.prognosisStatus);
    },
    hasFootballFiltered() {
      return Object.keys(this.filteredFootballEvents || {}).length > 0;
    },
    filteredRaceEvents() {
      return this.filterRaceByStatus(this.prognosisStatus);
    },
    hasRaceFiltered() {
      return Object.keys(this.filteredRaceEvents || {}).length > 0;
    },
    prognosisActiveCount() {
      const events = this.prognosisType === 'football'
        ? this.filterFootballByStatus('active')
        : this.filterRaceByStatus('active');
      return Object.keys(events).length;
    },
    prognosisPastCount() {
      const events = this.prognosisType === 'football'
        ? this.filterFootballByStatus('past')
        : this.filterRaceByStatus('past');
      return Object.keys(events).length;
    },
  },
  watch: {
    profileData: {
      deep: true,
      handler() {
        this.ensurePrognosisStatus();
        this.ensureActiveFootballEvent();
      },
    },
    active(tab) {
      if (tab === 'inventory' || tab === 'economy' || tab === 'collection') {
        this.refreshGameInfo();
      }
    },
  },

}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.panel_block {
  background: @DarkColorBG;
  padding: 4px;
  border-radius: 5px;
  margin: 8px 0 6px;
}

.prognosis_type_tabs {
  margin-bottom: 8px;
}

.period_filter {
  display: flex;
  flex-direction: row;
  gap: 4px;
  margin-bottom: 10px;

  .period_btn {
    flex: 1;
    cursor: pointer;
    .inset_panel_wrapper();

    .period_btn_inner {
      .inset_panel_inner();
      justify-content: center;
      gap: 6px;
      font-size: 13px;
    }

    .count {
      font-size: 11px;
      color: @pearl;
      .shadow_inset;
      padding: 0 4px;
      border-radius: 3px;
    }

    &.active .period_btn_inner {
      background: @colorText2;
      color: @colorText;
    }
  }
}

.title_block {
  display: flex;
  flex-direction: row;
  gap: 4px;
  flex-wrap: nowrap;
}

.panel_main {
  margin: 8px 0 6px;

  .title_wrapper {
    margin: 0;
    flex: 0 0 auto;
  }
}

.panel_secondary {
  .title_wrapper {
    margin: 0;
    flex: 0 0 auto;
  }
}

.body_item {
  .title_wrapper {
    background: @DarkColorBG;
    padding: 4px;
    border-radius: 5px;
    margin: 0 0 8px;
  }
}

.title_wrapper {
  background: @DarkColorBG;
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  text-align: left;
  transition: background 0.2s ease;

  .title {
    display: flex;
    flex-direction: row;
    gap: 4px;
    padding: 0 2px;
    align-items: stretch;
    min-height: 28px;

    &:not(:has(.icon)):not(:has(.name)) {
      .shadow_inset;
      align-items: center;
      min-height: 28px;
    }

    .icon {
      .shadow_inset;
      display: inline-flex;
      flex-direction: row;
      justify-content: center;
      align-items: center;
      width: 28px;
      height: 28px;
      min-width: 28px;
      flex-shrink: 0;
      padding: 2px;
      box-sizing: border-box;

      .icon_img {
        width: 20px;
        height: 20px;
        object-fit: contain;
      }

      &.emoji_icon {
        font-size: 18px;
        line-height: 1;
      }
    }

    .name {
      .shadow_inset;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      font-size: 13px;
      display: flex;
      align-items: center;
      padding: 2px 6px;
      flex: 1;
      min-width: 0;

      &.short {
        font-size: 11px;
        color: @colorBlur;
        flex: 0 0 auto;
        min-width: 28px;
        justify-content: center;
      }
    }
  }

  &.compact {
    .title {
      align-items: center;
    }

    .name {
      flex: 1;
      align-self: center;
    }
  }

  &.active {
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

.empty_text {
  padding: 10px;
  color: @colorBlur;
  font-size: 12px;
}

.settings_item {
  display: flex;
  flex-direction: row;
  justify-content: flex-start;
  align-items: center;
  gap: 8px;
  color: @colorText;

  .icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    flex-shrink: 0;
    .shadow_inset;
  }

  > .title {
    box-shadow: none;
    padding: 0;
  }
}

.subsection {
  margin-top: 12px;
  margin-bottom: 4px;
}

</style>