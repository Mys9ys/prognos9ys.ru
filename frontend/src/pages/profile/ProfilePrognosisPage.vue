<template>
  <div class="prognosis_page">
    <PageHeader>Ваши прогнозы</PageHeader>

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

    <div v-if="profileLoader" class="hint">Загрузка прогнозов…</div>

    <div class="football_block" v-else-if="prognosisType === 'football' && hasFootballFiltered">
      <div class="football_title_block">
        <ProfileTitle
          v-for="(arr, eventId) in filteredFootballEvents"
          :key="eventId"
          @click="setActiveFootballEvent(eventId)"
          :info="arr.info"
          :active="String(eventId) === String(activeFootballEvent)"
          :count="Object.keys(arr.items).length"
          :class="{'active': String(eventId) === String(activeFootballEvent)}"
        />
      </div>
      <div
        class="football_body_block"
        v-for="(arr, eventId) in filteredFootballEvents"
        :key="`${prognosisStatus}-${eventId}`"
      >
        <ProfileEventBody
          v-if="String(eventId) === String(activeFootballEvent)"
          :key="`${prognosisStatus}-${eventId}-body`"
          :matches="arr.items"
          :title="arr.info.NAME"
          :event-img="arr.info.img"
        />
      </div>
    </div>

    <div class="race_block" v-else-if="prognosisType === 'race' && hasRaceFiltered">
      <ProfileRaceBlock
        :events="filteredRaceEvents"
        :racers="profileData.racers"
      />
    </div>

    <div class="empty_text" v-else-if="prognosisType === 'football' && !profileLoader">
      Нет {{ prognosisStatus === 'active' ? 'активных' : 'прошедших' }} футбольных прогнозов
    </div>
    <div class="empty_text" v-else-if="prognosisType === 'race' && !profileLoader">
      Нет {{ prognosisStatus === 'active' ? 'активных' : 'прошедших' }} прогнозов Ф1
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import PageHeader from '@/components/main/PageHeader.vue';
import ProfileEventBody from '@/components/football/ProfileEventBody.vue';
import ProfileRaceBlock from '@/components/profile/ProfileRaceBlock.vue';
import ProfileTitle from '@/components/profile/ProfileTitle.vue';
import AppIcon from '@/components/ui/AppIcon.vue';

export default {
  name: 'ProfilePrognosisPage',
  components: {
    PageHeader,
    ProfileEventBody,
    ProfileRaceBlock,
    ProfileTitle,
    AppIcon,
  },
  data() {
    return {
      profileLoader: true,
      activeFootballEvent: '',
      prognosisType: 'football',
      prognosisStatus: 'active',
    };
  },
  created() {
    this.fillProfile();
  },
  methods: {
    ...mapActions({
      getProfileInfo: 'profile/getProfileData',
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
      return event?.info?.ACTIVE === 'Y';
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

    async fillProfile() {
      if (this.profileData?.load) {
        this.profileLoader = false;
        this.ensurePrognosisStatus();
        this.ensureActiveFootballEvent();
        return;
      }

      this.profileLoader = true;
      this.profileRequest.userToken = this.token;
      await this.getProfileInfo();
      this.profileLoader = false;
      this.ensurePrognosisStatus();
      this.ensureActiveFootballEvent();
    },
  },
  computed: {
    ...mapState({
      profileData: (state) => state.profile.profileData,
      profileRequest: (state) => state.profile.profileRequest,
      token: (state) => state.auth.authData.token,
    }),
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
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.prognosis_page {
  padding-bottom: 8px;
}

.prognosis_type_tabs {
  display: flex;
  flex-direction: row;
  gap: 4px;
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
}

.title_wrapper {
  background: @DarkColorBG;
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  cursor: pointer;

  .title .icon {
    .shadow_inset;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
  }

  &.active {
    background: @YesWrite;
  }
}

.hint,
.empty_text {
  color: @colorBlur;
  padding: 12px 4px;
  text-align: left;
}
</style>
