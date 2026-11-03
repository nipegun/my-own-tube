#!/bin/sh

set -eu
if (set -o pipefail) 2>/dev/null; then
  set -o pipefail
fi

readonly cWebUser="apache"
readonly cWebGroup="apache"
readonly cRepoUrl="https://github.com/nipegun/my-own-tube"
readonly cScriptUrl="https://raw.githubusercontent.com/nipegun/my-own-tube/refs/heads/main/deploy/install-update-reinstall-alpine.sh"
readonly cArchiveUrl="${cRepoUrl}/archive/refs/heads/main.tar.gz"
readonly cDefaultDomain="myowntube.home.arpa"
readonly cWebDir="/var/www/myowntube"
readonly cPublicDir="${cWebDir}/frontend/public"
readonly cLogsDir="/var/www/myowntube-logs"
readonly cVideosDir="/var/www/myowntube-videos"
readonly cDbDir="${cWebDir}/backend/db"
readonly cDbFileName="myowntube.db"
readonly cDbFile="${cDbDir}/${cDbFileName}"
readonly cVideosLink="${cWebDir}/backend/videos"
readonly cBackupDir="/var/backups/myowntube"
readonly cCertificateDir="/etc/ssl/myowntube"
readonly cCertificateFile="${cCertificateDir}/myowntube.crt"
readonly cCertificateKeyFile="${cCertificateDir}/myowntube.key"
readonly cApacheSite="000-myowntube"
readonly cApacheSiteFile="/etc/apache2/myowntube-site.conf"
readonly cApacheMainFile="/etc/apache2/httpd.conf"
readonly cSessionDir="/var/lib/myowntube/sessions"
readonly cApachePortsFile="/etc/apache2/myowntube-ports.conf"
readonly cInstallLog="/root/webapp-install.log"
readonly cCredentialsFile="/root/webapp-credentials.txt"
readonly cLockFile="/run/lock/myowntube-install.lock"
# Installation made before the rename to MyOwnTube. Its data is migrated and
# its files are retired into the backup directory after a successful update.
readonly cLegacyDefaultDomain="mytube.home.arpa"
readonly cLegacyWebDir="/var/www/mytube"
readonly cLegacyLogsDir="/var/www/mytube-logs"
readonly cLegacyVideosDir="/var/www/mytube-videos"
readonly cLegacyDbFileName="mytube.db"
readonly cLegacyBackupDir="/var/backups/mytube"
readonly cLegacyCertificateDir="/etc/ssl/mytube"
readonly cLegacyApacheSiteFile="/etc/apache2/mytube-site.conf"
readonly cLegacyApachePortsFile="/etc/apache2/mytube-ports.conf"
readonly cLegacySessionDir="/var/lib/mytube/sessions"
readonly cLegacyInstallLog="/root/app-web-install.log"
readonly cLegacyCredentialsFile="/root/app-web-credentials.txt"
readonly cPackages="apache2 apache2-ssl apache2-openrc openrc ca-certificates coreutils curl ffmpeg findutils flock openssl php84 php84-apache2 php84-ctype php84-fileinfo php84-mbstring php84-openssl php84-pdo_sqlite php84-posix php84-session php84-sqlite3 sqlite su-exec tar"

vTmpDir=""
vProjectSource=""
vPhpVersion=""
vPhpApacheModule=""
vStageWebDir=""
vPreviousWebDir=""
vDbBackupDir=""
vDeploymentPending=0
vRestartServiceOnCleanup=0
vServiceStateCaptured=0
vServiceWasRunning=0
vConfigBackupDir=""
vConfigRollbackPending=0
vCertificateStageDir=""
vCredentialsOutput=""
vConfigCount=0
vLogDirectory=""
vLogPid=""
vLogStarted=0

# MYOWNTUBE_DOMAIN wins. Otherwise an update keeps the ServerName already
# configured, except the old default name, which moves to the new default.
fDetectDomain() {
  local vDomain=""
  local vSiteFile=""

  if [ -n "${MYOWNTUBE_DOMAIN:-}" ]; then
    printf '%s' "$MYOWNTUBE_DOMAIN"
    return 0
  fi
  for vSiteFile in /etc/apache2/myowntube-site.conf /etc/apache2/mytube-site.conf; do
    if [ -f "$vSiteFile" ]; then
      vDomain="$(sed -n 's/^[[:space:]]*ServerName[[:space:]]\{1,\}\([^[:space:]]\{1,\}\)[[:space:]]*$/\1/p' "$vSiteFile" | sed -n '1p')"
      if [ -n "$vDomain" ]; then
        break
      fi
    fi
  done
  if [ -z "$vDomain" ] || [ "$vDomain" = "$cLegacyDefaultDomain" ]; then
    vDomain="$cDefaultDomain"
  fi
  printf '%s' "$vDomain"
}

cDomain="$(fDetectDomain)"
readonly cDomain

fRequireRoot() {
  if [ "$(id -u)" -ne 0 ]; then
    fError "Run the downloaded installer as root."
  fi
}

fAcquireLock() {
  if ! command -v flock >/dev/null 2>&1; then
    if [ ! -r /etc/alpine-release ] || ! command -v apk >/dev/null 2>&1; then
      fError "Alpine Linux with apk is required to install flock."
    fi
    apk add --no-cache flock 7>&- 8>&- 9>&- || return 1
  fi
  mkdir -p "$(dirname "$cLockFile")" || return 1
  exec 9> "$cLockFile" || return 1
  chmod 0600 "$cLockFile" || return 1
  if ! flock -n 9; then
    fError "Another MyOwnTube installation is already running."
  fi
}

