<template>
  <form class="form_code">
    <AuthInput
        v-for="(el, index) in inputs"
        :key="index"

        :name="el.vmod"
        v-model:value="el.value"
        v-model:error="el.error"

        @focusout="regFocusOut(el)"

        @input="checkPass(el)"

        ref="authInput"
        :inputInfo="el"
    ></AuthInput>
    <div class="btn_send_block">
      <div v-if="recoverPassError.error" class="error_mes">{{ recoverPassError.mes }}</div>
      <FillBtn @click="sendRecoverPass">Изменить пароль</FillBtn>
    </div>
  </form>
</template>

<script>
import AuthInput from "@/components/ui/input/AuthInput";
import {mapActions, mapState} from "vuex";
import FillBtn from "@/components/ui/btn/FillBtn";

export default {
  name: "FormCode",
  props: {
    userId: {
      type: String
    }
  },
  components: {
    FillBtn,
    AuthInput,
  },

  data(){
    return {
      errors: [],
      id1 : 1,
      id2 : 2,
      inputs: [
        { f_icon: require('@/assets/icon/form/code.svg'), title: 'Проверочный код', l_icon: '', vmod: 'code', value: '', error: ''},
        { f_icon: require('@/assets/icon/form/pass.svg'), title: 'Пароль', l_icon: require('@/assets/icon/form/eye.svg'),  vmod: 'pass', value: ''},
        { f_icon: require('@/assets/icon/form/pass.svg'), title: 'Повторите пароль', l_icon: require('@/assets/icon/form/eye.svg'), vmod: 'pass2', value: ''},
      ],
    }
  },
  methods: {

    ...mapActions({
      recoverPassRequest: 'recover/recoverPassRequest',
    }),

    async sendRecoverPass(){
      console.log('send', this.userId)

      this.errors = []

      this.passError()

      this.$refs.authInput.forEach((el, index) =>{

        if(el.inputInfo.error) this.errors.push(el.inputInfo.error)
        if(!el.inputInfo.value) {
          // вбиваем ошибки незаполненых полей
          this.inputs[index].error = 'Введите ' + el.inputInfo.title
          this.errors.push(this.inputs[index].error)
        } else {
          // вбиваем данные авторизации если не пришли ошибки
          if(!el.inputInfo.error){
            this.inputs[index].error = ''
            this.recoverPass[el.inputInfo.vmod] = el.inputInfo.value
          }
        }
      })

      if (this.errors.length === 0) {

        this.recoverPass['userId'] = this.userId

        await this.recoverPassRequest()

        if(this.sendPassSuccess){
          this.$router.push('/recover_success')
        }
      }
    },

    regFocusOut(elem) {
      if (elem.vmod === 'pass' || elem.vmod === 'pass2') {
        this.passError()
      }
    },

    checkPass(){
      this.passError()
    },

    passError() {

      this.inputs[this.id1].error = this.inputs[this.id2].error = ''
      if (this.inputs[this.id1].value !== this.inputs[this.id2].value) {
        this.errors.push('Пароли не совпадают')
        this.inputs[this.id1].error = this.inputs[this.id2].error = 'Пароли не совпадают'
      }
    },

    formSubmit(e) {
      e.preventDefault()
    },
  },
  computed: {
    ...mapState({
      recoverPass: state => state.recover.recoverPassData,
      recoverPassError: state => state.recover.recoverPassError,
      sendPassSuccess: state => state.recover.sendPassSuccess
    })
  },
}
</script>

<style lang="less" scoped>
  .form_code{
    padding-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .btn_send_block {
    position: relative;

    .error_mes {
      position: absolute;
      left: 10px;
      top: -20px;
      font-size: 14px;
      color: #FF6262;
    }

  }
</style>