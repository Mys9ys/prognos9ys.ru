<template>
  <div class="rules_block">
    <div class="section_title">Правила и инструкции</div>

    <div class="rule_section">
      <div class="rule_heading">Прогнозы</div>
      <p class="rule_text">
        Ставки и баллы за прогнозы — ядро игры. Бонусы усадьбы и фарма на них не влияют.
      </p>
    </div>

    <div class="rule_section">
      <div class="rule_heading">Экономика</div>
      <p class="rule_text">
        Прогнобаксы (🪙) — основная валюта ставок и торговли. Рублиусы (💎) — премиум-валюта,
        покупка в лавке казны и банке (не на бирже).
      </p>
    </div>

    <div class="rule_section">
      <div class="rule_heading">Банк и займы</div>
      <p class="rule_text">
        Вклад и займ — на 5 матчей выбранного турнира. Займ фиксированный: 50 🪙, к возврату с процентами.
        Досрочное погашение — с выплатой всех процентов.
      </p>
    </div>

    <div class="rule_section">
      <div class="rule_heading">Казна и биржа</div>
      <p class="rule_text">
        Лавка казны — сундуки ЧМ и премиум. Биржа — торговля предметами из инвентаря.
        Гос. вклад поддержки — 500 🪙 в ликвидность банка, проценты в казну после 5 туров.
      </p>
    </div>

    <div class="rule_section">
      <div class="rule_heading">Фарм (скоро)</div>
      <p class="rule_text">
        На гос. делянке можно добывать ресурсы между матчами: для себя (в инвентарь) или на казну за фиксированную оплату за заход.
      </p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ProfileRulesBlock',
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.rules_block {
  margin-top: 10px;
  .shadow_inset;
  padding: 8px;
  border-radius: 4px;
  text-align: left;
}

.section_title {
  font-size: 13px;
  color: @orange;
  margin-bottom: 8px;
}

.rule_section {
  margin-bottom: 10px;

  &:last-child {
    margin-bottom: 0;
  }
}

.rule_heading {
  font-size: 12px;
  font-weight: 700;
  color: @colorText;
  margin-bottom: 4px;
}

.rule_text {
  font-size: 12px;
  color: @colorBlur;
  line-height: 1.4;
  margin: 0;
}
</style>
