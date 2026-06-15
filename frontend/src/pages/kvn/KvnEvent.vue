<template>
  <PageHeader class="header">Соревнования</PageHeader>
  <div class="kvn_wrapper">
    <div class="games">
      <div class="game" v-for="(game, index) in getGames" :key="index">
        <GameTitle :title="game.title"></GameTitle>
        <GameTeams :info="game"></GameTeams>
        <GameBtns :number="$route.params.event+'/'+game.title.number"></GameBtns>
      </div>
    </div>
  </div>
</template>

<script>
import {mapState, mapMutations, mapActions, mapGetters} from 'vuex'
import GameTitle from "@/components/kvn/GameTitle";
import GameTeams from "@/components/kvn/GameTeams";
import GameBtns from "@/components/kvn/GameBtns";
import PageHeader from "@/components/main/PageHeader";

export default {
  name: "KvnEvent",
  components: {
    GameTitle,
    GameTeams,
    GameBtns,
    PageHeader
  },


  methods: {
    ...mapMutations({
      setEventData: 'kvn/setEventData',
    }),
    ...mapActions({
      fetchAnswer: 'kvn/fetchAnswer'
    }),
  },
  mounted() {
    this.fetchAnswer()
    console.log('window.location.href', window.location.href.includes('localhost'))
  },
  computed: {
    ...mapState({
      kvnEventData: state => state.kvnEventData,
    }),
    ...mapGetters({
      getGames: "kvn/getGames",
      getEventInfo: "kvn/getEventInfo"
    })
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

@blockBG: #253133;
.kvn_wrapper {
  position: relative;
  width: 400px;
  max-width: 100%;
  background: @YesWrite;

  //background: rgb(57,218,138);
  .kvn_header {
    //background: rgb(97, 154, 56);
    padding: 5px 8px;
    display: flex;
    flex-direction: row;
    gap: 10px;
    position: sticky;
    top: 0;
    z-index: 10;

    span {
      background: rgba(18, 18, 18, .25);
      border-radius: 2px;
      padding: 3px;
      color: #fff;
      font-size: 11px;

      //box-shadow: 0 3px 7px 0 rgb(217 221 227 / 40%);
    }
  }
}

.game {
  width: 100%;
  background: @blockBG;
  //background:rgb(45,45,45);
  padding: 5px;
  border-radius: 5px;
  margin-bottom: 5px;
}
</style>