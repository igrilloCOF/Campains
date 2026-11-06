# Cambios 0.3.0-dev

Corte documental y de capacidades verificadas: panel por rol e instalador web disponibles para pruebas; no es una declaración de aptitud para producción.

## Capacidades verificadas

- Instalador web de base nueva en copia temporal: protección CSRF; 36 preguntas, bundles FINDRISC/MEDAS, categorías TSI iniciales y campaña en preparación; `.env`/lock privados; segunda ejecución rechazada.
- Panel de administración por rol: login y vistas recorridas en MariaDB de ensayo; gestión de campañas, preguntas configurables, reglas guiadas, TSI, farmacias/importación con previsualización y confirmación, cuentas/reset, registros/incidencias, estadísticas/exportación y auditoría.
- Persistencia de edición de pregunta y límites/min-max/severidades; TSI no cambia de tipo. Editor de reglas tabular conserva inclusividad, guarda defaults redondeados, corrige el bug max-exclusive y evita huecos.
- Importación: archivo CSV con errores/duplicado no confirmado; CSV válido de dos filas confirmado.
- Activación ficticia auditó resultados FINDRISC calculado, diabetes actual `not_applicable`, MEDAS 14/14 y hash SHA256 de configuración; guardado de reglas posterior a activación fue rechazado y la campaña permaneció `active`.
- PHP 8.3 disponible; lint completo PHP y `tests/scoring_test.php` pasaron.

## Pendientes y límites

No probados: XAMPP, IONOS, pentest, auditoría RGPD/DPD, restauración, carga ni datos sanitarios reales. Todas las filas/usuarios usados fueron fixtures temporales. El sistema no se declara apto para producción.

Las fechas siguen `null` hasta que se definan. Faltan el listado de farmacias del organizador, detalles del contrato IONOS y confirmación de categorías TSI starter `001–006`. El organizador debe aclarar unidad de glucosa, intervalos de tabaquismo y sentido de «Paciente masculino» en diabetes gestacional. No modificar preguntas ni puntajes aprobados. El identificador SOE+número es pseudónimo; no afirmar que equivalga a anonimización o consentimiento.
