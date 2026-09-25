# MyOwnTube

The project name is `my-own-tube` and the application name is `MyOwnTube`. This is a standalone web application, organised into `frontend/`, `backend/`, `doc/`, `tests/` and `deploy/`. Domain names, account addresses and `MYTUBE_*` environment variables remain compatible with installed instances.

MyOwnTube is a small, private, self-hosted video library for Debian and Alpine Linux. It provides authenticated playback, validated uploads, search, history and resume progress, likes, Watch Later, playlists, comments, user administration, and English (UK and US), Spanish (Spain), and Spanish (Argentina) interfaces.

The application is intentionally simple: Apache serves PHP, SQLite stores metadata, and the original video files remain on disk. No build step, JavaScript package manager, or external database is required.

The interface follows the system light/dark preference, supports mobile navigation, and offers en-GB, en-US, es-AR, and es-ES in that order. Catalogue and account list pages show at most 24 entries per page, with previous/next controls and total counts.

## Project structure

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

Only `frontend/public/` is the HTTP document root. Views, components and translations remain in private frontend directories; PHP controllers, actions, configuration, database and media belong to the backend. The public entry point dispatches the existing `/index.php`, `/actions/*.php`, `/media.php` and `/thumb.php` URLs without exposing backend files.

The installer deploys `frontend/` and `backend/` together under `/var/www/mytube`. The database now lives at `/var/www/mytube/backend/db/mytube.db`; `/var/www/mytube/backend/videos` links to the existing `/var/www/mytube-videos` directory. Updates copy the former `/var/www/mytube/db/mytube.db` through a verified SQLite backup and preserve media. Rollback restores the former directory structure and Apache configuration.

## Main features

- Private by default: every page, video stream, and thumbnail requires a signed-in user.
- Registration is disabled initially and can be enabled temporarily by an administrator.
- Per-user likes, Watch Later, history, search history, playlists, language, and playback progress.
- Upload validation with `ffprobe`; a permitted filename extension alone is not trusted.
- HTTP range streaming, including suffix ranges, plus explicit downloads.
- CSRF protection, strict session cookies, Argon2id password hashes, escaped output, login throttling, and security headers.
- Administrator controls for users, roles, site language, registration, and folder synchronisation.
- Atomic database creation, migration of older schemas without discarding user data, and persistent update backups.

## Requirements

The Debian installer uses apt; the Alpine installer uses apk. They install Apache, PHP with SQLite and mbstring, SQLite CLI, FFmpeg/ffprobe, OpenSSL, curl, and their supporting packages. Run it as `root` on a host whose DNS name already resolves correctly.

Use a dedicated Apache instance. The installer selects the global prefork MPM and Apache PHP module, so it refuses to continue when an unrelated virtual host is enabled. It permits only Debian's `000-default.conf` and MyOwnTube's own site during validation.

The generated HTTPS virtual host enables the HAProxy PROXY protocol. Place MyOwnTube behind a trusted proxy or load balancer that sends PROXY protocol headers. If your deployment connects browsers directly to Apache, adapt that directive to your network design before exposing the service.

## Install, update or reinstall

### Debian

Download the installer over verified HTTPS, inspect it if required, and then execute it:

```bash
curl --proto '=https' --tlsv1.2 -fsSL \
  https://gitea.servint.home.arpa/nipegun/my-own-tube/raw/branch/master/deploy/install-update-reinstall-debian.sh \
  -o /tmp/install-update-reinstall-debian.sh
chmod 0755 /tmp/install-update-reinstall-debian.sh
sudo env \
  MYTUBE_DOMAIN='mytube.home.arpa' \
  MYTUBE_ADMIN_PASSWORD='replace-with-a-long-unique-password' \
  /tmp/install-update-reinstall-debian.sh
```

### Alpine

Run as root. Enable the Alpine main and community repositories; the installer uses the php84 packages. Install the bootstrap tools before downloading the Bash script:

