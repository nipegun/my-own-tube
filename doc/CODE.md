# MyOwnTube code guide

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

## 1. Architecture and design decisions

MyOwnTube is a server-rendered PHP web application with separate presentation and application directories. `frontend/public/index.php` is the small public entry point; `backend/router.php` dispatches the public URLs and `backend/index.php` controls page requests. Controllers in `backend/pages/` prepare data and include their matching templates from `frontend/views/pages/`. HTML/SVG components and translations live in `frontend/`; shared logic and POST actions live in `backend/`. This keeps Apache/PHP deployment simple while preventing private source and runtime data from becoming public files.

SQLite is the single metadata store. PDO prepared statements are centralised in `backend/inc/db.php`; WAL mode permits normal reads while short writes complete. Foreign keys are enabled on every application connection. The schema is created by `seed.php`, while idempotent compatibility migrations in `backend/inc/session.php` upgrade older installations during startup. Those migrations assign legacy private records to the existing administrator and preserve counts and content.

Video bytes are not stored in SQLite. Each imported video has an original media file, an adjacent JSON sidecar, and normally a generated JPEG thumbnail under the video directory. SQLite stores catalogue and user state plus a safe relative `file_path` and an `is_available` flag. Folder sync hides rows whose files disappeared while preserving their user state, and reactivates them if the media returns. `media.php` and `thumb.php` are authenticated gateways, so Apache denies direct access to both the database and video directory.

Authentication uses PHP sessions because the application is a private website rather than a public API. Passwords use Argon2id and older hashes are upgraded after a successful sign-in. Each session carries the account's database-backed `session_version`; password changes and administrative resets increment it so stale sessions fail their next authentication check. Mutation endpoints, including search-history recording, require POST and a session-bound CSRF token. User output is escaped at render boundaries. Login throttling is keyed by a hash of the remote client address. The Debian virtual host therefore expects a trusted upstream that sends HAProxy PROXY protocol, allowing Apache to supply the intended client address.

Internationalisation uses flat JSON key/value catalogues. English is the fallback; user locale is persisted on the account, while a site default applies to new accounts. All four catalogues must retain identical keys.

The installer is also the update mechanism. It downloads through HTTPS, stops Apache before the SQLite backup, validates the backup, deploys through a staging directory, keeps media outside the code tree, validates Apache before restart, and retains rollback copies. Because it changes the global Apache MPM and PHP module, it first refuses unrelated enabled sites and therefore targets a dedicated Apache instance. Code is immutable to the web user; only database, media, and logs are writable.

The interface follows the system light/dark preference, supports mobile navigation, and offers en-GB, en-US, es-AR, and es-ES in that order. Catalogue and account list pages show at most 24 entries per page, with previous/next controls and total counts.

The standalone installers are `deploy/install-update-reinstall-debian.sh` and `deploy/install-update-reinstall-alpine.sh`. Apache listens only on `127.0.0.1:11080` (HTTP) and `127.0.0.1:11443` (HTTPS with TLS enabled). HAProxy owns the public 80/443 ports and sends PROXY protocol to the HTTPS backend. HTTP redirects to the public HTTPS URL, which HAProxy forwards to 11443; the internal backend port is not exposed in browser URLs.

A normal `php backend/seed.php` creates the administrator, configuration and navigation categories, with an empty video catalogue. `php backend/seed.php --demo` explicitly adds sample metadata, kept unavailable until matching media is imported. Startup migrations also hide old entries without a media path while retaining their rows and user state. Stop web traffic before using `--force` to replace an existing database.

```text
frontend/
  public/        # index.php, styles.css, ui.js, .htaccess
  views/         # layout and page templates
  components/    # HTML and SVG rendering
  lang/          # translated interface strings
backend/
  actions/       # POST handlers
  pages/         # page controllers and queries
  inc/           # shared application logic
  tools/         # media import utilities
  db/            # private runtime database
  videos/        # private media directory or link
doc/
tests/
deploy/
```

