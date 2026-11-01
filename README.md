# Gestor de tareas por proyecto (Práctica 3)

Programación III, ITLA, 2026-C-3. Stack: PHP 8, MySQL, PHPMailer (incluido en vendor-manual/), PHPUnit.

## Requisitos

- XAMPP con Apache y MySQL encendidos, y el proyecto en `C:\xampp\htdocs\gestor-tareas-proyectos`.
- `php` y `mysql` disponibles en la terminal.
- `curl.exe` (viene con Windows). En PowerShell usa siempre `curl.exe`, no `curl`.
- Una cuenta de correo con contraseña de aplicación, o un servidor SMTP de pruebas.

## Instalación

Clona el repositorio dentro de `C:\xampp\htdocs\` y entra en la carpeta:

    git clone <URL-del-repositorio> gestor-tareas-proyectos
    cd gestor-tareas-proyectos
    git checkout practica-3

Crea la base de datos e importa las tablas, en este orden:

    mysql -u root -e "CREATE DATABASE gestor_tareas CHARACTER SET utf8mb4"
    mysql -u root gestor_tareas < database\schema.sql
    mysql -u root gestor_tareas < database\tareas.sql
    mysql -u root gestor_tareas < database\permisos.sql

Crea tu archivo de variables de entorno a partir de la plantilla y complétalo:

    copy .env.example .env
    notepad .env

La aplicación queda en `http://localhost/gestor-tareas-proyectos/public`. PHPMailer ya viene incluido, no hay que instalar nada más.

## Variables de entorno

Se leen del archivo `.env`, que está en `.gitignore` y nunca se sube.

| Variable | Para qué sirve |
|---|---|
| DB_HOST | Servidor de MySQL |
| DB_NAME | Nombre de la base de datos (gestor_tareas) |
| DB_USER | Usuario de MySQL |
| DB_PASS | Contraseña de MySQL |
| APP_URL | URL pública de la carpeta public, usada para armar el enlace de activación |
| SMTP_HOST | Servidor SMTP que envía los correos |
| SMTP_PORT | Puerto SMTP (587 usa STARTTLS, 465 usa SSL) |
| SMTP_USER | Usuario de la cuenta SMTP |
| SMTP_PASS | Contraseña de la cuenta SMTP (contraseña de aplicación) |
| SMTP_FROM | Dirección que aparece como remitente |
| SMTP_VERIFY_SSL | true por defecto. Solo en desarrollo, false si el entorno intercepta el tráfico SSL |

## Cómo funciona el correo

Ninguna operación envía correo directamente. Registro, recuperación y restablecimiento forzado guardan el mensaje en la tabla `correos_en_cola` con estado pendiente. El correo sale cuando se ejecuta, aparte, el enviador:

    php bin\enviar_correos.php

Si no hay SMTP disponible, el enlace de activación se puede leer en la columna `cuerpo` de la tabla `correos_en_cola`:

    mysql -u root gestor_tareas -e "SELECT cuerpo FROM correos_en_cola WHERE asunto LIKE '%ctiv%' ORDER BY id DESC LIMIT 1\G"

Abre ese enlace en el navegador (o con `curl.exe -i "<enlace>"`) para activar la cuenta.

Cada correo se marca como enviado antes de mandarlo, así que ejecutarlo dos veces no duplica envíos. Si el envío falla, el correo vuelve a `pendiente`.

## Cómo provocar cada criterio

En PowerShell, define primero estas variables. Los tokens salen de `login.php` (campo `token`). Cambia los correos por los tuyos.

    $BASE = "http://localhost/gestor-tareas-proyectos/public"
    $TOKEN = "token-de-cualquier-usuario"
    $TOKEN_E = "token-de-un-usuario-estandar"
    $TOKEN_A = "token-de-un-administrador"

### Primer Administrador

Todo usuario nace como Estándar e inactivo. Para tener un Administrador: regístrate, activa la cuenta (ver más abajo) y súbela de rol en MySQL:

    mysql -u root gestor_tareas -e "UPDATE usuarios SET rol='administrador' WHERE correo='admin@ejemplo.com'"

### Registro y activación (RF-CA-01, 02, 14, 15, 16, 17)

Registrar un usuario (queda inactivo y el correo queda en la cola):

    curl.exe -i -X POST -d "correo=ana@ejemplo.com" -d "password=Clave1234" "$BASE/registro.php"

