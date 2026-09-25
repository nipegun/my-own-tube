# Manual de uso de MyOwnTube

## 1. Primer inicio de sesión

Abre la dirección HTTPS configurada durante la instalación. MyOwnTube redirige cualquier petición no autenticada a la página de acceso.

Usa el correo `admin@mytube.home.arpa` y la contraseña proporcionada mediante `MYTUBE_ADMIN_PASSWORD` o guardada por el instalador en `/root/app-web-credentials.txt` (solo accesible para root). No existe una contraseña predeterminada integrada.

Después de ocho intentos fallidos desde la misma dirección cliente, el acceso queda bloqueado durante 15 minutos. Un administrador con terminal puede eliminar el bloqueo con el comando de recuperación de la sección 12.

## 2. Navegación e idioma

La barra superior contiene la búsqueda, la subida, las notificaciones y el menú de usuario. La barra lateral enlaza con Inicio, Tendencias, Historial, Vídeos que te gustan, Ver más tarde y Listas.

Elige **Idioma** en el menú de usuario para utilizar inglés británico, inglés estadounidense, español de Argentina o español de España. La elección se guarda en tu cuenta. Los administradores también pueden elegir en **Ajustes** el idioma predeterminado para las cuentas nuevas.

En móvil, utiliza el botón de menú para abrir la navegación. Toca fuera o pulsa Escape para cerrarla. El tema sigue el ajuste claro u oscuro del sistema operativo. Los listados ofrecen **Anterior** y **Siguiente** si superan las 24 entradas; los contadores reflejan la colección filtrada completa.

## 3. Explorar y buscar

**Inicio** muestra el catálogo por categorías. **Tendencias** ofrece las pestañas Ahora, Música, Tecnología, Gaming y Cocina. Ahora muestra los vídeos vistos según sus visualizaciones; las demás ordenan la categoría elegida por visualizaciones y fecha de publicación.

Escribe una consulta en el buscador superior. Los resultados coinciden con títulos, descripciones, canales y categorías. Las búsquedas enviadas aparecen como sugerencias privadas. Elimínalas todas desde **Ajustes → Borrar historial de búsqueda**.

## 4. Ver un vídeo

Selecciona una tarjeta para abrir la reproducción. MyOwnTube emite el archivo original y permite desplazarse mediante rangos de bytes HTTP. El progreso solo se guarda después de que el vídeo comience realmente. Al volver a uno incompleto se reanuda la posición guardada; el progreso cercano al final se considera completado.

Se cuenta una visualización por usuario y vídeo cuando se crea el primer registro de progreso. Las actualizaciones posteriores no vuelven a incrementar el contador.

En la página de reproducción puedes:

- Marcar o desmarcar «Me gusta».
- Añadir o quitar el vídeo de «Ver más tarde».
- Añadirlo o quitarlo de una de tus listas.
- Publicar comentarios y ordenarlos de más nuevos a más antiguos o al revés.
- Copiar un enlace mediante la función de compartir del navegador, si está disponible.
- Descargar el archivo original.

## 5. Historial, «Me gusta» y «Ver más tarde»

**Historial** muestra los vídeos vistos y su último progreso. Puedes quitar una entrada o borrar el historial completo. Borrar una entrada no resta una visualización ya contabilizada.

**Vídeos que te gustan** y **Ver más tarde** son colecciones privadas de cada cuenta. Utiliza los controles de las tarjetas o de la página de reproducción para modificarlas.

## 6. Listas

Abre **Listas** y elige **Nueva lista**. Introduce un nombre y una descripción opcional. Abre una lista para editar sus datos, quitar vídeos, eliminarla o iniciar **Reproducir todo**; la reproducción avanza después por los elementos restantes en orden.

Añadir dos veces el mismo vídeo no produce duplicados. Al quitar un elemento se cierran los huecos de posición. Al eliminar un usuario también se eliminan sus listas, «Me gusta», historial, búsquedas y elementos de «Ver más tarde».

## 7. Subir un vídeo

Cualquier usuario autenticado puede abrir **Subir** y seleccionar un archivo, un título, una categoría y una descripción. El tamaño máximo configurado es de 10 GiB.

Las extensiones admitidas son `mp4`, `m4v`, `avi`, `wmv`, `3gp`, `mpg`, `mpeg`, `divx`, `mp5` y `asf`. MyOwnTube usa después `ffprobe` para comprobar que el contenido tenga un flujo de vídeo real. Un HTML u otro archivo renombrado se rechaza y se eliminan sus restos parciales.

Si todo va bien, MyOwnTube escribe tres archivos relacionados en el directorio de vídeos:

- El vídeo original.
- Un JSON auxiliar con metadatos técnicos y descriptivos.
- Una miniatura JPEG cuando FFmpeg puede generarla.

