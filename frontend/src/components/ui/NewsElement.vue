<template>
  <div class="news_el">
    <div class="header">
      <div class="like_count"><b>Добавил: </b> Mys9ysilii</div>
      <div class="like_count">{{ item.PROPERTY_SEEN_VALUE ?? 0}} 👁</div>
      <div class="like_count">{{ item.PROPERTY_LIKES_VALUE ?? 0}} ❤</div>
    </div>
    <div class="news_title">{{item.NAME}}</div>
    <div class="news_preview" :class="{'small': smallNews}" v-html="item['~PREVIEW_TEXT']"></div>
    <div class="btn_box">
      <div class="like btn" @click="like = !like" v-if="!like">Нравится ❤</div>
      <div class="like btn" @click="like = !like" v-else>Не нравится 💔</div>
      <span v-if="smallNews" class="btn" @click="smallNews = !smallNews">Подробнее</span>
      <span v-else class="btn" @click="smallNews = !smallNews">Скрыть</span>
    </div>
  </div>
</template>

<script>

export default {
  name: "NewsElement",
  props: {
    item: {
      type: Object
    }
  },
  data() {
    return {
      like: false,
      // news: {
      //   title: 'Обновление 2.33',
      //   text: 'Добавлена возможность загружать аватарку.<br>' +
      //       '        Доступные форматы: png, gif, jpeg.<br>' +
      //       '        Размер картинки: не более 500кб.<br>' +
      //       '        Форма картинки: квадрат (обрезки пока нет).        ',
      //   seen: 12,
      //   likes: 3
      // },
      smallNews: true
    }
  },


}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.news_el{
  position: relative;
  background: @kerling;
  color: @colorText;
  display: flex;
  flex-direction: column;

  padding: 4px;
  border-radius: 5px;

  gap: 4px;

  margin-bottom: 4px;
  text-align: right;
  .news_title{
    .shadow_inset;
    text-align: left;
  }
  .news_preview{
    .shadow_inset;
    text-align: left;
  }
  .small{
    height: 22px;
    overflow: hidden;
  }
  .btn_box{
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    padding: 2px 0;
  }
  .btn{
    color: @colorText;
    display: inline;
    padding: 3px 8px;
    border-radius: 5px;
    .shadow_template;
    cursor: pointer;
    font-size: 12px;
    min-width: 105px;
    &:hover{
      opacity: 0.8;
    }
  }
}
.header{
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  gap: 4px;
  padding-right: 28px;

  .like_count {
    .shadow_inset;
    .flex_center;
    font-size: 12px;
    gap: 2px;
    padding: 3px 4px;
    min-width: 36px;
  }
}
.news_el:after {
  content: "";
  position: absolute;
  width: 0px;
  height: 0px;
  top: 0%;
  right: 0%;
  border-top: 28px solid @YesWrite;
  border-left: 28px solid @backGrey;
}
</style>