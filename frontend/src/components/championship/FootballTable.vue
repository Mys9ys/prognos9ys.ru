<template>
  <PreLoader v-if="elLoader"></PreLoader>
  <PageHeader class="header">Таблица</PageHeader>
  <TreasuryShopBlock :event-id="eventId" />
  <div class="title_wrapper" v-if="tableData.info">
    <div class="logo" >
      <img :src="url + tableData.info.img" alt="">
    </div>
    <div class="title">
      {{tableData.info.NAME}}
    </div>
  </div>

  <div class="stage_tabs" v-if="hasPlayoff">
    <button
      type="button"
      class="stage_tab"
      :class="{ active: activeStage === 'groups' }"
      @click="activeStage = 'groups'"
    >
      Групповой этап
    </button>
    <button
      type="button"
      class="stage_tab"
      :class="{ active: activeStage === 'playoff' }"
      @click="activeStage = 'playoff'"
    >
      Плей-офф
    </button>
  </div>

  <div v-if="tableData.groups && showGroups">
    <div class="table_wrapper" v-for="(teams, char) in tableData.groups" :key="char">
      <div class="title_wrapper group_wrapper group_header" v-if="!isDefaultGroup(char)">
        <span class="title">Группа: {{ char }}</span>
        <div
            class="group_matches_toggle"
            v-if="groupMatchesFor(char).length"
            @click="toggleGroupMatches(char)"
        >
          <span class="toggle_icon">{{ isGroupExpanded(char) ? '▼' : '▶' }}</span>
          <span>Матчи ({{ groupMatchesFor(char).length }})</span>
        </div>
      </div>

      <table class="table table-hover table_temp">
        <tr class="table_row">
          <th ><span class="t_col">#</span></th>
          <th ><span class="t_col">Команда</span></th>
          <th ><span class="t_col">И</span></th>
          <th ><span class="t_col">В</span></th>
          <th ><span class="t_col">Н</span></th>
          <th ><span class="t_col">П</span></th>
          <th ><span class="t_col">Мячи</span></th>
          <th ><span class="t_col">Очки</span></th>
        </tr>

        <tr v-for="(item, index) in teams" :key="index">
          <td><span class="t_col">{{index+1}}</span></td>
          <td class="team_col">
            <div class="flag">
              <img :src="url + item.info.img" alt="">
            </div>
            <div class="title">{{item.info.NAME}}</div>
          </td>
          <td><span class="t_col">{{item.matches ?? 0}}</span></td>
          <td><span class="t_col">{{item.win ?? 0}}</span></td>
          <td><span class="t_col">{{item.draw ?? 0}}</span></td>
          <td><span class="t_col">{{item.lose ?? 0}}</span></td>
          <td><span class="t_col">{{item.plus ?? 0}}-{{item.minus ?? 0}}</span></td>
          <td><span class="t_col">{{item.score ?? 0}}</span></td>
        </tr>
      </table>

      <div class="group_matches_list" v-if="!isDefaultGroup(char) && isGroupExpanded(char)">
        <EventMatch
            v-for="match in groupMatchesFor(char)"
            :key="match.number"
            :match="match"
            class="group_match_item"
        />
      </div>
    </div>
  </div>

  <PlayoffBracketBlock
    v-if="hasPlayoff && showPlayoff"
    :bracket="playoffBracket"
    :rounds="playoffRounds"
    :event-id="eventId"
    hide-title
  />

  <div class="table_empty" v-if="!elLoader && loadAttempted && !hasTableData">
    <span v-if="loadError">{{ loadError }}</span>
    <span v-else>Турнирная таблица пока пуста</span>
  </div>

  <div class="table_wrapper third_places_wrapper" v-if="thirdPlaces.length && showGroups">
    <div class="title_wrapper group_wrapper">
      <span class="title">Третьи места (сравнение групп)</span>
    </div>

    <table class="table table-hover table_temp">
      <tr class="table_row">
        <th><span class="t_col">#</span></th>
        <th><span class="t_col">Гр.</span></th>
        <th><span class="t_col">Команда</span></th>
        <th><span class="t_col">И</span></th>
        <th><span class="t_col">В</span></th>
        <th><span class="t_col">Н</span></th>
        <th><span class="t_col">П</span></th>
        <th><span class="t_col">Мячи</span></th>
        <th><span class="t_col">Очки</span></th>
      </tr>

      <tr v-for="(item, index) in thirdPlaces" :key="'third-' + index">
        <td><span class="t_col">{{ index + 1 }}</span></td>
        <td><span class="t_col">{{ item.sourceGroup }}</span></td>
        <td class="team_col">
          <div class="flag">
            <img :src="url + item.info.img" alt="">
          </div>
          <div class="title">{{ item.info.NAME }}</div>
        </td>
        <td><span class="t_col">{{ item.matches ?? 0 }}</span></td>
        <td><span class="t_col">{{ item.win ?? 0 }}</span></td>
        <td><span class="t_col">{{ item.draw ?? 0 }}</span></td>
        <td><span class="t_col">{{ item.lose ?? 0 }}</span></td>
        <td><span class="t_col">{{ item.plus ?? 0 }}-{{ item.minus ?? 0 }}</span></td>
        <td><span class="t_col">{{ item.score ?? 0 }}</span></td>
      </tr>
    </table>
  </div>
</template>

<script>
import PageHeader from "@/components/main/PageHeader";
import {mapActions, mapState} from "vuex";
import PreLoader from "@/components/main/PreLoader";
import EventMatch from "@/components/football/EventMatch";
import TreasuryShopBlock from "@/components/game/TreasuryShopBlock";
import PlayoffBracketBlock from "@/components/championship/PlayoffBracketBlock";