The installer deploys `frontend/` and `backend/` together under `/var/www/mytube`. The database now lives at `/var/www/mytube/backend/db/mytube.db`; `/var/www/mytube/backend/videos` links to the existing `/var/www/mytube-videos` directory. Updates copy the former `/var/www/mytube/db/mytube.db` through a verified SQLite backup and preserve media. Rollback restores the former directory structure and Apache configuration.

## 2. Module map

| Module | Path | Responsibility | Depends on | Used by |
|---|---|---|---|---|
| Configuration | `backend/config.php` | Application identity, four ordered locales, page size, paths, security limits and administrator defaults | PHP runtime | All web and CLI entry points |
| Page front controller | `backend/index.php` | Security headers, schema readiness, access control, page dispatch, layout | All `inc` modules, `pages` | Browser page requests |
| Pages | `backend/pages/*.php` | Query and render one application screen | DB, session, helpers, components, i18n | `backend/index.php` |
| Actions | `backend/actions/*.php` | Validate authenticated POST requests and mutate state | DB, session, helpers, i18n; videos where needed | HTML forms and watch progress JavaScript |
| Database adapter | `backend/inc/db.php` | One PDO connection, SQLite pragmas, prepared-query helpers, private error logging | `backend/config.php`, PDO SQLite | Pages, actions, auth, media, CLIs |
| Session and authentication | `backend/inc/session.php` | Cookie policy, CSRF, users, roles, throttling, schema compatibility, guards | DB, config; i18n when loaded | All protected entry points |
| Internationalisation | `backend/inc/i18n.php` | Locale normalisation, catalogue loading, fallback and persistence | DB, session, JSON catalogues | Pages, actions, shared UI |
| Video domain | `backend/inc/videos.php` | Safe paths, ffprobe validation, metadata, thumbnails, import and folder sync | DB, session, FFmpeg tools | Upload/sync actions, CLIs, media, components |
| UI components | `frontend/components/components.php` | Escaped video cards, rows, avatars and pagination navigation | helpers, icons, videos, i18n | Pages |
| Application chrome | `frontend/components/chrome.php` | Top bar, sidebar, user menu, search and locale controls | session, helpers, icons, i18n | `backend/index.php` |
| Icons | `frontend/components/icons.php` | Inline SVG icon functions | None | Chrome, components, pages |
| Helpers | `backend/inc/helpers.php` | Escaping, URLs, bounded database pagination and display formatting | i18n optionally | All rendering and redirects |
| Media gateway | `backend/media.php` | Authenticated GET/HEAD video streaming and single byte ranges | DB, session, helpers, videos | HTML video player and download links |
| Thumbnail gateway | `backend/thumb.php` | Authenticated GET/HEAD JPEG delivery | DB, session, helpers, videos | Video cards and watch page |
| Locale catalogues | `frontend/lang/*.json` | Matching translated interface strings for every locale | JSON parser | `backend/inc/i18n.php` |
| Seeder | `backend/seed.php` | Empty production initialisation; explicit unavailable demo metadata; verified replacement backup | Config, PDO SQLite | Installer and administrator CLI |
| Maintenance CLIs | `backend/*-cli.php` | Secure legacy admin, reset admin password, synchronise media | Shared application modules | Installer and server operator |
| Apache policy | `frontend/public/.htaccess` | Deny private/runtime files and CLI scripts when overrides apply | Apache modules | Deployed website |
| Presentation | `frontend/public/styles.css` | Responsive layouts with visible overflow handling and system light/dark themes | Rendered class names | All pages |
| Debian deployment | `deploy/install-update-reinstall-debian.sh` | Local Apache/TLS backend, protected logs/credentials, staged certificates and configuration rollback | Debian, apt, Apache, PHP, SQLite, curl, OpenSSL | Root server operator |
| Symlink helper | `backend/tools/generate_symlink.py` | Recursively link an external source tree into the persistent media directory | Python 3.13+, filesystem | Server operator before sync |
| Local development router | `deploy/local-dev-router.php` | Reproduce private-path denials under PHP's built-in server | PHP development server | Local repository setup |
| Regression tests | `tests/*` | Required PHP/HTTP/media/Apache integrations and separate browser regressions; temporary fixtures under `_/temp/` | PHP, Bash, Python, Node.js, tar, curl, SQLite, FFmpeg, Apache, OpenSSL; Chromium/Playwright | Contributors and CI |
| Browser navigation | `frontend/public/ui.js` | Mobile sidebar, backdrop, Escape and menu accessibility state | Native DOM and matchMedia | `backend/index.php` |
| Public bootstrap | `frontend/public/index.php` | Dispatch HTTP requests into the private backend | `backend/router.php` | Apache and the development router |
| HTTP dispatcher | `backend/router.php` | Allow only page, action and authenticated media routes | Page/action/media handlers | Public bootstrap |
| Templates | `frontend/views/*.php`, `frontend/views/pages/*.php` | Render prepared page data and shared layout | Frontend components and backend helpers | Page controllers |
| Navigation data | `backend/inc/navigation.php` | Query notifications and suggestions and resolve user navigation context | DB, session, i18n | `frontend/components/chrome.php` |
| Alpine deployment | `deploy/install-update-reinstall-alpine.sh` | Idempotent apk/PHP 8.4/OpenRC deployment, managed Apache main configuration and private sessions | Alpine main/community, Apache, PHP, SQLite, OpenSSL | Root server operator |

