<template>
  <div class="exist" v-if="dataBlock.exist">
    <PreLoader v-if="loader"></PreLoader>
    <SendSuccess v-if="success" :closeSuccess="closeSuccess"></SendSuccess>
    <div v-else class="wrapper" :class="{'admin' : role === 'admin'}">
      <div class="title_block">
        <div class="title" :class="{'blur': !active}">
          {{ dataBlock.title }}. Прогноз
        </div>

        <div class="btn_box">
          <div class="check_box">
            <div class="fill_mark" v-if="dragData.length === dataBlock.count"></div>
          </div>
          <div class="close" @click="active = !active">
            <span :class="{'active': active}">V</span>
          </div>
        </div>

      </div>
      <div class="data_block" v-if="active">
        <div class="title_block">

          <div class="reset" @click="resetData">Сбросить</div>

          <div class="title_count" v-if="dragData.length !==dataBlock.count">Осталось выбрать еще
            {{ dataBlock.count - dragData.length }}
          </div>
          <div class="title_count" v-else>Выбрано {{ dataBlock.count }} гонщиков</div>
        </div>

        <div class="btn_block" v-if="dragData.length === dataBlock.count">
          <div class="write_wrapper" :class="{'fill' : raceInfo.fill}">
            <div class="write" v-if="raceInfo.fill">Заполнено:</div>
            <div class="write" v-else>Не заполнено</div>
            <div class="write_date" v-if="raceInfo.fill">12.04 22:12</div>
          </div>

          <div class="send fill" v-if="raceInfo.fill" @click="sendPrognosis">Изменить</div>
          <div class="send" v-else @click="sendPrognosis">Отправить</div>
        </div>

        <div class="drag_block">
          <div class="race_list">
            <RaceListItem
                v-for="el in racers"
                :item="el"
                :key="el.ID"
                :ref="'el'+el.ID"
                draggable="true"
                @dragstart="onDragStart($event, el.ID)"
                :onMoveRight="onMoveRight"
            ></RaceListItem>
          </div>
          <div class="race_list_disable" v-if="dragData.length === dataBlock.count"></div>
          <div class="result_list"
               @drop="onDrop($event)"
               @dragover.prevent
               @dragenter.prevent
          >
            <DragResultList
                v-for="(id, index) in dragData"
                :key="id"
                :item="racers[id]"
                :place="index"
                :deleteElement="deleteElement"
            ></DragResultList>
          </div>
        </div>


      </div>
    </div>
  </div>
</template>

<script>
import RaceListItem from "@/components/race/RaceListItem";
import DragResultList from "@/components/race/DragResultList";
import {mapActions, mapState} from "vuex";
import SendSuccess from "@/components/main/SendSuccess";
import PreLoader from "@/components/main/PreLoader";

