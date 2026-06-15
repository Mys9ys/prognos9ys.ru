<template>
  <div class="element_box" v-if="element">
    <div class="title">
      <div class="grand_name">
        <div class="number_cell">
          # {{element.number}}
        </div>
        <div class="name">Гранд-при</div>
        <div class="name">{{element.name}}</div>
      </div>

      <div class="country" v-if="element.country">
        <div class="name">{{element.country.NAME}}</div>
        <div class="flag">
          <img :src="urlImg + element.country.flag" alt="">
        </div>
      </div>
    </div>
    <div class="qualification event_box">
      <div class="date">{{element.qual.date}}</div>
      <div class="time">{{element.qual.time}}</div>
      <div class="title">Квалификация</div>
      <div class="score_wrapper" v-if="element.result">
      <div class="score">{{element.result.qual_sum}}</div>
      </div>
    </div>
    <div class="race event_box" v-if="element.sprint">
      <div class="date">{{element.sprint.date}}</div>
      <div class="time">{{element.sprint.time}}</div>
      <div class="title">Спринт</div>
      <div class="score_wrapper" v-if="element.result">
        <div class="score">{{element.result.sprint_sum}}</div>
      </div>
    </div>
    <div class="race event_box">
      <div class="date">{{element.race.date}}</div>
      <div class="time">{{element.race.time}}</div>
      <div class="title">Гонка</div>
      <div class="score_wrapper" v-if="element.result">
        <div class="score" >{{element.result.race_sum}}</div>
      </div>
    </div>

    <div class="btn_block">
      <div class="status_wrapper" :class="{'close' : element.status==='Завершена', 'block' : element.status==='Отменена'}">
        <div class="status">{{element.status}}</div>
      </div>
      <div class="btn" v-if="element.active === 'Y'"
           @click="$router.push(link)"
      >{{element.fill ? 'Изменить' : 'Заполнить'}}</div>
      <div class="btn btn_grey" v-else @click="$router.push(link)">Посмотреть</div>
    </div>

  </div>
</template>

<script>
export default {
  name: "EventRace",
  props: {
    element: {
      type: Object
    }
  },
  data() {
    return {
      moreInfo: false,
      link: '/race/' + this.element.event + '/' + this.element.number,
      urlImg: 'https://prognos9ys.ru/'
    }
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.element_box {
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;

  .title{
    width: 100%;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    gap: 4px;

    font-size: 14px;

    .grand_name, .country{
      display: flex;
      flex-direction: row;
      gap: 4px;
      .flex_center;
    }

    .flag{
      width: 24px;
      height: 24px;
      padding: 1px;

      .shadow_inset;
      img{
        width: 100%;
      }
    }

    .name{
      .shadow_inset;
    }
  }

  .event_box{
    width: 100%;
    display: flex;
    flex-direction: row;
    gap: 4px;
    font-size: 12px;

    .date, .time, .title{
      .shadow_inset;
    }
  }
}
.btn{
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
  &:hover{
    opacity: 0.8;
  }
}
.btn_grey{
  background: @maxdarkgrey;
  color: @darkbg;
}

.number_cell{
  .shadow_inset;
  .flex_center;
}
.btn_block{
  display: flex;
  flex-direction: row;
  gap: 4px;
  justify-content: space-between;
}
.status_wrapper{
  background: @YesWrite;
  color: @colorText;
  padding: 2px;
  border-radius: 5px;
  .flex_center;
}
.status{
  .shadow_inset;
  .flex_center;
  font-size: 11px;
}

.close{
  background: @maxdarkgrey;
  color: @darkbg;
}
.block{
  background: @red;
}

.score_wrapper{
  background: @pearl;
  color: @colorText;
  padding: 2px;
  border-radius: 5px;

  .flex_center;
  .score{
    .shadow_inset;
    .flex_center;
    min-width: 35px;
    font-size: 11px;
  }
}
</style>