# MyOwnTube

El nombre del proyecto es `my-own-tube` y el de la aplicación es `MyOwnTube`. Es una webapp independiente organizada en `frontend/`, `backend/`, `doc/`, `tests/` y `deploy/`. Los dominios, las direcciones de las cuentas y las variables `MYTUBE_*` siguen siendo compatibles con las instalaciones existentes.

MyOwnTube es una pequeña videoteca privada y autoalojada para Debian y Alpine Linux. Ofrece reproducción autenticada, subidas validadas, búsqueda, historial y reanudación, «Me gusta», «Ver más tarde», listas, comentarios, administración de usuarios e interfaces en inglés británico y estadounidense, español de España y español de Argentina.

La aplicación es deliberadamente sencilla: Apache sirve PHP, SQLite guarda los metadatos y los vídeos originales permanecen en disco. No necesita compilación, gestor de paquetes JavaScript ni una base de datos externa.

La interfaz sigue el tema claro u oscuro del sistema, adapta la navegación al móvil y ofrece en-GB, en-US, es-AR y es-ES en ese orden. Los listados del catálogo y de las cuentas muestran como máximo 24 entradas por página, con controles anterior/siguiente y contadores totales.

## Estructura del proyecto

```text
frontend/
  public/        # index.php, styles.css, ui.js, .htaccess
  views/         # plantillas de documento y páginas
  components/    # renderizado HTML y SVG
  lang/          # textos traducidos de la interfaz
backend/
  actions/       # handlers de acciones POST
  pages/         # controladores de páginas y consultas
  inc/           # lógica compartida de la aplicación
  tools/         # utilidades de importación multimedia
  db/            # base de datos privada de ejecución
  videos/        # directorio o enlace multimedia privado
doc/
tests/
deploy/
```

La única raíz pública HTTP es `frontend/public/`. Las vistas, los componentes y las traducciones permanecen en carpetas privadas del frontend; los controladores PHP, las acciones, la configuración, la base y los medios pertenecen al backend. La entrada pública distribuye las URL existentes `/index.php`, `/actions/*.php`, `/media.php` y `/thumb.php` sin exponer archivos internos del backend.

El instalador despliega `frontend/` y `backend/` juntos bajo `/var/www/mytube`. La base pasa a `/var/www/mytube/backend/db/mytube.db`; `/var/www/mytube/backend/videos` enlaza con el directorio persistente `/var/www/mytube-videos`. Las actualizaciones copian la antigua `/var/www/mytube/db/mytube.db` mediante una copia SQLite verificada y conservan los medios. La reversión recupera la estructura anterior y su configuración de Apache.

## Funciones principales

- Privada por defecto: cada página, emisión de vídeo y miniatura exige haber iniciado sesión.
- El registro está desactivado inicialmente y un administrador puede habilitarlo de forma temporal.
- «Me gusta», «Ver más tarde», historial, búsquedas, listas, idioma y progreso independientes para cada usuario.
- Validación de las subidas con `ffprobe`; la extensión del nombre no se considera suficiente.
- Emisión mediante rangos HTTP, incluidos rangos de sufijo, y descarga explícita.
- Protección CSRF, cookies de sesión estrictas, contraseñas cifradas con Argon2id, salida escapada, limitación de accesos y cabeceras de seguridad.
- Controles administrativos para usuarios, roles, idioma del sitio, registro y sincronización de carpetas.
- Creación atómica de la base, migración no destructiva de esquemas antiguos y copias persistentes durante las actualizaciones.

## Requisitos

El instalador de Debian utiliza apt y el de Alpine utiliza apk. Instalan Apache, PHP con SQLite y mbstring, la CLI de SQLite, FFmpeg/ffprobe, OpenSSL, curl y sus dependencias. Ejecútalo como `root` en un equipo cuyo nombre DNS ya se resuelva correctamente.

Utiliza una instancia de Apache dedicada. El instalador selecciona globalmente el MPM prefork y el módulo PHP de Apache, por lo que se niega a continuar si hay un VirtualHost ajeno habilitado. Durante la validación solo admite `000-default.conf` de Debian y el sitio propio de MyOwnTube.

El VirtualHost HTTPS generado habilita el protocolo PROXY de HAProxy. Coloca MyOwnTube detrás de un proxy o balanceador de confianza que envíe dicho protocolo. Si los navegadores se conectan directamente a Apache, adapta esa directiva al diseño de tu red antes de publicar el servicio.

## Instalación, actualización o reinstalación

### Debian

Descarga el instalador mediante HTTPS verificado, revísalo si lo necesitas y ejecútalo:

```bash
curl --proto '=https' --tlsv1.2 -fsSL \
  https://gitea.servint.home.arpa/nipegun/my-own-tube/raw/branch/master/deploy/install-update-reinstall-debian.sh \
  -o /tmp/install-update-reinstall-debian.sh
chmod 0755 /tmp/install-update-reinstall-debian.sh
sudo env \
  MYTUBE_DOMAIN='mytube.home.arpa' \
  MYTUBE_ADMIN_PASSWORD='sustituye-esto-por-una-contraseña-larga-y-única' \
  /tmp/install-update-reinstall-debian.sh
```

