<template>
  <div class="input_wrapper"
       :class="{wrapper_error: error || inputInfo.error, wrapper_active : activeBorder}"
       @click="clickActiveInput">
    <div class="input_wrapper_inner">
      <div v-if="error || inputInfo.error" class="error_mes">{{ error || inputInfo.error }}</div>
      <div class="first_icon">
        <img :src="inputInfo.f_icon" alt="">
      </div>
      <div class="placeholder">{{ inputInfo.title }}</div>
      <input v-if="activeInput"
             :type="inputInfo.l_icon && !openPass ? 'password' : 'text'"
             v-focus
             class="input"
             @focusout="focusOut(inputInfo.title)"
             v-model="inputText"
             @input="inputChange($event.target.value)"
             @error="error || inputInfo.error"
      >
      <div class="last_icon" v-if="inputInfo.l_icon" @click="openPassword">
        <img :src="inputInfo.l_icon" alt="">
      </div>
    </div>
  </div>
</template>

<script>
import {mapActions, mapState} from "vuex";

export default {
  name: "AuthInput",
  props: {
    inputInfo: {
      type: Object
    }
  },
  data() {
    return {
      activeBorder: this.inputInfo.value ? true : false,
      activeInput: this.inputInfo.value ? true : false,
      inputText: this.inputInfo.value ?? '',
      openPass: false,
      error: this.inputInfo.error ?? ''
    }
  },
  methods: {

    ...mapActions({
      checkUniqValueRequest: 'reg/checkUniqValueRequest',
    }),

    clickActiveInput() {//Поднимаем плайсхолдер и окрашиваем окантовку инпута
      this.activeInput = true
      this.activeBorder = true
      this.error = null
    },

    focusOut(type) {

      if (!this.inputText) {
        this.activeInput = false
        this.error = 'Введите ' + type
        this.$emit('update:error', this.error)

      } else {

        // обнуляем ошибки для всех типов полей
        this.error = null
        this.$emit('update:error', this.error)

        if (type === 'E-mail') {
          if (!this.emailValidate(this.inputText)) {
            this.error = 'Укажите корректный ' + type
          } else {
            this.error = ''

            if (this.$router.currentRoute.value.path === '/register') {
              this.checkValueUniq(this.inputInfo.vmod, this.inputText)
            }
          }
          this.$emit('update:error', this.error)
        }

        if (type === 'Мобильный телефон') {
          if (this.inputText.length !== 16) {
            this.error = 'Ошибка в номере'
          } else {
            this.error = ''

            if (this.$router.currentRoute.value.path === '/register') {
              this.checkValueUniq(this.inputInfo.vmod, this.inputText)
            }
          }
          this.$emit('update:error', this.error)
        }

        if (type === 'Ф.И.О.') {
          if (this.inputText.length < 4 || this.inputText.indexOf(' ') < 0) {
            this.error = 'Введите фамилию и имя'
          } else {
            this.error = ''
          }
          this.$emit('update:error', this.error)
        }

        if (type === 'Пароль' || type === 'Повторите пароль') {
          if (this.inputText.length < 6) {
            this.error = 'Пароль должен быть больше 6 символов'
          } else {
            this.error = ''
          }
          this.$emit('update:error', this.error)
        }

        if(type === 'Проверочный код'){
          if(this.inputText.length !== 7){
            this.error = 'Код должен быть 6 символов'
          } else {
            this.error = ''
          }
          this.$emit('update:error', this.error)
        }

      }
    },
    openPassword() {
      this.openPass = !this.openPass
    },
    inputChange(value) {
      // включаем маску на номер
      if (this.inputInfo.vmod === 'phone') {
        this.inputText = value = this.replaceNumberForInput(value)
      }

      // маска на проверочный код
      if(this.inputInfo.vmod === 'code'){
        this.inputText = value = this.recoveryCodeValidate(value)
      }

      this.$emit('update:value', value)
    },

    async checkValueUniq(name, value) {// проверка обязательных полей на уникальность (тел+майл)
      this.checkUniqArray.name = name
      this.checkUniqArray.value = value

      this.checkUniqError.name = ''

      await this.checkUniqValueRequest()

      if (this.checkUniqError.name === name) {
        this.error = this.checkUniqError.mes
        this.$emit('update:error', this.error)
      }

    },

    emailValidate(email) { // проверка правила e-mail
      var re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
      return re.test(email);
    },

    replaceNumberForInput(value) {// маска на номер телефона
      if (!value) return

      let val = ''
      const x = value.replace(/\D/g, '').match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,4})/)

      if (x[1] === '7' || x[1] === '8') {
        x[1] = '+7'
      }

      val = !x[3] ? x[1] + x[2] : x[1] + '(' + x[2] + ') ' + x[3] + (x[4] ? '-' + x[4] : '')

      return val
    },

    recoveryCodeValidate(value){
      if(!value) return

      let val = ''
      const x = value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})/)

      val = x[1] + '-' + x[2]

      return val
    }

  },

  computed: {
    ...mapState({
      checkUniqArray: state => state.reg.checkUniqArray,
      checkUniqError: state => state.reg.checkUniqError,
    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.input_wrapper {
  position: relative;
  height: 60px;
  padding: 3px;

  background: @DarkColorBG;

  /* Серый */
  border-radius: 18px;

  &.wrapper_active {
    justify-content: space-between;
    background: @YesWrite;
  }

  &.wrapper_error {
    background: @warning;
  }

  .error_mes {
    position: absolute;
    left: 10px;
    top: -22px;
    color: @warning;
  }

  .input_wrapper_inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px 24px 8px 47px;
    border-radius: 18px;
    height: 54px;
    box-shadow: inset 0 2px 10px 1px rgba(0, 0, 0, .3), inset 0 0 0 60px rgba(0, 0, 0, .3), 0 1px rgba(255, 255, 255, .08);



    .first_icon {
      position: absolute;
      left: 18px;
      top: 50%;
      transform: translateY(-50%);
    }

    .last_icon {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
    }

    .placeholder {
      font-weight: 400;
      font-size: 14px;
      line-height: 116%;
      /* or 16px */

      display: flex;
      align-items: center;

      color: @colorText;
      background: transparent;

      /* Inside auto layout */

      flex: none;
      order: 0;
      align-self: stretch;
      flex-grow: 0;
    }

    .input {
      width: 100%;

      font-weight: 500;
      font-size: 14px;
      line-height: 112%;
      /* or 16px */

      display: flex;
      align-items: center;
      letter-spacing: 0.02em;
      //font-feature-settings: 'salt' on, 'liga' off;

      /* Черный */
      background: transparent;
      color: @color;

      border: none;
      //border-bottom: 1px solid #8A8A8E;
      &:active, &:focus {
        outline: none;
      }
    }
  }
}
</style>