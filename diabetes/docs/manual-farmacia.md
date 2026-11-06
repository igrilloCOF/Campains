# Manual de farmacia

**Versión `0.3.0-dev`, en desarrollo; no declarada apta para producción.** La importación CSV y los flujos de panel descritos se probaron en una instancia de ensayo con datos ficticios. No se han probado XAMPP/IONOS ni el uso con datos sanitarios reales.

## Acceso y campaña

Inicia sesión con la cuenta que te asigne administración; no compartas credenciales y usa el mecanismo de cambio/reset de contraseña disponible. El panel presenta funciones según el rol. La farmacia debe usar únicamente las campañas y fechas que administración haya confirmado. Las fechas de campaña actuales siguen `null` hasta que el organizador defina las fechas definitivas.

## Farmacias e importación de registros

El flujo de farmacia permite importar CSV con previsualización y confirmación. Antes de confirmar, revisa la previsualización, errores y posibles duplicados. En la prueba, un CSV con errores/duplicado no se confirmó y uno válido con dos filas sí. La prueba empleó fixtures, no demuestra compatibilidad con todos los formatos del organizador; solicita su formato oficial antes de importar datos operativos.

El identificador se basa en el SOE de farmacia y un número correlativo pseudónimo. No introduzcas nombres, apellidos, DNI u otros identificadores directos salvo que una instrucción formal aprobada establezca otra cosa. Este identificador no hace anónimos los datos. No se debe inferir que el sistema implementa consentimiento ni que la seudonimización reemplaza otras salvaguardas.

## Preguntas y puntuaciones

No modifiques respuestas aprobadas ni infieras datos que no conozcas. FINDRISC y MEDAS siguen los criterios aprobados; MEDAS se compone de 14 ítems con puntuación fija. En el flujo de activación ficticio se registró FINDRISC calculado, diabetes actual `not_applicable` y MEDAS 14/14. Los resultados son puntuaciones de cuestionario, no diagnóstico ni consejo médico.

Siguen pendientes de aclaración del organizador la unidad de glucosa, los intervalos de tabaquismo y el sentido de «Paciente masculino» en diabetes gestacional. No resuelvas estas dudas por cuenta propia ni las conviertas en cambios de cuestionario.

## Incidencias y soporte

El panel incluye registros e incidencias; si observas un error, duplicado o resultado inesperado, conserva la referencia necesaria según el procedimiento interno y comunícalo a administración. No intentes corregirlo mediante modificaciones improvisadas. Estadísticas/exportación y auditoría están disponibles para los roles autorizados; no exportes ni compartas información fuera del canal aprobado.

La prueba descrita no incluyó pacientes ni datos reales. No usar el sistema en operación real hasta completar las verificaciones pendientes y obtener las aprobaciones organizativas correspondientes. Este manual no constituye una declaración jurídica ni de anonimización.
