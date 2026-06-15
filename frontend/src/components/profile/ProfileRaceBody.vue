<template>
  <div class="body_wrapper">
    <RaceHeader
        :info="item.f1races">
    </RaceHeader>
    <div class="stage_wrapper">
      <RaceStageInfo
          v-for="(el, index) in item.f1races.stage"
          :key="index"
          :item="el"
      ></RaceStageInfo>
    </div>

  </div>
</template>

<script>
import RaceHeader from "@/components/race/RaceHeader";
import RaceStageInfo from "@/components/race/RaceStageInfo";

export default {
  name: "ProfileRaceBody",
  components: {
    RaceHeader,
    RaceStageInfo
  },

  props: {
    item: {
      type: Object
    }
  },
}
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.body_wrapper {
  display: flex;
  flex-direction: column;
  flex-wrap: wrap;
  gap: 4px;
  background: @DarkColorBG;
  color: @colorText;
  padding: 4px;
  border-radius: 5px;
}

.stage_wrapper{
  display: flex;
  flex-direction: column;
  flex-wrap: revert;
  gap: 4px;
}
</style>