# Gestor de tareas por proyecto (Práctica 4)

Programación III, ITLA, 2026-C-3. Stack: PHP 8, MySQL, PHPMailer (incluido en `vendor-manual/`), PHPUnit, Docker.

## Contenedor

Requisito: Docker Desktop. No hace falta instalar nada más.

```
git clone <URL-del-repositorio> gestor-tareas-proyectos
cd gestor-tareas-proyectos
git checkout practica-4
copy .env.example .env
docker compose up --build
```

La aplicación responde en **http://localhost:8085**. Solo la aplicación publica un puerto: la base de datos no es accesible desde fuera de la composición. Las tablas se crean solas la primera vez que arranca la base de datos, sin pasos manuales.

Detener el proyecto sin perder datos:

```
docker compose down
```

Volver a levantarlo (en segundo plano):

```
docker compose up -d
```

No uses `docker compose down -v`: la opción `-v` borra los volúmenes, y con ellos los documentos y la base de datos.

Los archivos de los documentos y los datos de MySQL viven en volúmenes con nombre, fuera de la imagen, y sobreviven a `docker compose down`.

### Atajo para consultar la base de datos

Varios ejemplos de este README consultan MySQL. En PowerShell, dentro de la carpeta del proyecto, define este atajo una vez por terminal:

```
function sql($q) { docker compose exec -T db sh -c "mysql -u root -p`$MYSQL_ROOT_PASSWORD gestor_tareas -e `"$q`"" }
```

Uso: `sql "SELECT id, correo, rol FROM usuarios"`

### Comprobar que los datos sobreviven

1. Inicia sesión y deja registrada una tarea (ver «Módulo de negocio»).
2. Ejecuta `docker compose down` (sin `-v`) y luego `docker compose up -d`.
3. Inicia sesión con el mismo usuario y vuelve a listar con `tareas.php`: el usuario y la tarea siguen ahí.

## API externa

- **API:** [ExchangeRate-API](https://www.exchangerate-api.com) (tasas de cambio). Exige una clave.
- **Operación que la usa:** `GET tarea_ver.php?id=ID&convertir=MONEDA`. Toma el costo de la tarea (guardado con `tarea_costo.php`) y lo convierte a la moneda pedida.
- **Variable de entorno:** `EXCHANGE_API_KEY`, definida en `.env` (que no se versiona). `.env.example` trae un valor de ejemplo.

Fijar el costo de una tarea (exige el permiso `tareas.costo`; `moneda` es opcional y por defecto es `USD`):

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=1" -d "costo=100" -d "moneda=USD" "$BASE/tarea_costo.php"
```

Ver la tarea con el costo convertido:

```
curl.exe -i -H "Authorization: Bearer $TOKEN_A" "$BASE/tarea_ver.php?id=1&convertir=DOP"
```

Respuesta real de la API externa para USD→DOP (sin la clave):

```json
{"result":"success","base_code":"USD","target_code":"DOP","conversion_rate":60.8336}
```

Si la clave no está definida, no es válida o la API no responde, `tarea_ver.php` sigue respondiendo 200 con `"costo_convertido":null` y un aviso, por ejemplo «El servicio de tasas de cambio no está configurado.» o «La clave del servicio de tasas de cambio no es válida.». El resto del proyecto sigue funcionando.

## Variables de entorno

Se leen del archivo `.env`, que está en `.gitignore` y nunca se sube. `.env.example` trae todas con un valor de ejemplo. Copiarlo a `.env` es el único paso previo para levantar el proyecto.

| Variable | Para qué sirve |
|---|---|
| `DB_HOST` | Servidor de MySQL (el nombre del servicio, `db`) |
| `DB_NAME` | Nombre de la base de datos (`gestor_tareas`) |
| `DB_USER` | Usuario de MySQL |
| `DB_PASS` | Contraseña de MySQL |
| `DB_ROOT_PASS` | Contraseña de root de MySQL dentro del contenedor |
| `APP_URL` | URL pública de la aplicación (`http://localhost:8085`), usada para armar el enlace de activación |
| `SMTP_HOST` | Servidor SMTP que envía los correos (vacío: los correos quedan en la cola) |
| `SMTP_PORT` | Puerto SMTP (587 usa STARTTLS, 465 usa SSL) |
| `SMTP_USER` | Usuario de la cuenta SMTP |
| `SMTP_PASS` | Contraseña de la cuenta SMTP (contraseña de aplicación) |
| `SMTP_FROM` | Dirección que aparece como remitente |
| `SMTP_VERIFY_SSL` | `true` por defecto. Solo en desarrollo, `false` si el entorno intercepta el tráfico SSL |
| `EXCHANGE_API_KEY` | Clave de ExchangeRate-API. Nunca va en el código, el Dockerfile, el compose ni `.env.example` |

