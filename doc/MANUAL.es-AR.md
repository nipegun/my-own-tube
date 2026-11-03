# Manual de uso de MyOwnTube

Este MANUAL también está disponible en:\
[en-GB](MANUAL.en-GB.md), [en-US](MANUAL.md), es-AR, [es-ES](MANUAL.es-ES.md)

## Índice

1. [Qué necesitás](#1-qué-necesitás)
2. [Instalar, actualizar o reinstalar](#2-instalar-actualizar-o-reinstalar)
3. [Primer inicio de sesión](#3-primer-inicio-de-sesión)
4. [Direcciones](#4-direcciones)
5. [Navegación e idioma](#5-navegación-e-idioma)
6. [Explorar y buscar](#6-explorar-y-buscar)
7. [Ver un video](#7-ver-un-video)
8. [Historial, videos que te gustan y Ver más tarde](#8-historial-videos-que-te-gustan-y-ver-más-tarde)
9. [Listas](#9-listas)
10. [Subir un video](#10-subir-un-video)
11. [Importar una carpeta de videos](#11-importar-una-carpeta-de-videos)
12. [Tu panel](#12-tu-panel)
13. [Panel de administración](#13-panel-de-administración)
14. [Contraseña olvidada](#14-contraseña-olvidada)
15. [Cerrar sesión](#15-cerrar-sesión)
16. [Actualizaciones, copias de seguridad y archivos instalados](#16-actualizaciones-copias-de-seguridad-y-archivos-instalados)
17. [Solución de problemas](#17-solución-de-problemas)

## 1. Qué necesitás

- Un servidor Debian o Alpine Linux (sirve una máquina virtual o un contenedor) donde puedas ejecutar comandos como `root`.
- Los puertos **80** y **443** libres en ese servidor. Apache atiende directamente en los dos: el puerto 80 solo redirige a HTTPS.
- Un nombre DNS que apunte al servidor. Si no tenés uno, el instalador usa `myowntube.home.arpa`.
- Un Apache dedicado: el instalador configura Apache solo para MyOwnTube y se niega a continuar si Apache ya sirve otros sitios.

El instalador agrega Apache, PHP (con SQLite y mbstring), la línea de comandos de SQLite, FFmpeg/ffprobe, OpenSSL y curl.

## 2. Instalar, actualizar o reinstalar

El mismo comando instala, actualiza y reinstala. Descarga la aplicación desde la rama `main` de `https://github.com/nipegun/my-own-tube`.

**Debian**:

```bash
curl -fsSL https://raw.githubusercontent.com/nipegun/my-own-tube/refs/heads/main/deploy/install-update-reinstall-debian.sh | bash
```

**Alpine Linux** (usa `/bin/sh`, no hace falta Bash). Si falta curl, ejecutá primero `apk add --no-cache curl ca-certificates`:

```sh
curl -fsSL https://raw.githubusercontent.com/nipegun/my-own-tube/refs/heads/main/deploy/install-update-reinstall-alpine.sh | sh
```

Dos variables de entorno opcionales cambian el resultado. Exportalas en la shell que ejecuta el comando (si las definís solo para curl, no llegan al instalador):

| Variable | Efecto |
|---|---|
| `MYOWNTUBE_DOMAIN` | Dominio del sitio. Si no la definís, una actualización conserva el dominio ya configurado y una instalación nueva usa `myowntube.home.arpa`. |
| `MYOWNTUBE_ADMIN_PASSWORD` | Contraseña del primer administrador (de 12 a 4096 caracteres). Si no la definís, se crea una aleatoria. Las contraseñas existentes nunca se cambian. |

Cada ejecución se puede repetir sin riesgo: conserva la base de datos, las cuentas, las contraseñas, la configuración, los videos y cualquier certificado que siga siendo válido, y no duplica la configuración. Cuando termina, vas a encontrar:

- `/root/webapp-install.log`: el registro completo de todas las ejecuciones.
- `/root/webapp-credentials.txt`: las contraseñas y las cuentas creadas o modificadas por el instalador.

Los dos archivos solo los puede leer `root` (modo 600).

Si el servidor todavía tiene una instalación hecha antes del cambio de nombre a MyOwnTube (`/var/www/mytube`), la primera ejecución la migra: copia la base de datos, mueve los videos, reutiliza un certificado válido, conserva el archivo de credenciales anterior, cambia la dirección del administrador por defecto a `admin@myowntube.home.arpa` y, por último, mueve los archivos viejos a `/var/backups/myowntube/previous-install-<fecha>`.

## 3. Primer inicio de sesión

Abrí `https://<tu dominio>/`. Todas las páginas te piden primero que inicies sesión y, después de iniciarla, abren la página que habías pedido.

Iniciá sesión con el correo `admin@myowntube.home.arpa` y la contraseña de `/root/webapp-credentials.txt` (o la que definiste en `MYOWNTUBE_ADMIN_PASSWORD`). No hay ninguna contraseña predeterminada incorporada.

Después de ocho intentos fallidos desde la misma dirección, el inicio de sesión queda bloqueado durante 15 minutos. El comando de recuperación de la [sección 14](#14-contraseña-olvidada) también elimina ese bloqueo.

El certificado es autofirmado: tu navegador te va a advertir la primera vez. Confiá en ese certificado en tus dispositivos o instalá un certificado de tu organización.

## 4. Direcciones

Cada sección tiene su propia dirección, así que podés guardarla en favoritos o compartirla con otro usuario del sitio:

| Dirección | Sección |
|---|---|
| `/` | Inicio |
| `/trending/` | Tendencias |
| `/search/?q=texto` | Resultados de búsqueda |
| `/watch/<video>/` | Ver un video |
| `/history/`, `/liked/`, `/watch-later/` | Tus colecciones |
| `/playlists/`, `/playlists/<lista>/` | Tus listas |
| `/upload/` | Subir un video |
| `/user/dashboard/profile/`, `/user/dashboard/account/` | Tu panel |
| `/admin/dashboard/users/`, `/admin/dashboard/settings/` | Panel de administración |
| `/auth/login/`, `/auth/register/`, `/auth/recover/` | Iniciar sesión, registrarse, contraseña olvidada |
| `/api/doc/` | Referencia de la interfaz de programación (API) |

`/user/dashboard/` abre la pestaña **Perfil** y `/admin/dashboard/` abre la pestaña **Usuarios**. Las direcciones viejas como `/index.php?page=history` siguen funcionando: redirigen a las nuevas.

## 5. Navegación e idioma

La barra superior contiene la búsqueda, **Crear → Subir video**, las notificaciones y el menú de tu cuenta. La barra lateral enlaza a Inicio, Tendencias, Historial, Videos que te gustan, Ver más tarde y Listas.

Elegí **Idioma** en el menú de la cuenta para usar English (UK), English (US), Español (Argentina) o Español (España). La elección se guarda en tu cuenta.

En un celular, usá el botón de menú para abrir la navegación; tocá afuera de ella o presioná Escape para cerrarla. El tema sigue la configuración clara u oscura de tu dispositivo. Las listas con más de 24 entradas muestran **Anterior** y **Siguiente**, y sus contadores se refieren a la lista completa.

## 6. Explorar y buscar

**Inicio** muestra el catálogo por categoría. **Tendencias** tiene las pestañas Ahora, Música, Tecnología, Gaming y Cocina: Ahora muestra los videos vistos según su cantidad de vistas; las otras pestañas ordenan esa categoría por vistas y fecha.

Escribí en el cuadro de búsqueda de arriba para buscar en títulos, descripciones, canales y categorías. Las búsquedas que enviás se convierten en sugerencias privadas de tu cuenta. Podés borrarlas en **Tu panel → Cuenta**.

## 7. Ver un video

Seleccioná un video para abrirlo. MyOwnTube reproduce el archivo original y te deja saltar a cualquier punto. Tu progreso se guarda una vez que el video empieza de verdad; si volvés a un video que no terminaste, sigue donde lo dejaste. Se cuenta una vista por persona y video.

En la página de reproducción podés:

- Marcar el video con Me gusta o deshacerlo.
- Agregarlo a Ver más tarde o quitarlo.
- Agregarlo a una de tus listas.
- Escribir comentarios y ordenarlos por los más recientes o los más antiguos.
- Compartir el enlace (cuando el navegador lo admite).
- Descargar el archivo original.

## 8. Historial, videos que te gustan y Ver más tarde

**Historial** muestra los videos que viste con su último progreso. Quitá una entrada con la cruz o borrá todo el historial; una vista ya contada no se resta.

**Videos que te gustan** y **Ver más tarde** son listas privadas de tu cuenta. Usá los botones de la página de reproducción, o la cruz de cada lista, para modificarlas.

## 9. Listas

Abrí **Listas**, escribí un nombre y una descripción opcional y elegí **Crear**. Dentro de una lista podés cambiarle el nombre, quitar videos, eliminarla o presionar **Reproducir todo**: la reproducción sigue entonces por la lista, en orden.

Agregar el mismo video dos veces no tiene ningún efecto. Eliminar una cuenta también elimina sus listas, sus Me gusta, su historial, sus sugerencias de búsqueda y su lista de Ver más tarde.

## 10. Subir un video

Cualquier usuario que haya iniciado sesión puede abrir **Subir video**, elegir un archivo y, de forma opcional, un título, una categoría y una descripción. El tamaño máximo es de 10 GiB.

Las extensiones aceptadas son `mp4`, `m4v`, `avi`, `wmv`, `3gp`, `mpg`, `mpeg`, `divx`, `mp5` y `asf`. MyOwnTube también comprueba con `ffprobe` que el archivo realmente contenga video; cualquier otra cosa se rechaza y no se conserva nada.

Después de una subida exitosa, la carpeta del video contiene el archivo original, un archivo JSON con sus metadatos y, cuando FFmpeg puede crearla, una miniatura JPEG. El video aparece en el catálogo de inmediato.

## 11. Importar una carpeta de videos

Copiá o enlazá archivos de video en `/var/www/myowntube-videos`; después, como administrador, abrí **Subir video** y presioná **Sincronizar carpeta**. La sincronización comprueba cada archivo, crea los archivos JSON y las miniaturas que faltan y agrega o actualiza el catálogo. Los archivos no válidos se omiten y se anotan en el registro de errores del servidor.

Desde la terminal del servidor:

```bash
# Debian
sudo -u www-data php /var/www/myowntube/backend/sync-videos-cli.php
# Alpine
su-exec apache:apache php84 /var/www/myowntube/backend/sync-videos-cli.php
```

Para enlazar todos los archivos de otra carpeta sin copiarlos, ejecutá desde una copia del repositorio:

```bash
sudo python3 backend/tools/generate_symlink.py /path/to/your/videos
```

Si un archivo desaparece, su video se oculta, pero se conservan los Me gusta, el historial, el progreso y las listas. Volvé a poner el archivo y sincronizá de nuevo para recuperarlo.

## 12. Tu panel

Abrí el menú de tu cuenta y elegí **Tu perfil** o **Configuración**. El panel tiene dos pestañas:

- **Perfil** (`/user/dashboard/profile/`): tu nombre, los contadores de historial, Me gusta, Ver más tarde y listas, las categorías que más ves y tus últimos videos. Acá podés cambiar tu nombre visible.
- **Cuenta** (`/user/dashboard/account/`): el idioma de la interfaz, el cambio de contraseña (la contraseña actual y la nueva dos veces, de 12 a 4096 caracteres) y el botón para borrar tus sugerencias de búsqueda.

Cambiar tu contraseña mantiene abierta la sesión actual y cierra todas tus otras sesiones.

## 13. Panel de administración

Los administradores ven **Administración** en el menú de la cuenta. El panel tiene dos pestañas:

- **Usuarios** (`/admin/dashboard/users/`): crear cuentas, cambiar el rol (usuario o administrador), establecer una contraseña nueva para otra cuenta y eliminar cuentas. Establecer una contraseña cierra todas las sesiones de esa cuenta. No podés eliminar tu propia cuenta, y el último administrador no puede perder el rol.
- **Configuración** (`/admin/dashboard/settings/`): el idioma por defecto para los visitantes y las cuentas nuevas, y el interruptor que permite el registro público en `/auth/register/`.

El registro público está desactivado por defecto. Activalo solo mientras las personas que esperás crean sus cuentas.

## 14. Contraseña olvidada

MyOwnTube no envía correos. El enlace **¿Olvidaste tu contraseña?** de la página de inicio de sesión abre `/auth/recover/`, que explica las dos formas de volver a entrar:

- **Usuarios**: pedile a un administrador que establezca una contraseña nueva para tu cuenta en **Administración → Usuarios**. Después cambiala en **Tu panel → Cuenta**.
- **Administradores** sin acceso: desde la terminal del servidor, restablecé cualquier cuenta existente y convertila en administradora:

```bash
# Debian
sudo -u www-data env MYOWNTUBE_ADMIN_PASSWORD='another-long-unique-password' \
  php /var/www/myowntube/backend/reset-admin-password-cli.php admin@myowntube.home.arpa
# Alpine
MYOWNTUBE_ADMIN_PASSWORD='another-long-unique-password' su-exec apache:apache \
  php84 /var/www/myowntube/backend/reset-admin-password-cli.php admin@myowntube.home.arpa
```

Si omitís `MYOWNTUBE_ADMIN_PASSWORD`, se muestra una contraseña aleatoria una sola vez. El comando también elimina los bloqueos de inicio de sesión y cierra todas las sesiones de esa cuenta. Solo funciona desde la terminal.

## 15. Cerrar sesión

Elegí **Cerrar sesión** en el menú de la cuenta. La sesión se destruye y su cookie vence. Cerrá siempre la sesión en los dispositivos compartidos.

## 16. Actualizaciones, copias de seguridad y archivos instalados

Volvé a ejecutar el instalador para actualizar. Antes de tocar nada, detiene Apache, hace una copia verificada de la base de datos en `/var/backups/myowntube` y conserva la versión anterior del código como copia para revertir. Si algo falla, restaura la configuración y el código anteriores y vuelve a iniciar Apache.

| Qué | Dónde |
|---|---|
| Código de la aplicación | `/var/www/myowntube` |
| Base de datos | `/var/www/myowntube/backend/db/myowntube.db` |
| Videos, archivos JSON y miniaturas | `/var/www/myowntube-videos` |
| Registros de Apache | `/var/www/myowntube-logs` |
| Copias de seguridad del instalador | `/var/backups/myowntube` |
| Certificado autofirmado | `/etc/ssl/myowntube` |
| Sitio de Apache (Debian) | `/etc/apache2/sites-available/000-myowntube.conf` y `/etc/apache2/ports.conf` |
| Sitio de Apache (Alpine) | `/etc/apache2/httpd.conf`, `/etc/apache2/myowntube-site.conf` y `/etc/apache2/myowntube-ports.conf` |
| Registro y credenciales del instalador | `/root/webapp-install.log` y `/root/webapp-credentials.txt` |

El código pertenece a `root`; solo la base de datos, los videos y los registros los puede escribir el usuario web (`www-data` en Debian, `apache` en Alpine).

Hacé tus propias copias de seguridad de la base de datos **y** de la carpeta de videos: una no sirve sin la otra. Copiá la base de datos con el comando de respaldo de SQLite en lugar de copiar el archivo mientras el sitio está en uso:

```bash
sqlite3 /var/www/myowntube/backend/db/myowntube.db ".backup '/root/myowntube-backup.db'"
```

## 17. Solución de problemas

### La página dice que la base de datos no está inicializada

Creala como el usuario web con una contraseña de administrador fuerte:

```bash
sudo -u www-data env MYOWNTUBE_ADMIN_PASSWORD='replace-with-a-long-unique-password' \
  php /var/www/myowntube/backend/seed.php
```

### Un archivo no se importa

Comprobá que su extensión esté aceptada, que el usuario web pueda leerlo, que no supere los 10 GiB y que `ffprobe` lo reconozca. Los detalles están en `/var/www/myowntube-logs/error.log`.

### El navegador rechaza HTTPS

Confiá en el certificado generado en tus dispositivos o instalá uno de tu organización. No te acostumbres a saltear las advertencias de certificado.

### Apache no arranca después de instalar

Probablemente otro servicio esté usando el puerto 80 o 443 (por ejemplo, un HAProxy anterior). Detenelo y volvé a ejecutar el instalador. El registro del instalador explica qué paso falló.

### El sitio muestra un error genérico

Los errores detallados de la base de datos y de las páginas se mantienen fuera del navegador a propósito. Leé `/var/www/myowntube-logs/error.log`.