Después añade el vídeo al catálogo. Los nombres se normalizan a una base segura y se hacen únicos sin sobrescribir datos.

## 8. Importar una carpeta de vídeos

Los administradores pueden elegir **Subir vídeo → Sincronizar carpeta** después de colocar archivos en `/var/www/mytube-videos`. La sincronización valida cada archivo compatible, crea los JSON y miniaturas que falten e inserta o actualiza las filas del catálogo. Los archivos no válidos se contabilizan como errores, se anotan en el registro de errores del servidor y no se añaden.

Cuando falta un archivo importado anteriormente, se conservan su fila de catálogo, «Me gusta», historial, progreso y pertenencia a listas, pero queda oculto y no se puede reproducir. Restaura el archivo y vuelve a sincronizar para recuperarlo sin perder ese estado.

Para mantenimiento sin navegador, ejecuta:

```bash
sudo -u www-data php /var/www/mytube/backend/sync-videos-cli.php
```

## 9. Perfil y contraseña

Abre **Tu perfil** para cambiar el nombre visible. Todo el texto proporcionado por usuarios se muestra como texto y no se interpreta como HTML.

Abre **Ajustes → Cambiar contraseña**. Introduce la contraseña actual y la nueva dos veces. Debe contener entre 12 y 4096 caracteres. El cambio correcto rota el identificador de la sesión actual y revoca todas las demás sesiones activas de esa cuenta.

## 10. Ajustes de administrador

Solo los administradores pueden abrir la gestión de usuarios. Pueden crear cuentas, promocionar o degradar usuarios y eliminarlos. MyOwnTube impide eliminar tu propia cuenta activa y retirar el rol del último administrador.

En **Ajustes**, un administrador puede:

- Establecer el idioma predeterminado para usuarios nuevos.
- Activar o desactivar el registro público.

El registro está desactivado por defecto. Al activarlo, la página de registro crea usuarios normales e inicia su sesión. Vuelve a desactivarlo cuando se hayan incorporado las personas previstas en una instalación privada.

## 11. Cerrar sesión

Elige **Cerrar sesión** en el menú de usuario. MyOwnTube destruye la sesión actual, caduca su cookie y genera un identificador limpio. Cierra siempre la sesión en dispositivos compartidos.

## 12. Recuperar el acceso de administrador

En el servidor Debian, restablece cualquier cuenta existente y conviértela en administradora:

```bash
sudo -u www-data env MYTUBE_ADMIN_PASSWORD='otra-contraseña-larga-y-única' \
  php /var/www/mytube/backend/reset-admin-password-cli.php admin@mytube.home.arpa
```

Si se omite `MYTUBE_ADMIN_PASSWORD`, el comando genera una contraseña aleatoria y la muestra una vez. También borra los bloqueos de acceso y revoca todas las sesiones activas de esa cuenta. Solo funciona por CLI y no se puede abrir desde la web.

Los ejemplos de mantenimiento utilizan Debian. En Alpine, ejecuta el mismo archivo PHP con `su-exec apache:apache php84` en lugar de `sudo -u www-data php`. Para recuperar credenciales, sitúa `MYTUBE_ADMIN_PASSWORD=...` antes de `su-exec`; la variable se transmite a PHP. Para sincronizar desde el servidor Alpine:

```bash
su-exec apache:apache php84 /var/www/mytube/backend/sync-videos-cli.php
```

## 13. Actualizaciones y copias

Ejecuta de nuevo el instalador de tu distribución para actualizar. Antes de cambiar el directorio desplegado, detiene Apache y guarda una copia SQLite verificada bajo `/var/backups/mytube`. Los vídeos permanecen en `/var/www/mytube-videos` y el árbol web anterior queda como copia de reversión. El instalador necesita una instancia de Apache dedicada y se niega a alterar una que tenga sitios ajenos habilitados.

Mantén copias independientes tanto de la base SQLite como del directorio de vídeos. Una copia del catálogo sin los archivos multimedia y JSON no puede reproducir el contenido importado.

Los instaladores independientes son `deploy/install-update-reinstall-debian.sh` y `deploy/install-update-reinstall-alpine.sh`. Apache escucha solo en `127.0.0.1:11080` (HTTP) y `127.0.0.1:11443` (HTTPS con TLS activado). HAProxy ocupa los puertos públicos 80/443 y envía el protocolo PROXY al backend HTTPS. HTTP redirige a la URL HTTPS pública, que HAProxy entrega a 11443; el puerto interno no aparece en las URL del navegador.

