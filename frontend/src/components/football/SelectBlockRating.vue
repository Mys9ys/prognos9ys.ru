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
          <template v-for="(item, rowIndex) in buildDisplayRows(data)" :key="item.key || rowKey(item, rowIndex)">
            <tr v-if="item._type === 'gap'" class="rating_gap_row">
              <td colspan="4">
                <span class="rating_gap_label">··· {{ item.fromPlace + 1 }}–{{ item.toPlace - 1 }} ···</span>
              </td>
            </tr>
            <tr v-else :class="{ rating_viewer_row: item.isViewer }">
              <td class="pr_table_col">{{ item.place }}</td>
              <td class="pr_table_col" v-if="diffValue(item) === 0"><div class="zero_diff">–</div></td>
              <td class="pr_table_col" v-else-if="diffValue(item) > 0"><div class="plus_diff">{{ diffValue(item) }}</div></td>
              <td class="pr_table_col" v-else-if="0 > diffValue(item)"><div class="minus_diff">{{ diffValue(item) }}</div></td>
              <td class="pr_table_col user_cell" v-if="item.user">
              <span class="user_ava">
                <img :src="url + item.user.img" alt="" v-if="item.user.img">
                <img src="@/assets/img/no_logo.png" alt="" v-else>
              </span>
              <div class="user_nick">
                {{ item.user.name }}
                <span v-if="item.isViewer" class="viewer_badge">вы</span>
              </div>
              <div class="user_actions" v-if="item.user.id">
                <span
                    v-if="canImpersonate"
                    class="user_enter"
                    title="Войти как пользователь"
                    @click.stop="loginAsUser(item.user.id)"
                >
                  <AppIcon name="exit_door" :size="14" />
                </span>
                <span class="user_info" @click.stop="$router.push('/profile/' + item.user.id)">i</span>
              </div>
            </td>
            <td class="pr_table_col user_cell" v-else>
              <div class="user_nick">—</div>
            </td>
            <td class="pr_table_col score">{{ item.score }}</td>
            </tr>
          </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex'
import AppIcon from '@/components/ui/AppIcon.vue'

export default {
  name: "SelectBlockRating",
  components: { AppIcon },
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
      impersonateError: '',
    }
  },

  computed: {
    ...mapState({
      userInfo: state => state.auth.userInfo,
    }),
    canImpersonate() {
      const role = this.userInfo?.role
      return !!this.userInfo?.can_impersonate
          || role === 'admin'
          || role === 'super_moder'
    },
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
    ...mapActions({
      impersonateStart: 'auth/impersonateStart',
    }),

    async loginAsUser(userId) {
      this.impersonateError = ''
      try {
        await this.impersonateStart(userId)
        this.$router.push('/').then(() => { this.$router.go() })
      } catch (e) {
        this.impersonateError = e.message || 'Не удалось войти'
        console.log('loginAsUser error', e)
      }
    },

    rowKey(el, rowIndex) {
      return el?.user?.id || el?.id || rowIndex
    },

    diffValue(row) {
      return Number(row?.diff ?? 0);
    },

    viewerUserId() {
      return Number(this.userInfo?.ID || 0);
    },

    buildDisplayRows(rows) {
      const list = Array.isArray(rows) ? rows : [];
      const viewerId = this.viewerUserId;
      const result = [];

      list.forEach((row, index) => {
        const curPlace = Number(row?.place ?? 0);
        const userId = Number(row?.user?.id ?? 0);
        const isViewer = viewerId > 0 && userId === viewerId;

        // Разрыв только перед строкой зрителя, если его место вне топ-N (добавлено API после обрезки).
        if (index > 0 && isViewer) {
          const prevPlace = Number(list[index - 1]?.place ?? 0);
          if (curPlace - prevPlace > 1) {
            result.push({
              _type: 'gap',
              fromPlace: prevPlace,
              toPlace: curPlace,
              key: `gap-viewer-${prevPlace}-${curPlace}`,
            });
          }
        }

        result.push({
          ...row,
          _type: 'row',
          isViewer,
          key: `row-${userId || index}-${curPlace}`,
        });
      });

      return result;
    },

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
      flex: 1;
      min-width: 0;
      text-align: left;
    }

    .user_actions{
      display: flex;
      flex-direction: row;
      gap: 3px;
      flex-shrink: 0;
    }

    .user_enter{
      cursor: pointer;
      display: flex;
      flex-direction: row;
      justify-content: center;
      align-items: center;
      width: 24px;
      height: 24px;
      font-size: 12px;
      line-height: 1;
      border: 2px solid @yellow;
      border-radius: 5px;
      background: rgba(0, 0, 0, 0.15);
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

.rating_gap_row {
  td {
    padding: 6px 2px;
    text-align: center;
  }

  .rating_gap_label {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 5px;
    font-size: 12px;
    color: @pearl;
    letter-spacing: 1px;
    .shadow_inset;
  }
}

.rating_viewer_row {
  td {
    background: fade(@YesWrite, 12%);
  }

  .user_nick {
    font-weight: 700;
  }

  .viewer_badge {
    display: inline-block;
    margin-left: 4px;
    padding: 0 4px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 700;
    color: @DarkColorBG;
    background: @YesWrite2;
    vertical-align: middle;
  }
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