<template>
  <div class="news_wrapper block_wrapper">
    <div class="title_wrapper news">
      <div class="title">Последняя новость</div>
    </div>
    <NewsElement
      :item="last"
    ></NewsElement>
    <div class="btn_box">
      <div class="btn_all news" @click="$router.push('/news')">Все новости <img src="@/assets/icon/pagination/right.svg" alt=""></div>
    </div>
  </div>
</template>

<script>
import NewsElement from "@/components/ui/NewsElement";
import {mapActions, mapState} from "vuex";

export default {
  name: "NewsBlock",
  components: {
    NewsElement
  },

  mounted() {
    this.$nextTick(function () {
      this.getNews()
    })
  },

  methods: {
    ...mapActions({
      getOneNews: 'news/getOneNews',
    }),

    async getNews(){
      await this.getOneNews()
    },
  },



  computed: {
    ...mapState({
      last: state => state.news.last,
    })
  },

}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.title_wrapper {
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  margin-top: 25px;
  text-align: left;
  margin-bottom: 10px;

  .title {
    .shadow_inset;
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
.news{
  background: @kerling;
}
</style>