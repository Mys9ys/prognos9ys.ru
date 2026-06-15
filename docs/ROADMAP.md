# Дорожная карта prognos9ys

Актуально на: **2026-06-15**

Файл для отслеживания плана развития. Отмечаем `[x]` — сделано, `[ ]` — в работе или в очереди.

---

## Уже сделано (инфраструктура и ЧМ-2026)

- [x] Модуль `prognos9ys.main` — Bitrix Engine API (Controller / Service)
- [x] Vue SPA (`frontend/`) → сборка в `mob_app/`, деплой через `deploy_mob_app.bat`
- [x] `mob_app` в git — деплой на прод через `git pull`
- [x] Legacy ajax-обёртки в `local/mob_app_ajax/ajax/`
- [x] Правило action-имён: `.cursor/rules/bitrix-engine-actions.mdc`
- [x] Футбольные рейтинги — `FootballRatingCalculator` (срезы по турам сохранены)
- [x] Страница матча: таблица результата, цвета, «Прогноз не заполнен»
- [x] Отправка прогноза через Bitrix API (валидация, ошибки, `TokenAuthFilter` под PHP 7.4)
- [x] `Prognos9ysAuthClass` — `role: admin` в auth
- [x] Upsert результатов пересчёта прогноза (`CalcFootballPrognosisResult`)
- [x] Таблица чемпионата: группы **A–L** (`ChampionshipFootballTable` + `FootballTable.vue`)
- [x] Обзор **третьих мест** между группами (`thirdPlaces`)
- [x] Установка модуля на прод (`install_module.php`, `php7.4`)

### Vue на Bitrix API (`USE_BITRIX_API: true`)

| Область | Store | Controller | Глубина |
|---------|-------|------------|---------|
| Профиль | `profileModule` | `ProfileController` | обёртка legacy |
| Каталог | `catalogModule` | `CatalogController` | обёртка legacy |
| Футбол: матчи, матч, прогноз | `footballModule` | `FootballController` | обёртка legacy |
| Футбольные рейтинги | `ratingModule` | `RatingController` | **полный перенос** |
| Таблица чемпионата | `championshipModule` | `ChampionshipController` | обёртка legacy |
| Рейтинги F1 | `ratingModule` | `RatingController` | обёртка legacy |

---

## Футбол — функциональность

| Статус | Задача | Сложн. | Примечание |
|--------|--------|--------|------------|
| [x] | Обзор 3-х мест (3-е место в каждой группе) | 2 | API + блок в `FootballTable.vue` |
| [x] | Матчи внутри групп (A, B, C…) | 3 | раскрывашка под таблицей, `groupMatches` в API |
| [x] | Рейтинг — сортировка/фильтр: активные / прошедшие | 2 | переключатель на `RatingPage.vue` |
| [x] | Сборники рейтинга (open/closed/private) | 3 | HL + `RatingSetController`, UI на рейтингах |
| [ ] | В списке матчей — лучшие прогнозы / все прогнозы | 3 | порог баллов, лента |
| [ ] | Союзы / гильдии / компании — выбор отображаемых | 3 | один user в нескольких группах, без инвайтов |
| [ ] | Добавление в друзья | 4 | |
| [ ] | Лайки в Highload | 3 | |
| [ ] | Доработать исправление результатов (админка) | 3 | |
| [ ] | Полный перенос на Model / Controller / Service (без legacy-обёрток) | 5 | рейтинги уже перенесены; таблица, матчи — в очереди |

### Уточнения (когда берём в работу)

- **Матчи в группах:** раскрывашка под таблицей или отдельная вкладка?
- **Гильдии:** фильтр в рейтинге и/или в таблице?
- **Публичные страницы:** какие данные скрывать (чужие прогнозы, личное)?

---

## Все виды спорта

| Статус | Задача | Сложн. | Примечание |
|--------|--------|--------|------------|
| [ ] | Публичные страницы без авторизации | 4 | таблица, рейтинг, матчи — read-only, минимум личных данных |
| [ ] | Проработать заполнение результатов (единый сценарий) | 4 | футбол, гонки, КС |

---

## CS / КС

| Статус | Задача | Сложн. |
|--------|--------|--------|
| [ ] | Разработать метрики | 3 |
| [ ] | Проверить формирование таблиц | 2 |

---

## Гонки (F1)

| Статус | Задача | Сложн. | Примечание |
|--------|--------|--------|------------|
| [ ] | Перенос на Bitrix API (как футбол) | 3 | сейчас обёртка только для рейтингов |
| [ ] | `RaceNearestCome`, админка результатов | 3 | в legacy, не в API |

---

## Ещё не на Bitrix API (legacy → модуль)

| Класс / endpoint | Назначение |
|------------------|------------|
| `Prognos9ysMainPageInfo` | главная |
| `Prognos9ysHumorHandler` | юмор |
| `NewsHandlerClass` | новости |
| `FootballNearestCome` | ближайшие матчи |
| `RaceNearestCome` | ближайшие гонки |
| Админка: `FootballSetResult`, `CalcFootballPrognosisResult`, race-аналоги | результаты и пересчёт |

---

## Рекомендуемый порядок (время ЧМ-2026)

1. [x] Группы A–L + третьи места
2. [x] Матчи в группах
3. [x] Рейтинг: активные / прошедшие
4. [x] Сборники рейтинга (open/closed/private)
5. [ ] Лучшие прогнозы в ленте матчей
6. [ ] Публичные страницы
7. [ ] Гильдии / друзья / лайки
8. [ ] Полный MVC (фоном)

---

## Тесты и утилиты

| Файл | Назначение |
|------|------------|
| `local/modules/prognos9ys.main/test_championship_table.php` | таблица групп, event 63849 |
| `local/modules/prognos9ys.main/test_football_ratings_compare.php` | сравнение рейтингов legacy vs calculator |
| `local/modules/prognos9ys.main/test_match_display.php` | матч, прогноз, результат |
| `local/modules/prognos9ys.main/test_send_prognosis.php` | отправка прогноза |
| `local/modules/prognos9ys.main/install_rating_sets_hl.php` | HL-блоки сборников рейтинга |
| `local/modules/prognos9ys.main/install_module.php` | установка модуля (`php7.4`) |
| `local/modules/prognos9ys.main/verify_module.php` | проверка модуля |

---

## Как обновлять этот файл

После завершения задачи: `[ ]` → `[x]`, при необходимости — строка в блок «Уже сделано» и дата в шапке.
