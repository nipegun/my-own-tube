# MyOwnTube user manual

## 1. First sign-in

Open the HTTPS address configured during installation. MyOwnTube redirects every unauthenticated request to the sign-in page.

Use the administrator email `admin@mytube.home.arpa` and the password supplied through `MYTUBE_ADMIN_PASSWORD` or stored by the installer in `/root/app-web-credentials.txt` (readable only by root). There is no built-in default password.

After eight failed attempts from the same client address, sign-in is blocked for 15 minutes. An administrator with terminal access can clear the block by running the password recovery command described in section 12.

## 2. Navigation and language

The top bar contains search, upload, notifications, and the user menu. The sidebar links to Home, Trending, History, Liked videos, Watch Later, and Playlists.

Choose **Language** in the user menu to use English (UK), English (US), Spanish (Argentina), or Spanish (Spain). Your choice is saved to your account. Administrators can also choose the default language for new accounts in **Settings**.

On mobile, use the menu button to open navigation. Tap outside it or press Escape to close it. The theme follows the light/dark setting of your operating system. Lists offer **Previous** and **Next** when they contain more than 24 entries; their counters refer to the complete filtered collection.

## 3. Browse and search

**Home** shows the catalogue by category. **Trending** offers Now, Music, Technology, Gaming, and Cooking tabs. Now lists watched videos by view count; the other tabs rank the selected category by views and publication date.

Type a query in the top search box. Results match video titles, descriptions, channels, and categories. Submitted searches appear as suggestions and are private to your account. Remove all of them from **Settings → Clear search history**.

## 4. Watch a video

Select a card to open the watch page. MyOwnTube streams the original file and supports seeking through HTTP byte ranges. Playback progress is saved only after the video actually starts. Returning to an unfinished video resumes the saved position; progress near the end is treated as completed.

A view is counted once per user and video, when the first progress record is created. Repeated progress updates do not increase the view count.

On the watch page you can:

- Like or unlike the video.
- Add or remove it from Watch Later.
- Add it to, or remove it from, one of your playlists.
- Post comments and sort comments newest or oldest.
- Copy a share link through the browser share interface when available.
- Download the original file.

## 5. History, likes, and Watch Later

**History** lists watched videos and their latest progress. Remove one entry or clear the whole history. Clearing an entry does not subtract an already counted view.

**Liked videos** and **Watch Later** are private, per-account collections. Use the controls on a video card or watch page to update them.

## 6. Playlists

Open **Playlists** and choose **New playlist**. Enter a name and an optional description. Open a playlist to edit its details, remove videos, delete it, or start **Play all**; playback then advances through the remaining items in order.

Adding the same video more than once has no effect. Removing an item closes position gaps so the remaining order stays consistent. Deleting a user also deletes that user's playlists, likes, history, search history, and Watch Later entries.

## 7. Upload a video

Any signed-in user can open **Upload** and choose one file, a title, category, and description. The maximum configured size is 10 GiB.

Permitted filename extensions are `mp4`, `m4v`, `avi`, `wmv`, `3gp`, `mpg`, `mpeg`, `divx`, `mp5`, and `asf`. MyOwnTube then uses `ffprobe` to verify that the content contains a real video stream. A renamed HTML or other non-video file is rejected and its partial files are removed.

On success MyOwnTube writes three related files into the video directory:

- The original video.
- A JSON sidecar containing technical and descriptive metadata.
- A JPEG thumbnail when FFmpeg can generate one.

The video is then added to the catalogue. File names are normalised to a safe base name and made unique without overwriting existing data.

## 8. Import a folder of videos

Administrators can select **Upload video → Sync folder** after placing files in `/var/www/mytube-videos`. Synchronisation validates each supported file, creates missing JSON sidecars and thumbnails, and inserts or updates catalogue rows. Invalid files are reported as errors, written to the server error log, and left out of the catalogue.

When a previously imported file is absent, its catalogue row, likes, history, progress, and playlist membership are retained, but it is hidden and cannot be played. Restore the file and synchronise again to make it available without losing that state.

For unattended maintenance, run:

```bash
sudo -u www-data php /var/www/mytube/backend/sync-videos-cli.php
```

## 9. Profile and password

Open **Your profile** to change your display name. All user-supplied text is displayed as text rather than interpreted as HTML.

Open **Settings → Change password**. Enter the current password and a new password twice. Passwords must contain 12 to 4096 characters. A successful change rotates the current session identifier and revokes every other active session for that account.

## 10. Administrator settings

Only administrators can open the user administration page. They can create accounts, promote or demote users, and delete accounts. MyOwnTube prevents deleting your own signed-in account and prevents removing the final administrator role.

In **Settings**, an administrator can:

- Set the default interface language for new users.
- Enable or disable public registration.

Registration is disabled by default. When it is enabled, the registration page creates standard users and signs them in. Disable it again after the intended users have joined a private deployment.

## 11. Sign out

Choose **Sign out** from the user menu. MyOwnTube destroys the current session, expires its cookie, and creates a clean session identifier. Shared devices should always be signed out after use.

## 12. Recover administrator access

On the Debian server, reset any existing account and promote it to administrator:

```bash
sudo -u www-data env MYTUBE_ADMIN_PASSWORD='another-long-unique-password' \
  php /var/www/mytube/backend/reset-admin-password-cli.php admin@mytube.home.arpa
```