### Alpine

Ejecuta como root. Habilita los repositorios main y community de Alpine; el instalador utiliza los paquetes php84. Instala las herramientas iniciales antes de descargar el script Bash:

```bash
apk add --no-cache bash ca-certificates curl flock
curl --proto '=https' --tlsv1.2 -fsSL \
  https://gitea.servint.home.arpa/nipegun/my-own-tube/raw/branch/master/deploy/install-update-reinstall-alpine.sh \
  -o /tmp/install-update-reinstall-alpine.sh
MYTUBE_DOMAIN='mytube.home.arpa' \
  MYTUBE_ADMIN_PASSWORD='sustituye-esto-por-una-contraseña-larga-y-única' \
  bash /tmp/install-update-reinstall-alpine.sh
```

`MYTUBE_ADMIN_PASSWORD` debe tener entre 12 y 4096 caracteres. Si se omite para una cuenta nueva o con la contraseña histórica, se genera una aleatoria. El instalador guarda la salida de credenciales de la CLI en `/root/app-web-credentials.txt`, separada del registro `/root/app-web-install.log`; ambos archivos tienen permisos 0600. Los comandos de mantenimiento ejecutados directamente siguen mostrando sus credenciales en la terminal.

Al ejecutar de nuevo el mismo script se realiza una actualización. Detiene Apache, crea una copia SQLite verificada, conserva el directorio externo de vídeos, prepara el código nuevo, comprueba Apache y restaura el directorio web anterior si el despliegue falla.

El instalador crea un certificado autofirmado cuando no existe uno válido para el host. Confía en él desde los dispositivos cliente o sustitúyelo por un certificado emitido por una autoridad de confianza.

Después abre:

```text
https://mytube.home.arpa/
```

El correo del administrador inicial es:

```text
admin@mytube.home.arpa
```

## Rutas instaladas

| Finalidad | Ruta |
|---|---|
| Código y base de datos SQLite | `/var/www/mytube` |
| Vídeos y archivos auxiliares persistentes | `/var/www/mytube-videos` |
| Registros de Apache | `/var/www/mytube-logs` |
| Copias persistentes de base, web, certificado y Apache | `/var/backups/mytube` |
| Certificado autofirmado y clave | `/etc/ssl/mytube` |
| VirtualHost de Apache | `/etc/apache2/sites-available/000-mytube.conf` |

El código pertenece a `root:root`. Solo la base, los vídeos y los registros son escribibles por `www-data` en Debian o `apache` en Alpine.

Los instaladores independientes son `deploy/install-update-reinstall-debian.sh` y `deploy/install-update-reinstall-alpine.sh`. Apache escucha solo en `127.0.0.1:11080` (HTTP) y `127.0.0.1:11443` (HTTPS con TLS activado). HAProxy ocupa los puertos públicos 80/443 y envía el protocolo PROXY al backend HTTPS. HTTP redirige a la URL HTTPS pública, que HAProxy entrega a 11443; el puerto interno no aparece en las URL del navegador.

Antes de modificar la configuración, el instalador registra los archivos anteriores en una carpeta privada de copias. Genera y valida los certificados nuevos antes de activarlos. Un fallo posterior restaura certificado y clave, ajustes PHP, listeners de Apache, enlaces de sitios y módulos y el árbol web anterior antes de intentar reiniciar Apache. Los fallos de recuperación se registran. Antes de confirmar el despliegue, el instalador comprueba el acceso HTTPS local con protocolo PROXY, validando el certificado y la respuesta de la aplicación.

Ambos instaladores son idempotentes respecto al estado de la aplicación: las ejecuciones repetidas conservan cuentas, hashes de contraseñas, ajustes, listas, bloqueos de acceso y medios. Reutilizan los certificados válidos cuya clave coincide y a los que les quedan más de 30 días. No reescriben configuraciones idénticas y solo registran credenciales cuando se crean o rotan. Un bloqueo exclusivo impide despliegues simultáneos y los procesos del servicio no lo heredan. Cada ejecución sigue añadiendo su registro y creando copias para recuperación. Un archivo de código distinto, otro dominio solicitado o nuevas versiones de paquetes se consideran una actualización.

En Alpine se utilizan `apk`, PHP 8.4, el usuario/grupo `apache` y OpenRC. En contenedores sin una instancia OpenRC arrancada se controla `httpd` directamente y se registra el arranque con `rc-update`. El instalador gestiona `/etc/apache2/httpd.conf`, `/etc/apache2/mytube-ports.conf`, `/etc/apache2/mytube-site.conf` y `/etc/php84/conf.d/99-mytube.ini`; no añade includes ni listeners duplicados. Las sesiones privadas viven en `/var/lib/mytube/sessions`. La configuración gestionada sustituye la configuración SSL predeterminada del paquete para que Apache solo escuche en los dos puertos locales del backend.

## Preparación local manual

Ejecuta los comandos desde la raíz del proyecto.