```bash
apk add --no-cache bash ca-certificates curl flock
curl --proto '=https' --tlsv1.2 -fsSL \
  https://gitea.servint.home.arpa/nipegun/my-own-tube/raw/branch/master/deploy/install-update-reinstall-alpine.sh \
  -o /tmp/install-update-reinstall-alpine.sh
MYTUBE_DOMAIN='mytube.home.arpa' \
  MYTUBE_ADMIN_PASSWORD='replace-with-a-long-unique-password' \
  bash /tmp/install-update-reinstall-alpine.sh
```

`MYTUBE_ADMIN_PASSWORD` must contain between 12 and 4096 characters. If it is omitted for a new or historical-default account, a random password is generated. The installer stores its CLI credential output in `/root/app-web-credentials.txt`, separate from `/root/app-web-install.log`; both files have mode 0600. Direct maintenance CLI commands still print their credentials to the terminal.

Running the same script again performs an update. It stops Apache, creates an integrity-checked SQLite backup, keeps the external video directory, stages the new code, tests the Apache configuration, and rolls back the web directory if deployment fails.

The installer creates a self-signed certificate when no valid certificate for the configured host exists. Trust that certificate on client devices, or replace it with a certificate issued by your own trusted authority.

After installation, open:

```text
https://mytube.home.arpa/
```

The initial administrator email is:

```text
admin@mytube.home.arpa
```

## Installed paths

| Purpose | Path |
|---|---|
| Application code and SQLite database | `/var/www/mytube` |
| Persistent video files and sidecars | `/var/www/mytube-videos` |
| Apache logs | `/var/www/mytube-logs` |
| Persistent database, web, certificate, and Apache backups | `/var/backups/mytube` |
| Self-signed certificate and key | `/etc/ssl/mytube` |
| Apache virtual host | `/etc/apache2/sites-available/000-mytube.conf` |

Code is owned by `root:root`. Only the database, videos, and logs are writable by `www-data` on Debian or `apache` on Alpine.

The standalone installers are `deploy/install-update-reinstall-debian.sh` and `deploy/install-update-reinstall-alpine.sh`. Apache listens only on `127.0.0.1:11080` (HTTP) and `127.0.0.1:11443` (HTTPS with TLS enabled). HAProxy owns the public 80/443 ports and sends PROXY protocol to the HTTPS backend. HTTP redirects to the public HTTPS URL, which HAProxy forwards to 11443; the internal backend port is not exposed in browser URLs.

Before changing configuration, the installer records the previous files in a private backup directory. New certificates are generated and validated before activation. A later failure restores certificate/key, PHP settings, Apache listeners, site/module links and the previous web tree before attempting to restart Apache. Recovery errors are logged instead of silently ignored. Before committing the deployment, the installer checks local HTTPS through PROXY protocol, validating the certificate and application response.

Both installers are idempotent for application state: repeated runs preserve existing accounts, password hashes, settings, playlists, login throttles and media. Valid matching certificates with more than 30 days remaining are reused. Configuration files with identical content are not rewritten, and credentials are recorded only when created or rotated. An exclusive lock prevents concurrent deployments; service processes do not inherit that lock. Each execution still appends its audit log and creates recovery backups. A changed code archive, requested domain or available package version is treated as an update.

On Alpine, the installer uses `apk`, PHP 8.4, the `apache` user/group, and OpenRC. In containers without a booted OpenRC instance, it controls `httpd` directly while registering the startup service with `rc-update`. It manages `/etc/apache2/httpd.conf`, `/etc/apache2/mytube-ports.conf`, `/etc/apache2/mytube-site.conf` and `/etc/php84/conf.d/99-mytube.ini`; it does not append repeated includes or listeners. Private sessions live in `/var/lib/mytube/sessions`. The default package SSL configuration is superseded by the managed configuration, so Apache only listens on the two loopback backend ports.

## Manual local setup

Run these commands from the project root.

For development, ensure PHP has PDO SQLite, mbstring, and Argon2id password support and that `ffmpeg` and `ffprobe` are on `PATH`. Then initialise the database and run a local server:

