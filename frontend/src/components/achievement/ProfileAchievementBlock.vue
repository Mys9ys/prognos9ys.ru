<template>
  <h3>Скоро будут</h3>
</template>

<script>
export default {
  name: "ProfileAchievementBlock",
  data(){
    return {
      achivki: {
        'total': {'title': 'Ранг', 'level': 0, },
        'football_prog_count': {'title': 'Футбольные прогнозы'},
        'race_prog': {}
      },
      achivki_temp: {
        'total': {
          0: {'name': 'Заглянувший', 'class' : 'blur'},
          1: {'name': 'Новичок', 'class' : 'blur'},
          5: {'name': 'Тестирующий', 'class' : 'blur'},
          10: {'name': 'Познающий', 'class' : 'blur'},
          25: {'name': 'Увлекшийся', 'class' : 'blur'},
          31: {'name': 'Чемпион Евро', 'class' : 'blur'},
          50: {'name': 'Болельщик', 'class' : 'blur'},
          64: {'name': 'Чемпион Мира', 'class' : 'blur'},
          100: {'name': 'Заядлый', 'class' : 'blur'},
          200: {'name': 'Познавший', 'class' : 'blur'},
        }
      }
    }
  }
}
</script>

<style scoped>

</style>