fActivateConfigFile() {
  local pStage="$1"
  local pTarget="$2"

  if [ -f "$pTarget" ] && cmp -s "$pStage" "$pTarget"; then
    rm -f "$pStage" || return 1
    return 0
  fi
  fBackupConfigFile "$pTarget" || return 1
  mv "$pStage" "$pTarget" || return 1
}

fPrepareLogs() {
  umask 077
  if [ -f "$cLegacyCredentialsFile" ] && [ ! -s "$cCredentialsFile" ]; then
    mv "$cLegacyCredentialsFile" "$cCredentialsFile" || return 1
  fi
  if [ -f "$cLegacyInstallLog" ] && [ ! -s "$cInstallLog" ]; then
    mv "$cLegacyInstallLog" "$cInstallLog" || return 1
  fi
  touch "$cInstallLog" "$cCredentialsFile" || return 1
  chmod 0600 "$cInstallLog" "$cCredentialsFile" || return 1
  vLogDirectory="$(mktemp -d /tmp/myowntube-log.XXXXXXXX)" || return 1
  mkfifo "${vLogDirectory}/output" || return 1
  exec 8>&1 7>&2
  tee -a "$cInstallLog" < "${vLogDirectory}/output" >&8 7>&- 8>&- 9>&- &
  vLogPid=$!
  exec > "${vLogDirectory}/output" 2>&1
  vLogStarted=1
  rm -f "${vLogDirectory}/output" || return 1
  rmdir "$vLogDirectory" || return 1
  vLogDirectory=""
}

fBackupConfigFile() {
  local pPath="$1"
  local vEntry=""
  local vBackup=""

  if [ -z "$vConfigBackupDir" ]; then
    vConfigBackupDir="$(mktemp -d "${cBackupDir}/config-XXXXXXXX")" || return 1
    chmod 0700 "$vConfigBackupDir" || return 1
  fi
  if [ -f "${vConfigBackupDir}/paths" ] && grep -Fqx -e "$pPath" "${vConfigBackupDir}/paths"; then
    return 0
  fi
  vEntry="${vConfigBackupDir}/${vConfigCount}"
  mkdir -p "$vEntry" || return 1
  printf '%s\n' "$pPath" > "${vEntry}/path" || return 1
  if [ -e "$pPath" ] || [ -L "$pPath" ]; then
    vBackup="${vEntry}/original"
    cp -a "$pPath" "$vBackup" || return 1
  fi
  printf '%s\t%s\n' "$pPath" "$vBackup" >> "${vConfigBackupDir}/manifest.tsv" || return 1
  printf '%s\n' "$pPath" >> "${vConfigBackupDir}/paths" || return 1
  vConfigCount=$((vConfigCount + 1))
  vConfigRollbackPending=1
}

fRestoreConfigFiles() {
  local vIndex=$((vConfigCount - 1))
  local vFailed=0
  local vEntry=""
  local vPath=""

  while [ "$vIndex" -ge 0 ]; do
    vEntry="${vConfigBackupDir}/${vIndex}"
    vPath="$(cat "${vEntry}/path")" || return 1
    if [ -e "${vEntry}/original" ] || [ -L "${vEntry}/original" ]; then
      if ! cp -a --remove-destination "${vEntry}/original" "$vPath"; then
        fLog "ERROR: Could not restore ${vPath}. Backup: ${vEntry}/original"
        vFailed=1
      fi
    elif ! rm -f "$vPath"; then
      fLog "ERROR: Could not remove new configuration ${vPath}."
      vFailed=1
    fi
    vIndex=$((vIndex - 1))
  done
  return "$vFailed"
}

fLog() {
  local pMessage="$1"

  printf '[MyOwnTube] %s\n' "$pMessage"
}

fError() {
  local pMessage="$1"

  printf '[MyOwnTube] ERROR: %s\n' "$pMessage" >&2
  exit 1
}

fRollback() {
  local vCleanupFailed=0

  if [ "$vRestartServiceOnCleanup" -eq 1 ] && { [ "$vConfigRollbackPending" -eq 1 ] || [ "$vDeploymentPending" -eq 1 ]; }; then
    if ! fStopService apache2; then
      fLog "ERROR: Apache could not stop for rollback. Backups are preserved at ${cBackupDir}."
      return 1
    fi
  fi
  if [ "$vConfigRollbackPending" -eq 1 ]; then
    if ! fRestoreConfigFiles; then
      vCleanupFailed=1
    fi
  fi
  if [ "$vDeploymentPending" -eq 1 ]; then
    local vFailedWebDir="${cBackupDir}/failed-web-$(date +%Y%m%d_%H%M%S)-$$"
    local vCanRestore=1

    if [ -e "$cWebDir" ] || [ -L "$cWebDir" ]; then
      if ! mv "$cWebDir" "$vFailedWebDir"; then
        vCanRestore=0
      fi
    fi
    if [ -n "$vPreviousWebDir" ] && [ -d "$vPreviousWebDir" ]; then
      if [ "$vCanRestore" -eq 1 ] && mv "$vPreviousWebDir" "$cWebDir"; then
        fLog "The previous web directory was restored after an installation failure."
      else
        fLog "The previous web directory could not be restored automatically: ${vPreviousWebDir}"
      fi
    else
      fLog "The failed web directory was preserved at ${vFailedWebDir}."
    fi
  fi

  case "$vStageWebDir" in
    /var/www/.myowntube-stage.*) rm -rf "$vStageWebDir" || true ;;
  esac
  case "$vTmpDir" in
    /tmp/myowntube-install.*) rm -rf "$vTmpDir" || true ;;
  esac
  case "$vCertificateStageDir" in
    "${cCertificateDir}/.new-"*) rm -rf "$vCertificateStageDir" || true ;;
  esac
  if [ "$vRestartServiceOnCleanup" -eq 1 ] && [ "$vServiceWasRunning" -eq 1 ]; then
    if ! fRestartService apache2; then
      fLog "ERROR: Apache could not restart after rollback. Inspect ${cInstallLog}."
      vCleanupFailed=1
    fi
  fi
  return "$vCleanupFailed"
}

