<template>
  <div class="wrapper">
    <PageHeader class="header">Восстановление пароля</PageHeader>

    <div class="title">Восстановление пароля по E-Mail</div>
    <form action="" class="form">
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
        <div v-if="recoverCodeError.error" class="error_mes">{{ recoverCodeError.mes }}</div>
        <FillBtn v-if="actionSend">Получить код</FillBtn>
        <TransparentBtn v-else @click="enterClick">Получить код</TransparentBtn>
      </div>


    </form>
    <div class="recover_wrapper" v-if="actionSend">
      <div class="recover_mes">
        Проверочный код отправлен
      </div>

      <FormCode :userId="this.userId"></FormCode>
    </div>

  </div>
</template>

<script>

import AuthInput from "@/components/ui/input/AuthInput";
import FormCode from "@/components/ui/form/FormCode";
import {mapActions, mapState} from "vuex";
import PageHeader from "@/components/main/PageHeader";
import FillBtn from "@/components/ui/btn/FillBtn";
import TransparentBtn from "@/components/ui/btn/TransparentBtn";

export default {
  name: "RecoverMail",
  components: {
    FillBtn,
    PageHeader,
    AuthInput,
    FormCode,
    TransparentBtn
  },
  data() {
    return {
      errors: [],
      inputs: [
        {f_icon: require('@/assets/icon/form/mail.svg'), title: 'E-mail', l_icon: '', vmod: 'mail', error: '', value: ''},
      ],
      actionSend: false,
    }
  },
  methods: {
    ...mapActions({
      recoverCodeRequest: 'recover/recoverCodeRequest',
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
            this.recoverCodeData[el.inputInfo.vmod] = el.inputInfo.value
          }
        }
      })

      if (this.errors.length === 0) {
        // запрос авторизации
        this.recoverCodeData['type'] = 'recoverMail'

        await this.recoverCodeRequest()

        if(this.sendCodeSuccess) this.actionSend = true

      }

    },

    formSubmit(e) {
      e.preventDefault()
    },
  },
  computed: {
    ...mapState({
      recoverCodeData: state => state.recover.recoverCodeData,
      recoverCodeError: state => state.recover.recoverCodeError,
      sendCodeSuccess: state => state.recover.sendCodeSuccess,
      userId: state => state.recover.userID,
    })
  },
}
</script>

<style lang="less" scoped>
@import "@/assets/css/variables.less";
.wrapper {
  .wrapper_template;
  padding-top: 45px;
  .title{
    margin-top: 16px;
    margin-bottom: 25px;
    font-weight: 400;
    font-size: 14px;
    line-height: 22px;

    text-align: left;
    /* identical to box height, or 157% */

    letter-spacing: -0.408px;

    /* Черный */

    color: #000000;

  }

  .form {
    margin-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .recover_mes{
    margin-top: 4px;
    font-weight: 400;
    font-size: 12px;
    line-height: 22px;
    /* identical to box height, or 183% */

    text-align: center;
    letter-spacing: -0.41px;

    /* Корп цвет */

    color: @darkbg;
  }


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