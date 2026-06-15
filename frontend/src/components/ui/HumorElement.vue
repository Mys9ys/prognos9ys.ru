<template>

  <div class="humor_el" v-if="!add">
    <div class="header">
      <div class="author like_count"><b>Добавил: </b> Mys9ysilii</div>
      <div class="like_count">{{ prank.seen ?? 0}} 👁</div>
      <div class="like_count">{{ prank.likes ?? 0}} ❤</div>
    </div>
    <div class="text">
      {{prank.text}}
    </div>
    <div class="btn_block">
      <div class="send btn" @click="getNextPrank">Следующая ↝</div>
      <div class="add btn" @click="add = true">Добавить +</div>

      <div class="like btn" @click="setLikes(prank.ID, 'up')" v-if="!like">Нравится ❤</div>
      <div class="like btn" @click="setLikes(prank.ID, 'down')" v-else>Не нравится 💔</div>
    </div>
  </div>
  <div class="humor_add" v-else>
      <div class="btn close" @click="add=false">x</div>
      <div class="error_mes" v-if="error">{{error}}</div>
      <div class="error_mes success_mes" v-if="success">{{success}}</div>
      <textarea ref="prankText" class="prank_text" v-model="textPrank" @click="error = ''"></textarea>
      <div class="btn_block btn_send">
        <div class="btn" @click="addPrank">Отправить</div>
      </div>
    </div>

</template>

<script>
import {mapActions, mapState} from "vuex";

export default {
  name: "HumorElement",
  data() {
    return {
     like: false,
      add: false,
      textPrank: '',
      error: '',
      success: ''
    }
  },

  mounted() {
    this.$nextTick(function () {
        console.log('mounted humor')
      this.getPrank()
    })
  },
  methods:{
    ...mapActions({
      getOnePrank: 'humor/getOnePrank',
      setLikesToPrank: 'humor/setLikesToPrank',
      sendNewPrank: 'humor/sendNewPrank',
    }),

    async addPrank(){
      this.success = ''
      // this.add = false
      if(!this.textPrank.length) {
        this.error = 'Вы отправляете пустоту'
      } else if(this.textPrank.length < 15) {
        this.error = 'Что то короткое('
      } else {
        console.log('text', this.textPrank)
        this.newPrank['text'] = this.textPrank
        this.newPrank['userToken'] = this.token

        console.log('this.newPrank', this.newPrank)
        await this.sendNewPrank()

        this.success = 'Отправлено успешно'

        setTimeout(() => {
          this.add = false
        }, 1800)
      }


    },

    getNextPrank(){
      this.getPrank()
      this.like = false
    },

    setLikes(id, type){
      if(type === 'up') this.prank.likes++
      if(type === 'down') this.prank.likes--

      this.like = !this.like
      this.setLikeFunc(id, this.prank.likes)
    },

    async getPrank(){
      await this.getOnePrank()
    },

    async setLikeFunc(id, likes){

      this.likeData['prankId'] = id
      this.likeData['likes'] = likes
      this.likeData['userToken'] = this.token

      await this.setLikesToPrank()


    }
  },

  computed: {
    ...mapState({
      prank: state => state.humor.prank,
      likeData: state => state.humor.likeData,
      newPrank: state => state.humor.newPrank,
      token: state => state.auth.authData.token,
    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.humor_el {
  position: relative;
  background: @cubersport;
  color: @colorText;
  display: flex;
  flex-direction: column;

  padding: 4px;
  border-radius: 5px;

  gap: 4px;

  margin-bottom: 4px;
  text-align: right;

  .text {
    .shadow_inset;
    text-align: left;
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

.humor_el:after {
  content: "";
  position: absolute;
  width: 0px;
  height: 0px;
  top: 0%;
  right: 0%;
  border-top: 28px solid @YesWrite;
  border-left: 28px solid @backGrey;
}

.btn_block {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  gap: 4px;
  padding: 2px 0;

  .btn {
    color: @colorText;
    display: inline;
    padding: 3px 8px;
    border-radius: 5px;
    .shadow_template;
    cursor: pointer;
    font-size: 12px;
    min-width: 105px;

    &:hover {
      opacity: 0.8;
    }
  }
}
.btn_send{
  text-align: right;
}
.humor_add{
  position: relative;
  width: 100%;
  top: 0;
  background: @cubersport;
  color: @colorText;
  display: flex;
  flex-direction: column;

  align-items: flex-end;

  padding: 4px;
  border-radius: 5px;
  padding-top: 24px;

  gap: 4px;

  text-align: right;

  .error_mes{
    position: absolute;
    top: 4px;
    left: 4px;
    width: 100%;
    text-align: left;
    height: 16px;
    color: @red;
    font-size: 12px;
    padding: 4px;
  }
  .success_mes{
    color: @YesWrite;
  }

  .prank_text{
    .shadow_inset;
    width: 100%;
    min-height: 115px;
    background: @cubersport;
    color: @colorText;
    font-size: 12px;
  }
}

.close{
  position: absolute;
  top:0px;
  right: 6px;
  padding: 0;
}
</style>