## 3. Key symbol index

| Symbol | File:line | What it does |
|---|---|---|
| `fDb` | `backend/inc/db.php:19` | Opens and caches PDO SQLite with foreign keys, WAL, timeout, and normal synchronous mode |
| `fQuery` | `backend/inc/db.php:45` | Executes a prepared statement and hides internal SQL errors from web users |
| `fE` | `backend/inc/helpers.php:3` | Escapes arbitrary output for an HTML text/attribute context |
| `fUrl` | `backend/inc/helpers.php:7` | Builds canonical front-controller URLs |
| `fCsrfRequire` | `backend/inc/session.php:69` | Rejects a mutation whose session CSRF token is absent or invalid |
| `fAuthEnsureSchema` | `backend/inc/session.php:115` | Creates auth/settings tables and applies idempotent compatibility migrations |
| `fAuthEnsureVideoAvailability` | `backend/inc/session.php:224` | Adds availability and hides legacy rows without media paths without deleting user state |
| `fAuthMigrateUserTable` | `backend/inc/session.php:278` | Rebuilds legacy likes, Watch Later, or history tables with user ownership while copying rows |
| `fAuthLoginAllowed` | `backend/inc/session.php:520` | Checks and expires the per-address login throttle window |
| `fAuthCreateUser` | `backend/inc/session.php:569` | Validates account data, hashes the password, and inserts a user |
| `fAuthLogin` | `backend/inc/session.php:633` | Verifies credentials, records failures, rehashes when needed, and rotates the session ID |
| `fAuthChangePassword` | `backend/inc/session.php:687` | Replaces a password, rotates the current session, and revokes the others |
| `fAuthAdminUpdateRole` | `backend/inc/session.php:737` | Changes a role without permitting removal of the final administrator |
| `fCurrentUser` | `backend/inc/session.php:810` | Loads the account and rejects a stale session version |
| `fRequireLogin` | `backend/inc/session.php:875` | Redirects anonymous browser requests to sign-in |
| `fRequireAdmin` | `backend/inc/session.php:882` | Enforces both authentication and administrator role |
| `fCurrentLocale` | `backend/inc/i18n.php:132` | Resolves user, session, and site locale in that order |
| `fT` | `backend/inc/i18n.php:162` | Retrieves a translated string with English fallback and parameter replacement |
| `fVideoPathFromFilePath` | `backend/inc/videos.php:126` | Resolves a catalogue path only inside the configured video directory |
| `fVideoSaveJson` | `backend/inc/videos.php:180` | Atomically writes a UTF-8 JSON sidecar through a temporary file and rename |
| `fVideoValidateFile` | `backend/inc/videos.php:356` | Enforces file size and requires ffprobe to find a real video stream |
| `fVideoImportJson` | `backend/inc/videos.php:450` | Normalises sidecar metadata and inserts or updates the matching catalogue row |
| `fVideosSyncFolder` | `backend/inc/videos.php:542` | Validates files, imports sidecars/thumbnails, and reconciles catalogue availability |
| `fVideoCard` | `frontend/components/components.php:54` | Renders a reusable, escaped catalogue card |
| `fValidateApacheIsolation` | `deploy/install-update-reinstall-debian.sh:211` | Refuses to change global Apache modules when unrelated sites are enabled |
| `fMain` | `deploy/install-update-reinstall-debian.sh:707` | Orders validation, backup, deployment, configuration, seed/migration, and restart |
| `fMain` | `backend/tools/generate_symlink.py:93` | Validates CLI input, creates links, and returns a meaningful process status |
| `fPaginate` | `backend/inc/helpers.php:25` | Counts filtered rows, clamps the page and fetches at most cPageSize entries |
| `fPaginationRender` | `frontend/components/components.php:151` | Renders previous/next links with preserved filters |
| `fBackupConfigFile` | `deploy/install-update-reinstall-debian.sh:84` | Journals a file or symlink before deployment mutation |
| `fRestoreConfigFiles` | `deploy/install-update-reinstall-debian.sh:108` | Restores journalled configuration after failure |
| `fCreateCertificate` | `deploy/install-update-reinstall-debian.sh:438` | Validates staged TLS files before activating them |
| `fVerifyBackend` | `deploy/install-update-reinstall-debian.sh:626` | Verifies the local HTTPS backend and certificate through PROXY protocol |
| `fNavigationData` | `backend/inc/navigation.php:3` | Prepares navigation data without HTML rendering |
| `fChannelById` | `backend/inc/videos.php:644` | Caches channel records used by video rendering |
| `fIdSet` | `backend/inc/videos.php:651` | Loads the current user's liked or saved video IDs |
| `fAcquireLock` | `deploy/install-update-reinstall-debian.sh:53` | Excludes overlapping installation processes |
| `fActivateConfigFile` | `deploy/install-update-reinstall-debian.sh:65` | Replaces only changed managed configuration |
| `fConfigureAlpineMain` | `deploy/install-update-reinstall-alpine.sh:473` | Builds the Alpine Apache configuration without duplicate includes |
| `fMain` | `deploy/install-update-reinstall-alpine.sh:748` | Runs the Alpine installation/update transaction |

