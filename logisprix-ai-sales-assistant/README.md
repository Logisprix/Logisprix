# Logisprix AI Sales Assistant v0.4.1 (versión de pruebas)
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

## Novedades 0.4.1
- Conversaciones guardadas en base de datos y bandeja comercial en **Logisprix AI > Conversaciones en directo**.
- Actualización del panel cada 4 segundos, toma de control humana y devolución a la IA.
- Configuración de nombre, bienvenida y colores desde **Logisprix AI > Diseño del chat**. Valores iniciales: `#73B735` y `#333333`.
- Formulario de lead con consentimiento en el chat actualizado.
- Creación/actualización de tablas de conversaciones al activar o actualizar.

## Comprobaciones obligatorias en staging
1. Instalar únicamente la carpeta del plugin como ZIP y activar en `staging.logisprix.com`.
2. Comprobar que existen las tablas de leads, conversaciones y mensajes.
3. Configurar API y correo comercial de prueba, insertar `[logisprix_ai_chat]`.
4. Abrir una conversación como visitante y el panel comercial en otra sesión; comprobar mensajes, toma de control, retorno a IA y captación de lead.
5. Revisar los errores PHP y REST, permisos, accesibilidad, antiabuso y privacidad antes de producción.

**Estado:** no validado en una instalación real de WordPress. No desplegar en producción sin pruebas. El sondeo de visitantes es cada cuatro segundos y el control de concurrencia todavía requiere pruebas de carga. Las conversaciones almacenadas necesitan política de retención y borrado conforme al RGPD.
