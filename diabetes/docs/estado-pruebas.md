# Estado de pruebas y requisitos

Actualizado: 5 de noviembre de 2026. Corte `0.3.0-dev`.

## Verificaciones ejecutadas

- PHP 8.3 disponible; lint de todos los archivos PHP pasó.
- `tests/scoring_test.php` pasó.
- Instalador web probado en copia temporal mediante CSRF: sembró 36 preguntas, dos bundles FINDRISC/MEDAS, categorías TSI y campaña en preparación; guardó `.env` y lock privados; rechazó una segunda ejecución.
- App conectada a base MariaDB de ensayo: login/panel y vistas administrativas recorridas.
- Edición de pregunta con límites, min/max y severidades persistió; el tipo TSI no pudo cambiarse.
- Editor de reglas generó interfaz tabular, guardó defaults redondeados sin cambiar inclusividad, corrigió el bug de máximo exclusivo e impidió huecos.
- MEDAS: 14 ítems con puntuación fija de 1 por ítem.
- Importación CSV: archivo con errores/duplicado previsualizado sin confirmación; CSV válido de dos filas confirmado.
- Activación de campaña ficticia completó harness y auditó resultados: FINDRISC calculado, diabetes actual `not_applicable`, MEDAS 14/14 y hash SHA256 de versión. POST a `rules-save` tras activar fue rechazado; el estado siguió `active`.

Usuarios, filas CSV y datos de la base fueron fixtures temporales, sin pacientes reales. Los resultados acreditan solo los recorridos enumerados, no una validación integral.

## No probado / no concluido

No se ha probado instalación en XAMPP ni IONOS; tampoco pentest, prueba de carga, auditoría RGPD/DPD, restauración de backups o flujos con datos sanitarios reales. No se declara aptitud para producción. HTTPS, secretos fuera de `public/`, permisos mínimos y backups son requisitos de despliegue a verificar; su cumplimiento no se presume.

## Pendientes funcionales y de organización

- Fechas de campaña definitivas; actualmente `null` hasta que se definan.
- Listado oficial de farmacias del organizador.
- Contrato IONOS, versiones PHP/base de datos, extensiones, permisos y document root: verificar en la cuenta concreta.
- Confirmar categorías TSI starter `001–006` con su fuente responsable.
- Aclarar con el organizador: unidad de glucosa; intervalos de tabaquismo; sentido de la opción «Paciente masculino» en diabetes gestacional.

No modificar preguntas ni puntajes aprobados mientras esas aclaraciones estén pendientes. No atribuir equivalencia a consentimiento o anonimización.
