<template>
  <div class="wrapper">
    <div class="date cell">
      <i class="bi bi-calendar4-event"></i> {{ title.date }}
    </div>
    <div class="time cell">
      <i class="bi bi-alarm"></i>  {{ title.time }}
    </div>
    <div class="number cell">
      {{ title.number }}
    </div>
    <div class="name cell">
      <i class="bi bi-joystick"></i>{{ title.name }}
    </div>
    <div class="score cell" v-if="title.score">
      <i class="fa fa-sticky-note" aria-hidden="true"></i> {{ title.score }}
    </div>
  </div>
</template>

<script>
export default {
  name: "GameTitle",
  props: {
    title: {
      type: Object,
    }
  }
}
</script>

<style lang="less" scoped>
@blockBG: #253133;
@colorText: #fff;
@colorText2: #228B22;
@qatar: #8a1538;
@nowrite: rgb(13,202, 240);
@yeswrite: #146c43;
@color: rgb(240,240,240);
@colorBlur: #A6B2AA;

@darkbg: rgb(25,25,25);
.wrapper{
  display: flex;
  flex-direction: row;
  justify-content: flex-start;
}
 .cell{
   background: @darkbg;
   //background:#474C52;
   color: @colorBlur;

   border-radius: 5px;
   margin-right: 4px;
   padding: 3px 5px;
   box-shadow: inset 0 2px 10px 1px rgba(0, 0, 0, .3), inset 0 0 0 2px rgba(0, 0, 0, .3), 0 1px rgba(255, 255, 255, .08);
   &:last-child{
     margin-right: 0;
   }
   &.kgtb_score{
     position: absolute;
     right: 0px;
     color: @colorText2;
   }
 }
</style>