## Cómo funciona el correo

Ninguna operación envía correo directamente. Registro, recuperación y restablecimiento forzado guardan el mensaje en la tabla `correos_en_cola` con estado `pendiente`. El correo sale cuando se ejecuta, aparte, el enviador:

```
docker compose exec app php bin/enviar_correos.php
```

Si no hay SMTP disponible (`SMTP_HOST` vacío), el enlace de activación se lee en la columna `cuerpo` de la tabla `correos_en_cola`:

```
sql "SELECT cuerpo FROM correos_en_cola WHERE asunto LIKE '%ctiv%' ORDER BY id DESC LIMIT 1\G"
```

Abre ese enlace en el navegador (o con `curl.exe -i "<enlace>"`) para activar la cuenta.

Cada correo se marca como enviado antes de mandarlo, así que ejecutarlo dos veces no duplica envíos. Si el envío falla, el correo vuelve a pendiente.

## Cómo provocar cada criterio

En PowerShell, define primero estas variables. Los tokens salen de `login.php` (campo `token`). Cambia los correos por los tuyos.

```
$BASE = "http://localhost:8085"
$TOKEN = "token-de-cualquier-usuario"
$TOKEN_E = "token-de-un-usuario-estandar"
$TOKEN_A = "token-de-un-administrador"
```

### Primer Administrador

Todo usuario nace como Estándar e inactivo. Para tener un Administrador: regístrate, activa la cuenta (ver más abajo) y súbela de rol en MySQL:

```
sql "UPDATE usuarios SET rol='administrador' WHERE correo='admin@ejemplo.com'"
```

### Registro y activación (RF-CA-01, 02, 14, 15, 16, 17)

Registrar un usuario (queda inactivo y el correo queda en la cola):

```
curl.exe -i -X POST -d "correo=ana@ejemplo.com" -d "password=Clave1234" "$BASE/registro.php"
```

Correo duplicado: repite el mismo comando, se rechaza.

Contraseña corta o sin números (5 caracteres): se rechaza con mensaje.

```
curl.exe -i -X POST -d "correo=otra@ejemplo.com" -d "password=ab1cd" "$BASE/registro.php"
```

Correo mal formado: se rechaza con mensaje.

```
curl.exe -i -X POST -d "correo=no-es-correo" -d "password=Clave1234" "$BASE/registro.php"
```

Iniciar sesión antes de activar: responde 403, «La cuenta no está activa».

```
curl.exe -i -X POST -d "correo=ana@ejemplo.com" -d "password=Clave1234" "$BASE/login.php"
```

Enviar el correo de activación y abrir el enlace que llega (o leerlo de la cola, ver «Cómo funciona el correo»):

```
docker compose exec app php bin/enviar_correos.php
```

Abrir el enlace una segunda vez, o después de vencido, se rechaza y el estado no cambia.

Reenviar el enlace. La respuesta es idéntica exista o no el correo, y el reenvío invalida el enlace anterior:

```
curl.exe -i -X POST -d "correo=ana@ejemplo.com" "$BASE/reenviar.php"
curl.exe -i -X POST -d "correo=noexiste@ejemplo.com" "$BASE/reenviar.php"
```

Contraseña guardada con hash y sal (RF-CA-02): el valor no coincide con la contraseña. Registra dos usuarios con la misma contraseña y compara:

```
sql "SELECT correo, password_hash FROM usuarios"
```

### Sesión (RF-CA-03, 07, 18, 19)

Iniciar sesión. La respuesta trae el campo `token`:

```
curl.exe -i -X POST -d "correo=ana@ejemplo.com" -d "password=Clave1234" "$BASE/login.php"
```

Contraseña incorrecta y correo inexistente: los dos devuelven 401 con el mismo mensaje.

```
curl.exe -i -X POST -d "correo=ana@ejemplo.com" -d "password=Incorrecta1" "$BASE/login.php"
curl.exe -i -X POST -d "correo=noexiste@ejemplo.com" -d "password=Incorrecta1" "$BASE/login.php"
```

Consultar el usuario autenticado. Sin cabecera se rechaza:

```
curl.exe -i -H "Authorization: Bearer $TOKEN" "$BASE/yo.php"
curl.exe -i "$BASE/yo.php"
```

