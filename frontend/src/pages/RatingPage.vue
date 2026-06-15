<template>
  <PreLoader v-if="catLoader"></PreLoader>
  <div class="ratings_wrapper">
    <PageHeader class="header">Рейтинги</PageHeader>

    <div class="period_filter" v-if="hasNowEvents || hasOldEvents">
      <div
          class="period_btn"
          v-if="hasNowEvents"
          :class="{ active: periodFilter === 'now' }"
          @click="setPeriod('now')"
      >
        <div class="period_btn_inner">
          Активные
          <span class="count" v-if="nowCount">{{ nowCount }}</span>
        </div>
      </div>
      <div
          class="period_btn"
          v-if="hasOldEvents"
          :class="{ active: periodFilter === 'old' }"
          @click="setPeriod('old')"
      >
        <div class="period_btn_inner">
          Прошедшие
          <span class="count" v-if="oldCount">{{ oldCount }}</span>
        </div>
      </div>
    </div>

    <div class="event_block" :class="{'small_category' : category}" v-if="filteredEvents && Object.keys(filteredEvents).length">
      <div class="el_event" v-for="(el, index) in filteredEvents" :key="index">
        <div class="img_box" @click="selectRating(el.ID, el.code)">
          <img :src="url + el.img" alt="">
        </div>
      </div>
    </div>

    <div class="empty_period" v-else-if="!catLoader">
      Событий в этой категории пока нет
    </div>

    <div class="rating_block" v-if="eventId && filteredEvents[eventId]">
      <div class="rating_title_wrapper">
        <div class="rating_title">
          {{ filteredEvents[eventId].NAME }}
        </div>
      </div>

      <RatingSetBar
          v-if="category === 'football'"
          :event-id="eventId"
          :token="token"
          :my-sets="mySets"
          :public-sets="publicSets"
          :active-set-id="activeSetId"
          :active-set="activeSet"
          @select="onSelectSet"
          @create="openCreateSet"
          @edit="openEditSet"
      />

      <FootballRatingBlock
          v-if="category === 'football'"
          :event-id="eventId"
          :set-id="activeSetId"
          @loaded="onRatingLoaded"
      />
      <RaceRatingBlock v-if="category === 'race'" :eventId="eventId"></RaceRatingBlock>
    </div>

    <RatingSetModal
        :visible="setModalVisible"
        :edit-set="editingSet"
        :event-id="eventId"
        :sport="category || 'football'"
        :available-users="ratingUsers"
        :user-token="token"
        @close="closeSetModal"
        @saved="onSetSaved"
        @deleted="onSetDeleted"
    />
  </div>
</template>

<script>
import PageHeader from "@/components/main/PageHeader";
import {mapActions, mapState} from "vuex";
import PreLoader from "@/components/main/PreLoader";
import FootballRatingBlock from "@/components/football/FootballRatingBlock";
import RaceRatingBlock from "@/components/race/RaceRatingBlock";
import RatingSetBar from "@/components/rating/RatingSetBar";
import RatingSetModal from "@/components/rating/RatingSetModal";
import { apiActions } from "@/api/bitrixClient";

