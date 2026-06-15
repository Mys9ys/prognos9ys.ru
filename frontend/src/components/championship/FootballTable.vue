<template>
  <PreLoader v-if="elLoader"></PreLoader>
  <PageHeader class="header">Таблица</PageHeader>
  <div class="title_wrapper" v-if="tableData.info">
    <div class="logo" >
      <img :src="url + tableData.info.img" alt="">
    </div>
    <div class="title">
      {{tableData.info.NAME}}
    </div>
  </div>

  <div v-if="tableData.groups">

    <div class="table_wrapper" v-for="(teams, char) in tableData.groups" :key="char">
      <div class="title_wrapper group_wrapper" v-if="char !== 0">
        <span class="title"> Группа: {{char}}</span>
      </div>

      <table class="table table-hover table_temp">
        <tr class="table_row">
          <th ><span class="t_col">#</span></th>
          <th ><span class="t_col">Команда</span></th>
          <th ><span class="t_col">И</span></th>
          <th ><span class="t_col">В</span></th>
          <th ><span class="t_col">Н</span></th>
          <th ><span class="t_col">П</span></th>
          <th ><span class="t_col">Мячи</span></th>
          <th ><span class="t_col">Очки</span></th>
        </tr>

        <tr v-for="(item, index) in teams" :key="index">
          <td><span class="t_col">{{index+1}}</span></td>
          <td class="team_col">
            <div class="flag">
              <img :src="url + item.info.img" alt="">
            </div>
            <div class="title">{{item.info.NAME}}</div>
          </td>
          <td><span class="t_col">{{item.matches ?? 0}}</span></td>
          <td><span class="t_col">{{item.win ?? 0}}</span></td>
          <td><span class="t_col">{{item.draw ?? 0}}</span></td>
          <td><span class="t_col">{{item.lose ?? 0}}</span></td>
          <td><span class="t_col">{{item.plus ?? 0}}-{{item.minus ?? 0}}</span></td>
          <td><span class="t_col">{{item.score ?? 0}}</span></td>
        </tr>
      </table>
    </div>

  </div>
</template>

<script>
import PageHeader from "@/components/main/PageHeader";
import {mapActions, mapState} from "vuex";
import PreLoader from "@/components/main/PreLoader";

export default {
  name: "FootballTable",
  components: {
    PageHeader,
    PreLoader
  },
  data() {
    return {
      url:  'https://prognos9ys.ru/',
      elLoader: false
    }
  },
  created() {
    this.fillTable()
  },

  methods: {
    ...mapActions({
      getTableInfo: 'championship/getFootballTable',
    }),

    async fillTable() {
      this.elLoader = true
      this.queryData.events = this.$route.params.event
      await this.getTableInfo()
      this.elLoader = false
    }
  },
  computed: {
    ...mapState({
      queryData: state => state.championship.queryData,
      tableData: state => state.championship.footballData
    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.title_wrapper{
  display: flex;
  flex-direction: row;
  gap: 4px;
  justify-content: flex-start;
  .logo{
    .shadow_inset;
    background: @colorBlur;
    img{
      width: 24px;
      height: 24px;
    }
  }
}
.group_wrapper{
  margin: 0;
  gap:0;
}
.table_wrapper{
  background: @DarkColorBG;
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  margin-top: 10px;
}
.table_row{
  gap:2px;
  background: @DarkColorBG;
  padding: 2px;
  border-radius: 5px;
  color: @colorText;
}
.t_col{

  .shadow_inset;
  color: @colorText;
  .flex_center;
}

.team_col{
  min-width: 145px;
  color: @colorText;
  display: flex;
  flex-direction: row;
  justify-content: flex-start;
  gap: 2px;
  .flag{
    padding: 2px;
    .shadow_inset;
    img{
      width: 18px;
      height: 18px;
    }
  }
  .title{
    width: 100%;
    .shadow_inset;
    text-align: left;
  }
}
</style>