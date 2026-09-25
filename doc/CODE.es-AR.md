# Guía del código de MyOwnTube

Este documento permite consultar de forma quirúrgica el código actual. Las rutas son relativas a la raíz y las líneas de los símbolos corresponden al código incluido.

## Índice

1. [Arquitectura y decisiones de diseño](#1-arquitectura-y-decisiones-de-diseño)
2. [Mapa de módulos](#2-mapa-de-módulos)
3. [Índice de símbolos clave](#3-índice-de-símbolos-clave)
4. [Flujos principales](#4-flujos-principales)
5. [Entradas, rutas y comandos](#5-entradas-rutas-y-comandos)
6. [Análisis de impacto](#6-análisis-de-impacto)
7. [Puntos de extensión](#7-puntos-de-extensión)
8. [Invariantes de datos y seguridad](#8-invariantes-de-datos-y-seguridad)

## 1. Arquitectura y decisiones de diseño

MyOwnTube es una webapp PHP renderizada en el servidor con carpetas separadas para presentación y lógica. `frontend/public/index.php` es la entrada pública mínima; `backend/router.php` distribuye las URL y `backend/index.php` controla las páginas. Los controladores de `backend/pages/` preparan los datos e incluyen las plantillas correspondientes de `frontend/views/pages/`. Los componentes HTML/SVG y las traducciones viven en `frontend/`; la lógica compartida y las acciones POST, en `backend/`. Así se mantiene el despliegue sencillo con Apache/PHP y los archivos internos y datos privados quedan fuera de la raíz pública.

SQLite es el único almacén de metadatos. `backend/inc/db.php` centraliza PDO y las consultas preparadas; WAL permite lecturas normales durante escrituras breves y cada conexión activa claves foráneas. `seed.php` crea el esquema inicial. Las migraciones idempotentes de `backend/inc/session.php` adaptan instalaciones antiguas sin borrar filas, contadores ni contenido y asignan al administrador los datos privados que antes eran globales.

Los bytes de video no se guardan en SQLite. Cada video importado tiene el original, un JSON adyacente y normalmente una miniatura JPEG. SQLite conserva el catálogo, el estado de usuario, un `file_path` relativo seguro e `is_available`. La sincronización oculta las filas cuyos archivos desaparecen, conserva su estado de usuario y las reactiva cuando vuelve el contenido. `media.php` y `thumb.php` son pasarelas autenticadas; Apache bloquea el acceso directo a la base y al directorio multimedia.

La autenticación usa sesiones PHP. Las contraseñas usan Argon2id y los cifrados antiguos se renuevan después de un acceso correcto. Cada sesión guarda el `session_version` de la cuenta en la base; los cambios y restablecimientos de contraseña lo incrementan para invalidar las sesiones antiguas. Las mutaciones, incluido el registro del historial de búsqueda, requieren POST y un token CSRF ligado a la sesión. La salida se escapa al renderizar y los intentos de acceso se limitan mediante un hash de la dirección cliente. El VirtualHost de Debian espera un proxy confiable que envíe el protocolo PROXY de HAProxy para que Apache informe la dirección correcta.

La internacionalización usa catálogos JSON planos. El inglés es la reserva; la cuenta guarda el idioma del usuario y un ajuste global se aplica a cuentas nuevas. Los cuatro catálogos deben mantener claves idénticas.

El instalador también actualiza: descarga por HTTPS, detiene Apache antes de copiar SQLite, verifica la copia, despliega desde un directorio de preparación, conserva los videos fuera del código, valida Apache y mantiene una reversión. Como cambia globalmente el MPM y el módulo PHP, rechaza primero los sitios habilitados ajenos y necesita una instancia de Apache dedicada. El usuario web solo puede escribir en base, videos y registros.

La interfaz sigue el tema claro u oscuro del sistema, adapta la navegación al móvil y ofrece en-GB, en-US, es-AR y es-ES en ese orden. Los listados del catálogo y de las cuentas muestran como máximo 24 entradas por página, con controles anterior/siguiente y contadores totales.

Los instaladores independientes son `deploy/install-update-reinstall-debian.sh` y `deploy/install-update-reinstall-alpine.sh`. Apache escucha solo en `127.0.0.1:11080` (HTTP) y `127.0.0.1:11443` (HTTPS con TLS activado). HAProxy ocupa los puertos públicos 80/443 y envía el protocolo PROXY al backend HTTPS. HTTP redirige a la URL HTTPS pública, que HAProxy entrega a 11443; el puerto interno no aparece en las URL del navegador.

`php backend/seed.php` crea el administrador, la configuración y las categorías de navegación, con un catálogo vacío. `php backend/seed.php --demo` agrega explícitamente metadatos de ejemplo, ocultos hasta importar los archivos multimedia correspondientes. Las migraciones también ocultan las entradas antiguas sin ruta de archivo conservando filas y datos personales. Detené el tráfico web antes de usar `--force` para sustituir una base existente.

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

El instalador despliega `frontend/` y `backend/` juntos bajo `/var/www/mytube`. La base pasa a `/var/www/mytube/backend/db/mytube.db`; `/var/www/mytube/backend/videos` enlaza con el directorio persistente `/var/www/mytube-videos`. Las actualizaciones copian la antigua `/var/www/mytube/db/mytube.db` mediante una copia SQLite verificada y conservan los medios. La reversión recupera la estructura anterior y su configuración de Apache.

## 2. Mapa de módulos

| Módulo | Ruta | Responsabilidad | Depende de | Usado por |
|---|---|---|---|---|
| Configuración | `backend/config.php` | Identidad, cuatro idiomas ordenados, tamaño de página, rutas, límites y administrador | PHP | Todas las entradas |
| Controlador de páginas | `backend/index.php` | Cabeceras, esquema, acceso, despacho y composición | Módulos `inc`, `pages` | Pedidos de páginas |
| Páginas | `backend/pages/*.php` | Consultar y renderizar cada pantalla | Base, sesión, componentes, i18n | `backend/index.php` |
| Acciones | `backend/actions/*.php` | Validar POST y modificar estado | Base, sesión, ayudas, i18n, video | Formularios y progreso |
| Adaptador de base | `backend/inc/db.php` | PDO único, pragmas y consultas preparadas | Configuración, PDO SQLite | Toda lectura/escritura |
| Sesión y autenticación | `backend/inc/session.php` | Cookies, CSRF, usuarios, roles, límites y migraciones | Base, configuración, i18n | Entradas protegidas |
| Internacionalización | `backend/inc/i18n.php` | Resolver idioma, cargar catálogos y persistir selección | Base, sesión, JSON | Interfaz y acciones |
| Dominio de video | `backend/inc/videos.php` | Rutas seguras, ffprobe, metadatos, miniaturas e importación | Base, sesión, FFmpeg | Subida, sync, multimedia |
| Componentes | `frontend/components/components.php` | Tarjetas, filas, avatares y navegación de páginas con salida escapada | Ayudas, iconos, video, i18n | Páginas |
| Armazón visual | `frontend/components/chrome.php` | Barra superior, lateral y menú de usuario | Sesión, ayudas, iconos, i18n | `backend/index.php` |
| Ayudas e iconos | `backend/inc/{helpers,icons}.php` | Escape, URL, formatos y SVG | i18n opcional | Renderizado y redirección |
| Pasarelas multimedia | `backend/{media,thumb}.php` | Video/rangos y JPEG autenticados | Base, sesión, video | Reproductor, tarjetas, descarga |
| Catálogos | `frontend/lang/*.json` | El mismo conjunto de cadenas en cada idioma | JSON | `backend/inc/i18n.php` |
| Inicializador | `backend/seed.php` | Inicialización de producción vacía; ejemplos explícitos no disponibles; copia verificada al sustituir | Configuración, PDO | Instalador y operador |
| CLI de mantenimiento | `backend/*-cli.php` | Administrador seguro, recuperación y sincronización | Módulos compartidos | Instalador y operador |
| Apache y estilos | `frontend/public/.htaccess`, `frontend/public/styles.css` | Bloqueo de datos y presentación adaptable | Apache, HTML renderizado | Web desplegada |
| Despliegue Debian | `deploy/install-update-reinstall-debian.sh` | Backend Apache/TLS local, registros y credenciales protegidos, certificados preparados y reversión de configuración | Debian y herramientas del sistema | Operador `root` |
| Ayuda de enlaces | `backend/tools/generate_symlink.py` | Enlazar un árbol externo sin superposiciones | Python 3.13, archivos | Operador antes del sync |
| Enrutador de desarrollo | `deploy/local-dev-router.php` | Reproducir el bloqueo de rutas privadas con el servidor integrado | Servidor de desarrollo de PHP | Preparación local |
| Pruebas de regresión | `tests/*` | Integraciones PHP/HTTP/multimedia/Apache obligatorias y pruebas de navegador separadas; temporales en `_/temp/` | PHP, Bash, Python, Node.js, tar, curl, SQLite, FFmpeg, Apache, OpenSSL; Chromium/Playwright | Colaboradores y CI |
| Navegación del navegador | `frontend/public/ui.js` | Barra lateral móvil, fondo, Escape y estado accesible del menú | DOM nativo y matchMedia | `backend/index.php` |
| Entrada pública | `frontend/public/index.php` | Entregar peticiones HTTP al backend privado | `backend/router.php` | Apache y enrutador de desarrollo |
| Distribuidor HTTP | `backend/router.php` | Admitir solo páginas, acciones y medios autenticados | Handlers de páginas, acciones y medios | Entrada pública |
| Plantillas | `frontend/views/*.php`, `frontend/views/pages/*.php` | Renderizar datos preparados y documento común | Componentes frontend y helpers backend | Controladores de páginas |
| Datos de navegación | `backend/inc/navigation.php` | Consultar notificaciones y sugerencias y preparar el contexto de navegación | Base, sesión, idiomas | `frontend/components/chrome.php` |
| Despliegue Alpine | `deploy/install-update-reinstall-alpine.sh` | Despliegue idempotente con apk/PHP 8.4/OpenRC, configuración Apache gestionada y sesiones privadas | Alpine main/community, Apache, PHP, SQLite, OpenSSL | Operador root |

## 3. Índice de símbolos clave

| Símbolo | Archivo:línea | Qué hace |
|---|---|---|
| `fDb` | `backend/inc/db.php:19` | Abre y conserva PDO SQLite con claves foráneas, WAL y espera |
| `fQuery` | `backend/inc/db.php:45` | Ejecuta una consulta preparada sin revelar SQL al usuario |
| `fE` | `backend/inc/helpers.php:3` | Escapa texto para HTML |
| `fUrl` | `backend/inc/helpers.php:7` | Construye URL del controlador frontal |
| `fCsrfRequire` | `backend/inc/session.php:69` | Rechaza una mutación sin el token de sesión correcto |
| `fAuthEnsureSchema` | `backend/inc/session.php:115` | Crea tablas de acceso/ajustes y ejecuta migraciones idempotentes |
| `fAuthEnsureVideoAvailability` | `backend/inc/session.php:224` | Añade disponibilidad y oculta filas antiguas sin archivo sin borrar datos personales |
| `fAuthMigrateUserTable` | `backend/inc/session.php:278` | Reconstruye tablas privadas antiguas y copia filas con propietario |
| `fAuthLoginAllowed` | `backend/inc/session.php:520` | Comprueba y vence el límite de intentos por dirección |
| `fAuthCreateUser` | `backend/inc/session.php:569` | Valida, cifra la contraseña e inserta una cuenta |
| `fAuthLogin` | `backend/inc/session.php:633` | Verifica acceso, registra fallos y rota la sesión |
| `fAuthChangePassword` | `backend/inc/session.php:687` | Cambia la contraseña, rota la sesión actual y revoca las demás |
| `fAuthAdminUpdateRole` | `backend/inc/session.php:737` | Cambia el rol sin quitar al último administrador |
| `fCurrentUser` | `backend/inc/session.php:810` | Carga la cuenta y rechaza una versión de sesión antigua |
| `fRequireLogin` | `backend/inc/session.php:875` | Exige una cuenta autenticada |
| `fRequireAdmin` | `backend/inc/session.php:882` | Exige autenticación y rol administrativo |
| `fCurrentLocale` | `backend/inc/i18n.php:132` | Resuelve idioma de cuenta, sesión o sitio |
| `fT` | `backend/inc/i18n.php:162` | Traduce con reserva inglesa y reemplaza parámetros |
| `fVideoPathFromFilePath` | `backend/inc/videos.php:126` | Resuelve un archivo solo dentro del directorio de videos |
| `fVideoSaveJson` | `backend/inc/videos.php:180` | Guarda el JSON mediante reemplazo atómico |
| `fVideoValidateFile` | `backend/inc/videos.php:356` | Aplica tamaño y exige un flujo real detectado por ffprobe |
| `fVideoImportJson` | `backend/inc/videos.php:450` | Normaliza el JSON e inserta o actualiza el catálogo |
| `fVideosSyncFolder` | `backend/inc/videos.php:542` | Valida, importa auxiliares/miniaturas y reconcilia disponibilidad |
| `fVideoCard` | `frontend/components/components.php:54` | Renderiza una tarjeta reutilizable y escapada |
| `fValidateApacheIsolation` | `deploy/install-update-reinstall-debian.sh:211` | Rechaza cambios globales de Apache si hay sitios ajenos habilitados |
| `fMain` | `deploy/install-update-reinstall-debian.sh:707` | Ordena validación, copia, despliegue, configuración y arranque |
| `fMain` | `backend/tools/generate_symlink.py:93` | Valida la CLI, crea enlaces y devuelve estado significativo |
| `fPaginate` | `backend/inc/helpers.php:25` | Cuenta filas filtradas, limita la página y obtiene como máximo cPageSize entradas |
| `fPaginationRender` | `frontend/components/components.php:151` | Genera enlaces anterior/siguiente conservando filtros |
| `fBackupConfigFile` | `deploy/install-update-reinstall-debian.sh:84` | Registra el archivo o enlace antes de modificarlo en el despliegue |
| `fRestoreConfigFiles` | `deploy/install-update-reinstall-debian.sh:108` | Restaura la configuración registrada tras un fallo |
| `fCreateCertificate` | `deploy/install-update-reinstall-debian.sh:438` | Valida los archivos TLS preparados antes de activarlos |
| `fVerifyBackend` | `deploy/install-update-reinstall-debian.sh:626` | Verifica el backend HTTPS local y su certificado mediante el protocolo PROXY |
| `fNavigationData` | `backend/inc/navigation.php:3` | Prepara datos de navegación sin renderizar HTML |
| `fChannelById` | `backend/inc/videos.php:644` | Guarda en caché los canales que utiliza el renderizado de videos |
| `fIdSet` | `backend/inc/videos.php:651` | Carga los ID de favoritos o guardados del usuario actual |
| `fAcquireLock` | `deploy/install-update-reinstall-debian.sh:53` | Impide instalaciones simultáneas |
| `fActivateConfigFile` | `deploy/install-update-reinstall-debian.sh:65` | Sustituye únicamente la configuración que cambia |
| `fConfigureAlpineMain` | `deploy/install-update-reinstall-alpine.sh:473` | Genera la configuración Apache de Alpine sin includes duplicados |
| `fMain` | `deploy/install-update-reinstall-alpine.sh:748` | Ejecuta la transacción de instalación o actualización en Alpine |

## 4. Flujos principales

### Pedido de página

`GET /index.php?page=…` → `frontend/public/index.php` → `backend/router.php` → `backend/index.php` carga lógica y renderizado → esquema, idioma y acceso → `backend/pages/<página>.php` prepara los datos → `frontend/views/pages/<página>.php` renderiza → `frontend/views/layout.php` compone el documento. Las URL de acciones y medios pasan por la misma entrada pública hacia sus handlers del backend.

### Inicio de sesión

Formulario → `POST actions/login.php` → `fCsrfRequire()` → `fAuthLoginAllowed()` → consulta preparada y `password_verify()` → contador de fallo o `fAuthStartSession()` → rotación del identificador y almacenamiento de `session_version` → 303 a destino interno validado. Cada carga posterior de usuario compara esa versión con SQLite.

### Búsqueda e historial privado

Formulario superior → `POST actions/record-search.php` → sesión/CSRF → consulta escalar limitada → inserción o actualización del historial propio → 303 a `GET page=search&q=…` → consulta de resultados de solo lectura. Los GET directos y de sugerencias no modifican el historial.

### Cambio de contraseña

Formulario de ajustes o CLI de recuperación → comprobar credenciales cuando corresponde → cifrado Argon2id → incrementar `users.session_version`. El cambio web también rota la sesión actual y guarda la nueva versión; las demás sesiones, o todas tras la CLI, quedan inválidas en su siguiente pedido.

### Creación de cuenta

Registro o formulario administrativo → guardia → `fAuthCreateUser()` → validación de correo, contraseña y nombre → `password_hash()` → inserción. El registro público inicia sesión; la creación administrativa vuelve a usuarios.

### Subida y sincronización

Formulario → `upload-video.php` → sesión/CSRF → extensión y tamaño → destino único → mover subida → `fVideoValidateFile()` → metadatos, JSON atómico, importación y miniatura. Un fallo limpia ese intento. La sincronización comprueba `ffprobe`, captura los identificadores existentes con archivo, recorre la carpeta, valida flujos, crea auxiliares ausentes, registra fallos por archivo y actualiza el catálogo. Después marca transaccionalmente como no disponibles los identificadores iniciales ausentes de las importaciones correctas, sin borrar filas ni estado de usuario ni ocultar subidas creadas después de la captura.

### Reproducción y progreso

Página → `media.php` valida sesión, ID y ruta → interpreta un rango → libera el bloqueo de sesión → envía bloques de 1 MiB. Tras `playing`, JavaScript publica el porcentaje con CSRF; una transacción crea historial e incrementa vistas solo una vez o actualiza progreso.

### Migración de inicio

Entrada web/CLI → `fAuthEnsureSchema()` → tablas ausentes → idioma y versión de sesión → disponibilidad de videos → administrador → reconstrucción transaccional de tablas privadas → `user_id` en comentarios/listas → índices y marcas. No se vacían datos ni métricas.

### Actualización Debian y Alpine

Instalador `fMain()` → validación de root y bloqueo exclusivo → registro privado → validación y aislamiento → paquetes/directorios/archivo → detener Apache → copia SQLite verificada → preparar web → registrar configuración → preparar y validar certificado → listeners locales y VirtualHost TLS → inicializar/migrar → reiniciar → verificar HTTPS mediante el protocolo PROXY → guardar credenciales → confirmar despliegue. La limpieza tras un fallo restaura configuración y árbol web antes de reiniciar.

### Catálogo y colecciones paginadas

Vista → recuento filtrado y consulta ordenada → `fPaginate()` limita la página solicitada y aplica un máximo de 24 filas → renderizado de tarjetas/filas → `fPaginationRender()` construye enlaces escapados conservando categoría, búsqueda, propietario o lista. Los comentarios usan `comments_page`; las demás colecciones paginadas, `p`. El orden incluye un ID estable para evitar duplicados entre páginas.

Antes de modificar la configuración, el instalador registra los archivos anteriores en una carpeta privada de copias. Genera y valida los certificados nuevos antes de activarlos. Un fallo posterior restaura certificado y clave, ajustes PHP, listeners de Apache, enlaces de sitios y módulos y el árbol web anterior antes de intentar reiniciar Apache. Los fallos de recuperación se registran. Antes de confirmar el despliegue, el instalador comprueba el acceso HTTPS local con protocolo PROXY, validando el certificado y la respuesta de la aplicación.

### Idempotencia y adaptación a cada distribución

Ambos instaladores son idempotentes respecto al estado de la aplicación: las ejecuciones repetidas conservan cuentas, hashes de contraseñas, ajustes, listas, bloqueos de acceso y medios. Reutilizan los certificados válidos cuya clave coincide y a los que les quedan más de 30 días. No reescriben configuraciones idénticas y solo registran credenciales cuando se crean o rotan. Un bloqueo exclusivo impide despliegues simultáneos y los procesos del servicio no lo heredan. Cada ejecución sigue añadiendo su registro y creando copias para recuperación. Un archivo de código distinto, otro dominio solicitado o nuevas versiones de paquetes se consideran una actualización.

En Alpine se utilizan `apk`, PHP 8.4, el usuario/grupo `apache` y OpenRC. En contenedores sin una instancia OpenRC arrancada se controla `httpd` directamente y se registra el arranque con `rc-update`. El instalador gestiona `/etc/apache2/httpd.conf`, `/etc/apache2/mytube-ports.conf`, `/etc/apache2/mytube-site.conf` y `/etc/php84/conf.d/99-mytube.ini`; no añade includes ni listeners duplicados. Las sesiones privadas viven en `/var/lib/mytube/sessions`. La configuración gestionada sustituye la configuración SSL predeterminada del paquete para que Apache solo escuche en los dos puertos locales del backend.

## 5. Entradas, rutas y comandos

### Páginas y medios

| Ruta/URL/comando | Handler | Archivo |
|---|---|---|
| `GET /index.php?page=login|register` | Acceso y registro condicional | `backend/pages/{login,register}.php` |
| `GET /index.php?page=home|trending|search` | Catálogo, tendencias y búsqueda de solo lectura | `backend/pages/{home,trending,search}.php` |
| `GET /index.php?page=watch&id=…` | Reproductor, acciones y comentarios | `backend/pages/watch.php` |
| `GET /index.php?page=liked|watchlater|history` | Colecciones privadas | `backend/pages/{liked,watchlater,history}.php` |
| `GET /index.php?page=playlists|playlist` | Listas propias | `backend/pages/{playlists,playlist}.php` |
| `GET /index.php?page=upload|profile|settings|users` | Subida, cuenta y administración | `backend/pages/{upload,profile,settings,users}.php` |
| `GET|HEAD /media.php?id=…[&download=1]` | Transmisión/rangos autenticados | `backend/media.php` |
| `GET|HEAD /thumb.php?id=…` | Miniatura autenticada | `backend/thumb.php` |

### Mutaciones POST

Todas requieren CSRF; salvo acceso y registro condicional, también una sesión iniciada.

| Ruta/URL/comando | Handler | Archivo |
|---|---|---|
| `/actions/login.php`, `/actions/register.php`, `/actions/logout.php` | Ciclo de sesión | Archivos homónimos en `backend/actions` |
| `/actions/update-profile.php`, `/actions/change-password.php`, `/actions/set-language.php` | Perfil, contraseña e idioma | Archivos homónimos |
| `/actions/toggle-like.php`, `/actions/toggle-watchlater.php`, `/actions/save-progress.php` | Colecciones y progreso propios | Archivos homónimos |
| `/actions/record-search.php`, `/actions/clear-search-history.php` | Registrar o borrar el historial de búsqueda propio | Archivos homónimos |
| `/actions/clear-history.php`, `/actions/remove-history.php` | Borrado del historial de reproducción propio | Archivos homónimos |
| `/actions/post-comment.php` | Crear comentario | Archivo homónimo |
| `/actions/create-playlist.php`, `/actions/update-playlist.php`, `/actions/delete-playlist.php`, `/actions/update-playlist-item.php` | CRUD de listas propias | Archivos homónimos |
| `/actions/upload-video.php` | Validar, guardar e importar | Archivo homónimo |
| `/actions/sync-videos.php`, `/actions/save-settings.php` | Sincronización y ajustes administrativos | Archivos homónimos |
| `/actions/create-user.php`, `/actions/update-user-role.php`, `/actions/delete-user.php` | Administración de cuentas | Archivos homónimos |

### CLI y despliegue

| Ruta/URL/comando | Handler | Archivo |
|---|---|---|
| `php backend/seed.php [--force] [--demo]` | Crear base; opcionalmente copiar y reemplazar | `backend/seed.php` |
| `php backend/secure-admin-cli.php` | Rotar credenciales históricas y migrar | Archivo indicado |
| `php backend/reset-admin-password-cli.php [correo]` | Restablecer/promover, revocar sesiones y borrar límites | Archivo indicado |
| `php backend/sync-videos-cli.php` | Sincronizar sin navegador | Archivo indicado |
| `deploy/install-update-reinstall-debian.sh` | Instalar, reinstalar o actualizar | Archivo indicado |
| `deploy/install-update-reinstall-alpine.sh` | Instalar, actualizar o reinstalar Alpine conservando el estado | Archivo indicado |
| `python3 backend/tools/generate_symlink.py ORIGEN` | Enlazar un árbol multimedia externo | Archivo indicado |
| `php -S 127.0.0.1:8080 -t frontend/public deploy/local-dev-router.php` | Servir el árbol local con rutas privadas protegidas | `deploy/local-dev-router.php` |
| `tests/run-tests.sh` | Ejecutar validaciones e integraciones obligatorias; --static excluye integraciones explícitamente | Archivo indicado |
| `tests/check-browser.py` | Comprobar paginación y móvil/escritorio en todos los idiomas y temas | `tests/check-browser.py` |

Las pruebas completas requieren PHP con PDO SQLite, mbstring y Argon2id, sqlite3, FFmpeg/ffprobe, Python 3.13+, Node.js, tar, OpenSSL, apache2-bin y libapache2-mod-php. `tests/run-tests.sh` falla si falta una dependencia obligatoria. `tests/run-tests.sh --static` ejecuta explícitamente solo sintaxis, idiomas y enrutador local. `tests/check-browser.py` necesita además Playwright para Python y Chromium; comprueba paginación y maquetación en móvil y escritorio, en cuatro idiomas y ambos temas, abriendo menús y navegación móvil. Ejecutá la suite completa y la comprobación de navegador antes del despliegue. `MYOWNTUBE_APACHE_ROOT` permite indicar la raíz de un paquete Apache extraído; `MYOWNTUBE_CHROMIUM`, el ejecutable Chromium; y `MYOWNTUBE_PHP_MODULE`, un módulo PHP de Apache extraído. Los temporales viven en `_/temp/`.


Las pruebas ejecutan tres flujos sucesivos por distribución y comparan volcados de la base, configuración, certificados y credenciales. También comprueban el bloqueo, el rechazo de sitios ajenos y la reversión sin arrancar un servicio que estaba parado. Las operaciones de paquetes y servicios se simulan en las pruebas portables; las pruebas HTTP de Apache/PHP ejecutan binarios reales.

## 6. Análisis de impacto

| Componente crítico | Qué se ve afectado al cambiarlo |
|---|---|
| Rutas de `backend/config.php` | Base, auxiliares, pasarelas, inicializador, CLI e instalador |
| `backend/inc/db.php` | Toda consulta/transacción, concurrencia y copias |
| `backend/inc/session.php` y `users.session_version` | Páginas, acciones, cookies, CSRF, revocación, roles, migraciones y datos propios |
| `backend/inc/i18n.php` o una clave | Interfaz y cuatro JSON; una ausencia usa inglés |
| Extensiones/rutas de `backend/inc/videos.php` | Subida, sync, reproducción, miniaturas y registros existentes |
| `videos.file_path`/`is_available` | Visibilidad, reproducción, descarga, miniatura, sync, progreso y estado conservado |
| `media.php` | Búsqueda temporal, descargas y concurrencia de sesión |
| `save-progress.php` | Historial, reanudación, vistas y tendencias |
| Claves de tablas por usuario | Colecciones, cascadas y migraciones antiguas |
| Nombres de página | Router, navegación y destinos posteriores al acceso |
| Instalador y permisos | Actualización, recuperación, Apache y límites de escritura |
| Reglas del enrutador local | Archivos del repositorio que puede exponer el servidor integrado |
| Protocolo PROXY | IP de límites/registros y conexiones directas |

## 7. Puntos de extensión

### Agregar una página

1. Agregá el nombre de ruta a la lista permitida en `backend/index.php`.
2. Creá `backend/pages/<nombre>.php` para consultas y lógica de acceso.
3. Creá `frontend/views/pages/<nombre>.php` para el HTML escapado e incluilo desde el controlador mediante `cFrontendPath`.
4. Agregá la navegación en `frontend/components/chrome.php` y los textos en los cuatro catálogos `frontend/lang/*.json`.
5. Actualizá los mapas de rutas/módulos y comprobá la página con ambos comandos de pruebas.

### Agregar una mutación

1. Creá `backend/actions/<nombre>.php` y cargá los módulos compartidos.
2. Exigí POST, `fCsrfRequire()` y `fRequireLogin()` o `fRequireAdmin()`.
3. Validá tipo, longitud, propiedad y referencias; usá marcadores y transacción si hay varios pasos.
4. Incluí `fCsrfField()` en el formulario, un aviso traducido y una redirección 303.
5. Agregá o ampliá una regresión en `tests/run-tests.sh`; una página GET debe seguir siendo de solo lectura.

### Agregar un idioma

Incluí el idioma y nombre en `backend/config.php`, creá un JSON con todas las claves inglesas y conservá marcadores. Comprobá paridad y persistencia y actualizá la documentación localizada.

### Agregar una colección por usuario

Usá una clave primaria que empiece por `user_id`, una clave foránea con la cascada prevista, filtrá siempre por `fUserId()` y agregá un índice para el orden. Si antes existía una tabla global, creá una migración transaccional probada con datos poblados.

### Agregar metadatos de video o una CLI

Modificá juntos `fVideoCreateMetadata()` y `fVideoImportJson()` y mantené JSON atómico, rutas seguras y compatibilidad con campos ausentes. Una CLI debe rechazar SAPIs web, reutilizar módulos, fallar con estado no nulo y quedar bloqueada por Apache.

## 8. Invariantes de datos y seguridad

- Ninguna ruta del catálogo se resuelve fuera de `cVideosPath`.
- Un video reproducible necesita original y JSON; la miniatura requiere además el JPEG.
- Las filas con archivo ausentes tras una sincronización correcta quedan no disponibles, no se borran; una importación posterior las reactiva.
- La extensión es solo un filtro: `ffprobe` debe detectar códec y dimensiones positivas.
- Los JSON y bases nuevas se activan por reemplazo atómico.
- Las consultas privadas incluyen `user_id` y las listas verifican propietario.
- No se puede degradar/eliminar al último administrador ni eliminar la propia cuenta activa.
- El registro empieza desactivado y las contraseñas cifradas miden entre 12 y 4096 caracteres.
- Las sesiones autenticadas deben coincidir con `users.session_version`; cambiar o restablecer una contraseña revoca las versiones antiguas.
- Las mutaciones iniciadas por usuarios usan POST con CSRF; los GET de resultados y sugerencias nunca registran historial de búsqueda. Las redirecciones se restringen al controlador.
- Los parámetros escalares rechazan arrays y los identificadores SQL dinámicos se limitan mediante listas permitidas explícitas.
- Las pasarelas cierran la sesión antes de enviar archivos largos.
- Las respuestas web ocultan SQL y rutas internas; el detalle queda en los registros.
- El instalador de Debian solo cambia módulos globales de Apache cuando no hay sitios ajenos habilitados.
