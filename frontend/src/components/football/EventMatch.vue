<template>
  <div class="match_card" v-if="match" :class="{ 'has_xp': showRewardTabs }">
    <div class="reward_tabs" v-if="showRewardTabs">
      <div class="match_xp_tab" v-if="showTreasureReward">
        <div class="chest_claimed">
          <span v-if="treasureCount > 1">x{{ treasureCount }} </span>
          <AppIcon name="chest_wc2026" :size="22" />
        </div>
      </div>
      <div class="match_xp_tab" v-if="showMoneyReward">
        <div class="money_claimed">
          Выигрыш +{{ moneyPayout }} <AppIcon name="prognobak" :size="16" />
        </div>
      </div>
      <div class="match_xp_tab" v-if="showXpReward">
      <button
          class="xp_btn"
          v-if="canClaimXp"
          :disabled="claiming"
          @click.stop="claimExperience"
      >
        {{ claiming ? '...' : `Получить опыт +${xpPoints}` }}
      </button>
      <div class="xp_claimed" v-else-if="xpStatus === 'claimed'">
        Опыт +{{ xpPoints }}
      </div>
      <div class="xp_level_up" v-if="levelUpMessage">{{ levelUpMessage }}</div>
      <div class="xp_error" v-if="claimError">{{ claimError }}</div>
      </div>
    </div>

    <div class="match_box">
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
          <div class="title_cell">{{ ratio.name }}</div>
          <div class="count" v-if="match.bet_ratio?.length">
            <span class="count_main">{{ ratio.count }}</span>
            <span class="count_sep"> / </span>
            <span class="count_bet">{{ match.bet_ratio[index]?.count ?? '-' }}</span>
          </div>
          <div class="count" v-else>
            {{ ratio.count }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import AppIcon from '@/components/ui/AppIcon.vue';

export default {
  name: "EventMatch",
  components: { AppIcon },
  props: {
    match: {
      type: Object
    }
  },
  data() {
    return {
      moreInfo: false,
      link: '/football/' + this.match.event + '/' + this.match.number,
      urlImg: 'https://prognos9ys.ru/',
      claiming: false,
      claimError: '',
      levelUpMessage: '',
      localXpStatus: null,
    }
  },
  computed: {
    xpReward() {
      return this.match?.xp_reward || null;
    },
    showXpReward() {
      if (this.match?.active !== 'N' || !this.xpReward) {
        return false;
      }

      const participated = Boolean(this.match?.send_info?.send_time)
        || Number(this.xpReward?.points) > 0
        || this.xpStatus === 'claimed'
        || this.xpStatus === 'pending';

      if (!participated) {
        return false;
      }

      return this.canClaimXp || this.xpStatus === 'claimed' || this.xpPoints > 0;
    },
    betReward() {
      return this.match?.bet_reward || null;
    },
    treasure() {
      return this.match?.treasure || null;
    },
    treasureCount() {
      return Number(this.treasure?.count ?? 0);
    },
    showTreasureReward() {
      return this.match?.active === 'N' && this.treasureCount > 0;
    },
    moneyPayout() {
      return Number(this.betReward?.payout ?? 0).toFixed(1);
    },
    showMoneyReward() {
      return Number(this.betReward?.payout ?? 0) > 0;
    },
    showRewardTabs() {
      return this.showXpReward || this.showMoneyReward || this.showTreasureReward;
    },
    xpPoints() {
      return this.xpReward?.points ?? 0;
    },
    xpStatus() {
      if (this.localXpStatus) {
        return this.localXpStatus;
      }

      return this.xpReward?.status;
    },
    canClaimXp() {
      return this.xpStatus === 'pending' || (this.xpReward?.can_claim && this.xpStatus !== 'claimed');
    },
  },
  methods: {
    ...mapActions({
      claimXp: 'game/claimXp',
    }),
    async claimExperience() {
      if (!this.match?.id || this.claiming) {
        return;
      }

      this.claiming = true;
      this.claimError = '';

      try {
        const result = await this.claimXp(this.match.id);
        this.localXpStatus = 'claimed';
        if (result?.level_rewards?.length) {
          const parts = result.level_rewards.map((reward) => {
            const bits = [`ур. ${reward.level}`];
            if (reward.prognobaks > 0) {
              bits.push(`+${reward.prognobaks} 💵`);
            }
            if (reward.rublius > 0) {
              bits.push(`+${reward.rublius} 💎`);
            }
            if (reward.chests > 0) {
              bits.push(`+${reward.chests} 🎁`);
            }
            return bits.join(' ');
          });
          this.claimError = '';
          this.levelUpMessage = `Новый уровень! ${parts.join('; ')}`;
        }
        await this.$store.dispatch('auth/refreshGameInfo');
      } catch (error) {
        this.claimError = error.message || 'Не удалось получить опыт';
      } finally {
        this.claiming = false;
      }
    },
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.match_card {
  display: flex;
  flex-direction: column;
  gap: 0;
  align-items: stretch;

  &.has_xp {
    align-items: flex-end;

    .match_box {
      align-self: stretch;
      width: 100%;
      border-radius: 5px 0 5px 5px;
    }
  }
}

.match_xp_tab {
  width: auto;
  min-width: 24%;
  max-width: 130px;
  background: @DarkColorBG;
  padding: 4px 4px 0;
  border-radius: 5px 5px 0 0;
  box-sizing: border-box;

  .xp_btn {
    display: block;
    width: 100%;
    border: none;
    cursor: pointer;
    background: @YesWrite;
    color: @orange;
    font-size: 10px;
    line-height: 1.2;
    border-radius: 3px 3px 0 0;
    .shadow_inset;
    padding: 5px 9px;
    box-sizing: border-box;
    min-height: 15px;
    text-align: center;
    font-weight: 600;
    white-space: nowrap;

    &:disabled {
      opacity: 0.65;
      cursor: wait;
    }

    &:hover:not(:disabled) {
      filter: brightness(1.04);
    }
  }

  .xp_claimed {
    width: 100%;
    box-sizing: border-box;
    font-size: 10px;
    line-height: 1.2;
    border-radius: 3px 3px 0 0;
    .shadow_inset;
    padding: 5px 9px;
    min-height: 15px;
    color: @orange;
    text-align: center;
    font-weight: 600;
    white-space: nowrap;
  }

  .money_claimed {
    width: 100%;
    box-sizing: border-box;
    font-size: 10px;
    line-height: 1.2;
    border-radius: 3px 3px 0 0;
    .shadow_inset;
    padding: 5px 9px;
    min-height: 15px;
    color: @YesWrite2;
    text-align: center;
    font-weight: 600;
    white-space: nowrap;
  }

  .chest_claimed {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    width: 100%;
    box-sizing: border-box;
    font-size: 10px;
    line-height: 1.2;
    border-radius: 3px 3px 0 0;
    .shadow_inset;
    padding: 5px 9px;
    min-height: 15px;
    color: @yellow;
    text-align: center;
    font-weight: 600;
    white-space: nowrap;
  }

  .xp_level_up {
    margin-top: 2px;
    font-size: 8px;
    color: @YesWrite;
    line-height: 1.1;
    text-align: center;
    font-weight: 600;
  }

  .xp_error {
    margin-top: 2px;
    font-size: 8px;
    color: @boks;
    line-height: 1.1;
    text-align: center;
  }
}

.reward_tabs {
  display: flex;
  flex-direction: row;
  gap: 4px;
  align-items: flex-end;
}

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
  font-size: 10px;
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
      font-size: 10px;

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

        .count_main {
          color: @pearl;
        }

        .count_bet {
          color: @valleyball;
          font-weight: 800;
        }

        .count_sep {
          color: @colorBlur;
          font-weight: 700;
          padding: 0 2px;
        }
      }
    }
  }
}
</style>