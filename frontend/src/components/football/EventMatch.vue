<template>
  <div class="match_box" v-if="match">
    <div class="left_block">
      <div class="number"># {{ match.number }}</div>
      <div class="time">{{ match.time }}</div>
    </div>

    <div class="team_block">
      <div class="team" v-for="(team, index) in match.teams"
           :key="index">
        <div class="flag">
          <img :src="urlImg + team.flag" alt="">
        </div>
        <div class="name">{{ team.name }}</div>
        <div class="score" :class="{'score_blur' : match.active === 'Y'}">{{ team.goals ?? 0 }}</div>
      </div>
    </div>

    <div class="right_block">
      <div class="send_info_block" v-if="!match.send_info.send_time">
        <div class="send_info">не заполнено</div>
      </div>
      <div class="send_info_block" v-else>
        <div class="send_info send_fill" :class="{'send_info_min' : match.send_info.score_result}">заполнено {{ match.send_info.send_time }}</div>
        <div class="score_result" v-if="match.send_info.score_result">{{ match.send_info.score_result }}</div>
      </div>

      <div class="btn_box">
        <div class="more_btn" @click="moreInfo = !moreInfo"><span
            :class="{'close' : !moreInfo, 'open' : moreInfo}"> > </span></div>
        <div class="match_btn" v-if="!match.send_info.send_time && match.active === 'Y'" @click="$router.push(link)">
          Заполнить
        </div>
        <div class="match_btn btn_change" v-if="match.send_info.send_time && match.active === 'Y'"
             @click="$router.push(link)">Изменить
        </div>
        <div class="match_btn btn_last" v-if="match.active === 'N'" @click="$router.push(link)">Посмотреть</div>
      </div>
    </div>
  </div>
  <div class="more_info" v-if="moreInfo">
    <div class="title">Коэффициенты на матч</div>
    <div class="box">
      <div class="cell" v-for="(ratio, index) in match.ratio" :key="index">
        <div class="title_cell">{{ratio.name}}</div>
        <div class="count">{{ratio.count}}</div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "EventMatch",
  props: {
    match: {
      type: Object
    }
  },
  data() {
    return {
      moreInfo: false,
      link: '/football/' + this.match.event + '/' + this.match.number,
      urlImg: 'https://prognos9ys.ru/'
    }
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.match_box {
  display: flex;
  flex-direction: row;
  gap: 4px;
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;

  .left_block {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 13%;
    max-width: 51px;

    .number {
      .shadow_inset;
      .flex_center;
      font-size: 12px;
      height: 24px;
    }

    .time {
      .shadow_inset;
      .flex_center;
      font-size: 12px;
      height: 24px;
    }
  }

  .team_block {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    flex-wrap: nowrap;
    gap: 4px;
    width: 61%;
    max-width: 238px;

    .team {
      display: flex;
      flex-direction: row;
      gap: 4px;

      .flag {
        width: 13%;
        max-width: 24px;
        .shadow_inset;
        padding: 3px;
        display: flex;
        flex-direction: row;
        justify-content: center;
        align-items: center;
        img{
          width: 98%;
          max-width: 20px;
          border-radius: 3px;
        }
      }

      .name {
        text-align: left;
        .shadow_inset;
        width: 80%;
        max-width: 194px;
        white-space: nowrap;
        overflow: hidden;
        padding: 0px 2px;
        text-overflow: ellipsis;
      }

      .score {
        .shadow_inset;
        width: 13%;
        max-width: 24px;
        &.score_blur{
          color: @colorBlur;
        }
      }
    }
  }

  .right_block {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 25%;
    max-width: 99px;

    .send_info_block {
      display: flex;
      flex-direction: row;
      gap: 4px;

      .send_info {
        width: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        height: 24px;
        line-height: 10px;
        .shadow_inset;
        font-size: 10px;
        color: @boks;
        &.send_fill{
          color: @NoWrite;
        }
      }

      .send_info_min {
        width: 76%;
        max-width: 75px;
      }

      .score_result {
        display: flex;
        flex-direction: column;
        justify-content: center;

        .shadow_inset;
        width: 24px;
        font-size: 10px;
        color: @maxGreen;
      }
    }

    .btn_box {
      display: flex;
      flex-direction: row;
      justify-content: center;
      height: 24px;
      gap: 4px;

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
        .shadow_template;

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

      .match_btn {
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: @colorText2;
        color: @colorText;
        cursor: pointer;
        .shadow_template;
        padding: 2px 2px;
        font-size: 10px;
        border-radius: 3px;
        text-align: center;
        border: 1px solid transparent;
        text-decoration: none;
        width: 76%;
        max-width: 75px;

        &:hover {
          background: @colorText;
          color: @colorText2;
          border: 1px solid @colorText2;
        }
      }

      .btn_last {
        background: @maxdarkgrey;
        color: @darkbg;

        &:hover {
          color: @darkbg;
          border: 1px solid @darkbg;
        }
      }

      .btn_change {
        background: @NoWrite;
        &:hover {
          color: @NoWrite;
          border: 1px solid @NoWrite;
        }
      }
    }
  }
}

.more_info {
  width: 100%;
  background: @DarkColorBG;
  color: @colorBlur;
  display: flex;
  flex-direction: column;
  gap:4px;
  padding: 4px;
  border-radius: 5px;

  .title{
    width: 100%;
    .shadow_inset;

    justify-content: left;
  }

  .box{
    width: 100%;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    gap: 4px;

    .cell{
      width: 24%;
      .shadow_inset;
      display: flex;
      flex-direction: row;
      color: @pearl;
      font-weight: 700;

      .title_cell{
        text-align: right;
        width: 35%;
        border-right: 3px solid @colorBlur;
        padding-right: 6px;
      }

      .count{
        width: 65%;
        text-align: left;
        padding-left: 6px;
      }
    }
  }
}
</style>