Correo duplicado: repite el mismo comando, se rechaza.

Contraseña corta o sin números (5 caracteres): se rechaza con mensaje.

    curl.exe -i -X POST -d "correo=otra@ejemplo.com" -d "password=ab1cd" "$BASE/registro.php"

Correo mal formado: se rechaza con mensaje.

    curl.exe -i -X POST -d "correo=no-es-correo" -d "password=Clave1234" "$BASE/registro.php"

Iniciar sesión antes de activar: responde 403, «La cuenta no está activa».

    curl.exe -i -X POST -d "correo=ana@ejemplo.com" -d "password=Clave1234" "$BASE/login.php"

Enviar el correo de activación y abrir el enlace que llega:

    php bin\enviar_correos.php

Abrir el enlace una segunda vez, o después de vencido, se rechaza y el estado no cambia.

Reenviar el enlace. La respuesta es idéntica exista o no el correo, y el reenvío invalida el enlace anterior:

    curl.exe -i -X POST -d "correo=ana@ejemplo.com" "$BASE/reenviar.php"
    curl.exe -i -X POST -d "correo=noexiste@ejemplo.com" "$BASE/reenviar.php"

Contraseña guardada con hash y sal (RF-CA-02): el valor no coincide con la contraseña. Registra dos usuarios con la misma contraseña y compara:

    mysql -u root gestor_tareas -e "SELECT correo, password_hash FROM usuarios"

### Sesión (RF-CA-03, 07, 18, 19)

Iniciar sesión. La respuesta trae el campo `token`:

    curl.exe -i -X POST -d "correo=ana@ejemplo.com" -d "password=Clave1234" "$BASE/login.php"

Contraseña incorrecta y correo inexistente: los dos devuelven 401 con el mismo mensaje.

    curl.exe -i -X POST -d "correo=ana@ejemplo.com" -d "password=Incorrecta1" "$BASE/login.php"
    curl.exe -i -X POST -d "correo=noexiste@ejemplo.com" -d "password=Incorrecta1" "$BASE/login.php"

Consultar el usuario autenticado. Sin cabecera se rechaza:

    curl.exe -i -H "Authorization: Bearer $TOKEN" "$BASE/yo.php"
    curl.exe -i "$BASE/yo.php"

Bloqueo: falla cinco veces seguidas con la contraseña incorrecta. El sexto intento, aun con la contraseña correcta, responde 423 durante 15 minutos. Un inicio de sesión correcto pone el contador en cero.

Cerrar sesión. Después, la misma credencial se rechaza:

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN" "$BASE/logout.php"
    curl.exe -i -H "Authorization: Bearer $TOKEN" "$BASE/yo.php"

### Roles y administración (RF-CA-04, 05, 06, 08, 20, 21)

La exigencia de rol de cada operación está en un solo lugar: `src/permisos.php`, constante `PERM_OPERACIONES`. El rechazo ocurre en el servidor, aunque la petición se construya a mano.

Con el token de un usuario Estándar, todas estas se rechazan:

    curl.exe -i -H "Authorization: Bearer $TOKEN_E" "$BASE/usuarios.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" -d "rol=administrador" "$BASE/usuario_rol.php"

Con el token de un Administrador:

    curl.exe -i -H "Authorization: Bearer $TOKEN_A" "$BASE/usuarios.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" -d "rol=administrador" "$BASE/usuario_rol.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" -d "accion=desactivar" "$BASE/usuario_estado.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" -d "accion=reactivar" "$BASE/usuario_estado.php"

Valores de `rol`: `administrador` o `estandar`. Valores de `accion`: `desactivar` o `reactivar`.

- Un usuario desactivado no inicia sesión y su sesión abierta deja de servir (probar con `yo.php`).
- Un Administrador no puede desactivarse a sí mismo: usa su propio `id` en `usuario_estado.php`.
- El listado nunca incluye hashes ni tokens.

### Contraseñas (RF-CA-09 a 13, 22)

Recuperación. La respuesta es idéntica exista o no el correo:

    curl.exe -i -X POST -d "correo=ana@ejemplo.com" "$BASE/recuperar.php"
    curl.exe -i -X POST -d "correo=noexiste@ejemplo.com" "$BASE/recuperar.php"

Ejecuta `php bin\enviar_correos.php` para que llegue el código. Úsalo para definir la contraseña nueva:

    curl.exe -i -X POST -d "codigo=CODIGO" -d "password=Nueva12345" "$BASE/restablecer.php"

