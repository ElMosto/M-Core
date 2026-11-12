# M-Core — ERP (front + APIs en PHP)

> **Nota — nombre temporal.** "M-Core" es un nombre provisional; cuando
> tengan el definitivo, solo hay que cambiar el texto visible en 6
> archivos (nada de lógica, es puro branding):
> `includes/layout-top.php` (título de pestaña + logo del sidebar),
> `login.php` (título + logo), `ticket-venta.php` y `ticket-proyecto.php`
> (encabezado del ticket), `assets/js/app.js` (comentario), y
> `api/config.php` (comentario). El correo del admin sembrado por defecto
> (`admin@m-core.local` en `api/db.php`, solo aplica a instalaciones
> nuevas) también puede actualizarse ahí. **A propósito no se tocaron**
> los prefijos internos del código (`timbra_` en nombres de función,
> `TIMBRA_` en constantes, ni el archivo `storage/timbra.sqlite`) — son
> detalles invisibles para cualquiera que use el sistema, así que
> renombrarlos ahora sería puro riesgo sin ningún beneficio visible,
> sobre todo con un nombre que va a volver a cambiar.

Front ligero (Bootstrap 5 + Bootstrap Icons vía CDN, sin build de Node) y un
backend en PHP puro + SQLite. Empezó como un SaaS de timbrado (Dashboard,
Alta de contribuyente/CSD, Timbrado individual/masivo) y ahora tiene login
con roles y un módulo de Tareas — la idea es seguir creciendo hacia un ERP.

## Requisitos del servidor

- PHP **8.0 o superior** (se usa `str_ends_with`, sintaxis de arrays con
  `const`, etc.).
- Extensiones: `pdo_sqlite`, `openssl`, `zip` (para exportar el Gantt a
  Excel), `fileinfo` (para validar el tipo de la foto de perfil), `curl` (esta última solo si configuras
  `TIMBRA_PAC_HEALTHCHECK_URL`).
- Apache con `.htaccess` habilitado (`AllowOverride All`) **o** el bloque
  equivalente en Nginx/otro servidor para bloquear `/storage/` (ver abajo).

## Cómo levantarlo en local

```bash
cd timbrado
php -S localhost:8000
```

Abre `http://localhost:8000/login.php`. La base de datos SQLite
(`storage/timbra.sqlite`) y las tablas se crean solas en la primera petición,
incluyendo un usuario **administrador por defecto**:

```
correo:     admin@m-core.local
contraseña: CambiaEstaClave123
```

**Entra y cámbiala de inmediato** (o crea tu propio admin y desactiva este)
desde el módulo Usuarios. Ese usuario se siembra una sola vez, la primera vez
que se crea la base de datos — si ya tienes usuarios, no se vuelve a crear.

Para probar la carga masiva, usa el archivo de ejemplo
`samples/lote-ejemplo.csv` (incluye filas válidas e inválidas a propósito,
para ver el desglose de rechazos).

## Arquitectura: pensado para web *y* para una futura app

`api/` es la única fuente de verdad de datos y acciones — tanto la web
actual como cualquier cliente nuevo (una app de Android, por ejemplo)
hablan con el sistema exclusivamente a través de esos endpoints, todos en
JSON. Las páginas `.php` de la raíz (`dashboard.php`, `tareas.php`, etc.)
son solo **vistas**: HTML + Bootstrap que le hacen `fetch()` a `api/` para
todo lo que muestran o guardan — no tienen lógica de negocio propia.

Dentro de `api/` hay dos estilos de endpoint, y la diferencia importa si
vas a construir un cliente nuevo:

- **Endpoints JSON "puros"** (la inmensa mayoría, incluido
  `procesar-empresa.php`) — siempre responden JSON, nunca redirigen. Si no
  hay sesión, responden `401` en vez de mandarte a `login.php`. Estos son
  los que un cliente nuevo (la app) puede llamar directo, sin sorpresas.
  (`procesar-empresa.php` guarda, además, un *fallback* a redirect solo
  para el caso remoto de que el formulario se envíe sin JavaScript — una
  app nunca cae en esa rama, siempre manda el header que activa JSON.)
- **Endpoints "estilo página"**, todos **fuera** de `api/`:
  `exportar-proyecto.php` y los dos `ticket-*.php` — están pensados para
  abrirse desde el navegador: si no hay sesión o no tienes permiso,
  **redirigen** a otra página en vez de responder JSON, y regresan un
  archivo o HTML para imprimir, no datos. Por eso se quedaron fuera de
  `api/` — no tiene sentido que una app los "consuma" como API, aunque sí
  puede abrirlos con un Intent/WebView si algún día quieres ese atajo
  (por ejemplo, "ver el ticket" abriendo esa URL en el navegador del
  teléfono en vez de reconstruir la vista nativa).

### Cómo se conectaría una app nativa

1. **Login**: `POST api/auth.php` con `{ "accion": "login", "email", "password" }`.
   La sesión sigue siendo una cookie de PHP — un cliente HTTP normal en
   Android (OkHttp, Retrofit con `CookieJar`) o iOS (`URLSession` con
   `HTTPCookieStorage`) la guarda y reenvía solo, igual que un navegador.
   No hace falta un token aparte para arrancar.
2. **Armar el menú**: la respuesta del login (y `GET api/auth.php` en
   cualquier momento) trae `modulos: [...]` — la lista de módulos a los
   que ese usuario tiene acceso, ya resuelta por el servidor. La app arma
   su navegación con esa lista en vez de reimplementar las reglas de
   permisos de `TIMBRA_MODULOS_ASIGNABLES`/Roles.
3. **Todo lo demás**: el resto de `api/*.php` — mismos contratos que ya
   usa la web, documentados endpoint por endpoint más abajo en este
   archivo.
4. **Logout**: `POST api/auth.php` con `{ "accion": "logout" }`.

Esto es una base sólida para empezar la app cuando decidan hacerlo, pero
**no** intenta resolver todo de una vez: seguridad de tokens de larga
duración, refresco de sesión, notificaciones push, o subir archivos desde
la cámara son temas que se atienden cuando lleguemos a construir la app de
verdad, no ahora.

### GET api/auth.php
```json
{ "autenticado": true, "usuario": { "id": 3, "nombre": "...", "email": "...", "rol": "lider_proyecto", "foto_path": null },
  "modulos": ["tareas", "clientes", "leads", "inventario", "ventas", "agenda", "perfil"] }
```
Sin sesión/token: `{ "autenticado": false }` (siempre `200`, nunca `401`
— es una pregunta, no una acción que requiera estar logueado). Funciona
igual sin importar si te identificaste por cookie (web) o por
`Authorization: Bearer <token>` (app) — ver `timbra_usuario_actual()`.

### POST api/auth.php
```json
{ "accion": "login", "email": "...", "password": "..." }
```
`200` con
`{ "success": true, "usuario": {...}, "modulos": [...], "redirect": "dashboard.php", "token": "...", "token_expira": "2026-09-15 12:00:00" }`
o `401` con `{ "success": false, "message": "..." }`. `redirect` es la
página web de inicio de ese usuario — la app la ignora y usa `modulos`
para decidir su propia pantalla. `token` se emite siempre, para
cualquiera que llame — la web lo ignora (sigue con su cookie), la app lo
guarda y lo manda en cada petición siguiente como
`Authorization: Bearer <token>`. Dura `TIMBRA_TOKEN_DIAS_VALIDEZ` días.
```json
{ "accion": "logout" }
```
`{ "success": true }`. Si la petición trae un token, lo revoca (deja de
servir); si no, cierra la sesión de cookie. `login.php` (la vista web) ya
usa este mismo endpoint por `fetch()` en vez de procesar el login él
mismo.

## Empaquetar como app de Android

> **Actualización de rumbo:** esta sección describía el plan original
> (PWA + TWA: empaquetar la web tal cual). Después se decidió algo más
> flexible — **Capacitor**, con el front empaquetado *dentro* de la app
> (no se descarga del servidor cada vez) y el servidor sirviendo
> **solo `/api/`**. Ese proyecto vive aparte, en `mosta-app/` (ver su
> propio `README.md` ahí). Lo de PWA de abajo **sigue funcionando y no
> se quitó** — la web sigue siendo instalable como PWA por su cuenta,
> es un beneficio aparte que no estorba con la app de Capacitor.
>
> Lo que si cambió en el backend para soportar la app de Capacitor:
> - **CORS**: `api/lib/helpers.php` ahora manda los encabezados
>   `Access-Control-Allow-*` cuando la petición viene de un origen en la
>   lista blanca `TIMBRA_CORS_ORIGENES_PERMITIDOS` (`api/config.php`) —
>   necesario porque la app llama a la API desde otro origen, cosa que la
>   web (mismo-origen) nunca necesitó.
> - **Login por token**: además de la cookie de sesión (que sigue usando
>   la web sin cambios), `POST api/auth.php { accion: "login" }` ahora
>   también regresa un `token` — la app lo guarda y lo manda como
>   `Authorization: Bearer <token>` en cada petición en vez de depender
>   de una cookie entre orígenes (poco confiable en un WebView).
>   `timbra_usuario_actual()` (`api/lib/auth.php`) revisa automáticamente
>   ambas formas — cookie o token — así que **ningún otro endpoint tuvo
>   que cambiar**, ninguno de los `require_once` que ya usaban en cada
>   `api/*.php` sabe ni le importa cuál de las dos identificó al usuario.
> - Tabla nueva `sesiones_token` (token, usuario, fecha de vencimiento).
>   Vencen solas después de `TIMBRA_TOKEN_DIAS_VALIDEZ` días (30 por
>   default); se revocan al hacer logout desde la app.

