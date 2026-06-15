<template>
  <div class="title">
    <div class="grand_name">
      <div class="number_cell">
        # {{ info.number }}
      </div>
      <div class="name">Гранд-при</div>
      <div class="name">{{ info.name }}</div>
    </div>

    <div class="country" v-if="info.country">
      <div class="name">{{ info.country.NAME }}</div>
      <div class="flag">
        <img :src="urlImg + info.country.flag" alt="">
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "RaceHeader",
  props: {
    info: {
      type: Object
    }
  },
  data() {
    return {
      moreInfo: false,
      urlImg: 'https://prognos9ys.ru/'
    }
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.title {
  width: 100%;
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  gap: 4px;

  font-size: 14px;

  .grand_name, .country {
    display: flex;
    flex-direction: row;
    gap: 4px;
    .flex_center;
  }

  .flag {
    width: 24px;
    height: 24px;
    padding: 1px;

    .shadow_inset;

    img {
      width: 100%;
    }
  }

  .name {
    .shadow_inset;
  }
}
.number_cell{
  .shadow_inset;
  .flex_center;
}
</style>