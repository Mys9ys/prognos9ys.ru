<template>
  <div class="wrapper">
    <PageHeader class="header">Регистрация</PageHeader>
    <form action="" class="form" v-if="nostrCount">
      <AuthInput
          v-for="(el, index) in inputs"
          :key="index"
          :name="el.vmod"
          v-model:value="el.value"
          v-model:error="el.error"

          @focusout="regFocusOut(el)"

          @input="checkPass(el)"

          ref="regInput"
          :inputInfo="el"
      ></AuthInput>
    </form>

    <div class="footer">
      <div class="btn_block">
        <div v-if="registerError" class="error_mes">{{ registerError }}</div>
        <!--        <BlueBtn class="disable" :arrow="true">Войти</BlueBtn>-->
        <FillBtn
            class="btn"
            :arrow="true"
            @click="enterClick"
        >Зарегистрироваться
        </FillBtn>
      </div>

    </div>

  </div>
</template>

<script>

import AuthInput from "@/components/ui/input/AuthInput";
import {mapActions, mapState} from "vuex";
import PageHeader from "@/components/main/PageHeader";
import FillBtn from "@/components/ui/btn/FillBtn";


export default {
  name: "RegisterPage",
  components: {
    PageHeader,
    AuthInput,
    FillBtn,
  },

  data() {
    return {
      defaultName: 'Нострадамус № ',
      errors: [],
      inputs: [
        { f_icon: require('@/assets/icon/form/fio.svg'), title: 'Nick (можно сменить)', l_icon: '', vmod: 'nick', value: ''},
        { f_icon: require('@/assets/icon/form/mail.svg'), title: 'E-mail', l_icon: '', vmod: 'mail', value: ''},
        { f_icon: require('@/assets/icon/form/pass.svg'), title: 'Пароль', l_icon: require('@/assets/icon/form/eye.svg'), vmod: 'pass', value: ''},
        { f_icon: require('@/assets/icon/form/pass.svg'), title: 'Повторите пароль', l_icon: require('@/assets/icon/form/eye.svg'), vmod: 'pass2', value: ''},
      ],
    }
  },

  created() {
    this.fillNostrCount()
  },

  watch: {
    nostrCount(){
      this.inputs[0].value = this.defaultName + this.nostrCount
    }
  },

  methods: {
    ...mapActions({
      registrationRequest: 'reg/registrationRequest',
      getNostrCount: 'reg/getNostrCount'
    }),

    async fillNostrCount(){
       await this.getNostrCount()
    },

    async enterClick() {

      this.errors = []

      this.passError()

      this.$refs.regInput.forEach((el, index) => {

        if (el.inputInfo.error) this.errors.push(el.inputInfo.error)
        if (!el.inputInfo.value) {
          // вбиваем ошибки незаполненых полей
          this.inputs[index].error = 'Введите ' + el.inputInfo.title
          this.errors.push(this.inputs[index].error)
        } else {
          // вбиваем данные регистрации
          if(!el.inputInfo.error){
            this.inputs[index].error = ''
            this.regData[el.inputInfo.vmod] = el.inputInfo.value
          }
        }
      })

      console.log('this.errors', this.errors)
      // this.errors.push('dzsd')


      if (this.errors.length === 0) {
        // запрос регистрации
        await this.registrationRequest()

        if (!this.registerError) this.$router.push('/register_success')
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
      this.inputs[2].error = this.inputs[3].error = ''
      if (this.inputs[2].value !== this.inputs[3].value) {
        this.errors.push('Пароли не совпадают')
        this.inputs[2].error = this.inputs[3].error = 'Пароли не совпадают'
      }
    }
  },

  computed: {
    ...mapState({
      nostrCount: state => state.reg.nostrCount,
      avaLink: state => state.reg.avaLink,
      regData: state => state.reg.regData,
      registerError: state => state.reg.registerError,
    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";
.wrapper {
  position: relative;
  background: transparent;
  width: 100%;
  margin: 0 auto;
  height: 100vh;
  text-align: center;
  padding: 0 24px;
  padding-top: 45px;

  .form {
    margin-top: 55px;
    display: flex;
    flex-direction: column;
    gap: 24px;

    .btn {
      margin-top: 69px;
    }
  }

  .footer {
    text-align: center;
    margin-top: 25px;
    display: flex;
    flex-direction: column;
    gap: 12px;

    .btn_block {
      position: relative;

      .error_mes {
        position: absolute;
        display: inline-block;
        left: 10px;
        bottom: 60px;
        color: #FF6262;
      }
    }
  }

}
</style>