Ya está la primera mitad lista en este mismo repo — la app **ya es una
PWA instalable** — y aquí está la guía para la segunda mitad (empacarla
como `.apk`/`.aab`), que corres tú en tu máquina porque necesita
Android SDK/Java, herramientas que no existen en este entorno de chat.

### Lo que ya quedó armado

- **`manifest.json`** — nombre, ícono, color de tema, `display: standalone`
  (para que abra sin la barra de direcciones del navegador).
- **`assets/icons/`** — el set completo de íconos (48 a 512px, más
  versiones "maskable" para el ícono adaptable de Android), dibujados a
  partir del mismo sello que ya usas en el sidebar y el login.
- **`service-worker.js`** — lo mínimo para que Chrome considere la app
  instalable, con una regla importante: **nunca cachea páginas PHP ni
  nada de `/api/`** (son dinámicas y dependen de tu sesión — cachearlas
  podría mostrar datos viejos, o de otra cuenta en un dispositivo
  compartido). Solo cachea `/assets/` (css/js/íconos) para que cargue más
  rápido, y muestra `offline.html` si intentas navegar sin conexión.
- Las etiquetas `<link rel="manifest">` y el registro del service worker
  ya están en `includes/layout-top.php` y en `login.php`.
- **`.well-known/assetlinks.json`** — plantilla con dos valores que
  todavía no existen (`package_name` y la huella del certificado) hasta
  que generes la app en el paso 3 de abajo.

### Requisito: HTTPS de verdad

