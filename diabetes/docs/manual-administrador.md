# Manual de administración

**Versión `0.3.0-dev`, en desarrollo; no declarada apta para producción.** Los flujos citados se verificaron en una app de ensayo MariaDB con fixtures temporales, no con pacientes reales. No se han probado XAMPP/IONOS, seguridad mediante pentest, carga ni restauración.

## Acceso y alcance del panel

Accede con una cuenta de rol administrador. El panel por rol ofrece administración de campañas, preguntas configurables, reglas guiadas, categorías TSI, farmacias e importación CSV, cuentas y reset de contraseña, registros/incidencias, estadísticas/exportación y auditoría. Se probaron login y vistas de administración. Usa cuentas individuales y limita permisos según las necesidades operativas.

Las preguntas pueden editarse con límites, mínimos/máximos y severidades. TSI no puede cambiarse a otro tipo. El editor tabular de reglas conserva inclusividad y comprueba que no haya huecos; guarda defaults redondeados. MEDAS tiene 14 ítems y puntuación fija. No cambies preguntas ni puntajes aprobados por iniciativa propia.

## Preparar y activar campaña

Configura la campaña en preparación, revisa sus parámetros y las farmacias/cuentas autorizadas. La campaña de preparación creada por el instalador mantiene sus fechas en `null`; no la actives hasta que las fechas definitivas y el listado de farmacias estén confirmados por el organizador. Comprueba el catálogo TSI starter `001–006` con la fuente responsable.

El flujo de activación se probó en una campaña ficticia: dejó constancia de resultados FINDRISC calculado, diabetes actual `not_applicable`, MEDAS 14/14 y hash SHA256 de versión. Tras activar, el POST de guardado de reglas fue rechazado y el estado permaneció `active`. Considera la configuración bloqueada al activar y revisa cuidadosamente antes de hacerlo. No interpretes esta prueba acotada como prueba de todos los escenarios.

## Importación y gestión operativa

La importación CSV ofrece previsualización. En la prueba, el archivo con errores/duplicado no se confirmó; un CSV válido de dos filas sí se confirmó. Revisa siempre el resultado de previsualización y no confirmes datos con errores. La prueba no valida formatos de los listados reales del organizador.

El panel incluye registros e incidencias, estadísticas/exportación y auditoría. Mantén el acceso restringido y sigue el procedimiento interno al revisar o exportar. El identificador de farmacia se compone de SOE más número correlativo pseudónimo; no es anonimización ni sustituto de las medidas organizativas necesarias.

## Instalador y seguridad de despliegue

Para una base nueva, usa `public/install.php` conforme a [instalación de desarrollo](instalacion-desarrollo.md). La prueba temporal confirmó protección CSRF, creación de 36 preguntas, bundles FINDRISC/MEDAS, categorías TSI y campaña en preparación, almacenamiento privado de `.env`/lock y rechazo de segunda ejecución. Configura `public/` como document root, mantén secretos fuera del área pública y al terminar retira o bloquea el instalador según el procedimiento de despliegue. No uses una base ya ocupada como instalación nueva.

Usa HTTPS fuera de un entorno local, secretos no publicados, permisos mínimos y copias protegidas. Define y comprueba un proceso de backup y restauración antes de depender del sistema; la restauración aún no se ha probado. No des por cumplidas obligaciones de protección de datos por el mero funcionamiento técnico. No se ha hecho auditoría RGPD/DPD.

## Decisiones pendientes

Solicita aclaración al organizador antes de configurar o activar sobre: unidad de glucosa; intervalos de tabaquismo; sentido de «Paciente masculino» en diabetes gestacional. No alteres las preguntas/puntajes aprobados. Faltan las fechas definitivas, el listado de farmacias y confirmación del catálogo TSI starter `001–006`. No se debe afirmar que exista equivalencia entre consentimiento, anonimización e identificador pseudónimo.