## 4. Main flows

### Page request

`GET /index.php?page=…` → `frontend/public/index.php` → `backend/router.php` → `backend/index.php` loads application and rendering modules → schema, locale and access checks → `backend/pages/<page>.php` prepares data → `frontend/views/pages/<page>.php` renders content → `frontend/views/layout.php` wraps it. Action and media URLs go from the same public entry point to their backend handlers.

### Sign-in

Login form → `POST actions/login.php` → `fCsrfRequire()` → `fAuthLoginAllowed()` → prepared user lookup and `password_verify()` → failure counter or `fAuthStartSession()` → session ID rotation and stored `session_version` → HTTP 303 to a validated internal destination. Every later user lookup compares that version with SQLite.

### Search and private history

Top-bar form → `POST actions/record-search.php` → login/CSRF guards → bounded scalar query → per-user history upsert → HTTP 303 to `GET page=search&q=…` → read-only result query. Direct result and suggestion GETs never mutate history.

### Password replacement

Settings form or reset CLI → verify current credentials when applicable → Argon2id hash → increment `users.session_version`. The in-app change also rotates the current session and stores the new version; all other sessions, or every session after a CLI reset, become invalid on their next request.

### Registration or administrator-created account

Registration/settings form → registration/administrator guard → `fAuthCreateUser()` → email/password/name validation → `password_hash()` → user insert. Public registration additionally starts the new session; administrator creation returns to the users page.

### Upload

