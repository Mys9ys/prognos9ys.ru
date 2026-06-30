# WC26 Assets Handover (Pennants + Scarves)

Документ фиксирует наработки по коллекционкам ЧМ-2026, чтобы следующая модель или разработчик могли продолжить без потери контекста.

## 1) Что уже сделано

### Вымпелы (практически production-ready)

- В проекте внедрена коллекция вымпелов `pennant_wc26_{slug}` для 48 сборных.
- Финальный рабочий подход: AI-генерация в стиле референсов `pennant_site` / `pennant_chm2026`.
- Ключевой визуал:
  - флаг как полотно вымпела;
  - золотая рамка/штанга в стиле существующих игровых иконок;
  - один упрощенный золотой кубок по центру размером около `1/3` внутреннего поля;
  - без текста на самом вымпеле.
- UI подключение вымпелов реализовано через динамический импорт в `frontend/src/config/wc26PennantIcons.js`.

### Шарфы (концепт подтвержден, серия в прогрессе)

- Зафиксирован стиль **Premium Matchday**:
  - дуга шарфа;
  - плотная золотая оплетка;
  - крупное название страны по центру (EN);
  - слева национальный символ;
  - справа знак `26` в цветах флага + кубок;
  - фон в виде шахматки (для ручного удаления/маски в Photoshop).
- Подтверждена удачная версия для Argentina `..._final_v1`.
- Сгенерированы также `BRA/FRA/JPN` версии `..._final_v1`.
- Новое требование пользователя: **убрать центральный символ/кубок** на шарфе (чтобы не дублировать правый блок `26+кубок`) — это целевой `v2`.

## 2) Источники правды по 48 сборным

- Backend (основная конфигурация):  
  `local/modules/prognos9ys.main/lib/Service/Game/Wc26CollectibleConfig.php`
- Frontend (сетка/лейблы):  
  `frontend/src/config/wc26Teams.js`
- Slug-нейминг:
  - вымпел: `pennant_wc26_{slug}`
  - шарф: `scarf_wc26_{slug}`

Важно: держать `slug`-списки синхронными в backend/frontend.

## 3) Файлы и директории пайплайна

### 3.1 Вымпелы

- Промпты и mapping `slug -> flagPrompt`:  
  `local/tools/pennant_ai_prompts.js`
- Импорт «сырых» AI PNG в фронтовые ассеты (без trim):  
  `local/tools/import_pennant_ai_raw.mjs`
- Опциональная постобработка (удаление шахматки, trim, square, resize):  
  `local/tools/process_pennant_ai_png.mjs`
- Финальные фронтовые ассеты:  
  `frontend/src/assets/collectibles/pennants/`
- Сырые AI-файлы (рабочее хранилище):  
  `local/tools/assets/pennants/ai/`

### 3.2 Шарфы

- Пилоты и текущие финальные рендеры:  
  `local/tools/output/scarfs/pilot/`
- На момент фиксации:
  - `scarf_wc26_arg_premium_final_v1.png`
  - `scarf_wc26_bra_premium_final_v1.png`
  - `scarf_wc26_fra_premium_final_v1.png`
  - `scarf_wc26_jpn_premium_final_v1.png`

## 4) Принятые решения по качеству PNG

- Для ручной доводки в Photoshop требуется **полный PNG без обрезки объекта**.
- Шахматный фон допустим и даже предпочтителен на этапе генерации (удобнее для пользователя).
- Auto-trim/автовыделение использовать только как fallback, когда нужно быстро сделать технический результат без ручной ретуши.

## 5) Текущая проблема/ограничение среды

- В текущем чате периодически недоступен инструмент генерации изображений (`GenerateImage is not available for the current selected model`).
- Это не проблема промптов, а ограничение выбранной модели/конфигурации.
- Практический вывод: для продолжения генерации открыть чат/модель с включенной image-generation.

## 6) Эталонный промпт для шарфов v2 (без центрального кубка)