```bash
MYTUBE_ADMIN_PASSWORD='replace-with-a-long-unique-password' php backend/seed.php
php -S 127.0.0.1:8080 -t frontend/public deploy/local-dev-router.php
```

Open `http://127.0.0.1:8080/`. The router prevents the development server from exposing the database, internal modules, sidecar files, dotfiles, configuration, or CLI entry points. The built-in server is for local development only; use the provided Apache configuration for deployment.

A normal `php backend/seed.php` creates the administrator, configuration and navigation categories, with an empty video catalogue. `php backend/seed.php --demo` explicitly adds sample metadata, kept unavailable until matching media is imported. Startup migrations also hide old entries without a media path while retaining their rows and user state. Stop web traffic before using `--force` to replace an existing database.

## Import existing video files

Copy or link supported videos into the installed video directory, then sign in as an administrator and use **Upload video → Sync folder**. The CLI equivalent is:

```bash
sudo -u www-data php /var/www/mytube/backend/sync-videos-cli.php
```

MyOwnTube accepts `mp4`, `m4v`, `avi`, `wmv`, `3gp`, `mpg`, `mpeg`, `divx`, `mp5`, and `asf` filenames, but imports a file only when `ffprobe` confirms a real video stream. It creates an adjacent JSON sidecar and, when FFmpeg can decode the file, a JPEG thumbnail.

From a clone of this repository, the optional helper links every file in another folder into the persistent video directory, without allowing source and destination trees to overlap:

```bash
sudo python3 backend/tools/generate_symlink.py /path/to/source/videos
```

Synchronisation ignores unsupported or invalid files and records their filenames and validation errors in the server error log. If an imported file disappears, its catalogue row and per-user state are retained but the video is hidden and cannot be streamed. A later successful synchronisation makes it available again when the file returns.

The maintenance examples below use Debian. On Alpine, run the same PHP file as `su-exec apache:apache php84` instead of `sudo -u www-data php`. For recovery, put `MYTUBE_ADMIN_PASSWORD=...` before `su-exec`; the environment variable is passed to PHP. To synchronise from the Alpine server:

```bash
su-exec apache:apache php84 /var/www/mytube/backend/sync-videos-cli.php
```

## Administrator recovery

Reset an existing account and promote it to administrator from the server terminal:

```bash
sudo -u www-data env MYTUBE_ADMIN_PASSWORD='another-long-unique-password' \
  php /var/www/mytube/backend/reset-admin-password-cli.php admin@mytube.home.arpa
```

If the environment variable is omitted, a random password is printed once. A reset revokes every active session for that account. Both recovery commands are CLI-only and are blocked by Apache.

## Tests

```bash
tests/run-tests.sh
tests/check-browser.py
```

Full tests require PHP with PDO SQLite, mbstring and Argon2id, sqlite3, FFmpeg/ffprobe, Python 3.13+, Node.js, tar, OpenSSL, apache2-bin and libapache2-mod-php. `tests/run-tests.sh` fails if required dependencies are missing. `tests/run-tests.sh --static` explicitly runs only syntax, locale and local-router checks. `tests/check-browser.py` additionally requires the Python Playwright package and Chromium; it checks pagination and layouts at mobile and desktop widths in all four languages and both themes, opening menus and mobile navigation. Run both the full suite and the browser check before deployment. `MYOWNTUBE_APACHE_ROOT` can point to an extracted Apache package root; `MYOWNTUBE_CHROMIUM` can select the Chromium executable, and `MYOWNTUBE_PHP_MODULE` can select an extracted Apache PHP module. All test files live under `_/temp/`.

## Documentation

- [User manual](doc/MANUAL.md)
- [Technical code guide](doc/CODE.md)
- [README for Spain](README.es-ES.md)
- [README for Argentina](README.es-AR.md)

## Data safety

Back up `/var/www/mytube/backend/db/mytube.db` with SQLite's online backup mechanism and keep `/var/www/mytube-videos` with it. Do not copy only the main SQLite file while the application is writing unless the WAL has been checkpointed. Both installers handle this backup safely.