fCloseLogs() {
  local vLogStatus=0

  if [ "$vLogStarted" -eq 1 ]; then
    exec 1>&8 2>&7
    exec 8>&- 7>&-
    vLogStarted=0
    wait "$vLogPid" || vLogStatus=1
  fi
  case "$vLogDirectory" in
    /tmp/myowntube-log.*) rm -rf "$vLogDirectory" || vLogStatus=1 ;;
  esac
  return "$vLogStatus"
}

fCleanup() {
  local vCleanupStatus=0

  fRollback || vCleanupStatus=1
  fCloseLogs || vCleanupStatus=1
  return "$vCleanupStatus"
}

fValidateEnvironment() {
  fRequireRoot || return 1
  if [ ! -r /etc/alpine-release ] || ! command -v apk >/dev/null 2>&1; then
    fError "This installer requires Alpine Linux with apk."
  fi
  case "$cDomain" in
    ''|*[!A-Za-z0-9.-]*) fError "MYOWNTUBE_DOMAIN is not a valid DNS name." ;;
  esac
  if [ "${#cDomain}" -gt 253 ] || ! printf '%s\n' "$cDomain" | grep -Eq '^([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)*[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?$'; then
    fError "MYOWNTUBE_DOMAIN is not a valid DNS name."
  fi
  fValidateApacheIsolation
}

fValidateApacheIsolation() {
  local vSitePath=""

  if [ -f "$cApacheMainFile" ] && grep -q '^# Managed by MyOwnTube (Alpine)\.$' "$cApacheMainFile"; then
    return 0
  fi
  for vSitePath in /etc/apache2/httpd.conf /etc/apache2/conf.d/*.conf; do
    if [ ! -f "$vSitePath" ]; then
      continue
    fi
    if grep -Eq '^[[:space:]]*<VirtualHost' "$vSitePath"; then
      if [ "$vSitePath" = /etc/apache2/conf.d/ssl.conf ] \
        && grep -Eq '^ServerName[[:space:]]+www\.example\.com:443[[:space:]]*$' "$vSitePath" \
        && grep -Eq '^DocumentRoot[[:space:]]+"/var/www/localhost/htdocs"[[:space:]]*$' "$vSitePath"; then
        continue
      fi
      fError "Another Apache site is configured in ${vSitePath}. A dedicated Apache instance is required."
    fi
  done
}

fInstallPackages() {
  fLog "Installing Alpine Apache, PHP 8.4, OpenRC, and required extensions."
  apk update 7>&- 8>&- 9>&- || return 1
  apk add --no-cache --upgrade $cPackages 7>&- 8>&- 9>&- || return 1
  if ! id "$cWebUser" >/dev/null 2>&1; then
    fError "The apache system user was not created by the Apache package."
  fi
}

fPrepareDirectories() {
  fLog "Preparing directories in /var/www."

  mkdir -p "$cLogsDir" "$cVideosDir" "$cBackupDir" || return 1
  touch "${cLogsDir}/access.log" "${cLogsDir}/error.log" || return 1
  chown -R "${cWebUser}:${cWebGroup}" "$cLogsDir" "$cVideosDir" || return 1
  chown root:root "$cBackupDir" || return 1
  chmod 0750 "$cLogsDir" "$cVideosDir" || return 1
  chmod 0640 "${cLogsDir}/access.log" "${cLogsDir}/error.log" || return 1
  chmod 0700 "$cBackupDir" || return 1
}

fDownloadCode() {
  local vArchiveFile=""
  local vExtractDir=""
  local vPublicEntry=""

  fLog "Downloading code from ${cRepoUrl}."

  vTmpDir="$(mktemp -d /tmp/myowntube-install.XXXXXXXX)" || return 1
  vArchiveFile="${vTmpDir}/myowntube.tar.gz"
  vExtractDir="${vTmpDir}/src"

  mkdir -p "$vExtractDir" || return 1
  curl --proto '=https' --tlsv1.2 -fsSL "$cArchiveUrl" -o "$vArchiveFile" || return 1
  tar -tzf "$vArchiveFile" >/dev/null || return 1
  if [ -n "$(tar -tzf "$vArchiveFile" | sed -n '/^\//p;/\.\.\//p' | sed -n '1p')" ]; then
    fError "The downloaded archive contains an unsafe path."
  fi
  tar -xzf "$vArchiveFile" -C "$vExtractDir" || return 1

  vPublicEntry="$(find "$vExtractDir" -type f -path '*/frontend/public/index.php' | sed -n '1p')"
  vProjectSource="${vPublicEntry%/frontend/public/index.php}"
  if [ -z "$vPublicEntry" ] || [ ! -f "${vProjectSource}/backend/config.php" ] || [ ! -f "${vProjectSource}/backend/router.php" ]; then
    fError "The frontend/public and backend directories were not found in the downloaded repository."
  fi
}

