<template>
  <div class="humor_wrapper block_wrapper">
    <div class="title_wrapper humor">
      <div class="title">Минутка юмора</div>
    </div>
    <HumorElement></HumorElement>
    <div class="btn_box">
      <div class="btn_all humor" @click="$router.push('/humor')">Весь юмор
        <img src="@/assets/icon/pagination/right.svg" alt=""></div>
    </div>
  </div>
</template>

<script>
import HumorElement from "@/components/ui/HumorElement";

export default {
  name: "HumorBlock",
  components: {
    HumorElement
  }

}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.title_wrapper {
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  margin-top: 25px;
  text-align: left;
  margin-bottom: 10px;

  .title {
    .shadow_inset;
  }
}

.empty_wrapper {
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
  margin-top: 8px;
  text-align: left;
  margin-bottom: 10px;
}

.empty {
  .shadow_inset;
  font-size: 12px;
}

.btn_box {
  text-align: right;
}

.btn_all {

  display: inline-block;
  background: @colorText2;
  color: @colorText;
  cursor: pointer;
  .shadow_template;
  padding: 2px 6px;
  font-size: 14px;
  border-radius: 3px;
  text-align: center;
  border: 1px solid transparent;
  text-decoration: none;
  margin-top: 10px;

  img {
    margin-left: 12px;
  }

  &:hover {
    opacity: 0.8;
  }
}
.humor{
  background: @cubersport;
}
</style>