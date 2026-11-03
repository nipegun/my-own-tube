# Guía del código de MyOwnTube

Este CODE también está disponible en:\
[en-GB](CODE.en-GB.md), [en-US](CODE.md), [es-AR](CODE.es-AR.md), es-ES

Este documento es un mapa quirúrgico del código actual. Las rutas son relativas a la raíz del repositorio y los números de línea de los símbolos se refieren al código fuente actual.

## Índice

1. [Arquitectura y decisiones de diseño](#1-arquitectura-y-decisiones-de-diseño)
2. [Mapa de módulos](#2-mapa-de-módulos)
3. [Índice de símbolos clave](#3-índice-de-símbolos-clave)
4. [Flujos principales](#4-flujos-principales)
5. [Puntos de entrada, rutas y comandos](#5-puntos-de-entrada-rutas-y-comandos)
6. [Análisis de impacto](#6-análisis-de-impacto)
7. [Puntos de extensión](#7-puntos-de-extensión)
8. [Invariantes de datos y seguridad](#8-invariantes-de-datos-y-seguridad)
9. [Desarrollo local](#9-desarrollo-local)

## 1. Arquitectura y decisiones de diseño

MyOwnTube es una aplicación web PHP renderizada en el servidor, con carpetas separadas para la presentación y para la aplicación. Solo `frontend/public/` es la raíz de documentos HTTP. Su `.htaccess` envía toda dirección que no sea un archivo público real (`styles.css`, `ui.js`) a `frontend/public/index.php`, que pasa la petición a `backend/router.php`. Los componentes HTML/SVG, las plantillas y las traducciones viven en `frontend/`; el enrutamiento, los controladores, los manejadores de formularios y la lógica compartida viven en `backend/`. Así el despliegue con Apache/PHP sigue siendo sencillo y ningún código fuente privado ni dato de ejecución llega nunca a ser un archivo público.

**Una sola tabla de rutas.** `backend/inc/routes.php` es la única fuente de verdad de todas las direcciones públicas. `fRoutePages()` asigna los nombres de página a rutas limpias (`/`, `/watch/{id}/`, `/user/dashboard/profile/`...), `fRouteSectionDefaults()` envía las raíces de sección (`/user/dashboard/`, `/admin/dashboard/`, `/auth/`) a su pestaña por defecto y `fRouteApi()` enumera cada endpoint de la API con sus métodos, manejador, nivel de acceso, campos y respuestas. El controlador de páginas, `fUrl()`, el despachador de la API y el documento OpenAPI de `/api/doc/` leen todos estas tablas, así que una ruta nueva se declara una sola vez. Las antiguas direcciones `/index.php?page=...` responden con un 301 hacia la ruta nueva, y las direcciones sin la barra final se canonicalizan con un 301.

**Páginas y paneles.** Las páginas siguen las reglas de endpoints: autenticación en `/auth/login/`, `/auth/register/` y `/auth/recover/`; un panel de usuario en `/user/dashboard/` con las pestañas `profile` y `account`; un panel de administrador en `/admin/dashboard/` con las pestañas `users` y `settings`. Cada pestaña tiene su propia URL y la barra de pestañas la renderiza `fDashboardTabs()` a partir de `fRouteDashboardTabs()`. Una petición anónima a una página protegida se redirige a `/auth/login/?next=<ruta solicitada>` y vuelve allí después de iniciar sesión.

**API.** Todo lo que se comporta como una API vive bajo `/api/`: los manejadores de formularios (`/api/auth/*`, `/api/user/*`, `/api/admin/*`), el endpoint JSON de progreso y el flujo de vídeo y la miniatura autenticados. Los manejadores son los archivos de `backend/actions/` más `backend/media.php` y `backend/thumb.php`; el enrutador asigna la ruta limpia de la API al archivo e inyecta los parámetros de ruta como `{id}` en `$_GET`, de modo que los manejadores conservan su estructura original. El enrutador responde 404 a las rutas de API desconocidas y 405 con `Allow` a los métodos no admitidos. Los endpoints de formulario mantienen el patrón post/redirect/get: validan el token CSRF de la sesión, guardan un aviso traducido y responden 303.

SQLite es el único almacén de metadatos. Las sentencias preparadas de PDO están centralizadas en `backend/inc/db.php`; el modo WAL permite lecturas normales mientras terminan las escrituras breves. Las claves foráneas están activadas en todas las conexiones de la aplicación. El esquema lo crea `seed.php`, mientras que las migraciones de compatibilidad idempotentes de `backend/inc/session.php` actualizan las instalaciones antiguas durante el arranque (incluido el cambio de la dirección del administrador por defecto de `admin@mytube.home.arpa` a `admin@myowntube.home.arpa`).

Los bytes de los vídeos no se guardan en SQLite. Cada vídeo importado tiene un archivo multimedia original, un JSON auxiliar adyacente y, normalmente, una miniatura JPEG generada, dentro del directorio de vídeos. SQLite guarda el catálogo y el estado de los usuarios, además de un `file_path` relativo seguro y un indicador `is_available`. La sincronización de carpeta oculta las filas cuyos archivos han desaparecido conservando su estado de usuario, y las reactiva si el archivo multimedia vuelve.

La autenticación usa sesiones PHP (cookie `MYOWNTUBESESSID`, constante `cSessionName`). Las contraseñas usan Argon2id y los hashes antiguos se actualizan tras un inicio de sesión correcto. Cada sesión lleva el `session_version` de la cuenta, guardado en la base de datos; los cambios de contraseña, las contraseñas establecidas por un administrador y los restablecimientos por CLI lo incrementan, de modo que las sesiones obsoletas fallan en su siguiente comprobación. La aplicación no envía correos, así que la recuperación de contraseña consiste en que un administrador establezca una contraseña nueva desde `/admin/dashboard/users/` o en usar la CLI de restablecimiento en el servidor; `/auth/recover/` explica ambas opciones.

La internacionalización usa catálogos JSON planos de clave/valor. El inglés es el idioma de reserva; el idioma del usuario se guarda en la cuenta, mientras que un idioma por defecto del sitio se aplica a los visitantes y a las cuentas nuevas. Los cuatro catálogos deben mantener claves idénticas. La referencia de la API en `/api/doc/` es documentación técnica y solo está escrita en inglés.

El instalador es también el mecanismo de actualización. Apache escucha directamente en los puertos 80 (redirección permanente a HTTPS) y 443 (TLS), sin HAProxy local ni protocolo PROXY. El instalador descarga por HTTPS, detiene Apache antes de la copia de seguridad de SQLite, valida la copia, despliega a través de un directorio de preparación, mantiene los archivos multimedia fuera del árbol de código, valida Apache antes de reiniciarlo y conserva copias de reversión. Como cambia el MPM global de Apache y el módulo PHP, rechaza los sitios habilitados ajenos y, por tanto, está pensado para una instancia de Apache dedicada. El código es inmutable para el usuario web; solo se puede escribir en la base de datos, los archivos multimedia y los registros. Apache sirve el código desde `/var/www/myowntube` (la estructura `/opt/<usuario>` de las reglas del proyecto solo se aplica a los servicios que no son Apache). Una instalación hecha antes del cambio de nombre (`/var/www/mytube`, `mytube.db`, `/root/app-web-*`) se migra en la primera ejecución y sus archivos se retiran al directorio de copias de seguridad.

```text
frontend/
  public/        # index.php, styles.css, ui.js, .htaccess
  views/         # plantilla base, plantillas de páginas, plantilla de la referencia de la API
  components/    # renderizado HTML y SVG
  lang/          # textos traducidos de la interfaz
backend/
  actions/       # manejadores de formularios y JSON de la API
  pages/         # controladores de páginas y consultas
  inc/           # lógica compartida: rutas, OpenAPI, BD, sesión, i18n, vídeos
  tools/         # utilidades de importación multimedia
  db/            # base de datos privada de ejecución (myowntube.db)
  videos/        # directorio multimedia privado o enlace
doc/
deploy/
```

## 2. Mapa de módulos

| Módulo | Ruta | Responsabilidad | Depende de | Usado por |
|---|---|---|---|---|
| Configuración | `backend/config.php` | Identidad de la aplicación, nombre de la cookie de sesión, cuatro idiomas ordenados, tamaño de página, rutas, límites de seguridad y valores por defecto del administrador | Entorno de ejecución de PHP | Todos los puntos de entrada web y CLI |
| Tablas de rutas | `backend/inc/routes.php` | Rutas de páginas, valores por defecto de sección, nombres de página antiguos, pestañas de los paneles, tabla de la API, resolución de rutas, `fUrl()`/`fApiUrl()` | Ninguna | Enrutador, controlador de páginas, utilidades, OpenAPI, vistas |
| Despachador HTTP | `backend/router.php` | Despacho de `/api/` con gestión de 404/405 y parámetros de ruta; todo lo demás va al controlador de páginas | Tablas de rutas, manejadores | Arranque público |
| Controlador frontal de páginas | `backend/index.php` | Cabeceras de seguridad, redirecciones antiguas y de barra final, valores por defecto de sección, disponibilidad del esquema, control de acceso, despacho de páginas, página 404, plantilla base | Todos los módulos de `inc`, `pages` | Peticiones de páginas del navegador |
| Páginas | `backend/pages/*.php` | Consultar y renderizar una pantalla de la aplicación (incluidas `account`, `site-settings`, `recover`) | BD, sesión, utilidades, componentes, i18n | `backend/index.php` |
| Manejadores de la API | `backend/actions/*.php` | Validar peticiones POST autenticadas y modificar el estado | BD, sesión, utilidades, i18n; vídeos cuando hace falta | Formularios HTML y JavaScript del progreso de reproducción a través de `/api/...` |
| Referencia de la API | `backend/api-doc.php`, `backend/inc/openapi.php`, `frontend/views/api-doc.php` | Documento OpenAPI 3.1 y HTML de estilo Swagger generados a partir de `fRouteApi()` | Tablas de rutas, utilidades | `/api/doc/`, `/api/doc/openapi.json` |
| Adaptador de base de datos | `backend/inc/db.php` | Una conexión PDO, pragmas de SQLite, utilidades de consultas preparadas, registro privado de errores | `backend/config.php`, PDO SQLite | Páginas, manejadores, autenticación, multimedia, CLI |
| Sesión y autenticación | `backend/inc/session.php` | Política de cookies, CSRF, usuarios, roles, restablecimiento de contraseña de administrador, limitación de intentos, compatibilidad del esquema, guardas, destinos de redirección seguros | BD, configuración, tablas de rutas; i18n cuando está cargado | Todos los puntos de entrada protegidos |
| Internacionalización | `backend/inc/i18n.php` | Normalización de idiomas, carga de catálogos, reserva y persistencia | BD, sesión, catálogos JSON | Páginas, manejadores, interfaz compartida |
| Dominio de vídeo | `backend/inc/videos.php` | Rutas seguras, validación con ffprobe, metadatos, miniaturas y su URL de API, importación y sincronización de carpeta | BD, sesión, herramientas de FFmpeg | Manejadores de subida/sincronización, CLI, multimedia, componentes |
| Componentes de interfaz | `frontend/components/components.php` | Tarjetas de vídeo escapadas, filas, avatares, pestañas de los paneles y paginación | utilidades, iconos, vídeos, i18n | Páginas |
| Marco de la aplicación | `frontend/components/chrome.php` | Barra superior, barra lateral, menú de la cuenta (paneles, idioma, cierre de sesión), búsqueda | sesión, utilidades, iconos, i18n | `backend/index.php` |
| Iconos | `frontend/components/icons.php` | Funciones de iconos SVG en línea | Ninguna | Marco, componentes, páginas |
| Utilidades | `backend/inc/helpers.php` | Escape, página actual, paginación acotada y formato de presentación; carga las tablas de rutas | Tablas de rutas; i18n opcionalmente | Todo el renderizado y las redirecciones |
| Pasarela multimedia | `backend/media.php` | Transmisión autenticada de vídeo por GET/HEAD y rangos de bytes únicos | BD, sesión, utilidades, vídeos | `/api/user/videos/{id}/stream` |
| Pasarela de miniaturas | `backend/thumb.php` | Entrega autenticada de JPEG por GET/HEAD | BD, sesión, utilidades, vídeos | `/api/user/videos/{id}/thumbnail` |
| Catálogos de idioma | `frontend/lang/*.json` | Textos de interfaz traducidos y coincidentes para cada idioma | Analizador JSON | `backend/inc/i18n.php` |
| Inicializador | `backend/seed.php` | Inicialización vacía de producción; metadatos de demostración explícitos y no disponibles; copia de seguridad verificada al sustituir | Configuración, PDO SQLite | Instalador y CLI de administrador |
| CLI de mantenimiento | `backend/*-cli.php` | Asegurar el administrador antiguo (e informar del cambio de dirección), restablecer una contraseña, sincronizar archivos multimedia | Módulos compartidos de la aplicación | Instalador y operador del servidor |
| Política de Apache | `frontend/public/.htaccess` | Denegar rutas privadas y archivos ocultos, enviar toda petición que no sea un archivo al controlador frontal | Reescritura de Apache | Sitio web desplegado |
| Presentación | `frontend/public/styles.css` | Diseños adaptables sin desbordamiento horizontal, temas claro/oscuro del sistema, estilos de la referencia de la API | Nombres de clase renderizados | Todas las páginas |
| Navegación en el navegador | `frontend/public/ui.js` | Barra lateral móvil, fondo oscurecido, Escape y estado de accesibilidad del menú | DOM nativo y matchMedia | Plantilla base |
| Arranque público | `frontend/public/index.php` | Pasar cada petición al backend privado | `backend/router.php` | Apache y el enrutador de desarrollo |
| Plantillas | `frontend/views/*.php`, `frontend/views/pages/*.php` | Renderizar los datos de página preparados, la plantilla base compartida, el 404 y la referencia de la API | Componentes del frontend y utilidades del backend | Controladores de páginas |
| Datos de navegación | `backend/inc/navigation.php` | Consultar notificaciones y sugerencias y resolver el contexto de navegación del usuario | BD, sesión, i18n | `frontend/components/chrome.php` |
| Despliegue en Debian | `deploy/install-update-reinstall-debian.sh` | Apache en 80/443 con TLS, registro/credenciales protegidos, detección del dominio, certificados preparados por etapas, reversión de la configuración, migración y retirada de la instalación `mytube` | Debian, apt, Apache, PHP, SQLite, curl, OpenSSL | Operador root del servidor |
| Despliegue en Alpine | `deploy/install-update-reinstall-alpine.sh` | La misma transacción en sh/apk/PHP 8.4/OpenRC con una configuración principal de Apache gestionada y sesiones privadas | Alpine main/community, Apache, PHP, SQLite, OpenSSL | Operador root del servidor |
| Utilidad de enlaces simbólicos | `backend/tools/generate_symlink.py` | Enlazar recursivamente un árbol de origen externo en `/var/www/myowntube-videos` | Python 3.13+, sistema de archivos | Operador del servidor antes de sincronizar |
| Enrutador de desarrollo local | `deploy/local-dev-router.php` | Reproducir el comportamiento de `.htaccess` (denegación de rutas privadas, controlador frontal) en el servidor integrado de PHP | Servidor de desarrollo de PHP | Configuración local |

## 3. Índice de símbolos clave

| Símbolo | Archivo:línea | Qué hace |
|---|---|---|
| `fRoutePages` | `backend/inc/routes.php:6` | Asigna los nombres de página a rutas limpias |
| `fRouteSectionDefaults` | `backend/inc/routes.php:29` | Pestaña por defecto de cada raíz de sección |
| `fRouteLegacyPages` | `backend/inc/routes.php:40` | Traduce los antiguos nombres de `?page=` a los nombres de página actuales |
| `fRouteDashboardTabs` | `backend/inc/routes.php:60` | Pestañas de los paneles de usuario y de administrador |
| `fRouteApi` | `backend/inc/routes.php:73` | Cada endpoint de `/api/` con sus métodos, manejador, acceso, campos y respuestas |
| `fRouteMatchPage` | `backend/inc/routes.php:480` | Resuelve la ruta de una petición en un nombre de página y un parámetro `id` |
| `fRouteMatchApi` | `backend/inc/routes.php:493` | Resuelve una ruta de API (con o sin barra final) en su ruta declarada |
| `fUrl` | `backend/inc/routes.php:514` | Construye la URL limpia de una página, rellenando `{id}` y la cadena de consulta |
| `fApiUrl` | `backend/inc/routes.php:532` | Rellena `{id}` en una ruta de API |
| `fRouteLegacyDestination` | `backend/inc/routes.php:541` | Destino de una antigua dirección `/index.php?page=...` |
| `fRouterPlainResponse` | `backend/router.php:5` | Respuestas 404/405/500 en texto plano del despachador |
| `fOpenApiSpec` | `backend/inc/openapi.php:38` | Construye el documento OpenAPI 3.1 a partir de `fRouteApi()` |
| `fDb` | `backend/inc/db.php:19` | Abre y guarda en caché PDO SQLite con claves foráneas, WAL, tiempo de espera y modo síncrono normal |
| `fQuery` | `backend/inc/db.php:45` | Ejecuta una sentencia preparada y oculta a los usuarios web los errores internos de SQL |
| `fE` | `backend/inc/helpers.php:5` | Escapa una salida arbitraria para un contexto HTML de texto/atributo |
| `fPaginate` | `backend/inc/helpers.php:18` | Cuenta las filas filtradas, ajusta la página a los límites y obtiene como máximo `cPageSize` entradas |
| `fCsrfRequire` | `backend/inc/session.php:69` | Rechaza una modificación cuyo token CSRF de sesión falta o no es válido |
| `fAuthEnsureSchema` | `backend/inc/session.php:115` | Crea las tablas de autenticación/ajustes y aplica migraciones de compatibilidad idempotentes |
| `fAuthEnsureDefaultAdminEmail` | `backend/inc/session.php:175` | Cambia una sola vez `admin@mytube.home.arpa` por la dirección actual del administrador por defecto |
| `fAuthEnsureVideoAvailability` | `backend/inc/session.php:248` | Añade la disponibilidad y oculta las filas antiguas sin ruta multimedia sin borrar el estado de los usuarios |
| `fAuthMigrateUserTable` | `backend/inc/session.php:313` | Reconstruye las tablas antiguas de «Me gusta», Ver más tarde o historial con propiedad por usuario |
| `fAuthLoginAllowed` | `backend/inc/session.php:555` | Comprueba y hace caducar la ventana de limitación de inicios de sesión por dirección |
| `fAuthCreateUser` | `backend/inc/session.php:604` | Valida los datos de la cuenta, calcula el hash de la contraseña e inserta un usuario |
| `fAuthLogin` | `backend/inc/session.php:668` | Verifica las credenciales, registra los fallos, recalcula el hash cuando hace falta y rota el ID de sesión |
| `fAuthChangePassword` | `backend/inc/session.php:722` | Sustituye la contraseña propia, rota la sesión actual y revoca las demás |
| `fAuthAdminUpdateRole` | `backend/inc/session.php:772` | Cambia un rol sin permitir quitar el último administrador |
| `fAuthAdminSetPassword` | `backend/inc/session.php:799` | Un administrador establece la contraseña de otra cuenta y revoca sus sesiones |
| `fAuthSafeDestination` | `backend/inc/session.php:860` | Solo acepta rutas de página internas como destino de redirección (nunca `/api/`, esquemas ni hosts) |
| `fCurrentUser` | `backend/inc/session.php:888` | Carga la cuenta y rechaza una versión de sesión obsoleta |
| `fRequireLogin` | `backend/inc/session.php:953` | Redirige las peticiones anónimas al inicio de sesión |
| `fRequireAdmin` | `backend/inc/session.php:960` | Exige tanto la autenticación como el rol de administrador |
| `fCurrentLocale` | `backend/inc/i18n.php:132` | Resuelve el idioma del usuario, de la sesión y del sitio, en ese orden |
| `fT` | `backend/inc/i18n.php:162` | Obtiene un texto traducido con reserva en inglés y sustitución de parámetros |
| `fVideoThumbnailUrl` | `backend/inc/videos.php:118` | URL de API de la miniatura de un vídeo cuando existe el JPEG |
| `fVideoPathFromFilePath` | `backend/inc/videos.php:126` | Resuelve una ruta del catálogo solo dentro del directorio de vídeos configurado |
| `fVideoSaveJson` | `backend/inc/videos.php:180` | Escribe de forma atómica un JSON auxiliar en UTF-8 mediante un archivo temporal y un cambio de nombre |
| `fVideoValidateFile` | `backend/inc/videos.php:356` | Impone el tamaño de archivo y exige que ffprobe encuentre un flujo de vídeo real |
| `fVideoImportJson` | `backend/inc/videos.php:450` | Normaliza los metadatos del JSON auxiliar e inserta o actualiza la fila correspondiente del catálogo |
| `fVideosSyncFolder` | `backend/inc/videos.php:542` | Valida los archivos, importa JSON auxiliares/miniaturas y concilia la disponibilidad del catálogo |
| `fChannelById` | `backend/inc/videos.php:644` | Guarda en caché los registros de canal usados al renderizar vídeos |
| `fIdSet` | `backend/inc/videos.php:651` | Carga los ID de los vídeos que le gustan o que ha guardado el usuario actual |
| `fNavigationData` | `backend/inc/navigation.php:3` | Prepara los datos de navegación sin renderizar HTML |
| `fVideoCard` | `frontend/components/components.php:54` | Renderiza una tarjeta de catálogo reutilizable y escapada |
| `fDashboardTabs` | `frontend/components/components.php:151` | Renderiza la barra de pestañas de un panel con la pestaña activa |
| `fPaginationRender` | `frontend/components/components.php:165` | Renderiza los enlaces anterior/siguiente conservando los filtros |
| `fDetectDomain` | `deploy/install-update-reinstall-debian.sh:45` | `MYOWNTUBE_DOMAIN`; si no, el `ServerName` configurado; si no, el valor por defecto; el antiguo valor por defecto pasa al nuevo |
| `fAcquireLock` | `deploy/install-update-reinstall-debian.sh:94` | Impide procesos de instalación simultáneos |
| `fActivateConfigFile` | `deploy/install-update-reinstall-debian.sh:106` | Sustituye solo la configuración gestionada que ha cambiado |
| `fPrepareLogs` | `deploy/install-update-reinstall-debian.sh:118` | Se hace cargo de `/root/app-web-*` y abre `/root/webapp-*` con modo 600 |
| `fBackupConfigFile` | `deploy/install-update-reinstall-debian.sh:131` | Registra en el diario un archivo o enlace simbólico antes de un cambio del despliegue |
| `fRestoreConfigFiles` | `deploy/install-update-reinstall-debian.sh:155` | Restaura la configuración registrada en el diario tras un fallo |
| `fValidateApacheIsolation` | `deploy/install-update-reinstall-debian.sh:258` | Se niega a cambiar módulos globales de Apache cuando hay sitios ajenos habilitados |
| `fSaveCurrentData` | `deploy/install-update-reinstall-debian.sh:330` | Copia de seguridad verificada de SQLite (de la instalación actual o de la anterior) y migración de vídeos |
| `fCreateCertificate` | `deploy/install-update-reinstall-debian.sh:503` | Reutiliza un certificado válido (también el anterior) o prepara y valida uno nuevo |
| `fConfigureApache` | `deploy/install-update-reinstall-debian.sh:554` | Módulos, `Listen 80`/`Listen 443`, los dos VirtualHosts de la plantilla de Apache |
| `fRetireLegacyApacheSite` | `deploy/install-update-reinstall-debian.sh:671` | Deshabilita y registra en el diario el sitio `000-mytube` |
| `fVerifyBackend` | `deploy/install-update-reinstall-debian.sh:721` | Verifica `https://<dominio>/auth/login/` en el puerto 443 con el certificado instalado |
| `fRetireLegacyInstallation` | `deploy/install-update-reinstall-debian.sh:730` | Mueve los archivos de la instalación `mytube` a `previous-install-*` |
| `fMain` | `deploy/install-update-reinstall-debian.sh:833` | Ordena la validación, la copia de seguridad, el despliegue, la configuración, la inicialización/migración, el reinicio y la retirada |
| `fConfigureAlpineMain` | `deploy/install-update-reinstall-alpine.sh:600` | Construye la configuración principal de Apache de Alpine sin inclusiones duplicadas |
| `fRetireLegacyApacheFiles` | `deploy/install-update-reinstall-alpine.sh:747` | Registra en el diario y elimina `mytube-site.conf` y `mytube-ports.conf` |
| `fMain` | `deploy/install-update-reinstall-alpine.sh:930` | Ejecuta la transacción de instalación/actualización de Alpine |
| `fMain` | `backend/tools/generate_symlink.py:93` | Valida la entrada de la CLI, crea los enlaces y devuelve un estado de proceso significativo |

## 4. Flujos principales

### Petición de página

`GET /history/` → `.htaccess` de Apache (no es un archivo) → `frontend/public/index.php` → `backend/router.php` (no es `/api/`) → `backend/index.php`: cabeceras de seguridad → redirección del antiguo `?page=`, redirección por falta de barra final, redirección a la pestaña por defecto de la sección → disponibilidad de la base de datos → `fRouteMatchPage()` → `fAuthEnsureSchema()` e idioma → comprobaciones de acceso (público, sesión iniciada, administrador) → `backend/pages/<página>.php` prepara los datos → `frontend/views/pages/<página>.php` renderiza → `frontend/views/layout.php` lo envuelve. Una ruta desconocida renderiza `frontend/views/page-not-found.php` con estado 404.

### Petición a la API

`POST /api/user/likes/toggle` → `.htaccess` → `frontend/public/index.php` → `backend/router.php` → `fRouteMatchApi()` → comprobación del método (405 con `Allow` si no es correcto) → parámetros de ruta copiados en `$_GET` → `require backend/actions/toggle-like.php` → guardas de inicio de sesión y CSRF → modificación → aviso → 303 a la página de procedencia.

### Inicio de sesión

`GET /playlists/` anónimo → 302 a `/auth/login/?next=%2Fplaylists%2F` → formulario → `POST /api/auth/login` → `fCsrfRequire()` → `fAuthLoginAllowed()` → `password_verify()` → contador de fallos o `fAuthStartSession()` (rotación del ID de sesión y `session_version` guardado) → `fAuthSafeDestination(next)` → 303 a `/playlists/`.

### Recuperación de contraseña

`/auth/login/` → **¿Has olvidado tu contraseña?** → `/auth/recover/` (solo información, la aplicación no envía correos) → un administrador abre `/admin/dashboard/users/` → `POST /api/admin/users/password` → `fAuthAdminSetPassword()` → hash Argon2id y `session_version + 1` → todas las sesiones de esa cuenta dejan de ser válidas. Sin ningún administrador: `reset-admin-password-cli.php` desde el servidor.

### Búsqueda e historial privado

Formulario de la barra superior → `POST /api/user/searches` → guardas de inicio de sesión/CSRF → consulta acotada → inserción o actualización (upsert) del historial de cada usuario → 303 a `/search/?q=…` → consulta de resultados de solo lectura. Los GET de resultados y de sugerencias nunca modifican el historial.

### Sustitución de contraseña

`/user/dashboard/account/` → `POST /api/user/password` → verificar la contraseña actual → hash Argon2id → `session_version + 1` → la sesión actual se rota y sigue funcionando; todas las demás sesiones se revocan.

### Subida

`/upload/` → `POST /api/user/videos/upload` (multipart, `LimitRequestBody 0` de Apache solo aquí) → guardas de inicio de sesión y CSRF → comprobaciones de extensión/tamaño → destino sin colisiones → `move_uploaded_file()` → `fVideoValidateFile()`/ffprobe → `fVideoCreateMetadata()` → JSON auxiliar atómico → `fVideoImportJson()` y miniatura → 303 a `/watch/{id}/`. Cualquier fallo elimina el vídeo, el JSON auxiliar y la miniatura creados en ese intento.

### Sincronización de carpeta

`POST /api/admin/videos/sync` o `sync-videos-cli.php` → `fVideosSyncFolder()` → comprobación previa de `ffprobe` → instantánea de los ID existentes respaldados por archivo → enumerar las extensiones permitidas → validar los flujos reales → generar los JSON auxiliares que falten → `fVideoImportJson()` → generar/actualizar las miniaturas → registrar los fallos de cada archivo → marcar como no disponibles, en una transacción, los ID de la instantánea que no se importaron. Las filas y el estado de los usuarios nunca se borran.

### Reproducción y progreso

`/watch/{id}/` → `<video src="/api/user/videos/{id}/stream">` → guarda de inicio de sesión y comprobación de ruta segura → rango único opcional → liberación del bloqueo de sesión → bloques de 1 MiB. Tras el evento `playing`, JavaScript envía por POST el porcentaje con el token CSRF a `/api/user/history/progress`; una transacción inserta el historial y cuenta la visualización solo en la primera inserción; en caso contrario, actualiza el progreso. La respuesta es JSON.

### Migración al arrancar

Cualquier punto de entrada de la aplicación o de mantenimiento → `fAuthEnsureSchema()` → tablas que faltan → cambio de la dirección del administrador por defecto → ajustes → idioma del usuario y versión de sesión → disponibilidad de los vídeos → identificador del canal de subidas → búsqueda del administrador → tablas privadas antiguas reconstruidas con propiedad → índices → marcadores de migración en `schema_migrations`.

### Instalación, actualización y migración (Debian y Alpine)

`fMain()` → comprobación de root y bloqueo exclusivo → `fPrepareLogs()` (se hace cargo de `/root/app-web-*`) → comprobaciones del entorno y del aislamiento → paquetes y directorios → descarga del archivo comprimido de `main` → detener Apache → `fSaveCurrentData()` (copia de seguridad verificada de `myowntube.db`, o de la anterior `mytube.db`; vídeos movidos desde `/var/www/mytube-videos`, dejando un enlace en su lugar) → despliegue por etapas en `/var/www/myowntube` → `fConfigureApache()` (ini de PHP, certificado, módulos, puertos 80/443, VirtualHosts, retirada del sitio/ini anterior, configtest) → inicialización (seed) o `secure-admin-cli.php` → habilitar y reiniciar Apache → `fVerifyBackend()` → guardar las credenciales → confirmación (commit) → `fRetireLegacyInstallation()`. Un fallo antes de la confirmación restaura la configuración registrada en el diario y el árbol web anterior, y reinicia Apache si estaba en marcha.

### Catálogo y colecciones paginados

Controlador de página → recuento filtrado y consulta ordenada de filas → `fPaginate()` ajusta la página solicitada a los límites y aplica el límite de 24 filas → renderizado de tarjetas/filas → `fPaginationRender()` construye URL limpias escapadas que conservan el contexto de categoría, búsqueda o lista. Los comentarios usan `comments_page`; las demás listas usan `p`. La ordenación incluye un ID estable para evitar duplicados entre páginas.

## 5. Puntos de entrada, rutas y comandos

### Páginas

| Ruta/URL/comando | Manejador | Archivo |
|---|---|---|
| `GET /` (`?cat=`, `?p=`) | Catálogo por categorías | `backend/pages/home.php` |
| `GET /trending/` (`?tab=`) | Lista de tendencias filtrada por categoría | `backend/pages/trending.php` |
| `GET /search/?q=…` | Resultados de búsqueda de solo lectura | `backend/pages/search.php` |
| `GET /watch/{id}/` (`?list=`, `?comment_sort=`, `?comments_page=`) | Reproductor, acciones y comentarios | `backend/pages/watch.php` |
| `GET /history/` | Historial de reproducción del usuario actual | `backend/pages/history.php` |
| `GET /liked/` | «Me gusta» del usuario actual | `backend/pages/liked.php` |
| `GET /watch-later/` | Lista de Ver más tarde del usuario actual | `backend/pages/watchlater.php` |
| `GET /playlists/` | Listas del usuario actual | `backend/pages/playlists.php` |
| `GET /playlists/{id}/` | Detalle de una lista propia | `backend/pages/playlist.php` |
| `GET /upload/` | Formulario de subida y carpeta de vídeos | `backend/pages/upload.php` |
| `GET /auth/login/` (`?next=`) | Formulario público de inicio de sesión | `backend/pages/login.php` |
| `GET /auth/register/` | Formulario de registro, cuando está habilitado | `backend/pages/register.php` |
| `GET /auth/recover/` | Información pública de recuperación de contraseña | `backend/pages/recover.php` |
| `GET /user/dashboard/` | 302 a `/user/dashboard/profile/` | `backend/index.php` |
| `GET /user/dashboard/profile/` | Pestaña Perfil | `backend/pages/profile.php` |
| `GET /user/dashboard/account/` | Pestaña Cuenta: idioma, contraseña, historial de búsquedas | `backend/pages/account.php` |
| `GET /admin/dashboard/` | 302 a `/admin/dashboard/users/` | `backend/index.php` |
| `GET /admin/dashboard/users/` | Pestaña Usuarios | `backend/pages/users.php` |
| `GET /admin/dashboard/settings/` | Pestaña de configuración del sitio | `backend/pages/site-settings.php` |
| `GET /index.php?page=…` | 301 a la dirección actual | `backend/index.php` |

### API

Todos los endpoints siguientes están declarados en `fRouteApi()` y documentados en `/api/doc/` (HTML) y `/api/doc/openapi.json`. Los endpoints de formulario solo aceptan POST, exigen el token CSRF y responden 303; todos, excepto el inicio de sesión y el registro, requieren un usuario con la sesión iniciada.

| Ruta/URL/comando | Manejador | Archivo |
|---|---|---|
| `POST /api/auth/login` | Verificar las credenciales e iniciar la sesión | `backend/actions/login.php` |
| `POST /api/auth/register` | Crear un usuario estándar si el registro está habilitado | `backend/actions/register.php` |
| `POST /api/auth/logout` | Destruir y rotar la sesión | `backend/actions/logout.php` |
| `POST /api/user/profile` | Cambiar el nombre visible | `backend/actions/update-profile.php` |
| `POST /api/user/password` | Verificar y sustituir la contraseña actual | `backend/actions/change-password.php` |
| `POST /api/user/language` | Guardar el idioma del usuario | `backend/actions/set-language.php` |
| `POST /api/user/searches` | Registrar una búsqueda privada y abrir los resultados | `backend/actions/record-search.php` |
| `POST /api/user/searches/clear` | Borrar las sugerencias de búsqueda privadas | `backend/actions/clear-search-history.php` |
| `POST /api/user/likes/toggle` | Añadir/quitar un «Me gusta» | `backend/actions/toggle-like.php` |
| `POST /api/user/watch-later/toggle` | Añadir/quitar un elemento de Ver más tarde | `backend/actions/toggle-watchlater.php` |
| `POST /api/user/history/progress` | Progreso en JSON y transacción de la primera visualización | `backend/actions/save-progress.php` |
| `POST /api/user/history/remove` | Borrar un elemento del historial | `backend/actions/remove-history.php` |
| `POST /api/user/history/clear` | Borrar todo el historial | `backend/actions/clear-history.php` |
| `POST /api/user/comments` | Publicar un comentario | `backend/actions/post-comment.php` |
| `POST /api/user/playlists/create` | Crear una lista | `backend/actions/create-playlist.php` |
| `POST /api/user/playlists/update` | Editar el nombre y la descripción de una lista | `backend/actions/update-playlist.php` |
| `POST /api/user/playlists/delete` | Eliminar una lista | `backend/actions/delete-playlist.php` |
| `POST /api/user/playlists/items` | Añadir/quitar/recolocar un elemento | `backend/actions/update-playlist-item.php` |
| `POST /api/user/videos/upload` | Validar, guardar e importar una subida | `backend/actions/upload-video.php` |
| `GET\|HEAD /api/user/videos/{id}/stream` (`?download=1`) | Archivo multimedia autenticado y rangos de bytes | `backend/media.php` |
| `GET\|HEAD /api/user/videos/{id}/thumbnail` | Miniatura autenticada | `backend/thumb.php` |
| `POST /api/admin/videos/sync` | Sincronización de carpeta | `backend/actions/sync-videos.php` |
| `POST /api/admin/settings` | Idioma del sitio e interruptor de registro | `backend/actions/save-settings.php` |
| `POST /api/admin/users/create` | Crear una cuenta | `backend/actions/create-user.php` |
| `POST /api/admin/users/role` | Cambiar un rol | `backend/actions/update-user-role.php` |
| `POST /api/admin/users/password` | Establecer la contraseña de otra cuenta | `backend/actions/set-user-password.php` |
| `POST /api/admin/users/delete` | Eliminar una cuenta | `backend/actions/delete-user.php` |
| `GET /api/doc/`, `GET /api/doc/openapi.json` | Referencia de la API y documento OpenAPI | `backend/api-doc.php` |

### Comandos de CLI y de despliegue

| Ruta/URL/comando | Manejador | Archivo |
|---|---|---|
| `php backend/seed.php [--force] [--demo]` | Crear una base de datos de forma atómica; opcionalmente, hacer una copia de seguridad y sustituirla | `backend/seed.php` |
| `php backend/secure-admin-cli.php` | Rotar las credenciales por defecto históricas, cambiar la antigua dirección por defecto, ejecutar las migraciones del esquema | `backend/secure-admin-cli.php` |
| `php backend/reset-admin-password-cli.php [email]` | Restablecer/ascender una cuenta, revocar sesiones, eliminar las limitaciones de intentos | `backend/reset-admin-password-cli.php` |
| `php backend/sync-videos-cli.php` | Sincronizar archivos multimedia sin navegador | `backend/sync-videos-cli.php` |
| `deploy/install-update-reinstall-debian.sh` | Instalar, actualizar o reinstalar en Debian | Misma ruta |
| `deploy/install-update-reinstall-alpine.sh` | Instalar, actualizar o reinstalar en Alpine | Misma ruta |
| `python3 backend/tools/generate_symlink.py SOURCE` | Enlazar un árbol de archivos externo en el directorio multimedia | `backend/tools/generate_symlink.py` |
| `php -S 127.0.0.1:8080 -t frontend/public deploy/local-dev-router.php` | Servidor local con el comportamiento de `.htaccess` | `deploy/local-dev-router.php` |

Variables de entorno de los instaladores: `MYOWNTUBE_DOMAIN`, `MYOWNTUBE_ADMIN_PASSWORD` y `MYOWNTUBE_INSTALLER_LIBRARY_ONLY=1` (Alpine; carga las funciones del instalador sin ejecutar la instalación).

## 6. Análisis de impacto

| Componente crítico/compartido | Los cambios afectan a |
|---|---|
| Tablas de páginas o de la API de `backend/inc/routes.php` | Todos los enlaces y formularios (`fUrl()`), las redirecciones tras iniciar sesión, los paneles, el despachador de la API, `/api/doc/`, las ubicaciones de `LimitRequestBody` en ambos instaladores y los dos conjuntos de documentación |
| Rutas y `cSessionName` de `backend/config.php` | Conexión a la BD, JSON auxiliares, pasarelas multimedia, inicializador, CLI, suposiciones de los instaladores, la cookie de sesión y el esquema de seguridad de OpenAPI |
| `backend/inc/db.php` | Todas las consultas y transacciones; cambiar los pragmas afecta a la concurrencia y al comportamiento de las copias de seguridad |
| `backend/inc/session.php` y `users.session_version` | Todas las páginas y manejadores, formularios con CSRF, revocación, roles, migraciones, datos propiedad de los usuarios, seguridad de las redirecciones |
| `fAuthSafeDestination()` | Todas las redirecciones `next`/`back`; ampliarla puede crear redirecciones abiertas |
| `backend/inc/i18n.php` o una clave de idioma | Marco de la aplicación, páginas, avisos de los manejadores, los cuatro catálogos JSON; las claves que faltan recurren al inglés |
| Extensiones permitidas/reglas de rutas de `backend/inc/videos.php` | Subida, sincronización de carpeta, búsqueda de archivos multimedia, miniaturas, JSON auxiliares, registros ya importados, la descripción del campo de subida en `fRouteApi()` |
| Semántica de `videos.file_path`/`is_available` | Visibilidad en el catálogo, reproducción, miniaturas, sincronización, descarga, validación del progreso, estado de los usuarios conservado |
| Lógica de rangos de `backend/media.php` | Desplazamiento por el vídeo en el navegador, descargas, concurrencia de sesión en peticiones largas, cabeceras de contenido |
| `backend/actions/save-progress.php` | Orden del historial, reanudación, recuento de visualizaciones, clasificación de tendencias |
| Claves de las tablas propiedad de los usuarios | «Me gusta», Ver más tarde, historial, listas, borrado en cascada de cuentas, migración antigua |
| `frontend/public/.htaccess` y `deploy/local-dev-router.php` | Qué archivos son públicos y si las URL limpias llegan al controlador frontal |
| Nombres de clase de `frontend/public/styles.css` | Todos los renderizadores de páginas, marco de la aplicación, componentes y la referencia de la API |
| Rutas, nombres y propietarios de los instaladores | Actualizaciones, la migración y retirada de `mytube`, recuperación de copias de seguridad, acceso de Apache, límites de escritura, destino de la utilidad de enlaces simbólicos, ambos manuales |
| Puertos y VirtualHosts de Apache | Exposición directa en 80/443, redirección de HTTP a HTTPS, dirección del cliente que ven la limitación de intentos y los registros |

## 7. Puntos de extensión

### Añadir una página

1. Añade el nombre de la página y su ruta limpia a `fRoutePages()` (y a `fRouteDashboardTabs()` si es una pestaña de un panel).
2. Añádela a `$aPublicPages` o a `$aAdminPages` en `backend/index.php` cuando no sea una página normal con sesión iniciada.
3. Crea `backend/pages/<nombre>.php` para las consultas y la lógica sensible al acceso, y `frontend/views/pages/<nombre>.php` para el HTML escapado (en una pestaña de panel, llama primero a `fDashboardTabs()`).
4. Enlázala con `fUrl('<nombre>')`, añade la navegación en `frontend/components/chrome.php` y los textos en los cuatro catálogos `frontend/lang/*.json`.
5. Actualiza los mapas de rutas y de módulos aquí y en los manuales.

### Añadir un endpoint de la API

1. Crea `backend/actions/<nombre>.php` y carga la configuración, la BD, la sesión, las utilidades y la i18n.
2. Llama a `fRequireLogin()` o a `fRequireAdmin()` y a `fCsrfRequire()` antes de leer los datos de la modificación.
3. Valida los tipos, las longitudes, la propiedad y los registros relacionados. Usa los marcadores de posición de `fQuery()` y una transacción para los invariantes de varios pasos.
4. Añade una entrada a `fRouteApi()` bajo `/api/auth/`, `/api/user/` o `/api/admin/` con métodos, manejador, acceso, campos y respuestas; `/api/doc/` se actualiza solo.
5. Apunta el `action` del formulario a la nueva ruta, añade `fCsrfField()`, guarda un aviso traducido y redirige con 303.
6. Las páginas GET deben seguir siendo de solo lectura: todo cambio de estado pasa por un endpoint POST bajo `/api/`.

### Añadir un idioma

1. Añade el idioma normalizado y su nombre visible en `backend/config.php`.
2. Copia el conjunto completo de claves en inglés a `frontend/lang/<idioma>.json` y traduce los valores sin cambiar los marcadores de posición.
3. Añade las variantes de README, MANUAL y CODE y sus líneas de idiomas.

### Añadir una colección propiedad del usuario

Crea una tabla cuya clave primaria empiece por `user_id`, referencia a los usuarios con la regla `ON DELETE` deseada, filtra todas las consultas por `fUserId()` y añade un índice para el orden de su lista. Si las instalaciones antiguas tenían una tabla global, añade en `fAuthEnsureSchema()` una migración transaccional que conserve los datos.

### Añadir metadatos de vídeo

Amplía `fVideoCreateMetadata()` y `fVideoImportJson()` a la vez. Decide si SQLite necesita el valor para hacer consultas; si no, guárdalo en el JSON auxiliar. Mantén las escrituras atómicas de los JSON auxiliares, las reglas de rutas seguras, la reversión de las subidas y la compatibilidad con los JSON auxiliares que no incluyan el nuevo campo.

### Añadir una CLI de mantenimiento

Rechaza las SAPI que no sean CLI antes de cargar lógica que modifique datos, devuelve un valor distinto de cero en caso de fallo, reutiliza las utilidades de la aplicación y nunca expongas secretos a través de una ruta web. Mantén el sufijo `*-cli.php` para que Apache y el enrutador de desarrollo denieguen el acceso.

## 8. Invariantes de datos y seguridad

- Las filas de la base de datos nunca apuntan fuera de `cVideosPath`; solo se resuelven los nombres base de `videos.file_path`.
- Un archivo importado reproducible necesita tanto el archivo multimedia como el JSON auxiliar. Una miniatura necesita además el archivo JPEG.
- Las filas del catálogo respaldadas por archivo que no aparecen en una sincronización correcta pasan a no estar disponibles, no se borran; una nueva importación correcta las reactiva.
- Las extensiones de vídeo son una lista de permitidos inicial, no una prueba del contenido. `ffprobe` debe encontrar un códec de vídeo y unas dimensiones positivas.
- Los JSON auxiliares y las bases de datos nuevas de inicialización se activan mediante un cambio de nombre, no se escriben en su sitio.
- Las lecturas y escrituras de datos propiedad de los usuarios incluyen `user_id`; las acciones sobre listas confirman además la propiedad de la lista.
- El último administrador no se puede degradar ni eliminar, el usuario con la sesión iniciada no puede eliminar su propia cuenta y los administradores solo cambian su propia contraseña desde la pestaña de la cuenta.
- El registro público está desactivado por defecto. Las contraseñas se guardan con hash y deben tener entre 12 y 4096 caracteres.
- Las sesiones autenticadas deben coincidir con `users.session_version`; cambiar, establecer o restablecer una contraseña revoca las versiones obsoletas.
- Las modificaciones usan POST con un token CSRF de sesión; todas las modificaciones viven bajo `/api/`. Los GET de resultados y de sugerencias nunca registran historial de búsquedas. Los destinos de redirección son únicamente rutas de página internas.
- Los métodos no admitidos en rutas de la API responden 405; las rutas de API desconocidas responden 404 y las páginas desconocidas renderizan una página 404.
- Las sesiones de las pasarelas multimedia y de miniaturas se cierran antes de la salida de archivos largos para no serializar las peticiones de un mismo usuario.
- Las respuestas web no revelan excepciones SQL ni detalles del sistema de archivos; los errores completos van a los registros del servidor.
- La base de datos de ejecución y los archivos de vídeo no forman parte del árbol de código y deben copiarse juntos en las copias de seguridad.
- Los instaladores solo cambian módulos globales de Apache en una instancia sin sitios ajenos habilitados.

## 9. Desarrollo local

Ejecuta estos comandos desde la raíz del proyecto. PHP necesita PDO SQLite, mbstring y Argon2id; `ffmpeg` y `ffprobe` deben estar en el `PATH`.

```bash
MYOWNTUBE_ADMIN_PASSWORD='replace-with-a-long-unique-password' php backend/seed.php
php -S 127.0.0.1:8080 -t frontend/public deploy/local-dev-router.php
```

Abre `http://127.0.0.1:8080/` e inicia sesión como `admin@myowntube.home.arpa`. El enrutador reproduce `.htaccess`: sirve `styles.css` y `ui.js`, deniega el acceso a la base de datos, los módulos internos, los archivos ocultos, la configuración y los puntos de entrada de CLI, y envía todas las demás direcciones al controlador frontal. `php backend/seed.php --demo` añade metadatos de ejemplo que siguen sin estar disponibles hasta que se importan los archivos multimedia correspondientes. Detén el tráfico web antes de usar `--force` para sustituir una base de datos existente.
