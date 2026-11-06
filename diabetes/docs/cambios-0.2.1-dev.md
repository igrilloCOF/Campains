# Cambios del corte 0.2.1-dev

Base de trabajo: `0.2.0-dev`, conservada en `releases/diabetes-campaign-app-v0.2.0-dev.zip` (SHA-256 `747c57b4976c8390e4ac7b5001b023c9590f98e9551df1f3cf5be98fd1edb1bc`). El corte anterior no se ha rehecho.

## Cambios de esta fase

La farmacia ve e introduce solamente su número correlativo positivo. El servidor obtiene el SOE desde la farmacia asociada a la sesión, comprueba el formato `OR-XXX-F` con tres dígitos y construye el identificador mostrado como `SOE-número` (por ejemplo, `OR-201-F-1`). La base sigue guardando solo el número canónico en `patient_records.patient_code` (los ceros iniciales se normalizan y cero no es válido); la unicidad existente por campaña/farmacia/número permite repetir números en otras farmacias o campañas. No se añadieron campos de nombre, apellidos, DNI ni una relación entre campañas.

Los dos tipos de nota quedan persistidos en `record_incidents`: `validation_warning` y `validation_observation`. Las observaciones no se convierten en errores y no bloquean el registro. También se registran sus campos en la auditoría.

Al procesar cualquier petición, el servidor cambia a `closed` las campañas `active` cuya fecha final ya pasó y registra el cambio automático en `audit_log`. La ruta de escritura comprueba además el estado y las fechas en MariaDB dentro de la transacción; si vence entre comprobaciones, se rechaza el guardado y se intenta persistir el cierre.

Se añadió `record_calculations.is_current`. Antes de recalcular durante una corrección, los resultados vigentes anteriores pasan a `is_current=0`; los nuevos quedan vigentes por defecto. No se borran ni sobrescriben los cálculos históricos. El listado de farmacia consulta solo `is_current=1` y el histórico continúa seleccionable con `is_current=0`.

No se cambiaron reglas de FINDRISC/MEDAS ni las preguntas pendientes sobre unidad de glucosa, intervalos de tabaco o relación entre sexo y diabetes gestacional.

## Archivos modificados

`VERSION`, `README.md`, `database/schema.sql`, `public/index.php`, `docs/instalacion-desarrollo.md` y `docs/estado-pruebas.md`.

## Archivos nuevos

`database/migrations/003_current_calculation_marker.sql` y `docs/cambios-0.2.1-dev.md`.

## Actualización de MariaDB

Desde v0.2.0-dev, respaldar la base y ejecutar una sola vez `database/migrations/003_current_calculation_marker.sql`. Marca como vigente el cálculo de mayor `id` por registro y escala, y conserva los demás como históricos. Para una instalación nueva, usar `database/schema.sql` actualizado. Desde v0.1.0-dev se deben ejecutar en orden las migraciones `002` y `003`.

## Pruebas y límites

Se ejecutaron comprobaciones estáticas de patrones de SOE/número, unicidad compuesta, persistencia de observaciones, cierre por fecha y consultas de puntuación vigente. No hay PHP CLI ni servidor MariaDB en este entorno: no se ejecutaron `php -l`, las pruebas PHP, la migración ni escrituras/lecturas reales de MariaDB. Esas verificaciones deben hacerse en XAMPP/MariaDB antes de usar datos reales.
