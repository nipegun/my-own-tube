# MyOwnTube user manual

This MANUAL is also available in:\
en-GB, [en-US](MANUAL.md), [es-AR](MANUAL.es-AR.md), [es-ES](MANUAL.es-ES.md)

## Index

1. [What you need](#1-what-you-need)
2. [Install, update or reinstall](#2-install-update-or-reinstall)
3. [First sign-in](#3-first-sign-in)
4. [Addresses](#4-addresses)
5. [Navigation and language](#5-navigation-and-language)
6. [Browse and search](#6-browse-and-search)
7. [Watch a video](#7-watch-a-video)
8. [History, liked videos and Watch later](#8-history-liked-videos-and-watch-later)
9. [Playlists](#9-playlists)
10. [Upload a video](#10-upload-a-video)
11. [Import a folder of videos](#11-import-a-folder-of-videos)
12. [Your dashboard](#12-your-dashboard)
13. [Administration dashboard](#13-administration-dashboard)
14. [Forgotten password](#14-forgotten-password)
15. [Sign out](#15-sign-out)
16. [Updates, backups and installed files](#16-updates-backups-and-installed-files)
17. [Troubleshooting](#17-troubleshooting)

## 1. What you need

- A Debian or Alpine Linux server (a virtual machine or a container is fine) where you can run commands as `root`.
- Ports **80** and **443** free on that server. Apache answers on both directly: port 80 only redirects to HTTPS.
- A DNS name that points to the server. Without one, the installer uses `myowntube.home.arpa`.
- A dedicated Apache: the installer configures Apache for MyOwnTube alone and refuses to continue if Apache already serves other sites.

The installer adds Apache, PHP (with SQLite and mbstring), the SQLite command line, FFmpeg/ffprobe, OpenSSL and curl.

## 2. Install, update or reinstall

The same command installs, updates and reinstalls. It downloads the application from the `main` branch of `https://github.com/nipegun/my-own-tube`.

**Debian**:

```bash
curl -fsSL https://raw.githubusercontent.com/nipegun/my-own-tube/refs/heads/main/deploy/install-update-reinstall-debian.sh | bash
```

**Alpine Linux** (uses `/bin/sh`, Bash is not needed). If curl is missing, run `apk add --no-cache curl ca-certificates` first:

```sh
curl -fsSL https://raw.githubusercontent.com/nipegun/my-own-tube/refs/heads/main/deploy/install-update-reinstall-alpine.sh | sh
```

Two optional environment variables change the result. Export them in the shell that runs the command (setting them only for curl does not reach the installer):

| Variable | Effect |
|---|---|
| `MYOWNTUBE_DOMAIN` | Domain of the site. If you do not set it, an update keeps the domain already configured, and a new installation uses `myowntube.home.arpa`. |
| `MYOWNTUBE_ADMIN_PASSWORD` | Password of the first administrator (12 to 4096 characters). If you do not set it, a random one is created. Existing passwords are never changed. |

Every run is safe to repeat: it keeps the database, accounts, passwords, settings, videos and any certificate that is still valid, and it does not duplicate configuration. When it finishes you will find:

- `/root/webapp-install.log`: the full log of every run.
- `/root/webapp-credentials.txt`: the passwords and accounts created or changed by the installer.

Both files can only be read by `root` (mode 600).

If the server still has an installation made before the rename to MyOwnTube (`/var/www/mytube`), the first run migrates it: it copies the database, moves the videos, reuses a valid certificate, keeps the previous credentials file, renames the default administrator address to `admin@myowntube.home.arpa` and finally moves the old files to `/var/backups/myowntube/previous-install-<date>`.

## 3. First sign-in

Open `https://<your domain>/`. Every page asks you to sign in first and, after signing in, opens the page you had requested.

Sign in with the email `admin@myowntube.home.arpa` and the password from `/root/webapp-credentials.txt` (or the one you set in `MYOWNTUBE_ADMIN_PASSWORD`). There is no built-in default password.

After eight failed attempts from the same address, signing in is blocked for 15 minutes. The recovery command in [section 14](#14-forgotten-password) also clears that block.

The certificate is self-signed: your browser will warn you the first time. Trust that certificate on your devices, or install a certificate from your organisation.

## 4. Addresses

Every section has its own address, so you can bookmark it or share it with another user of the site:

| Address | Section |
|---|---|
| `/` | Home |
| `/trending/` | Trending |
| `/search/?q=text` | Search results |
| `/watch/<video>/` | Watch a video |
| `/history/`, `/liked/`, `/watch-later/` | Your collections |
| `/playlists/`, `/playlists/<playlist>/` | Your playlists |
| `/upload/` | Upload a video |
| `/user/dashboard/profile/`, `/user/dashboard/account/` | Your dashboard |
| `/admin/dashboard/users/`, `/admin/dashboard/settings/` | Administration dashboard |
| `/auth/login/`, `/auth/register/`, `/auth/recover/` | Sign in, register, forgotten password |
| `/api/doc/` | Reference of the programming interface (API) |

`/user/dashboard/` opens the **Profile** tab and `/admin/dashboard/` opens the **Users** tab. Old addresses such as `/index.php?page=history` still work: they redirect to the new ones.

## 5. Navigation and language

The top bar contains search, **Create → Upload video**, notifications and your account menu. The side bar links to Home, Trending, History, Liked videos, Watch later and Playlists.

Choose **Language** in the account menu to use English (UK), English (US), Spanish (Argentina) or Spanish (Spain). The choice is saved in your account.

On a phone, use the menu button to open the navigation; tap outside it or press Escape to close it. The theme follows the light or dark setting of your device. Lists with more than 24 entries show **Previous** and **Next**, and their counters refer to the whole list.

## 6. Browse and search

**Home** shows the catalogue by category. **Trending** has the tabs Now, Music, Technology, Gaming and Cooking: Now lists watched videos by number of views; the other tabs rank that category by views and date.

Type in the top search box to search titles, descriptions, channels and categories. The searches you submit become private suggestions for your account. You can delete them in **Your dashboard → Account**.

## 7. Watch a video

Select a video to open it. MyOwnTube plays the original file and lets you jump to any point. Your progress is saved once the video actually starts; if you come back to an unfinished video, it continues where you left it. A view is counted once per person and video.

On the watch page you can:

- Like the video or undo it.
- Add it to Watch later or remove it.
- Add it to one of your playlists.
- Write comments and sort them by newest or oldest.
- Share the link (when the browser supports it).
- Download the original file.

## 8. History, liked videos and Watch later

**History** lists the videos you have watched, with their latest progress. Remove one entry with the cross or clear the whole history; a view already counted is not subtracted.

**Liked videos** and **Watch later** are private lists of your account. Use the buttons on the watch page, or the cross in each list, to change them.

## 9. Playlists

Open **Playlists**, type a name and an optional description and choose **Create**. Inside a playlist you can rename it, remove videos, delete it or press **Play all**: playback then continues through the list in order.

Adding the same video twice has no effect. Deleting an account also deletes its playlists, likes, history, search suggestions and Watch later list.

## 10. Upload a video

Any signed-in user can open **Upload video**, choose one file and, optionally, a title, category and description. The maximum size is 10 GiB.

Accepted extensions are `mp4`, `m4v`, `avi`, `wmv`, `3gp`, `mpg`, `mpeg`, `divx`, `mp5` and `asf`. MyOwnTube also checks with `ffprobe` that the file really contains video; anything else is rejected and nothing is kept.

After a successful upload, the video folder holds the original file, a JSON file with its metadata and, when FFmpeg can create it, a JPEG thumbnail. The video appears in the catalogue at once.

## 11. Import a folder of videos

Copy or link video files into `/var/www/myowntube-videos`, then, as an administrator, open **Upload video** and press **Sync folder**. Synchronisation checks each file, creates the missing JSON files and thumbnails and adds or updates the catalogue. Invalid files are skipped and written to the server error log.

From the server terminal:

```bash
# Debian
sudo -u www-data php /var/www/myowntube/backend/sync-videos-cli.php
# Alpine
su-exec apache:apache php84 /var/www/myowntube/backend/sync-videos-cli.php
```

To link every file of another folder without copying it, run from a copy of the repository:

```bash
sudo python3 backend/tools/generate_symlink.py /path/to/your/videos
```

If a file disappears, its video is hidden but likes, history, progress and playlists are kept. Put the file back and synchronise again to recover it.

## 12. Your dashboard

Open your account menu and choose **Your profile** or **Settings**. The dashboard has two tabs:

- **Profile** (`/user/dashboard/profile/`): your name, counters of history, likes, Watch later and playlists, the categories you watch most and your latest videos. Change your display name here.
- **Account** (`/user/dashboard/account/`): your interface language, the password change (current password plus the new one twice, 12 to 4096 characters) and the button to clear your search suggestions.

Changing your password keeps the current session open and closes all your other sessions.

## 13. Administration dashboard

Administrators see **Administration** in the account menu. The dashboard has two tabs:

- **Users** (`/admin/dashboard/users/`): create accounts, change the role (user or administrator), set a new password for another account and delete accounts. Setting a password closes every session of that account. You cannot delete your own account, and the last administrator cannot lose the role.
- **Settings** (`/admin/dashboard/settings/`): the default language for visitors and new accounts, and the switch that allows public registration at `/auth/register/`.

Public registration is off by default. Turn it on only while the people you expect create their accounts.

## 14. Forgotten password

MyOwnTube does not send email. The **Forgotten your password?** link on the sign-in page opens `/auth/recover/`, which explains the two ways back in:

- **Users**: ask an administrator to set a new password for your account in **Administration → Users**. Change it afterwards in **Your dashboard → Account**.
- **Administrators** without access: from the server terminal, reset any existing account and make it administrator:

```bash
# Debian
sudo -u www-data env MYOWNTUBE_ADMIN_PASSWORD='another-long-unique-password' \
  php /var/www/myowntube/backend/reset-admin-password-cli.php admin@myowntube.home.arpa
# Alpine
MYOWNTUBE_ADMIN_PASSWORD='another-long-unique-password' su-exec apache:apache \
  php84 /var/www/myowntube/backend/reset-admin-password-cli.php admin@myowntube.home.arpa
```

If you omit `MYOWNTUBE_ADMIN_PASSWORD`, a random password is printed once. The command also clears the sign-in blocks and closes every session of that account. It only works from the terminal.

## 15. Sign out

Choose **Sign out** in the account menu. The session is destroyed and its cookie expires. Always sign out on shared devices.

## 16. Updates, backups and installed files

Run the installer again to update. Before touching anything it stops Apache, makes a verified copy of the database in `/var/backups/myowntube` and keeps the previous version of the code as a rollback copy. If something fails, it restores the previous configuration and code and starts Apache again.

| What | Where |
|---|---|
| Application code | `/var/www/myowntube` |
| Database | `/var/www/myowntube/backend/db/myowntube.db` |
| Videos, JSON files and thumbnails | `/var/www/myowntube-videos` |
| Apache logs | `/var/www/myowntube-logs` |
| Installer backups | `/var/backups/myowntube` |
| Self-signed certificate | `/etc/ssl/myowntube` |
| Apache site (Debian) | `/etc/apache2/sites-available/000-myowntube.conf` and `/etc/apache2/ports.conf` |
| Apache site (Alpine) | `/etc/apache2/httpd.conf`, `/etc/apache2/myowntube-site.conf` and `/etc/apache2/myowntube-ports.conf` |
| Installer log and credentials | `/root/webapp-install.log` and `/root/webapp-credentials.txt` |

The code belongs to `root`; only the database, videos and logs can be written by the web user (`www-data` on Debian, `apache` on Alpine).

Keep your own backups of the database **and** of the video folder: one is useless without the other. Copy the database with SQLite's backup command rather than copying the file while the site is in use:

```bash
sqlite3 /var/www/myowntube/backend/db/myowntube.db ".backup '/root/myowntube-backup.db'"
```

## 17. Troubleshooting

### The page says that the database is not initialised

Create it as the web user with a strong administrator password:

```bash
sudo -u www-data env MYOWNTUBE_ADMIN_PASSWORD='replace-with-a-long-unique-password' \
  php /var/www/myowntube/backend/seed.php
```

### A file is not imported

Check that its extension is accepted, that the web user can read it, that it is not larger than 10 GiB and that `ffprobe` recognises it. Details are in `/var/www/myowntube-logs/error.log`.

### The browser rejects HTTPS

Trust the generated certificate on your devices or install one from your organisation. Do not get used to skipping certificate warnings.

### Apache does not start after installing

Another service is probably using port 80 or 443 (for example a previous HAProxy). Stop it, then run the installer again. The installer log explains which step failed.

### The site shows a generic error

Detailed database and page errors are kept out of the browser on purpose. Read `/var/www/myowntube-logs/error.log`.