Upload form → `actions/upload-video.php` → login and CSRF guards → extension/size checks → collision-free destination → `move_uploaded_file()` → `fVideoValidateFile()`/ffprobe → `fVideoCreateMetadata()` → atomic sidecar → `fVideoImportJson()` and thumbnail → redirect to watch. Any failure removes video, sidecar, and thumbnail created by that attempt.

### Folder synchronisation

Admin form or `sync-videos-cli.php` → `fVideosSyncFolder()` → preflight `ffprobe` → snapshot existing file-backed IDs → enumerate allowed extensions → validate real streams → generate missing sidecar → `fVideoImportJson()` → generate/refresh thumbnail → log per-file failures → transactionally mark snapshot IDs absent from successful imports unavailable → count imported/generated/errors. Rows and user state are never deleted, and uploads created after the snapshot are not hidden by reconciliation.

### Playback and progress

Watch page queries the catalogue → `<video>` requests `media.php?id=…` → login guard and safe-path check → optional single range parsing → session lock released → 1 MiB streaming blocks. After the `playing` event, JavaScript posts a CSRF-protected percentage to `save-progress.php`; a transaction inserts history and increments views only on first insert, otherwise it updates progress.

### Playlist mutation

Playlist form → action login/CSRF guard → ownership-qualified query → transactional insert/remove/reposition → positions are rewritten contiguously → HTTP 303 back to watch or playlist.

### Startup migration

Any application/maintenance entry point → `fAuthEnsureSchema()` → create missing settings/auth tables → add user locale and session version → add video availability → find administrator → transactionally rebuild legacy private tables and copy rows → add `user_id` to comments/playlists and assign existing rows → create indexes → record migration markers.

### Debian and Alpine updates

Installer `fMain()` → root validation and exclusive lock → root-only logging → validation/isolation → packages/directories/archive → stop Apache → verified SQLite backup → staged web deployment → journal configuration → staged and verified certificate → loopback listeners and TLS VirtualHost → seed/migrate → restart → verify HTTPS through PROXY protocol → save credentials → commit deployment. Failure cleanup restores journalled configuration and the previous web tree before restarting.

### Paginated catalogue and collections

Page handler → filtered count and ordered row query → `fPaginate()` clamps the requested page and applies the 24-row limit → existing card/row rendering → `fPaginationRender()` builds escaped links preserving category, search, owner or playlist context. Comments use `comments_page`; other paginated collections use `p`. Sorting includes a stable ID to prevent duplicates across pages.

Before changing configuration, the installer records the previous files in a private backup directory. New certificates are generated and validated before activation. A later failure restores certificate/key, PHP settings, Apache listeners, site/module links and the previous web tree before attempting to restart Apache. Recovery errors are logged instead of silently ignored. Before committing the deployment, the installer checks local HTTPS through PROXY protocol, validating the certificate and application response.

### Idempotence and platform adapters

Both installers are idempotent for application state: repeated runs preserve existing accounts, password hashes, settings, playlists, login throttles and media. Valid matching certificates with more than 30 days remaining are reused. Configuration files with identical content are not rewritten, and credentials are recorded only when created or rotated. An exclusive lock prevents concurrent deployments; service processes do not inherit that lock. Each execution still appends its audit log and creates recovery backups. A changed code archive, requested domain or available package version is treated as an update.

On Alpine, the installer uses `apk`, PHP 8.4, the `apache` user/group, and OpenRC. In containers without a booted OpenRC instance, it controls `httpd` directly while registering the startup service with `rc-update`. It manages `/etc/apache2/httpd.conf`, `/etc/apache2/mytube-ports.conf`, `/etc/apache2/mytube-site.conf` and `/etc/php84/conf.d/99-mytube.ini`; it does not append repeated includes or listeners. Private sessions live in `/var/lib/mytube/sessions`. The default package SSL configuration is superseded by the managed configuration, so Apache only listens on the two loopback backend ports.

## 5. Entry points, routes, and commands

### Pages and media

