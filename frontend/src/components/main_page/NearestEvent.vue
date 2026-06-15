<template>
  <div class="nearest_events_wrapper block_wrapper">
    <div class="title_wrapper nearest">
      <div class="title">Ближайшие события</div>
    </div>
    <div class="title_wrapper period_wrapper nearest">
      <div class="period"
           v-for="(el, index) in arr"
           @click="setActive(index)"
           :class="{'active': active=== index}"
           :key="index">
        <div class="name">{{el.name}}</div>
        <div class="count el">{{el.count}}</div>
        <div class="set el">{{el.set}}</div>
      </div>
    </div>
    <div class="events_period_wrapper" v-for="(a, period) in arr" :key="period">
      <div class="events_period_block" v-if="period === active">
        <div class="title_wrapper period_wrapper nearest" v-if="a.count === 0">
          <div class="empty" >событий нет</div>
        </div>
        <div v-else>
          <div class="events_for" v-for="(items, event) in arrNearest[period]" :key="event">
            <div class="race" v-if="event === 'race'">
              <EventRace
                  v-for="(el, index) in items['items']"
                  :key="index"
                  :element="el"
              ></EventRace>
            </div>

            <div class="football" v-if="event === 'football'">
              <div class="football_event_box" v-for="(events, event_id) in items" :key="event_id">
                <div class="event_title">
                  <div class="img_box">
                    <img :src="urlImg+events['info']['img']" alt="">
                  </div>
                  <div class="info_box">
                    <div class="name">{{events['info']['PREVIEW_TEXT']}}</div>
                    <div class="btn_box">
                      <div class="count">Матчей: {{Object.keys(events['items']).length}}</div>
                      <div class="btn_block">
                        <div class="event_btn" @click="$router.push('/championship/'+event +'/' +event_id)">
                          <span class="text">Таблица</span>
                          <img class="arrow" src="@/assets/icon/pagination/right.svg" alt="">
                        </div>
                        <div class="event_btn" @click="$router.push('/'+event +'/' +event_id)">
                          <span class="text">Список</span>
                          <img class="arrow" src="@/assets/icon/pagination/right.svg" alt="">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <EventMatch
                    v-for="(el, index) in events['items']"
                    :key="index"
                    :match="el"
                ></EventMatch>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
    <div class="btn_box">
      <div class="btn_all nearest" @click="$router.push('/catalog')">Все события
        <img src="@/assets/icon/pagination/right.svg" alt=""></div>
    </div>

  </div>
</template>

<script>
// import SectionMatches from "@/components/football/SectionMatches";
import {mapActions, mapState} from "vuex";
import EventRace from "@/components/race/EventRace";
import EventMatch from "@/components/football/EventMatch";

export default {
  name: "NearestEvent",
  components: {
    EventRace,
    EventMatch
  },
  data() {
    return {
      active: 'today',
      arr: {
        'yesterday': {'name': 'Вчера', 'count': 0, 'set': 0},
        'today': {'name': 'Сегодня', 'count': 0, 'set': 0},
        'tomorrow': {'name': 'Завтра', 'count': 0, 'set': 0},
      },
      // link: '/football/' + this.match.event + '/' + this.match.number,
      urlImg: 'https://prognos9ys.ru/'
    }
  },

  mounted() {
    this.getNearestFootball()
  },

  methods: {
    ...mapActions({
      authRequest: 'mainPage/getNearest',
    }),

    setActive(value) {
      this.active = value
    },

    async getNearestFootball() {

      this.setToken['userToken'] = this.token

      await this.authRequest()

      if(this.arrNearest){
        Object.keys(this.arrNearest).forEach((index)=>{
          if(this.arrNearest[index]) {
            this.arr[index] = this.arrNearest[index]['info']
            if(this.arrNearest[index]['info']['visible'] === true) this.active = index
          }
        })
      }
    }

  },

  computed: {
    ...mapState({
      token: state => state.auth.authData.token,
      setToken: state => state.mainPage.setToken,
      arrNearest: state => state.mainPage.arrNearest
    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.block_wrapper {

}

.title_wrapper {
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  margin-top: 25px;
  text-align: left;

  .title {
    .shadow_inset;
  }
}

.period_wrapper {
  margin-top: 10px;
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  margin-bottom: 4px;

  .period {
    display: flex;
    flex-direction: row;
    gap: 2px;
    .shadow_inset;

    .el {
      .shadow_inset;
      .flex_center;
      font-size: 12px;
    }

    .set {
      color: @pearl;
    }
  }

  .active {
    background: @colorText2;
  }
}

.empty_wrapper {
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  margin-top: 8px;
  text-align: left;
  margin-bottom: 10px;
}

.empty {
  .shadow_inset;
  font-size: 12px;
}

.btn_box {
  text-align: right;
}

.btn_all {
  display: inline-block;
  background: @colorText2;
  color: @colorText;
  cursor: pointer;
  .shadow_template;
  padding: 2px 6px;
  font-size: 14px;
  border-radius: 3px;
  text-align: center;
  border: 1px solid transparent;
  text-decoration: none;
  margin-top: 10px;

  img {
    margin-left: 12px;
  }

  &:hover {
    opacity: 0.8;
  }
}

.nearest {
  background: @billiard;
}
.empty{
  font-size: 12px;
}
.events_for{
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-bottom: 5px;
}

.football_event_box{
  display: flex;
  flex-direction: column;
  gap: 2px;

  margin-top: 8px;

  .event_title{
    width: 100%;
    display: flex;
    flex-direction: row;
    padding: 4px;
    color: @color;
    border-radius: 5px;
    text-align: left;
    gap: 4px;

    background: @YesWrite2;

    .img_box{
      width: 52px;
      height: 52px;
      .shadow_inset;
      //.flex_center;
      background: @colorText;
      img{
        width: 100%;
        height: 100%;
      }
    }

    .info_box{
      width:85%;
      display: flex;
      flex-direction: column;
      gap:4px;

      .name{
        width:100%;
        font-size: 16px;
        .shadow_inset;
        .flex_center;
        justify-content: left;
      }

      .btn_box{
        display: flex;
        flex-direction: row;
        gap:4px;
        justify-content: space-between;

        .count{
          .flex_center;
          .shadow_inset;
          font-size: 12px;
        }

        .btn_block{
          display: flex;
          flex-direction: row;
          gap:4px;

          .event_btn{
            min-width: 70px;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            gap: 3px;
            font-size: 12px;
            color: @colorText;
            .shadow_template;
            padding: 2px;
            border-radius: 5px;
            .text{
              width: 80%;
            }
            .arrow{
              width: 10px;
            }
          }
        }
      }
    }
  }
}

</style>