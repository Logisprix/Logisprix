# Logisprix AI Sales Assistant (MVP)
Plugin WordPress para responder consultas usando contenido publico del propio WordPress y recoger solicitudes comerciales.

## Instalacion
1. Copiar la carpeta `logisprix-ai-sales-assistant` a `wp-content/plugins/`.
2. Activar el plugin desde WordPress.
3. En **Logisprix AI**, configurar clave API OpenAI (preferiblemente definir `LOGISPRIX_OPENAI_API_KEY` en wp-config.php), modelo y correo comercial.
4. Insertar `[logisprix_ai_chat]` en una pagina.

## Funcionalidades
- Busqueda de entradas, paginas y productos WooCommerce publicados mediante busqueda WordPress, con contexto limitado para el modelo.
- Respuestas fundamentadas en fragmentos recuperados, y derivacion comercial para consultas de presupuesto o insuficientemente documentadas.
- Formulario de solicitud con nombre, empresa, email, consulta y consentimiento para gestionar la peticion.
- Tabla de leads en administracion y aviso por email.

## Limitaciones antes de produccion
- La busqueda WordPress no indexa necesariamente todos los metadatos, PDFs, fichas tecnicas o productos; implementar indexacion incremental y recuperacion semantica.
- Añadir captcha/honeypot, limites de uso robustos y proteccion antiabuso. El rate limit por IP actual es basico.
- Revisar politicas de privacidad, plazos de retencion, derecho de supresion/exportacion y acuerdo con proveedores conforme RGPD.
- Revisar accesibilidad, localizacion, pruebas automatizadas, permisos y seguridad, y gestion de errores.
- Validar manualmente las respuestas tecnicas con el equipo comercial antes de publicar.
- El formulario guarda emails aportados voluntariamente; NO extrae emails de visitantes ni incluye consentimiento para marketing.
- No hay despliegue automatico ni cambios en produccion.
