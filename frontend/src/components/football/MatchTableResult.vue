<template>
  <div class="match_wrapper">
    <div class="match_header">
      <div class="number cell"># {{info.matches.number}}</div>
      <div class="cell teams"> <span class="tname">{{info.matches.home.NAME}} </span> <span>-</span> <span class="tname">{{info.matches.guest.NAME}}</span></div>
      <div class="cell number score">{{info.result.all}}</div>
      <div class="more_btn" @click="visible = !visible">
        <span :class="{'close' : !visible, 'open' : visible}"> > </span>
      </div>
    </div>

    <div class="match_table" v-if="visible">
      <MatchTable :info="info"></MatchTable>
    </div>
  </div>
</template>

<script>
import MatchTable from "@/components/football/MatchTable";

export default {
  name: "MatchTableResult",
  components: {
    MatchTable
  },

  props: {
    info:{
      type: Object
    }
  },
  data() {
    return {
      visible: false
    }
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
  .match_wrapper{
    .match_header{
      background: @DarkColorBG;
      padding: 4px;
      border-radius: 5px 5px 0 0;
      color: @colorText;
      margin-top: 5px;

      display: flex;
      flex-direction: row;
      justify-content: space-between;
      gap: 4px;
      .cell{
        .shadow_inset;
      }
      .number{
        min-width: 40px;
      }

      .teams{
        display: flex;
        flex-direction: row;
        justify-content: space-between;
        gap: 3px;
        min-width: 220px;
        text-align: left;
        .tname{
          max-width: 107px;
          white-space: nowrap;
          overflow: hidden;
          padding: 0px 2px;
          text-overflow: ellipsis;
        }
      }
      .score{
        color: @YesWrite;
      }
    }
  }

.more_btn {
  display: flex;
  flex-direction: column;
  justify-content: center;
  max-width: 24px;
  height: 24px;
  width: 24%;
  background: @valleyball;
  padding: 2px 2px;
  border-radius: 3px;
  cursor: pointer;
  box-shadow: 0 2px 3px rgba(0, 0, 0, .4), 0 -1px 0 rgba(0, 0, 0, .2);

  span{
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
  }

  .close {
    transform: rotate(90deg);
  }

  .open {
    transform: rotate(-90deg);
  }

  &:hover {
    background: @colorText;
    color: @valleyball;
    border: 1px solid @valleyball;
  }
}
</style>