fSaveCurrentData() {
  local vDatabaseSource=""
  local vIntegrityResult=""
  local vTimestamp=""

  vTimestamp="$(date +%Y%m%d_%H%M%S)"
  vDbBackupDir="${cBackupDir}/db-${vTimestamp}-$$"
  mkdir -p "$vDbBackupDir" || return 1
  chmod 0700 "$vDbBackupDir" || return 1

  if [ -f "$cDbFile" ]; then
    vDatabaseSource="$cDbFile"
  elif [ -f "${cLegacyWebDir}/backend/db/${cLegacyDbFileName}" ]; then
    fLog "Migrating the database of the previous installation from ${cLegacyWebDir}."
    vDatabaseSource="${cLegacyWebDir}/backend/db/${cLegacyDbFileName}"
  elif [ -f "${cLegacyWebDir}/db/${cLegacyDbFileName}" ]; then
    fLog "Migrating the previous database into backend/db."
    vDatabaseSource="${cLegacyWebDir}/db/${cLegacyDbFileName}"
  elif [ -f /var/lib/mytube/db/mytube.db ]; then
    fLog "Migrating previous database from /var/lib/mytube/db."
    vDatabaseSource="/var/lib/mytube/db/mytube.db"
  elif [ -f /opt/mytube/app-web/db/mytube.db ]; then
    fLog "Migrating previous database from /opt/mytube/app-web/db."
    vDatabaseSource="/opt/mytube/app-web/db/mytube.db"
  elif [ -f /opt/mytube/web-app/db/mytube.db ]; then
    fLog "Migrating previous database from /opt/mytube/web-app/db."
    vDatabaseSource="/opt/mytube/web-app/db/mytube.db"
  elif [ -f /opt/mytube/webapp/db/mytube.db ]; then
    fLog "Migrating previous database from /opt/mytube/webapp/db."
    vDatabaseSource="/opt/mytube/webapp/db/mytube.db"
  fi

  if [ -n "$vDatabaseSource" ]; then
    fLog "Creating a consistent and persistent database backup."
    sqlite3 "$vDatabaseSource" ".timeout 5000" ".backup '${vDbBackupDir}/${cDbFileName}'" || return 1
    vIntegrityResult="$(sqlite3 "${vDbBackupDir}/${cDbFileName}" 'PRAGMA quick_check;')" || return 1
    if [ "$vIntegrityResult" != "ok" ]; then
      fError "The database backup failed its integrity check."
    fi
    chmod 0600 "${vDbBackupDir}/${cDbFileName}" || return 1
  fi

  if [ -n "$(find "$cVideosDir" -mindepth 1 -maxdepth 1 2>/dev/null | sed -n '1p')" ]; then
    fLog "Keeping existing videos from ${cVideosDir}."
  elif [ -d "$cLegacyVideosDir" ] && [ ! -L "$cLegacyVideosDir" ]; then
    # Moving keeps the disk usage flat; the link keeps the previous
    # installation working if this run has to roll back.
    fLog "Moving the videos of the previous installation from ${cLegacyVideosDir}."
    rmdir "$cVideosDir" || return 1
    mv "$cLegacyVideosDir" "$cVideosDir" || return 1
    ln -s "$cVideosDir" "$cLegacyVideosDir" || return 1
  elif [ -d "$cVideosLink" ] && [ ! -L "$cVideosLink" ]; then
    fLog "Migrating previous videos from ${cVideosLink}."
    cp -a "${cVideosLink}/." "$cVideosDir/" || return 1
  elif [ -d "${cLegacyWebDir}/backend/videos" ] && [ ! -L "${cLegacyWebDir}/backend/videos" ]; then
    fLog "Migrating previous videos from ${cLegacyWebDir}/backend/videos."
    cp -a "${cLegacyWebDir}/backend/videos/." "$cVideosDir/" || return 1
  elif [ -d "${cLegacyWebDir}/videos" ] && [ ! -L "${cLegacyWebDir}/videos" ]; then
    fLog "Migrating previous videos from ${cLegacyWebDir}/videos."
    cp -a "${cLegacyWebDir}/videos/." "$cVideosDir/" || return 1
  elif [ -d /var/lib/mytube/videos ]; then
    fLog "Migrating previous videos from /var/lib/mytube/videos."
    cp -a /var/lib/mytube/videos/. "$cVideosDir/" || return 1
  elif [ -d /opt/mytube/app-web/videos ]; then
    fLog "Migrating previous videos from /opt/mytube/app-web/videos."
    cp -a /opt/mytube/app-web/videos/. "$cVideosDir/" || return 1
  elif [ -d /opt/mytube/web-app/videos ]; then
    fLog "Migrating previous videos from /opt/mytube/web-app/videos."
    cp -a /opt/mytube/web-app/videos/. "$cVideosDir/" || return 1
  elif [ -d /opt/mytube/webapp/videos ]; then
    fLog "Migrating previous videos from /opt/mytube/webapp/videos."
    cp -a /opt/mytube/webapp/videos/. "$cVideosDir/" || return 1
  fi
}

