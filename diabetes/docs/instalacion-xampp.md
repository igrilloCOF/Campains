# Instalación en XAMPP

## Estado

La versión `0.3.0-dev` se probó en entorno PHP/MariaDB de ensayo, pero **no se ha probado en XAMPP** ni se certifica compatibilidad de una combinación de versiones concreta. PHP 8.3 estuvo disponible en el entorno de verificación; el lint PHP y la prueba de scoring pasaron. No usar con datos reales ni considerar apta para producción.

## Preparación

Comprueba en tu instalación Apache, PHP con PDO/`pdo_mysql` y MariaDB compatible. Crea una base vacía y usuario exclusivo con privilegios mínimos. Coloca el proyecto fuera del área pública y configura Apache con `public/` como document root. No expongas directorios de código, base de datos, herramientas ni archivos de entorno.

## Instalación de base nueva

1. Accede a `public/install.php` localmente mediante el servidor configurado y sigue el formulario. El instalador requiere una base vacía; no es una herramienta de actualización.
2. Usa las credenciales exactas de MariaDB y crea la cuenta administradora. Comprueba que el instalador guarde `.env` y el lock fuera del acceso web y con permisos restrictivos.
3. En la prueba temporal el instalador usó protección CSRF, creó 36 preguntas, bundles FINDRISC/MEDAS, categorías TSI starter y campaña en preparación; rechazó la segunda ejecución. Aun así, verifica el resultado en tu entorno.
4. Al terminar, retira `public/install.php` o deniega su acceso conforme al procedimiento local. Confirma `public/` como único document root, secretos no descargables y permisos mínimos.
5. Inicia sesión y verifica roles/capacidades con fixtures ficticios. No establezcas fechas ni actives la campaña mientras falten confirmaciones del organizador.

Usa HTTP solo para pruebas locales aisladas. Para cualquier acceso por red configura HTTPS y cookies seguras. Mantén secretos fuera del document root y realiza copias protegidas; prueba restauración en entorno aislado (pendiente de validación).

## Actualizaciones y límites

No ejecutes el instalador sobre una base con tablas ni importes el esquema inicial sobre una instalación existente. Para actualizaciones identifica origen, realiza backup y sigue las migraciones exactas del changelog; los DDL pueden confirmar cambios aunque una secuencia falle.

No se han probado XAMPP, restauración, carga, pentest ni datos sanitarios reales. Sigue pendientes: fechas definitivas, listado de farmacias, detalles IONOS, confirmación TSI `001–006` y aclaraciones del organizador sobre unidad de glucosa, intervalos de tabaquismo y opción «Paciente masculino» en diabetes gestacional. No alteres preguntas o puntuaciones aprobadas.