| Route/URL/command | Handler | File |
|---|---|---|
| `GET /index.php?page=login` | Public sign-in form | `backend/pages/login.php` |
| `GET /index.php?page=register` | Conditional public registration form | `backend/pages/register.php` |
| `GET /index.php?page=home` | Category catalogue | `backend/pages/home.php` |
| `GET /index.php?page=trending` | Category-filtered trending catalogue | `backend/pages/trending.php` |
| `GET /index.php?page=search&q=…` | Read-only search results and private suggestions | `backend/pages/search.php` |
| `GET /index.php?page=watch&id=…` | Player, actions, and comments | `backend/pages/watch.php` |
| `GET /index.php?page=liked` | Current user's likes | `backend/pages/liked.php` |
| `GET /index.php?page=watchlater` | Current user's Watch Later list | `backend/pages/watchlater.php` |
| `GET /index.php?page=history` | Current user's playback history | `backend/pages/history.php` |
| `GET /index.php?page=playlists` | Current user's playlists | `backend/pages/playlists.php` |
| `GET /index.php?page=playlist&id=…` | Owned playlist details | `backend/pages/playlist.php` |
| `GET /index.php?page=upload` | Authenticated upload form | `backend/pages/upload.php` |
| `GET /index.php?page=profile` | Display-name profile form | `backend/pages/profile.php` |
| `GET /index.php?page=settings` | Language, password, search history, and administrator settings | `backend/pages/settings.php` |
| `GET /index.php?page=users` | Administrator user management | `backend/pages/users.php` |
| `GET|HEAD /media.php?id=…[&download=1]` | Authenticated media/range gateway | `backend/media.php` |
| `GET|HEAD /thumb.php?id=…` | Authenticated thumbnail gateway | `backend/thumb.php` |

### Mutation endpoints

Every endpoint below accepts POST only and requires CSRF; all except login and conditional registration require a signed-in user.

| Route/URL/command | Handler | File |
|---|---|---|
| `/actions/login.php` | Verify credentials and start session | `backend/actions/login.php` |
| `/actions/register.php` | Create a standard user if registration is enabled | `backend/actions/register.php` |
| `/actions/logout.php` | Destroy and rotate session | `backend/actions/logout.php` |
| `/actions/update-profile.php` | Change display name | `backend/actions/update-profile.php` |
| `/actions/change-password.php` | Verify and replace current password | `backend/actions/change-password.php` |
| `/actions/set-language.php` | Persist the current user's locale | `backend/actions/set-language.php` |
| `/actions/toggle-like.php` | Add/remove a per-user like | `backend/actions/toggle-like.php` |
| `/actions/toggle-watchlater.php` | Add/remove a Watch Later item | `backend/actions/toggle-watchlater.php` |
| `/actions/save-progress.php` | JSON progress and first-view transaction | `backend/actions/save-progress.php` |
| `/actions/clear-history.php` | Delete all current-user history | `backend/actions/clear-history.php` |
| `/actions/remove-history.php` | Delete one current-user history item | `backend/actions/remove-history.php` |
| `/actions/record-search.php` | Record a bounded per-user query and redirect to results | `backend/actions/record-search.php` |
| `/actions/clear-search-history.php` | Delete private search suggestions | `backend/actions/clear-search-history.php` |
| `/actions/post-comment.php` | Create an escaped-at-render comment | `backend/actions/post-comment.php` |
| `/actions/create-playlist.php` | Create an owned playlist | `backend/actions/create-playlist.php` |
| `/actions/update-playlist.php` | Edit owned playlist metadata | `backend/actions/update-playlist.php` |
| `/actions/delete-playlist.php` | Delete an owned playlist | `backend/actions/delete-playlist.php` |
| `/actions/update-playlist-item.php` | Add/remove/reposition an owned item | `backend/actions/update-playlist-item.php` |
| `/actions/upload-video.php` | Validate, persist, and import an upload | `backend/actions/upload-video.php` |
| `/actions/sync-videos.php` | Administrator folder sync | `backend/actions/sync-videos.php` |
| `/actions/save-settings.php` | Administrator locale/registration settings | `backend/actions/save-settings.php` |
| `/actions/create-user.php` | Administrator account creation | `backend/actions/create-user.php` |
| `/actions/update-user-role.php` | Administrator role change | `backend/actions/update-user-role.php` |
| `/actions/delete-user.php` | Administrator account deletion | `backend/actions/delete-user.php` |

