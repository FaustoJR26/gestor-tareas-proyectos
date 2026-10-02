# Gestor de tareas por proyecto: Control de acceso (Práctica 1)

Programación III, ITLA, 2026-C-3. Stack: PHP 8, MySQL, PHPMailer (incluido en `vendor-manual/`).

## Requisitos

- XAMPP con Apache y MySQL encendidos, y el proyecto en `C:\xampp\htdocs\gestor-tareas-proyectos`.
- `php` y `mysql` disponibles en la terminal.
- `curl`.
- Una cuenta de correo con contraseña de aplicación, o un servidor SMTP de pruebas.

## Instalación

1. Clona el repositorio dentro de `C:\xampp\htdocs\` y entra en la carpeta:

```
git clone <URL-del-repositorio> gestor-tareas-proyectos
cd gestor-tareas-proyectos
git checkout practica-1
```

2. Crea la base de datos e importa las tablas, en este orden:

```
mysql -u root -e "CREATE DATABASE gestor_tareas CHARACTER SET utf8mb4"
mysql -u root gestor_tareas < database\schema.sql
mysql -u root gestor_tareas < database\tareas.sql
```

3. Crea tu archivo de variables de entorno a partir de la plantilla y complétalo:

```
copy .env.example .env
notepad .env
```

4. La aplicación queda en `http://localhost/gestor-tareas-proyectos/public`. PHPMailer ya viene incluido, no hay que instalar nada más.

## Variables de entorno

Se leen del archivo `.env`, que está en `.gitignore` y nunca se sube.

| Variable | Para qué sirve |
|---|---|
| DB_HOST | Servidor de MySQL |
| DB_NAME | Nombre de la base de datos (`gestor_tareas`) |
| DB_USER | Usuario de MySQL |
| DB_PASS | Contraseña de MySQL |
| APP_URL | URL pública de la carpeta `public`, usada para armar el enlace de activación |
| SMTP_HOST | Servidor SMTP que envía los correos |
| SMTP_PORT | Puerto SMTP (587 usa STARTTLS, 465 usa SSL) |
| SMTP_USER | Usuario de la cuenta SMTP |
| SMTP_PASS | Contraseña de la cuenta SMTP (contraseña de aplicación) |
| SMTP_FROM | Dirección que aparece como remitente |
| SMTP_VERIFY_SSL | `true` por defecto. Solo en desarrollo, `false` si el entorno intercepta el tráfico SSL |

## Cómo funciona el correo

Ninguna operación envía correo directamente. Registro, recuperación y restablecimiento forzado guardan el mensaje en la tabla `correos_en_cola` con estado `pendiente`. El correo sale cuando se ejecuta, aparte, el enviador:

```
php bin\enviar_correos.php
```

Cada correo se marca como enviado antes de mandarlo, así que ejecutarlo dos veces no duplica envíos. Si el envío falla, el correo vuelve a `pendiente`.

## Cómo provocar cada criterio

En los ejemplos, `BASE` es `http://localhost/gestor-tareas-proyectos/public`. Escribe la URL completa en cada comando. Cambia los correos por los tuyos.

### Primer Administrador

Todo usuario nace como Estándar e inactivo. Para tener un Administrador: regístrate, activa la cuenta (ver más abajo) y súbela de rol en MySQL:

```
mysql -u root gestor_tareas -e "UPDATE usuarios SET rol='administrador' WHERE correo='admin@ejemplo.com'"
```

### Registro y activación (RF-CA-01, 02, 14, 15, 16, 17)

Registrar un usuario (queda inactivo y el correo queda en la cola):

```
curl -i -X POST -d "correo=ana@ejemplo.com" -d "password=Clave1234" BASE/registro.php
```

Correo duplicado: repite el mismo comando, se rechaza.

Contraseña corta o sin números (5 caracteres): se rechaza con mensaje.

```
curl -i -X POST -d "correo=otra@ejemplo.com" -d "password=ab1cd" BASE/registro.php
```

Correo mal formado: se rechaza con mensaje.

```
curl -i -X POST -d "correo=no-es-correo" -d "password=Clave1234" BASE/registro.php
```

Iniciar sesión antes de activar: responde 403, «La cuenta no está activa».

```
curl -i -X POST -d "correo=ana@ejemplo.com" -d "password=Clave1234" BASE/login.php
```

Enviar el correo de activación y abrir el enlace que llega:

```
php bin\enviar_correos.php
```

Abrir el enlace una segunda vez, o después de vencido, se rechaza y el estado no cambia.

Reenviar el enlace. La respuesta es idéntica exista o no el correo, y el reenvío invalida el enlace anterior:

```
curl -i -X POST -d "correo=ana@ejemplo.com" BASE/reenviar.php
curl -i -X POST -d "correo=noexiste@ejemplo.com" BASE/reenviar.php
```

Contraseña guardada con hash y sal (RF-CA-02): el valor no coincide con la contraseña. Registra dos usuarios con la misma contraseña y compara:

```
mysql -u root gestor_tareas -e "SELECT correo, password_hash FROM usuarios"
```

### Sesión (RF-CA-03, 07, 18, 19)

Iniciar sesión. La respuesta trae el campo `token`:

