\# Bitácora, Asignación 2



Agente: Claude (conversación en claude.ai). Fecha: 20 de octubre de 2026.



\## Tarea 1: construir la pieza Gestión de permisos y su suite



\*\*Qué le pedí:\*\* Que me guiara paso a paso para hacer la Asignación 2 a partir del PDF de la asignación y mi README.



\*\*Qué me devolvió:\*\* Plan por fases, tabla `solicitudes\_permiso`, máquina de estados (`estados\_solicitud.php`), servicio `solicitudes.php` con el PDO por parámetro, 8 pruebas PHPUnit y 5 endpoints.



\### Error 1: columna inexistente en la prueba

\- \*\*Qué hizo mal:\*\* En su primer borrador, `crearUsuario` insertaba en `usuarios` una columna `activo`.

\- \*\*Cómo lo detecté:\*\* Al compartirle mi `schema.sql` se vio que mi tabla usa `estado ENUM('pendiente','activo','desactivado')`. Habría fallado con "Unknown column".

\- \*\*Cómo lo corregí:\*\* El INSERT pasó a usar `estado = 'activo'`. Resultado: `OK (12 tests, 24 assertions)`.



\### Error 2: sintaxis de cmd en PowerShell

\- \*\*Qué hizo mal:\*\* Me dio `mysql ... < archivo.sql`.

\- \*\*Cómo lo detecté:\*\* PowerShell respondió "The '<' operator is reserved for future use".

\- \*\*Cómo lo corregí:\*\* Usé `Get-Content archivo.sql | mysql ...`.



\### Error 3: ignoró que ya tenía Composer

\- \*\*Qué hizo mal:\*\* Me mandó descargar un `.phar` de PHPUnit sin saber que ya tenía `vendor/`.

\- \*\*Cómo lo detecté:\*\* Mi `git status` mostraba `composer.json`, `composer.lock` y `vendor/`.

\- \*\*Cómo lo corregí:\*\* Mantuve Composer y subí `vendor/` al repositorio para que el clon limpio funcione.



\### Error 4: PATH de mysql

\- \*\*Qué hizo mal:\*\* Dio por hecho que `mysql` estaba en el PATH.

\- \*\*Cómo lo detecté:\*\* PowerShell dijo "The term 'mysql' is not recognized".

\- \*\*Cómo lo corregí:\*\* `$env:Path += ";C:\\xampp\\mysql\\bin"`.



\### Error 5: endpoint que no llegó a existir

\- \*\*Qué pasó:\*\* `solicitud\_crear.php` no estaba en mi carpeta.

\- \*\*Cómo lo detecté:\*\* `php -l public\\solicitud\_crear.php` dijo "Could not open input file".

\- \*\*Cómo lo corregí:\*\* Creé el archivo con el código completo y volví a comprobar con `php -l`.