Bloqueo: falla cinco veces seguidas con la contraseña incorrecta. El sexto intento, aun con la contraseña correcta, responde 423 durante 15 minutos. Un inicio de sesión correcto pone el contador en cero.

Cerrar sesión. Después, la misma credencial se rechaza:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN" "$BASE/logout.php"
curl.exe -i -H "Authorization: Bearer $TOKEN" "$BASE/yo.php"
```

### Roles y administración (RF-CA-04, 05, 06, 08, 20, 21)

La exigencia de rol de cada operación está en un solo lugar: `src/permisos.php`, constante `PERM_OPERACIONES`. El rechazo ocurre en el servidor, aunque la petición se construya a mano.

Con el token de un usuario Estándar, todas estas se rechazan:

```
curl.exe -i -H "Authorization: Bearer $TOKEN_E" "$BASE/usuarios.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" -d "rol=administrador" "$BASE/usuario_rol.php"
```

Con el token de un Administrador:

```
curl.exe -i -H "Authorization: Bearer $TOKEN_A" "$BASE/usuarios.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" -d "rol=administrador" "$BASE/usuario_rol.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" -d "accion=desactivar" "$BASE/usuario_estado.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" -d "accion=reactivar" "$BASE/usuario_estado.php"
```

Valores de `rol`: `administrador` o `estandar`. Valores de `accion`: `desactivar` o `reactivar`.

- Un usuario desactivado no inicia sesión y su sesión abierta deja de servir (probar con `yo.php`).
- Un Administrador no puede desactivarse a sí mismo: usa su propio id en `usuario_estado.php`.
- El listado nunca incluye hashes ni tokens.

### Contraseñas (RF-CA-09 a 13, 22)

Recuperación. La respuesta es idéntica exista o no el correo:

```
curl.exe -i -X POST -d "correo=ana@ejemplo.com" "$BASE/recuperar.php"
curl.exe -i -X POST -d "correo=noexiste@ejemplo.com" "$BASE/recuperar.php"
```

Ejecuta `docker compose exec app php bin/enviar_correos.php` (o lee el código en `correos_en_cola`). Úsalo para definir la contraseña nueva:

```
curl.exe -i -X POST -d "codigo=CODIGO" -d "password=Nueva12345" "$BASE/restablecer.php"
```

- Usar el código una segunda vez, o vencido, se rechaza y la contraseña no cambia.
- La contraseña vieja ya no inicia sesión; la nueva sí.
- Una credencial emitida antes del cambio se rechaza.

Restablecimiento forzado por un Administrador (el usuario recibe el código por la cola):

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" "$BASE/usuario_reset.php"
```

Cambio de contraseña con sesión. Con la actual incorrecta se rechaza:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN" -d "actual=Incorrecta1" -d "nueva=Nueva12345" "$BASE/cambiar_password.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN" -d "actual=Clave1234" -d "nueva=Nueva12345" "$BASE/cambiar_password.php"
```

### Correo por cola (RF-NOT-08, 09, 12, 13)

1. Registra un usuario: la operación termina bien, aunque no haya SMTP.
2. Comprueba que el correo quedó pendiente:

```
sql "SELECT id, destinatario, estado FROM correos_en_cola"
```

3. Con un SMTP válido en `.env`, recrea la aplicación y ejecuta el enviador dos veces. El correo se entrega una sola vez:

```
docker compose up -d --force-recreate app
docker compose exec app php bin/enviar_correos.php
docker compose exec app php bin/enviar_correos.php
```

### Reinicio de la aplicación (RD-09)

Los usuarios, las solicitudes y las tareas viven en MySQL, dentro de un volumen. Ejecuta `docker compose down` (sin `-v`), luego `docker compose up -d`, y vuelve a listarlos (`usuarios.php`, `tareas.php`, `solicitudes.php`).

## Gestión de permisos (RF-GP-01 a 07)

Un usuario Estándar solicita el permiso `eliminar_tareas`; un Administrador lo aprueba o rechaza. Transiciones: Pendiente → Aprobada → Aplicada y Pendiente → Rechazada, declaradas en `src/estados_solicitud.php`. Las solicitudes de los ejemplos se numeran 1, 2, 3 en una base limpia.

**RF-GP-01.** Crear solicitud (nace pendiente):

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "permiso=eliminar_tareas" "$BASE/solicitud_crear.php"
```

**RF-GP-02.** El Administrador lista las pendientes; el Estándar recibe 200 con solo sus propias solicitudes:

```
curl.exe -i -H "Authorization: Bearer $TOKEN_A" "$BASE/solicitudes.php"
curl.exe -i -H "Authorization: Bearer $TOKEN_E" "$BASE/solicitudes.php"
```

