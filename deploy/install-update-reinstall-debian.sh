#!/bin/bash

set -euo pipefail

readonly cWebUser="www-data"
readonly cWebGroup="www-data"
readonly cRepoUrl="https://github.com/nipegun/my-own-tube"
readonly cScriptUrl="https://raw.githubusercontent.com/nipegun/my-own-tube/refs/heads/main/deploy/install-update-reinstall-debian.sh"
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
readonly cApacheSiteFile="/etc/apache2/sites-available/${cApacheSite}.conf"
readonly cApachePortsFile="/etc/apache2/ports.conf"
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
readonly cLegacyApacheSite="000-mytube"
readonly cLegacyInstallLog="/root/app-web-install.log"
readonly cLegacyCredentialsFile="/root/app-web-credentials.txt"
readonly cPackages="apache2 ca-certificates curl ffmpeg libapache2-mod-php openssl php php-cli php-mbstring php-sqlite3 sqlite3 tar"

# MYOWNTUBE_DOMAIN wins. Otherwise an update keeps the ServerName already
# configured, except the old default name, which moves to the new default.
function fDetectDomain() {
  local vDomain=""
  local vSiteFile=""

  if [ -n "${MYOWNTUBE_DOMAIN:-}" ]; then
    printf '%s' "$MYOWNTUBE_DOMAIN"
    return 0
  fi
  for vSiteFile in /etc/apache2/sites-available/000-myowntube.conf /etc/apache2/sites-available/000-mytube.conf; do
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
aConfigPaths=()
aConfigBackups=()

function fRequireRoot() {
  if [ "${EUID}" -ne 0 ]; then
    fError "Run the downloaded installer as root."
  fi
}

function fAcquireLock() {
  if ! command -v flock >/dev/null 2>&1; then
    fError "flock is required. Install the distribution's flock/util-linux package first."
  fi
  mkdir -p "$(dirname "$cLockFile")" || return 1
  exec 9> "$cLockFile" || return 1
  chmod 0600 "$cLockFile" || return 1
  if ! flock -n 9; then
    fError "Another MyOwnTube installation is already running."
  fi
}

function fActivateConfigFile() {
  local pStage="$1"
  local pTarget="$2"

  if [ -f "$pTarget" ] && cmp -s "$pStage" "$pTarget"; then
    rm -f "$pStage" || return 1
    return 0
  fi
  fBackupConfigFile "$pTarget" || return 1
  mv "$pStage" "$pTarget" || return 1
}

function fPrepareLogs() {
  umask 077
  if [ -f "$cLegacyCredentialsFile" ] && [ ! -s "$cCredentialsFile" ]; then
    mv "$cLegacyCredentialsFile" "$cCredentialsFile" || return 1
  fi
  if [ -f "$cLegacyInstallLog" ] && [ ! -s "$cInstallLog" ]; then
    mv "$cLegacyInstallLog" "$cInstallLog" || return 1
  fi
  touch "$cInstallLog" "$cCredentialsFile" || return 1
  chmod 0600 "$cInstallLog" "$cCredentialsFile" || return 1
  exec > >(tee -a "$cInstallLog" 9>&-) 2>&1
}

function fBackupConfigFile() {
  local pPath="$1"
  local vPath=""
  local vBackup=""

  for vPath in "${aConfigPaths[@]}"; do
    if [ "$vPath" = "$pPath" ]; then
      return 0
    fi
  done
  if [ -z "$vConfigBackupDir" ]; then
    vConfigBackupDir="$(mktemp -d "${cBackupDir}/config-XXXXXXXX")" || return 1
    chmod 0700 "$vConfigBackupDir" || return 1
  fi
  if [ -e "$pPath" ] || [ -L "$pPath" ]; then
    vBackup="${vConfigBackupDir}/${#aConfigPaths[@]}"
    cp -a "$pPath" "$vBackup" || return 1
  fi
  printf '%s\t%s\n' "$pPath" "$vBackup" >> "${vConfigBackupDir}/manifest.tsv" || return 1
  aConfigPaths+=("$pPath")
  aConfigBackups+=("$vBackup")
  vConfigRollbackPending=1
}

function fRestoreConfigFiles() {
  local vIndex=0
  local vFailed=0

  for ((vIndex=${#aConfigPaths[@]}-1; vIndex>=0; vIndex--)); do
    if [ -n "${aConfigBackups[$vIndex]}" ]; then
      if ! cp -a --remove-destination "${aConfigBackups[$vIndex]}" "${aConfigPaths[$vIndex]}"; then
        fLog "ERROR: Could not restore ${aConfigPaths[$vIndex]}. Backup: ${aConfigBackups[$vIndex]}"
        vFailed=1
      fi
    elif ! rm -f "${aConfigPaths[$vIndex]}"; then
      fLog "ERROR: Could not remove new configuration ${aConfigPaths[$vIndex]}."
      vFailed=1
    fi
  done
  return "$vFailed"
}

function fLog() {
  local pMessage="$1"

  printf '[MyOwnTube] %s\n' "$pMessage"
}

function fError() {
  local pMessage="$1"

  printf '[MyOwnTube] ERROR: %s\n' "$pMessage" >&2
  exit 1
}

function fCleanup() {
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

  if [ -n "$vStageWebDir" ] && [ -d "$vStageWebDir" ] && [[ "$vStageWebDir" == /var/www/.myowntube-stage.* ]]; then
    rm -rf "$vStageWebDir" || true
  fi
  if [ -n "$vTmpDir" ] && [ -d "$vTmpDir" ] && [[ "$vTmpDir" == /tmp/myowntube-install.* ]]; then
    rm -rf "$vTmpDir" || true
  fi
  if [ -n "$vCertificateStageDir" ] && [[ "$vCertificateStageDir" == "${cCertificateDir}/.new-"* ]]; then
    rm -rf "$vCertificateStageDir" || true
  fi
  if [ "$vRestartServiceOnCleanup" -eq 1 ] && [ "$vServiceWasRunning" -eq 1 ]; then
    if ! fRestartService apache2; then
      fLog "ERROR: Apache could not restart after rollback. Inspect ${cInstallLog}."
      vCleanupFailed=1
    fi
  fi
  return "$vCleanupFailed"
}

function fValidateEnvironment() {
  if [ "${EUID}" -ne 0 ]; then
    fError "Run the downloaded installer as root, for example with sudo. Source: ${cScriptUrl}"
  fi

  if [ ! -r /etc/debian_version ]; then
    fError "This installer is prepared for Debian."
  fi

  if ! command -v apt-get >/dev/null 2>&1; then
    fError "apt-get was not found."
  fi

  if [ "${#cDomain}" -gt 253 ] || [[ ! "$cDomain" =~ ^([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)*[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?$ ]]; then
    fError "MYOWNTUBE_DOMAIN is not a valid DNS name."
  fi

  fValidateApacheIsolation
}

function fValidateApacheIsolation() {
  local vSitePath=""
  local vSiteName=""

  if [ ! -d /etc/apache2/sites-enabled ]; then
    return 0
  fi

  for vSitePath in /etc/apache2/sites-enabled/*; do
    if [ ! -e "$vSitePath" ] && [ ! -L "$vSitePath" ]; then
      continue
    fi

    vSiteName="$(basename "$vSitePath")"
    case "$vSiteName" in
      000-default.conf|"${cApacheSite}.conf"|"${cLegacyApacheSite}.conf")
        ;;
      *)
        fError "Another Apache site is enabled (${vSiteName}). This installer requires a dedicated Apache instance so it cannot change the global PHP module or MPM used by unrelated sites."
        ;;
    esac
  done
}

function fInstallPackages() {
  fLog "Installing Apache, PHP, and required extensions."
  apt-get update 9>&- || return 1
  DEBIAN_FRONTEND=noninteractive apt-get install -y $cPackages 9>&- || return 1

  if command -v phpenmod >/dev/null 2>&1; then
    phpenmod mbstring sqlite3 || return 1
  fi
}

function fPrepareDirectories() {
  fLog "Preparing directories in /var/www."

  mkdir -p "$cLogsDir" "$cVideosDir" "$cBackupDir" || return 1
  touch "${cLogsDir}/access.log" "${cLogsDir}/error.log" || return 1
  chown -R "${cWebUser}:${cWebGroup}" "$cLogsDir" "$cVideosDir" || return 1
  chown root:root "$cBackupDir" || return 1
  chmod 0750 "$cLogsDir" "$cVideosDir" || return 1
  chmod 0640 "${cLogsDir}/access.log" "${cLogsDir}/error.log" || return 1
  chmod 0700 "$cBackupDir" || return 1
}

function fDownloadCode() {
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

function fSaveCurrentData() {
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

function fInstallCode() {
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

function fDetectPhp() {
  vPhpVersion="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')" || return 1
  vPhpApacheModule="php${vPhpVersion}"

  if [ ! -f "/etc/apache2/mods-available/${vPhpApacheModule}.load" ]; then
    fError "Apache PHP module was not found: ${vPhpApacheModule}"
  fi
  if ! php -r 'exit(defined("PASSWORD_ARGON2ID") ? 0 : 1);'; then
    fError "The installed PHP build does not provide Argon2id password hashing."
  fi
}

function fConfigurePhp() {
  local vPhpConfigDir="/etc/php/${vPhpVersion}/apache2/conf.d"
  local vPhpConfigFile="${vPhpConfigDir}/99-myowntube.ini"
  local vLegacyPhpConfigFile="${vPhpConfigDir}/99-mytube.ini"
  local vPhpStageFile="${vPhpConfigFile}.new.$$"

  if [ ! -d "$vPhpConfigDir" ]; then
    fError "The Apache PHP configuration directory was not found: ${vPhpConfigDir}"
  fi

  printf '%s\n' \
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

function fCreateCertificate() {
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

function fConfigureApache() {
  local vApacheStageFile="${cApacheSiteFile}.new.$$"
  local vModuleFile=""
  local vPortsStageFile="${cApachePortsFile}.new.$$"

  fDetectPhp || return 1
  fConfigurePhp || return 1
  fCreateCertificate || return 1

  for vModuleFile in /etc/apache2/mods-available/*.load /etc/apache2/mods-available/*.conf; do
    if [ -f "$vModuleFile" ]; then
      fBackupConfigFile "/etc/apache2/mods-enabled/$(basename "$vModuleFile")" || return 1
    fi
  done
  fLog "Enabling required Apache modules."
  a2dismod mpm_event >/dev/null 2>&1 || true
  a2dismod mpm_worker >/dev/null 2>&1 || true
  a2enmod mpm_prefork rewrite headers dir mime alias auth_basic authn_core authn_file authz_user ssl "$vPhpApacheModule" >/dev/null || return 1

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

  fBackupConfigFile "/etc/apache2/sites-enabled/${cApacheSite}.conf" || return 1
  fBackupConfigFile /etc/apache2/sites-enabled/000-default.conf || return 1
  chmod 0644 "$vApacheStageFile" || return 1
  fActivateConfigFile "$vApacheStageFile" "$cApacheSiteFile" || return 1

  a2ensite "$cApacheSite" >/dev/null || return 1
  a2dissite 000-default >/dev/null 2>&1 || true
  fRetireLegacyApacheSite || return 1
  apache2ctl configtest || return 1
}

function fRetireLegacyApacheSite() {
  local vEnabledSite="/etc/apache2/sites-enabled/${cLegacyApacheSite}.conf"
  local vAvailableSite="/etc/apache2/sites-available/${cLegacyApacheSite}.conf"

  if [ -e "$vEnabledSite" ] || [ -L "$vEnabledSite" ]; then
    fLog "Disabling the Apache site of the previous installation."
    fBackupConfigFile "$vEnabledSite" || return 1
    rm -f "$vEnabledSite" || return 1
  fi
  if [ -e "$vAvailableSite" ]; then
    fBackupConfigFile "$vAvailableSite" || return 1
    rm -f "$vAvailableSite" || return 1
  fi
}

function fRunPhpAsWebUser() {
  runuser -u "$cWebUser" -- php "$@"
}

function fSeedDatabase() {
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

function fSaveCredentials() {
  if ! grep -Eq '^(Administrator password:|New password for the secured account\(s\):|Administrator email changed:)' "$vCredentialsOutput"; then
    fLog "Existing credentials were preserved."
    return 0
  fi
  printf '\n[%s] https://%s/\n' "$(date -Is)" "$cDomain" >> "$cCredentialsFile" || return 1
  cat "$vCredentialsOutput" >> "$cCredentialsFile" || return 1
  chmod 0600 "$cCredentialsFile" || return 1
}

function fVerifyBackend() {
  fLog "Checking the HTTPS site on port 443."
  curl --noproxy '*' \
    --resolve "${cDomain}:443:127.0.0.1" \
    --cacert "$cCertificateFile" \
    --connect-timeout 5 --max-time 30 --fail --silent --show-error \
    "https://${cDomain}/auth/login/" -o /dev/null || return 1
}

function fRetireLegacyInstallation() {
  local vRetiredDir=""

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

function fUseSystemd() {
  [ -d /run/systemd/system ] && command -v systemctl >/dev/null 2>&1
}

function fServiceRunning() {
  local pService="$1"

  if fUseSystemd; then
    systemctl is-active --quiet "$pService" 9>&-
  else
    service "$pService" status >/dev/null 2>&1 9>&-
  fi
}

function fRememberServiceState() {
  local pService="$1"

  if [ "$vServiceStateCaptured" -eq 0 ]; then
    if fServiceRunning "$pService"; then
      vServiceWasRunning=1
    fi
    vServiceStateCaptured=1
  fi
  vRestartServiceOnCleanup=1
}

function fEnableService() {
  local pService="$1"

  if fUseSystemd; then
    systemctl enable "$pService" >/dev/null 9>&- || return 1
  else
    update-rc.d "$pService" defaults >/dev/null || return 1
  fi
}

function fRestartService() {
  local pService="$1"

  if fUseSystemd; then
    systemctl restart "$pService" 9>&- || return 1
  else
    service "$pService" restart 9>&- || return 1
  fi
}

function fStopService() {
  local pService="$1"

  fRememberServiceState "$pService" || return 1
  if fUseSystemd; then
    systemctl stop "$pService" 9>&- || return 1
  else
    service "$pService" stop 9>&- || return 1
  fi
}

function fShowSummary() {
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

function fMain() {
  fRequireRoot || return 1
  fAcquireLock || return 1
  fPrepareLogs || return 1
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

if [[ -z "${BASH_SOURCE[0]:-}" || "${BASH_SOURCE[0]:-}" == "$0" ]]; then
  trap 'fCleanup < /dev/null' EXIT
  if ! fMain "$@" < /dev/null; then
    printf '[MyOwnTube] ERROR: Installation, reinstallation, or update failed.\n' >&2
    exit 1
  fi
fi
