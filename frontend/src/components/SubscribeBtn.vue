<template>
  <div v-if="!subscribeToken" class="subscribe_btn" @click="subscribeActivate">Подписаться на рассылку</div>
</template>

<script>
import {mapActions, mapState} from "vuex";
// import {firebase} from 'https://www.gstatic.com/firebasejs/3.6.8/firebase-messaging.js';

export default {
  name: "SubscribeBtn",
  data() {
    return {
      messaging: '',
      firebase: ''
    }
  },


  methods: {
    ...mapActions({
      setSubscribeRule: 'subscribe/setSubscribeRule',
    }),
    // async subscribeActivate(){
    //   this.subscribeData.token = this.token
    //   await this.setSubscribeRule()
    // }

    subscribeActivate() {
      this.firebase.initializeApp({
        messagingSenderId: '475334260660'
      })

      if ('Notification' in window) {
        this.messaging = this.firebase.messaging();

        // пользователь уже разрешил получение уведомлений
        // подписываем на уведомления если ещё не подписали
        if (Notification.permission === 'granted') {
          this.subscribe();
        }

        // по клику, запрашиваем у пользователя разрешение на уведомления
        // и подписываем его

      }

    },

    subscribe() {
      // запрашиваем разрешение на получение уведомлений
      this.messaging.requestPermission()
          .then(function () {
            // получаем ID устройства
            this.messaging.getToken()
                .then(function (currentToken) {
                  console.log(currentToken);

                  if (currentToken) {
                    this.sendTokenToServer(currentToken);
                  } else {
                    console.warn('Не удалось получить токен.');
                    this.setTokenSentToServer(false);
                  }
                })
                .catch(function (err) {
                  console.warn('При получении токена произошла ошибка.', err);
                  this.setTokenSentToServer(false);
                });
          })
          .catch(function (err) {
            console.warn('Не удалось получить разрешение на показ уведомлений.', err);
          });
    },

    // отправка ID на сервер
    sendTokenToServer(currentToken) {
      if (!this.isTokenSentToServer(currentToken)) {
        console.log('Отправка токена на сервер...');

        // var url = '/test/brauser_mes/save.php'; // адрес скрипта на сервере который сохраняет ID устройства
        // $.post(url, {
        //   token: currentToken
        // });

        this.setTokenSentToServer(currentToken);
      } else {
        console.log('Токен уже отправлен на сервер.');
      }
    },

// используем localStorage для отметки того,
// что пользователь уже подписался на уведомления
    isTokenSentToServer(currentToken) {
      return window.localStorage.getItem('sentFirebaseMessagingToken') == currentToken;
    },

    setTokenSentToServer(currentToken) {
      window.localStorage.setItem(
          'sentFirebaseMessagingToken',
          currentToken ? currentToken : ''
      );
    }


  },
  computed: {
    ...mapState({
      subscribeToken: state => state.subscribe.subscribeToken,
      subscribeData: state => state.subscribe.subscribeData,
      token: state => state.auth.authData.token,
    })
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.subscribe_btn {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  background: #228b22;
  color: #fff;
  cursor: pointer;
  .shadow_template;
  padding: 3px 12px;
  border-radius: 5px;
  text-decoration: none;
  margin-bottom: 4px;
}
</style>