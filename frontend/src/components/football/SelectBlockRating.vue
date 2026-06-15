<template>
  <div class="select_wrapper">
    <select class="select" v-if="arRating" v-model="selected">
      <option v-for="(id) in matchNumbers" :value="id" :key="id">№: {{id}}</option>
    </select>
    <div v-for="(data, index) in arRating" :key="index">
      <div v-if="selected==index">
        <table class="table table-dark table-hover rating_table_box">
          <thead>
          <tr>
            <th class="pr_table_col">#</th>
            <th class="pr_table_col">Δ</th>
            <th class="pr_table_col">Ник</th>
            <th class="pr_table_col">Баллы</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="(el) in data" :key="el.id">
            <td class="pr_table_col">{{el.place}}</td>
            <td class="pr_table_col" v-if="el.diff === 0"><div class="zero_diff">–</div></td>
            <td class="pr_table_col" v-else-if="el.diff >0"><div class="plus_diff">{{el.diff}}</div></td>
            <td class="pr_table_col" v-else-if="el.diff <0"><div class="minus_diff">{{el.diff}}</div></td>
            <td class="pr_table_col user_cell">
              <span class="user_ava">
                <img :src="url+el.user.img" alt="" v-if="el.user.img">
                <img src="@/assets/img/ava_no_img.jpg" alt="" v-else>
              </span>
              <div class="user_nick">{{el.user.name}}</div><span class="user_info" @click="$router.push('/profile/' + el.user.id)">i</span></td>
            <td class="pr_table_col score">{{el.score}}</td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "SelectBlockRating",
  props: {
    arRating: {
      type: Object
    },
  },
  data(){
    return{
      matchNumbers: [],
      selected: '',
      url:  'https://prognos9ys.ru',
    }
  },

  // mounted() {
  //   this.checkIds()
  // },

  watch:{
    arRating(){
      this.selectMatch(this.arRating)
    }
  },

  methods: {
    selectMatch(arRating){
      this.matchNumbers = Object.keys(arRating || {})

      this.selected = this.matchNumbers.length

      return this.matchNumbers.reverse()
    },
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.rating_table_box{
  th,td{
    padding: 2px;
    text-align: left;
  }

  .user_cell{
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    gap: 4px;

    .user_ava{
      width: 36px;
      background: @colorBlur;
      border-radius: 5px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      img{
        border: 1px solid @YesWrite;
        width: 100%;
        border-radius: 50%;
      }
    }
    .user_nick{
      width: 85%;
      text-align: left;
    }

    .user_info{
      cursor: pointer;
      display: flex;
      flex-direction: row;
      justify-content: center;
      align-items: center;
      width: 24px;
      height: 24px;
      font-weight: 700;
      color: @YesWrite;
      border: 2px solid @YesWrite;
      border-radius: 5px;
    }
  }
  .score{
    text-align: right;
  }
}
.zero_diff{
  min-width: 20px;
  color: @pearl;
  font-size: 16px;
  font-weight: 700;
}
.plus_diff{
  color: @YesWrite;
}
.minus_diff{
  color: @red;
}
.select_wrapper{
  text-align: right;
  .select{
    font-weight: 500;
    font-size: 14px;
    line-height: 22px;
    /* identical to box height, or 157% */

    display: flex;
    flex-direction: row;
    gap: 12px;

    text-align: right;
    font-family: 'Roboto', sans-serif;

    /* Серый */
    color: #8A8A8E;
    border: none;
    outline: none;
    margin-bottom: 4px;
    padding: 0 4px;
    border-radius: 5px;
    option{
      text-align: left;
    }
  }
}

</style>