If `MYTUBE_ADMIN_PASSWORD` is omitted, the command generates and prints a random password once. It also clears login throttles and revokes every active session for that account. The command works only from the CLI and cannot be opened through the website.

The maintenance examples below use Debian. On Alpine, run the same PHP file as `su-exec apache:apache php84` instead of `sudo -u www-data php`. For recovery, put `MYTUBE_ADMIN_PASSWORD=...` before `su-exec`; the environment variable is passed to PHP. To synchronise from the Alpine server:

```bash
su-exec apache:apache php84 /var/www/mytube/backend/sync-videos-cli.php
```

## 13. Updates and backups

Run the installer for your distribution again to update. Before changing the deployed directory, it stops Apache and stores an integrity-checked SQLite backup below `/var/backups/mytube`. Existing videos remain in `/var/www/mytube-videos`, and the previous web tree is retained as a rollback copy. The installer requires a dedicated Apache instance and refuses to alter one with unrelated enabled sites.

Keep independent backups of both the SQLite database and the video directory. A catalogue backup without its video and JSON sidecar files cannot play imported media.

The standalone installers are `deploy/install-update-reinstall-debian.sh` and `deploy/install-update-reinstall-alpine.sh`. Apache listens only on `127.0.0.1:11080` (HTTP) and `127.0.0.1:11443` (HTTPS with TLS enabled). HAProxy owns the public 80/443 ports and sends PROXY protocol to the HTTPS backend. HTTP redirects to the public HTTPS URL, which HAProxy forwards to 11443; the internal backend port is not exposed in browser URLs.

Before changing configuration, the installer records the previous files in a private backup directory. New certificates are generated and validated before activation. A later failure restores certificate/key, PHP settings, Apache listeners, site/module links and the previous web tree before attempting to restart Apache. Recovery errors are logged instead of silently ignored. Before committing the deployment, the installer checks local HTTPS through PROXY protocol, validating the certificate and application response.

`MYTUBE_ADMIN_PASSWORD` must contain between 12 and 4096 characters. If it is omitted for a new or historical-default account, a random password is generated. The installer stores its CLI credential output in `/root/app-web-credentials.txt`, separate from `/root/app-web-install.log`; both files have mode 0600. Direct maintenance CLI commands still print their credentials to the terminal.

The installer deploys `frontend/` and `backend/` together under `/var/www/mytube`. The database now lives at `/var/www/mytube/backend/db/mytube.db`; `/var/www/mytube/backend/videos` links to the existing `/var/www/mytube-videos` directory. Updates copy the former `/var/www/mytube/db/mytube.db` through a verified SQLite backup and preserve media. Rollback restores the former directory structure and Apache configuration.

Only `frontend/public/` is the HTTP document root. Views, components and translations remain in private frontend directories; PHP controllers, actions, configuration, database and media belong to the backend. The public entry point dispatches the existing `/index.php`, `/actions/*.php`, `/media.php` and `/thumb.php` URLs without exposing backend files.

Both installers are idempotent for application state: repeated runs preserve existing accounts, password hashes, settings, playlists, login throttles and media. Valid matching certificates with more than 30 days remaining are reused. Configuration files with identical content are not rewritten, and credentials are recorded only when created or rotated. An exclusive lock prevents concurrent deployments; service processes do not inherit that lock. Each execution still appends its audit log and creates recovery backups. A changed code archive, requested domain or available package version is treated as an update.

On Alpine, the installer uses `apk`, PHP 8.4, the `apache` user/group, and OpenRC. In containers without a booted OpenRC instance, it controls `httpd` directly while registering the startup service with `rc-update`. It manages `/etc/apache2/httpd.conf`, `/etc/apache2/mytube-ports.conf`, `/etc/apache2/mytube-site.conf` and `/etc/php84/conf.d/99-mytube.ini`; it does not append repeated includes or listeners. Private sessions live in `/var/lib/mytube/sessions`. The default package SSL configuration is superseded by the managed configuration, so Apache only listens on the two loopback backend ports.

## 14. Troubleshooting

### The database is not initialised

Run `seed.php` as the web user, supplying a strong administrator password:

```bash
sudo -u www-data env MYTUBE_ADMIN_PASSWORD='replace-with-a-long-unique-password' \
  php /var/www/mytube/backend/seed.php
```

### A file is not imported

Confirm its extension is supported, it is readable by `www-data`, it is no larger than 10 GiB, and `ffprobe` recognises a video stream. Check `/var/www/mytube-logs/error.log` for server-side details.

### The browser rejects HTTPS

Trust the generated certificate on the client or install a certificate issued by your organisation. Do not bypass certificate warnings for a production service.

### Direct connections fail before HTTP is processed

The installed HTTPS virtual host expects HAProxy PROXY protocol. Confirm that the upstream proxy sends it, or have the server administrator adapt `RemoteIPProxyProtocol` to the actual network topology and re-run `apache2ctl configtest`.

### The application reports a generic error

Detailed database and rendering errors are intentionally kept out of web responses. Review the Apache error log instead; this avoids disclosing paths or SQL details to users.

A normal `php backend/seed.php` creates the administrator, configuration and navigation categories, with an empty video catalogue. `php backend/seed.php --demo` explicitly adds sample metadata, kept unavailable until matching media is imported. Startup migrations also hide old entries without a media path while retaining their rows and user state. Stop web traffic before using `--force` to replace an existing database.
