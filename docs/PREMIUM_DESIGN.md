# Premium — дизайн и статус реализации

Документ фиксирует согласованную механику Premium и порядок внедрения.

## Ценность Premium

- **Очередь работ** (бесконечная): добыча, крафт, выставление лотов — только с активным Premium.
- **Офлайн-обработка** очереди через cron (этап 2).
- **Биржа**: до **30** лотов, срок **7** дней, комиссия **5%** (без Premium: 10 / 3 / 20%).
- **Приоритет лотов** продавцов с Premium в каталоге (при равной цене — раньше по времени).
- **Авто-сбор XP** с матчей (без кнопки в шапке).
- **Прогноз** (этап 5): кнопка «Рандом» (без лимита перебросов), правка прогноза в первые **30** мин матча (счёт, карточки, пенальти).

## Активация

- Свитки `premium_scroll` в инвентаре (1 / 3 / 5 суток).
- Активация **суммирует** срок в `UF_PREMIUM_UNTIL` на кошельке.
- UI:
  - **Шапка**: под прогнобаксами — остаток Premium + `+` (активировать следующий свиток FIFO).
  - **Инвентарь**: как сундуки — «Активировать» / «Все» с предупреждением.

### API

- `GameController::activatePremiumScrollAction(days, activateAll)`
  - `days=0` — один свиток FIFO (любая длительность).
  - `days=1|3|5` — конкретный тип; `activateAll=1` — все свитки этого типа.

### Конфиг

- `PremiumEconomyConfig.php` — лимиты биржи, комиссия, окно правки прогноза.
- `PremiumService.php` — `hasActivePremium`, `getSummary`, `activateScrolls`, сортировка лотов.

## Очередь работ (этап 2 — реализовано)

| Тип задачи | Описание |
|------------|----------|
| `farm` | добыча / переработка (`treasury` / `self`) |
| `album_craft` | крафт альбомов |
| `exchange_list` | выставить лот на биржу |

Макросы Premium (этапы 0–3): планировщик цепочек, резерв 🪙, казна на бирже, модератор «Продать крафт» — см. git log.

**Этап 4 (в работе):** докупка коллекций до 16/32/48 с биржи (`AlbumCollectionBuyService`) — готово; модератор «Крафт ×5» — готово.

Правила:

- Только Premium.
- Валидация при постановке и при старте (ресурсы, деньги для self).
- Фейл → пропуск, очередь идёт дальше.
- **Журнал** во вкладке «Запуски» (очередь + история).
- Cron: `local/modules/prognos9ys.main/cron_process_premium_work_queue.php` (каждые 1–5 мин).

### API

- `GameController::enqueuePremiumWorkAction(taskType, payloadJson)`
- `GameController::cancelPremiumWorkAction(taskId)`
- Состояние очереди в `getFarmState` → `farm.work_queue`

### HL

- `prognos9ys_premium_work_queue` — `upgrade_premium_work_queue_hl.php`

### UI

- `ProfileFarmBlock.vue` — вкладка «Запуски»: очередь, журнал, кнопки «★ В очередь»

## Биржа — реализовано (этап 1)

- `ExchangeService::hasActivePremium` — через `PremiumService`.
- `commission_percent` в state — персональный для продавца.
- При покупке комиссия считается по Premium **продавца**.
- Сортировка лотов: цена → Premium-продавец → дата создания (раньше).

## Авто XP — реализовано (этап 1)

- В `ExperienceService::getPendingSummaryForUser` при активном Premium вызывается `claimAll`.
- Кнопка «+XP» в шапке скрыта, пока Premium активен.

## Прогноз (этап 5 — в планах)

- **Рандом**: полный прогноз, неограниченные перебросы до «Отправить».
- **Правка после старта** (только Premium, ≤30 мин): счёт, карточки, пенальти (раз за матч метрики).

## Файлы этапа 1

| Компонент | Путь |
|-----------|------|
| Конфиг | `PremiumEconomyConfig.php` |
| Сервис | `PremiumService.php` |
| HL поле | `UF_PREMIUM_UNTIL` на wallet (`upgradeWalletPremiumHl`) |
| API | `activatePremiumScroll` |
| Шапка | `HeaderBlock.vue` |
| Инвентарь | `ProfileInventoryBlock.vue` |
| Биржа | `ExchangeService.php`, `ExchangeConfig.php` |

## Деплой

После изменений во `frontend/`: `local\mob_app_ajax\deploy_mob_app.bat`  
На бой: git commit → push → `git pull` на сервере.

HL-поле на существующем проекте: при первом запросе срабатывает `ensurePremiumWalletSchema()` (или `install_game_economy_hl.php`).