- Usar el código una segunda vez, o vencido, se rechaza y la contraseña no cambia.
- La contraseña vieja ya no inicia sesión; la nueva sí.
- Una credencial emitida antes del cambio se rechaza.

Restablecimiento forzado por un Administrador (el usuario recibe el código por la cola):

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" "$BASE/usuario_reset.php"

Cambio de contraseña con sesión. Con la actual incorrecta se rechaza:

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN" -d "actual=Incorrecta1" -d "nueva=Nueva12345" "$BASE/cambiar_password.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN" -d "actual=Clave1234" -d "nueva=Nueva12345" "$BASE/cambiar_password.php"

### Correo por cola (RF-NOT-08, 09, 12, 13)

1. En `.env`, pon un `SMTP_HOST` que no responda.
2. Registra un usuario: la operación termina bien.
3. Comprueba que el correo quedó pendiente:

        mysql -u root gestor_tareas -e "SELECT id, destinatario, estado FROM correos_en_cola"

4. Restaura el `SMTP_HOST` correcto y ejecuta el enviador dos veces. El correo se entrega una sola vez:

        php bin\enviar_correos.php
        php bin\enviar_correos.php

### Reinicio de la aplicación (RD-09)

Los usuarios y las tareas viven en MySQL. Reinicia Apache y MySQL desde XAMPP y vuelve a listarlos (`usuarios.php`, `tareas.php`).

## Gestión de permisos (RF-GP-01 a 07)

Un usuario Estándar solicita el permiso `eliminar_tareas`; un Administrador lo aprueba o rechaza. Transiciones: Pendiente → Aprobada → Aplicada y Pendiente → Rechazada, declaradas en `src/estados_solicitud.php`. Las solicitudes de los ejemplos se numeran 1, 2, 3 en una base limpia.

**RF-GP-01.** Crear solicitud (nace `pendiente`):

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "permiso=eliminar_tareas" "$BASE/solicitud_crear.php"

**RF-GP-02.** El Administrador lista las pendientes; el Estándar recibe 200 con solo sus propias solicitudes:

    curl.exe -i -H "Authorization: Bearer $TOKEN_A" "$BASE/solicitudes.php"
    curl.exe -i -H "Authorization: Bearer $TOKEN_E" "$BASE/solicitudes.php"

**RF-GP-03 y 05.** Aprobar. Antes de aprobar el Estándar no puede eliminar (403); después pasa el chequeo de permiso (200 si la tarea existe, 404 si no):

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" "$BASE/tarea_eliminar.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=1" "$BASE/solicitud_aprobar.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" "$BASE/tarea_eliminar.php"
    mysql -u root gestor_tareas -e "SELECT id, estado, resuelto_por, resuelto_en FROM solicitudes_permiso"

**RF-GP-04.** Rechazar. Sin motivo responde 422; con motivo se completa:

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "permiso=eliminar_tareas" "$BASE/solicitud_crear.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" "$BASE/solicitud_rechazar.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" -d "motivo=No procede" "$BASE/solicitud_rechazar.php"

**RF-GP-06.** Aprobar dos veces responde 409 y el estado no cambia. Aprobar una rechazada también:

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=1" "$BASE/solicitud_aprobar.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=2" "$BASE/solicitud_aprobar.php"

**RF-GP-07.** El Administrador crea su propia solicitud e intenta aprobarla (403):

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "permiso=eliminar_tareas" "$BASE/solicitud_crear.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=3" "$BASE/solicitud_aprobar.php"

**RD-09.** Reinicia Apache y MySQL desde XAMPP y vuelve a listar con `solicitudes.php`: las solicitudes siguen ahí.

## Módulo de negocio: Tareas (RF-NEG-02 a 06, 09)

La entidad central es `tareas` (`database/tareas.sql`). Los estados, las transiciones permitidas, las prohibidas y el estado terminal están declarados en un solo lugar: `src/estados_tarea.php`. La tabla de transiciones está en `docs/maquina-de-estados.md`. La lógica vive en `src/tareas.php` y no depende de la máquina de estados de Gestión de permisos (`src/estados_solicitud.php`). Cada cambio de estado se resuelve en un solo punto: la función `tarea_mover`.