### CLI and deployment commands

| Route/URL/command | Handler | File |
|---|---|---|
| `php backend/seed.php [--force] [--demo]` | Create an atomic database; optionally back up and replace | `backend/seed.php` |
| `php backend/secure-admin-cli.php` | Rotate historical default credentials and run schema migrations | `backend/secure-admin-cli.php` |
| `php backend/reset-admin-password-cli.php [email]` | Reset/promote an account, revoke sessions, and clear throttles | `backend/reset-admin-password-cli.php` |
| `php backend/sync-videos-cli.php` | Synchronise media without a browser | `backend/sync-videos-cli.php` |
| `deploy/install-update-reinstall-debian.sh` | Install, reinstall, or update Debian deployment | Same path |
| `deploy/install-update-reinstall-alpine.sh` | Install, update or reinstall Alpine while preserving application state | Same path |
| `python3 backend/tools/generate_symlink.py SOURCE` | Link an external file tree into persistent media | `backend/tools/generate_symlink.py` |
| `php -S 127.0.0.1:8080 -t frontend/public deploy/local-dev-router.php` | Serve the local application with private paths protected | `deploy/local-dev-router.php` |
| `tests/run-tests.sh` | Run required static and integration checks; --static explicitly excludes integrations | `tests/run-tests.sh` |
| `tests/check-browser.py` | Check pagination and mobile/desktop layouts in all locales and themes | `tests/check-browser.py` |

Full tests require PHP with PDO SQLite, mbstring and Argon2id, sqlite3, FFmpeg/ffprobe, Python 3.13+, Node.js, tar, OpenSSL, apache2-bin and libapache2-mod-php. `tests/run-tests.sh` fails if required dependencies are missing. `tests/run-tests.sh --static` explicitly runs only syntax, locale and local-router checks. `tests/check-browser.py` additionally requires the Python Playwright package and Chromium; it checks pagination and layouts at mobile and desktop widths in all four languages and both themes, opening menus and mobile navigation. Run both the full suite and the browser check before deployment. `MYOWNTUBE_APACHE_ROOT` can point to an extracted Apache package root; `MYOWNTUBE_CHROMIUM` can select the Chromium executable, and `MYOWNTUBE_PHP_MODULE` can select an extracted Apache PHP module. All test files live under `_/temp/`.


Installer tests execute three successive flows for each distribution and compare database dumps, configuration, certificates and credential files. They also exercise locking, rejection of unrelated sites and rollback without starting a previously stopped service. Package and service administration are simulated in the portable fixtures; Apache/PHP HTTP tests run real server binaries.

## 6. Impact analysis

| Critical/shared component | Changes affect |
|---|---|
| `backend/config.php` paths | DB connection, sidecars, media gateways, seeder, CLIs, installer assumptions |
| `backend/inc/db.php` | Every query and transaction; changing pragmas affects concurrency and backup behaviour |
| `backend/inc/session.php` and `users.session_version` | Every page/action, cookie compatibility, CSRF forms, revocation, roles, migrations, user-owned data |
| `backend/inc/i18n.php` or a locale key | Chrome, pages, action toasts, all four JSON catalogues; missing locale keys fall back to English |
| `backend/inc/videos.php` allowed extensions/path rules | Upload, folder sync, media lookup, thumbnails, sidecars, existing imported records |
| `videos.file_path`/`is_available` semantics | Catalogue visibility, playback, thumbnail delivery, sync, download, progress validation, preserved user state |
| `media.php` range logic | Browser seeking, downloads, long-request session concurrency, content headers |
| `save-progress.php` | History ordering, resume, view counts, trending rank |
| User-owned table keys | Likes, Watch Later, history, playlists, cascading account deletion, legacy migration |
| Page names in `backend/index.php` | Routing, sidebar URLs, login `next` destinations, documentation |
| `frontend/public/styles.css` class names | Every renderer in pages, chrome, and components |
| Installer paths/ownership | Updates, backup recovery, Apache access, web write boundaries, symlink helper destination |
| Local development router rules | Which repository files the PHP built-in server can expose |
| Apache PROXY protocol setting | Client IP seen by throttling/logs and whether direct TLS connections are accepted |