fInstallCode() {
  local vIntegrityResult=""
  local vTimestamp=""

  fLog "Preparing an atomic deployment for ${cWebDir}."

  vStageWebDir="/var/www/.myowntube-stage.$$"
  if [ -e "$vStageWebDir" ]; then
    fError "The staging directory already exists: ${vStageWebDir}"
  fi
  mkdir -p "$vStageWebDir" || return 1
  cp -a "${vProjectSource}/frontend" "${vProjectSource}/backend" "$vStageWebDir/" || return 1

  rm -rf "${vStageWebDir}/backend/.claude" "${vStageWebDir}/frontend/.claude" "${vStageWebDir}/backend/db" "${vStageWebDir}/backend/videos" || return 1
  mkdir -p "${vStageWebDir}/backend/db" || return 1
  cp -a "${vDbBackupDir}/." "${vStageWebDir}/backend/db/" || return 1
  ln -s "$cVideosDir" "${vStageWebDir}/backend/videos" || return 1

  chown -R root:root "$vStageWebDir" || return 1
  chown -R "${cWebUser}:${cWebGroup}" "${vStageWebDir}/backend/db" "$cLogsDir" "$cVideosDir" || return 1
  chown -h root:root "${vStageWebDir}/backend/videos" || return 1
  find "$vStageWebDir" -type d -exec chmod 0755 {} \; || return 1
  find "$vStageWebDir" -type f -exec chmod 0644 {} \; || return 1
  chmod 0750 "${vStageWebDir}/backend/db" || return 1
  chmod 0750 "$cVideosDir" "$cLogsDir" || return 1
  chmod 0640 "${cLogsDir}/access.log" "${cLogsDir}/error.log" || return 1

  if [ -f "${vStageWebDir}/backend/db/${cDbFileName}" ]; then
    vIntegrityResult="$(sqlite3 "${vStageWebDir}/backend/db/${cDbFileName}" 'PRAGMA quick_check;')" || return 1
    if [ "$vIntegrityResult" != "ok" ]; then
      fError "The staged database failed its integrity check."
    fi
    chmod 0640 "${vStageWebDir}/backend/db/${cDbFileName}" || return 1
  fi

  vTimestamp="$(date +%Y%m%d_%H%M%S)"
  if [ -e "$cWebDir" ] || [ -L "$cWebDir" ]; then
    vPreviousWebDir="${cBackupDir}/web-${vTimestamp}-$$"
    mv "$cWebDir" "$vPreviousWebDir" || return 1
  fi

  vDeploymentPending=1
  if ! mv "$vStageWebDir" "$cWebDir"; then
    if [ -n "$vPreviousWebDir" ] && [ -d "$vPreviousWebDir" ]; then
      if mv "$vPreviousWebDir" "$cWebDir"; then
        vDeploymentPending=0
      fi
    else
      vDeploymentPending=0
    fi
    fError "Could not activate the staged web directory."
  fi
  vStageWebDir=""
}

fDetectPhp() {
  vPhpVersion="$(php84 -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')" || return 1
  vPhpApacheModule="/usr/lib/apache2/mod_php84.so"
  if [ ! -f "$vPhpApacheModule" ]; then
    fError "Install php84-apache2 from the Alpine community repository."
  fi
  if ! php84 -r 'exit(defined("PASSWORD_ARGON2ID") && extension_loaded("pdo_sqlite") && extension_loaded("mbstring") && extension_loaded("session") && extension_loaded("ctype") ? 0 : 1);'; then
    fError "PHP 8.4 requires Argon2id, PDO SQLite, mbstring, session, and ctype support."
  fi
}

fConfigurePhp() {
  local vPhpConfigDir="/etc/php84/conf.d"
  local vPhpConfigFile="${vPhpConfigDir}/99-myowntube.ini"
  local vLegacyPhpConfigFile="${vPhpConfigDir}/99-mytube.ini"
  local vPhpStageFile="${vPhpConfigFile}.new.$$"

  if [ ! -d "$vPhpConfigDir" ]; then
    fError "The Apache PHP configuration directory was not found: ${vPhpConfigDir}"
  fi

  mkdir -p "$cSessionDir" || return 1
  # The installer runs with umask 077: the parent must stay traversable by Apache.
  chmod 0755 "$(dirname "$cSessionDir")" || return 1
  chown "${cWebUser}:${cWebGroup}" "$cSessionDir" || return 1
  chmod 0700 "$cSessionDir" || return 1

  printf '%s\n' \
    "session.save_path = ${cSessionDir}" \
    'display_errors = Off' \
    'log_errors = On' \
    'expose_php = Off' \
    'upload_max_filesize = 10G' \
    'post_max_size = 11G' \
    'max_file_uploads = 1' \
    'max_input_vars = 100' \
    'max_multipart_body_parts = 20' \
    'max_execution_time = 0' \
    'max_input_time = -1' \
    'memory_limit = 256M' \
    > "$vPhpStageFile" || return 1
  chmod 0644 "$vPhpStageFile" || return 1
  fActivateConfigFile "$vPhpStageFile" "$vPhpConfigFile" || return 1
  if [ -e "$vLegacyPhpConfigFile" ]; then
    fBackupConfigFile "$vLegacyPhpConfigFile" || return 1
    rm -f "$vLegacyPhpConfigFile" || return 1
  fi
}