Todas las operaciones exigen sesión válida (se rechazan con 401 sin ella) y están declaradas en `PERM_OPERACIONES` de `src/permisos.php`. Las variables `$BASE`, `$TOKEN_E` y `$TOKEN_A` son las definidas arriba.

### Cómo provocar cada funcionalidad

**1. Crear tarea** (nace `pendiente`). Sin título responde 422:

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "titulo=Mi tarea" "$BASE/tarea_crear.php"

**2. Listar tareas** (el Estándar ve las suyas; el Administrador, todas):

    curl.exe -i -H "Authorization: Bearer $TOKEN_E" "$BASE/tareas.php"

**3. Ver una tarea.** Un id que no existe responde 404:

    curl.exe -i -H "Authorization: Bearer $TOKEN_E" "$BASE/tarea_ver.php?id=1"

**4. Cambiar el estado.** Transición permitida (200):

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" -d "estado=en_progreso" "$BASE/tarea_estado.php"

Transición prohibida (409, el estado no cambia): con una tarea nueva en `pendiente` (por ejemplo la 2), intenta completarla:

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=2" -d "estado=completada" "$BASE/tarea_estado.php"

Condición de la transición: un Estándar no puede completar una tarea en `en_revision` (409); un Administrador sí:

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" -d "estado=en_revision" "$BASE/tarea_estado.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" -d "estado=completada" "$BASE/tarea_estado.php"
    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=1" -d "estado=completada" "$BASE/tarea_estado.php"

Estado terminal: la tarea 1 ya está `completada`. Intenta moverla a cualquier estado; responde 409 siempre y el estado no cambia:

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_A" -d "id=1" -d "estado=en_progreso" "$BASE/tarea_estado.php"

**5. Eliminar tarea** (exige el permiso `eliminar_tareas` de Gestión de permisos). Sin el permiso aprobado responde 403:

    curl.exe -i -X POST -H "Authorization: Bearer $TOKEN_E" -d "id=1" "$BASE/tarea_eliminar.php"

Para obtenerlo: el Estándar crea la solicitud (`solicitud_crear.php`), un Administrador la aprueba (`solicitud_aprobar.php`) y entonces la eliminación responde 200 (o 404 si la tarea no existe).

## Pruebas

Un solo comando ejecuta toda la suite (crea la base `gestor_tareas_test` si no existe, importa las tablas y lanza PHPUnit). Requiere MySQL encendido:

    php bin\probar.php

`bin\probar.php` se conecta a MySQL como `root` sin contraseña en `localhost` (los valores van fijos en el script, no se leen de `.env`). Si tu MySQL usa otra contraseña, las pruebas no podrán conectarse; el XAMPP por defecto funciona tal cual.

- Las pruebas de gestión de permisos están en `tests/SolicitudesTest.php` y llaman al servicio `src/solicitudes.php` directamente, sin levantar la aplicación.
- Las pruebas de la máquina de estados de Tareas están en `tests/TareasEstadosTest.php`: una por cada fila de las tablas de `docs/maquina-de-estados.md`, una por la condición de la transición y una por el estado terminal. Llaman a `src/tareas.php` directamente.
- La prueba de integración está en `tests/IntegracionTareasPermisosTest.php`. Usa Gestión de permisos y Tareas reales, sin dobles y sin levantar la aplicación. Verifica las dos caras: eliminar se rechaza mientras la solicitud no está aprobada, y se permite después de que un Administrador la aprueba.

## Defectos

Cada línea indica la prueba que detecta el defecto y el comportamiento que comprueba.

1. Defecto 1 (la transición prohibida `pendiente → completada` pasa a ser permitida): `test_pendiente_no_puede_pasar_a_completada_y_el_estado_no_cambia`. Comprueba que la transición prohibida se rechaza y el estado no cambia.
2. Defecto 2 (el estado terminal `completada` deja de ser terminal): `test_de_completada_no_sale_ninguna_transicion`. Comprueba que de `completada` no sale ninguna transición.
3. Defecto 3 (`en_revision → completada` sin verificar que quien la ejecuta sea Administrador): `test_un_estandar_no_puede_completar_una_tarea_en_revision`. Comprueba que un Estándar no completa una tarea.
4. Defecto 4 (`tarea_eliminar` sin consultar a Gestión de permisos): `test_eliminar_tarea_se_rechaza_mientras_la_solicitud_no_esta_aprobada`. Comprueba que eliminar se rechaza mientras la solicitud no está aprobada.