## 7. Extension points

### Add a page

1. Add the route name to the allow-list in `backend/index.php`.
2. Create `backend/pages/<name>.php` for queries and access-sensitive logic.
3. Create `frontend/views/pages/<name>.php` for escaped HTML; require it from the controller using `cFrontendPath`.
4. Add navigation in `frontend/components/chrome.php` and translated strings in all four `frontend/lang/*.json` catalogues.
5. Update the route/module maps and exercise the page with both test commands.

### Add a mutation endpoint

1. Create `backend/actions/<name>.php` and load config, DB, session, helpers, and i18n.
2. Enforce POST, call `fCsrfRequire()`, and call `fRequireLogin()` or `fRequireAdmin()` before reading mutation input.
3. Validate types, lengths, ownership, and foreign records. Use `fQuery()` placeholders and a transaction for multi-step invariants.
4. Add `fCsrfField()` to the calling form, store a translated toast, and redirect with HTTP 303.
5. Add the endpoint to the route table and update impact notes.
6. Add or extend a regression in `tests/run-tests.sh`; a GET page must remain read-only.

### Add a locale

1. Add the normalised locale and display name in `backend/config.php`.
2. Copy the complete English key set into `frontend/lang/<locale>.json` and translate values without changing placeholders.
3. Verify key parity and exercise account/site locale persistence.
4. Add the language to all README and manual variants if project documentation policy requires it.

### Add a user-owned collection

Create a table whose primary key begins with `user_id`, reference users with the intended `ON DELETE` rule, qualify every query by `fUserId()`, and add an index for its list order. If older installs had a global table, add a transactional, data-preserving migration in `fAuthEnsureSchema()` and test it with populated legacy rows.

### Add video metadata

Extend `fVideoCreateMetadata()` and `fVideoImportJson()` together. Decide whether SQLite needs the value for querying; otherwise keep it in the sidecar. Preserve atomic sidecar writes, safe-path rules, upload rollback, and compatibility with sidecars that omit the new field.

### Add a maintenance CLI

Reject non-CLI SAPIs before loading mutable logic, return nonzero on failure, reuse application helpers, and never expose secrets through a web route. Add an Apache denial pattern if the filename no longer matches `*-cli.php`.

## 8. Data and security invariants

- Database rows never point outside `cVideosPath`; only basenames from `videos.file_path` are resolved.
- A playable imported file requires both the media file and JSON sidecar. A thumbnail also requires the JPEG file.
- File-backed catalogue rows that fail to appear in a successful sync become unavailable, not deleted; successful re-import reactivates them.
- Video extensions are an initial allow-list, not proof of content. `ffprobe` must find a video codec and positive dimensions.
- Sidecars and new seed databases are activated by rename, not written in place.
- User-owned reads and writes include `user_id`; playlist actions additionally confirm playlist ownership.
- The final administrator cannot be demoted or deleted, and the signed-in user cannot delete their own account.
- Public registration defaults to off. Passwords are hashed and must be 12–4096 characters.
- Authenticated sessions must match `users.session_version`; changing or resetting a password revokes stale versions.
- User-initiated mutations use POST with a session CSRF token; result and suggestion GETs never record search history. Redirect destinations are restricted to the front controller.
- Parameters that are expected to be scalar reject array-shaped input; dynamic SQL identifiers are restricted to explicit allow-lists.
- Media and thumbnail sessions are closed before long file output to avoid serialising requests from one user.
- Web responses do not disclose SQL exceptions or filesystem details; full errors go to server logs.
- The runtime database and video files are intentionally ignored by version control and must be backed up separately.
- The Debian installer changes global Apache modules only on an instance without unrelated enabled sites.
