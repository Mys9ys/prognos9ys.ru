<template>
  <div class="faq_wrapper">
    <PageHeader class="header">Правила</PageHeader>
    <ProfileRulesBlock />
  </div>
</template>

<script>
import PageHeader from '@/components/main/PageHeader';
import ProfileRulesBlock from '@/components/profile/ProfileRulesBlock.vue';

export default {
  name: 'FaqPage',
  components: {
    PageHeader,
    ProfileRulesBlock,
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.faq_wrapper {
  padding: 0 4px 12px;
}
</style>
