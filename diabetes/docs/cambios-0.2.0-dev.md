# Cambios del corte 0.2.0-dev

Corte anterior: `0.1.0-dev`, conservado en `releases/diabetes-campaign-app-v0.1.0.zip`. SHA-256 del archivo conservado: `35e1d7c71f8c6b3df7fd591488449d7efdf0c958b8636061024512694ab56d32`.

## Correcciones de esta fase

El servicio runtime `QuestionnaireService` consulta las preguntas desde MariaDB por campaña y versión. Los JSON de `config/` quedan como plantillas de instalación. Se añadió `campaign_config_versions`, y preguntas, reglas y registros se relacionan con una versión compuesta campaña/versión. El sembrado inicial crea la versión, calcula la huella SHA-256 de la plantilla y luego inserta las preguntas y reglas.

El esquema añade estado de invalidación lógica, incidencias y revisiones conservadas. No se proporciona una ruta de borrado físico ni se implementa aún una interfaz para invalidar. El historial de corrección captura el código anterior, las respuestas y cálculos anteriores antes de guardar la revisión corregida.

El cambio obligatorio de contraseña ya tiene una ruta funcional, mínimo de 14 caracteres y auditoría. Se añadió un límite básico de cinco intentos fallidos por cuenta/IP en quince minutos, guardando claves HMAC. Cada petición autenticada vuelve a verificar en MariaDB que la cuenta y, para farmacia, la farmacia asociada siguen habilitadas.

Las validaciones numéricas bloquean formatos incorrectos y valores negativos, permiten el cero y admiten umbrales por pregunta con tres niveles: bloqueo, advertencia y observación. No hay umbrales clínicos configurados por defecto. Las advertencias y observaciones se muestran y, al guardar, se registran como incidencias abiertas.

Las pruebas de puntuación ya utilizan casos separados con resultados esperados explícitos. Se corrige el caso de cintura masculina exactamente 102 cm y se cubren fronteras de edad, IMC, cintura masculina/femenina, diabetes actual y puntos de corte MEDAS.

## Archivos modificados

`.env.example`, `README.md`, `app/Core/Security.php`, `app/Services/AuthService.php`, `app/Services/QuestionnaireService.php`, `app/Services/ValidationException.php`, `bin/seed-campaign.php`, `database/schema.sql`, `docs/estado-pruebas.md`, `docs/instalacion-desarrollo.md`, `public/index.php` y `tests/scoring_test.php`.

## Archivos nuevos

`VERSION`, `database/migrations/002_configuration_history_security.sql` y `docs/cambios-0.2.0-dev.md`.

## Migración

Para actualizar una base del primer corte, realizar una copia de seguridad completa y ejecutar una sola vez `database/migrations/002_configuration_history_security.sql`. Los DDL de MariaDB pueden producir commits implícitos; ante un fallo, inspeccionar qué pasos sí se aplicaron antes de reintentar. Para una instalación nueva, importar `database/schema.sql` actualizado.

## Límites vigentes

El panel administrativo integral está pendiente: gestión visual de campañas/versiones, edición de cuestionario y reglas, validación antes de activar, revisión de incidencias, invalidación, importación revisada de farmacias, exportaciones y diccionario. La inmutabilidad frente a edición directa de la base de datos no está garantizada por triggers en este corte; la protección del runtime y futuros permisos de base de datos deben someterse a pruebas.

Este entorno no contiene PHP CLI ni servidor MariaDB. No se ejecutaron PHP lint, pruebas PHP, comandos CLI, importación de esquema, migración SQL ni pruebas HTTP/integración. Esta versión no se declara apta para producción ni para datos sanitarios reales.
