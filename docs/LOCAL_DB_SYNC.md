# Синхронизация БД: бой → локалка

Инструкция для периодического обновления локальной копии данными с production.  
Код берём из git, **данные** — из дампа MySQL.

> Дампы **не коммитим** в git. Храним только в `.osp/backup/db/`.

---

## Когда делать

- Перед крупными тестами (экономика, прогнозы, модерация, новые фичи)
- Когда локалка «отстаёт» по пользователям, матчам, кошелькам
- Раз в 1–2 недели или по необходимости

---

## Настройки (один раз)

Файл `.osp/env.ini`:

```ini
DB_SYNC_PROD_HOST = prognos9ys.ru
DB_SYNC_LOCAL_HOST = prognos9ys
DB_SYNC_LOCAL_SCHEME = http
PROD_SSH_USER = mys9ys9ka
PROD_SSH_HOST = mys9ys9ka.beget.tech
; MYSQL_BIN = D:\OSPanel\modules\MySQL-8.0\bin
```

- `DB_SYNC_LOCAL_HOST` — как открываете сайт в OSPanel (обычно `http://prognos9ys`)
- `MYSQL_BIN` — раскомментировать, если импорт не находит mysql

---

## Шаг 1. Дамп на бою

В **веб-SSH Beget** (или SSH, когда настроите):

```bash
cd ~/prognos9ys.ru/public_html
git pull
php7.4 local/tools/prod_db_dump.php
```

Результат: `.osp/backup/db/prognos9ys_YYYYMMDD_HHMMSS.sql.gz`

Проверка:

```bash
ls -lh .osp/backup/db/
```

---

## Шаг 2. Скачать дамп на Windows

Папка на локалке:

```
D:\OSPanel\home\prognos9ys\.osp\backup\db\
```

### Способ A — файловый менеджер Beget (надёжно)

1. Beget → **Файловый менеджер**
2. `prognos9ys.ru/public_html/.osp/backup/db/`
3. Скачать `prognos9ys_*.sql.gz`
4. Положить в `.osp\backup\db\` на локалке

### Способ B — scp (когда включён внешний SSH в Beget)

```powershell
mkdir D:\OSPanel\home\prognos9ys\.osp\backup\db -Force
local\tools\download_prod_db.bat prognos9ys_YYYYMMDD_HHMMSS.sql.gz
```

Или вручную:

```powershell
scp mys9ys9ka@mys9ys9ka.beget.tech:~/prognos9ys.ru/public_html/.osp/backup/db/prognos9ys_*.sql.gz .osp\backup\db\
```

> `crown` — внутреннее имя сервера Beget, **с Windows не работает**. Используйте `mys9ys9ka.beget.tech`.

### Способ C — FTP/SFTP (FileZilla)

Хост `mys9ys9ka.beget.tech`, логин/пароль из раздела **FTP** в Beget.  
Тот же путь к файлу в `public_html/.osp/backup/db/`.

---

## Шаг 3. Импорт на локалке

Из корня проекта:

```bat
cd D:\OSPanel\home\prognos9ys
local\tools\local_db_import.bat .osp\backup\db\prognos9ys_YYYYMMDD_HHMMSS.sql.gz --confirm --sanitize
```

**Сначала превью** (ничего не меняет):

```bat
local\tools\local_db_import.bat .osp\backup\db\prognos9ys_YYYYMMDD_HHMMSS.sql.gz --dry-run
```

### Что делает импорт

1. Пересоздаёт локальную БД (`bitrix/.settings.php`)
2. Импортирует дамп (`.sql` или `.sql.gz`)
3. Меняет URL: `prognos9ys.ru` → `http://prognos9ys`
4. С `--sanitize`: отключает cron-агенты и SMTP (чтобы локалка ничего не слала)
5. Чистит кэш Bitrix

Импорт ~10–50 МБ обычно **1–5 минут**. Дождитесь строки `Готово.`

### Если bat не находит PHP

```bat
D:\OSPanel\modules\PHP-7.4\php.exe local\tools\local_db_import.php .osp\backup\db\ИМЯ_ФАЙЛА.sql.gz --confirm --sanitize
```

---

## Шаг 4. Проверка

1. Открыть `http://prognos9ys` (или ваш `DB_SYNC_LOCAL_HOST`)
2. **Ctrl+F5** (жёсткое обновление)
3. Залогиниться своим аккаунтом с боя (пароль тот же, что на проде)

---

## Что **не** синхронизируется

| Что | Примечание |
|-----|------------|
| `/upload/` | Картинки, аватарки — в git нет. Для UI можно докачать вручную или жить без части картинок |
| Код | Только через `git pull` / ваша ветка |
| Секреты `.env` | Локальные, отдельно |

Для тестов логики (прогнозы, банки, казна) **достаточно дампа БД**.

---

## Быстрая шпаргалка

```
БОЙ:     git pull → php7.4 local/tools/prod_db_dump.php
СКАЧАТЬ: Beget файловый менеджер → .osp\backup\db\
ЛОКАЛКА: local\tools\local_db_import.bat ...sql.gz --confirm --sanitize
```

---

## Частые проблемы

| Симптом | Решение |
|---------|---------|
| `php.exe not found` | Запуск через `D:\OSPanel\modules\PHP-7.4\php.exe` или поправить bat |
| `mysql client not found` | В `.osp/env.ini` указать `MYSQL_BIN = D:\OSPanel\modules\MySQL-8.0\bin` |
| `Connection closed` при ssh с Windows | Внешний SSH в Beget не включён → файловый менеджер / FTP |
| Сайт редиректит на prognos9ys.ru | Повторить импорт или проверить `DB_SYNC_LOCAL_HOST` в env.ini |
| Пустые аватарки | Нормально без синхронизации `/upload/` |

---

## Файлы инструментов

| Файл | Где запускать |
|------|----------------|
| `local/tools/prod_db_dump.php` | Бой (crown) |
| `local/tools/local_db_import.bat` | Windows / OSPanel |
| `local/tools/download_prod_db.bat` | Windows (когда SSH работает) |
| `local/tools/db_sync_lib.php` | Общая логика (не запускать вручную) |

---

## Безопасность

- Дампы содержат **реальные данные** пользователей — не выкладывать, не коммитить
- На локалке всегда использовать `--sanitize`
- На локалке **не** запускать seed-скрипты, бьющие в `https://prognos9ys.ru`, без `--dry-run`

---

*Последнее обновление: июнь 2026*
