<template>
  <div class="wrapper">
    <PageHeader class="header">Авторизация</PageHeader>

    <form action="" @click="formSubmit" class="form">
      <AuthInput
          v-for="(el, index) in inputs"
          :key="index"
          :name="el.vmod"
          v-model:value="el.value"
          v-model:error="el.error"

          ref="authInput"
          :inputInfo="el"
      ></AuthInput>

      <div class="btn_send_block">
        <div v-if="loginError" class="error_mes">{{ loginError }}</div>
        <!--        <BlueBtn class="disable" :arrow="true">Войти</BlueBtn>-->
        <FillBtn @click="enterClick" :arrow="true">Войти</FillBtn>
      </div>

    </form>

    <div class="btn_link_box">
      <div class="btn_link" @click="$router.push('/recover')">Забыли свой пароль?</div>
      <div class="btn_link" @click="$router.push('/register')">Регистрация</div>
    </div>

  </div>
</template>

<script>
import {mapMutations, mapActions, mapState} from 'vuex'

import AuthInput from "@/components/ui/input/AuthInput";
import PageHeader from "@/components/main/PageHeader";
import FillBtn from "@/components/ui/btn/FillBtn";

export default {
  name: "AuthPage",
  components: {
    PageHeader,
    AuthInput,
    FillBtn
  },
  data() {
    return {
      errors: [],
      inputs: [
        {
          f_icon: require('@/assets/icon/form/mail.svg'),
          title: 'E-mail',
          l_icon: '',
          vmod: 'mail',
          value: '',
          error: null,
        },
        {
          f_icon: require('@/assets/icon/form/pass.svg'),
          title: 'Пароль',
          l_icon: require('@/assets/icon/form/eye.svg'),
          vmod: 'pass',
          value: '',
          error: null,
        },
      ],
      error: null,
      allowFlag: []
    }
  },

  methods: {
    ...mapMutations({
      setLoginData: 'auth/setLoginData',
    }),
    ...mapActions({
      authRequest: 'auth/authRequest',
    }),

    async enterClick() {

      this.errors = []

      this.$refs.authInput.forEach((el, index) =>{

        console.log('el', el.inputInfo)

        if(el.inputInfo.error) this.errors.push(el.inputInfo.error)
        if(!el.inputInfo.value) {
          // вбиваем ошибки незаполненых полей
          this.inputs[index].error = 'Введите ' + el.inputInfo.title
          this.errors.push(this.inputs[index].error)
        } else {
          // вбиваем данные авторизации если не пришли ошибки
          if(!el.inputInfo.error){
            this.inputs[index].error = ''
            this.loginData[el.inputInfo.vmod] = el.inputInfo.value
          }
        }
      })

      if (this.errors.length === 0) {
        // запрос авторизации
        this.loginData['type'] = 'newLogin'

        await this.authRequest()

        if (!this.loginError) this.$router.push('/main')
      }

    },

    formSubmit(e) {
      e.preventDefault()
    },

  },

  computed: {
    ...mapState({
      loginData: state => state.auth.loginData,
      loginError: state => state.auth.loginError,
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
    display: flex;
    margin-top: 55px;
    flex-direction: column;
    gap: 24px;
  }

  .btn_send_block {
    position: relative;

    .error_mes {
      position: absolute;
      left: 10px;
      top: -22px;
      color: #FF6262;
    }

  }

  .btn_link_box {
    margin-top: 13px;
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-wrap: nowrap;

    .btn_link {
      display: inline-block;
      margin-bottom: 8px;
      text-decoration: underline;
      color: @darkbg;
      cursor: pointer;
    }
  }

  .disable {
    background: #8A8A8E;
    cursor: not-allowed;
  }

  .privacy {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
    bottom: 45px;
  }
}
</style>