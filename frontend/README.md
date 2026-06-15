# Мобильное приложение (Vue SPA)

Исходники SPA для `/mob_app/`. Сборка деплоится в корень сайта: `../mob_app/`.

## Установка

```bash
cd frontend
npm install
```

## Разработка

```bash
npm run serve
```

Для локальной разработки откройте сайт Bitrix (например `https://prognos9ys/`) — API-URL в `src/store/config.js` строятся от `window.location.origin`.

## Сборка и деплой

```bash
# из корня репозитория
local\mob_app_ajax\deploy_mob_app.bat
```

Скрипт: `npm run build` → копирует `frontend/dist` в `mob_app/` + legacy ajax из `local/mob_app_ajax/ajax/`.

## API

Bitrix Engine actions вызываются через `src/api/bitrixClient.js`.  
См. `.cursor/rules/bitrix-engine-actions.mdc` в корне репозитория.
