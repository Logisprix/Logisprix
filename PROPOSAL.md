# Propuesta de implantacion: Logisprix AI Sales Assistant

## Objetivo
Atender preguntas habituales sobre productos de almacenaje, reconocer solicitudes de presupuesto y recoger leads cualificados con consentimiento para que un comercial realice seguimiento.

## Alcance del MVP
- Widget insertable mediante shortcode WordPress.
- Respuestas OpenAI basadas en entradas, paginas y productos publicados recuperados mediante busqueda nativa.
- Derivacion a comercial ante consultas de presupuesto o informacion no verificada.
- Captura de nombre, empresa, correo y consulta con aceptacion de privacidad.
- Notificacion por email, panel de leads y exportacion CSV.
- Proteccion inicial: limites de peticiones por IP y honeypot.

## Siguiente fase (necesaria antes de produccion)
1. Indexacion incremental del sitio y documentos tecnicos, incluyendo metadatos de WooCommerce y busqueda semantica con citas.
2. Cualificacion estructurada de leads: tipo de estanteria, peso, medidas, ubicacion, plazo, presupuesto y nivel de interes.
3. Integracion CRM o email comercial con deduplicacion y asignacion.
4. Pruebas automatizadas y en entorno de staging: WordPress, PHP, WooCommerce, errores API, seguridad, carga y accesibilidad.
5. RGPD: informacion por capas, consentimiento independiente para marketing, retencion, exportacion y supresion de datos.
6. Monitorizacion de costes y uso, limites configurables y registro sin datos personales sensibles.

## Criterios de aceptacion
- No inventar capacidades de carga ni especificaciones tecnicas.
- Enviar solicitudes de presupuesto al formulario comercial.
- Registrar leads consentidos y avisar por correo al equipo.
- Permitir consultar y exportar leads solo a administradores.
- No desplegar automaticamente ni fusionar sin revision.

## Estado
Prototipo funcional de codigo (no validado en WordPress real). PR en borrador. No apto para produccion hasta completar los puntos anteriores.