**RF-GP-03 y 05.** Aprobar. Antes de aprobar el Estándar no puede eliminar (403); después pasa el chequeo de permiso (200 si la tarea existe, 404 si no):

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" "$BASE/tarea_eliminar.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=1" "$BASE/solicitud_aprobar.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" "$BASE/tarea_eliminar.php"
sql "SELECT id, estado, resuelto_por, resuelto_en FROM solicitudes_permiso"
```

**RF-GP-04.** Rechazar. Sin motivo responde 422; con motivo se completa:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "permiso=eliminar_tareas" "$BASE/solicitud_crear.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" "$BASE/solicitud_rechazar.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" -d "motivo=No procede" "$BASE/solicitud_rechazar.php"
```

**RF-GP-06.** Aprobar dos veces responde 409 y el estado no cambia. Aprobar una rechazada también:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=1" "$BASE/solicitud_aprobar.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" "$BASE/solicitud_aprobar.php"
```

**RF-GP-07.** El Administrador crea su propia solicitud e intenta aprobarla (403):

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "permiso=eliminar_tareas" "$BASE/solicitud_crear.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=3" "$BASE/solicitud_aprobar.php"
```

**RD-09.** Ejecuta `docker compose down` (sin `-v`) y `docker compose up -d`, y vuelve a listar con `solicitudes.php`: las solicitudes siguen ahí.

## Módulo de negocio: Tareas (RF-NEG-02 a 06, 09)

La entidad central es `tareas` (`database/tareas.sql`). Los estados, las transiciones permitidas, las prohibidas y el estado terminal están declarados en un solo lugar: `src/estados_tarea.php`. La tabla de transiciones está en `docs/maquina-de-estados.md`. La lógica vive en `src/tareas.php` y no depende de la máquina de estados de Gestión de permisos (`src/estados_solicitud.php`). Cada cambio de estado se resuelve en un solo punto: la función `tarea_mover`.

Todas las operaciones exigen sesión válida (se rechazan con 401 sin ella) y están declaradas en `PERM_OPERACIONES` de `src/permisos.php`. Las variables `$BASE`, `$TOKEN_E` y `$TOKEN_A` son las definidas arriba.

### Cómo provocar cada funcionalidad

**1. Crear tarea** (nace pendiente). Sin título responde 422:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "titulo=Mi tarea" "$BASE/tarea_crear.php"
```

**2. Listar tareas** (el Estándar ve las suyas; el Administrador, todas):

```
curl.exe -i -H "Authorization: Bearer $TOKEN_E" "$BASE/tareas.php"
```

**3. Ver una tarea.** Un id que no existe responde 404:

```
curl.exe -i -H "Authorization: Bearer $TOKEN_E" "$BASE/tarea_ver.php?id=1"
```

**4. Cambiar el estado.** Transición permitida (200):

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" -d "estado=en_progreso" "$BASE/tarea_estado.php"
```

Transición prohibida (409, el estado no cambia): con una tarea nueva en `pendiente` (por ejemplo la 2), intenta completarla:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=2" -d "estado=completada" "$BASE/tarea_estado.php"
```

Condición de la transición: un Estándar no puede completar una tarea en `en_revision` (409); un Administrador sí:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" -d "estado=en_revision" "$BASE/tarea_estado.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" -d "estado=completada" "$BASE/tarea_estado.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=1" -d "estado=completada" "$BASE/tarea_estado.php"
```

Estado terminal: la tarea 1 ya está `completada`. Intenta moverla a cualquier estado; responde 409 siempre y el estado no cambia:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=1" -d "estado=en_progreso" "$BASE/tarea_estado.php"
```

**5. Eliminar tarea** (exige el permiso `eliminar_tareas` de Gestión de permisos). Sin el permiso aprobado responde 403:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" "$BASE/tarea_eliminar.php"
```

Para obtenerlo: el Estándar crea la solicitud (`solicitud_crear.php`), un Administrador la aprueba (`solicitud_aprobar.php`) y entonces la eliminación responde 200 (o 404 si la tarea no existe).