Antes de modificar la configuración, el instalador registra los archivos anteriores en una carpeta privada de copias. Genera y valida los certificados nuevos antes de activarlos. Un fallo posterior restaura certificado y clave, ajustes PHP, listeners de Apache, enlaces de sitios y módulos y el árbol web anterior antes de intentar reiniciar Apache. Los fallos de recuperación se registran. Antes de confirmar el despliegue, el instalador comprueba el acceso HTTPS local con protocolo PROXY, validando el certificado y la respuesta de la aplicación.

`MYTUBE_ADMIN_PASSWORD` debe tener entre 12 y 4096 caracteres. Si se omite para una cuenta nueva o con la contraseña histórica, se genera una aleatoria. El instalador guarda la salida de credenciales de la CLI en `/root/app-web-credentials.txt`, separada del registro `/root/app-web-install.log`; ambos archivos tienen permisos 0600. Los comandos de mantenimiento ejecutados directamente siguen mostrando sus credenciales en la terminal.

El instalador despliega `frontend/` y `backend/` juntos bajo `/var/www/mytube`. La base pasa a `/var/www/mytube/backend/db/mytube.db`; `/var/www/mytube/backend/videos` enlaza con el directorio persistente `/var/www/mytube-videos`. Las actualizaciones copian la antigua `/var/www/mytube/db/mytube.db` mediante una copia SQLite verificada y conservan los medios. La reversión recupera la estructura anterior y su configuración de Apache.

La única raíz pública HTTP es `frontend/public/`. Las vistas, los componentes y las traducciones permanecen en carpetas privadas del frontend; los controladores PHP, las acciones, la configuración, la base y los medios pertenecen al backend. La entrada pública distribuye las URL existentes `/index.php`, `/actions/*.php`, `/media.php` y `/thumb.php` sin exponer archivos internos del backend.

Ambos instaladores son idempotentes respecto al estado de la aplicación: las ejecuciones repetidas conservan cuentas, hashes de contraseñas, ajustes, listas, bloqueos de acceso y medios. Reutilizan los certificados válidos cuya clave coincide y a los que les quedan más de 30 días. No reescriben configuraciones idénticas y solo registran credenciales cuando se crean o rotan. Un bloqueo exclusivo impide despliegues simultáneos y los procesos del servicio no lo heredan. Cada ejecución sigue añadiendo su registro y creando copias para recuperación. Un archivo de código distinto, otro dominio solicitado o nuevas versiones de paquetes se consideran una actualización.

En Alpine se utilizan `apk`, PHP 8.4, el usuario/grupo `apache` y OpenRC. En contenedores sin una instancia OpenRC arrancada se controla `httpd` directamente y se registra el arranque con `rc-update`. El instalador gestiona `/etc/apache2/httpd.conf`, `/etc/apache2/mytube-ports.conf`, `/etc/apache2/mytube-site.conf` y `/etc/php84/conf.d/99-mytube.ini`; no añade includes ni listeners duplicados. Las sesiones privadas viven en `/var/lib/mytube/sessions`. La configuración gestionada sustituye la configuración SSL predeterminada del paquete para que Apache solo escuche en los dos puertos locales del backend.

## 14. Solución de problemas

### La base no está inicializada

Ejecuta `seed.php` como usuario web y proporciona una contraseña segura:

```bash
sudo -u www-data env MYTUBE_ADMIN_PASSWORD='sustituye-esto-por-una-contraseña-larga-y-única' \
  php /var/www/mytube/backend/seed.php
```

### Un archivo no se importa

Comprueba que su extensión sea compatible, que `www-data` pueda leerlo, que no supere 10 GiB y que `ffprobe` reconozca un flujo de vídeo. Consulta `/var/www/mytube-logs/error.log` para ver los detalles del servidor.

### El navegador rechaza HTTPS

Confía en el certificado generado desde el cliente o instala uno emitido por tu organización. No ignores avisos de certificado en un servicio de producción.

### Las conexiones directas fallan antes de procesar HTTP

El VirtualHost HTTPS instalado espera el protocolo PROXY de HAProxy. Confirma que el proxy anterior lo envíe o pide al administrador que adapte `RemoteIPProxyProtocol` a la topología real y ejecute `apache2ctl configtest`.

### La aplicación muestra un error genérico

Los errores detallados de base de datos y renderizado no se incluyen deliberadamente en las respuestas web. Revisa el registro de errores de Apache; así no se revelan rutas ni consultas SQL a los usuarios.

`php backend/seed.php` crea el administrador, la configuración y las categorías de navegación, con un catálogo vacío. `php backend/seed.php --demo` añade explícitamente metadatos de ejemplo, ocultos hasta importar los archivos multimedia correspondientes. Las migraciones también ocultan las entradas antiguas sin ruta de archivo conservando filas y datos personales. Detén el tráfico web antes de usar `--force` para sustituir una base existente.
