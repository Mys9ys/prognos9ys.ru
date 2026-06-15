<template>
  <div class="accordion kg_description_block" id="accordionExample">
    <div class="accordion-item">
      <h6 class="accordion-header" :id="'heading'+info.title.number+'game'">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                :data-bs-target="'#collapse'+info.title.number+'game'" aria-expanded="false" :aria-controls="'collapse'+info.title.number+'game'">Подробнее
        </button>
      </h6>
      <div :id="'collapse'+info.title.number+'game'" class="accordion-collapse collapse" :aria-labelledby="'heading'+info.title.number+'game'"
           data-bs-parent="#accordionExample">
        <div class="accordion-body">
          <table class="table table-striped-columns kvn_table">
            <thead>
            <tr>
              <th scope="col">Команда</th>
              <th scope="col"><span class="kvn_span_show">1</span> <span class="kvn_span_hide">Приветствие</span></th>
              <th scope="col"><span class="kvn_span_show">2</span> <span class="kvn_span_hide">Биатлон</span></th>
              <th scope="col"><span class="kvn_span_show">3</span> <span class="kvn_span_hide">Домашка</span></th>
              <th scope="col"><span class="kvn_span_show">S</span> <span class="kvn_span_hide">Результат</span></th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(team, key) in info.teams" :key="key">
              <td>{{team.name}}</td>
              <td>{{team.stage1}}</td>
              <td>{{team.stage2}}</td>
              <td>{{team.stage3}}</td>
              <td>{{team.result}}</td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "GameTeams",
  props: {
    info: {
      type: Object
    }
  }
}
</script>

<style lang="less" scoped>
@blockBG: #253133;
@colorText: #fff;
@colorText2: #228B22;
@qatar: #8a1538;
@nowrite: rgb(13, 202, 240);
@yeswrite: #146c43;
@color: rgb(240, 240, 240);
@colorBlur: #A6B2AA;

@darkbg: rgb(25, 25, 25);
.kg_description_block {
  margin: 3px 0px;

  .accordion-item {
    border: none;
  }

  .accordion-button {
    background: @darkbg;
    padding: 3px 5px;
    color: @colorBlur;
    box-shadow: inset 0 2px 10px 1px rgba(0, 0, 0, .3), inset 0 0 0 2px rgba(0, 0, 0, .3), 0 1px rgba(255, 255, 255, .08);

    &:after {
      background-image: none;
      font-family: "FontAwesome";
      content: "\F078";
      color: @colorBlur;
    }
  }

  .table-striped-columns > :not(caption) > tr > :nth-child(2n) {
    color: @colorBlur;
    background-color: rgb(50, 50, 50);
  }

  .table > :not(caption) > * > * {
    padding: 3px;
  }

  .kvn_table {
    color: @colorBlur;
    background: @blockBG;

    .kvn_span_hide {
      display: none;
      padding: 3px 0;
    }
    tr{
      th:first-child, td:first-child{
        text-align: left;
      }
    }
  }

  .accordion-body {
    padding: 0px;
  }
}
</style>