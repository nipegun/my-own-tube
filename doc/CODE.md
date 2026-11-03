# MyOwnTube code guide

This CODE is also available in:\
[en-GB](CODE.en-GB.md), en-US, [es-AR](CODE.es-AR.md), [es-ES](CODE.es-ES.md)

This document is a surgical map of the current codebase. Paths are relative to the repository root, and symbol line numbers refer to the current source.

## Index

1. [Architecture and design decisions](#1-architecture-and-design-decisions)
2. [Module map](#2-module-map)
3. [Key symbol index](#3-key-symbol-index)
4. [Main flows](#4-main-flows)
5. [Entry points, routes, and commands](#5-entry-points-routes-and-commands)
6. [Impact analysis](#6-impact-analysis)
7. [Extension points](#7-extension-points)
8. [Data and security invariants](#8-data-and-security-invariants)
9. [Local development](#9-local-development)

## 1. Architecture and design decisions

MyOwnTube is a server-rendered PHP web application with separate presentation and application directories. Only `frontend/public/` is the HTTP document root. Its `.htaccess` sends every address that is not a real public file (`styles.css`, `ui.js`) to `frontend/public/index.php`, which hands the request to `backend/router.php`. HTML/SVG components, templates and translations live in `frontend/`; routing, controllers, form handlers and shared logic live in `backend/`. This keeps Apache/PHP deployment simple while no private source or runtime data is ever a public file.

**One route table.** `backend/inc/routes.php` is the single source of truth for every public address. `fRoutePages()` maps page names to clean paths (`/`, `/watch/{id}/`, `/user/dashboard/profile/`...), `fRouteSectionDefaults()` sends section roots (`/user/dashboard/`, `/admin/dashboard/`, `/auth/`) to their default tab, and `fRouteApi()` lists every API endpoint with its methods, handler, access level, fields and responses. The page controller, `fUrl()`, the API dispatcher and the OpenAPI document at `/api/doc/` all read these tables, so a new route is declared once. Former `/index.php?page=...` addresses answer with a 301 to the new path, and addresses without the final slash are canonicalised with a 301.

**Pages and dashboards.** Pages follow the endpoint rules: authentication at `/auth/login/`, `/auth/register/` and `/auth/recover/`; a user dashboard at `/user/dashboard/` with the `profile` and `account` tabs; an administrator dashboard at `/admin/dashboard/` with the `users` and `settings` tabs. Every tab has its own URL and the tab bar is rendered by `fDashboardTabs()` from `fRouteDashboardTabs()`. An anonymous request for a protected page is redirected to `/auth/login/?next=<requested path>` and returns there after signing in.

**API.** Everything that behaves as an API lives under `/api/`: form handlers (`/api/auth/*`, `/api/user/*`, `/api/admin/*`), the JSON progress endpoint, and the authenticated video stream and thumbnail. The handlers are the files in `backend/actions/` plus `backend/media.php` and `backend/thumb.php`; the router maps the clean API path to the file and injects path parameters such as `{id}` into `$_GET`, so the handlers keep their original structure. The router answers 404 for unknown API paths and 405 with `Allow` for unsupported methods. Form endpoints keep the post/redirect/get pattern: they validate the session CSRF token, store a translated notice and answer 303.

SQLite is the single metadata store. PDO prepared statements are centralised in `backend/inc/db.php`; WAL mode permits normal reads while short writes complete. Foreign keys are enabled on every application connection. The schema is created by `seed.php`, while idempotent compatibility migrations in `backend/inc/session.php` upgrade older installations during startup (including the rename of the default administrator address from `admin@mytube.home.arpa` to `admin@myowntube.home.arpa`).

Video bytes are not stored in SQLite. Each imported video has an original media file, an adjacent JSON sidecar, and normally a generated JPEG thumbnail under the video directory. SQLite stores catalogue and user state plus a safe relative `file_path` and an `is_available` flag. Folder sync hides rows whose files disappeared while preserving their user state, and reactivates them if the media returns.

Authentication uses PHP sessions (cookie `MYOWNTUBESESSID`, constant `cSessionName`). Passwords use Argon2id and older hashes are upgraded after a successful sign-in. Each session carries the account's database-backed `session_version`; password changes, administrator-set passwords and CLI resets increment it so stale sessions fail their next check. The application does not send email, so password recovery is either an administrator setting a new password from `/admin/dashboard/users/` or the server-side reset CLI; `/auth/recover/` explains both.

Internationalisation uses flat JSON key/value catalogues. English is the fallback; the user locale is stored on the account, while a site default applies to visitors and new accounts. All four catalogues must keep identical keys. The API reference at `/api/doc/` is technical documentation and is written in English only.

The installer is also the update mechanism. Apache listens directly on ports 80 (permanent redirect to HTTPS) and 443 (TLS), without a local HAProxy or PROXY protocol. The installer downloads through HTTPS, stops Apache before the SQLite backup, validates the backup, deploys through a staging directory, keeps media outside the code tree, validates Apache before restart, and retains rollback copies. Because it changes the global Apache MPM and PHP module, it refuses unrelated enabled sites and therefore targets a dedicated Apache instance. Code is immutable to the web user; only database, media and logs are writable. Apache serves the code from `/var/www/myowntube` (the `/opt/<user>` layout of the project rules applies only to services that are not Apache). An installation made before the rename (`/var/www/mytube`, `mytube.db`, `/root/app-web-*`) is migrated by the first run and its files are retired into the backup directory.

```text
frontend/
  public/        # index.php, styles.css, ui.js, .htaccess
  views/         # layout, page templates, API reference template
  components/    # HTML and SVG rendering
  lang/          # translated interface strings
backend/
  actions/       # API form and JSON handlers
  pages/         # page controllers and queries
  inc/           # shared logic: routes, OpenAPI, DB, session, i18n, videos
  tools/         # media import utilities
  db/            # private runtime database (myowntube.db)
  videos/        # private media directory or link
doc/
deploy/
```

## 2. Module map

| Module | Path | Responsibility | Depends on | Used by |
|---|---|---|---|---|
| Configuration | `backend/config.php` | Application identity, session cookie name, four ordered locales, page size, paths, security limits and administrator defaults | PHP runtime | All web and CLI entry points |
| Route tables | `backend/inc/routes.php` | Page paths, section defaults, legacy page names, dashboard tabs, API table, matching, `fUrl()`/`fApiUrl()` | None | Router, page controller, helpers, OpenAPI, views |
| HTTP dispatcher | `backend/router.php` | `/api/` dispatch with 404/405 handling and path parameters; everything else to the page controller | Route tables, handlers | Public bootstrap |
| Page front controller | `backend/index.php` | Security headers, legacy and slash redirects, section defaults, schema readiness, access control, page dispatch, 404 page, layout | All `inc` modules, `pages` | Browser page requests |
| Pages | `backend/pages/*.php` | Query and render one application screen (including `account`, `site-settings`, `recover`) | DB, session, helpers, components, i18n | `backend/index.php` |
| API handlers | `backend/actions/*.php` | Validate authenticated POST requests and mutate state | DB, session, helpers, i18n; videos where needed | HTML forms and watch progress JavaScript through `/api/...` |
| API reference | `backend/api-doc.php`, `backend/inc/openapi.php`, `frontend/views/api-doc.php` | OpenAPI 3.1 document and Swagger-style HTML generated from `fRouteApi()` | Route tables, helpers | `/api/doc/`, `/api/doc/openapi.json` |
| Database adapter | `backend/inc/db.php` | One PDO connection, SQLite pragmas, prepared-query helpers, private error logging | `backend/config.php`, PDO SQLite | Pages, handlers, auth, media, CLIs |
| Session and authentication | `backend/inc/session.php` | Cookie policy, CSRF, users, roles, administrator password reset, throttling, schema compatibility, guards, safe redirect targets | DB, config, route tables; i18n when loaded | All protected entry points |
| Internationalisation | `backend/inc/i18n.php` | Locale normalisation, catalogue loading, fallback and persistence | DB, session, JSON catalogues | Pages, handlers, shared UI |
| Video domain | `backend/inc/videos.php` | Safe paths, ffprobe validation, metadata, thumbnails and their API URL, import and folder sync | DB, session, FFmpeg tools | Upload/sync handlers, CLIs, media, components |
| UI components | `frontend/components/components.php` | Escaped video cards, rows, avatars, dashboard tabs and pagination | helpers, icons, videos, i18n | Pages |
| Application chrome | `frontend/components/chrome.php` | Top bar, sidebar, account menu (dashboards, language, sign-out), search | session, helpers, icons, i18n | `backend/index.php` |
| Icons | `frontend/components/icons.php` | Inline SVG icon functions | None | Chrome, components, pages |
| Helpers | `backend/inc/helpers.php` | Escaping, current page, bounded pagination and display formatting; loads the route tables | Route tables; i18n optionally | All rendering and redirects |
| Media gateway | `backend/media.php` | Authenticated GET/HEAD video streaming and single byte ranges | DB, session, helpers, videos | `/api/user/videos/{id}/stream` |
| Thumbnail gateway | `backend/thumb.php` | Authenticated GET/HEAD JPEG delivery | DB, session, helpers, videos | `/api/user/videos/{id}/thumbnail` |
| Locale catalogues | `frontend/lang/*.json` | Matching translated interface strings for every locale | JSON parser | `backend/inc/i18n.php` |
| Seeder | `backend/seed.php` | Empty production initialisation; explicit unavailable demo metadata; verified replacement backup | Config, PDO SQLite | Installer and administrator CLI |
| Maintenance CLIs | `backend/*-cli.php` | Secure legacy admin (and report the address rename), reset a password, synchronise media | Shared application modules | Installer and server operator |
| Apache policy | `frontend/public/.htaccess` | Deny private paths and dotfiles, route every non-file request to the front controller | Apache rewrite | Deployed website |
| Presentation | `frontend/public/styles.css` | Responsive layouts without horizontal overflow, system light/dark themes, API reference styles | Rendered class names | All pages |
| Browser navigation | `frontend/public/ui.js` | Mobile sidebar, backdrop, Escape and menu accessibility state | Native DOM and matchMedia | Layout |
| Public bootstrap | `frontend/public/index.php` | Hand every request to the private backend | `backend/router.php` | Apache and the development router |
| Templates | `frontend/views/*.php`, `frontend/views/pages/*.php` | Render prepared page data, shared layout, 404 and API reference | Frontend components and backend helpers | Page controllers |
| Navigation data | `backend/inc/navigation.php` | Query notifications and suggestions and resolve user navigation context | DB, session, i18n | `frontend/components/chrome.php` |
| Debian deployment | `deploy/install-update-reinstall-debian.sh` | Apache on 80/443 with TLS, protected log/credentials, domain detection, staged certificates, configuration rollback, migration and retirement of the `mytube` installation | Debian, apt, Apache, PHP, SQLite, curl, OpenSSL | Root server operator |
| Alpine deployment | `deploy/install-update-reinstall-alpine.sh` | Same transaction in sh/apk/PHP 8.4/OpenRC with a managed Apache main configuration and private sessions | Alpine main/community, Apache, PHP, SQLite, OpenSSL | Root server operator |
| Symlink helper | `backend/tools/generate_symlink.py` | Recursively link an external source tree into `/var/www/myowntube-videos` | Python 3.13+, filesystem | Server operator before sync |
| Local development router | `deploy/local-dev-router.php` | Reproduce the `.htaccess` behaviour (private-path denials, front controller) under PHP's built-in server | PHP development server | Local setup |

## 3. Key symbol index

| Symbol | File:line | What it does |
|---|---|---|
| `fRoutePages` | `backend/inc/routes.php:6` | Maps page names to clean paths |
| `fRouteSectionDefaults` | `backend/inc/routes.php:29` | Default tab of each section root |
| `fRouteLegacyPages` | `backend/inc/routes.php:40` | Translates former `?page=` names into current page names |
| `fRouteDashboardTabs` | `backend/inc/routes.php:60` | Tabs of the user and administrator dashboards |
| `fRouteApi` | `backend/inc/routes.php:73` | Every `/api/` endpoint with methods, handler, access, fields and responses |
| `fRouteMatchPage` | `backend/inc/routes.php:480` | Resolves a request path into a page name and `id` parameter |
| `fRouteMatchApi` | `backend/inc/routes.php:493` | Resolves an API path (with or without final slash) into its route |
| `fUrl` | `backend/inc/routes.php:514` | Builds the clean URL of a page, filling `{id}` and the query string |
| `fApiUrl` | `backend/inc/routes.php:532` | Fills `{id}` in an API path |
| `fRouteLegacyDestination` | `backend/inc/routes.php:541` | Destination of a former `/index.php?page=...` address |
| `fRouterPlainResponse` | `backend/router.php:5` | Plain-text 404/405/500 answers of the dispatcher |
| `fOpenApiSpec` | `backend/inc/openapi.php:38` | Builds the OpenAPI 3.1 document from `fRouteApi()` |
| `fDb` | `backend/inc/db.php:19` | Opens and caches PDO SQLite with foreign keys, WAL, timeout, and normal synchronous mode |
| `fQuery` | `backend/inc/db.php:45` | Executes a prepared statement and hides internal SQL errors from web users |
| `fE` | `backend/inc/helpers.php:5` | Escapes arbitrary output for an HTML text/attribute context |
| `fPaginate` | `backend/inc/helpers.php:18` | Counts filtered rows, clamps the page and fetches at most `cPageSize` entries |
| `fCsrfRequire` | `backend/inc/session.php:69` | Rejects a mutation whose session CSRF token is absent or invalid |
| `fAuthEnsureSchema` | `backend/inc/session.php:115` | Creates auth/settings tables and applies idempotent compatibility migrations |
| `fAuthEnsureDefaultAdminEmail` | `backend/inc/session.php:175` | Renames `admin@mytube.home.arpa` to the current default administrator address once |
| `fAuthEnsureVideoAvailability` | `backend/inc/session.php:248` | Adds availability and hides legacy rows without media paths without deleting user state |
| `fAuthMigrateUserTable` | `backend/inc/session.php:313` | Rebuilds legacy likes, Watch later or history tables with user ownership |
| `fAuthLoginAllowed` | `backend/inc/session.php:555` | Checks and expires the per-address login throttle window |
| `fAuthCreateUser` | `backend/inc/session.php:604` | Validates account data, hashes the password, and inserts a user |
| `fAuthLogin` | `backend/inc/session.php:668` | Verifies credentials, records failures, rehashes when needed, and rotates the session ID |
| `fAuthChangePassword` | `backend/inc/session.php:722` | Replaces the own password, rotates the current session and revokes the others |
| `fAuthAdminUpdateRole` | `backend/inc/session.php:772` | Changes a role without permitting removal of the final administrator |
| `fAuthAdminSetPassword` | `backend/inc/session.php:799` | Administrator sets another account's password and revokes its sessions |
| `fAuthSafeDestination` | `backend/inc/session.php:860` | Accepts only internal page paths as redirect targets (never `/api/`, schemes or hosts) |
| `fCurrentUser` | `backend/inc/session.php:888` | Loads the account and rejects a stale session version |
| `fRequireLogin` | `backend/inc/session.php:953` | Redirects anonymous requests to sign-in |
| `fRequireAdmin` | `backend/inc/session.php:960` | Enforces both authentication and administrator role |
| `fCurrentLocale` | `backend/inc/i18n.php:132` | Resolves user, session, and site locale in that order |
| `fT` | `backend/inc/i18n.php:162` | Retrieves a translated string with English fallback and parameter replacement |
| `fVideoThumbnailUrl` | `backend/inc/videos.php:118` | API URL of a video thumbnail when the JPEG exists |
| `fVideoPathFromFilePath` | `backend/inc/videos.php:126` | Resolves a catalogue path only inside the configured video directory |
| `fVideoSaveJson` | `backend/inc/videos.php:180` | Atomically writes a UTF-8 JSON sidecar through a temporary file and rename |
| `fVideoValidateFile` | `backend/inc/videos.php:356` | Enforces file size and requires ffprobe to find a real video stream |
| `fVideoImportJson` | `backend/inc/videos.php:450` | Normalises sidecar metadata and inserts or updates the matching catalogue row |
| `fVideosSyncFolder` | `backend/inc/videos.php:542` | Validates files, imports sidecars/thumbnails, and reconciles catalogue availability |
| `fChannelById` | `backend/inc/videos.php:644` | Caches channel records used by video rendering |
| `fIdSet` | `backend/inc/videos.php:651` | Loads the current user's liked or saved video IDs |
| `fNavigationData` | `backend/inc/navigation.php:3` | Prepares navigation data without HTML rendering |
| `fVideoCard` | `frontend/components/components.php:54` | Renders a reusable, escaped catalogue card |
| `fDashboardTabs` | `frontend/components/components.php:151` | Renders the tab bar of a dashboard with the active tab |
| `fPaginationRender` | `frontend/components/components.php:165` | Renders previous/next links with preserved filters |
| `fDetectDomain` | `deploy/install-update-reinstall-debian.sh:45` | `MYOWNTUBE_DOMAIN`, else the configured `ServerName`, else the default; the old default moves to the new one |
| `fAcquireLock` | `deploy/install-update-reinstall-debian.sh:94` | Excludes overlapping installation processes |
| `fActivateConfigFile` | `deploy/install-update-reinstall-debian.sh:106` | Replaces only changed managed configuration |
| `fPrepareLogs` | `deploy/install-update-reinstall-debian.sh:118` | Takes over `/root/app-web-*` and opens `/root/webapp-*` with mode 600 |
| `fBackupConfigFile` | `deploy/install-update-reinstall-debian.sh:131` | Journals a file or symlink before a deployment change |
| `fRestoreConfigFiles` | `deploy/install-update-reinstall-debian.sh:155` | Restores journalled configuration after a failure |
| `fValidateApacheIsolation` | `deploy/install-update-reinstall-debian.sh:258` | Refuses to change global Apache modules when unrelated sites are enabled |
| `fSaveCurrentData` | `deploy/install-update-reinstall-debian.sh:330` | Verified SQLite backup (current or previous installation) and video migration |
| `fCreateCertificate` | `deploy/install-update-reinstall-debian.sh:503` | Reuses a valid certificate (also the previous one) or stages and validates a new one |
| `fConfigureApache` | `deploy/install-update-reinstall-debian.sh:554` | Modules, `Listen 80`/`Listen 443`, the two VirtualHosts of the Apache template |
| `fRetireLegacyApacheSite` | `deploy/install-update-reinstall-debian.sh:671` | Disables and journals the `000-mytube` site |
| `fVerifyBackend` | `deploy/install-update-reinstall-debian.sh:721` | Verifies `https://<domain>/auth/login/` on port 443 with the installed certificate |
| `fRetireLegacyInstallation` | `deploy/install-update-reinstall-debian.sh:730` | Moves the files of the `mytube` installation into `previous-install-*` |
| `fMain` | `deploy/install-update-reinstall-debian.sh:833` | Orders validation, backup, deployment, configuration, seed/migration, restart and retirement |
| `fConfigureAlpineMain` | `deploy/install-update-reinstall-alpine.sh:600` | Builds the Alpine Apache main configuration without duplicate includes |
| `fRetireLegacyApacheFiles` | `deploy/install-update-reinstall-alpine.sh:747` | Journals and removes `mytube-site.conf` and `mytube-ports.conf` |
| `fMain` | `deploy/install-update-reinstall-alpine.sh:930` | Runs the Alpine installation/update transaction |
| `fMain` | `backend/tools/generate_symlink.py:93` | Validates CLI input, creates links, and returns a meaningful process status |

## 4. Main flows

### Page request

`GET /history/` → Apache `.htaccess` (not a file) → `frontend/public/index.php` → `backend/router.php` (not `/api/`) → `backend/index.php`: security headers → legacy `?page=` redirect, missing final slash redirect, section default redirect → database readiness → `fRouteMatchPage()` → `fAuthEnsureSchema()` and locale → access checks (public, signed-in, administrator) → `backend/pages/<page>.php` prepares data → `frontend/views/pages/<page>.php` renders → `frontend/views/layout.php` wraps it. An unknown path renders `frontend/views/page-not-found.php` with status 404.

### API request

`POST /api/user/likes/toggle` → `.htaccess` → `frontend/public/index.php` → `backend/router.php` → `fRouteMatchApi()` → method check (405 with `Allow` if wrong) → path parameters copied into `$_GET` → `require backend/actions/toggle-like.php` → login and CSRF guards → mutation → notice → 303 to the referring page.

### Sign-in

Anonymous `GET /playlists/` → 302 to `/auth/login/?next=%2Fplaylists%2F` → form → `POST /api/auth/login` → `fCsrfRequire()` → `fAuthLoginAllowed()` → `password_verify()` → failure counter or `fAuthStartSession()` (session ID rotation and stored `session_version`) → `fAuthSafeDestination(next)` → 303 to `/playlists/`.

### Password recovery

`/auth/login/` → **Forgotten your password?** → `/auth/recover/` (information only, the application sends no email) → an administrator opens `/admin/dashboard/users/` → `POST /api/admin/users/password` → `fAuthAdminSetPassword()` → Argon2id hash and `session_version + 1` → every session of that account becomes invalid. Without any administrator: `reset-admin-password-cli.php` from the server.

### Search and private history

Top-bar form → `POST /api/user/searches` → login/CSRF guards → bounded query → per-user history upsert → 303 to `/search/?q=…` → read-only result query. Result and suggestion GETs never change history.

### Password replacement

`/user/dashboard/account/` → `POST /api/user/password` → verify current password → Argon2id hash → `session_version + 1` → the current session is rotated and keeps working; every other session is revoked.

### Upload

`/upload/` → `POST /api/user/videos/upload` (multipart, Apache `LimitRequestBody 0` only here) → login and CSRF guards → extension/size checks → collision-free destination → `move_uploaded_file()` → `fVideoValidateFile()`/ffprobe → `fVideoCreateMetadata()` → atomic sidecar → `fVideoImportJson()` and thumbnail → 303 to `/watch/{id}/`. Any failure removes the video, sidecar and thumbnail created by that attempt.

### Folder synchronisation

`POST /api/admin/videos/sync` or `sync-videos-cli.php` → `fVideosSyncFolder()` → preflight `ffprobe` → snapshot existing file-backed IDs → enumerate allowed extensions → validate real streams → generate missing sidecars → `fVideoImportJson()` → generate/refresh thumbnails → log per-file failures → transactionally mark snapshot IDs that were not imported as unavailable. Rows and user state are never deleted.

### Playback and progress

`/watch/{id}/` → `<video src="/api/user/videos/{id}/stream">` → login guard and safe-path check → optional single range → session lock released → 1 MiB blocks. After the `playing` event, JavaScript posts the percentage with the CSRF token to `/api/user/history/progress`; a transaction inserts history and counts the view only on first insert, otherwise it updates progress. The response is JSON.

### Startup migration

Any application or maintenance entry point → `fAuthEnsureSchema()` → missing tables → default administrator address rename → settings → user locale and session version → video availability → uploads channel handle → administrator lookup → legacy private tables rebuilt with ownership → indexes → migration markers in `schema_migrations`.

### Install, update and migration (Debian and Alpine)

`fMain()` → root check and exclusive lock → `fPrepareLogs()` (takes over `/root/app-web-*`) → environment and isolation checks → packages and directories → download the `main` archive → stop Apache → `fSaveCurrentData()` (verified backup of `myowntube.db`, or of the previous `mytube.db`; videos moved from `/var/www/mytube-videos` with a link left behind) → staged deployment into `/var/www/myowntube` → `fConfigureApache()` (PHP ini, certificate, modules, ports 80/443, VirtualHosts, retirement of the previous site/ini, configtest) → seed or `secure-admin-cli.php` → enable and restart Apache → `fVerifyBackend()` → save credentials → commit → `fRetireLegacyInstallation()`. A failure before the commit restores the journalled configuration and the previous web tree and restarts Apache if it was running.

### Paginated catalogue and collections

Page controller → filtered count and ordered row query → `fPaginate()` clamps the requested page and applies the 24-row limit → card/row rendering → `fPaginationRender()` builds escaped clean URLs that keep category, search or playlist context. Comments use `comments_page`; other lists use `p`. Sorting includes a stable ID to prevent duplicates across pages.

## 5. Entry points, routes, and commands

### Pages

| Route/URL/command | Handler | File |
|---|---|---|
| `GET /` (`?cat=`, `?p=`) | Category catalogue | `backend/pages/home.php` |
| `GET /trending/` (`?tab=`) | Category-filtered trending list | `backend/pages/trending.php` |
| `GET /search/?q=…` | Read-only search results | `backend/pages/search.php` |
| `GET /watch/{id}/` (`?list=`, `?comment_sort=`, `?comments_page=`) | Player, actions and comments | `backend/pages/watch.php` |
| `GET /history/` | Current user's playback history | `backend/pages/history.php` |
| `GET /liked/` | Current user's likes | `backend/pages/liked.php` |
| `GET /watch-later/` | Current user's Watch later list | `backend/pages/watchlater.php` |
| `GET /playlists/` | Current user's playlists | `backend/pages/playlists.php` |
| `GET /playlists/{id}/` | Owned playlist details | `backend/pages/playlist.php` |
| `GET /upload/` | Upload form and videos folder | `backend/pages/upload.php` |
| `GET /auth/login/` (`?next=`) | Public sign-in form | `backend/pages/login.php` |
| `GET /auth/register/` | Registration form when enabled | `backend/pages/register.php` |
| `GET /auth/recover/` | Public password recovery information | `backend/pages/recover.php` |
| `GET /user/dashboard/` | 302 to `/user/dashboard/profile/` | `backend/index.php` |
| `GET /user/dashboard/profile/` | Profile tab | `backend/pages/profile.php` |
| `GET /user/dashboard/account/` | Account tab: language, password, search history | `backend/pages/account.php` |
| `GET /admin/dashboard/` | 302 to `/admin/dashboard/users/` | `backend/index.php` |
| `GET /admin/dashboard/users/` | Users tab | `backend/pages/users.php` |
| `GET /admin/dashboard/settings/` | Site settings tab | `backend/pages/site-settings.php` |
| `GET /index.php?page=…` | 301 to the current address | `backend/index.php` |

### API

Every endpoint below is declared in `fRouteApi()` and documented at `/api/doc/` (HTML) and `/api/doc/openapi.json`. Form endpoints accept POST only, require the CSRF token and answer 303; all except sign-in and registration require a signed-in user.

| Route/URL/command | Handler | File |
|---|---|---|
| `POST /api/auth/login` | Verify credentials and start the session | `backend/actions/login.php` |
| `POST /api/auth/register` | Create a standard user if registration is enabled | `backend/actions/register.php` |
| `POST /api/auth/logout` | Destroy and rotate the session | `backend/actions/logout.php` |
| `POST /api/user/profile` | Change display name | `backend/actions/update-profile.php` |
| `POST /api/user/password` | Verify and replace the current password | `backend/actions/change-password.php` |
| `POST /api/user/language` | Save the user locale | `backend/actions/set-language.php` |
| `POST /api/user/searches` | Record a private search and open the results | `backend/actions/record-search.php` |
| `POST /api/user/searches/clear` | Delete private search suggestions | `backend/actions/clear-search-history.php` |
| `POST /api/user/likes/toggle` | Add/remove a like | `backend/actions/toggle-like.php` |
| `POST /api/user/watch-later/toggle` | Add/remove a Watch later item | `backend/actions/toggle-watchlater.php` |
| `POST /api/user/history/progress` | JSON progress and first-view transaction | `backend/actions/save-progress.php` |
| `POST /api/user/history/remove` | Delete one history item | `backend/actions/remove-history.php` |
| `POST /api/user/history/clear` | Delete the whole history | `backend/actions/clear-history.php` |
| `POST /api/user/comments` | Publish a comment | `backend/actions/post-comment.php` |
| `POST /api/user/playlists/create` | Create a playlist | `backend/actions/create-playlist.php` |
| `POST /api/user/playlists/update` | Edit playlist name and description | `backend/actions/update-playlist.php` |
| `POST /api/user/playlists/delete` | Delete a playlist | `backend/actions/delete-playlist.php` |
| `POST /api/user/playlists/items` | Add/remove/reposition an item | `backend/actions/update-playlist-item.php` |
| `POST /api/user/videos/upload` | Validate, store and import an upload | `backend/actions/upload-video.php` |
| `GET\|HEAD /api/user/videos/{id}/stream` (`?download=1`) | Authenticated media and byte ranges | `backend/media.php` |
| `GET\|HEAD /api/user/videos/{id}/thumbnail` | Authenticated thumbnail | `backend/thumb.php` |
| `POST /api/admin/videos/sync` | Folder synchronisation | `backend/actions/sync-videos.php` |
| `POST /api/admin/settings` | Site locale and registration switch | `backend/actions/save-settings.php` |
| `POST /api/admin/users/create` | Create an account | `backend/actions/create-user.php` |
| `POST /api/admin/users/role` | Change a role | `backend/actions/update-user-role.php` |
| `POST /api/admin/users/password` | Set another account's password | `backend/actions/set-user-password.php` |
| `POST /api/admin/users/delete` | Delete an account | `backend/actions/delete-user.php` |
| `GET /api/doc/`, `GET /api/doc/openapi.json` | API reference and OpenAPI document | `backend/api-doc.php` |

### CLI and deployment commands

| Route/URL/command | Handler | File |
|---|---|---|
| `php backend/seed.php [--force] [--demo]` | Create an atomic database; optionally back up and replace | `backend/seed.php` |
| `php backend/secure-admin-cli.php` | Rotate historical default credentials, rename the old default address, run schema migrations | `backend/secure-admin-cli.php` |
| `php backend/reset-admin-password-cli.php [email]` | Reset/promote an account, revoke sessions, clear throttles | `backend/reset-admin-password-cli.php` |
| `php backend/sync-videos-cli.php` | Synchronise media without a browser | `backend/sync-videos-cli.php` |
| `deploy/install-update-reinstall-debian.sh` | Install, update or reinstall on Debian | Same path |
| `deploy/install-update-reinstall-alpine.sh` | Install, update or reinstall on Alpine | Same path |
| `python3 backend/tools/generate_symlink.py SOURCE` | Link an external file tree into the media directory | `backend/tools/generate_symlink.py` |
| `php -S 127.0.0.1:8080 -t frontend/public deploy/local-dev-router.php` | Local server with the `.htaccess` behaviour | `deploy/local-dev-router.php` |

Installer environment variables: `MYOWNTUBE_DOMAIN`, `MYOWNTUBE_ADMIN_PASSWORD`, and `MYOWNTUBE_INSTALLER_LIBRARY_ONLY=1` (Alpine; loads the installer functions without running the installation).

## 6. Impact analysis

| Critical/shared component | Changes affect |
|---|---|
| `backend/inc/routes.php` page or API tables | Every link and form (`fUrl()`), redirects after login, dashboards, the API dispatcher, `/api/doc/`, `LimitRequestBody` locations in both installers and both documentation sets |
| `backend/config.php` paths and `cSessionName` | DB connection, sidecars, media gateways, seeder, CLIs, installer assumptions, the session cookie and the OpenAPI security scheme |
| `backend/inc/db.php` | Every query and transaction; changing pragmas affects concurrency and backup behaviour |
| `backend/inc/session.php` and `users.session_version` | Every page and handler, CSRF forms, revocation, roles, migrations, user-owned data, redirect safety |
| `fAuthSafeDestination()` | Every `next`/`back` redirect; widening it can create open redirects |
| `backend/inc/i18n.php` or a locale key | Chrome, pages, handler notices, all four JSON catalogues; missing keys fall back to English |
| `backend/inc/videos.php` allowed extensions/path rules | Upload, folder sync, media lookup, thumbnails, sidecars, existing imported records, the upload field description in `fRouteApi()` |
| `videos.file_path`/`is_available` semantics | Catalogue visibility, playback, thumbnails, sync, download, progress validation, preserved user state |
| `backend/media.php` range logic | Browser seeking, downloads, long-request session concurrency, content headers |
| `backend/actions/save-progress.php` | History order, resume, view counts, trending rank |
| User-owned table keys | Likes, Watch later, history, playlists, cascading account deletion, legacy migration |
| `frontend/public/.htaccess` and `deploy/local-dev-router.php` | Which files are public and whether clean URLs reach the front controller |
| `frontend/public/styles.css` class names | Every renderer in pages, chrome, components and the API reference |
| Installer paths, names and ownership | Updates, the `mytube` migration and retirement, backup recovery, Apache access, writable boundaries, symlink helper destination, both manuals |
| Apache ports and VirtualHosts | Direct exposure on 80/443, HTTP to HTTPS redirect, client address seen by throttling and logs |

## 7. Extension points

### Add a page

1. Add the page name and clean path to `fRoutePages()` (and to `fRouteDashboardTabs()` if it is a dashboard tab).
2. Add it to `$aPublicPages` or `$aAdminPages` in `backend/index.php` when it is not a normal signed-in page.
3. Create `backend/pages/<name>.php` for queries and access-sensitive logic, and `frontend/views/pages/<name>.php` for escaped HTML (call `fDashboardTabs()` first in a dashboard tab).
4. Link it with `fUrl('<name>')`, add navigation in `frontend/components/chrome.php` and strings in all four `frontend/lang/*.json` catalogues.
5. Update the route and module maps here and in the manuals.

### Add an API endpoint

1. Create `backend/actions/<name>.php` and load config, DB, session, helpers and i18n.
2. Call `fRequireLogin()` or `fRequireAdmin()` and `fCsrfRequire()` before reading mutation input.
3. Validate types, lengths, ownership and foreign records. Use `fQuery()` placeholders and a transaction for multi-step invariants.
4. Add an entry to `fRouteApi()` under `/api/auth/`, `/api/user/` or `/api/admin/` with methods, handler, access, fields and responses; `/api/doc/` updates itself.
5. Point the form `action` to the new path, add `fCsrfField()`, store a translated notice and redirect with 303.
6. Keep GET pages read-only: every state change goes through a POST endpoint under `/api/`.

### Add a locale

1. Add the normalised locale and display name in `backend/config.php`.
2. Copy the complete English key set into `frontend/lang/<locale>.json` and translate values without changing placeholders.
3. Add README, MANUAL and CODE variants and their language lines.

### Add a user-owned collection

Create a table whose primary key begins with `user_id`, reference users with the intended `ON DELETE` rule, qualify every query by `fUserId()`, and add an index for its list order. If older installs had a global table, add a transactional, data-preserving migration in `fAuthEnsureSchema()`.

### Add video metadata

Extend `fVideoCreateMetadata()` and `fVideoImportJson()` together. Decide whether SQLite needs the value for querying; otherwise keep it in the sidecar. Preserve atomic sidecar writes, safe-path rules, upload rollback, and compatibility with sidecars that omit the new field.

### Add a maintenance CLI

Reject non-CLI SAPIs before loading mutable logic, return nonzero on failure, reuse application helpers, and never expose secrets through a web route. Keep the `*-cli.php` suffix so Apache and the development router deny it.

## 8. Data and security invariants

- Database rows never point outside `cVideosPath`; only basenames from `videos.file_path` are resolved.
- A playable imported file requires both the media file and JSON sidecar. A thumbnail also requires the JPEG file.
- File-backed catalogue rows that fail to appear in a successful sync become unavailable, not deleted; successful re-import reactivates them.
- Video extensions are an initial allow-list, not proof of content. `ffprobe` must find a video codec and positive dimensions.
- Sidecars and new seed databases are activated by rename, not written in place.
- User-owned reads and writes include `user_id`; playlist actions additionally confirm playlist ownership.
- The final administrator cannot be demoted or deleted, the signed-in user cannot delete their own account, and administrators change their own password only from the account tab.
- Public registration defaults to off. Passwords are hashed and must be 12–4096 characters.
- Authenticated sessions must match `users.session_version`; changing, setting or resetting a password revokes stale versions.
- Mutations use POST with a session CSRF token; every mutation lives under `/api/`. Result and suggestion GETs never record search history. Redirect targets are internal page paths only.
- Unsupported methods on API paths answer 405; unknown API paths answer 404 and unknown pages render a 404 page.
- Media and thumbnail sessions are closed before long file output to avoid serialising requests from one user.
- Web responses do not disclose SQL exceptions or filesystem details; full errors go to server logs.
- The runtime database and video files are not part of the code tree and must be backed up together.
- The installers change global Apache modules only on an instance without unrelated enabled sites.

## 9. Local development

Run these commands from the project root. PHP needs PDO SQLite, mbstring and Argon2id; `ffmpeg` and `ffprobe` must be on `PATH`.

```bash
MYOWNTUBE_ADMIN_PASSWORD='replace-with-a-long-unique-password' php backend/seed.php
php -S 127.0.0.1:8080 -t frontend/public deploy/local-dev-router.php
```

Open `http://127.0.0.1:8080/` and sign in as `admin@myowntube.home.arpa`. The router reproduces `.htaccess`: it serves `styles.css` and `ui.js`, denies the database, internal modules, dotfiles, configuration and CLI entry points, and sends every other address to the front controller. `php backend/seed.php --demo` adds sample metadata that stays unavailable until matching media is imported. Stop web traffic before using `--force` to replace an existing database.
