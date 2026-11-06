# Instalación de desarrollo

## Requisitos y estado

PHP 8.3 estuvo disponible en el entorno de verificación; la aplicación requiere PHP con PDO y `pdo_mysql`, Apache y MariaDB compatible. La compatibilidad concreta con XAMPP o IONOS no está verificada. Este corte (`0.3.0-dev`) no se declara apto para producción.

## Base nueva

1. Crea una base de datos MariaDB vacía y un usuario exclusivo con permisos mínimos necesarios.
2. Coloca el proyecto en una ubicación no pública y configura Apache para que `public/` sea el document root. No publiques `app/`, `config/`, `database/`, `bin/`, `tests/` ni secretos.
3. Abre `public/install.php` por HTTPS cuando no sea un entorno local. Sigue el formulario con los datos exactos del motor, establece la cuenta administradora y completa el asistente. El instalador crea esquema, preguntas, bundles FINDRISC/MEDAS, categorías TSI iniciales y una campaña en preparación sin fechas. En la prueba temporal protegida por CSRF guardó `.env` y lock privados, y rechazó una segunda ejecución.
4. Configura permisos mínimos para que la instalación escriba sus archivos privados. Después, retira o bloquea `public/install.php` siguiendo el procedimiento de despliegue, verifica que `.env`/lock no se sirven por HTTP y reduce permisos de escritura no necesarios.
5. Revisa la configuración de campaña en panel y usa solo fixtures ficticios. No establezcas fechas ni actives campaña hasta confirmar fechas, farmacias y decisiones pendientes con el organizador.

No vuelvas a ejecutar una instalación nueva sobre una base con tablas. Si falla tras crear tablas, no reintentes a ciegas; inspecciona la base y, en pruebas desechables, empieza con una base vacía nueva.

## Seguridad operativa

Usa HTTPS en entornos accesibles por red, secretos fuera del document root, permisos mínimos y copias protegidas de base/configuración. Establece una rutina de respaldo y verifica la restauración en entorno aislado: no se ha probado restauración. No inventes rutas/credenciales; obtén los valores del entorno anfitrión. El identificador SOE+número es pseudónimo, no anonimización. No se ha hecho auditoría RGPD/DPD ni pentest.

## Actualizar instalaciones existentes

No uses el instalador como actualizador. Antes de cualquier migración, respalda base y configuración, identifica la versión de origen y consulta el changelog correspondiente. No ejecutes el esquema inicial sobre una base existente; los DDL MariaDB pueden confirmar cambios automáticamente. Inspecciona el estado si una migración falla antes de cualquier reintento.

## Pruebas

Verificaciones de este corte: PHP 8.3 disponible; lint de todos los PHP correcto; `tests/scoring_test.php` pasó. Se probaron instalación web en copia temporal con rechazo a segunda ejecución, login/panel y vistas administrativas en MariaDB de ensayo, edición de preguntas y reglas, importación CSV, y bloqueo de guardado de reglas tras activar campaña ficticia. Los detalles están en [estado de pruebas](estado-pruebas.md).

No se probaron XAMPP, IONOS, pentest, auditoría RGPD/DPD, restauración, carga ni datos sanitarios. Las fixtures temporales no son pacientes reales. Las fechas de campaña siguen `null`; las categorías TSI starter `001–006` requieren confirmación. Aclarar con el organizador unidad de glucosa, intervalos de tabaquismo y «Paciente masculino» en diabetes gestacional. No cambiar cuestionario ni puntajes aprobados.