Para desarrollo, asegúrate de que PHP tenga PDO SQLite, mbstring y compatibilidad con Argon2id y de que `ffmpeg` y `ffprobe` estén en `PATH`. Después inicializa la base y arranca el servidor local:

```bash
MYTUBE_ADMIN_PASSWORD='sustituye-esto-por-una-contraseña-larga-y-única' php backend/seed.php
php -S 127.0.0.1:8080 -t frontend/public deploy/local-dev-router.php
```

Abre `http://127.0.0.1:8080/`. El enrutador impide que el servidor de desarrollo exponga la base de datos, los módulos internos, los archivos auxiliares, los archivos ocultos, la configuración o las entradas CLI. El servidor integrado solo sirve para desarrollo; usa Apache para el despliegue.

`php backend/seed.php` crea el administrador, la configuración y las categorías de navegación, con un catálogo vacío. `php backend/seed.php --demo` añade explícitamente metadatos de ejemplo, ocultos hasta importar los archivos multimedia correspondientes. Las migraciones también ocultan las entradas antiguas sin ruta de archivo conservando filas y datos personales. Detén el tráfico web antes de usar `--force` para sustituir una base existente.

## Importar vídeos existentes

Copia o enlaza vídeos compatibles en el directorio instalado y, como administrador, usa **Subir vídeo → Sincronizar carpeta**. La alternativa por CLI es:

```bash
sudo -u www-data php /var/www/mytube/backend/sync-videos-cli.php
```

MyOwnTube admite nombres `mp4`, `m4v`, `avi`, `wmv`, `3gp`, `mpg`, `mpeg`, `divx`, `mp5` y `asf`, pero solo importa el archivo si `ffprobe` confirma un flujo de vídeo real. Crea un JSON adyacente y, si FFmpeg puede decodificarlo, una miniatura JPEG.

Desde un clon de este repositorio, la herramienta opcional enlaza todos los archivos de otra carpeta sin permitir que los árboles de origen y destino se solapen:

```bash
sudo python3 backend/tools/generate_symlink.py /ruta/a/videos
```

La sincronización ignora los archivos incompatibles o no válidos y registra sus nombres y errores de validación en el registro de errores del servidor. Si desaparece un archivo importado, se conservan su fila de catálogo y el estado de cada usuario, pero el vídeo queda oculto y no se puede emitir. Una sincronización posterior vuelve a dejarlo disponible cuando regresa el archivo.

Los ejemplos de mantenimiento utilizan Debian. En Alpine, ejecuta el mismo archivo PHP con `su-exec apache:apache php84` en lugar de `sudo -u www-data php`. Para recuperar credenciales, sitúa `MYTUBE_ADMIN_PASSWORD=...` antes de `su-exec`; la variable se transmite a PHP. Para sincronizar desde el servidor Alpine:

```bash
su-exec apache:apache php84 /var/www/mytube/backend/sync-videos-cli.php
```

## Recuperación del administrador

Restablece una cuenta existente y conviértela en administradora desde el terminal del servidor:

```bash
sudo -u www-data env MYTUBE_ADMIN_PASSWORD='otra-contraseña-larga-y-única' \
  php /var/www/mytube/backend/reset-admin-password-cli.php admin@mytube.home.arpa
```

Si omites la variable, se genera una contraseña aleatoria y se muestra una vez. El restablecimiento revoca todas las sesiones activas de esa cuenta. Los comandos de recuperación solo funcionan por CLI y Apache los bloquea.

## Pruebas

```bash
tests/run-tests.sh
tests/check-browser.py
```

Las pruebas completas requieren PHP con PDO SQLite, mbstring y Argon2id, sqlite3, FFmpeg/ffprobe, Python 3.13+, Node.js, tar, OpenSSL, apache2-bin y libapache2-mod-php. `tests/run-tests.sh` falla si falta una dependencia obligatoria. `tests/run-tests.sh --static` ejecuta explícitamente solo sintaxis, idiomas y enrutador local. `tests/check-browser.py` necesita además Playwright para Python y Chromium; comprueba paginación y maquetación en móvil y escritorio, en cuatro idiomas y ambos temas, abriendo menús y navegación móvil. Ejecuta la suite completa y la comprobación de navegador antes del despliegue. `MYOWNTUBE_APACHE_ROOT` permite indicar la raíz de un paquete Apache extraído; `MYOWNTUBE_CHROMIUM`, el ejecutable Chromium; y `MYOWNTUBE_PHP_MODULE`, un módulo PHP de Apache extraído. Los temporales viven en `_/temp/`.

## Documentación

- [Manual de uso](doc/MANUAL.es-ES.md)
- [Guía técnica](doc/CODE.es-ES.md)
- [README en inglés](README.md)
- [README para Argentina](README.es-AR.md)

## Seguridad de los datos

Copia `/var/www/mytube/backend/db/mytube.db` con el mecanismo de copia en línea de SQLite y conserva junto a ella `/var/www/mytube-videos`. No copies únicamente el fichero principal mientras haya escrituras sin haber consolidado el WAL. Ambos instaladores realizan esa copia de forma segura.