export default {
  name: "RacerSelectBlock",
  props: {
    racers: {
      type: Object
    },
    dataBlock: {
      type: Object
    },
    raceInfo: {
      type: Object
    },
    role: {
      type: String
    }
  },
  components: {
    RaceListItem,
    DragResultList,
    SendSuccess,
    PreLoader
  },
  data() {
    return {
      urlImg: 'https://prognos9ys.ru/',
      dragData: [],
      active: this.dataBlock.active ?? false,
      loader: false,
      success: false,
      sendData: {}
    }
  },

  mounted() {
    this.$nextTick(function () {
      this.dataBlock.data.forEach((el, id)=>
      {
        this.onMoveRight(id, el)
      })
    })
  },

  methods: {
    ...mapActions({
      sendPognosisData: 'race/sendPognosisData',
      setEventResult: 'admin/setRaceResult',
    }),
    onDragStart(e, index) {
      e.dataTransfer.dropEffect = 'move'
      e.dataTransfer.effectAllowed = 'move'
      e.dataTransfer.setData('itemId', index.toString())

      setTimeout(() => {
        e.target.style.visibility = 'hidden'
      }, 0)
      // e.target.style.opacity = '1'
      e.target.classList.add('drag')
    },

    closeSuccess() {
      this.success = false
    },

    onDrop(e) {
      const itemId = parseInt(e.dataTransfer.getData('itemId'))

      this.dragData.push(itemId)
    },

    recoverElem(e) {
      console.log('recover', e.dataTransfer.getData('itemId'))
      // const id = parseInt(e.dataTransfer.getData('itemId'))
      // this.$refs['el'+id][0].$el.style.visibility = 'visible'
    },

    resetData() {
      Object.keys(this.racers).forEach(id => {
        this.$refs['el' + id][0].$el.style.visibility = 'visible'
      })
      this.dragData = []
    },

    onMoveRight(e, id) {
      this.dragData.push(parseInt(id))

      this.$refs['el' + id][0].$el.style.visibility = 'hidden'

    },

    deleteElement(index, id) {
      delete this.dragData[index];

      this.$refs['el' + id][0].$el.style.visibility = 'visible'

      this.dragData = this.dragData.filter(i => i !== undefined)

    },

    async sendPrognosis() {

      this.loader = true

      this.sendData = this.raceInfo

      this.sendData['data'] =  this.dragData
      this.sendData['race_id'] = this.raceInfo.race_id
      this.sendData['type'] = this.dataBlock.type

      if (this.role === 'admin') {

        this.sendData['role'] = this.role

        this.adminQueryEvent['info'] = this.sendData

        await this.setEventResult()
        if (this.sensSuccess) this.success = true

      } else {

        this.queryEvent['info'] = this.sendData

        await this.sendPognosisData()
        if (this.prognosisSuccess) this.success = true

      }
      this.loader = false

      this.active = false

    }
  },
  computed: {
    ...mapState({
      queryEvent: state => state.race.queryEvent,
      prognosisSuccess: state => state.race.sendSuccess,

      adminQueryEvent: state => state.admin.queryEvent,
      sensSuccess: state => state.admin.sendSuccess
    })
  }
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.wrapper {
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;

  .title_block {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    gap: 4px;
  }

  .title {
    .shadow_inset;
  }

  .blur {
    color: @hockei;
  }

  .btn_box {
    display: flex;
    flex-direction: row;
    gap: 4px;

    .check_box {
      position: relative;
      .shadow_inset;
      max-width: 24px;
      height: 24px;
      width: 24px;

      .fill_mark {
        position: relative;
        width: 20px;
        height: 10px;

        border-left: 2px solid @colorText2;
        border-bottom: 2px solid @colorText2;

        left: 50%;

        transform: rotate(-45deg) translate(-50%, -50%);
      }
    }

    .close {
      display: flex;
      flex-direction: column;
      justify-content: center;
      max-width: 24px;
      height: 24px;
      width: 24px;
      background: @valleyball;
      padding: 2px 2px;
      border-radius: 3px;
      cursor: pointer;
      .shadow_template;
    }
  }


  .active {
    transform: rotate(180deg);
  }

}

.data_block {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex-wrap: nowrap;

  .title_block {
    display: flex;
    flex-direction: row;
    justify-content: space-between;

    .title_count {
      display: flex;
      align-items: center;
      flex-direction: row;
      width: 100%;
      .shadow_inset;
      font-size: 11px;
      color: @maxGreen;

    }

    .reset {
      display: flex;
      flex-direction: column;
      justify-content: center;
      background: @dark;
      color: @colorText;
      cursor: pointer;
      .shadow_template;
      padding: 2px 2px;
      font-size: 10px;
      border-radius: 3px;
      text-align: center;
      border: 1px solid transparent;
      text-decoration: none;
      width: 76%;
      max-width: 75px;
    }
  }
}


.drag_block {
  position: relative;
  display: flex;
  flex-direction: row;
  gap: 4px;
  flex-wrap: nowrap;
}

.race_list {
  width: 45%;
  display: flex;
  flex-direction: column;

  gap: 2px;
}

.race_list_disable {
  top: 0;
  left: 0;

  position: absolute;
  width: 45%;
  height: 100%;
  background: @maxdarkgrey;
  opacity: 0.15;
}

.result_list {
  width: 55%;
  display: flex;
  flex-direction: column;

  gap: 2px;

  .shadow_inset;

  min-height: 120px;
  padding-bottom: 30px;
}

.btn_block {
  display: flex;
  flex-direction: row;

  justify-content: space-between;

  .write_wrapper {
    display: flex;
    flex-direction: row;
    gap: 4px;
    background: @YesWrite;
    color: @colorText;
    padding: 2px;
    border-radius: 5px;
    font-size: 12px;

    .write, .write_date {
      .shadow_inset;
    }
  }

  .send {
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: @colorText2;
    color: @colorText;
    cursor: pointer;
    .shadow_template;
    padding: 2px 2px;
    font-size: 10px;
    border-radius: 3px;
    text-align: center;
    border: 1px solid transparent;
    text-decoration: none;
    width: 76%;
    max-width: 75px;
  }

  .fill {
    background: @NoWrite;
  }
}

.admin {
  background: @red;
}
</style>