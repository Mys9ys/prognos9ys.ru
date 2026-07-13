# AGENTS.md

## Cursor Cloud specific instructions

This repo is a **1C-Bitrix CMS (PHP 7.4) monolith** with a decoupled **Vue 3 SPA** in `frontend/` that builds into `mob_app/`.

### What can and cannot run in the cloud VM
- **The Vue frontend (`frontend/`) is the only part that runs standalone here.** Node + npm are preinstalled; install/lint/build/serve all work. The startup update script runs `npm install --prefix frontend`.
- **The PHP/Bitrix backend cannot run in this environment.** The Bitrix core (`/bitrix/`), `/upload/`, Composer `vendor/`, and the MySQL database are gitignored / not present, and PHP + Composer + MySQL are not installed. Every PHP entry point (`index.php`, `p/*`, `mob_app/ajax/*`, `local/modules/prognos9ys.main/*`) needs the proprietary Bitrix core plus a populated DB, so backend / end-to-end API testing is not possible here. Local dev of the backend is documented for Windows/OSPanel in `docs/LOCAL_DB_SYNC.md`.

### Frontend dev (commands live in `frontend/package.json`, `frontend/README.md`)
- Run from `frontend/`: `npm run serve` (dev server), `npm run build` (prod bundle to `frontend/dist/`, gitignored), `npm run lint`.
- Dev server serves the SPA at `http://localhost:8080/mob_app/` (Vue CLI `publicPath: '/mob_app/'`, history routing). Network host is unavailable in the VM; use `localhost`.
- Production deploy copies `dist/` into `mob_app/` via `local/mob_app_ajax/deploy_mob_app.bat` (Windows/OSPanel only) and ships via `git pull` on the server — not runnable here.

### Non-obvious gotchas
- Route `/` redirects to `/catalog`, and most pages call the Bitrix backend, so they show loaders/errors without a live backend (expected in this VM). API base URLs derive from `window.location.origin` and point at `/bitrix/services/main/ajax.php` (`frontend/src/store/config.js`).
- **Fully client-side pages work offline** with no backend — e.g. the guide at `/mob_app/faq` and article pages `/mob_app/faq/<slug>` (rendered from `src/config/guideArticles.js`). Use these to sanity-check the SPA.
- `npm run lint` exits non-zero due to **3 pre-existing** `vue/valid-template-root` errors (empty template roots in `ProfilePrognosisBlock.vue`, `RaceOneHeader.vue`, `LoaderMini.vue`). These are not introduced by env setup.
- Vue Engine action naming conventions for the backend API are documented in `.cursor/rules/bitrix-engine-actions.mdc`.