export default {
  name: "RatingPage",
  components: {
    PageHeader,
    PreLoader,
    FootballRatingBlock,
    RaceRatingBlock,
    RatingSetBar,
    RatingSetModal,
  },
  data() {
    return {
      url: `${window.location.origin}/`,
      category: '',
      eventId: '',
      catLoader: false,
      periodFilter: 'now',
      activeSetId: null,
      activeSet: null,
      setModalVisible: false,
      editingSet: null,
      ratingUsers: [],
    }
  },

  created() {
    this.fillCatalogElem()
  },

  methods: {
    ...mapActions({
      getEventsInfo: 'catalog/getEventsInfo',
      loadRatingSets: 'ratingSet/loadSets',
    }),

    async fillCatalogElem() {
      this.catLoader = true
      this.queryData['type'] = 'all'

      await this.getEventsInfo()
      this.initPeriodFilter()
      this.catLoader = false
    },

    initPeriodFilter() {
      if (this.hasNowEvents) {
        this.periodFilter = 'now'
      } else if (this.hasOldEvents) {
        this.periodFilter = 'old'
      }
      this.resetSelectionIfNeeded()
    },

    setPeriod(period) {
      if (this.periodFilter === period) {
        return
      }
      this.periodFilter = period
      this.resetSelectionIfNeeded()
    },

    resetSelectionIfNeeded() {
      if (!this.eventId || !this.filteredEvents[this.eventId]) {
        this.eventId = ''
        this.category = ''
        this.clearActiveSet()
      }
    },

    clearActiveSet() {
      this.activeSetId = null
      this.activeSet = null
    },

    async selectRating(id, code) {
      this.eventId = id
      this.category = code
      this.clearActiveSet()
      if (code === 'football') {
        await this.refreshSets()
      }
    },

    async refreshSets() {
      if (!this.eventId || this.category !== 'football') {
        return
      }
      await this.loadRatingSets({
        sport: 'football',
        eventId: Number(this.eventId),
        userToken: this.token || '',
      })
    },

    onSelectSet(set) {
      if (!set) {
        this.clearActiveSet()
        return
      }
      this.activeSetId = set.id
      this.activeSet = set
    },

    async openCreateSet() {
      await this.ensureRatingUsers()
      this.editingSet = null
      this.setModalVisible = true
    },

    async openEditSet(set) {
      if (set?.isOwner === false) {
        return
      }
      await this.ensureRatingUsers()
      try {
        const response = await apiActions.ratingSet.get(set.id, this.token)
        this.editingSet = response.set
        this.setModalVisible = true
      } catch (e) {
        console.log('edit set error', e)
      }
    },

    closeSetModal() {
      this.setModalVisible = false
      this.editingSet = null
    },

    async onSetSaved(set) {
      if (set?.id) {
        this.activeSetId = set.id
        this.activeSet = set
      }
      await this.refreshSets()
    },

    async onSetDeleted() {
      this.clearActiveSet()
      await this.refreshSets()
    },

    onRatingLoaded() {
      if (!this.activeSetId) {
        this.ratingUsers = this.extractUsersFromRating(this.footballRating)
      }
    },

    async ensureRatingUsers() {
      if (this.ratingUsers.length || !this.eventId) {
        return
      }

      try {
        const response = await apiActions.rating.getFootball(this.eventId, null, this.token || '')
        if (response.status === 'ok') {
          this.ratingUsers = this.extractUsersFromRating(response.ratings)
        }
      } catch (e) {
        console.log('load rating users error', e)
      }
    },

    extractUsersFromRating(ratings) {
      const all = ratings?.all
      if (!all) {
        return []
      }

      const tourKeys = Object.keys(all).map(Number).filter((n) => n > 0)
      if (!tourKeys.length) {
        return []
      }

      const lastTour = Math.max(...tourKeys)
      const rows = all[lastTour] || []
      const map = new Map()

      rows.forEach((row) => {
        const user = row?.user
        if (user?.id) {
          map.set(user.id, user)
        }
      })

      return Array.from(map.values())
    },
  },

  computed: {
    ...mapState({
      ratingEvents: state => state.catalog.ratingEvents,
      queryData: state => state.catalog.queryData,
      token: state => state.auth.authData.token,
      mySets: state => state.ratingSet.mySets,
      publicSets: state => state.ratingSet.publicSets,
      footballRating: state => state.rating.footballRating,
    }),
    nowEvents() {
      return this.ratingEvents?.now || {}
    },
    oldEvents() {
      return this.ratingEvents?.old || {}
    },
    hasNowEvents() {
      return Object.keys(this.nowEvents).length > 0
    },
    hasOldEvents() {
      return Object.keys(this.oldEvents).length > 0
    },
    nowCount() {
      return Object.keys(this.nowEvents).length
    },
    oldCount() {
      return Object.keys(this.oldEvents).length
    },
    filteredEvents() {
      return this.periodFilter === 'old' ? this.oldEvents : this.nowEvents
    },
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.ratings_wrapper{
  .period_filter{
    display: flex;
    flex-direction: row;
    gap: 4px;
    margin-bottom: 10px;

    .period_btn{
      flex: 1;
      cursor: pointer;
      .inset_panel_wrapper();

      .period_btn_inner{
        .inset_panel_inner();
        justify-content: center;
        gap: 6px;
        font-size: 13px;
      }

      .count{
        font-size: 11px;
        color: @pearl;
        .shadow_inset;
        padding: 0 4px;
        border-radius: 3px;
      }

      &.active .period_btn_inner{
        background: @colorText2;
        color: @colorText;
      }
    }
  }

  .empty_period{
    margin-top: 8px;
    padding: 10px;
    border-radius: 5px;
    background: @DarkColorBG;
    color: @colorText;
    font-size: 13px;
    .shadow_inset;
  }

  .event_block{
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 4px;
    .el_event{
      background: @DarkColorBG;
      width: 19%;
      padding: 4px;
      border-radius: 5px;
      .img_box{
        cursor: pointer;
        background: @colorBlur;
        width: 100%;
        img{
          width: 100%;
        }
      }
    }

    &.small_category{
      .el_event{
        width: 40px;
      }
    }
  }

  .rating_block{
    margin-top: 36px;
  }
  .rating_title_wrapper{
    .inset_panel_wrapper();
    margin-bottom: 4px;

    .rating_title{
      .inset_panel_inner();
      justify-content: center;
      font-size: 14px;
    }
  }
}
</style>