**6. Costo y conversión de moneda** (usa la API externa, ver sección «API externa»):

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" -d "costo=100" -d "moneda=USD" "$BASE/tarea_costo.php"
curl.exe -i -H "Authorization: Bearer $TOKEN_A" "$BASE/tarea_ver.php?id=2&convertir=DOP"
```
## Manejador de documentos (RF-DOC-01 a 05)

Todas las operaciones exigen sesión válida (401 sin ella) y están declaradas en `PERM_OPERACIONES` de `src/permisos.php`. El rechazo ocurre en el servidor. La lógica vive en `src/documentos.php`. Los archivos se guardan en un volumen con nombre, fuera de la imagen, y los registros en MySQL, que tiene su propio volumen.

Crea un archivo de prueba y uno vacío:

```
Set-Content -Path prueba.txt -Value "Contenido de prueba"
New-Item -ItemType File -Path vacio.txt -Force
```

`$TOKEN` es el del dueño del documento y `$TOKEN_E2` el de otro usuario Estándar.

**RF-DOC-01. Subir** (el campo se llama `archivo`). Responde 201 con nombre, tipo, tamaño, fecha y quién lo subió:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN" -F "archivo=@prueba.txt" "$BASE/documento_subir.php"
sql "SELECT * FROM documentos"
```

Archivo ausente o vacío (RD-07): rechazo controlado, sin excepción sin manejar:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN" "$BASE/documento_subir.php"
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN" -F "archivo=@vacio.txt" "$BASE/documento_subir.php"
```

Sin sesión se rechaza:

```
curl.exe -i -X POST -F "archivo=@prueba.txt" "$BASE/documento_subir.php"
```

**RF-DOC-02. Listar.** El Estándar ve solo los suyos y sin eliminados; el Administrador ve los de todos:

```
curl.exe -i -H "Authorization: Bearer $TOKEN" "$BASE/documentos.php"
curl.exe -i -H "Authorization: Bearer $TOKEN_E2" "$BASE/documentos.php"
curl.exe -i -H "Authorization: Bearer $TOKEN_A" "$BASE/documentos.php"
```

**RF-DOC-03. Descargar.** El dueño y el Administrador lo logran (200); otro usuario Estándar recibe un rechazo:

```
curl.exe -i -H "Authorization: Bearer $TOKEN" -o descargado.txt "$BASE/documento_descargar.php?id=1"
Get-Content descargado.txt
curl.exe -i -H "Authorization: Bearer $TOKEN_E2" "$BASE/documento_descargar.php?id=1"
```

**RF-DOC-04. Eliminar de forma lógica.** Otro usuario Estándar es rechazado y el documento sigue ahí:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E2" -d "id=1" "$BASE/documento_eliminar.php"
```

El dueño lo elimina (200). Deja de aparecer en el listado, pero el registro sigue en la base de datos con `eliminado_en` lleno:

```
curl.exe -i -X POST -H "Authorization: Bearer $TOKEN" -d "id=1" "$BASE/documento_eliminar.php"
curl.exe -i -H "Authorization: Bearer $TOKEN" "$BASE/documentos.php"
sql "SELECT id, nombre, eliminado_en FROM documentos"
```

**RF-DOC-05. Persistencia.** Con un documento subido y sin eliminar, ejecuta `docker compose down` (sin `-v`) y `docker compose up -d`. Después inicia sesión de nuevo con el mismo usuario, lista con `documentos.php` y descarga el documento: sigue ahí.

## Pruebas automáticas (opcional, con XAMPP)

La suite de PHPUnit se ejecuta fuera de Docker, con XAMPP (MySQL en `localhost`, usuario `root` sin contraseña):

```
php bin\probar.php
```

Crea la base `gestor_tareas_test` si no existe, importa las tablas y lanza PHPUnit.

- `tests/SolicitudesTest.php`: Gestión de permisos, llama al servicio `src/solicitudes.php` directamente.
- `tests/TareasEstadosTest.php`: una prueba por cada fila de las tablas de `docs/maquina-de-estados.md`, una por la condición de la transición y una por el estado terminal.
- `tests/IntegracionTareasPermisosTest.php`: integración con Gestión de permisos y Tareas reales, sin dobles.

### Defectos

Cada línea indica la prueba que detecta el defecto y el comportamiento que comprueba.

- **Defecto 1** (la transición prohibida `pendiente → completada` pasa a ser permitida): `test_pendiente_no_puede_pasar_a_completada_y_el_estado_no_cambia`.
- **Defecto 2** (el estado terminal `completada` deja de ser terminal): `test_de_completada_no_sale_ninguna_transicion`.
- **Defecto 3** (`en_revision → completada` sin verificar que quien la ejecuta sea Administrador): `test_un_estandar_no_puede_completar_una_tarea_en_revision`.
- **Defecto 4** (`tarea_eliminar` sin consultar a Gestión de permisos): `test_eliminar_tarea_se_rechaza_mientras_la_solicitud_no_esta_aprobada`.