export default {
  name: "FootballTable",
  components: {
    PageHeader,
    PreLoader,
    EventMatch,
    TreasuryShopBlock,
    PlayoffBracketBlock,
  },
  data() {
    return {
      url: `${window.location.origin}/`,
      elLoader: false,
      loadAttempted: false,
      expandedGroups: {},
      activeStage: 'groups',
    }
  },
  watch: {
    eventId() {
      this.activeStage = 'groups';
    },
  },
  created() {
    this.fillTable()
  },

  methods: {
    ...mapActions({
      getTableInfo: 'championship/getFootballTable',
    }),

    async fillTable() {
      this.elLoader = true
      this.loadAttempted = false
      try {
        await this.$store.commit('championship/setQueryData', {
          events: this.$route.params.event,
          token: this.token || '',
        })
        await this.getTableInfo()
      } finally {
        this.loadAttempted = true
        this.elLoader = false
      }
    },

    groupMatchesFor(group) {
      const matches = this.tableData?.groupMatches?.[group];
      return Array.isArray(matches) ? matches : [];
    },

    isGroupExpanded(group) {
      return Boolean(this.expandedGroups[group]);
    },

    toggleGroupMatches(group) {
      this.expandedGroups = {
        ...this.expandedGroups,
        [group]: !this.expandedGroups[group],
      };
    },

    isDefaultGroup(group) {
      return group === 0 || group === '0';
    },
  },
  computed: {
    ...mapState({
      tableData: state => state.championship.footballData,
      token: state => state.auth.authData.token,
      loadError: state => state.championship.errors,
    }),
    hasTableData() {
      const groups = this.tableData?.groups;
      if (groups && typeof groups === 'object') {
        if (Object.keys(groups).some((key) => !this.isDefaultGroup(key))) {
          return true;
        }
        const defaultGroup = groups[0] || groups['0'];
        if (Array.isArray(defaultGroup) && defaultGroup.length > 0) {
          return true;
        }
      }
      return this.thirdPlaces.length > 0
        || this.playoffRounds.length > 0
        || Boolean(this.playoffBracket)
        || Boolean(this.tableData?.info?.NAME);
    },
    playoffRounds() {
      const rounds = this.tableData?.playoffRounds;
      return Array.isArray(rounds) ? rounds : [];
    },
    playoffBracket() {
      const bracket = this.tableData?.playoffBracket;
      return bracket?.columns?.length ? bracket : null;
    },
    hasPlayoff() {
      return this.playoffRounds.length > 0 || Boolean(this.playoffBracket);
    },
    showGroups() {
      return !this.hasPlayoff || this.activeStage === 'groups';
    },
    showPlayoff() {
      return this.hasPlayoff && this.activeStage === 'playoff';
    },
    thirdPlaces() {
      const places = this.tableData?.thirdPlaces;
      return Array.isArray(places) ? places : Object.values(places || {});
    },
    eventId() {
      return this.$route.params.event;
    },
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.title_wrapper{
  display: flex;
  flex-direction: row;
  gap: 4px;
  justify-content: flex-start;
  .logo{
    .shadow_inset;
    background: @colorBlur;
    img{
      width: 24px;
      height: 24px;
    }
  }
}
.group_wrapper{
  margin: 0;
  gap: 4px;
}

.group_header{
  width: 100%;
  justify-content: space-between;
  align-items: stretch;
  margin-bottom: 4px;

  .title{
    flex: 1;
    display: flex;
    align-items: center;
    text-align: left;
    .shadow_inset;
  }
}
.table_wrapper{
  background: @DarkColorBG;
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  margin-top: 10px;
}
.table_row{
  gap:2px;
  background: @DarkColorBG;
  padding: 2px;
  border-radius: 5px;
  color: @colorText;
}
.t_col{

  .shadow_inset;
  color: @colorText;
  .flex_center;
}

.third_places_wrapper{
  margin-top: 16px;
}

.table_empty{
  margin-top: 16px;
  padding: 12px;
  border-radius: 5px;
  background: @DarkColorBG;
  color: @colorText;
  font-size: 14px;
  .shadow_inset;
}

.group_matches_toggle{
  flex-shrink: 0;
  align-self: stretch;
  display: inline-flex;
  flex-direction: row;
  align-items: center;
  gap: 4px;
  padding: 0 6px;
  border-radius: 3px;
  background: @colorText2;
  color: @colorText;
  cursor: pointer;
  font-size: 11px;
  line-height: 1;
  border: 1px solid transparent;
  box-sizing: border-box;
  .shadow_template;

  &:hover{
    background: @colorText;
    color: @colorText2;
    border-color: @colorText2;
  }

  .toggle_icon{
    width: 10px;
    font-size: 9px;
    text-align: center;
  }
}

.group_matches_list{
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.stage_tabs {
  display: flex;
  flex-direction: row;
  gap: 4px;
  margin-top: 10px;
  background: @DarkColorBG;
  padding: 4px;
  border-radius: 5px;
}

.stage_tab {
  flex: 1;
  border: 1px solid transparent;
  background: fade(@colorBlur, 35%);
  color: @colorText;
  border-radius: 3px;
  padding: 6px 8px;
  font-size: 12px;
  line-height: 1.2;
  cursor: pointer;
  .shadow_inset;

  &:hover {
    filter: brightness(1.08);
  }

  &.active {
    background: @colorText2;
    color: @colorText;
    border-color: fade(@colorText, 25%);
    .shadow_template;
  }
}

.team_col{
  min-width: 145px;
  color: @colorText;
  display: flex;
  flex-direction: row;
  justify-content: flex-start;
  gap: 2px;
  .flag{
    padding: 2px;
    .shadow_inset;
    img{
      width: 18px;
      height: 18px;
    }
  }
  .title{
    width: 100%;
    .shadow_inset;
    text-align: left;
  }
}
</style>