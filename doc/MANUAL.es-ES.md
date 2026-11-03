# Manual de usuario de MyOwnTube

Este MANUAL también está disponible en:\
[en-GB](MANUAL.en-GB.md), [en-US](MANUAL.md), [es-AR](MANUAL.es-AR.md), es-ES

## Índice

1. [Qué necesitas](#1-qué-necesitas)
2. [Instalar, actualizar o reinstalar](#2-instalar-actualizar-o-reinstalar)
3. [Primer inicio de sesión](#3-primer-inicio-de-sesión)
4. [Direcciones](#4-direcciones)
5. [Navegación e idioma](#5-navegación-e-idioma)
6. [Explorar y buscar](#6-explorar-y-buscar)
7. [Ver un vídeo](#7-ver-un-vídeo)
8. [Historial, vídeos que te gustan y Ver más tarde](#8-historial-vídeos-que-te-gustan-y-ver-más-tarde)
9. [Listas de reproducción](#9-listas-de-reproducción)
10. [Subir un vídeo](#10-subir-un-vídeo)
11. [Importar una carpeta de vídeos](#11-importar-una-carpeta-de-vídeos)
12. [Tu panel](#12-tu-panel)
13. [Panel de administración](#13-panel-de-administración)
14. [Contraseña olvidada](#14-contraseña-olvidada)
15. [Cerrar sesión](#15-cerrar-sesión)
16. [Actualizaciones, copias de seguridad y archivos instalados](#16-actualizaciones-copias-de-seguridad-y-archivos-instalados)
17. [Solución de problemas](#17-solución-de-problemas)

## 1. Qué necesitas

- Un servidor Debian o Alpine Linux (vale una máquina virtual o un contenedor) en el que puedas ejecutar comandos como `root`.
- Los puertos **80** y **443** libres en ese servidor. Apache responde directamente en ambos: el puerto 80 solo redirige a HTTPS.
- Un nombre DNS que apunte al servidor. Si no lo tienes, el instalador usa `myowntube.home.arpa`.
- Un Apache dedicado: el instalador configura Apache solo para MyOwnTube y se niega a continuar si Apache ya sirve otros sitios.

El instalador añade Apache, PHP (con SQLite y mbstring), la línea de comandos de SQLite, FFmpeg/ffprobe, OpenSSL y curl.

## 2. Instalar, actualizar o reinstalar

El mismo comando instala, actualiza y reinstala. Descarga la aplicación desde la rama `main` de `https://github.com/nipegun/my-own-tube`.

**Debian**:

```bash
curl -fsSL https://raw.githubusercontent.com/nipegun/my-own-tube/refs/heads/main/deploy/install-update-reinstall-debian.sh | bash
```

**Alpine Linux** (usa `/bin/sh`, no necesita Bash). Si falta curl, ejecuta antes `apk add --no-cache curl ca-certificates`:

```sh
curl -fsSL https://raw.githubusercontent.com/nipegun/my-own-tube/refs/heads/main/deploy/install-update-reinstall-alpine.sh | sh
```

Dos variables de entorno opcionales cambian el resultado. Expórtalas en la shell que ejecuta el comando (definirlas solo para curl no las hace llegar al instalador):

| Variable | Efecto |
|---|---|
| `MYOWNTUBE_DOMAIN` | Dominio del sitio. Si no la defines, una actualización conserva el dominio ya configurado y una instalación nueva usa `myowntube.home.arpa`. |
| `MYOWNTUBE_ADMIN_PASSWORD` | Contraseña del primer administrador (de 12 a 4096 caracteres). Si no la defines, se crea una aleatoria. Las contraseñas existentes nunca se cambian. |

Cada ejecución se puede repetir sin riesgo: conserva la base de datos, las cuentas, las contraseñas, los ajustes, los vídeos y cualquier certificado que siga siendo válido, y no duplica la configuración. Al terminar encontrarás:

- `/root/webapp-install.log`: el registro completo de cada ejecución.
- `/root/webapp-credentials.txt`: las contraseñas y las cuentas creadas o modificadas por el instalador.

Solo `root` puede leer ambos archivos (modo 600).

Si el servidor aún tiene una instalación hecha antes del cambio de nombre a MyOwnTube (`/var/www/mytube`), la primera ejecución la migra: copia la base de datos, mueve los vídeos, reutiliza un certificado válido, conserva el archivo de credenciales anterior, cambia la dirección del administrador por defecto a `admin@myowntube.home.arpa` y, por último, mueve los archivos antiguos a `/var/backups/myowntube/previous-install-<fecha>`.

## 3. Primer inicio de sesión

Abre `https://<tu dominio>/`. Todas las páginas te piden primero que inicies sesión y, después de iniciarla, abren la página que habías solicitado.

Inicia sesión con el correo `admin@myowntube.home.arpa` y la contraseña que figura en `/root/webapp-credentials.txt` (o la que definiste en `MYOWNTUBE_ADMIN_PASSWORD`). No existe ninguna contraseña predeterminada integrada.

Tras ocho intentos fallidos desde la misma dirección, el inicio de sesión queda bloqueado durante 15 minutos. El comando de recuperación de la [sección 14](#14-contraseña-olvidada) también elimina ese bloqueo.

El certificado es autofirmado: tu navegador te avisará la primera vez. Marca ese certificado como de confianza en tus dispositivos o instala un certificado de tu organización.

## 4. Direcciones

Cada sección tiene su propia dirección, así que puedes guardarla en marcadores o compartirla con otro usuario del sitio:

| Dirección | Sección |
|---|---|
| `/` | Inicio |
| `/trending/` | Tendencias |
| `/search/?q=texto` | Resultados de búsqueda |
| `/watch/<vídeo>/` | Ver un vídeo |
| `/history/`, `/liked/`, `/watch-later/` | Tus colecciones |
| `/playlists/`, `/playlists/<lista>/` | Tus listas |
| `/upload/` | Subir un vídeo |
| `/user/dashboard/profile/`, `/user/dashboard/account/` | Tu panel |
| `/admin/dashboard/users/`, `/admin/dashboard/settings/` | Panel de administración |
| `/auth/login/`, `/auth/register/`, `/auth/recover/` | Iniciar sesión, registrarse, contraseña olvidada |
| `/api/doc/` | Referencia de la interfaz de programación (API) |

`/user/dashboard/` abre la pestaña **Perfil** y `/admin/dashboard/` abre la pestaña **Usuarios**. Las direcciones antiguas como `/index.php?page=history` siguen funcionando: redirigen a las nuevas.

## 5. Navegación e idioma

La barra superior contiene la búsqueda, **Crear → Subir vídeo**, las notificaciones y el menú de tu cuenta. La barra lateral enlaza con Inicio, Tendencias, Historial, Vídeos que te gustan, Ver más tarde y Listas.

Elige **Idioma** en el menú de la cuenta para usar inglés (Reino Unido), inglés (EE. UU.), español (Argentina) o español (España). La elección se guarda en tu cuenta.

En un teléfono, usa el botón de menú para abrir la navegación; toca fuera de ella o pulsa Escape para cerrarla. El tema sigue el ajuste claro u oscuro de tu dispositivo. Las listas con más de 24 entradas muestran **Anterior** y **Siguiente**, y sus contadores se refieren a la lista completa.

## 6. Explorar y buscar

**Inicio** muestra el catálogo por categorías. **Tendencias** tiene las pestañas Ahora, Música, Tecnología, Gaming y Cocina: Ahora muestra los vídeos vistos según su número de visualizaciones; las demás pestañas ordenan esa categoría por visualizaciones y fecha.

Escribe en el cuadro de búsqueda superior para buscar en títulos, descripciones, canales y categorías. Las búsquedas que envías se convierten en sugerencias privadas de tu cuenta. Puedes borrarlas en **Tu panel → Cuenta**.

## 7. Ver un vídeo

Selecciona un vídeo para abrirlo. MyOwnTube reproduce el archivo original y te permite saltar a cualquier punto. Tu progreso se guarda en cuanto el vídeo empieza realmente; si vuelves a un vídeo que no terminaste, continúa donde lo dejaste. Cada visualización se cuenta una sola vez por persona y vídeo.

En la página de reproducción puedes:

- Marcar que te gusta el vídeo o deshacerlo.
- Añadirlo a Ver más tarde o quitarlo.
- Añadirlo a una de tus listas.
- Escribir comentarios y ordenarlos por más recientes o más antiguos.
- Compartir el enlace (si el navegador lo permite).
- Descargar el archivo original.

## 8. Historial, vídeos que te gustan y Ver más tarde

**Historial** muestra los vídeos que has visto con su último progreso. Quita una entrada con la cruz o borra todo el historial; una visualización ya contada no se resta.

**Vídeos que te gustan** y **Ver más tarde** son listas privadas de tu cuenta. Usa los botones de la página de reproducción, o la cruz de cada lista, para modificarlas.

## 9. Listas de reproducción

Abre **Listas**, escribe un nombre y, si quieres, una descripción, y elige **Crear**. Dentro de una lista puedes cambiarle el nombre, quitar vídeos, eliminarla o pulsar **Reproducir todo**: la reproducción continúa entonces por la lista en orden.

Añadir dos veces el mismo vídeo no tiene ningún efecto. Eliminar una cuenta también elimina sus listas, sus «Me gusta», su historial, sus sugerencias de búsqueda y su lista de Ver más tarde.

## 10. Subir un vídeo

Cualquier usuario que haya iniciado sesión puede abrir **Subir vídeo**, elegir un archivo y, opcionalmente, un título, una categoría y una descripción. El tamaño máximo es de 10 GiB.

Las extensiones admitidas son `mp4`, `m4v`, `avi`, `wmv`, `3gp`, `mpg`, `mpeg`, `divx`, `mp5` y `asf`. MyOwnTube también comprueba con `ffprobe` que el archivo contenga realmente vídeo; cualquier otra cosa se rechaza y no se conserva nada.

Tras una subida correcta, la carpeta de vídeos contiene el archivo original, un archivo JSON con sus metadatos y, cuando FFmpeg puede crearla, una miniatura JPEG. El vídeo aparece en el catálogo de inmediato.

## 11. Importar una carpeta de vídeos

Copia o enlaza archivos de vídeo en `/var/www/myowntube-videos` y, después, como administrador, abre **Subir vídeo** y pulsa **Sincronizar carpeta**. La sincronización comprueba cada archivo, crea los archivos JSON y las miniaturas que falten y añade o actualiza el catálogo. Los archivos no válidos se omiten y se anotan en el registro de errores del servidor.

Desde la terminal del servidor:

```bash
# Debian
sudo -u www-data php /var/www/myowntube/backend/sync-videos-cli.php
# Alpine
su-exec apache:apache php84 /var/www/myowntube/backend/sync-videos-cli.php
```

Para enlazar todos los archivos de otra carpeta sin copiarlos, ejecuta desde una copia del repositorio:

```bash
sudo python3 backend/tools/generate_symlink.py /path/to/your/videos
```

Si un archivo desaparece, su vídeo se oculta, pero se conservan los «Me gusta», el historial, el progreso y las listas. Vuelve a colocar el archivo y sincroniza de nuevo para recuperarlo.

## 12. Tu panel

Abre el menú de tu cuenta y elige **Tu perfil** o **Configuración**. El panel tiene dos pestañas:

- **Perfil** (`/user/dashboard/profile/`): tu nombre, los contadores de historial, «Me gusta», Ver más tarde y listas, las categorías que más ves y tus últimos vídeos. Aquí puedes cambiar tu nombre visible.
- **Cuenta** (`/user/dashboard/account/`): el idioma de tu interfaz, el cambio de contraseña (la contraseña actual y la nueva dos veces, de 12 a 4096 caracteres) y el botón para borrar tus sugerencias de búsqueda.

Cambiar tu contraseña mantiene abierta la sesión actual y cierra todas tus demás sesiones.

## 13. Panel de administración

Los administradores ven **Administración** en el menú de la cuenta. El panel tiene dos pestañas:

- **Usuarios** (`/admin/dashboard/users/`): crear cuentas, cambiar el rol (usuario o administrador), establecer una contraseña nueva para otra cuenta y eliminar cuentas. Establecer una contraseña cierra todas las sesiones de esa cuenta. No puedes eliminar tu propia cuenta, y el último administrador no puede perder el rol.
- **Configuración** (`/admin/dashboard/settings/`): el idioma por defecto para los visitantes y las cuentas nuevas, y el interruptor que permite el registro público en `/auth/register/`.

El registro público está desactivado por defecto. Actívalo solo mientras las personas que esperas crean sus cuentas.

## 14. Contraseña olvidada

MyOwnTube no envía correos. El enlace **¿Has olvidado tu contraseña?** de la página de inicio de sesión abre `/auth/recover/`, que explica las dos formas de volver a entrar:

- **Usuarios**: pide a un administrador que establezca una contraseña nueva para tu cuenta en **Administración → Usuarios**. Después, cámbiala en **Tu panel → Cuenta**.
- **Administradores** sin acceso: desde la terminal del servidor, restablece cualquier cuenta existente y conviértela en administradora:

```bash
# Debian
sudo -u www-data env MYOWNTUBE_ADMIN_PASSWORD='another-long-unique-password' \
  php /var/www/myowntube/backend/reset-admin-password-cli.php admin@myowntube.home.arpa
# Alpine
MYOWNTUBE_ADMIN_PASSWORD='another-long-unique-password' su-exec apache:apache \
  php84 /var/www/myowntube/backend/reset-admin-password-cli.php admin@myowntube.home.arpa
```

Si omites `MYOWNTUBE_ADMIN_PASSWORD`, se muestra una contraseña aleatoria una sola vez. El comando también elimina los bloqueos de inicio de sesión y cierra todas las sesiones de esa cuenta. Solo funciona desde la terminal.

## 15. Cerrar sesión

Elige **Cerrar sesión** en el menú de la cuenta. La sesión se destruye y su cookie caduca. Cierra siempre la sesión en dispositivos compartidos.

## 16. Actualizaciones, copias de seguridad y archivos instalados

Vuelve a ejecutar el instalador para actualizar. Antes de tocar nada, detiene Apache, hace una copia verificada de la base de datos en `/var/backups/myowntube` y conserva la versión anterior del código como copia de reversión. Si algo falla, restaura la configuración y el código anteriores y vuelve a arrancar Apache.

| Qué | Dónde |
|---|---|
| Código de la aplicación | `/var/www/myowntube` |
| Base de datos | `/var/www/myowntube/backend/db/myowntube.db` |
| Vídeos, archivos JSON y miniaturas | `/var/www/myowntube-videos` |
| Registros de Apache | `/var/www/myowntube-logs` |
| Copias de seguridad del instalador | `/var/backups/myowntube` |
| Certificado autofirmado | `/etc/ssl/myowntube` |
| Sitio de Apache (Debian) | `/etc/apache2/sites-available/000-myowntube.conf` y `/etc/apache2/ports.conf` |
| Sitio de Apache (Alpine) | `/etc/apache2/httpd.conf`, `/etc/apache2/myowntube-site.conf` y `/etc/apache2/myowntube-ports.conf` |
| Registro y credenciales del instalador | `/root/webapp-install.log` y `/root/webapp-credentials.txt` |

El código pertenece a `root`; el usuario web (`www-data` en Debian, `apache` en Alpine) solo puede escribir en la base de datos, los vídeos y los registros.

Haz tus propias copias de seguridad de la base de datos **y** de la carpeta de vídeos: una no sirve de nada sin la otra. Copia la base de datos con el comando de copia de seguridad de SQLite en lugar de copiar el archivo mientras el sitio está en uso:

```bash
sqlite3 /var/www/myowntube/backend/db/myowntube.db ".backup '/root/myowntube-backup.db'"
```

## 17. Solución de problemas

### La página dice que la base de datos no está inicializada

Créala como usuario web con una contraseña de administrador robusta:

```bash
sudo -u www-data env MYOWNTUBE_ADMIN_PASSWORD='replace-with-a-long-unique-password' \
  php /var/www/myowntube/backend/seed.php
```

### Un archivo no se importa

Comprueba que su extensión esté admitida, que el usuario web pueda leerlo, que no supere los 10 GiB y que `ffprobe` lo reconozca. Los detalles están en `/var/www/myowntube-logs/error.log`.

### El navegador rechaza HTTPS

Marca como de confianza el certificado generado en tus dispositivos o instala uno de tu organización. No te acostumbres a saltarte los avisos de certificado.

### Apache no arranca después de instalar

Probablemente otro servicio esté usando el puerto 80 o el 443 (por ejemplo, un HAProxy anterior). Detenlo y vuelve a ejecutar el instalador. El registro del instalador explica qué paso ha fallado.

### El sitio muestra un error genérico

Los errores detallados de la base de datos y de las páginas se mantienen fuera del navegador a propósito. Consulta `/var/www/myowntube-logs/error.log`.