fCreateCertificate() {
  local vCertificatePublicKey=""
  local vPrivatePublicKey=""

  mkdir -p "$cCertificateDir" || return 1
  chmod 0755 "$cCertificateDir" || return 1

  if [ ! -f "$cCertificateFile" ] && [ ! -f "$cCertificateKeyFile" ] \
    && [ -f "${cLegacyCertificateDir}/mytube.crt" ] && [ -f "${cLegacyCertificateDir}/mytube.key" ]; then
    fLog "Reusing the certificate of the previous installation if it is still valid."
    fBackupConfigFile "$cCertificateFile" || return 1
    fBackupConfigFile "$cCertificateKeyFile" || return 1
    cp -a "${cLegacyCertificateDir}/mytube.crt" "$cCertificateFile" || return 1
    cp -a "${cLegacyCertificateDir}/mytube.key" "$cCertificateKeyFile" || return 1
  fi

  if [ -f "$cCertificateFile" ] && [ -f "$cCertificateKeyFile" ]; then
    if openssl x509 -in "$cCertificateFile" -noout -checkhost "$cDomain" >/dev/null 2>&1 \
      && openssl x509 -in "$cCertificateFile" -noout -checkend 2592000 >/dev/null 2>&1; then
      vCertificatePublicKey="$(openssl x509 -in "$cCertificateFile" -pubkey -noout)" || return 1
      vPrivatePublicKey="$(openssl pkey -in "$cCertificateKeyFile" -pubout 2>/dev/null)" || vPrivatePublicKey=""
      if [ -n "$vPrivatePublicKey" ] && [ "$vCertificatePublicKey" = "$vPrivatePublicKey" ]; then
        return 0
      fi
    fi
  fi

  vCertificateStageDir="$(mktemp -d "${cCertificateDir}/.new-XXXXXXXX")" || return 1
  fLog "Creating a self-signed HTTPS certificate for ${cDomain}."
  openssl req \
    -x509 \
    -nodes \
    -newkey rsa:3072 \
    -sha256 \
    -days 825 \
    -keyout "${vCertificateStageDir}/myowntube.key" \
    -out "${vCertificateStageDir}/myowntube.crt" \
    -subj '/CN=MyOwnTube' \
    -addext "subjectAltName=DNS:${cDomain},DNS:www.${cDomain}" || return 1
  openssl x509 -in "${vCertificateStageDir}/myowntube.crt" -noout -checkhost "$cDomain" || return 1
  openssl pkey -in "${vCertificateStageDir}/myowntube.key" -check -noout || return 1
  chmod 0600 "${vCertificateStageDir}/myowntube.key" || return 1
  chmod 0644 "${vCertificateStageDir}/myowntube.crt" || return 1
  fBackupConfigFile "$cCertificateFile" || return 1
  fBackupConfigFile "$cCertificateKeyFile" || return 1
  mv "${vCertificateStageDir}/myowntube.crt" "$cCertificateFile" || return 1
  mv "${vCertificateStageDir}/myowntube.key" "$cCertificateKeyFile" || return 1
  rmdir "$vCertificateStageDir" || return 1
  vCertificateStageDir=""
}

fConfigureAlpineMain() {
  local vStageFile="${cApacheMainFile}.new.$$"
  local vModule=""

  printf '%s\n' \
    '# Managed by MyOwnTube (Alpine).' \
    'ServerRoot /var/www' \
    'PidFile /run/apache2/httpd.pid' \
    'DefaultRuntimeDir /run/apache2' \
    "ServerName ${cDomain}" \
    "ErrorLog ${cLogsDir}/error.log" \
    > "$vStageFile" || return 1
  for vModule in mpm_prefork unixd log_config authz_core authz_host authz_user authn_core authn_file auth_basic dir mime headers rewrite alias ssl socache_shmcb; do
    if [ ! -f "/usr/lib/apache2/mod_${vModule}.so" ]; then
      fError "Required Apache module is missing: ${vModule}"
    fi
    printf 'LoadModule %s_module /usr/lib/apache2/mod_%s.so\n' "$vModule" "$vModule" >> "$vStageFile" || return 1
  done
  printf '%s\n' \
    "LoadModule php_module ${vPhpApacheModule}" \
    "User ${cWebUser}" \
    "Group ${cWebGroup}" \
    'TypesConfig /etc/apache2/mime.types' \
    'DirectoryIndex index.php' \
    'ServerTokens Prod' \
    'ServerSignature Off' \
    'SSLSessionCache shmcb:/run/apache2/ssl_scache(512000)' \
    'LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined' \
    '<FilesMatch "\.php$">' \
    '  SetHandler application/x-httpd-php' \
    '</FilesMatch>' \
    '<Directory />' \
    '  AllowOverride None' \
    '  Require all denied' \
    '</Directory>' \
    "Include ${cApachePortsFile}" \
    "Include ${cApacheSiteFile}" \
    >> "$vStageFile" || return 1
  chmod 0644 "$vStageFile" || return 1
  fActivateConfigFile "$vStageFile" "$cApacheMainFile" || return 1
}