```text
Premium Matchday football scarf, high-end collectible render, isolated on transparent-checkerboard background (for Photoshop cutout), no crop, full scarf visible.

Style and composition (strict):
- elegant upward arc scarf shape
- thick ornate braided gold border along the edges
- realistic knitted fabric texture, premium 3D lighting, sharp details
- long tassels/fringe on both ends in team colors
- center: full ENGLISH team name in large embossed metallic gold serif letters
- IMPORTANT: do NOT place any World Cup symbol/trophy/logo in the center under the team name (no center cup, no center emblem duplication)
- left end: national symbol/emblem of the team (clean premium interpretation)
- right end: stylized “26” in team flag colors + ONE small golden world cup trophy integrated only here
- no FIFA text anywhere
- no extra labels, no watermarks, no mockup background, no stadium
- keep checkerboard background visible
- deliver as single PNG image look (full object, not trimmed)

Team: {TEAM_NAME}
Country style cues: {FLAG_COLORS_AND_LAYOUT}
Left symbol: {NATIONAL_SYMBOL}
Right 26 colors: {RIGHT_26_COLORS}
```

## 7) Подставленные поля для текущих 4 стран (v2)

- Argentina:
  - `Team`: `ARGENTINA`
  - `Country style cues`: `sky blue / white / sky blue bands, subtle Sun of May motif`
  - `Left symbol`: `Sun of May / AFA-inspired motif`
  - `Right 26 colors`: `sky blue and white with gold accents`
- Brazil:
  - `Team`: `BRAZIL`
  - `Country style cues`: `green base with yellow diamond and blue circle references`
  - `Left symbol`: `CBF-inspired crest with five stars`
  - `Right 26 colors`: `green, yellow, blue`
- France:
  - `Team`: `FRANCE`
  - `Country style cues`: `vertical tricolor blue / white / red`
  - `Left symbol`: `Gallic rooster`
  - `Right 26 colors`: `blue, white, red`
- Japan:
  - `Team`: `JAPAN`
  - `Country style cues`: `white base with red sun disc motif`
  - `Left symbol`: `chrysanthemum / imperial seal inspired motif`
  - `Right 26 colors`: `white and red`

## 8) Как продолжить работу (чеклист)

1. Открыть image-enabled модель/чат.
2. Сгенерировать `v2` для `ARG/BRA/FRA/JPN` (проверка, что центр без кубка).
3. Утвердить визуал и прогнать все 48 стран в том же стиле.
4. Сложить PNG в согласованную директорию (обычно `local/tools/output/scarfs/...`).
5. Добавить импорт/конфиг шарфов на фронте (аналогично `wc26PennantIcons.js`).
6. После изменений в `frontend/` обязательно выполнить:
   - `local\mob_app_ajax\deploy_mob_app.bat`
7. Деплой на production только через git:
   - commit + push
   - на сервере `git pull`

## 9) Команды по вымпелам (уже рабочие)

- Импорт сырых AI-файлов:
  - `node local/tools/import_pennant_ai_raw.mjs`
  - `node local/tools/import_pennant_ai_raw.mjs --slug=ger`
- Опциональная постобработка:
  - `node local/tools/process_pennant_ai_png.mjs --pilot`
  - `node local/tools/process_pennant_ai_png.mjs <input.png> <output.png> [size]`

## 10) Риски и рекомендации

- Не смешивать «сырые для Photoshop» и «боевые оптимизированные» ассеты в одном этапе без явного флага процесса.
- Не переименовывать `slug`-коды: они завязаны на backend labels, item-коды и фронтовый маппинг.
- Для больших батчей шарфов сразу вести файл промптов наподобие `pennant_ai_prompts.js` (например `scarf_ai_prompts.js`) с единым шаблоном и подстановками.

---

Если продолжение делает другая модель: начать с раздела **8) Как продолжить работу**, затем свериться с `Wc26CollectibleConfig.php` и `wc26Teams.js`, и только после этого запускать batch-генерацию.
