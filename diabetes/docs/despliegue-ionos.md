# Despliegue en IONOS

## Estado y prerrequisitos

El instalador web existe y su flujo se probó en una copia temporal PHP/MariaDB, pero **no se ha probado en IONOS**. Antes de proceder, valida para el contrato concreto las versiones PHP disponibles, extensiones requeridas, versión/servidor de base de datos, permisos de archivos, document root, límites del alojamiento y configuración HTTPS. No inventes credenciales, nombres de host ni rutas: consulta los valores en la cuenta IONOS del organizador. Este corte está en desarrollo y no se declara apto para producción.

## Preparar la cuenta

1. Confirma con el responsable el dominio/subdominio y si el contrato permite que el document root apunte a `public/`. La única carpeta pública debe ser `public/`; el código restante y secretos han de quedar fuera del acceso web.
2. Verifica que la versión PHP asignada satisface los requisitos del proyecto y que están disponibles PDO y `pdo_mysql`. Confirma versiones y compatibilidad de MariaDB con IONOS; no se ha validado el contrato concreto.
3. Crea una base vacía y usuario exclusivo desde las herramientas de la cuenta. Copia el host, nombre, usuario y contraseña tal como aparecen allí y consérvalos de forma privada. No asumas que el host es `localhost`.
4. Configura HTTPS antes de abrir el sitio públicamente. Determina la ruta absoluta/document root y permisos desde la cuenta, no desde un ejemplo externo. Limita permisos para escribir `.env` y el lock privados durante la instalación.

## Instalación nueva

1. Sube un paquete limpio sin `.env` local, backups ni credenciales; coloca los archivos no públicos fuera del document root.
2. Abre `https://<dominio>/install.php` solo cuando el dominio ya sirva `public/` por HTTPS y el entorno haya sido validado. El instalador comprueba conexión y requiere base sin tablas; no actualiza una instalación existente.
3. Introduce únicamente credenciales reales de esa cuenta y configura la cuenta administradora. No se proporcionan aquí valores de ejemplo que puedan confundirse con credenciales.
4. La instalación crea esquema, 36 preguntas, bundles FINDRISC/MEDAS, categorías TSI starter `001–006` y campaña en preparación con fechas `null`. Las categorías starter deben confirmarse con el organizador. En prueba temporal se guardaron `.env` y lock privados y una segunda ejecución fue rechazada; comprueba esos permisos y comportamiento en IONOS.
5. Tras completar, retira `public/install.php` o deniega su acceso de forma verificable y reduce permisos de escritura. Comprueba que secretos y directorios internos no sean servidos por HTTP. Revisa panel, roles, auditoría y flujo de importación con fixtures ficticios.

Si el instalador falla tras comenzar, no lo reintentes sobre una base parcialmente creada: inspecciona el estado con personal técnico. En una prueba desechable puede ser más seguro crear una base nueva vacía; no borres datos que deban conservarse.

## Actualizaciones y operación

El instalador no es actualizador. Para una base existente, detén escrituras, respalda base y configuración, identifica versión de origen y aplica solo las instrucciones/migraciones de su changelog. Verifica backup y restauración en entorno separado antes de depender del sistema; la restauración aún no se ha probado.

Exige HTTPS, secretos fuera del área pública, permisos mínimos, cuentas individuales y copias protegidas. No se han realizado pruebas IONOS, pentest, prueba de carga ni auditoría RGPD/DPD; no se han usado datos sanitarios reales. No afirmes que pseudonimización equivale a anonimización o consentimiento.

## Pendientes del organizador

Antes de activar: confirmar fechas definitivas, listado de farmacias y categorías TSI starter `001–006`; aclarar unidad de glucosa, intervalos de tabaquismo y sentido de «Paciente masculino» en diabetes gestacional. No cambiar preguntas ni puntajes aprobados sin autorización. Las fechas permanecen `null` hasta definirse.