fConfigureApache() {
  local vApacheStageFile="${cApacheSiteFile}.new.$$"
  local vPortsStageFile="${cApachePortsFile}.new.$$"

  fDetectPhp || return 1
  fConfigurePhp || return 1
  fCreateCertificate || return 1

  mkdir -p /run/apache2 || return 1

  printf '%s\n' 'Listen 80' 'Listen 443 https' > "$vPortsStageFile" || return 1
  chmod 0644 "$vPortsStageFile" || return 1
  fActivateConfigFile "$vPortsStageFile" "$cApachePortsFile" || return 1

  fLog "Configuring MyOwnTube VirtualHost."
  printf '%s\n' \
    '<VirtualHost *:80>' \
    "  Redirect permanent / https://${cDomain}/" \
    "  ServerName ${cDomain}" \
    "  ServerAlias www.${cDomain}" \
    "  DocumentRoot ${cPublicDir}" \
    "  <Directory \"${cPublicDir}\">" \
    '    Require all granted' \
    '    Options FollowSymLinks' \
    '    AllowOverride All' \
    '  </Directory>' \
    "  ServerAdmin admin@${cDomain}" \
    "  ErrorLog  ${cLogsDir}/error.log" \
    "  CustomLog ${cLogsDir}/access.log combined" \
    "  <Directory \"${cPublicDir}/__\">" \
    '    AuthType Basic' \
    '    AuthName "Restricted Content"' \
    '    AuthUserFile /etc/apache2/.htpasswd' \
    '    Require valid-user' \
    '  </Directory>' \
    '  RewriteEngine on' \
    "  RewriteCond %{SERVER_NAME} =www.${cDomain} [OR]" \
    "  RewriteCond %{SERVER_NAME} =${cDomain}" \
    '  RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]' \
    '</VirtualHost>' \
    '' \
    '<IfModule mod_ssl.c>' \
    '  <VirtualHost *:443>' \
    '    SSLEngine on' \
    "    ServerName ${cDomain}" \
    "    ServerAlias www.${cDomain}" \
    "    DocumentRoot ${cPublicDir}" \
    '    DirectoryIndex index.php' \
    "    <Directory \"${cWebDir}\">" \
    '      Require all denied' \
    '    </Directory>' \
    "    <Directory \"${cPublicDir}\">" \
    '      Require all granted' \
    '      Options FollowSymLinks' \
    '      AllowOverride All' \
    '    </Directory>' \
    "    <Directory \"${cDbDir}\">" \
    '      Require all denied' \
    '    </Directory>' \
    "    <Directory \"${cVideosDir}\">" \
    '      Options -Indexes +FollowSymLinks' \
    '      AllowOverride None' \
    '      Require all denied' \
    '    </Directory>' \
    '    <Location "/videos/">' \
    '      Require all denied' \
    '    </Location>' \
    "    <DirectoryMatch \"^${cWebDir}/(backend|frontend/(components|views|lang))(/|$)\">" \
    '      Require all denied' \
    '    </DirectoryMatch>' \
    "    <DirectoryMatch \"^${cWebDir}/(.*/)?\\.\">" \
    '      Require all denied' \
    '    </DirectoryMatch>' \
    '    <FilesMatch "^(config|seed|.*-cli)\\.php$">' \
    '      Require all denied' \
    '    </FilesMatch>' \
    '    <Location "/api/">' \
    '      LimitRequestBody 1048576' \
    '    </Location>' \
    '    <Location "/api/user/videos/upload">' \
    '      LimitRequestBody 0' \
    '    </Location>' \
    '    Header always set Strict-Transport-Security "max-age=31536000"' \
    '    Header always set X-Content-Type-Options "nosniff"' \
    '    Header always set X-Frame-Options "DENY"' \
    '    Header always set Referrer-Policy "same-origin"' \
    "    ServerAdmin admin@${cDomain}" \
    "    ErrorLog  ${cLogsDir}/error.log" \
    "    CustomLog ${cLogsDir}/access.log combined" \
    "    SSLCertificateFile    ${cCertificateFile}" \
    "    SSLCertificateKeyFile ${cCertificateKeyFile}" \
    '    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1' \
    '    SSLCipherSuite HIGH:!aNULL:!MD5' \
    '  </VirtualHost>' \
    '</IfModule>' \
    > "$vApacheStageFile" || return 1

  chmod 0644 "$vApacheStageFile" || return 1
  fActivateConfigFile "$vApacheStageFile" "$cApacheSiteFile" || return 1

  fConfigureAlpineMain || return 1
  fRetireLegacyApacheFiles || return 1
  httpd -t || return 1
}

fRetireLegacyApacheFiles() {
  local vLegacyFile=""

  for vLegacyFile in "$cLegacyApacheSiteFile" "$cLegacyApachePortsFile"; do
    if [ -e "$vLegacyFile" ] || [ -L "$vLegacyFile" ]; then
      fBackupConfigFile "$vLegacyFile" || return 1
      rm -f "$vLegacyFile" || return 1
    fi
  done
}

fRunPhpAsWebUser() {
  su-exec "${cWebUser}:${cWebGroup}" php84 "$@"
}

fSeedDatabase() {
  local vIntegrityResult=""

  vCredentialsOutput="${vTmpDir}/credentials-output.txt"
  if [ -f "$cDbFile" ]; then
    fLog "The database already exists; keeping it without reinitializing."
    fRunPhpAsWebUser "${cWebDir}/backend/secure-admin-cli.php" > "$vCredentialsOutput" || return 1
  else
    fLog "Creating initial database."
    fRunPhpAsWebUser "${cWebDir}/backend/seed.php" > "$vCredentialsOutput" || return 1
  fi

  vIntegrityResult="$(sqlite3 "$cDbFile" 'PRAGMA quick_check;')" || return 1
  if [ "$vIntegrityResult" != "ok" ]; then
    fError "The installed database failed its integrity check."
  fi
  chown -R "${cWebUser}:${cWebGroup}" "$cDbDir" || return 1
  chmod 0750 "$cDbDir" || return 1
  chmod 0640 "$cDbFile" || return 1
}

fSaveCredentials() {
  if ! grep -Eq '^(Administrator password:|New password for the secured account\(s\):|Administrator email changed:)' "$vCredentialsOutput"; then
    fLog "Existing credentials were preserved."
    return 0
  fi
  printf '\n[%s] https://%s/\n' "$(date -Is)" "$cDomain" >> "$cCredentialsFile" || return 1
  cat "$vCredentialsOutput" >> "$cCredentialsFile" || return 1
  chmod 0600 "$cCredentialsFile" || return 1
}

fVerifyBackend() {
  fLog "Checking the HTTPS site on port 443."
  curl --noproxy '*' \
    --resolve "${cDomain}:443:127.0.0.1" \
    --cacert "$cCertificateFile" \
    --connect-timeout 5 --max-time 30 --fail --silent --show-error \
    "https://${cDomain}/auth/login/" -o /dev/null || return 1
}

