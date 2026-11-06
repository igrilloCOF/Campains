# Campaña Diabetes — aplicación PHP/MariaDB

Aplicación web en desarrollo para la preparación y operación de campañas, con paneles por rol, cuestionarios y puntuación FINDRISC/MEDAS. Versión documental: `0.3.0-dev`. PHP 8.3 está disponible y pasó el lint de todos los archivos PHP; pasó `tests/scoring_test.php`. El instalador web y diversos flujos de panel se probaron con una base MariaDB de ensayo. Esto no certifica compatibilidad XAMPP/IONOS ni aptitud para producción.

## Capacidades verificadas en este corte

La instalación nueva usa `public/install.php`: en una copia temporal protegida por CSRF creó 36 preguntas, bundles FINDRISC/MEDAS, categorías TSI iniciales, campaña en preparación y secretos/lock privados; una segunda ejecución fue rechazada. El panel por rol permite gestionar campañas, preguntas configurables, reglas guiadas, categorías TSI, farmacias/importación CSV con previsualización y confirmación, cuentas/reset, registros e incidencias, estadísticas/exportación y auditoría. Se probaron login y vistas administrativas; edición de preguntas y reglas, validación de límites y huecos, e importación de CSV inválido (sin confirmación) y válido (dos filas confirmadas).

La activación de una campaña de prueba completó el harness y auditó los resultados; una vez activa, se rechazó el guardado de reglas y el estado se mantuvo activo. El bloqueo configuracional al activar está comprobado en ese flujo. La campaña conserva fechas `null` hasta que se definan. El identificador de farmacia visible combina SOE y número correlativo pseudónimo; no representa anonimización.

## Instalación

Para una base nueva, configura `public/` como document root y sigue [instalación de desarrollo](docs/instalacion-desarrollo.md) o [instalación XAMPP](docs/instalacion-xampp.md). Tras completar la instalación, retira o bloquea el instalador siguiendo el procedimiento del manual. IONOS aún requiere validar el contrato y las versiones PHP/base de datos concretas; consulta [despliegue IONOS](docs/despliegue-ionos.md). No inventes credenciales ni rutas: usa los valores de la cuenta real.

## Asuntos pendientes y límites

Pendientes: fechas definitivas; listado de farmacias proporcionado por el organizador; detalles y compatibilidad del contrato IONOS; aclarar con el organizador unidad de glucosa, intervalos de tabaquismo y sentido de «Paciente masculino» en diabetes gestacional. No alterar preguntas o puntajes aprobados sin autorización. Confirmar los nombres/categorías TSI starter `001–006` con la fuente responsable. No se han probado XAMPP ni IONOS, pentest, auditoría RGPD/DPD, restauración de copias, carga ni flujos con datos sanitarios reales. Las cuentas, CSV y filas de base usados fueron fixtures temporales, sin pacientes reales.

Este corte no se declara apto para producción. Antes de cualquier operación real se requieren validaciones técnicas, operativas y de protección de datos apropiadas. No se afirma equivalencia entre identificador pseudónimo, consentimiento y anonimización.

## Documentación

- [Manual de administración](docs/manual-administrador.md)
- [Manual de farmacia](docs/manual-farmacia.md)
- [Estado de pruebas](docs/estado-pruebas.md)
- [Criterios aprobados](docs/criterios-aprobados.md)
- [Cambios 0.3.0-dev](docs/cambios-0.3.0-dev.md)
