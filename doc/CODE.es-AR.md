# Guía del código de MyOwnTube

Este CODE también está disponible en:\
[en-GB](CODE.en-GB.md), [en-US](CODE.md), es-AR, [es-ES](CODE.es-ES.md)

Este documento es un mapa quirúrgico del código actual. Las rutas son relativas a la raíz del repositorio, y los números de línea de los símbolos se refieren al código fuente actual.

## Índice

1. [Arquitectura y decisiones de diseño](#1-arquitectura-y-decisiones-de-diseño)
2. [Mapa de módulos](#2-mapa-de-módulos)
3. [Índice de símbolos clave](#3-índice-de-símbolos-clave)
4. [Flujos principales](#4-flujos-principales)
5. [Puntos de entrada, rutas y comandos](#5-puntos-de-entrada-rutas-y-comandos)
6. [Análisis de impacto](#6-análisis-de-impacto)
7. [Puntos de extensión](#7-puntos-de-extensión)
8. [Invariantes de datos y de seguridad](#8-invariantes-de-datos-y-de-seguridad)
9. [Desarrollo local](#9-desarrollo-local)

## 1. Arquitectura y decisiones de diseño

MyOwnTube es una aplicación web PHP renderizada en el servidor, con directorios separados para la presentación y para la aplicación. Solo `frontend/public/` es la raíz de documentos HTTP. Su `.htaccess` envía toda dirección que no sea un archivo público real (`styles.css`, `ui.js`) a `frontend/public/index.php`, que le pasa la solicitud a `backend/router.php`. Los componentes HTML/SVG, las plantillas y las traducciones viven en `frontend/`; el enrutamiento, los controladores, los manejadores de formularios y la lógica compartida viven en `backend/`. Así el despliegue con Apache/PHP sigue siendo simple y ningún código fuente privado ni dato de ejecución llega nunca a ser un archivo público.

**Una única tabla de rutas.** `backend/inc/routes.php` es la única fuente de verdad de todas las direcciones públicas. `fRoutePages()` asocia nombres de página con rutas limpias (`/`, `/watch/{id}/`, `/user/dashboard/profile/`...), `fRouteSectionDefaults()` envía las raíces de sección (`/user/dashboard/`, `/admin/dashboard/`, `/auth/`) a su pestaña por defecto, y `fRouteApi()` enumera cada endpoint de la API con sus métodos, manejador, nivel de acceso, campos y respuestas. El controlador de páginas, `fUrl()`, el despachador de la API y el documento OpenAPI de `/api/doc/` leen todos estas tablas, así que una ruta nueva se declara una sola vez. Las direcciones anteriores `/index.php?page=...` responden con un 301 hacia la ruta nueva, y las direcciones sin la barra final se canonicalizan con un 301.

**Páginas y paneles.** Las páginas siguen las reglas de endpoints: autenticación en `/auth/login/`, `/auth/register/` y `/auth/recover/`; un panel de usuario en `/user/dashboard/` con las pestañas `profile` y `account`; un panel de administrador en `/admin/dashboard/` con las pestañas `users` y `settings`. Cada pestaña tiene su propia URL y la barra de pestañas la renderiza `fDashboardTabs()` a partir de `fRouteDashboardTabs()`. Una solicitud anónima a una página protegida se redirige a `/auth/login/?next=<ruta solicitada>` y vuelve ahí después de iniciar sesión.

**API.** Todo lo que se comporta como una API vive bajo `/api/`: los manejadores de formularios (`/api/auth/*`, `/api/user/*`, `/api/admin/*`), el endpoint JSON de progreso, y el stream de video y la miniatura autenticados. Los manejadores son los archivos de `backend/actions/` más `backend/media.php` y `backend/thumb.php`; el router asocia la ruta limpia de la API con el archivo e inyecta los parámetros de ruta, como `{id}`, en `$_GET`, de modo que los manejadores conservan su estructura original. El router responde 404 para las rutas de API desconocidas y 405 con `Allow` para los métodos no admitidos. Los endpoints de formularios mantienen el patrón post/redirect/get: validan el token CSRF de la sesión, guardan un aviso traducido y responden 303.

SQLite es el único almacén de metadatos. Las sentencias preparadas de PDO están centralizadas en `backend/inc/db.php`; el modo WAL permite lecturas normales mientras se completan escrituras cortas. Las claves foráneas están activadas en cada conexión de la aplicación. El esquema lo crea `seed.php`, mientras que las migraciones de compatibilidad idempotentes de `backend/inc/session.php` actualizan las instalaciones anteriores durante el arranque (incluido el cambio de la dirección del administrador por defecto de `admin@mytube.home.arpa` a `admin@myowntube.home.arpa`).

Los bytes de los videos no se guardan en SQLite. Cada video importado tiene un archivo multimedia original, un sidecar JSON al lado y, normalmente, una miniatura JPEG generada dentro del directorio de videos. SQLite guarda el estado del catálogo y de los usuarios, más un `file_path` relativo y seguro y una marca `is_available`. La sincronización de carpetas oculta las filas cuyos archivos desaparecieron, conservando su estado de usuario, y las reactiva si el archivo multimedia vuelve.

La autenticación usa sesiones de PHP (cookie `MYOWNTUBESESSID`, constante `cSessionName`). Las contraseñas usan Argon2id y los hashes más antiguos se actualizan después de un inicio de sesión exitoso. Cada sesión lleva el `session_version` de la cuenta, respaldado en la base de datos; los cambios de contraseña, las contraseñas establecidas por un administrador y los restablecimientos por CLI lo incrementan, así que las sesiones obsoletas fallan en su siguiente verificación. La aplicación no envía correos, por lo que la recuperación de la contraseña consiste en que un administrador establezca una contraseña nueva desde `/admin/dashboard/users/` o en usar la CLI de restablecimiento del lado del servidor; `/auth/recover/` explica las dos opciones.

La internacionalización usa catálogos JSON planos de clave/valor. El inglés es el idioma de respaldo; el idioma del usuario se guarda en la cuenta, mientras que un idioma por defecto del sitio se aplica a los visitantes y a las cuentas nuevas. Los cuatro catálogos tienen que mantener claves idénticas. La referencia de la API en `/api/doc/` es documentación técnica y está escrita solo en inglés.

El instalador también es el mecanismo de actualización. Apache escucha directamente en los puertos 80 (redirección permanente a HTTPS) y 443 (TLS), sin un HAProxy local ni protocolo PROXY. El instalador descarga por HTTPS, detiene Apache antes del respaldo de SQLite, valida el respaldo, despliega a través de un directorio de preparación (staging), mantiene los archivos multimedia fuera del árbol de código, valida Apache antes de reiniciarlo y conserva copias para revertir. Como cambia el MPM global de Apache y el módulo de PHP, rechaza los sitios habilitados ajenos y, por lo tanto, está pensado para una instancia de Apache dedicada. El código es inmutable para el usuario web; solo la base de datos, los archivos multimedia y los registros se pueden escribir. Apache sirve el código desde `/var/www/myowntube` (la estructura `/opt/<usuario>` de las reglas del proyecto se aplica solo a los servicios que no son Apache). Una instalación hecha antes del cambio de nombre (`/var/www/mytube`, `mytube.db`, `/root/app-web-*`) la migra la primera ejecución, y sus archivos se retiran al directorio de copias de seguridad.

```text
frontend/
  public/        # index.php, styles.css, ui.js, .htaccess
  views/         # layout, plantillas de páginas, plantilla de la referencia de la API
  components/    # renderizado de HTML y SVG
  lang/          # textos traducidos de la interfaz
backend/
  actions/       # manejadores de formularios y JSON de la API
  pages/         # controladores de páginas y consultas
  inc/           # lógica compartida: rutas, OpenAPI, BD, sesión, i18n, videos
  tools/         # utilidades de importación de multimedia
  db/            # base de datos privada de ejecución (myowntube.db)
  videos/        # directorio privado de multimedia o enlace
doc/
deploy/
```

## 2. Mapa de módulos

| Módulo | Ruta | Responsabilidad | Depende de | Usado por |
|---|---|---|---|---|
| Configuración | `backend/config.php` | Identidad de la aplicación, nombre de la cookie de sesión, cuatro idiomas ordenados, tamaño de página, rutas, límites de seguridad y valores por defecto del administrador | Entorno de ejecución de PHP | Todos los puntos de entrada web y CLI |
| Tablas de rutas | `backend/inc/routes.php` | Rutas de páginas, valores por defecto de las secciones, nombres de páginas heredados, pestañas de los paneles, tabla de la API, resolución de rutas, `fUrl()`/`fApiUrl()` | Ninguna | Router, controlador de páginas, helpers, OpenAPI, vistas |
| Despachador HTTP | `backend/router.php` | Despacho de `/api/` con manejo de 404/405 y parámetros de ruta; todo lo demás va al controlador de páginas | Tablas de rutas, manejadores | Arranque público |
| Controlador frontal de páginas | `backend/index.php` | Cabeceras de seguridad, redirecciones heredadas y de barra final, valores por defecto de las secciones, disponibilidad del esquema, control de acceso, despacho de páginas, página 404, layout | Todos los módulos de `inc`, `pages` | Solicitudes de páginas del navegador |
| Páginas | `backend/pages/*.php` | Consultar y renderizar una pantalla de la aplicación (incluidas `account`, `site-settings`, `recover`) | BD, sesión, helpers, componentes, i18n | `backend/index.php` |
| Manejadores de la API | `backend/actions/*.php` | Validar solicitudes POST autenticadas y modificar el estado | BD, sesión, helpers, i18n; videos cuando hace falta | Formularios HTML y el JavaScript de progreso de reproducción, a través de `/api/...` |
| Referencia de la API | `backend/api-doc.php`, `backend/inc/openapi.php`, `frontend/views/api-doc.php` | Documento OpenAPI 3.1 y HTML estilo Swagger generados a partir de `fRouteApi()` | Tablas de rutas, helpers | `/api/doc/`, `/api/doc/openapi.json` |
| Adaptador de base de datos | `backend/inc/db.php` | Una conexión PDO, pragmas de SQLite, helpers de consultas preparadas, registro privado de errores | `backend/config.php`, PDO SQLite | Páginas, manejadores, autenticación, multimedia, CLI |
| Sesión y autenticación | `backend/inc/session.php` | Política de cookies, CSRF, usuarios, roles, restablecimiento de la contraseña de administrador, limitación de intentos, compatibilidad del esquema, guardas, destinos de redirección seguros | BD, configuración, tablas de rutas; i18n cuando está cargado | Todos los puntos de entrada protegidos |
| Internacionalización | `backend/inc/i18n.php` | Normalización del idioma, carga de catálogos, respaldo y persistencia | BD, sesión, catálogos JSON | Páginas, manejadores, interfaz compartida |
| Dominio de videos | `backend/inc/videos.php` | Rutas seguras, validación con ffprobe, metadatos, miniaturas y su URL de la API, importación y sincronización de carpetas | BD, sesión, herramientas de FFmpeg | Manejadores de subida/sincronización, CLI, multimedia, componentes |
| Componentes de interfaz | `frontend/components/components.php` | Tarjetas de video escapadas, filas, avatares, pestañas de los paneles y paginación | helpers, íconos, videos, i18n | Páginas |
| Marco de la aplicación (chrome) | `frontend/components/chrome.php` | Barra superior, barra lateral, menú de la cuenta (paneles, idioma, cierre de sesión), búsqueda | sesión, helpers, íconos, i18n | `backend/index.php` |
| Íconos | `frontend/components/icons.php` | Funciones de íconos SVG en línea | Ninguna | Chrome, componentes, páginas |
| Helpers | `backend/inc/helpers.php` | Escapado, página actual, paginación acotada y formato de presentación; carga las tablas de rutas | Tablas de rutas; i18n de forma opcional | Todo el renderizado y las redirecciones |
| Pasarela multimedia | `backend/media.php` | Streaming de video autenticado por GET/HEAD y rangos de bytes simples | BD, sesión, helpers, videos | `/api/user/videos/{id}/stream` |
| Pasarela de miniaturas | `backend/thumb.php` | Entrega autenticada de JPEG por GET/HEAD | BD, sesión, helpers, videos | `/api/user/videos/{id}/thumbnail` |
| Catálogos de idiomas | `frontend/lang/*.json` | Textos traducidos de la interfaz, equivalentes en todos los idiomas | Parser JSON | `backend/inc/i18n.php` |
| Seeder | `backend/seed.php` | Inicialización vacía para producción; metadatos de demostración marcados explícitamente como no disponibles; respaldo verificado antes de reemplazar | Configuración, PDO SQLite | Instalador y CLI del administrador |
| CLI de mantenimiento | `backend/*-cli.php` | Asegurar el administrador heredado (e informar el cambio de dirección), restablecer una contraseña, sincronizar los archivos multimedia | Módulos compartidos de la aplicación | Instalador y operador del servidor |
| Política de Apache | `frontend/public/.htaccess` | Denegar las rutas privadas y los archivos ocultos (dotfiles), enrutar toda solicitud que no sea un archivo al controlador frontal | Rewrite de Apache | Sitio web desplegado |
| Presentación | `frontend/public/styles.css` | Diseños adaptables sin desborde horizontal, temas claro/oscuro del sistema, estilos de la referencia de la API | Nombres de clases renderizados | Todas las páginas |
| Navegación en el navegador | `frontend/public/ui.js` | Barra lateral móvil, fondo (backdrop), Escape y estado de accesibilidad del menú | DOM nativo y matchMedia | Layout |
| Arranque público | `frontend/public/index.php` | Pasar cada solicitud al backend privado | `backend/router.php` | Apache y el router de desarrollo |
| Plantillas | `frontend/views/*.php`, `frontend/views/pages/*.php` | Renderizar los datos de página preparados, el layout compartido, la página 404 y la referencia de la API | Componentes del frontend y helpers del backend | Controladores de páginas |
| Datos de navegación | `backend/inc/navigation.php` | Consultar notificaciones y sugerencias y resolver el contexto de navegación del usuario | BD, sesión, i18n | `frontend/components/chrome.php` |
| Despliegue en Debian | `deploy/install-update-reinstall-debian.sh` | Apache en 80/443 con TLS, registro y credenciales protegidos, detección del dominio, certificados preparados en staging, reversión de la configuración, migración y retiro de la instalación `mytube` | Debian, apt, Apache, PHP, SQLite, curl, OpenSSL | Operador root del servidor |
| Despliegue en Alpine | `deploy/install-update-reinstall-alpine.sh` | La misma transacción en sh/apk/PHP 8.4/OpenRC, con una configuración principal de Apache gestionada y sesiones privadas | Alpine main/community, Apache, PHP, SQLite, OpenSSL | Operador root del servidor |
| Ayudante de symlinks | `backend/tools/generate_symlink.py` | Enlazar de forma recursiva un árbol de origen externo dentro de `/var/www/myowntube-videos` | Python 3.13+, sistema de archivos | Operador del servidor antes de sincronizar |
| Router de desarrollo local | `deploy/local-dev-router.php` | Reproducir el comportamiento de `.htaccess` (denegación de rutas privadas, controlador frontal) en el servidor integrado de PHP | Servidor de desarrollo de PHP | Configuración local |

## 3. Índice de símbolos clave

| Símbolo | Archivo:línea | Qué hace |
|---|---|---|
| `fRoutePages` | `backend/inc/routes.php:6` | Asocia nombres de página con rutas limpias |
| `fRouteSectionDefaults` | `backend/inc/routes.php:29` | Pestaña por defecto de cada raíz de sección |
| `fRouteLegacyPages` | `backend/inc/routes.php:40` | Traduce los nombres anteriores de `?page=` a los nombres de página actuales |
| `fRouteDashboardTabs` | `backend/inc/routes.php:60` | Pestañas de los paneles de usuario y de administrador |
| `fRouteApi` | `backend/inc/routes.php:73` | Cada endpoint de `/api/` con sus métodos, manejador, acceso, campos y respuestas |
| `fRouteMatchPage` | `backend/inc/routes.php:480` | Resuelve una ruta de solicitud en un nombre de página y un parámetro `id` |
| `fRouteMatchApi` | `backend/inc/routes.php:493` | Resuelve una ruta de la API (con o sin barra final) en su ruta declarada |
| `fUrl` | `backend/inc/routes.php:514` | Construye la URL limpia de una página, completando `{id}` y la query string |
| `fApiUrl` | `backend/inc/routes.php:532` | Completa `{id}` en una ruta de la API |
| `fRouteLegacyDestination` | `backend/inc/routes.php:541` | Destino de una dirección anterior `/index.php?page=...` |
| `fRouterPlainResponse` | `backend/router.php:5` | Respuestas de texto plano 404/405/500 del despachador |
| `fOpenApiSpec` | `backend/inc/openapi.php:38` | Construye el documento OpenAPI 3.1 a partir de `fRouteApi()` |
| `fDb` | `backend/inc/db.php:19` | Abre y guarda en caché PDO SQLite con claves foráneas, WAL, timeout y modo sincrónico normal |
| `fQuery` | `backend/inc/db.php:45` | Ejecuta una sentencia preparada y oculta a los usuarios web los errores internos de SQL |
| `fE` | `backend/inc/helpers.php:5` | Escapa cualquier salida para un contexto de texto/atributo HTML |
| `fPaginate` | `backend/inc/helpers.php:18` | Cuenta las filas filtradas, ajusta la página a los límites y trae como máximo `cPageSize` entradas |
| `fCsrfRequire` | `backend/inc/session.php:69` | Rechaza una modificación cuyo token CSRF de sesión falta o no es válido |
| `fAuthEnsureSchema` | `backend/inc/session.php:115` | Crea las tablas de autenticación/configuración y aplica migraciones de compatibilidad idempotentes |
| `fAuthEnsureDefaultAdminEmail` | `backend/inc/session.php:175` | Cambia una sola vez `admin@mytube.home.arpa` por la dirección actual del administrador por defecto |
| `fAuthEnsureVideoAvailability` | `backend/inc/session.php:248` | Agrega la disponibilidad y oculta las filas heredadas sin rutas multimedia, sin borrar el estado de usuario |
| `fAuthMigrateUserTable` | `backend/inc/session.php:313` | Reconstruye las tablas heredadas de Me gusta, Ver más tarde o historial con propiedad por usuario |
| `fAuthLoginAllowed` | `backend/inc/session.php:555` | Comprueba y hace vencer la ventana de limitación de inicios de sesión por dirección |
| `fAuthCreateUser` | `backend/inc/session.php:604` | Valida los datos de la cuenta, genera el hash de la contraseña e inserta un usuario |
| `fAuthLogin` | `backend/inc/session.php:668` | Verifica las credenciales, registra los fallos, vuelve a generar el hash cuando hace falta y rota el ID de sesión |
| `fAuthChangePassword` | `backend/inc/session.php:722` | Reemplaza la contraseña propia, rota la sesión actual y revoca las demás |
| `fAuthAdminUpdateRole` | `backend/inc/session.php:772` | Cambia un rol sin permitir quitar el último administrador |
| `fAuthAdminSetPassword` | `backend/inc/session.php:799` | Un administrador establece la contraseña de otra cuenta y revoca sus sesiones |
| `fAuthSafeDestination` | `backend/inc/session.php:860` | Acepta como destinos de redirección solo rutas de páginas internas (nunca `/api/`, esquemas ni hosts) |
| `fCurrentUser` | `backend/inc/session.php:888` | Carga la cuenta y rechaza una versión de sesión obsoleta |
| `fRequireLogin` | `backend/inc/session.php:953` | Redirige las solicitudes anónimas al inicio de sesión |
| `fRequireAdmin` | `backend/inc/session.php:960` | Exige tanto autenticación como rol de administrador |
| `fCurrentLocale` | `backend/inc/i18n.php:132` | Resuelve el idioma del usuario, de la sesión y del sitio, en ese orden |
| `fT` | `backend/inc/i18n.php:162` | Obtiene un texto traducido con respaldo en inglés y reemplazo de parámetros |
| `fVideoThumbnailUrl` | `backend/inc/videos.php:118` | URL de la API de la miniatura de un video cuando el JPEG existe |
| `fVideoPathFromFilePath` | `backend/inc/videos.php:126` | Resuelve una ruta del catálogo solo dentro del directorio de videos configurado |
| `fVideoSaveJson` | `backend/inc/videos.php:180` | Escribe de forma atómica un sidecar JSON en UTF-8 mediante un archivo temporal y un renombrado |
| `fVideoValidateFile` | `backend/inc/videos.php:356` | Impone el tamaño de archivo y exige que ffprobe encuentre un flujo de video real |
| `fVideoImportJson` | `backend/inc/videos.php:450` | Normaliza los metadatos del sidecar e inserta o actualiza la fila correspondiente del catálogo |
| `fVideosSyncFolder` | `backend/inc/videos.php:542` | Valida archivos, importa sidecars/miniaturas y concilia la disponibilidad en el catálogo |
| `fChannelById` | `backend/inc/videos.php:644` | Guarda en caché los registros de canales usados al renderizar videos |
| `fIdSet` | `backend/inc/videos.php:651` | Carga los ID de los videos que le gustan al usuario actual o que guardó |
| `fNavigationData` | `backend/inc/navigation.php:3` | Prepara los datos de navegación sin renderizar HTML |
| `fVideoCard` | `frontend/components/components.php:54` | Renderiza una tarjeta de catálogo reutilizable y escapada |
| `fDashboardTabs` | `frontend/components/components.php:151` | Renderiza la barra de pestañas de un panel con la pestaña activa |
| `fPaginationRender` | `frontend/components/components.php:165` | Renderiza los enlaces de anterior/siguiente conservando los filtros |
| `fDetectDomain` | `deploy/install-update-reinstall-debian.sh:45` | `MYOWNTUBE_DOMAIN`, si no el `ServerName` configurado, si no el valor por defecto; el valor por defecto viejo pasa al nuevo |
| `fAcquireLock` | `deploy/install-update-reinstall-debian.sh:94` | Impide procesos de instalación superpuestos |
| `fActivateConfigFile` | `deploy/install-update-reinstall-debian.sh:106` | Reemplaza solo la configuración gestionada que cambió |
| `fPrepareLogs` | `deploy/install-update-reinstall-debian.sh:118` | Se hace cargo de `/root/app-web-*` y abre `/root/webapp-*` con modo 600 |
| `fBackupConfigFile` | `deploy/install-update-reinstall-debian.sh:131` | Anota en el journal un archivo o symlink antes de un cambio de despliegue |
| `fRestoreConfigFiles` | `deploy/install-update-reinstall-debian.sh:155` | Restaura la configuración anotada en el journal después de un fallo |
| `fValidateApacheIsolation` | `deploy/install-update-reinstall-debian.sh:258` | Se niega a cambiar módulos globales de Apache cuando hay sitios ajenos habilitados |
| `fSaveCurrentData` | `deploy/install-update-reinstall-debian.sh:330` | Respaldo verificado de SQLite (de la instalación actual o de la anterior) y migración de los videos |
| `fCreateCertificate` | `deploy/install-update-reinstall-debian.sh:503` | Reutiliza un certificado válido (también el anterior) o prepara en staging y valida uno nuevo |
| `fConfigureApache` | `deploy/install-update-reinstall-debian.sh:554` | Módulos, `Listen 80`/`Listen 443`, los dos VirtualHosts de la plantilla de Apache |
| `fRetireLegacyApacheSite` | `deploy/install-update-reinstall-debian.sh:671` | Deshabilita y anota en el journal el sitio `000-mytube` |
| `fVerifyBackend` | `deploy/install-update-reinstall-debian.sh:721` | Verifica `https://<dominio>/auth/login/` en el puerto 443 con el certificado instalado |
| `fRetireLegacyInstallation` | `deploy/install-update-reinstall-debian.sh:730` | Mueve los archivos de la instalación `mytube` a `previous-install-*` |
| `fMain` | `deploy/install-update-reinstall-debian.sh:833` | Ordena validación, respaldo, despliegue, configuración, seed/migración, reinicio y retiro |
| `fConfigureAlpineMain` | `deploy/install-update-reinstall-alpine.sh:600` | Construye la configuración principal de Apache de Alpine sin includes duplicados |
| `fRetireLegacyApacheFiles` | `deploy/install-update-reinstall-alpine.sh:747` | Anota en el journal y elimina `mytube-site.conf` y `mytube-ports.conf` |
| `fMain` | `deploy/install-update-reinstall-alpine.sh:930` | Ejecuta la transacción de instalación/actualización de Alpine |
| `fMain` | `backend/tools/generate_symlink.py:93` | Valida la entrada de la CLI, crea los enlaces y devuelve un código de salida significativo |

## 4. Flujos principales

### Solicitud de página

`GET /history/` → `.htaccess` de Apache (no es un archivo) → `frontend/public/index.php` → `backend/router.php` (no es `/api/`) → `backend/index.php`: cabeceras de seguridad → redirección heredada de `?page=`, redirección por barra final faltante, redirección al valor por defecto de la sección → disponibilidad de la base de datos → `fRouteMatchPage()` → `fAuthEnsureSchema()` e idioma → comprobaciones de acceso (pública, con sesión iniciada, administrador) → `backend/pages/<página>.php` prepara los datos → `frontend/views/pages/<página>.php` renderiza → `frontend/views/layout.php` lo envuelve. Una ruta desconocida renderiza `frontend/views/page-not-found.php` con estado 404.

### Solicitud a la API

`POST /api/user/likes/toggle` → `.htaccess` → `frontend/public/index.php` → `backend/router.php` → `fRouteMatchApi()` → comprobación del método (405 con `Allow` si no corresponde) → parámetros de ruta copiados en `$_GET` → `require backend/actions/toggle-like.php` → guardas de inicio de sesión y CSRF → modificación → aviso → 303 a la página de origen.

### Inicio de sesión

`GET /playlists/` anónimo → 302 a `/auth/login/?next=%2Fplaylists%2F` → formulario → `POST /api/auth/login` → `fCsrfRequire()` → `fAuthLoginAllowed()` → `password_verify()` → contador de fallos o `fAuthStartSession()` (rotación del ID de sesión y `session_version` guardado) → `fAuthSafeDestination(next)` → 303 a `/playlists/`.

### Recuperación de contraseña

`/auth/login/` → **¿Olvidaste tu contraseña?** → `/auth/recover/` (solo informativa; la aplicación no envía correos) → un administrador abre `/admin/dashboard/users/` → `POST /api/admin/users/password` → `fAuthAdminSetPassword()` → hash Argon2id y `session_version + 1` → todas las sesiones de esa cuenta dejan de ser válidas. Sin ningún administrador: `reset-admin-password-cli.php` desde el servidor.

### Búsqueda e historial privado

Formulario de la barra superior → `POST /api/user/searches` → guardas de inicio de sesión/CSRF → consulta acotada → upsert en el historial del usuario → 303 a `/search/?q=…` → consulta de resultados de solo lectura. Los GET de resultados y de sugerencias nunca modifican el historial.

### Reemplazo de contraseña

`/user/dashboard/account/` → `POST /api/user/password` → verificación de la contraseña actual → hash Argon2id → `session_version + 1` → la sesión actual se rota y sigue funcionando; todas las demás sesiones se revocan.

### Subida

`/upload/` → `POST /api/user/videos/upload` (multipart, `LimitRequestBody 0` de Apache solo acá) → guardas de inicio de sesión y CSRF → comprobaciones de extensión/tamaño → destino sin colisiones → `move_uploaded_file()` → `fVideoValidateFile()`/ffprobe → `fVideoCreateMetadata()` → sidecar atómico → `fVideoImportJson()` y miniatura → 303 a `/watch/{id}/`. Cualquier fallo elimina el video, el sidecar y la miniatura creados en ese intento.

### Sincronización de carpetas

`POST /api/admin/videos/sync` o `sync-videos-cli.php` → `fVideosSyncFolder()` → comprobación previa de `ffprobe` → instantánea de los ID existentes respaldados por archivos → enumeración de las extensiones permitidas → validación de flujos reales → generación de los sidecars faltantes → `fVideoImportJson()` → generación/actualización de miniaturas → registro de los fallos por archivo → marcado transaccional como no disponibles de los ID de la instantánea que no se importaron. Las filas y el estado de usuario nunca se borran.

### Reproducción y progreso

`/watch/{id}/` → `<video src="/api/user/videos/{id}/stream">` → guarda de inicio de sesión y comprobación de ruta segura → rango único opcional → liberación del bloqueo de sesión → bloques de 1 MiB. Después del evento `playing`, JavaScript envía el porcentaje con el token CSRF a `/api/user/history/progress`; una transacción inserta el historial y cuenta la vista solo en la primera inserción; si no, actualiza el progreso. La respuesta es JSON.

### Migración en el arranque

Cualquier punto de entrada de la aplicación o de mantenimiento → `fAuthEnsureSchema()` → tablas faltantes → cambio de la dirección del administrador por defecto → configuración → idioma del usuario y versión de sesión → disponibilidad de los videos → handle del canal de subidas → búsqueda de administradores → tablas privadas heredadas reconstruidas con propiedad por usuario → índices → marcas de migración en `schema_migrations`.

### Instalación, actualización y migración (Debian y Alpine)

`fMain()` → comprobación de root y bloqueo exclusivo → `fPrepareLogs()` (se hace cargo de `/root/app-web-*`) → comprobaciones del entorno y del aislamiento → paquetes y directorios → descarga del archivo de `main` → detención de Apache → `fSaveCurrentData()` (respaldo verificado de `myowntube.db`, o del `mytube.db` anterior; videos movidos desde `/var/www/mytube-videos`, dejando un enlace en su lugar) → despliegue en staging hacia `/var/www/myowntube` → `fConfigureApache()` (ini de PHP, certificado, módulos, puertos 80/443, VirtualHosts, retiro del sitio/ini anteriores, configtest) → seed o `secure-admin-cli.php` → habilitación y reinicio de Apache → `fVerifyBackend()` → guardado de las credenciales → commit → `fRetireLegacyInstallation()`. Un fallo antes del commit restaura la configuración anotada en el journal y el árbol web anterior, y reinicia Apache si estaba en ejecución.

### Catálogo y colecciones paginados

Controlador de página → conteo filtrado y consulta ordenada de filas → `fPaginate()` ajusta la página pedida a los límites y aplica el límite de 24 filas → renderizado de tarjetas/filas → `fPaginationRender()` construye URL limpias escapadas que conservan el contexto de categoría, búsqueda o lista. Los comentarios usan `comments_page`; las demás listas usan `p`. El orden incluye un ID estable para evitar duplicados entre páginas.

## 5. Puntos de entrada, rutas y comandos

### Páginas

| Ruta/URL/comando | Manejador | Archivo |
|---|---|---|
| `GET /` (`?cat=`, `?p=`) | Catálogo por categoría | `backend/pages/home.php` |
| `GET /trending/` (`?tab=`) | Lista de tendencias filtrada por categoría | `backend/pages/trending.php` |
| `GET /search/?q=…` | Resultados de búsqueda de solo lectura | `backend/pages/search.php` |
| `GET /watch/{id}/` (`?list=`, `?comment_sort=`, `?comments_page=`) | Reproductor, acciones y comentarios | `backend/pages/watch.php` |
| `GET /history/` | Historial de reproducción del usuario actual | `backend/pages/history.php` |
| `GET /liked/` | Me gusta del usuario actual | `backend/pages/liked.php` |
| `GET /watch-later/` | Lista de Ver más tarde del usuario actual | `backend/pages/watchlater.php` |
| `GET /playlists/` | Listas del usuario actual | `backend/pages/playlists.php` |
| `GET /playlists/{id}/` | Detalle de una lista propia | `backend/pages/playlist.php` |
| `GET /upload/` | Formulario de subida y carpeta de videos | `backend/pages/upload.php` |
| `GET /auth/login/` (`?next=`) | Formulario público de inicio de sesión | `backend/pages/login.php` |
| `GET /auth/register/` | Formulario de registro cuando está habilitado | `backend/pages/register.php` |
| `GET /auth/recover/` | Información pública de recuperación de contraseña | `backend/pages/recover.php` |
| `GET /user/dashboard/` | 302 a `/user/dashboard/profile/` | `backend/index.php` |
| `GET /user/dashboard/profile/` | Pestaña Perfil | `backend/pages/profile.php` |
| `GET /user/dashboard/account/` | Pestaña Cuenta: idioma, contraseña, historial de búsquedas | `backend/pages/account.php` |
| `GET /admin/dashboard/` | 302 a `/admin/dashboard/users/` | `backend/index.php` |
| `GET /admin/dashboard/users/` | Pestaña Usuarios | `backend/pages/users.php` |
| `GET /admin/dashboard/settings/` | Pestaña de configuración del sitio | `backend/pages/site-settings.php` |
| `GET /index.php?page=…` | 301 a la dirección actual | `backend/index.php` |

### API

Cada endpoint de abajo está declarado en `fRouteApi()` y documentado en `/api/doc/` (HTML) y en `/api/doc/openapi.json`. Los endpoints de formularios aceptan solo POST, exigen el token CSRF y responden 303; todos, salvo el inicio de sesión y el registro, exigen un usuario con sesión iniciada.

| Ruta/URL/comando | Manejador | Archivo |
|---|---|---|
| `POST /api/auth/login` | Verificar las credenciales e iniciar la sesión | `backend/actions/login.php` |
| `POST /api/auth/register` | Crear un usuario estándar si el registro está habilitado | `backend/actions/register.php` |
| `POST /api/auth/logout` | Destruir y rotar la sesión | `backend/actions/logout.php` |
| `POST /api/user/profile` | Cambiar el nombre visible | `backend/actions/update-profile.php` |
| `POST /api/user/password` | Verificar y reemplazar la contraseña actual | `backend/actions/change-password.php` |
| `POST /api/user/language` | Guardar el idioma del usuario | `backend/actions/set-language.php` |
| `POST /api/user/searches` | Registrar una búsqueda privada y abrir los resultados | `backend/actions/record-search.php` |
| `POST /api/user/searches/clear` | Borrar las sugerencias de búsqueda privadas | `backend/actions/clear-search-history.php` |
| `POST /api/user/likes/toggle` | Agregar/quitar un Me gusta | `backend/actions/toggle-like.php` |
| `POST /api/user/watch-later/toggle` | Agregar/quitar un elemento de Ver más tarde | `backend/actions/toggle-watchlater.php` |
| `POST /api/user/history/progress` | Progreso en JSON y transacción de primera vista | `backend/actions/save-progress.php` |
| `POST /api/user/history/remove` | Borrar un elemento del historial | `backend/actions/remove-history.php` |
| `POST /api/user/history/clear` | Borrar todo el historial | `backend/actions/clear-history.php` |
| `POST /api/user/comments` | Publicar un comentario | `backend/actions/post-comment.php` |
| `POST /api/user/playlists/create` | Crear una lista | `backend/actions/create-playlist.php` |
| `POST /api/user/playlists/update` | Editar el nombre y la descripción de una lista | `backend/actions/update-playlist.php` |
| `POST /api/user/playlists/delete` | Eliminar una lista | `backend/actions/delete-playlist.php` |
| `POST /api/user/playlists/items` | Agregar/quitar/reposicionar un elemento | `backend/actions/update-playlist-item.php` |
| `POST /api/user/videos/upload` | Validar, guardar e importar una subida | `backend/actions/upload-video.php` |
| `GET\|HEAD /api/user/videos/{id}/stream` (`?download=1`) | Multimedia autenticada y rangos de bytes | `backend/media.php` |
| `GET\|HEAD /api/user/videos/{id}/thumbnail` | Miniatura autenticada | `backend/thumb.php` |
| `POST /api/admin/videos/sync` | Sincronización de carpetas | `backend/actions/sync-videos.php` |
| `POST /api/admin/settings` | Idioma del sitio e interruptor de registro | `backend/actions/save-settings.php` |
| `POST /api/admin/users/create` | Crear una cuenta | `backend/actions/create-user.php` |
| `POST /api/admin/users/role` | Cambiar un rol | `backend/actions/update-user-role.php` |
| `POST /api/admin/users/password` | Establecer la contraseña de otra cuenta | `backend/actions/set-user-password.php` |
| `POST /api/admin/users/delete` | Eliminar una cuenta | `backend/actions/delete-user.php` |
| `GET /api/doc/`, `GET /api/doc/openapi.json` | Referencia de la API y documento OpenAPI | `backend/api-doc.php` |

### Comandos de CLI y de despliegue

| Ruta/URL/comando | Manejador | Archivo |
|---|---|---|
| `php backend/seed.php [--force] [--demo]` | Crear una base de datos de forma atómica; opcionalmente, respaldar y reemplazar | `backend/seed.php` |
| `php backend/secure-admin-cli.php` | Rotar las credenciales por defecto históricas, cambiar la dirección por defecto vieja, ejecutar las migraciones del esquema | `backend/secure-admin-cli.php` |
| `php backend/reset-admin-password-cli.php [email]` | Restablecer/promover una cuenta, revocar sesiones, borrar las limitaciones de intentos | `backend/reset-admin-password-cli.php` |
| `php backend/sync-videos-cli.php` | Sincronizar los archivos multimedia sin navegador | `backend/sync-videos-cli.php` |
| `deploy/install-update-reinstall-debian.sh` | Instalar, actualizar o reinstalar en Debian | La misma ruta |
| `deploy/install-update-reinstall-alpine.sh` | Instalar, actualizar o reinstalar en Alpine | La misma ruta |
| `python3 backend/tools/generate_symlink.py SOURCE` | Enlazar un árbol de archivos externo dentro del directorio multimedia | `backend/tools/generate_symlink.py` |
| `php -S 127.0.0.1:8080 -t frontend/public deploy/local-dev-router.php` | Servidor local con el comportamiento de `.htaccess` | `deploy/local-dev-router.php` |

Variables de entorno del instalador: `MYOWNTUBE_DOMAIN`, `MYOWNTUBE_ADMIN_PASSWORD` y `MYOWNTUBE_INSTALLER_LIBRARY_ONLY=1` (Alpine; carga las funciones del instalador sin ejecutar la instalación).

## 6. Análisis de impacto

| Componente crítico/compartido | Los cambios afectan a |
|---|---|
| Tablas de páginas o de la API de `backend/inc/routes.php` | Todos los enlaces y formularios (`fUrl()`), las redirecciones después del inicio de sesión, los paneles, el despachador de la API, `/api/doc/`, las ubicaciones de `LimitRequestBody` en los dos instaladores y los dos conjuntos de documentación |
| Rutas y `cSessionName` de `backend/config.php` | Conexión a la BD, sidecars, pasarelas multimedia, seeder, CLI, supuestos del instalador, la cookie de sesión y el esquema de seguridad de OpenAPI |
| `backend/inc/db.php` | Todas las consultas y transacciones; cambiar los pragmas afecta la concurrencia y el comportamiento de los respaldos |
| `backend/inc/session.php` y `users.session_version` | Todas las páginas y manejadores, los formularios con CSRF, la revocación, los roles, las migraciones, los datos propios de cada usuario, la seguridad de las redirecciones |
| `fAuthSafeDestination()` | Todas las redirecciones `next`/`back`; ampliarla puede crear redirecciones abiertas |
| `backend/inc/i18n.php` o una clave de idioma | Marco de la aplicación, páginas, avisos de los manejadores, los cuatro catálogos JSON; las claves faltantes recurren al inglés |
| Extensiones permitidas/reglas de rutas de `backend/inc/videos.php` | Subida, sincronización de carpetas, búsqueda de archivos multimedia, miniaturas, sidecars, registros ya importados, la descripción del campo de subida en `fRouteApi()` |
| Semántica de `videos.file_path`/`is_available` | Visibilidad en el catálogo, reproducción, miniaturas, sincronización, descarga, validación del progreso, conservación del estado de usuario |
| Lógica de rangos de `backend/media.php` | Desplazamiento en el reproductor del navegador, descargas, concurrencia de sesión en solicitudes largas, cabeceras de contenido |
| `backend/actions/save-progress.php` | Orden del historial, reanudación, conteo de vistas, posición en tendencias |
| Claves de las tablas propias de cada usuario | Me gusta, Ver más tarde, historial, listas, borrado en cascada de cuentas, migración heredada |
| `frontend/public/.htaccess` y `deploy/local-dev-router.php` | Qué archivos son públicos y si las URL limpias llegan al controlador frontal |
| Nombres de clases de `frontend/public/styles.css` | Todos los renderizadores de páginas, del marco de la aplicación, de los componentes y de la referencia de la API |
| Rutas, nombres y propietarios del instalador | Actualizaciones, la migración y el retiro de `mytube`, la recuperación de respaldos, el acceso de Apache, los límites de escritura, el destino del ayudante de symlinks, los dos manuales |
| Puertos y VirtualHosts de Apache | Exposición directa en 80/443, redirección de HTTP a HTTPS, dirección del cliente que ven la limitación de intentos y los registros |

## 7. Puntos de extensión

### Agregar una página

1. Agregá el nombre de la página y su ruta limpia a `fRoutePages()` (y a `fRouteDashboardTabs()` si es una pestaña de un panel).
2. Agregala a `$aPublicPages` o a `$aAdminPages` en `backend/index.php` cuando no sea una página normal con sesión iniciada.
3. Creá `backend/pages/<nombre>.php` para las consultas y la lógica sensible al acceso, y `frontend/views/pages/<nombre>.php` para el HTML escapado (en una pestaña de panel, llamá primero a `fDashboardTabs()`).
4. Enlazala con `fUrl('<nombre>')`, agregá la navegación en `frontend/components/chrome.php` y los textos en los cuatro catálogos `frontend/lang/*.json`.
5. Actualizá acá los mapas de rutas y de módulos, y también los manuales.

### Agregar un endpoint de la API

1. Creá `backend/actions/<nombre>.php` y cargá la configuración, la BD, la sesión, los helpers y la i18n.
2. Llamá a `fRequireLogin()` o a `fRequireAdmin()` y a `fCsrfRequire()` antes de leer los datos de la modificación.
3. Validá tipos, longitudes, propiedad y registros relacionados. Usá marcadores de posición de `fQuery()` y una transacción para los invariantes de varios pasos.
4. Agregá una entrada a `fRouteApi()` bajo `/api/auth/`, `/api/user/` o `/api/admin/` con métodos, manejador, acceso, campos y respuestas; `/api/doc/` se actualiza solo.
5. Apuntá el `action` del formulario a la ruta nueva, agregá `fCsrfField()`, guardá un aviso traducido y redirigí con 303.
6. Las páginas GET tienen que seguir siendo de solo lectura: todo cambio de estado pasa por un endpoint POST bajo `/api/`.

### Agregar un idioma

1. Agregá el código de idioma normalizado y su nombre visible en `backend/config.php`.
2. Copiá el conjunto completo de claves en inglés a `frontend/lang/<idioma>.json` y traducí los valores sin cambiar los marcadores de posición.
3. Agregá las variantes de README, MANUAL y CODE y sus líneas de idiomas.

### Agregar una colección propia del usuario

Creá una tabla cuya clave primaria empiece por `user_id`, referenciá a los usuarios con la regla `ON DELETE` que corresponda, calificá cada consulta con `fUserId()` y agregá un índice para el orden de su lista. Si las instalaciones anteriores tenían una tabla global, agregá en `fAuthEnsureSchema()` una migración transaccional que conserve los datos.

### Agregar metadatos de video

Ampliá `fVideoCreateMetadata()` y `fVideoImportJson()` a la vez. Decidí si SQLite necesita el valor para hacer consultas; si no, mantenelo en el sidecar. Conservá las escrituras atómicas de sidecars, las reglas de rutas seguras, la reversión de subidas y la compatibilidad con los sidecars que no incluyen el campo nuevo.

### Agregar una CLI de mantenimiento

Rechazá las SAPI que no sean CLI antes de cargar lógica que modifique datos, devolvé un código distinto de cero si falla, reutilizá los helpers de la aplicación y nunca expongas secretos a través de una ruta web. Mantené el sufijo `*-cli.php` para que Apache y el router de desarrollo la denieguen.

## 8. Invariantes de datos y de seguridad

- Las filas de la base de datos nunca apuntan fuera de `cVideosPath`; solo se resuelven los nombres base de `videos.file_path`.
- Un archivo importado reproducible necesita tanto el archivo multimedia como el sidecar JSON. Una miniatura necesita además el archivo JPEG.
- Las filas del catálogo respaldadas por archivos que no aparecen en una sincronización exitosa pasan a no estar disponibles, no se borran; una reimportación exitosa las reactiva.
- Las extensiones de video son una lista de permitidos inicial, no una prueba del contenido. `ffprobe` tiene que encontrar un códec de video y dimensiones positivas.
- Los sidecars y las bases de datos nuevas creadas por el seeder se activan con un renombrado, no se escriben en el lugar.
- Las lecturas y escrituras de datos propios de cada usuario incluyen `user_id`; las acciones sobre listas además confirman que la lista le pertenece al usuario.
- El último administrador no puede ser degradado ni eliminado, el usuario con sesión iniciada no puede eliminar su propia cuenta, y los administradores cambian su propia contraseña solo desde la pestaña de la cuenta.
- El registro público está desactivado por defecto. Las contraseñas se guardan como hash y tienen que tener entre 12 y 4096 caracteres.
- Las sesiones autenticadas tienen que coincidir con `users.session_version`; cambiar, establecer o restablecer una contraseña revoca las versiones obsoletas.
- Las modificaciones usan POST con un token CSRF de sesión; todas las modificaciones viven bajo `/api/`. Los GET de resultados y de sugerencias nunca registran el historial de búsquedas. Los destinos de redirección son solo rutas de páginas internas.
- Los métodos no admitidos en rutas de la API responden 405; las rutas de API desconocidas responden 404 y las páginas desconocidas renderizan una página 404.
- Las sesiones de multimedia y de miniaturas se cierran antes de una salida larga de archivo para no serializar las solicitudes de un mismo usuario.
- Las respuestas web no revelan excepciones de SQL ni detalles del sistema de archivos; los errores completos van a los registros del servidor.
- La base de datos de ejecución y los archivos de video no forman parte del árbol de código y se tienen que respaldar juntos.
- Los instaladores cambian módulos globales de Apache solo en una instancia sin sitios ajenos habilitados.

## 9. Desarrollo local

Ejecutá estos comandos desde la raíz del proyecto. PHP necesita PDO SQLite, mbstring y Argon2id; `ffmpeg` y `ffprobe` tienen que estar en el `PATH`.

```bash
MYOWNTUBE_ADMIN_PASSWORD='replace-with-a-long-unique-password' php backend/seed.php
php -S 127.0.0.1:8080 -t frontend/public deploy/local-dev-router.php
```

Abrí `http://127.0.0.1:8080/` e iniciá sesión como `admin@myowntube.home.arpa`. El router reproduce `.htaccess`: sirve `styles.css` y `ui.js`, deniega la base de datos, los módulos internos, los archivos ocultos, la configuración y los puntos de entrada de CLI, y envía cualquier otra dirección al controlador frontal. `php backend/seed.php --demo` agrega metadatos de ejemplo que siguen sin estar disponibles hasta que se importan los archivos multimedia correspondientes. Detené el tráfico web antes de usar `--force` para reemplazar una base de datos existente.
