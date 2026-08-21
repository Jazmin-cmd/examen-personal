## 1. Arquitectura y stack

- **Backend**: PHP 8 puro (sin framework), con autoload PSR-4 vía Composer (`App\` - `src/`).
  Un único front controller ([`public/index.php`](public/index.php)) enruta a mano por método +
  URI. Se eligió no usar un framework porque el alcance es un solo recurso (`personas`) más un
  puñado de endpoints de soporte (`/buscar`, `/auditoria`, `/cedulas/*`); un router manual es
  suficiente y mantiene visible cada decisión de seguridad.

- **Persistencia**: MySQL vía PDO con *prepared statements* en todas las consultas (`src/Persona.php`,
  `src/Auditoria.php`). Se eligió MySQL por ser el motor más común para este tipo de CRUD relacional.

- **Frontend**: HTML/JS/CSS servidos como PHP simple ([`public/app.php`](public/app.php)), sin build
  step, consumiendo la API propia vía `fetch`. SweetAlert2 para diálogos y Cloudflare Turnstile para
  el captcha (ambos por CDN).

- **Config**: variables de entorno vía `vlucas/phpdotenv`, cargadas desde `.env` (fuera de git, ver
  `.env.example`).

### Cómo levantar el proyecto desde cero

```bash
composer install
cp .env.example .env        
mysql -u root -p < database/schema.sql
php database/generar_placeholders.php   
php database/seed.php                   
php -S localhost:8000 -t public
```

Variables requeridas en `.env` (ver [`.env.example`](.env.example)):

| Variable | Uso |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Conexión MySQL |
| `TURNSTILE_SECRET_KEY` | Validación server-side del captcha (secreta) |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` | Notificación de búsquedas |

La *site key* de Turnstile no es secreta (está pensada para viajar en el HTML del cliente), por eso
está embebida directamente en [`app.php`](public/app.php); lo que sí debe mantenerse fuera del
código es la *secret key*, que solo se usa en el backend para validar el token contra la API de
Cloudflare. (VALIDACION PRUEBA CAPTCHA)

## 2. Almacenamiento de las imágenes

**Decisión: filesystem, no base64 en la base de datos.** `ImagenHelper::procesar()`
([`src/ImagenHelper.php`](src/ImagenHelper.php)) guarda el archivo en `storage/cedulas/` con un
nombre aleatorio (`random_bytes(16)` + extensión real) y la tabla `personas` solo referencia ese
nombre de archivo.

**Ventajas obtenidas:**
- El listado principal (500+ personas) nunca carga las imágenes — la consulta de listado
  (`Persona::buscarConFiltro`) selecciona columnas puntuales, sin el contenido binario. Esto es lo
  que mantiene el listado liviano y rápido con volumen.
- Las imágenes se sirven con cache HTTP normal de archivos y no inflan cada fila ~33% extra (overhead
  típico de base64).

**Desventajas asumidas:**
-  La fila en la BD y el archivo en disco pueden desincronizarse: si el
  proceso muere entre el `INSERT`/`UPDATE` y `move_uploaded_file`, o viceversa, puede quedar un
  archivo huérfano o una referencia rota. No hay job de reconciliación implementado en este
  ejercicio; en producción se resolvería con un **cron periódico** que recorra
  `storage/cedulas/` y elimine archivos que no tengan una fila correspondiente en `personas`
  (y, en sentido inverso, detecte referencias rotas si el archivo no existe).

- El borrado de una persona (`Persona::eliminar`) día a día debería eliminar también sus imágenes
  asociadas para evitar huérfanos permanentes. En este ejercicio se prioriza dejar documentado
  el mecanismo de sincronización.

## 3. Validación de archivos subidos

Todo en `ImagenHelper::procesar()`:
- **Tipo real, no declarado**: se usa `finfo_file()` sobre el contenido (MIME real) y
  `getimagesize()` (falla si no es una imagen procesable), en vez de confiar en la extensión del
  archivo o el `Content-Type` que manda el navegador.
- **Límite de tamaño**: 2 MB por archivo, verificado contra `$_FILES[...]['size']` antes de tocar el
  disco.
- **Nombre de archivo**: se descarta por completo el nombre recibido del cliente; se genera uno
  nuevo con `bin2hex(random_bytes(16))`. Esto evita path traversal y colisiones, y es lo que permite
  que la ruta de lectura (`GET /cedulas/...`) valide con una regex estricta (`[a-zA-Z0-9_-]+\.(jpg|png|webp)`).
- **Chequeo anti-polyglot**: se busca contenido tipo `<?php`, `<?=` o `<script>` dentro de los bytes
  del archivo antes de guardarlo. No es una garantía absoluta, pero reduce el riesgo de un archivo
  con extensión de imagen y payload ejecutable embebido.
- El archivo se sirve luego con `Content-Type` explícito según la extensión real (nunca inferido del
  nombre que puso el usuario), y desde fuera del document root, así que en ningún punto el navegador
  puede interpretarlo como HTML/JS.

## 4. Obtención de la IP real detrás del túnel

`IpHelper::obtenerIpReal()` ([`src/IpHelper.php`](src/IpHelper.php)) prioriza el header
`CF-Connecting-IP`, que Cloudflare agrega con la IP real del visitante en cada request que pasa por
su red (incluido el tráfico que llega via Cloudflare Tunnel). Como fallback (para desarrollo local,
donde no hay túnel) usa `X-Forwarded-For` y finalmente `REMOTE_ADDR`.

**Limitación adicional — usuario detrás de VPN:** si quien realiza la búsqueda usa una VPN, el
mecanismo sigue funcionando correctamente (Cloudflare reporta la IP real que originó la conexión),
pero esa IP corresponde al proveedor de VPN y no a la ubicación física del usuario. Es una
limitación inherente a cualquier sistema de identificación por IP, no una falla de este mecanismo,
y no tiene solución completa sin servicios externos de detección de VPN/proxy.

## 5. Captcha (Cloudflare Turnstile)

- El widget de Turnstile se renderiza en el modal previo a ejecutar una búsqueda
  (`prepararBusqueda()` en `app.php`) se pide una sola verificación por intento de búsqueda.

- El token que devuelve el widget se manda al backend (`captcha_token`) y se valida ahí contra la
  API de Cloudflare (`CaptchaHelper::validar()`, con timeout de 5s para no colgar la request).
  **La validación ocurre siempre en el servidor**; el frontend nunca decide por sí solo que el
  captcha es válido.
- **Por qué no se puede eludir pegándole directo al endpoint**: `GET /buscar` exige
  `captcha_token` y lo valida contra Cloudflare en cada llamada; sin un token real (emitido por el
  widget para ese sitio) la verificación falla y responde `403`. Un cliente HTTP sin navegador no
  puede resolver el challenge de Turnstile, así que no puede generar un token válido.
- **No reutilizable indefinidamente**: los tokens de Turnstile son de un solo uso por diseño de
  Cloudflare (`siteverify` los invalida al primer uso). Además, tras una búsqueda válida el backend
  marca la sesión como verificada por 15 minutos (`$_SESSION['captcha_verificado_hasta']`), usados
  únicamente para permitir que la tabla se refresque sola después de un alta/edición/baja
  (`GET /personas-refrescar`) sin repetir el widget en cada acción — pero ese endpoint exige esa
  marca de sesión vigente, así que tampoco es un endpoint de búsqueda libre: sin haber pasado antes
  por un captcha válido en `/buscar`, responde `403` y no expone datos.
- Las claves (`TURNSTILE_SECRET_KEY`) viven en `.env`, fuera del código y del repositorio.

## 6. Auditoría de búsquedas

Cada llamada exitosa a `GET /buscar` inserta una fila en `busquedas_auditoria`
([`src/Auditoria.php`](src/Auditoria.php)) con: fecha/hora (`created_at`), término buscado,
cantidad de resultados, IP de origen, país/ciudad/proveedor/coordenadas obtenidos de la
geolocalización, y si la notificación a Telegram se envió con éxito.

Falta hoy una vista en el frontend que liste ese historial: el endpoint `GET /auditoria` existe y
pagina correctamente, pero no está conectado a ninguna pantalla de `app.php` — **queda como pendiente
inmediato** (ver sección 9).

## 7. Geolocalización de IP

Se usa [ip-api.com](http://ip-api.com) (`GeoHelper::obtenerInfo()`), gratuito y sin necesidad de API
key para uso básico. Se eligió por simplicidad de integración para el alcance del ejercicio.

- **Timeout**: 3 segundos por request; si no responde a tiempo, no bloquea la búsqueda.
- **Fallas / errores**: si la llamada falla, devuelve error, o el campo `status` no es `success`, la
  función devuelve `null` y la búsqueda sigue su curso normalmente — el registro de auditoría
  simplemente guarda los campos de geo como `null` y el frontend muestra "desconocido".
- **IPs privadas** (`127.0.0.1`, rangos `192.168.x.x`, etc., frecuentes en pruebas locales antes de
  pasar por el túnel): se filtran antes de llamar a la API (`FILTER_FLAG_NO_PRIV_RANGE` /
  `FILTER_FLAG_NO_RES_RANGE`), ya que para esas direcciones la API no devuelve datos útiles.
- **Límite de la API gratuita** (~45 req/min en ip-api.com): no se implementó un limitador propio;
  al superarse el límite la API responde con error/vacío, lo cual ya está cubierto por el mismo
  manejo de fallas de arriba (se guarda `null`, no rompe nada). Con más tiempo se agregaría un
  caché corto por IP para no repetir la consulta en búsquedas seguidas del mismo visitante.

## 8. Notificación a Telegram

`TelegramHelper::notificar()` envía un mensaje de texto por cada búsqueda a un grupo, con: dominio
de origen del request, método HTTP, endpoint, filtro utilizado, cantidad de resultados, y
ciudad/país aproximados del origen de la consulta.

**Qué se decidió omitir y por qué:** el mensaje **no incluye la IP completa ni los datos de las
personas encontradas** (nombres, documentos). La ciudad/país aproximados se conservan porque aportan valor para detectar patrones de abuso (por ejemplo, muchas búsquedas desde un mismo origen geográfico) sin identificar a un individuo concreto.

- El envío tiene timeout (5s) y valida el campo `ok` de la respuesta de Telegram; el resultado
  (`true`/`false`) queda persistido en `busquedas_auditoria.telegram_enviado`.
- **Si la API de Telegram falla** (bot removido del grupo, token inválido, timeout, etc.), la
  búsqueda del usuario se completa igual — el fallo solo se refleja en el registro de auditoría, no
  se propaga como error al usuario.
- Token del bot y chat ID están en `.env`, nunca en el código fuente.

## 9. Seguridad y manejo de datos personales

- Todas las consultas SQL usan *prepared statements* de PDO (`Persona.php`, `Auditoria.php`); no hay
  concatenación de valores de entrada en ninguna query.
- Validación de entrada duplicada en cliente y servidor (nombres/apellidos solo letras, documento
  solo dígitos, fecha de nacimiento no futura, número de documento único a nivel de constraint
  `UNIQUE` en MySQL) — la del cliente es solo UX, la autoritativa es siempre la del backend.
- `storage/cedulas/`, `.env` y `vendor/` están fuera de `public/` (document root), por lo que no son
  accesibles directamente por URL; las imágenes solo se sirven a través del endpoint controlado
  `GET /cedulas/{archivo}`.
- No hay autenticación de usuarios (fuera del alcance de esta consigna), así que ningún dato se
  segmenta por usuario — toda persona con la URL pública puede operar el CRUD durante la ventana de
  evaluación.

### Política de retención del historial de búsquedas

Se define una retención de **90 días** para `busquedas_auditoria`: es tiempo suficiente para
trazabilidad de incidentes (detectar abuso, entender picos de tráfico) sin acumular indefinidamente
direcciones IP y patrones de búsqueda, que son datos personales. **No se implementó el job de
purgado automático dentro del plazo de este ejercicio** (quedaría como una tarea programada —cron o
evento de MySQL— que borre filas con `created_at` mayor a 90 días); hoy es una política documentada
pero de aplicación manual. Independientemente de esta política, al finalizar la ventana de
evaluación se eliminan manualmente tanto los registros de auditoría como los datos sintéticos de
`personas`, conforme a la advertencia de la sección 2 de la consigna.

## 10. Qué quedó fuera de alcance

- **Manejo de errores uniforme**: solo la creación de personas (`POST /personas`) atrapa
  `PDOException` para no filtrar detalles internos; el resto de las rutas debería envolverse igual
  (o centralizarse en un manejador de errores único) para garantizar que ningún mensaje de error
  exponga trazas o detalles de infraestructura.
- **Validación de rango de IP de Cloudflare** para el header `CF-Connecting-IP` (ver sección 4).
- **Caché de geolocalización** por IP para reducir llamadas repetidas a la API gratuita.

## 11. Uso de inteligencia artificial

Este proyecto se desarrolló con asistencia de inteligencia artificial (Claude) en distintas etapas
del proceso.

- Se identificó y corrigió que el endpoint `GET /personas-refrescar` permitía ejecutar búsquedas sin
  pasar por el captcha (duplicaba la lógica de `/buscar` sin validar `captcha_token`). Se resolvió
  atando ese endpoint a una marca de sesión de verificación reciente en vez de repetir el widget en
  cada refresco.