fRetireLegacyInstallation() {
  local vRetiredDir=""

  if [ -d "$cLegacySessionDir" ]; then
    rm -rf "$cLegacySessionDir" || fLog "WARNING: ${cLegacySessionDir} could not be removed."
    rmdir "$(dirname "$cLegacySessionDir")" 2>/dev/null || true
  fi
  if [ ! -e "$cLegacyWebDir" ] && [ ! -e "$cLegacyLogsDir" ] && [ ! -L "$cLegacyVideosDir" ] \
    && [ ! -e "$cLegacyCertificateDir" ] && [ ! -e "$cLegacyBackupDir" ]; then
    return 0
  fi

  vRetiredDir="${cBackupDir}/previous-install-$(date +%Y%m%d_%H%M%S)-$$"
  fLog "Retiring the files of the previous installation into ${vRetiredDir}."
  mkdir -p "$vRetiredDir" || return 1
  chmod 0700 "$vRetiredDir" || return 1
  if [ -e "$cLegacyWebDir" ]; then
    mv "$cLegacyWebDir" "${vRetiredDir}/www" || fLog "WARNING: ${cLegacyWebDir} could not be moved."
  fi
  if [ -e "$cLegacyLogsDir" ]; then
    mv "$cLegacyLogsDir" "${vRetiredDir}/logs" || fLog "WARNING: ${cLegacyLogsDir} could not be moved."
  fi
  if [ -L "$cLegacyVideosDir" ]; then
    rm -f "$cLegacyVideosDir" || fLog "WARNING: the link ${cLegacyVideosDir} could not be removed."
  elif [ -d "$cLegacyVideosDir" ]; then
    fLog "WARNING: ${cLegacyVideosDir} was kept because ${cVideosDir} already contained videos."
  fi
  if [ -e "$cLegacyCertificateDir" ]; then
    mv "$cLegacyCertificateDir" "${vRetiredDir}/certificates" || fLog "WARNING: ${cLegacyCertificateDir} could not be moved."
  fi
  if [ -e "$cLegacyBackupDir" ]; then
    mv "$cLegacyBackupDir" "${vRetiredDir}/backups" || fLog "WARNING: ${cLegacyBackupDir} could not be moved."
  fi
}

fUseOpenrc() {
  command -v rc-service >/dev/null 2>&1 && [ -e /run/openrc/softlevel ]
}

fServiceRunning() {
  local pService="$1"
  local vPid=""

  if fUseOpenrc; then
    rc-service "$pService" status >/dev/null 2>&1 7>&- 8>&- 9>&-
  else
    if [ ! -r /run/apache2/httpd.pid ]; then
      return 1
    fi
    vPid="$(cat /run/apache2/httpd.pid)"
    case "$vPid" in
      ''|*[!0-9]*) return 1 ;;
    esac
    kill -0 "$vPid" 2>/dev/null
  fi
}

fRememberServiceState() {
  local pService="$1"

  if [ "$vServiceStateCaptured" -eq 0 ]; then
    if fServiceRunning "$pService"; then
      vServiceWasRunning=1
    fi
    vServiceStateCaptured=1
  fi
  vRestartServiceOnCleanup=1
}

fEnableService() {
  local pService="$1"

  rc-update add "$pService" default >/dev/null 7>&- 8>&- 9>&- || return 1
}

fRestartService() {
  local pService="$1"

  if fUseOpenrc; then
    if fServiceRunning "$pService"; then
      rc-service "$pService" restart 7>&- 8>&- 9>&- || return 1
    else
      rc-service "$pService" start 7>&- 8>&- 9>&- || return 1
    fi
  elif fServiceRunning "$pService"; then
    httpd -k restart 7>&- 8>&- 9>&- || return 1
  else
    httpd -k start 7>&- 8>&- 9>&- || return 1
  fi
}

fStopService() {
  local pService="$1"
  local vAttempt=0

  fRememberServiceState "$pService" || return 1
  if ! fServiceRunning "$pService"; then
    return 0
  fi
  if fUseOpenrc; then
    rc-service "$pService" stop 7>&- 8>&- 9>&- || return 1
  else
    httpd -k stop 7>&- 8>&- 9>&- || return 1
    while fServiceRunning "$pService"; do
      if [ "$vAttempt" -ge 100 ]; then
        return 1
      fi
      sleep 0.1 || return 1
      vAttempt=$((vAttempt + 1))
    done
  fi
}

fShowSummary() {
  fLog "Install, reinstall, or update completed."
  fLog "Application: https://${cDomain}/"
  fLog "DocumentRoot: ${cPublicDir}"
  fLog "Logs: ${cLogsDir}"
  fLog "Videos: ${cVideosDir}"
  fLog "Persistent backups: ${cBackupDir}"
  fLog "HTTPS certificate: ${cCertificateFile}"
  fLog "Trust the self-signed certificate on client devices if the installer created it."
  fLog "Code owner: root:root"
  fLog "Writable data owner: ${cWebUser}:${cWebGroup}"
  fLog "Installation log: ${cInstallLog}"
  fLog "Administrator credentials: ${cCredentialsFile} (root only)."
}

fMain() {
  fRequireRoot || return 1
  fPrepareLogs || return 1
  fAcquireLock || return 1
  fValidateEnvironment || return 1
  fInstallPackages || return 1
  fPrepareDirectories || return 1
  fDownloadCode || return 1
  fStopService apache2 || return 1
  fSaveCurrentData || return 1
  fInstallCode || return 1
  fConfigureApache || return 1
  fSeedDatabase || return 1
  fEnableService apache2 || return 1
  fRestartService apache2 || return 1
  fVerifyBackend || return 1
  fSaveCredentials || return 1
  vRestartServiceOnCleanup=0
  vDeploymentPending=0
  vConfigRollbackPending=0
  fRetireLegacyInstallation || fLog "WARNING: the previous installation could not be fully retired."
  fShowSummary || return 1
}

# Tests and callers sourcing these functions can explicitly select library mode.
if [ "${MYOWNTUBE_INSTALLER_LIBRARY_ONLY:-0}" != 1 ]; then
  trap 'fCleanup < /dev/null' 0
  if ! fMain "$@" < /dev/null; then
    printf '[MyOwnTube] ERROR: Installation, reinstallation, or update failed.\n' >&2
    exit 1
  fi
  if ! fCleanup < /dev/null; then
    trap - 0
    exit 1
  fi
  trap - 0
fi