Todo esto solo funciona sobre **HTTPS con un dominio real** (Chrome
exige esto para instalar una PWA, y Play Store lo exige para publicar).
`localhost` cuenta como excepción para probar en desarrollo, pero para
la versión final necesitas el sitio ya desplegado con certificado válido
(Let's Encrypt gratis funciona perfecto).

### Paso 1 — Confirmar que la PWA ya es instalable

Con el sitio en HTTPS, ábrelo en Chrome de escritorio → DevTools →
pestaña **Lighthouse** → categoría **PWA** → "Analyze page load". Debe
salir en verde. En un Android real, deberías poder entrar al sitio en
Chrome y ver la opción **"Instalar app"** en el menú de tres puntos.

### Paso 2 — Instalar Bubblewrap

Bubblewrap es la herramienta oficial de Google para convertir una PWA en
un proyecto Android (TWA). En tu máquina (no en el servidor):

```bash
npm install -g @bubblewrap/cli
```

Necesita Java JDK 17+ instalado; si no lo tienes, Bubblewrap te ofrece
descargarlo la primera vez que lo corres.

### Paso 3 — Generar el proyecto Android

```bash
bubblewrap init --manifest=https://tudominio.com/manifest.json
```

Te va a preguntar varias cosas interactivamente — las más importantes:

- **Application ID** (el "package name"): algo tipo `mx.mosta.app`
  (dominio invertido; usa el que prefieran, pero no lo cambies después,
  Play Store lo trata como el identificador permanente de la app).
- Si no tienes una llave de firma (keystore), Bubblewrap te ofrece crear
  una — **guárdala en un lugar seguro y no la pierdas**: sin ella no
  puedes publicar actualizaciones futuras de la misma app.

Al terminar, Bubblewrap imprime la **huella SHA-256** de esa llave.
Cópiala.

### Paso 4 — Completar assetlinks.json y volver a subirlo

Edita `.well-known/assetlinks.json` en el servidor (no en tu máquina) y
reemplaza los dos placeholders con el `package_name` del paso 3 y la
huella SHA-256 que te dio Bubblewrap. Esto es lo que le prueba a Android
"esta app y este sitio web son del mismo dueño" — sin este archivo
correcto, la app abre pero se ve con la barra de Chrome encima (como un
navegador normal, no como una app).

### Paso 5 — Compilar

```bash
bubblewrap build
```

Genera `app-release-signed.apk` (para probar en un dispositivo/emulador
con `adb install app-release-signed.apk`) y un `.aab` (el formato que
pide Play Store para publicar).

### Paso 6 — Publicar (opcional, cuando estén listos)

Cuenta de Google Play Console (pago único, actualmente ~$25 USD) → crear
la ficha de la app (nombre, descripción, capturas de pantalla, ícono) →
subir el `.aab` del paso 5. El login, los módulos y los permisos por rol
funcionan exactamente igual que en la web — es el mismo `api/auth.php` y
las mismas cookies de sesión, solo que dentro de una ventana sin barra de
navegador.

## Apariencia (logo y colores)

Exclusivo de admin — igual que Usuarios y Roles, no pasa por el checklist
de módulos asignables (`api/lib/auth.php`, junto con `usuarios`/`roles`).

- **Logo**: sube un PNG (máx. 1 MB) y se usa en vez del sello SVG de
  fábrica en el sidebar, el login, y ambos tickets imprimibles. Se guarda
  en `assets/branding/` (público por HTTP, igual que los avatares — no es
  información sensible). "Quitar logo" borra el archivo y regresa al
  sello de fábrica.
- **Colores**: 5 paletas de fábrica (Verde sello — la original, Azul
  acero, Vino, Grafito y ámbar, Índigo) para elegir con un clic, o mueve
  los 3 selectores de color a lo que quieras — elegir un color a mano
  automáticamente marca la paleta como "personalizada". Los 3 colores que
  se guardan son fondo oscuro (sidebar/topbar/login), color principal
  (botones/acentos) y su variante oscura (hover/texto); el resto de tonos
  derivados (`--brand-soft`, `--ink-2`) se calculan solos mezclando esos
  tres hacia blanco/negro (`timbra_mezclar_color()` en `api/lib/helpers.php`).

Cómo se aplica sin tocar cada página una por una: `timbra_apariencia_css_vars()`
imprime un `<style>` con las variables ya resueltas, y cada vista lo
manda **después** del `<link>` a `theme.css` — mismo principio que el bug
de móvil que ya corregimos antes (con la misma especificidad, gana lo que
aparece más abajo). Está conectado en `includes/layout-top.php`,
`login.php`, y ambos `ticket-*.php` (estos últimos con su propio par de
variables `--ticket-*`, ya que no cargan `theme.css`).

### GET api/apariencia.php  (admin)
```json
{ "logo_path": "assets/branding/logo-a1b2c3d4e5f6.png", "paleta": "azul_acero",
  "color_ink": "#0F1B2D", "color_brand": "#1B6FA8", "color_brand_dark": "#124F79" }
```
`logo_path` es `null` si no se ha subido ninguno (se usa el sello de fábrica).

### POST api/apariencia.php  (admin)
```json
{ "accion": "guardar_colores", "paleta": "azul_acero", "color_ink": "#0F1B2D", "color_brand": "#1B6FA8", "color_brand_dark": "#124F79" }
```
Los 3 colores deben ser hex de 6 dígitos (`#RRGGBB`) — `422` si no.
```
POST api/apariencia.php  (multipart/form-data: accion=subir_logo, logo=<archivo>)
```
Solo PNG, máx. 1 MB — `422` si no. Borra el logo anterior si había uno.
```json
{ "accion": "quitar_logo" }
```
Borra el archivo actual y regresa `logo_path` a `null`.

## Login y roles (dinámicos)


Los roles **ya no son 4 fijos en código** — viven en la base de datos
(tablas `roles` y `rol_modulos`), administrables desde la pantalla
**Roles** (`roles.php`, exclusiva de admin). La primera vez que se crea la
base de datos, se siembran los 4 roles originales con exactamente los
mismos permisos que tenían codificados antes, así que actualizar no cambia
nada para una instalación que ya estaba corriendo:

| Rol (sembrado) | Módulos de fábrica |
|---|---|
| **admin** | Todos, siempre — bypass fijo en código, no depende de `rol_modulos`. |
| **contador** | Dashboard, Contribuyentes/CSD, Timbrado, Tareas, Inventario, Ventas. |
| **lider_proyecto** | Tareas, Clientes, Inventario, Ventas. |
| **colaborador** | Tareas, Inventario, Ventas. |

Desde **Roles** puedes: crear roles nuevos (con su propio checklist de
módulos), editar a qué módulos tiene acceso cualquier rol —incluidos los 4
originales—, renombrarlos, y eliminar los que tú creaste (no los
originales, y no si todavía tienen usuarios). La `clave` de un rol
(el valor que se guarda en `usuarios.rol`) es **inmutable** una vez creado
— cambiarla dejaría huérfanos a los usuarios que ya la tienen; lo que sí
se puede cambiar después es el nombre visible y los módulos.

Tres módulos son especiales y **no aparecen en el checklist** de Roles
(`timbra_puede()` en `api/lib/auth.php` los resuelve aparte, no consultan
`rol_modulos`):
- **usuarios** y **roles**: exclusivos de `admin` siempre — así ningún rol
  se puede conceder a sí mismo más acceso ni crear una escalada de
  privilegios.
- **perfil**: universal — cualquier sesión lo tiene, sin importar el rol.

Cada vista PHP llama `timbra_requerir_modulo('nombre_modulo')` al inicio
(redirige a `login.php` si no hay sesión, o al módulo de inicio del usuario
si su rol no aplica — el primero de `TIMBRA_MODULOS_ORDEN` al que tenga
acceso). Cada endpoint de `/api` llama `timbra_requerir_modulo_api(...)`,
que responde `401`/`403` en JSON en vez de redirigir.

**Importante — límite de este sistema:** el checklist de Roles controla
qué *pantallas* puede abrir un rol (acceso a módulos). No controla reglas
de negocio más finas que siguen ancladas a las claves originales por
código, como:
- Quién puede **crear proyectos/etapas** (`admin` o `lider_proyecto`,
  hardcodeado en `api/proyectos.php`/`api/etapas.php`).
- Quién **aprueba** un cambio de estado de tarea o edita/elimina
  proyectos-etapas-tareas ajenas (`admin`, o el `lider_proyecto` dueño del
  proyecto — ver `timbra_es_aprobador()` en `api/tareas.php`).

Un rol nuevo con acceso al módulo Tareas puede **verlas y que le asignen
trabajo** (`timbra_roles_con_modulo($pdo, 'tareas')` ya lo resuelve
dinámicamente), pero no va a poder crear proyectos ni aprobar cambios de
estado a menos que sea literalmente `admin` o `lider_proyecto`. Extender
esas reglas a roles arbitrarios es un cambio de diseño más grande (pasar de
"rol fijo" a un concepto de "dueño"/"aprobador" por proyecto) que no está
incluido aquí — avísame si lo quieres y lo construimos.

Dentro de Tareas, un `colaborador`/`contador` (o cualquier rol sin
capacidad de gestión) nunca puede ver ni tocar tareas ajenas, sin importar
qué `proyecto_id` mande al endpoint — el filtro se fuerza en el servidor
(`api/tareas.php`), no solo se oculta en la interfaz. La visibilidad de
proyectos vive en `timbra_puede_ver_proyecto()` (`api/lib/helpers.php`).

El módulo **Usuarios** (`usuarios.php` / `api/usuarios.php`) es exclusivo de
`admin`: crea cuentas, cambia el rol de alguien (elegido de la lista
dinámica de roles) o las activa/desactiva.

### GET api/roles.php  (admin)
```json
[{ "id": 2, "clave": "contador", "nombre": "Contador", "es_sistema": 1,
   "num_usuarios": 3, "modulos": ["dashboard", "contribuyentes", "timbrado", "tareas", "inventario", "ventas"] }]
```
### POST api/roles.php  (admin)
```json
{ "accion": "crear", "clave": "supervisor_ventas", "nombre": "Supervisor de ventas", "modulos": ["ventas", "productos_servicios", "finanzas"] }
{ "accion": "editar_permisos", "id": 5, "modulos": ["ventas"] }
{ "accion": "renombrar", "id": 5, "nombre": "Supervisor comercial" }
{ "accion": "eliminar", "id": 5 }
```
`clave` debe ser única, minúsculas/números/guion bajo (regex
`^[a-z][a-z0-9_]{2,30}$`), y es inmutable después de crear el rol.
`modulos` se filtra contra `TIMBRA_MODULOS_ASIGNABLES` — nunca se puede
colar `usuarios` o `roles` en la lista, ni desde el navegador. `eliminar`
falla con `422` si el rol es de sistema o si todavía tiene usuarios.

## Estructura

```
index.php                    redirige a login.php o al módulo de inicio del usuario
manifest.json                 manifest de la PWA (nombre, íconos, colores)
service-worker.js             cachea solo /assets/, nunca páginas ni /api/
offline.html                  respaldo mostrado al navegar sin conexión
assets/icons/                 íconos de la PWA en todos los tamaños (48–512px + maskable)
.well-known/assetlinks.json   verificación de la TWA (se completa al generar la app)
verificar-servidor.php        diagnóstico de un solo uso al desplegar (bórralo después)
login.php / logout.php      autenticación
dashboard.php                Panel de control (KPIs + alertas tempranas)      [contador, admin]
alta-contribuyente.php       Alta de contribuyente y CSD                     [contador, admin]
timbrado.php                 Timbrado individual y masivo                    [contador, admin]
tareas.php                   Lista de proyectos (?) o etapas+tablero de uno (?id=N)   [lider_proyecto, colaborador, contador, admin]
exportar-proyecto.php        Descarga el diagrama de Gantt en .xlsx           [admin, lider_proyecto dueño]
clientes.php                 Alta y lista de clientes                        [lider_proyecto, admin]
perfil.php                   "Mi usuario": editar nombre/foto y contraseña    [todos los roles]
inventario.php               Catálogo, solicitudes y (admin) aprobaciones    [todos los roles]
ventas.php                   Registrar ventas de productos/servicios         [todos los roles]
productos-servicios.php      Catálogo de lo que se vende                     [admin]
finanzas.php                 KPIs, tendencia, top clientes y cobranza        [admin]
usuarios.php                 Alta de cuentas y cambio de rol                 [admin]
roles.php                    Crear roles y asignar módulos por rol           [admin]
apariencia.php               Logo y colores del sistema                      [admin]
agenda.php                   Calendario de citas y tareas pendientes         [todos los roles]
leads.php                    Tabla de leads, asignación y (admin) fuentes    [admin, lider_proyecto, colaborador]
ticket-venta.php             Ticket imprimible de una venta                  [ventas]
ticket-proyecto.php          Ticket imprimible de cobros de un proyecto      [tareas, según visibilidad del proyecto]

includes/                   layout compartido (sidebar filtrado por rol, topbar)
assets/css/theme.css         sistema de diseño (incluye el tablero kanban)
assets/css/ticket.css         estilos de las páginas de ticket imprimibles
assets/js/*.js                lógica de cada vista (fetch a /api)

api/
  config.php                 constantes, catálogo de roles y matriz de acceso a módulos
  db.php                      conexión PDO/SQLite + migración de tablas + siembra del admin
  lib/helpers.php              validaciones, respuestas JSON, catálogos
  lib/Csd.php                  validación real de .cer/.key con OpenSSL
  lib/auth.php                  sesión, login, timbra_requerir_modulo()/_api()
  auth.php                    GET whoami+módulos / POST login,logout      [público — es el punto de entrada]
  procesar-empresa.php         POST alta de contribuyente/CSD (multipart)  [contribuyentes]
  pac-status.php               GET  estado del PAC                                  [dashboard|timbrado]
  kpis.php                     GET  KPIs del dashboard                              [dashboard]
  alertas.php                  GET  alertas tempranas                               [dashboard]
  alertas-ack.php               POST descarta una alerta                            [dashboard]
  timbrar-individual.php        POST timbre de prueba                               [timbrado]
  timbrar-lote.php               POST sube un CSV y crea el lote                    [timbrado]
  lote-estado.php                GET  procesa el siguiente bloque y reporta avance  [timbrado]
  usuarios.php                   GET/POST administra cuentas y roles                [admin]
  roles.php                      GET/POST crea roles, permisos, renombra, elimina   [admin]
  apariencia.php                 GET/POST logo, colores y paleta                    [admin]
  citas.php                      GET/POST CRUD de citas + invitaciones              [agenda]
  agenda.php                     GET combina citas + tareas pendientes por rango    [agenda]
  leads.php                      GET/POST lista, simula, asigna, cambia estado      [leads]
  leads-config.php               GET/POST activa fuentes (Meta/LinkedIn)            [admin]
  usuarios-asignables.php         GET  colaborador/contador/líder activos, para "Asignar a" [lider_proyecto, admin]
  etapas.php                      GET/POST lista/crea etapas de un proyecto          [tareas, dueño para crear]
  costos-extra.php                GET/POST costos extra de un proyecto externo       [tareas, dueño para crear]
  proyecto-abonos.php             GET/POST abonos de un proyecto externo             [tareas, dueño para crear/eliminar]
  perfil.php                      GET/POST perfil propio: nombre, foto, contraseña   [cualquier sesión]
  inventario-items.php            GET catálogo / POST alta,editar,baja (admin)       [inventario]
  inventario-solicitudes.php      GET/POST solicitar, aprobar, rechazar, devolver    [inventario]
  ventas.php                      GET/POST listar/ver detalle, registrar venta       [ventas]
  productos-servicios.php         GET catálogo (vía ventas) / POST CRUD (admin)      [ventas para leer, admin para escribir]
  finanzas.php                    GET KPIs/tendencia/detalle / POST marcar cobrado   [admin]
  lib/XlsxWriter.php               generador mínimo de .xlsx (sin librerías externas, usa ZipArchive)
  clientes.php                    GET/POST lista/registra clientes                   [clientes]
  proyectos.php                   GET/POST lista/crea proyectos                     [tareas]
  tareas.php                       GET/POST lista/crea/cambia estado de tareas      [tareas, con filtro por dueño]

storage/
  timbra.sqlite                base de datos (se crea sola, con el admin sembrado)
  csd/{RFC}/certificado.cer     CSD guardados por contribuyente
  csd/{RFC}/llave.key
  .htaccess                     bloquea el acceso HTTP directo (Apache)

samples/lote-ejemplo.csv      CSV de prueba para la carga masiva
```

Si el servidor no es Apache, replica el bloqueo de `/storage/` en tu
configuración (por ejemplo, en Nginx: `location /storage/ { deny all; }`),
y en producción mueve esa carpeta fuera del webroot por completo.

## Qué es real y qué es un punto de extensión

- **Validación del CSD (.cer/.key)** en `api/procesar-empresa.php`: **real**.
  Usa `openssl_pkey_get_private()`/`openssl_x509_parse()` sobre el DER
  envuelto en PEM (misma técnica que usan las librerías CFDI open-source en
  PHP) para confirmar que la contraseña abre la llave, que el certificado
  esté vigente, que el par cert/llave coincida y que el RFC del certificado
  concuerde con el capturado.
- **Validación estructural del CFDI** (RFC, catálogos, importes) en
  `timbrar-individual.php` y `lote-estado.php`: **real**, pero es solo
  estructura — no es una validación fiscal ante el SAT/PAC.
- **Timbrado real ante el PAC**: **pendiente de conectar**. Los archivos
  marcan con un comentario exacto dónde insertar la llamada al SDK/API de tu
  PAC; mientras tanto se genera un folio/UUID de prueba tras pasar la
  validación estructural, para que el flujo completo sea probable de punta a
  punta.
- **Estado del PAC** (`api/pac-status.php`): si configuras
  `TIMBRA_PAC_HEALTHCHECK_URL` en `api/config.php`, hace una verificación
  HTTP real y clasifica por latencia; si la dejas vacía, reporta un latido
  local (no inventa una verificación remota) y lo indica con
  `"verificado_contra": "sin_configurar"`.
- **Procesamiento del lote**: cada llamada a `lote-estado.php` procesa hasta
  `TIMBRA_FILAS_POR_TICK` filas pendientes (el polling del front actúa como
  motor). Para lotes grandes en producción, sustituye este patrón por un
  worker de cola o una tarea de cron independiente de la pantalla abierta.

## Contratos JSON (para referencia rápida del front)

### GET api/pac-status.php
```json
{ "estado": "ok", "latencia_ms": 240, "verificado_contra": "sin_configurar" }
```

### GET api/kpis.php
```json
{
  "exitosos_hoy": 12, "rechazados_hoy": 2,
  "folios_saldo": 4988, "folios_total": 5000,
  "pac_estado": "ok", "pac_latencia_ms": 12
}
```

### GET api/alertas.php
```json
[{ "id": "racha_rechazos", "severidad": "danger", "mensaje": "...", "detalle": "...", "timestamp": "hace 12 min" }]
```

### POST api/alertas-ack.php
Body `{ "id": "racha_rechazos" }` → `{ "success": true }`

### POST api/procesar-empresa.php (multipart/form-data)
Campos: `rfc`, `razon_social`, `regimen_fiscal`, `archivo_cer`, `archivo_key`, `password_csd`
→ `{ "success": true, "message": "...", "folio_tenant": "TEN-0001" }`
→ `{ "success": false, "message": "...", "errors": { "password_csd": "..." }, "code": "CSD_PASSWORD_INVALIDA" }`

### POST api/timbrar-individual.php (JSON)
Body: `{ rfc_receptor, razon_receptor, uso_cfdi, total, metodo_pago }`
→ `{ "success": true, "uuid": "...", "folio": "F-000123", "message": "..." }`
→ `{ "success": false, "message": "...", "code": "CFDI33101" }`

### POST api/timbrar-lote.php (multipart/form-data, campo archivo_csv)
CSV con encabezado: `rfc_receptor,razon_receptor,uso_cfdi,total,metodo_pago`
→ `{ "lote_id": "LOTE-...", "total_filas": 480 }`

### GET api/lote-estado.php?lote_id=LOTE-...
```json
{
  "procesados": 210, "total": 480, "exitosos": 198, "rechazados": 12,
  "terminado": false,
  "errores": [{ "fila": 34, "rfc": "...", "codigo": "CFDI33130", "motivo": "..." }]
}
```

### GET api/usuarios.php  (admin)
```json
[{ "id": 2, "nombre": "Ana Ruiz", "email": "ana@empresa.mx", "rol": "colaborador", "activo": 1, "created_at": "..." }]
```
### POST api/usuarios.php  (admin)
```json
{ "accion": "crear", "nombre": "Ana Ruiz", "email": "ana@empresa.mx", "password": "········", "rol": "colaborador" }
{ "accion": "editar", "id": 2, "nombre": "Ana Ruiz Gómez", "email": "ana@empresa.mx", "password": "" }
{ "accion": "cambiar_rol", "id": 2, "rol": "lider_proyecto" }
{ "accion": "toggle_activo", "id": 2 }
```
`editar` corrige nombre/correo; `password` es opcional — mándalo vacío
para no tocarla, o con 8+ caracteres para resetearla. Valida que el correo
no choque con el de otro usuario.

### GET api/proyectos.php
Lista **filtrada según el rol** (ver tabla de visibilidad más arriba):
```json
[{ "id": 1, "nombre": "Migración CFDI 4.0", "descripcion": "...",
   "fecha_inicio": "2026-08-01", "fecha_fin_tentativa": "2026-09-15",
   "tipo": "externo", "costo": 25000.00, "cliente_id": 3, "cliente_nombre": "Grupo Acme",
   "creado_por": 3, "creado_por_nombre": "Luis Peña",
   "total_tareas": 8, "tareas_completadas": 3, "total_etapas": 3, "total_costos_extra": 1500.00 }]
```
### GET api/proyectos.php?id=1
Igual, pero un solo objeto — `404` si no existe, `403` si el usuario no
puede verlo (`timbra_puede_ver_proyecto()`).

### POST api/proyectos.php  (admin / lider_proyecto)
Body: `{ "nombre": "...", "descripcion": "...", "tipo": "externo", "costo": 25000.00, "cliente_id": 3, "fecha_inicio": "2026-08-01", "fecha_fin_tentativa": "2026-09-15" }`

`tipo` es `interno` o `externo` (obligatorio). Regla de negocio, validada en
el backend:
- `interno` → **no puede** llevar `costo` (se rechaza si lo mandas). Tampoco lleva cliente.
- `externo` → **requiere** `costo` numérico mayor a cero **y** `cliente_id`
  de un cliente activo ya registrado en `api/clientes.php`.

Fechas opcionales; si mandas ambas, fin no puede ser anterior a inicio.

### GET api/clientes.php  (admin / lider_proyecto)
```json
[{ "id": 3, "nombre": "Grupo Acme", "contacto_nombre": "...", "email": "...",
   "telefono": "...", "notas": "...", "total_proyectos": 2 }]
```
### POST api/clientes.php  (admin / lider_proyecto)
Body: `{ "nombre": "...", "contacto_nombre": "...", "email": "...", "telefono": "...", "notas": "..." }`
(solo `nombre` es obligatorio)

### GET api/costos-extra.php?proyecto_id=1
Solo aplica a proyectos externos. Mismas reglas de acceso que las tareas del proyecto.
```json
[{ "id": 9, "descripcion": "Toma extra solicitada por el cliente", "monto": 1500.00,
   "creado_por_nombre": "Luis Peña", "created_at": "2026-08-10 12:00:00" }]
```
### POST api/costos-extra.php  (admin, o lider_proyecto dueño del proyecto)
Body: `{ "proyecto_id": 1, "descripcion": "...", "monto": 1500.00 }` — se
rechaza con `422` si el proyecto es `interno` (los internos no llevan costo,
tampoco costos extra).

### GET api/proyecto-abonos.php?proyecto_id=1
Control de lo que se ha pagado de un proyecto externo, con abonos
individuales en vez de un simple sí/no. Mismas reglas de acceso que las
tareas del proyecto.
```json
{
  "total_proyecto": 26500.00, "total_abonado": 15000.00, "saldo": 11500.00,
  "abonos": [{ "id": 3, "monto": 10000.00, "fecha": "2026-08-01",
    "metodo_pago": "Transferencia", "notas": "Anticipo", "registrado_por_nombre": "Luis Peña", "created_at": "..." }]
}
```
### POST api/proyecto-abonos.php  (admin, o lider_proyecto dueño del proyecto)
```json
{ "accion": "crear", "proyecto_id": 1, "monto": 5000.00, "fecha": "2026-08-12", "metodo_pago": "Efectivo", "notas": "..." }
{ "accion": "eliminar", "id": 3 }
```
`422` si el proyecto es `interno`. `metodo_pago` y `notas` son opcionales.
Eliminar es un borrado real (a diferencia de proyectos/etapas/tareas) — es
para corregir un abono capturado por error, no un historial que quieras
conservar oculto.

### GET api/etapas.php?proyecto_id=1
```json
[{ "id": 4, "proyecto_id": 1, "nombre": "Grabar los videos en las locaciones",
   "orden": 1, "total_tareas": 5, "tareas_completadas": 2 }]
```
Mismas reglas de acceso que `api/proyectos.php?id=`.

### POST api/etapas.php  (admin, o lider_proyecto dueño del proyecto)
Body: `{ "proyecto_id": 1, "nombre": "...", "orden": 1 }` — `orden` es
opcional (si se omite, se asigna el siguiente consecutivo del proyecto).

### GET api/tareas.php?proyecto_id=1
`proyecto_id` es **obligatorio** (las tareas siempre se consultan dentro de
un proyecto). `403` si el usuario no tiene acceso a ese proyecto. Cada tarea
incluye `etapa_id` para que el front la agrupe bajo su etapa correspondiente.
```json
[{ "id": 5, "proyecto_id": 1, "etapa_id": 4, "titulo": "...", "descripcion": "...",
   "asignado_a": 2, "asignado_nombre": "Ana Ruiz", "asignado_rol": "colaborador",
   "prioridad": "alta", "estado": "en_progreso", "fecha_inicio": "2026-08-10", "fecha_fin": "2026-08-20",
   "estado_propuesto": "completada", "propuesto_por": 2, "propuesto_por_nombre": "Ana Ruiz", "propuesto_en": "..." }]
```
`estado_propuesto` es `null` cuando no hay ningún cambio pendiente. Si
quien pregunta es `contador` o `colaborador`, el backend agrega
`AND asignado_a = <su id>` sin importar nada más — dentro de un proyecto al
que sí tiene acceso, solo ve sus propias tareas, nunca las de sus
compañeros.

### POST api/tareas.php  accion=crear  (admin, o lider_proyecto dueño del proyecto)
```json
{ "accion": "crear", "etapa_id": 4, "titulo": "...", "descripcion": "...",
  "asignado_a": 2, "prioridad": "alta", "fecha_inicio": "2026-08-10", "fecha_fin": "2026-08-20" }
```
`etapa_id`, `asignado_a`, `fecha_inicio` y `fecha_fin` son **todos
obligatorios** — sin una etapa válida, sin alguien responsable o sin ambas
fechas (con `fecha_fin` no anterior a `fecha_inicio`), el backend rechaza
la creación con `422` (no solo la interfaz lo exige). `asignado_a` debe ser
un usuario **activo** con
rol `colaborador`, `contador` o `lider_proyecto` (nunca `admin`). El
`proyecto_id` se deriva de la etapa, no se manda aparte.

### POST api/tareas.php  accion=editar / eliminar  (admin, o lider_proyecto dueño)
```json
{ "accion": "editar", "id": 5, "titulo": "...", "descripcion": "...",
  "asignado_a": 2, "prioridad": "alta", "fecha_inicio": "2026-08-10", "fecha_fin": "2026-08-20", "etapa_id": 7 }
```
Mismas validaciones que crear (título y `asignado_a` obligatorios). `etapa_id`
es opcional — si se manda, mueve la tarea a otra etapa del mismo proyecto.
```json
{ "accion": "eliminar", "id": 5 }
```
Borrado suave (`eliminado = 1`); la tarea deja de aparecer en cualquier
consulta pero no se borra de la base de datos.

### POST api/proyectos.php / api/etapas.php  accion=editar / eliminar
Mismo patrón en ambos endpoints — `{ "accion": "editar", "id": N, ... }` con
los mismos campos que la creación, o `{ "accion": "eliminar", "id": N }`.
Solo admin o el `lider_proyecto` dueño del proyecto correspondiente.
Eliminar un proyecto lo archiva (`archivado = 1`); eliminar una etapa se
**rechaza con 422** si todavía tiene tareas activas.

### Flujo de cambio de estado — solicitud y aprobación

La persona asignada **nunca** cambia el estado directamente: solo puede
**proponer** uno nuevo. El admin o el líder dueño del proyecto lo aprueban
o lo rechazan (o cambian el estado ellos mismos sin pasar por aprobación).

```json
{ "accion": "solicitar_cambio_estado", "id": 5, "estado": "completada" }
```
Solo la persona en `asignado_a` de esa tarea puede llamarlo (`403` para
cualquier otro, incluido el líder o el admin). Guarda el cambio en
`estado_propuesto` — `estado` (el oficial) no se toca todavía.

```json
{ "accion": "aprobar_cambio_estado", "id": 5 }
{ "accion": "rechazar_cambio_estado", "id": 5 }
```
Solo admin o el líder dueño del proyecto (`403` para cualquier otro,
incluida la propia persona asignada). Aprobar copia `estado_propuesto` a
`estado` y limpia la propuesta; rechazar solo limpia la propuesta, sin
tocar `estado`. `422` si la tarea no tiene ninguna propuesta pendiente.

```json
{ "accion": "cambiar_estado", "id": 5, "estado": "completada" }
```
Cambio directo, sin pasar por aprobación — **solo** admin o el líder dueño
del proyecto (ya no incluye a la persona asignada; para eso existe
`solicitar_cambio_estado`).

### GET api/usuarios-asignables.php  (admin / lider_proyecto)
```json
[{ "id": 2, "nombre": "Ana Ruiz", "rol": "colaborador" }]
```
Solo usuarios activos con rol `colaborador`, `contador` o `lider_proyecto`.

## Desplegar en tu servidor

Un punto importante: **no subas solo la carpeta `api/`** — aunque ahí vive
toda la lógica, `api/config.php` calcula rutas como `storage/` y
`assets/avatars/` **un nivel arriba** de `api/` (`TIMBRA_ROOT = dirname(__DIR__)`),
y las páginas `.php` de la raíz (`dashboard.php`, `tareas.php`, etc.) son
lo que sirve la web. Sube el **proyecto completo tal cual está**, en un
solo lugar — `api/` es la lógica, pero necesita a su lado el resto.

### Si tu hosting es cPanel

- **Subir los archivos**: la forma más rápida es *File Manager* → sube el
  `.zip` del proyecto completo → clic derecho → **Extract**. Mucho más
  rápido que arrastrar cientos de archivos por FTP uno por uno.
- **A dónde**: si el sitio va en el dominio principal de esa cuenta, es
  `public_html/`. Si es un dominio adicional o subdominio, cPanel ya te
  asignó su propia carpeta (revisa en *Domains* o *Subdomains* cuál es) —
  sube ahí, no en `public_html/`.
- **Versión de PHP**: *Select PHP Version* (a veces aparece como *MultiPHP
  Manager*) → elige 8.1 u 8.2 para esa cuenta/dominio. Ahí mismo, en la
  lista de extensiones con casillas, confirma que `pdo_sqlite`, `openssl`,
  `zip` y `fileinfo` estén marcadas (`openssl` y `fileinfo` casi siempre
  vienen activas de fábrica; `pdo_sqlite`/`zip` a veces hay que
  marcarlas tú).
- **HTTPS**: *SSL/TLS Status* → si no está activo, corre *Run AutoSSL*
  (Let's Encrypt gratis, cPanel lo renueva solo). En esa misma pantalla
  suele haber un botón para forzar la redirección a HTTPS — actívalo.
- **Permisos**: en cPanel, PHP casi siempre corre como el dueño de la
  cuenta (no como "nobody"), así que `storage/`, `assets/avatars/` y `assets/branding/`
  normalmente ya son escribibles apenas subes los archivos. Si
  `verificar-servidor.php` marca algo en rojo ahí, entra a *File Manager*
  → clic derecho sobre la carpeta → *Change Permissions* → 755 (o 775 si
  755 no basta).
- **`.htaccess`**: cPanel trae `AllowOverride All` activado por default
  en las cuentas de los usuarios, así que `storage/.htaccess` debería
  funcionar sin que hagas nada más — pero de todas formas confirma
  entrando directo a `https://tudominio.com/storage/timbra.sqlite` en el
  navegador: **debe darte error 403**, nunca descargar el archivo.
- No necesitas crear nada en *MySQL Databases* — la app usa SQLite (un
  solo archivo), no un servidor de base de datos aparte.

### 1. Requisitos del servidor
Ya están detallados arriba en "Requisitos del servidor" — PHP 8+, las
extensiones (`pdo_sqlite`, `openssl`, `zip`, `fileinfo`), y permisos de
escritura en `storage/`, `assets/avatars/` y `assets/branding/`. Sube todo y abre
**`verificar-servidor.php`** en el navegador — te dice en un vistazo si
falta algo, antes de que pierdas tiempo probando módulo por módulo.
Bórralo del servidor en cuanto todo salga en verde.

### 2. HTTPS
Obligatorio, no opcional — lo necesitas tanto para que las sesiones sean
seguras como para que Chrome deje instalar la PWA (ver la sección de
Android más arriba). La mayoría de los hostings contratados ya traen
Let's Encrypt gratis desde el panel; actívalo antes de abrir el sitio la
primera vez.

### 3. ¿Dominio raíz o subcarpeta?
Si vas a servir el sitio en la raíz del dominio (`https://tudominio.com/`),
no hay que tocar nada. Si en cambio va en una subcarpeta
(`https://tudominio.com/erp/`), ajusta **`manifest.json`**: cambia
`"start_url": "/index.php?source=pwa"` y `"scope": "/"` para que
incluyan esa subcarpeta (`"/erp/index.php?source=pwa"` y `"/erp/"`) — si
no, la PWA intentará abrir la app en la raíz del dominio y no la va a
encontrar.

### 4. Primer arranque
La primera petición a cualquier página crea sola `storage/timbra.sqlite`
con todas las tablas y siembra el usuario administrador:
```
correo:     admin@m-core.local
contraseña: CambiaEstaClave123
```
Entra y **cámbiala de inmediato** desde "Mi usuario", o crea tu propio
admin desde Usuarios y desactiva este.

### 5. Bloquear `/storage/` si no usas Apache
`storage/.htaccess` (con `Require all denied`) solo protege en Apache con
`AllowOverride All`. Si tu servidor usa Nginx u otro, agrega el bloqueo
equivalente en su configuración — por ejemplo en Nginx:
```nginx
location /storage/ { deny all; return 404; }
```
Sin esto, alguien podría descargar `storage/timbra.sqlite` directo por
HTTP — con Apache y el `.htaccess` de por sí incluido no hace falta nada
más.

## Migraciones sobre una base ya existente

`api/db.php` usa `CREATE TABLE IF NOT EXISTS` para las tablas nuevas y, para
columnas agregadas a una tabla que ya existía (como `fecha_inicio` y
`fecha_fin_tentativa` en `proyectos`), revisa con `PRAGMA table_info` y
aplica `ALTER TABLE ... ADD COLUMN` solo si falta. Esto significa que puedes
reemplazar los archivos del proyecto por esta versión sin perder los
usuarios, proyectos o tareas que ya hayas creado — la primera petición
después de actualizar ajusta el esquema sola.

Un ejemplo concreto de este patrón: las tareas antes solo tenían
`fecha_limite`; ahora tienen `fecha_inicio` y `fecha_fin` (ambas
obligatorias al crear una tarea nueva). Al agregar las columnas, `db.php`
copia una sola vez el valor que ya tenías en `fecha_limite` hacia
`fecha_fin`, así que las tareas viejas no se quedan sin fecha — les falta
`fecha_inicio` (no había forma de inventar ese dato), y el front lo muestra
como `¿?` hasta que la edites.

## Exportar el diagrama de Gantt a Excel

`exportar-proyecto.php?id=N` genera el `.xlsx` al vuelo con `api/lib/XlsxWriter.php`
— una clase propia de ~200 líneas que arma el archivo OOXML directamente
con `ZipArchive` (ya viene con PHP), sin PhpSpreadsheet ni ninguna otra
dependencia. Solo admin o el `lider_proyecto` dueño de ese proyecto pueden
descargarlo — el botón "Exportar proyecto" en `tareas.php` usa la misma
condición (`$puedeCrearTareaAqui`) que ya protege crear etapas/tareas, y el
propio `exportar-proyecto.php` la vuelve a validar en el servidor.

Cómo arma las fechas de cada barra:
- **Inicio/Fin** = `fecha_inicio`/`fecha_fin` reales de la tarea (campos obligatorios al crearla). Una tarea muy vieja migrada desde antes de que existieran esos campos cae al día en que se creó como respaldo.
- El color de la barra sigue el estado: gris-azulado (pendiente), ámbar (en
  progreso), verde-sello (completada) — la misma paleta del front.
- El rango completo del Gantt (columnas de fecha) sale de la fecha más
  temprana y más tardía entre las del proyecto y las de sus tareas, con un
  tope de 180 días para no generar un archivo gigante; si se trunca, se
  anota en el propio archivo.

## Inventario interno

Pensado para equipo interno (diademas, mouses, etc. en un call center, por
ejemplo) — no tiene relación con los clientes ni con el timbrado.

- **Cualquier rol** puede ver el catálogo y solicitar artículos; cada
  quien ve solo **sus propias** solicitudes en "Mis solicitudes".
  Asumí que cualquier colaborador de la empresa (no solo el rol
  `colaborador`) puede solicitar equipo — ajusta `TIMBRA_MODULOS['inventario']`
  en `api/config.php` si lo quieres más restringido.
- **Solo admin** puede: dar de alta/editar/dar de baja artículos, aprobar o
  rechazar solicitudes, y marcar un préstamo como devuelto.
- Cada solicitud registra: quién la pidió, qué y cuánto, fecha y hora
  exactas (`fecha_solicitud`), quién la aprobó y cuándo (`aprobado_por`,
  `fecha_aprobacion`), y si ya se devolvió (`devuelto`, `fecha_devolucion`).
- Las existencias se controlan solas: al **aprobar** se descuenta
  `cantidad_disponible` (rechaza si no alcanza); al **marcar como
  devuelto** se regresa esa cantidad. Rechazar una solicitud no toca
  existencias, porque nunca se habían descontado.
- Dar de baja un artículo es reversible (botón "Reactivar") y no borra su
  historial de préstamos. Editar la cantidad total valida que no la bajes
  por debajo de lo que hay prestado sin devolver en ese momento.

### GET api/inventario-items.php
`?todos=1` (solo tiene efecto real para admin) incluye también los dados
de baja; sin ese parámetro, solo artículos activos.
```json
[{ "id": 1, "nombre": "Diadema USB", "descripcion": "...", "cantidad_total": 20, "cantidad_disponible": 14, "activo": 1 }]
```
### POST api/inventario-items.php  (admin)
```json
{ "accion": "crear", "nombre": "...", "descripcion": "...", "cantidad_total": 20 }
{ "accion": "editar", "id": 1, "nombre": "...", "descripcion": "...", "cantidad_total": 25 }
{ "accion": "baja", "id": 1 }
{ "accion": "reactivar", "id": 1 }
```

### GET api/inventario-solicitudes.php
Sin parámetros: solo las solicitudes del usuario en sesión.
`?pendientes=1` o `?todas=1` (ambos solo admin): todas las solicitudes.
```json
[{ "id": 9, "item_id": 1, "item_nombre": "Diadema USB",
   "solicitado_por": 4, "solicitado_por_nombre": "Ana Ruiz", "cantidad": 1,
   "estado": "aprobado", "fecha_solicitud": "2026-08-10 09:15:00",
   "aprobado_por": 1, "aprobado_por_nombre": "Administrador", "fecha_aprobacion": "...",
   "devuelto": 0, "fecha_devolucion": null, "notas": "..." }]
```
### POST api/inventario-solicitudes.php
```json
{ "accion": "solicitar", "item_id": 1, "cantidad": 1, "notas": "..." }
```
Cualquier rol. `422` si no hay artículo válido o la cantidad es menor a 1
(no valida existencias todavía — eso se checa al aprobar).
```json
{ "accion": "aprobar", "id": 9 }
{ "accion": "rechazar", "id": 9 }
{ "accion": "marcar_devuelto", "id": 9 }
```
Las tres exclusivas de admin. `aprobar` falla con `422` si ya no hay
existencias suficientes; `marcar_devuelto` solo aplica a solicitudes ya
`aprobado` y todavía no devueltas.

## Finanzas

Exclusivo de admin. Junta en un solo lugar las dos fuentes de ingresos del
sistema — no calcula nada nuevo, solo suma lo que ya existe en Proyectos y
en Ventas:

- **Ingresos por proyectos** = costo base + costos extra de cada proyecto
  `externo` no archivado.
- **Ingresos por ventas** = el total de cada venta registrada.

Encima de la suma simple, agregué lo que me pareció necesario para que
esto sirva como una vista financiera de verdad y no solo un sumador:

- **Filtro de periodo** (este mes / mes pasado / este año / todo el
  historial) para no mezclar meses distintos en el mismo número.
- **Cobrado vs. pendiente de cobro**: para **ventas**, sigue siendo un
  interruptor manual (columna `cobrado`) — tú lo marcas cuando el dinero
  entra. Para **proyectos**, se calcula solo sumando sus **abonos reales**
  (ver el módulo de Abonos más abajo) — más preciso que un simple sí/no,
  ya que un proyecto casi siempre se cobra en pagos parciales. Esto separa
  "lo que se ha generado" de "lo que ya se cobró", que normalmente son
  números distintos en cualquier negocio.
- **Tendencia de los últimos 6 meses** (proyectos vs. ventas, apilados),
  para ver si el negocio va para arriba o para abajo — independiente del
  filtro de periodo, siempre muestra la misma ventana de 6 meses.
- **Top clientes** por ingresos combinados (proyectos + ventas), dentro
  del periodo filtrado — útil para ver quién le genera más a la empresa.
- **Ticket promedio de venta**, un indicador estándar de negocio que no
  existía en ningún otro módulo.

### GET api/finanzas.php?periodo=mes_actual|mes_pasado|anio_actual|todo
```json
{
  "periodo": { "preset": "mes_actual", "desde": "2026-08-01", "hasta": "2026-08-31", "label": "Este mes" },
  "kpis": {
    "ingresos_proyectos": 45000, "ingresos_ventas": 12500, "ingresos_totales": 57500,
    "cobrado_total": 30000, "pendiente_total": 27500,
    "num_proyectos": 3, "num_ventas": 9, "ticket_promedio_venta": 1388.89
  },
  "ingresos_por_mes": [{ "mes": "2026-03", "proyectos": 0, "ventas": 4200, "total": 4200 }],
  "top_clientes": [{ "cliente_nombre": "Grupo Acme", "total": 28000 }],
  "proyectos": [{ "id": 1, "nombre": "...", "cliente_nombre": "...", "costo": 25000, "extras": 1500, "total": 26500, "cobrado": 0, "created_at": "..." }],
  "ventas": [{ "id": 12, "cliente_nombre": "...", "vendido_por_nombre": "...", "total": 799, "cobrado": 1, "created_at": "..." }]
}
```
### POST api/finanzas.php
```json
{ "accion": "marcar_cobrado_proyecto", "id": 1 }
{ "accion": "marcar_cobrado_venta", "id": 12 }
```
Ambas alternan (toggle) el estado `cobrado` — llamarlo dos veces lo
regresa a "pendiente". Exclusivas de admin.

## Abonos (control de pagos de un proyecto)

Dentro de cada proyecto **externo** (`tareas.php?id=N`), junto a la sección
Costos, hay una sección **Abonos** — para llevar el control de los pagos
parciales que va haciendo el cliente, en vez de un simple "ya me pagaron
sí/no".

- Muestra **Total del proyecto** (costo base + extras), **Abonado**
  (suma de todos los abonos) y **Saldo pendiente**, con una barra de
  progreso visual.
- Cada abono registra: monto, fecha del pago, método de pago (opcional) y
  notas — quién lo registró y cuándo queda guardado solo.
- Solo admin o el `lider_proyecto` dueño del proyecto pueden registrar o
  eliminar abonos (mismo patrón que Costos extra). Eliminar un abono es un
  borrado real, no suave — es para corregir una captura equivocada.
- **Finanzas ya no usa un interruptor manual para "cobrado" de un
  proyecto** — lo calcula sumando sus abonos reales. Si un proyecto se
  cobra en tres pagos a lo largo de un mes, ahora eso se refleja tal cual,
  en vez de un solo clic de "ya cobré todo" que no dice cuánto ni cuándo.

## Ventas y Productos/servicios

Ventas para vender algo que **no** pasa por el flujo de Proyectos — por
ejemplo un producto físico o un servicio suelto para un cliente.

- **Productos y servicios** (`productos-servicios.php`) es **solo admin** —
  aquí se da de alta el catálogo (nombre, tipo, precio) que va a aparecer
  disponible para vender. Alta, edición y baja (reversible), mismo patrón
  que Inventario.
- **Ventas** (`ventas.php`) está abierto a **todos los roles** — cualquiera
  puede registrar una venta armando una o varias líneas del catálogo, con
  cliente opcional (tomado de los mismos clientes del módulo Clientes) y
  notas libres. El historial de ventas también es visible para todos (no
  se filtra por quién la registró), ya que es información de ingresos de
  la empresa, no de una tarea personal — si prefieres que cada quien vea
  solo lo que él vendió, es un ajuste sencillo en `api/ventas.php`.
- **El precio nunca lo manda el navegador**: cada línea se resuelve contra
  el catálogo en el servidor en el momento de la venta, así nadie puede
  alterar un total editando el HTML o la petición. El nombre/precio
  quedan "fotografiados" en `venta_items` por si el catálogo cambia
  después — el historial de una venta vieja no cambia de precio con retroactividad.

### GET api/productos-servicios.php
`?todos=1` incluye los dados de baja (uso interno de la pantalla admin).
```json
[{ "id": 1, "nombre": "Diseño de landing page", "descripcion": "...", "tipo": "servicio", "precio": 3500.00, "activo": 1 }]
```
### POST api/productos-servicios.php  (admin)
```json
{ "accion": "crear", "nombre": "...", "descripcion": "...", "tipo": "producto", "precio": 299.00 }
{ "accion": "editar", "id": 1, "nombre": "...", "descripcion": "...", "tipo": "servicio", "precio": 3800.00 }
{ "accion": "baja", "id": 1 }
{ "accion": "reactivar", "id": 1 }
```

### GET api/ventas.php
```json
[{ "id": 12, "cliente_id": 3, "cliente_nombre": "Grupo Acme", "vendido_por": 4,
   "vendido_por_nombre": "Ana Ruiz", "total": 3799.00, "notas": "...", "created_at": "...", "total_lineas": 2 }]
```
### GET api/ventas.php?id=12
Igual, más `"items": [{ "nombre_item", "tipo_item", "precio_unitario", "cantidad", "subtotal" }]`.

### POST api/ventas.php  (cualquier rol)
```json
{ "cliente_id": 3, "notas": "...", "items": [{ "producto_id": 1, "cantidad": 2 }] }
```
`cliente_id` es opcional (`null` = venta sin cliente registrado). `items`
no puede ir vacío; cada `producto_id` debe existir y estar activo en el
catálogo — el precio y el total se calculan en el servidor, ignorando
cualquier precio que llegue en la petición.

### POST api/ventas.php  { "accion": "eliminar", "id": 12 }  (solo admin)
Borra la venta y sus líneas — borrado real (no soft-delete), pensado para
corregir una venta capturada por error, no como historial a conservar
oculto. `403` para cualquier rol que no sea admin; el botón de eliminar
(ícono de bote de basura) solo se dibuja para admin tanto en la tabla como
en el detalle de cada venta.

### POST api/ventas.php  { "accion": "editar", "id": 12, "cliente_id"?, "notas"? }  (solo admin)
Corrige el cliente y/o las notas de una venta ya registrada. A propósito
**no** toca las líneas ni el total — esos ya quedaron resueltos (con
precio "fotografiado") al momento de registrar la venta; si necesitas
cambiar los artículos, elimínala y regístrala de nuevo. Mismo botón de
lápiz visible solo para admin, junto al de eliminar.

## Agenda

Universal — como `perfil`, cualquier sesión la tiene, sin pasar por el
checklist de Roles (`timbra_puede()` la resuelve directo, ver
`api/lib/auth.php`).

Combina dos cosas en un solo calendario mensual:
- **Citas**: las registras tú (título, fecha, hora, cliente opcional,
  descripción) y puedes invitar a otras personas. Quien invitas queda
  "invitado" hasta que él mismo responda desde su Agenda — ni quien creó
  la cita ni nadie más puede aceptar en su nombre.
- **Tareas pendientes**: se leen del módulo de Tareas (`fecha_fin`,
  estado distinto de `completada`), no se duplican en ninguna tabla nueva.

Visibilidad (se resuelve en `api/agenda.php`, no en el front):

| Rol | Ve en su calendario |
|---|---|
| **admin** | Todas las citas y todas las tareas pendientes de la empresa. |
| **lider_proyecto** | Sus propias citas + sus propias tareas asignadas + las tareas de su equipo dentro de **los proyectos que él creó**. |
| **cualquier otro rol** | Sus propias citas (donde participa) + sus propias tareas asignadas. |

### GET api/agenda.php?desde=YYYY-MM-DD&hasta=YYYY-MM-DD
```json
{
  "citas": [{ "id": 3, "titulo": "...", "fecha": "2026-08-12", "hora_inicio": "10:00",
              "hora_fin": "11:00", "cliente_nombre": "...", "creado_por_nombre": "...", "mi_estado": "aceptado" }],
  "tareas": [{ "id": 9, "titulo": "...", "prioridad": "alta", "estado": "en_progreso",
               "fecha_fin": "2026-08-14", "proyecto_id": 1, "proyecto_nombre": "...", "asignado_nombre": "..." }]
}
```
`mi_estado` no aparece para admin (él no necesita responder invitaciones,
ve todo directo).

### GET api/citas.php
Sin parámetros: mis citas (donde participo), sin cancelar.
`?id=5`: detalle con `participantes: [{ usuario_id, nombre, estado }]`.
`?invitaciones_pendientes=1`: solo las citas donde me invitaron y no he
respondido.
`?usuarios_invitables=1`: usuarios activos (menos yo mismo) para el
selector "Invitar a" — cualquier rol puede invitar a cualquier otro rol.

### POST api/citas.php
```json
{ "accion": "crear", "titulo": "...", "descripcion": "...", "cliente_id": 3,
  "fecha": "2026-08-12", "hora_inicio": "10:00", "hora_fin": "11:00", "invitados": [4, 7] }
```
Te agrega a ti automáticamente como participante `aceptado`; cada id en
`invitados` queda `invitado`. `cliente_id` e `invitados` son opcionales.
```json
{ "accion": "editar", "id": 3, "titulo": "...", "fecha": "...", "hora_inicio": "..." }
{ "accion": "cancelar", "id": 3 }
```
Solo quien creó la cita (o admin) puede editarla o cancelarla — cancelar
es borrado suave (`cancelada = 1`), deja de aparecer en cualquier
calendario.
```json
{ "accion": "responder_invitacion", "cita_id": 3, "respuesta": "aceptado" }
```
Solo la persona invitada puede responder su propia invitación (`403` para
cualquier otro, incluido quien creó la cita).

## Leads

Módulo nuevo, con roles distintos a los demás:

| Rol | Qué ve / puede hacer |
|---|---|
| **admin** | Todos los leads, activa/desactiva fuentes (Meta/LinkedIn), botón de simulación, puede asignar. |
| **lider_proyecto** | Todos los leads (tabla completa), puede asignar a un colaborador. No ve el panel de fuentes/simulación — eso es exclusivo de admin. |
| **colaborador** (u otro rol con acceso a `leads`) | **Solo** los leads que tiene asignados — el filtro se fuerza en el servidor (`api/leads.php`), igual que en Tareas. Puede actualizar el estado de sus propios leads, no puede asignar. |

`leads` se concedió de fábrica a `lider_proyecto` y `colaborador` — incluso
si tu base de datos ya existía antes de este módulo, `api/db.php` se lo
agrega solo la primera vez que corres esta versión (consulta idempotente,
no lo duplica si ya lo tenían). Sigue siendo administrable desde **Roles**
como cualquier otro módulo, y "usuarios/roles" siguen fuera del alcance de
cualquier rol que no sea admin, como siempre.

**Todavía no hay integración real con Meta ni LinkedIn.** El botón "Simular
generación de leads" (marcado explícitamente como temporal en la propia
pantalla) genera 100 leads de prueba por cada fuente que esté activada —
nombres, empresas, correos y teléfonos inventados, solo para poder probar
el flujo de asignación de punta a punta. Cuando conectes las APIs reales,
ese botón (y `accion=simular` en `api/leads.php`) es lo que hay que
retirar; el resto del módulo (tabla, asignación, estados) sigue igual, sin
importar de dónde vengan los leads.

### GET api/leads-config.php  (admin)
```json
{ "meta_activo": 0, "linkedin_activo": 1 }
```
### POST api/leads-config.php  (admin)
```json
{ "meta_activo": true, "linkedin_activo": true }
```

### GET api/leads.php
Lista filtrada según el rol (ver tabla arriba).
```json
[{ "id": 8, "nombre": "Andrea García", "empresa": "Grupo Bravo", "email": "...",
   "telefono": "...", "fuente": "meta", "mensaje": "Quiero más información...",
   "estado": "asignado", "asignado_a": 4, "asignado_nombre": "Ana Ruiz",
   "asignado_por_nombre": "Luis Peña", "fecha_asignacion": "...", "created_at": "..." }]
```
`?colaboradores_asignables=1` (admin/lider_proyecto): usuarios activos con
acceso al módulo Leads que no sean `lider_proyecto` — el destino típico de
una asignación.

### POST api/leads.php
```json
{ "accion": "simular" }
```
Solo admin. Genera 100 leads por cada fuente activa en `leads_config`;
`422` si ninguna fuente está activa.
```json
{ "accion": "asignar", "id": 8, "colaborador_id": 4 }
```
Admin o `lider_proyecto`. Si el lead estaba en `nuevo`, pasa a `asignado`
automáticamente.
```json
{ "accion": "cambiar_estado", "id": 8, "estado": "contactado" }
```
Admin/lider_proyecto (cualquier lead), o la persona a la que se lo
asignaron (solo el suyo) — `403` para cualquier otro.

## Tickets imprimibles (Ventas y Proyectos)

Dos páginas independientes, sin el layout general (sin sidebar/topbar) —
pensadas para abrirse en pestaña nueva e imprimirse o guardarse como PDF
con el diálogo de impresión del propio navegador (`window.print()`). No
generan un archivo en el servidor ni dependen de ninguna librería de PDF,
para mantener la misma filosofía ligera del resto del proyecto.

- **`ticket-venta.php?id=N`** — botón de impresora en cada fila de
  `ventas.php` y dentro del modal "Ver" detalle. Desglosa cada línea de la
  venta (artículo, cantidad, precio unitario, subtotal) y el total.
- **`ticket-proyecto.php?id=N`** — botón "Imprimir ticket" en la sección
  "Costos" de `tareas.php?id=N` (solo aparece en proyectos `externo`, ya
  que un interno no tiene nada que cobrar). Desglosa el costo base **más
  cada costo extra como su propia línea**, con quién lo agregó y cuándo, y
  el total.

Ambas respetan exactamente los mismos permisos que ya existían para ver esa
venta o ese proyecto — no son una puerta trasera para ver algo que de otro
modo no podrías: `ticket-venta.php` exige acceso al módulo `ventas` (visto
por todos) y `ticket-proyecto.php` reutiliza `timbra_puede_ver_proyecto()`.

## Editar y eliminar (corregir equivocaciones)

Admin y el líder dueño de cada proyecto pueden editar y eliminar proyectos,
etapas y tareas directamente desde `tareas.php` — íconos de lápiz/bote de
basura junto a cada fila/tarjeta. Reglas:

- **Eliminar es borrado suave**: proyectos usan `archivado`, etapas y
  tareas usan `eliminado`. Nada se borra de la base de datos; solo deja de
  aparecer en listas, tableros y en el Gantt exportado.
- **Una etapa no se puede eliminar si todavía tiene tareas** — el backend
  lo rechaza con un mensaje claro, para no dejar tareas huérfanas.
- Editar una tarea permite incluso **moverla de etapa** (cambiar
  `etapa_id`), siempre dentro del mismo proyecto.
- Todos los mismos permisos de creación aplican a editar/eliminar: un
  `lider_proyecto` solo puede tocar lo que él mismo creó; `admin` puede
  tocar cualquier proyecto.

Endpoints: `accion=editar` / `accion=eliminar` en `api/proyectos.php`,
`api/etapas.php` y `api/tareas.php` (ver contratos JSON más abajo).

## Mi usuario

`perfil.php` es el único módulo accesible para **todos** los roles sin
excepción — cada quien administra su propio nombre, foto y contraseña,
nunca el de alguien más (no hay parámetro de "a quién" editar; siempre es
el dueño de la sesión). La foto se guarda en `assets/avatars/` — pública
por HTTP a propósito, ya que no es información sensible como el CSD — y se
ve reflejada de inmediato en el pie del menú lateral.

### GET api/perfil.php  (cualquier sesión — siempre es la propia)
```json
{ "id": 3, "nombre": "Luis Peña", "email": "luis@empresa.mx", "rol": "lider_proyecto", "foto_path": "assets/avatars/user-3-....jpg" }
```
### POST api/perfil.php  accion=actualizar_perfil  (multipart/form-data)
Campos: `nombre` (obligatorio), `foto` (opcional, JPG/PNG/WEBP, máx. 2 MB).
Actualiza también `$_SESSION` para que el nombre/foto se vean reflejados
sin tener que volver a iniciar sesión.

### POST api/perfil.php  accion=cambiar_password  (JSON)
```json
{ "password_actual": "...", "password_nueva": "...", "password_nueva_confirmar": "..." }
```
Verifica la contraseña actual con `password_verify()`; exige 8+ caracteres
y que la confirmación coincida.

## Diseño

- Tipografías: **Fraunces** (encabezados), **Inter** (texto), **IBM Plex
  Mono** (RFC, folios, UUID, códigos de error) — cargadas desde Google Fonts.
- Color de marca: tinta verde-sello `#0F6E5C` sobre un panel oscuro
  `#12211F` en la barra lateral; fondo general gris-papel `#EEF2F0`.
- Elemento firma: el "sello" circular (KPI de éxito, animación `stamp-anim`
  al actualizar valores/toasts) evoca el sellado digital de cada CFDI.
- Sin frameworks de compilación: solo Bootstrap 5 y Bootstrap Icons por CDN.
