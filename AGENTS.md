# AGENTS.md

## Cursor Cloud specific instructions

This repo is a **1C-Bitrix CMS (PHP 7.4) monolith** with a decoupled **Vue 3 SPA** in `frontend/`.

### What can and cannot run in the cloud VM
- **Vue frontend (`frontend/`) is the only part that runs standalone here.** Install/lint/build/serve all work with Node + npm (already installed).
- **The PHP/Bitrix backend cannot run in this environment.** The Bitrix core (`/bitrix/`), `/vendor/`, `/upload/`, and the MySQL database are gitignored / not present, and PHP/Composer are not installed. All PHP entry points (`index.php`, `p/*`, `mob_app/ajax/*`, `local/modules/prognos9ys.main/*`) require the proprietary Bitrix core + a populated DB. Do not attempt end-to-end backend testing here.

### Frontend dev (see `frontend/package.json` and `frontend/README.md`)
- Dependencies are installed by the startup update script (`npm install --prefix frontend`).
- Commands (run in `frontend/`): `npm run serve` (dev server), `npm run build` (prod bundle), `npm run lint`.
- The dev server serves the SPA at `http://localhost:8080/mob_app/` (Vue CLI `publicPath: /mob_app/`, web-history routing).

### Non-obvious gotchas
- Route `/` redirects to `/catalog`, and most pages call the Bitrix backend, so they show loaders/errors without a live backend. **Public, fully client-side pages render offline** — e.g. the guide at `/mob_app/faq` (renders from `src/config/guideArticles.js`). Use these to sanity-check the SPA without a backend.
- API base URLs are derived from `window.location.origin` (`src/store/config.js`), pointing at `/bitrix/services/main/ajax.php` (Bitrix Engine Actions). With no backend, those calls fail — expected in this VM.
- `npm run lint` currently reports 3 pre-existing `vue/valid-template-root` errors (empty templates in existing components); these are not introduced by env setup.
- `npm run build` writes to `frontend/dist/` (gitignored). Production deploy copies `dist/` into `mob_app/` via `local/mob_app_ajax/deploy_mob_app.bat` (Windows/OSPanel only) and ships via git pull — not runnable here.