```
curl -i -X POST -d "correo=ana@ejemplo.com" -d "password=Clave1234" BASE/login.php
```

Contraseña incorrecta y correo inexistente: los dos devuelven 401 con el mismo mensaje.

```
curl -i -X POST -d "correo=ana@ejemplo.com" -d "password=Incorrecta1" BASE/login.php
curl -i -X POST -d "correo=noexiste@ejemplo.com" -d "password=Incorrecta1" BASE/login.php
```

Consultar el usuario autenticado. Sin cabecera se rechaza:

```
curl -i -H "Authorization: Bearer TOKEN" BASE/yo.php
curl -i BASE/yo.php
```

Bloqueo: falla cinco veces seguidas con la contraseña incorrecta. El sexto intento, aun con la contraseña correcta, responde 423 durante 15 minutos. Un inicio de sesión correcto pone el contador en cero.

Cerrar sesión. Después, la misma credencial se rechaza:

```
curl -i -X POST -H "Authorization: Bearer TOKEN" BASE/logout.php
curl -i -H "Authorization: Bearer TOKEN" BASE/yo.php
```

### Roles y administración (RF-CA-04, 05, 06, 08, 20, 21)

La exigencia de rol de cada operación está en un solo lugar: `src/permisos.php`, constante `PERM_OPERACIONES`. El rechazo ocurre en el servidor, aunque la petición se construya a mano.

Con el token de un usuario Estándar, todas estas se rechazan:

```
curl -i -H "Authorization: Bearer TOKEN_ESTANDAR" BASE/usuarios.php
curl -i -X POST -H "Authorization: Bearer TOKEN_ESTANDAR" -d "id=1" -d "rol=administrador" BASE/usuario_rol.php
```

Con el token de un Administrador:

```
curl -i -H "Authorization: Bearer TOKEN_ADMIN" BASE/usuarios.php
curl -i -X POST -H "Authorization: Bearer TOKEN_ADMIN" -d "id=2" -d "rol=administrador" BASE/usuario_rol.php
curl -i -X POST -H "Authorization: Bearer TOKEN_ADMIN" -d "id=2" -d "accion=desactivar" BASE/usuario_estado.php
curl -i -X POST -H "Authorization: Bearer TOKEN_ADMIN" -d "id=2" -d "accion=reactivar" BASE/usuario_estado.php
```

Valores de `rol`: `administrador` o `estandar`. Valores de `accion`: `desactivar` o `reactivar`.

- Un usuario desactivado no inicia sesión y su sesión abierta deja de servir (probar con `yo.php`).
- Un Administrador no puede desactivarse a sí mismo: usa su propio `id` en `usuario_estado.php`.
- El listado nunca incluye hashes ni tokens.

### Contraseñas (RF-CA-09 a 13, 22)

Recuperación. La respuesta es idéntica exista o no el correo:

```
curl -i -X POST -d "correo=ana@ejemplo.com" BASE/recuperar.php
curl -i -X POST -d "correo=noexiste@ejemplo.com" BASE/recuperar.php
```

Ejecuta `php bin\enviar_correos.php` para que llegue el código. Úsalo para definir la contraseña nueva:

```
curl -i -X POST -d "codigo=CODIGO" -d "password=Nueva12345" BASE/restablecer.php
```

- Usar el código una segunda vez, o vencido, se rechaza y la contraseña no cambia.
- La contraseña vieja ya no inicia sesión; la nueva sí.
- Una credencial emitida antes del cambio se rechaza.

Restablecimiento forzado por un Administrador (el usuario recibe el código por la cola):

```
curl -i -X POST -H "Authorization: Bearer TOKEN_ADMIN" -d "id=2" BASE/usuario_reset.php
```

Cambio de contraseña con sesión. Con la actual incorrecta se rechaza:

```
curl -i -X POST -H "Authorization: Bearer TOKEN" -d "actual=Incorrecta1" -d "nueva=Nueva12345" BASE/cambiar_password.php
curl -i -X POST -H "Authorization: Bearer TOKEN" -d "actual=Clave1234" -d "nueva=Nueva12345" BASE/cambiar_password.php
```

### Correo por cola (RF-NOT-08, 09, 12, 13)

1. En `.env`, pon un `SMTP_HOST` que no responda.
2. Registra un usuario: la operación termina bien.
3. Comprueba que el correo quedó pendiente:

```
mysql -u root gestor_tareas -e "SELECT id, destinatario, estado FROM correos_en_cola"
```

4. Restaura el `SMTP_HOST` correcto y ejecuta el enviador dos veces. El correo se entrega una sola vez:

```
php bin\enviar_correos.php
php bin\enviar_correos.php
```

### Reinicio de la aplicación (RD-09)

Los usuarios viven en MySQL. Reinicia Apache y MySQL desde XAMPP y vuelve a listarlos.

## Máquina de estados de negocio (RF-NEG-03, 04, 05)

La entidad central es `tareas` (`database/tareas.sql`). Los estados, las transiciones permitidas, las prohibidas y el estado terminal están declarados en un solo lugar: `src/estados_tarea.php`. La tabla de transiciones está en `docs/maquina-de-estados.md`.
